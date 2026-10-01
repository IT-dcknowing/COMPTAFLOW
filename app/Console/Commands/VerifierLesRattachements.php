<?php

namespace App\Console\Commands;

use App\Models\Cabinet;
use App\Models\Company;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Les rattachements disent-ils tous la même chose ?
 *
 * Quatre écrans parlent des mêmes personnes sans lire la même table :
 *
 *   - « Gestion des utilisateurs » lit `users` ;
 *   - « Switch » et « Gestion des entités » listent les personnes d'une
 *     COMPTABILITÉ : `users.company_id`, la table `company_user`, et le
 *     créateur du dossier ;
 *   - « Affectations » liste les membres d'un CABINET : la table `cabinet_user`.
 *
 * Une même personne peut donc figurer ici et pas là, en toute logique — mais
 * il arrive aussi que les tables aient divergé pour de bon. Trois cas :
 *
 *   1. un cabinet dont le gérant a été supprimé : il reste un identifiant qui
 *      ne désigne personne, et l'écran affiche « gérant : aucun » ;
 *   2. des lignes de `cabinet_user` qui pointent vers une personne ou un
 *      cabinet effacé — invisibles partout, mais bien présentes ;
 *   3. quelqu'un qui tient une comptabilité du cabinet sans appartenir au
 *      cabinet : il travaille pour la maison sans y figurer.
 *
 * Sans --appliquer, la commande se contente de dire ce qu'elle voit.
 */
class VerifierLesRattachements extends Command
{
    protected $signature = 'cabinets:verifier
                            {--appliquer : Répare ce qui peut l\'être (sans cette option, simple constat)}';

    protected $description = "Confronte les rattachements cabinet / comptabilité / personne, et répare ce qui a divergé";

    public function handle(): int
    {
        $appliquer = (bool) $this->option('appliquer');

        DB::connection()->disableQueryLog();

        if (!$appliquer) {
            $this->warn('Simple constat : rien ne sera modifié. Ajoutez --appliquer pour réparer.');
        }

        $personnes = User::pluck('id')->flip();
        $cabinets = Cabinet::pluck('nom', 'id');

        $soucis = 0;
        $soucis += $this->gerantsDisparus($personnes, $appliquer);
        $soucis += $this->lignesOrphelines($personnes, $cabinets, $appliquer);
        $soucis += $this->tiennentSansAppartenir($cabinets, $appliquer);

        $this->newLine();

        if ($soucis === 0) {
            $this->info('Les quatre écrans lisent des données cohérentes : rien à signaler.');
            return self::SUCCESS;
        }

        $this->warn("$soucis incohérence(s) relevée(s).");

        if (!$appliquer) {
            $this->line('Relancez avec --appliquer pour réparer.');
        }

        return self::SUCCESS;
    }

    /**
     * 1. Un cabinet dont le gérant n'existe plus.
     */
    private function gerantsDisparus($personnes, bool $appliquer): int
    {
        $sansGerant = Cabinet::get(['id', 'nom', 'user_id'])
            ->filter(fn ($c) => !$personnes->has($c->user_id));

        if ($sansGerant->isEmpty()) {
            return 0;
        }

        $this->newLine();
        $this->warn($sansGerant->count() . ' cabinet(s) dont le gérant a été supprimé :');

        foreach ($sansGerant as $c) {
            // Le plus ancien membre reprend la maison. Sans membre, on le dit :
            // un cabinet sans gérant ne se devine pas.
            $successeur = DB::table('cabinet_user')
                ->where('cabinet_id', $c->id)
                ->whereIn('user_id', $personnes->keys())
                ->orderBy('id')
                ->value('user_id');

            $repreneur = $successeur ? User::find($successeur) : null;

            $this->line(sprintf('    %-5s %-38s  %s',
                $c->id,
                mb_strimwidth($c->nom, 0, 38, '…'),
                $repreneur
                    ? 'repris par ' . trim($repreneur->name . ' ' . $repreneur->last_name)
                    : 'aucun membre pour le reprendre'));

            if ($appliquer && $repreneur) {
                Cabinet::where('id', $c->id)->update(['user_id' => $repreneur->id]);
                DB::table('cabinet_user')
                    ->where('cabinet_id', $c->id)->where('user_id', $repreneur->id)
                    ->update(['role' => 'gerant', 'updated_at' => now()]);
            }
        }

        return $sansGerant->count();
    }

    /**
     * 2. Des lignes d'appartenance qui ne désignent plus rien.
     */
    private function lignesOrphelines($personnes, $cabinets, bool $appliquer): int
    {
        $lignes = DB::table('cabinet_user')->get(['id', 'cabinet_id', 'user_id']);

        $perdues = $lignes->filter(fn ($l) => !$personnes->has($l->user_id) || !$cabinets->has($l->cabinet_id));

        if ($perdues->isEmpty()) {
            return 0;
        }

        $this->newLine();
        $this->warn($perdues->count() . ' ligne(s) d\'appartenance ne désignent plus personne :');
        $this->line('    Elles sont invisibles à l\'écran, mais comptent encore en base.');

        if ($appliquer) {
            DB::table('cabinet_user')->whereIn('id', $perdues->pluck('id'))->delete();
        }

        return $perdues->count();
    }

    /**
     * 3. Tenir une comptabilité du cabinet sans appartenir au cabinet.
     */
    private function tiennentSansAppartenir($cabinets, bool $appliquer): int
    {
        $dossiers = Company::whereNotNull('cabinet_id')->pluck('cabinet_id', 'id');

        if ($dossiers->isEmpty()) {
            return 0;
        }

        $acces = DB::table('company_user')
            ->whereIn('company_id', $dossiers->keys())
            ->get(['company_id', 'user_id']);

        $membres = DB::table('cabinet_user')->get(['cabinet_id', 'user_id'])
            ->groupBy('cabinet_id')
            ->map(fn ($g) => $g->pluck('user_id')->flip());

        $manquants = [];

        foreach ($acces as $a) {
            $cabinetId = $dossiers[$a->company_id];

            if (!$cabinets->has($cabinetId)) {
                continue;
            }

            if (!isset($membres[$cabinetId]) || !$membres[$cabinetId]->has($a->user_id)) {
                $manquants[$cabinetId][$a->user_id] = true;
            }
        }

        if ($manquants === []) {
            return 0;
        }

        $total = collect($manquants)->map(fn ($m) => count($m))->sum();

        $this->newLine();
        $this->warn("$total personne(s) tiennent une comptabilité d'un cabinet sans y appartenir :");

        foreach ($manquants as $cabinetId => $utilisateurs) {
            $this->line('    ' . ($cabinets[$cabinetId] ?? ('#' . $cabinetId)));

            foreach (array_keys($utilisateurs) as $userId) {
                $u = User::find($userId);

                if (!$u) {
                    continue;
                }

                $this->line(sprintf('        %-30s %s',
                    mb_strimwidth(trim($u->name . ' ' . $u->last_name), 0, 30, '…'),
                    $u->email_adresse));

                if ($appliquer) {
                    DB::table('cabinet_user')->insert([
                        'cabinet_id' => $cabinetId,
                        'user_id' => $userId,
                        'role' => 'collaborateur',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
        }

        return $total;
    }
}
