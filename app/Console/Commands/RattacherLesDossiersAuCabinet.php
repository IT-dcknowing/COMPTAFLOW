<?php

namespace App\Console\Commands;

use App\Models\Cabinet;
use App\Models\Company;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Range les comptabilités sous le cabinet de celui qui les a ouvertes.
 *
 * Un dossier ouvert par un collaborateur appartient au cabinet, pas à la
 * personne. Mais `companies.cabinet_id` n'est renseigné que depuis que la
 * fonction cabinet existe : tout ce qui a été créé avant est resté sans
 * rattachement, et apparaît comme un portefeuille personnel.
 *
 * La commande suit la seule piste fiable : QUI a créé le dossier. Si cette
 * personne appartient à un cabinet — qu'elle le gère ou qu'elle en soit
 * membre — le dossier y retourne.
 *
 * `--cabinet=` rattache en plus les dossiers dont le créateur n'appartient à
 * aucun cabinet. C'est le cas quand une seule maison existe et qu'elle tient
 * tout : on le dit alors explicitement, plutôt que de le deviner.
 *
 * Rien n'est écrit sans --appliquer, et un dossier déjà rattaché n'est jamais
 * déplacé.
 */
class RattacherLesDossiersAuCabinet extends Command
{
    protected $signature = 'cabinets:rattacher-dossiers
                            {--cabinet= : Rattacher aussi les dossiers orphelins à ce cabinet (identifiant ou nom)}
                            {--forcer : Déplacer aussi les dossiers déjà rattachés ailleurs}
                            {--lister : Afficher les cabinets connus, et s\'arrêter là}
                            {--appliquer : Enregistre les rattachements (sans cette option, simple simulation)}';

    protected $description = "Rattache chaque comptabilité au cabinet de la personne qui l'a créée";

    private function listerLesCabinets(): void
    {
        $this->line('Les cabinets connus, avec ce qu\'ils portent :');
        $this->newLine();

        $dossiers = Company::whereNotNull('cabinet_id')
            ->selectRaw('cabinet_id, COUNT(*) as n')->groupBy('cabinet_id')->pluck('n', 'cabinet_id');

        foreach (Cabinet::orderBy('nom')->get(['id', 'nom', 'user_id']) as $c) {
            $gerant = User::find($c->user_id);

            $this->line(sprintf('    %-5s %-38s %4d dossier(s)   gérant : %s',
                $c->id,
                mb_strimwidth($c->nom, 0, 38, '…'),
                $dossiers[$c->id] ?? 0,
                $gerant ? trim($gerant->name . ' ' . $gerant->last_name) : 'aucun'));
        }
    }

    /**
     * Un cabinet désigné par son identifiant ou par son nom.
     *
     * Un identifiant d'ENTREPRISE ressemble à un identifiant de cabinet : on
     * le dit, plutôt que de rattacher au hasard.
     */
    private function trouverLeCabinet(string $designation): ?Cabinet
    {
        if (ctype_digit($designation)) {
            if ($cabinet = Cabinet::find($designation)) {
                return $cabinet;
            }
        } else {
            // Le nom exact d'abord. « DC-KNOWING » cherché au hasard
            // ramènerait aussi « Cabinet IT dc knowing » : ranger tous les
            // dossiers d'une maison sous une autre ne se rattrape pas.
            if ($exact = Cabinet::where('nom', $designation)->first()) {
                return $exact;
            }

            $approchants = Cabinet::where('nom', 'like', '%' . $designation . '%')->get(['id', 'nom']);

            if ($approchants->count() === 1) {
                return Cabinet::find($approchants->first()->id);
            }

            if ($approchants->count() > 1) {
                $this->error('Plusieurs cabinets portent « ' . $designation . " ». Désignez-en un par son identifiant :");
                foreach ($approchants as $a) {
                    $this->line(sprintf('    %-5s %s', $a->id, $a->nom));
                }
                return null;
            }
        }

        if (ctype_digit($designation) && $entreprise = Company::find($designation)) {
            $this->error(sprintf(
                'Aucun cabinet n\'a l\'identifiant %s — mais c\'est celui de l\'ENTREPRISE « %s ».',
                $designation, $entreprise->company_name
            ));
            $this->line('Les cabinets ont leurs propres identifiants. Voici les vôtres :');
        } else {
            $this->error('Aucun cabinet ne correspond à « ' . $designation . ' ».');
        }

        $this->newLine();
        $this->listerLesCabinets();

        return null;
    }

    public function handle(): int
    {
        $appliquer = (bool) $this->option('appliquer');

        DB::connection()->disableQueryLog();

        if ($this->option('lister')) {
            $this->listerLesCabinets();
            return self::SUCCESS;
        }

        $cabinetParDefaut = null;

        if ($this->option('cabinet')) {
            $cabinetParDefaut = $this->trouverLeCabinet((string) $this->option('cabinet'));

            if (!$cabinetParDefaut) {
                return self::FAILURE;
            }
        }

        if (!$appliquer) {
            $this->warn('Mode simulation : rien ne sera modifié. Ajoutez --appliquer pour enregistrer.');
        }

        // Le cabinet de chaque personne : celui qu'elle gère d'abord, sinon
        // celui dont elle est membre.
        $gere = Cabinet::pluck('id', 'user_id');
        $membre = DB::table('cabinet_user')->pluck('cabinet_id', 'user_id');

        $nomsCabinets = Cabinet::pluck('nom', 'id');

        $sansRattachement = Company::query()
            ->when(!$this->option('forcer'), fn ($q) => $q->whereNull('cabinet_id'))
            ->orderBy('company_name')
            ->get(['id', 'company_name', 'user_id', 'cabinet_id']);

        if ($sansRattachement->isEmpty()) {
            $this->info('Toutes les comptabilités sont déjà rattachées à un cabinet.');
            return self::SUCCESS;
        }

        $this->line($sansRattachement->count() . ' comptabilité(s) à examiner'
            . ($this->option('forcer') ? ' (dossiers déjà rattachés compris).' : ' sans cabinet.'));

        $parCabinet = [];
        $orphelins = [];

        foreach ($sansRattachement as $company) {
            // --forcer veut dire : tout ranger sous le cabinet demandé, quel
            // que soit le créateur et le rattachement actuel.
            if ($this->option('forcer') && $cabinetParDefaut) {
                if ((int) $company->cabinet_id === (int) $cabinetParDefaut->id) {
                    continue;
                }

                $parCabinet[$cabinetParDefaut->id][] = [
                    'dossier' => $company,
                    'motif' => $company->cabinet_id ? 'déplacé sur demande' : 'rattachement demandé',
                ];
                continue;
            }

            $cabinetId = $gere[$company->user_id] ?? $membre[$company->user_id] ?? null;
            $motif = isset($gere[$company->user_id]) ? 'son créateur gère ce cabinet' : 'son créateur en est membre';

            if (!$cabinetId && $cabinetParDefaut) {
                $cabinetId = $cabinetParDefaut->id;
                $motif = 'rattachement demandé en ligne de commande';
            }

            if (!$cabinetId) {
                $orphelins[] = $company;
                continue;
            }

            $parCabinet[$cabinetId][] = ['dossier' => $company, 'motif' => $motif];
        }

        foreach ($parCabinet as $cabinetId => $dossiers) {
            $this->newLine();
            $this->line(sprintf('%s — %d dossier(s)', $nomsCabinets[$cabinetId] ?? ('#' . $cabinetId), count($dossiers)));

            foreach ($dossiers as $d) {
                $createur = User::find($d['dossier']->user_id);

                $this->line(sprintf('      %-38s  créé par %-24s  (%s)',
                    mb_strimwidth($d['dossier']->company_name, 0, 38, '…'),
                    mb_strimwidth($createur ? trim($createur->name . ' ' . $createur->last_name) : 'inconnu', 0, 24, '…'),
                    $d['motif']));
            }

            if ($appliquer) {
                Company::whereIn('id', collect($dossiers)->pluck('dossier.id'))
                    ->update(['cabinet_id' => $cabinetId]);
            }
        }

        if ($orphelins !== []) {
            $this->newLine();
            $this->warn(count($orphelins) . ' dossier(s) laissé(s) de côté : leur créateur n\'appartient à aucun cabinet.');

            foreach (array_slice($orphelins, 0, 15) as $company) {
                $createur = User::find($company->user_id);
                $this->line(sprintf('      %-38s  créé par %s',
                    mb_strimwidth($company->company_name, 0, 38, '…'),
                    $createur ? trim($createur->name . ' ' . $createur->last_name) : 'inconnu'));
            }

            if (count($orphelins) > 15) {
                $this->line(sprintf('      … et %d autre(s).', count($orphelins) - 15));
            }

            $this->line('Pour les rattacher malgré tout : --cabinet=<identifiant>');
        }

        $rattaches = collect($parCabinet)->flatten(1)->count();

        $this->newLine();
        $verbe = $appliquer ? 'rattaché(s)' : 'à rattacher';
        $this->info("$rattaches dossier(s) $verbe.");

        if (!$appliquer) {
            $this->line('Relancez avec --appliquer pour enregistrer.');
        } else {
            $this->line('Les dossiers restent au cabinet même si leur créateur le quitte.');
        }

        return self::SUCCESS;
    }
}
