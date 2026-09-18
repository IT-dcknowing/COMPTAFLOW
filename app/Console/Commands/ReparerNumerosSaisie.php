<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\EcritureComptable;
use App\Services\NumerotationSaisie;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Sépare les pièces qui partagent à tort un même numéro de saisie global.
 *
 * L'import universel écrivait ses lignes par paquets de mille et relisait la
 * base pour connaître le dernier numéro attribué : la base ignorant encore le
 * paquet en cours, toutes les pièces de l'import recevaient le même numéro.
 * Un journal entier pouvait ainsi rester figé sur ECR_000000000020.
 *
 * La génération est corrigée (voir NumerotationSaisie) ; cette commande répare
 * les écritures déjà enregistrées. Elle reconstitue les pièces d'origine en
 * regroupant les lignes par date, journal, numéro de saisie utilisateur et
 * référence de pièce — la clé même que l'import cherchait à respecter.
 *
 * Par sécurité elle ne modifie rien sans --appliquer.
 */
class ReparerNumerosSaisie extends Command
{
    protected $signature = 'saisies:reparer-numeros
                            {--company= : Ne traiter que cette entreprise}
                            {--appliquer : Enregistre les nouveaux numéros (sans cette option, simple simulation)}';

    protected $description = "Redonne un numéro distinct à chaque pièce lorsqu'un import les a toutes regroupées sous le même";

    public function handle(): int
    {
        $appliquer = (bool) $this->option('appliquer');

        if (!$appliquer) {
            $this->warn('Mode simulation : aucune écriture ne sera modifiée. Ajoutez --appliquer pour enregistrer.');
        }

        $entreprises = Company::query()
            ->when($this->option('company'), fn ($q) => $q->where('id', $this->option('company')))
            ->orderBy('id')
            ->get(['id', 'company_name']);

        $totalPieces = 0;
        $totalLignes = 0;

        foreach ($entreprises as $entreprise) {
            [$pieces, $lignes] = $this->traiterEntreprise($entreprise, $appliquer);
            $totalPieces += $pieces;
            $totalLignes += $lignes;
        }

        $this->newLine();
        if ($totalPieces === 0) {
            $this->info('Aucun numéro de saisie partagé à tort : rien à réparer.');
            return self::SUCCESS;
        }

        $verbe = $appliquer ? 'renumérotées' : 'à renuméroter';
        $this->info("$totalPieces pièce(s) $verbe, soit $totalLignes ligne(s).");

        if (!$appliquer) {
            $this->line('Relancez avec --appliquer pour enregistrer.');
        }

        return self::SUCCESS;
    }

    /**
     * @return array{0:int,1:int} nombre de pièces et de lignes concernées
     */
    private function traiterEntreprise(Company $entreprise, bool $appliquer): array
    {
        // Numéros système portant des lignes de plusieurs dates, journaux ou
        // références : le signe qu'ils couvrent plus d'une pièce.
        $suspects = EcritureComptable::where('company_id', $entreprise->id)
            ->where('n_saisie', 'like', 'ECR%')
            ->select('n_saisie')
            ->groupBy('n_saisie')
            ->havingRaw("COUNT(DISTINCT CONCAT_WS('|', date, code_journal_id, COALESCE(n_saisie_user, ''), COALESCE(reference_piece, ''))) > 1")
            ->pluck('n_saisie');

        if ($suspects->isEmpty()) {
            return [0, 0];
        }

        $this->newLine();
        $this->line("Entreprise {$entreprise->id} — {$entreprise->company_name} : "
            . $suspects->count() . ' numéro(s) partagé(s) par plusieurs pièces.');

        $pieces = 0;
        $lignes = 0;

        foreach ($suspects as $numero) {
            $groupes = EcritureComptable::where('company_id', $entreprise->id)
                ->where('n_saisie', $numero)
                ->orderBy('date')
                ->orderBy('id')
                ->get()
                ->groupBy(fn ($e) => implode('|', [
                    $e->date,
                    $e->code_journal_id,
                    $e->n_saisie_user ?? '',
                    $e->reference_piece ?? '',
                ]));

            $this->line("  $numero : " . $groupes->count() . ' pièces, '
                . $groupes->sum(fn ($g) => $g->count()) . ' lignes.');

            $renumeroter = function () use ($groupes, $appliquer, $entreprise, &$pieces, &$lignes) {
                foreach ($groupes as $groupe) {
                    $premier = $groupe->first();
                    $nouveau = NumerotationSaisie::global(
                        $entreprise->id,
                        $premier->exercices_comptables_id,
                        $premier->date
                    );

                    $debit = $groupe->sum(fn ($e) => (float) $e->debit);
                    $credit = $groupe->sum(fn ($e) => (float) $e->credit);
                    $ecart = abs($debit - $credit) > 0.01
                        ? sprintf('  ⚠ écart %s', number_format($debit - $credit, 2, ',', ' '))
                        : '';

                    $this->line(sprintf('    %-22s %2d ligne(s)  %s%s',
                        $nouveau, $groupe->count(), $premier->date, $ecart));

                    if ($appliquer) {
                        EcritureComptable::whereIn('id', $groupe->pluck('id'))
                            ->update(['n_saisie' => $nouveau]);
                    }

                    $pieces++;
                    $lignes += $groupe->count();
                }
            };

            // En simulation rien n'est écrit : inutile d'ouvrir une transaction.
            $appliquer ? DB::transaction($renumeroter) : $renumeroter();
        }

        return [$pieces, $lignes];
    }
}
