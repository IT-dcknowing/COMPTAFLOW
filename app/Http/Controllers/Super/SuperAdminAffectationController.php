<?php

namespace App\Http\Controllers\Super;

use App\Http\Controllers\Controller;
use App\Models\Cabinet;
use App\Models\Company;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Rattacher des personnes à des comptabilités et à des cabinets.
 *
 * Deux rattachements, deux portées :
 *
 *   - une COMPTABILITÉ se donne nommément. Un collaborateur ne voit que les
 *     dossiers qu'on lui affecte, jamais tout le portefeuille du cabinet.
 *
 *   - un CABINET rattache la personne à la maison. Les dossiers qu'elle
 *     ouvrira porteront ce cabinet, et y resteront même si elle part : c'est
 *     ce qui empêche un départ d'emporter des comptabilités.
 *
 * L'adresse suffit : si personne ne la porte, le compte est créé.
 */
class SuperAdminAffectationController extends Controller
{
    public function index(Request $request)
    {
        $entreprises = Company::orderBy('company_name')->get(['id', 'company_name', 'cabinet_id']);
        $cabinets = Cabinet::with('gerant:id,name,last_name,email_adresse')->orderBy('nom')->get();

        // Qui est déjà rattaché à quoi : la page ne sert à rien sans cela.
        $parEntreprise = DB::table('company_user')
            ->join('users', 'company_user.user_id', '=', 'users.id')
            ->select('company_user.company_id', 'company_user.role', 'users.id', 'users.name',
                     'users.last_name', 'users.email_adresse')
            ->get()
            ->groupBy('company_id');

        // Le createur d'une comptabilite en est le premier utilisateur, sans
        // qu'aucune ligne de liaison ne l'y pose : la page l'ignorait, et un
        // dossier qu'on venait d'ouvrir semblait rattache a personne.
        $createurs = Company::whereNotNull('user_id')
            ->join('users', 'companies.user_id', '=', 'users.id')
            ->select('companies.id as company_id', 'users.id', 'users.name',
                     'users.last_name', 'users.email_adresse')
            ->get();

        foreach ($createurs as $createur) {
            $liste = $parEntreprise->get($createur->company_id, collect());

            if ($liste->contains(fn ($m) => (int) $m->id === (int) $createur->id)) {
                continue;
            }

            $createur->role = 'createur';
            $parEntreprise[$createur->company_id] = $liste->prepend($createur);
        }

        // Le role dans le cabinet se lit a l'ecran : sans lui, on ne savait pas
        // qui tient la maison et qui y travaille.
        $parCabinet = DB::table('cabinet_user')
            ->join('users', 'cabinet_user.user_id', '=', 'users.id')
            ->select('cabinet_user.cabinet_id', 'cabinet_user.role', 'users.id', 'users.name',
                     'users.last_name', 'users.email_adresse')
            ->get()
            ->groupBy('cabinet_id');

        $entrepriseChoisie = $request->integer('company_id') ?: null;
        $cabinetChoisi = $request->integer('cabinet_id') ?: null;

        // Les personnes deja connues : on les propose plutot que de faire
        // retaper une adresse, et de creer un doublon a la moindre faute.
        $personnes = User::whereNotNull('email_adresse')
            ->orderBy('name')
            ->get(['id', 'name', 'last_name', 'email_adresse'])
            ->map(fn ($u) => [
                'id' => $u->id,
                'email' => $u->email_adresse,
                'prenom' => (string) $u->name,
                'famille' => (string) $u->last_name,
            ])->values();

        // Les dossiers que porte chaque cabinet : choisir un cabinet doit
        // montrer ce qu'il gere, et permettre d'en donner l'acces d'un clic.
        $dossiersParCabinet = Company::whereNotNull('cabinet_id')
            ->orderBy('company_name')
            ->get(['id', 'company_name', 'cabinet_id'])
            ->groupBy('cabinet_id');

        return view('superadmin.affectations', compact(
            'entreprises', 'cabinets', 'parEntreprise', 'parCabinet',
            'entrepriseChoisie', 'cabinetChoisi', 'personnes', 'dossiersParCabinet'
        ));
    }

    /**
     * Rattache une personne à une comptabilité, en la créant au besoin.
     */
    public function affecterAUneComptabilite(Request $request)
    {
        $donnees = $request->validate([
            'company_id' => ['required', Rule::exists('companies', 'id')],
            'email_adresse' => ['required', 'email', 'max:191'],
            'role' => ['required', Rule::in(['admin', 'comptable'])],
            'name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
        ], [
            'email_adresse.email' => "Cette adresse n'est pas valide (exemple : jean@societe.com).",
        ]);

        $company = Company::findOrFail($donnees['company_id']);
        [$user, $cree] = $this->trouverOuCreer($donnees);

        $dejaLa = DB::table('company_user')
            ->where('company_id', $company->id)->where('user_id', $user->id)->exists();

        if ($dejaLa) {
            return back()->with('error', sprintf(
                '%s est déjà rattaché à « %s ».', $user->email_adresse, $company->company_name
            ));
        }

        DB::table('company_user')->insert([
            'company_id' => $company->id,
            'user_id' => $user->id,
            'role' => $donnees['role'],
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return back()->with('success', sprintf(
            '%s %s rattaché%s à « %s » en %s.%s',
            $user->email_adresse,
            $cree ? '(compte créé)' : '',
            '',
            $company->company_name,
            $donnees['role'] === 'admin' ? 'administrateur du dossier' : 'acces limite',
            $cree ? ' Un mot de passe provisoire lui a été attribué : il devra le changer.' : ''
        ));
    }

    /**
     * Retire une personne d'une comptabilité.
     */
    public function retirerDUneComptabilite(Request $request)
    {
        $donnees = $request->validate([
            'company_id' => ['required', Rule::exists('companies', 'id')],
            'user_id' => ['required', Rule::exists('users', 'id')],
        ]);

        DB::table('company_user')
            ->where('company_id', $donnees['company_id'])
            ->where('user_id', $donnees['user_id'])
            ->delete();

        // Le rattachement historique compte aussi : sans cela, la personne
        // continue de voir le dossier par users.company_id.
        User::where('id', $donnees['user_id'])
            ->where('company_id', $donnees['company_id'])
            ->update(['company_id' => null]);

        return back()->with('success', "L'accès a été retiré.");
    }

    /**
     * Rattache une personne à un cabinet, en la créant au besoin.
     */
    public function affecterAUnCabinet(Request $request)
    {
        $donnees = $request->validate([
            'cabinet_id' => ['required', Rule::exists('cabinets', 'id')],
            'email_adresse' => ['required', 'email', 'max:191'],
            'name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
        ]);

        $cabinet = Cabinet::findOrFail($donnees['cabinet_id']);
        [$user, $cree] = $this->trouverOuCreer($donnees);

        $dejaLa = DB::table('cabinet_user')
            ->where('cabinet_id', $cabinet->id)->where('user_id', $user->id)->exists();

        if ($dejaLa) {
            return back()->with('error', sprintf(
                '%s appartient déjà au cabinet « %s ».', $user->email_adresse, $cabinet->nom
            ));
        }

        DB::table('cabinet_user')->insert([
            'cabinet_id' => $cabinet->id,
            'user_id' => $user->id,
            'role' => 'collaborateur',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Les dossiers que cette personne tenait déjà rejoignent le cabinet.
        //
        // Sans cela, ils resteraient un portefeuille personnel : elle garde
        // ses accès, mais le jour où elle part, le cabinet n'aurait aucun
        // titre sur eux. On ne touche qu'aux dossiers SANS cabinet — un
        // dossier déjà rangé ailleurs ne se déplace pas par un rattachement.
        $siens = Company::whereNull('cabinet_id')
            ->where(fn ($q) => $q->where('user_id', $user->id)
                ->orWhereIn('id', DB::table('company_user')->where('user_id', $user->id)->pluck('company_id')))
            ->pluck('id');

        if ($siens->isNotEmpty()) {
            Company::whereIn('id', $siens)->update(['cabinet_id' => $cabinet->id]);
        }

        return back()->with('success', sprintf(
            '%s rattaché au cabinet « %s ».%s%s Ses accès aux comptabilités se donnent dossier par dossier.',
            $user->email_adresse, $cabinet->nom,
            $cree ? ' Compte créé, avec un mot de passe provisoire à changer.' : '',
            $siens->isNotEmpty()
                ? sprintf(" %d comptabilité(s) qu'il tenait déjà rejoignent le cabinet — il les garde,"
                    . " et elles y resteront même s'il part.", $siens->count())
                : " Les dossiers qu'il ouvrira resteront au cabinet, même s'il le quitte."
        ));
    }

    /**
     * Retire une personne d'un cabinet.
     *
     * Les dossiers, eux, restent au cabinet : c'est tout l'intérêt du
     * rattachement.
     */
    public function retirerDUnCabinet(Request $request)
    {
        $donnees = $request->validate([
            'cabinet_id' => ['required', Rule::exists('cabinets', 'id')],
            'user_id' => ['required', Rule::exists('users', 'id')],
        ]);

        DB::table('cabinet_user')
            ->where('cabinet_id', $donnees['cabinet_id'])
            ->where('user_id', $donnees['user_id'])
            ->delete();

        return back()->with('success',
            'La personne ne fait plus partie du cabinet. Les dossiers qu\'elle avait ouverts y restent.');
    }

    /**
     * Cree une personne, et rien d'autre.
     *
     * Creer un compte ne donne acces a aucune comptabilite : les acces se
     * donnent ensuite, dossier par dossier. C'est pour cela qu'aucune
     * habilitation n'est demandee ici.
     */
    public function creerUnePersonne(Request $request)
    {
        $donnees = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'email_adresse' => ['required', 'email', 'max:191', Rule::unique('users', 'email_adresse')],
            'password' => ['required', 'string', 'min:8'],
        ], [
            'email_adresse.unique' => 'Cette adresse est deja utilisee : choisissez-la dans la liste.',
            'password.min' => 'Le mot de passe doit faire au moins 8 caracteres.',
        ]);

        $user = User::create([
            'name' => $donnees['name'],
            'last_name' => $donnees['last_name'],
            'email_adresse' => $donnees['email_adresse'],
            'password' => Hash::make($donnees['password']),
            // Aucun role : la personne n'est liee a aucune comptabilite, elle
            // ne peut donc rien faire, et le dire est plus juste que de lui
            // coller un titre. Des qu'elle ouvre un dossier, elle en est
            // l'administratrice.
            'role' => null,
            'is_active' => true,
        ]);

        return back()->with('success', sprintf(
            "%s %s (%s) a ete cree, sans aucun role ni habilitation. "
            . "Il n'a encore acces a aucune comptabilite : "
            . "donnez-lui les dossiers un par un ci-dessus.",
            $user->name, $user->last_name, $user->email_adresse
        ));
    }

    /**
     * Remplace l'adresse d'une personne.
     */
    public function changerLAdresse(Request $request)
    {
        $donnees = $request->validate([
            'user_id' => ['required', Rule::exists('users', 'id')],
            'nouvelle_adresse' => ['required', 'email', 'max:191'],
        ]);

        $user = User::findOrFail($donnees['user_id']);

        $prise = User::where('email_adresse', $donnees['nouvelle_adresse'])
            ->where('id', '!=', $user->id)->exists();

        if ($prise) {
            return back()->with('error', "Cette adresse est deja celle d'un autre compte.");
        }

        $ancienne = $user->email_adresse;
        $user->forceFill(['email_adresse' => $donnees['nouvelle_adresse']])->save();

        return back()->with('success', sprintf(
            "L'adresse de %s est passee de %s a %s. Ses acces ne changent pas.",
            trim($user->name . ' ' . $user->last_name), $ancienne, $user->email_adresse
        ));
    }

    /**
     * Donne a une personne l'acces a plusieurs dossiers d'un cabinet.
     *
     * Le role y est toujours administrateur : il s'agit de confier la tenue
     * du dossier, pas d'y ouvrir une fenetre.
     */
    public function affecterDesDossiersDuCabinet(Request $request)
    {
        $donnees = $request->validate([
            'cabinet_id' => ['required', Rule::exists('cabinets', 'id')],
            'user_id' => ['required', Rule::exists('users', 'id')],
            'dossiers' => ['required', 'array', 'min:1'],
            'dossiers.*' => [Rule::exists('companies', 'id')],
        ], [
            'dossiers.required' => 'Choisissez au moins un dossier.',
        ]);

        // On ne donne que des dossiers du cabinet annonce : un identifiant
        // glisse dans le formulaire ne doit pas ouvrir un dossier etranger.
        $duCabinet = Company::where('cabinet_id', $donnees['cabinet_id'])
            ->whereIn('id', $donnees['dossiers'])->pluck('id');

        $deja = DB::table('company_user')->where('user_id', $donnees['user_id'])
            ->whereIn('company_id', $duCabinet)->pluck('company_id');

        $aPoser = $duCabinet->diff($deja);

        foreach ($aPoser as $companyId) {
            DB::table('company_user')->insert([
                'company_id' => $companyId,
                'user_id' => $donnees['user_id'],
                'role' => 'admin',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return back()->with('success', sprintf(
            '%d dossier(s) confie(s) en administrateur.%s',
            $aPoser->count(),
            $deja->count() ? sprintf(' %d etai(en)t deja accorde(s).', $deja->count()) : ''
        ));
    }

    /**
     * Le compte derrière une adresse, créé s'il n'existe pas.
     *
     * @return array{0: User, 1: bool}  le compte, et s'il vient d'être créé
     */
    private function trouverOuCreer(array $donnees): array
    {
        $user = User::where('email_adresse', $donnees['email_adresse'])->first();

        if ($user) {
            return [$user, false];
        }

        // Un mot de passe provisoire, jamais affiché : la personne passe par
        // « mot de passe oublié ». Le montrer à l'écran le ferait circuler.
        // Les champs facultatifs peuvent manquer : la validation ne rend que
        // ce qui a ete envoye.
        $user = User::create([
            'name' => ($donnees['name'] ?? null) ?: Str::before($donnees['email_adresse'], '@'),
            'last_name' => ($donnees['last_name'] ?? null) ?: '',
            'email_adresse' => $donnees['email_adresse'],
            'password' => Hash::make(Str::random(32)),
            // Le role demande sur le formulaire porte sur la COMPTABILITE, pas
            // sur le compte : le recopier ici donnait un titre global a
            // quelqu'un qu'on venait seulement de rattacher a un dossier.
            'role' => null,
            'is_active' => true,
        ]);

        return [$user, true];
    }
}
