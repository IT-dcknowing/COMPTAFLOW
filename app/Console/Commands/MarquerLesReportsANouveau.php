<?php

namespace App\Console\Commands;

use App\Models\CodeJournal;
use App\Models\Company;
use App\Models\EcritureComptable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Remet le drapeau « report à nouveau » sur les écritures de clôture déjà
 * enregistrées.
 *
 * `is_ran` ne figurait pas dans les champs assignables du modèle : la clôture
 * le passait à true, Eloquent le jetait en silence, et les lignes de report
 * naissaient à false. Le tableau des flux les prenait alors pour de vrais
 * encaissements de janvier, et la trésorerie d'ouverture restait à zéro.
 *
 * Le modèle est corrigé ; cette commande reprend l'existant. Une écriture est
 * reconnue comme report à nouveau si elle réunit trois signes : son journal est
 * celui des reports (RAN ou REP), son numéro de saisie commence par RAN-, et
 * son libellé annonce un report. Deux suffisent — un dossier peut avoir
 * renommé son journal.
 *
 * Aucun montant n'est touché : seul le drapeau change. Par sécurité, rien
 * n'est écrit sans --appliquer.
 */
class MarquerLesReportsANouveau extends Command
{
    protected $signature = 'saisies:marquer-reports
                            {--company= : Ne traiter que cette entreprise}
                            {--appliquer : Enregistre le marquage (sans cette option, simple simulation)}';

    protected $description = "Remet le drapeau « report à nouveau » sur les écritures de clôture déjà enregistrées";

    public function handle(): int
    {
        $appliquer = (bool) $this->option('appliquer');

        DB::connection()->disableQueryLog();

        if (!$appliquer) {
            $this->warn('Mode simulation : aucune écriture ne sera modifiée. Ajoutez --appliquer pour enregistrer.');
        }

        $this->line('Recherche des reports à nouveau non marqués…');

        $journauxRan = CodeJournal::whereIn('code_journal', ['RAN', 'REP', 'RAN1', 'REP1'])
            ->pluck('id', 'id');

        $candidats = EcritureComptable::query()
            ->when($this->option('company'), fn ($q) => $q->where('company_id', $this->option('company')))
            ->where(fn ($q) => $q->whereNull('is_ran')->orWhere('is_ran', false))
            ->where(function ($q) use ($journauxRan) {
                $q->where('n_saisie', 'like', 'RAN-%')
                    ->orWhere('description_operation', 'like', 'REPORT%NOUVEAU%')
                    ->orWhere('reference_piece', 'like', 'RAN-%');

                if ($journauxRan->isNotEmpty()) {
                    $q->orWhereIn('code_journal_id', $journauxRan->all());
                }
            })
            ->get(['id', 'company_id', 'code_journal_id', 'n_saisie', 'description_operation', 'reference_piece', 'date']);

        if ($candidats->isEmpty()) {
            $this->info('Aucun report à nouveau à marquer : rien à faire.');
            return self::SUCCESS;
        }

        // Deux signes concordants suffisent : un dossier peut avoir renommé son
        // journal, ou saisi ses reports sous un autre numéro.
        $retenues = $candidats->filter(function ($e) use ($journauxRan) {
            $signes = 0;
            $signes += $journauxRan->has($e->code_journal_id) ? 1 : 0;
            $signes += str_starts_with((string) $e->n_saisie, 'RAN-') ? 1 : 0;
            $signes += str_starts_with((string) $e->reference_piece, 'RAN-') ? 1 : 0;
            $signes += stripos((string) $e->description_operation, 'report') !== false ? 1 : 0;

            return $signes >= 2;
        });

        $ecartees = $candidats->count() - $retenues->count();

        if ($retenues->isEmpty()) {
            $this->info('Aucune écriture ne réunit assez de signes : rien n\'a été marqué.');
            $this->line("$ecartees écriture(s) examinée(s) ne portaient qu'un seul signe.");
            return self::SUCCESS;
        }

        $noms = Company::whereIn('id', $retenues->pluck('company_id')->unique())
            ->pluck('company_name', 'id');

        foreach ($retenues->groupBy('company_id') as $companyId => $lignes) {
            $this->newLine();
            $this->line(sprintf('Entreprise %-4s %-38s %5d ligne(s)',
                $companyId, mb_strimwidth($noms[$companyId] ?? '?', 0, 38, '…'), $lignes->count()));

            foreach ($lignes->groupBy('n_saisie') as $numero => $duLot) {
                $this->line(sprintf('      %-22s %5d ligne(s)   %s',
                    $numero, $duLot->count(), $duLot->first()->date));
            }

            if ($appliquer) {
                EcritureComptable::whereIn('id', $lignes->pluck('id'))->update(['is_ran' => true]);
            }
        }

        $this->newLine();
        $verbe = $appliquer ? 'marquée(s)' : 'à marquer';
        $this->info($retenues->count() . " ligne(s) $verbe comme report à nouveau.");

        if ($ecartees > 0) {
            $this->line("$ecartees écriture(s) écartée(s) : un seul signe, insuffisant pour trancher.");
        }

        if ($appliquer) {
            $this->line('Le tableau des flux les compte désormais en trésorerie d\'ouverture,');
            $this->line('et non plus en encaissements de janvier.');
        } else {
            $this->line('Relancez avec --appliquer pour enregistrer.');
        }

        return self::SUCCESS;
    }
}
