<?php

namespace App\Console\Commands;

use App\Models\CodeJournal;
use App\Models\Company;
use App\Models\EcritureComptable;
use App\Services\DecoupageDesPieces;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Où des pièces partagent-elles un même numéro de saisie, et avec quel effet ?
 *
 * Ne modifie rien. Sert à répondre à trois questions :
 *   - quels dossiers sont touchés ?
 *   - quels journaux, à l'intérieur d'un dossier ?
 *   - le tableau des flux de trésorerie est-il concerné ?
 *
 * Ce dernier point est le seul qui compte pour la justesse des états : la
 * balance, le grand livre, le compte de résultat et le bilan additionnent par
 * compte et ignorent le numéro de saisie. Le tableau des flux, lui, raisonne
 * par pièce — un numéro partagé y compense encaissements et décaissements.
 * Seules les pièces qui portent un compte de trésorerie (classe 5) l'affectent.
 */
class DiagnostiquerNumerosSaisie extends Command
{
    protected $signature = 'saisies:diagnostic-numeros
                            {--company= : Ne diagnostiquer que cette entreprise}
                            {--journaux : Détailler journal par journal}';

    protected $description = "Recense les numéros de saisie partagés par plusieurs pièces, et leur effet sur les états";

    public function handle(): int
    {
        DB::connection()->disableQueryLog();

        $this->line('Recherche des numéros couvrant plusieurs pièces…');
        $depart = microtime(true);

        $candidats = EcritureComptable::query()
            ->when($this->option('company'), fn ($q) => $q->where('company_id', $this->option('company')))
            ->where('n_saisie', 'like', 'ECR%')
            ->select('company_id', 'n_saisie')
            ->groupBy('company_id', 'n_saisie')
            ->havingRaw("COUNT(DISTINCT CONCAT_WS('|', date, code_journal_id)) > 1 OR COUNT(*) > 2")
            ->get();

        $this->line(sprintf('Balayage terminé en %.1f s : %d numéro(s) à examiner.',
            microtime(true) - $depart, $candidats->count()));

        if ($candidats->isEmpty()) {
            $this->info('Aucun numéro de saisie partagé : rien à signaler.');
            return self::SUCCESS;
        }

        $noms = Company::pluck('company_name', 'id');
        $journaux = CodeJournal::pluck('code_journal', 'id');

        // [company_id][code_journal_id] => compteurs
        $bilan = [];

        foreach ($candidats->groupBy('company_id') as $companyId => $numeros) {
            foreach ($numeros as $candidat) {
                $lignes = EcritureComptable::where('company_id', $companyId)
                    ->where('n_saisie', $candidat->n_saisie)
                    ->with('planComptable:id,numero_de_compte')
                    ->get(['id', 'date', 'code_journal_id', 'debit', 'credit', 'plan_comptable_id']);

                $pieces = DecoupageDesPieces::decouper($lignes);

                if ($pieces->count() < 2) {
                    continue;   // numéro sain
                }

                // Le journal de la première ligne : c'est celui sous lequel
                // l'utilisateur verra le problème dans l'application.
                $journalId = $lignes->first()->code_journal_id;

                $tresorerie = $lignes->contains(fn ($e) => $e->planComptable
                    && str_starts_with($e->planComptable->numero_de_compte, '5'));

                $case = &$bilan[$companyId][$journalId];
                $case['numeros'] = ($case['numeros'] ?? 0) + 1;
                $case['pieces'] = ($case['pieces'] ?? 0) + $pieces->count();
                $case['lignes'] = ($case['lignes'] ?? 0) + $lignes->count();
                $case['tresorerie'] = ($case['tresorerie'] ?? 0) + ($tresorerie ? 1 : 0);
                unset($case);
            }
        }

        if ($bilan === []) {
            $this->info('Aucun numéro ne couvre réellement plusieurs pièces : rien à signaler.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->line('Un numéro « partagé » désigne plusieurs pièces à la fois.');
        $this->line('Colonne « trésorerie » : numéros portant un compte de classe 5,');
        $this->line('les seuls à fausser le tableau des flux de trésorerie.');

        $totalNumeros = 0;
        $totalTreso = 0;

        foreach ($bilan as $companyId => $parJournal) {
            $numeros = array_sum(array_column($parJournal, 'numeros'));
            $pieces = array_sum(array_column($parJournal, 'pieces'));
            $lignes = array_sum(array_column($parJournal, 'lignes'));
            $treso = array_sum(array_column($parJournal, 'tresorerie'));

            $totalNumeros += $numeros;
            $totalTreso += $treso;

            $this->newLine();
            $this->line(sprintf('Entreprise %-4s %-42s %4d numéro(s), %5d pièce(s), %6d ligne(s), %4d en trésorerie',
                $companyId, mb_strimwidth($noms[$companyId] ?? '?', 0, 42, '…'),
                $numeros, $pieces, $lignes, $treso));

            if ($treso > 0) {
                $this->warn('    → tableau des flux de trésorerie faussé pour ce dossier.');
            } else {
                $this->line('    → aucun compte de trésorerie concerné : tous les états restent justes.');
            }

            if (!$this->option('journaux')) {
                continue;
            }

            arsort($parJournal);
            foreach ($parJournal as $journalId => $c) {
                $this->line(sprintf('      %-8s %4d numéro(s), %5d pièce(s), %6d ligne(s)%s',
                    $journaux[$journalId] ?? ('#' . $journalId),
                    $c['numeros'], $c['pieces'], $c['lignes'],
                    $c['tresorerie'] > 0 ? sprintf('   (%d en trésorerie)', $c['tresorerie']) : ''));
            }
        }

        $this->newLine();
        $this->info("$totalNumeros numéro(s) partagé(s) au total, dont $totalTreso touchant la trésorerie.");
        $this->line('Aucune écriture n\'a été modifiée : ce diagnostic ne fait que lire.');

        return self::SUCCESS;
    }
}
