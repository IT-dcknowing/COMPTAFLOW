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
                            {--appliquer : Enregistre les nouveaux numéros (sans cette option, simple simulation)}
                            {--detail : Affiche chaque pièce, et pas seulement celles en écart}';

    protected $description = "Redonne un numéro distinct à chaque pièce lorsqu'un import les a toutes regroupées sous le même";

    /** Ce qui distingue deux pièces au sein d'un même numéro. */
    private const CLE_PIECE = "CONCAT_WS('|', date, code_journal_id, COALESCE(n_saisie_user, ''), COALESCE(reference_piece, ''))";

    public function handle(): int
    {
        $appliquer = (bool) $this->option('appliquer');
        $detail = (bool) $this->option('detail');

        if (!$appliquer) {
            $this->warn('Mode simulation : aucune écriture ne sera modifiée. Ajoutez --appliquer pour enregistrer.');
        }

        // Un seul balayage de la table pour tous les dossiers : une lecture par
        // entreprise coûterait des minutes sur une base de production, sans
        // rien afficher, et donnerait l'impression que la commande est figée.
        $this->line('Recherche des numéros partagés par plusieurs pièces…');
        $depart = microtime(true);

        $suspects = EcritureComptable::query()
            ->when($this->option('company'), fn ($q) => $q->where('company_id', $this->option('company')))
            ->where('n_saisie', 'like', 'ECR%')
            ->select('company_id', 'n_saisie', DB::raw('COUNT(*) as lignes'))
            ->groupBy('company_id', 'n_saisie')
            ->havingRaw('COUNT(DISTINCT ' . self::CLE_PIECE . ') > 1')
            ->get();

        $this->line(sprintf('Balayage terminé en %.1f s : %d numéro(s) à reprendre.',
            microtime(true) - $depart, $suspects->count()));

        if ($suspects->isEmpty()) {
            $this->info('Aucun numéro de saisie partagé à tort : rien à réparer.');
            return self::SUCCESS;
        }

        $noms = Company::whereIn('id', $suspects->pluck('company_id')->unique())
            ->pluck('company_name', 'id');

        $totalPieces = 0;
        $totalLignes = 0;
        $totalEcarts = 0;

        foreach ($suspects->groupBy('company_id') as $companyId => $numeros) {
            $this->newLine();
            $this->line("Entreprise $companyId — " . ($noms[$companyId] ?? '?') . ' : '
                . $numeros->count() . ' numéro(s), ' . $numeros->sum('lignes') . ' ligne(s).');

            foreach ($numeros as $suspect) {
                [$pieces, $lignes, $ecarts] = $this->reprendre(
                    (int) $companyId, $suspect->n_saisie, $appliquer, $detail
                );
                $totalPieces += $pieces;
                $totalLignes += $lignes;
                $totalEcarts += $ecarts;
            }
        }

        $this->newLine();
        $verbe = $appliquer ? 'renumérotées' : 'à renuméroter';
        $this->info("$totalPieces pièce(s) $verbe, soit $totalLignes ligne(s).");

        if ($totalEcarts > 0) {
            $this->warn("$totalEcarts pièce(s) restent déséquilibrées après regroupement : à vérifier.");
        }

        if (!$appliquer) {
            $this->line('Relancez avec --appliquer pour enregistrer.');
        }

        return self::SUCCESS;
    }

    /**
     * Redonne un numéro à chaque pièce cachée derrière un numéro unique.
     *
     * @return array{0:int,1:int,2:int} pièces, lignes, pièces déséquilibrées
     */
    private function reprendre(int $companyId, string $numero, bool $appliquer, bool $detail): array
    {
        $groupes = EcritureComptable::where('company_id', $companyId)
            ->where('n_saisie', $numero)
            ->orderBy('date')
            ->orderBy('id')
            ->get(['id', 'date', 'code_journal_id', 'n_saisie_user', 'reference_piece',
                'debit', 'credit', 'exercices_comptables_id'])
            ->groupBy(fn ($e) => implode('|', [
                $e->date,
                $e->code_journal_id,
                $e->n_saisie_user ?? '',
                $e->reference_piece ?? '',
            ]));

        $this->line("  $numero : " . $groupes->count() . ' pièces, '
            . $groupes->sum(fn ($g) => $g->count()) . ' lignes.');

        $pieces = 0;
        $lignes = 0;
        $ecarts = 0;

        $renumeroter = function () use ($groupes, $appliquer, $detail, $companyId, &$pieces, &$lignes, &$ecarts) {
            foreach ($groupes as $groupe) {
                $premier = $groupe->first();
                $nouveau = NumerotationSaisie::global(
                    $companyId,
                    $premier->exercices_comptables_id,
                    $premier->date
                );

                $solde = $groupe->sum(fn ($e) => (float) $e->debit) - $groupe->sum(fn ($e) => (float) $e->credit);
                $desequilibre = abs($solde) > 0.01;

                if ($desequilibre || $detail) {
                    $this->line(sprintf('    %-22s %3d ligne(s)  %s%s',
                        $nouveau, $groupe->count(), $premier->date,
                        $desequilibre ? '  ⚠ écart ' . number_format($solde, 2, ',', ' ') : ''));
                }

                if ($appliquer) {
                    EcritureComptable::whereIn('id', $groupe->pluck('id'))
                        ->update(['n_saisie' => $nouveau]);
                }

                $pieces++;
                $lignes += $groupe->count();
                $ecarts += $desequilibre ? 1 : 0;
            }
        };

        // En simulation rien n'est écrit : inutile d'ouvrir une transaction.
        $appliquer ? DB::transaction($renumeroter) : $renumeroter();

        return [$pieces, $lignes, $ecarts];
    }
}
