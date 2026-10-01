<?php

namespace App\Services;

use App\Models\Cabinet;
use App\Models\Company;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Qui est rattaché à quoi — une seule réponse, pour tous les écrans.
 *
 * Quatre pages parlaient des mêmes personnes en lisant chacune une table
 * différente : « Gestion des utilisateurs » ne regardait que `users.company_id`
 * et affichait donc « N/A » pour quelqu'un qui venait d'ouvrir une
 * comptabilité ; « Gestion des entités » comptait ses utilisateurs sur la même
 * colonne et annonçait zéro ; « Mon espace » lisait, lui, les trois
 * rattachements. On pouvait ainsi voir quelqu'un n'avoir rien fait alors qu'il
 * tenait un dossier.
 *
 * Trois rattachements existent, et les trois comptent :
 *
 *   1. `companies.user_id` — celui qui a CRÉÉ la comptabilité. Il est le
 *      premier : c'est lui l'utilisateur du dossier, et son administrateur.
 *   2. `company_user` — les personnes à qui on a confié le dossier, avec le
 *      rôle qu'on leur a donné dessus (admin, ou habilitations cochées).
 *   3. `users.company_id` — le rattachement historique, posé par la gestion des
 *      utilisateurs avant que la table pivot n'existe.
 *
 * Le cabinet s'y ajoute : son gérant répond de tout le portefeuille.
 */
class Rattachements
{
    /** Rôle sur une comptabilité : son créateur en est l'administrateur. */
    public const CREATEUR = 'createur';
    public const ADMIN = 'admin';
    public const COLLABORATEUR = 'collaborateur';
    public const CABINET = 'cabinet';

    /**
     * Les identifiants des comptabilités de l'espace d'une personne.
     *
     * Une seule règle, pour la liste comme pour l'ouverture : un dossier
     * visible dans un espace doit pouvoir s'y ouvrir.
     *
     * @return array<int, int>
     */
    public static function comptabilitesDe($user): array
    {
        if (!$user || !$user->id) {
            return [];
        }

        $ids = Company::where('user_id', $user->id)->pluck('id')->toArray();

        $ids = array_merge($ids, DB::table('company_user')
            ->where('user_id', $user->id)->pluck('company_id')->toArray());

        // Le gérant voit tout ce que porte son cabinet, y compris les dossiers
        // qu'un collaborateur a ouverts de son côté : ils reviennent au cabinet.
        $cabinetGere = Cabinet::where('user_id', $user->id)->first();
        if ($cabinetGere) {
            $ids = array_merge($ids, Company::where('cabinet_id', $cabinetGere->id)
                ->pluck('id')->toArray());
        }

        // Un collaborateur du cabinet ne voit QUE les dossiers qu'on lui a
        // confiés : seul le gérant voit l'ensemble, par la branche ci-dessus.

        if ($user->company_id) {
            $ids[] = (int) $user->company_id;

            $ids = array_merge($ids, Company::where('parent_company_id', $user->company_id)
                ->pluck('id')->toArray());
        }

        return array_values(array_unique(array_map('intval', array_filter($ids))));
    }

    /**
     * Les personnes rattachées à une comptabilité, créateur compris.
     *
     * @return Collection<int, User>
     */
    public static function personnesDe(Company $company): Collection
    {
        $ids = collect([$company->user_id])
            ->merge(DB::table('company_user')->where('company_id', $company->id)->pluck('user_id'))
            ->merge(User::where('company_id', $company->id)->pluck('id'))
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique();

        if ($ids->isEmpty()) {
            return collect();
        }

        return User::whereIn('id', $ids)->orderBy('last_name')->orderBy('name')->get();
    }

    /**
     * Les personnes de plusieurs comptabilités d'un coup.
     *
     * La page des entités en liste des dizaines : les interroger une par une
     * ferait des centaines de requêtes pour un seul écran. Trois suffisent.
     *
     * @param  iterable<int, Company>  $dossiers
     * @return array<int, Collection<int, User>>
     */
    public static function personnesDePlusieurs(iterable $dossiers): array
    {
        $ids = collect($dossiers)->pluck('id')->map(fn ($id) => (int) $id)->unique();

        if ($ids->isEmpty()) {
            return [];
        }

        $createurs = Company::whereIn('id', $ids)->whereNotNull('user_id')
            ->pluck('user_id', 'id');

        $confies = DB::table('company_user')->whereIn('company_id', $ids)
            ->get(['company_id', 'user_id'])->groupBy('company_id');

        $historiques = User::whereIn('company_id', $ids)
            ->get(['id', 'company_id'])->groupBy('company_id');

        $comptes = User::whereIn('id', collect($createurs->values())
            ->merge($confies->flatten()->pluck('user_id'))
            ->merge($historiques->flatten()->pluck('id'))
            ->filter()->unique())
            ->get()->keyBy('id');

        $parDossier = [];

        foreach ($ids as $id) {
            $personnes = collect([$createurs[$id] ?? null])
                ->merge(($confies[$id] ?? collect())->pluck('user_id'))
                ->merge(($historiques[$id] ?? collect())->pluck('id'))
                ->filter()
                ->map(fn ($u) => (int) $u)
                ->unique()
                ->map(fn ($u) => $comptes[$u] ?? null)
                ->filter()
                ->sortBy(fn ($u) => mb_strtolower(trim($u->last_name . ' ' . $u->name)))
                ->values();

            $parDossier[$id] = $personnes;
        }

        return $parDossier;
    }

    /**
     * Le rôle d'une personne SUR une comptabilité, ou null si elle n'y est pas.
     *
     * Le créateur passe en premier : son rôle ne dépend d'aucune ligne de
     * liaison, il tient le dossier parce qu'il l'a ouvert.
     */
    public static function roleSur(Company $company, $user): ?string
    {
        if (!$user || !$user->id) {
            return null;
        }

        if ((int) $company->user_id === (int) $user->id) {
            return self::CREATEUR;
        }

        $pivot = DB::table('company_user')
            ->where('company_id', $company->id)->where('user_id', $user->id)
            ->value('role');

        if ($pivot) {
            return $pivot === 'admin' ? self::ADMIN : self::COLLABORATEUR;
        }

        if ((int) $user->company_id === (int) $company->id) {
            return self::COLLABORATEUR;
        }

        if ($company->cabinet_id && Cabinet::where('id', $company->cabinet_id)
            ->where('user_id', $user->id)->exists()) {
            return self::CABINET;
        }

        return null;
    }

    /**
     * Ce qu'une personne est, en une ligne, d'après ce qu'elle tient vraiment.
     *
     * Le rôle enregistré sur le compte ne suffit pas à décrire quelqu'un : un
     * compte sans rôle peut très bien administrer la comptabilité qu'il a
     * ouverte. On dit donc ce qui se constate.
     */
    public static function libelleDuRole($user): string
    {
        if (!$user) {
            return 'Aucun rôle';
        }

        if ($user->role === 'super_admin') {
            return 'Super administrateur';
        }

        if ($user->role === 'admin') {
            return 'Administrateur';
        }

        $tenues = Company::where('user_id', $user->id)->count()
            + DB::table('company_user')->where('user_id', $user->id)
                ->where('role', 'admin')->count();

        if ($tenues > 0) {
            return 'Administrateur de ses comptabilités';
        }

        if (self::comptabilitesDe($user) !== []) {
            return 'Sur habilitations';
        }

        // Personne n'est « rien ». Quelqu'un sans comptabilité est un
        // collaborateur : il appartient à la maison, et l'accès aux dossiers ne
        // lui est pas donné d'office. Il deviendra administrateur du premier
        // dossier qu'il ouvrira.
        return 'Collaborateur';
    }

    /**
     * Les noms des comptabilités d'une personne, pour l'affichage.
     *
     * @return Collection<int, string>
     */
    public static function nomsDesComptabilitesDe($user): Collection
    {
        $ids = self::comptabilitesDe($user);

        return $ids === []
            ? collect()
            : Company::whereIn('id', $ids)->orderBy('company_name')->pluck('company_name', 'id');
    }
}
