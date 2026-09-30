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
                            {--cabinet= : Rattacher aussi les dossiers orphelins à ce cabinet}
                            {--appliquer : Enregistre les rattachements (sans cette option, simple simulation)}';

    protected $description = "Rattache chaque comptabilité au cabinet de la personne qui l'a créée";

    public function handle(): int
    {
        $appliquer = (bool) $this->option('appliquer');

        DB::connection()->disableQueryLog();

        $cabinetParDefaut = null;

        if ($this->option('cabinet')) {
            $cabinetParDefaut = Cabinet::find($this->option('cabinet'));

            if (!$cabinetParDefaut) {
                $this->error('Ce cabinet n\'existe pas. Les cabinets connus :');
                foreach (Cabinet::orderBy('nom')->get(['id', 'nom']) as $c) {
                    $this->line(sprintf('    %-4s %s', $c->id, $c->nom));
                }
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

        $sansRattachement = Company::whereNull('cabinet_id')
            ->orderBy('company_name')
            ->get(['id', 'company_name', 'user_id']);

        if ($sansRattachement->isEmpty()) {
            $this->info('Toutes les comptabilités sont déjà rattachées à un cabinet.');
            return self::SUCCESS;
        }

        $this->line($sansRattachement->count() . ' comptabilité(s) sans cabinet.');

        $parCabinet = [];
        $orphelins = [];

        foreach ($sansRattachement as $company) {
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
