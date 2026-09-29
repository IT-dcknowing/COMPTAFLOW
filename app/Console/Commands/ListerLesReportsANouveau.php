<?php

namespace App\Console\Commands;

use App\Models\CodeJournal;
use App\Models\Company;
use App\Models\EcritureComptable;
use App\Models\ExerciceComptable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Quelles comptabilités ont déjà passé un report à nouveau, et dans quel état ?
 *
 * Deux défauts de la clôture ont laissé des traces qu'il faut pouvoir repérer
 * avant de décider quoi reprendre :
 *
 *   - le drapeau `is_ran` n'était pas assignable, et partait en silence. Les
 *     lignes de report existent mais ne se disent pas reports : le tableau des
 *     flux les compte alors en encaissements de janvier.
 *
 *   - le résultat était ajouté aux soldes reportés avec son propre signe. Or
 *     ces soldes sont algébriques et leur somme vaut déjà le résultat : un
 *     bénéfice partait au débit, et le report se retrouvait déséquilibré du
 *     double.
 *
 * Cette commande ne modifie rien. Elle dit, dossier par dossier, ce qui a été
 * reporté, si c'est équilibré, et si le drapeau est posé.
 */
class ListerLesReportsANouveau extends Command
{
    protected $signature = 'saisies:reports-existants
                            {--company= : Ne regarder que cette entreprise}
                            {--desequilibres : N\'afficher que les reports qui ne tombent pas juste}';

    protected $description = "Recense les comptabilités ayant déjà passé un report à nouveau, et l'état de chacun";

    /** Tolérance d'arrondi, en unités monétaires. */
    private const TOLERANCE = 0.01;

    public function handle(): int
    {
        DB::connection()->disableQueryLog();

        $this->line('Recherche des reports à nouveau déjà passés…');

        $journauxRan = CodeJournal::whereIn('code_journal', ['RAN', 'REP', 'RAN1', 'REP1'])
            ->pluck('id', 'id');

        $lignes = EcritureComptable::query()
            ->when($this->option('company'), fn ($q) => $q->where('company_id', $this->option('company')))
            ->where(function ($q) use ($journauxRan) {
                $q->where('is_ran', true)
                    ->orWhere('n_saisie', 'like', 'RAN-%')
                    ->orWhere('reference_piece', 'like', 'RAN-%')
                    ->orWhere('description_operation', 'like', 'REPORT%NOUVEAU%');

                if ($journauxRan->isNotEmpty()) {
                    $q->orWhereIn('code_journal_id', $journauxRan->all());
                }
            })
            ->get(['id', 'company_id', 'exercices_comptables_id', 'code_journal_id', 'n_saisie',
                   'reference_piece', 'description_operation', 'date', 'debit', 'credit', 'is_ran']);

        if ($lignes->isEmpty()) {
            $this->info('Aucune comptabilité n\'a encore passé de report à nouveau.');
            return self::SUCCESS;
        }

        // Deux signes concordants : un journal renommé reste reconnu, sans
        // qu'un « report de charges » passe pour un report à nouveau.
        $reports = $lignes->filter(function ($e) use ($journauxRan) {
            if ($e->is_ran) {
                return true;
            }

            $signes = 0;
            $signes += $journauxRan->has($e->code_journal_id) ? 1 : 0;
            $signes += str_starts_with((string) $e->n_saisie, 'RAN-') ? 1 : 0;
            $signes += str_starts_with((string) $e->reference_piece, 'RAN-') ? 1 : 0;
            $signes += stripos((string) $e->description_operation, 'report') !== false ? 1 : 0;

            return $signes >= 2;
        });

        if ($reports->isEmpty()) {
            $this->info('Aucune comptabilité n\'a encore passé de report à nouveau.');
            return self::SUCCESS;
        }

        $noms = Company::whereIn('id', $reports->pluck('company_id')->unique())
            ->pluck('company_name', 'id');
        $exercices = ExerciceComptable::whereIn('id', $reports->pluck('exercices_comptables_id')->unique())
            ->pluck('intitule', 'id');

        $this->newLine();
        $this->line('Colonnes : origine, date, lignes, total débit, total crédit, état.');
        $this->line('« clôture » : produit par la clôture de l\'exercice dans l\'application.');
        $this->line('« saisi »   : entré à la main ou arrivé par import — son écart, s\'il y en a,');
        $this->line('              ne vient pas du défaut de la clôture.');

        $dossiers = 0;
        $desequilibres = 0;
        $ecartsSaisis = 0;
        $nonMarques = 0;

        foreach ($reports->groupBy('company_id') as $companyId => $duDossier) {
            $aMontrer = [];

            foreach ($duDossier->groupBy(fn ($e) => $e->exercices_comptables_id . '|' . $e->n_saisie) as $cle => $duLot) {
                $debit = round($duLot->sum(fn ($e) => (float) $e->debit), 2);
                $credit = round($duLot->sum(fn ($e) => (float) $e->credit), 2);
                $ecart = round($debit - $credit, 2);
                $marque = $duLot->every(fn ($e) => (bool) $e->is_ran);

                if ($this->option('desequilibres') && abs($ecart) < self::TOLERANCE) {
                    continue;
                }

                [$exerciceId, $numero] = explode('|', $cle, 2);

                // La cloture signe ce qu'elle produit : numero « RAN-<annee> » ET
                // reference « RAN-<exercice> ». Un report saisi a la main ou
                // arrive par import n'a pas cette signature — et son ecart, s'il
                // en a un, ne vient pas du defaut de la cloture.
                $parLaCloture = str_starts_with($numero, 'RAN-')
                    && $duLot->every(fn ($e) => str_starts_with((string) $e->reference_piece, 'RAN-'));

                $aMontrer[] = [
                    'exercice' => $exercices[$exerciceId] ?? ('#' . $exerciceId),
                    'numero' => $numero,
                    'cloture' => $parLaCloture,
                    'date' => $duLot->first()->date,
                    'lignes' => $duLot->count(),
                    'debit' => $debit,
                    'credit' => $credit,
                    'ecart' => $ecart,
                    'marque' => $marque,
                ];

                $desequilibres += (abs($ecart) >= self::TOLERANCE && $parLaCloture) ? 1 : 0;
                $nonMarques += $marque ? 0 : 1;
            }

            if ($aMontrer === []) {
                continue;
            }

            $dossiers++;
            $this->newLine();
            $this->line(sprintf('Entreprise %-4s %s', $companyId,
                mb_strimwidth($noms[$companyId] ?? '?', 0, 40, '…')));

            foreach ($aMontrer as $r) {
                $ecartsSaisis += (abs($r['ecart']) >= self::TOLERANCE && !$r['cloture']) ? 1 : 0;

                $etat = [];
                if (abs($r['ecart']) >= self::TOLERANCE) {
                    $etat[] = 'ÉCART ' . number_format(abs($r['ecart']), 0, ',', ' ')
                        . ($r['ecart'] > 0 ? ' au débit' : ' au crédit');
                }
                if (!$r['marque']) {
                    $etat[] = 'non marqué';
                }

                $texte = sprintf('      %-14s %-22s %-9s %s  %4d lignes   D %14s   C %14s%s',
                    mb_strimwidth($r['exercice'], 0, 14, '…'),
                    $r['numero'],
                    $r['cloture'] ? 'clôture' : 'saisi',
                    \Carbon\Carbon::parse($r['date'])->format('d/m/Y'),
                    $r['lignes'],
                    number_format($r['debit'], 0, ',', ' '),
                    number_format($r['credit'], 0, ',', ' '),
                    $etat ? '   ← ' . implode(', ', $etat) : '   ✓');

                $etat ? $this->warn($texte) : $this->line($texte);
            }
        }

        $this->newLine();
        $this->info("$dossiers comptabilité(s) ont déjà passé un report à nouveau.");

        if ($nonMarques > 0) {
            $this->warn("$nonMarques report(s) ne portent pas le drapeau : le tableau des flux les compte");
            $this->warn("en encaissements de janvier au lieu de trésorerie d'ouverture.");
            $this->line('    php artisan saisies:marquer-reports --appliquer');
        }

        if ($desequilibres > 0) {
            $this->newLine();
            $this->warn("$desequilibres report(s) PRODUITS PAR LA CLÔTURE ne sont pas équilibrés.");
            $this->line("Pour ceux-là, l'écart vaut le double du résultat de l'exercice clôturé : le");
            $this->line("bénéfice partait au débit du compte de résultat au lieu du crédit.");
            $this->line('Ils sont à reprendre à la main — aucune commande ne réécrit un montant.');
        }

        $autresEcarts = $ecartsSaisis;
        if ($autresEcarts > 0) {
            $this->newLine();
            $this->line("$autresEcarts report(s) SAISIS OU IMPORTÉS ne sont pas équilibrés non plus,");
            $this->line("mais pour une autre raison : la clôture ne les a pas produits. Ce sont des");
            $this->line('écritures d\'ouverture incomplètes, à vérifier avec le bilan de clôture.');
        }

        $this->newLine();
        $this->line('Aucune écriture n\'a été modifiée : ce recensement ne fait que lire.');

        return self::SUCCESS;
    }
}
