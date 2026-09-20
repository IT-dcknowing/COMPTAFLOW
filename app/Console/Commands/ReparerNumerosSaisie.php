<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\EcritureComptable;
use App\Services\DecoupageDesPieces;
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
 * les écritures déjà enregistrées. Le découpage repose sur l'équilibre des
 * pièces (voir DecoupageDesPieces) : une écriture saine, même à deux lignes de
 * références différentes, n'est jamais touchée.
 *
 * Par sécurité elle ne modifie rien sans --appliquer.
 */
class ReparerNumerosSaisie extends Command
{
    protected $signature = 'saisies:reparer-numeros
                            {--company= : Ne traiter que cette entreprise}
                            {--appliquer : Enregistre les nouveaux numéros (sans cette option, simple simulation)}
                            {--depuis= : Ne toucher que les pièces enregistrées à partir de cette date (AAAA-MM-JJ)}
                            {--tout-lhistorique : Autorise la reprise des pièces anciennes, sans limite de date}
                            {--lignes-max=2 : Au-delà de ce nombre de lignes, un numéro est examiné ; une valeur plus haute accélère le balayage}
                            {--detail : Affiche chaque pièce, et pas seulement celles en écart}';

    protected $description = "Redonne un numéro distinct à chaque pièce lorsqu'un import les a toutes regroupées sous le même";

    public function handle(): int
    {
        $appliquer = (bool) $this->option('appliquer');
        $detail = (bool) $this->option('detail');
        $lignesMax = max(1, (int) $this->option('lignes-max'));
        $depuis = $this->option('depuis');

        // Des dizaines de milliers de requêtes passent ici : gardées en
        // mémoire, elles suffisent à faire tuer le processus.
        DB::connection()->disableQueryLog();

        // Un numéro de pièce est une référence : il figure sur des grands
        // livres, des balances et des états déjà édités. Le réécrire sur tout
        // l'historique ne doit pas pouvoir arriver par inadvertance.
        if ($appliquer && !$depuis && !$this->option('tout-lhistorique')) {
            $this->error('Reprise de tout l\'historique refusée.');
            $this->line('Limitez la reprise aux saisies récentes :');
            $this->line('    php artisan saisies:reparer-numeros --appliquer --depuis=' . now()->format('Y-m-d'));
            $this->line('Ou, si vous voulez vraiment reprendre les pièces anciennes :');
            $this->line('    php artisan saisies:reparer-numeros --appliquer --tout-lhistorique');

            return self::FAILURE;
        }

        if (!$appliquer) {
            $this->warn('Mode simulation : aucune écriture ne sera modifiée. Ajoutez --appliquer pour enregistrer.');
        }

        if ($depuis) {
            $this->line("Périmètre : pièces enregistrées à partir du $depuis. Les plus anciennes gardent leur numéro.");
        }

        // Un seul balayage de la table pour tous les dossiers : une lecture par
        // entreprise coûterait des minutes sur une base de production, sans
        // rien afficher, et donnerait l'impression que la commande est figée.
        //
        // La présélection est large à dessein : tout numéro portant plus de deux
        // lignes, ou couvrant plusieurs dates ou journaux, mérite un examen.
        // C'est le découpage sur l'équilibre qui tranche ensuite, et il laisse
        // intact tout numéro ne couvrant qu'une seule pièce.
        $this->line('Recherche des numéros couvrant plusieurs pièces…');
        $depart = microtime(true);

        $suspects = EcritureComptable::query()
            ->when($this->option('company'), fn ($q) => $q->where('company_id', $this->option('company')))
            ->where('n_saisie', 'like', 'ECR%')
            ->select('company_id', 'n_saisie', DB::raw('COUNT(*) as lignes'))
            ->groupBy('company_id', 'n_saisie')
            ->havingRaw("COUNT(DISTINCT CONCAT_WS('|', date, code_journal_id)) > 1 OR COUNT(*) > ?", [$lignesMax])
            // Un numéro n'est repris que si toutes ses lignes ont été
            // enregistrées après la date demandée : une pièce à cheval sur la
            // limite reste entière, du côté de l'ancien.
            ->when($depuis, fn ($q) => $q->havingRaw('MIN(created_at) >= ?', [$depuis . ' 00:00:00']))
            ->get();

        $this->line(sprintf('Balayage terminé en %.1f s : %d numéro(s) à examiner.',
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
        $intacts = 0;

        foreach ($suspects->groupBy('company_id') as $companyId => $numeros) {
            $this->newLine();
            $this->line("Entreprise $companyId — " . ($noms[$companyId] ?? '?') . ' : '
                . $numeros->count() . ' numéro(s), ' . $numeros->sum('lignes') . ' ligne(s).');

            foreach ($numeros as $suspect) {
                [$pieces, $lignes, $ecarts] = $this->reprendre(
                    (int) $companyId, $suspect->n_saisie, $appliquer, $detail
                );

                if ($pieces === 0) {
                    $intacts++;
                    continue;
                }

                $totalPieces += $pieces;
                $totalLignes += $lignes;
                $totalEcarts += $ecarts;
            }
        }

        $this->newLine();

        if ($intacts > 0) {
            $this->line("$intacts numéro(s) examiné(s) ne couvrent qu'une seule pièce : laissés tels quels.");
        }

        if ($totalPieces === 0) {
            $this->info('Aucun numéro de saisie à découper.');
            return self::SUCCESS;
        }

        $verbe = $appliquer ? 'renumérotées' : 'à renuméroter';
        $this->info("$totalPieces pièce(s) $verbe, soit $totalLignes ligne(s).");

        if ($totalEcarts > 0) {
            $this->warn("$totalEcarts pièce(s) restent déséquilibrées : à vérifier avant d'appliquer.");
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
     *                                  (0 pièce = numéro laissé intact)
     */
    private function reprendre(int $companyId, string $numero, bool $appliquer, bool $detail): array
    {
        $lignes = EcritureComptable::where('company_id', $companyId)
            ->where('n_saisie', $numero)
            ->get(['id', 'date', 'code_journal_id', 'debit', 'credit', 'exercices_comptables_id']);

        $pieces = DecoupageDesPieces::decouper($lignes);

        // Une seule pièce : le numéro est bon, on n'y touche pas.
        if ($pieces->count() < 2) {
            if ($detail) {
                $this->line("  $numero : une seule pièce, laissée telle quelle.");
            }
            return [0, 0, 0];
        }

        $this->line("  $numero : " . $pieces->count() . ' pièces, ' . $lignes->count() . ' lignes.');

        $ecarts = 0;

        $renumeroter = function () use ($pieces, $appliquer, $detail, $companyId, &$ecarts) {
            foreach ($pieces as $piece) {
                $premier = $piece->first();
                $nouveau = NumerotationSaisie::global(
                    $companyId,
                    $premier->exercices_comptables_id,
                    $premier->date
                );

                $desequilibre = !DecoupageDesPieces::estEquilibree($piece);
                $ecarts += $desequilibre ? 1 : 0;

                if ($desequilibre || $detail) {
                    $this->line(sprintf('    %-22s %3d ligne(s)  %s%s',
                        $nouveau, $piece->count(), $premier->date,
                        $desequilibre
                            ? '  ⚠ écart ' . number_format(DecoupageDesPieces::solde($piece), 2, ',', ' ')
                            : ''));
                }

                if ($appliquer) {
                    EcritureComptable::whereIn('id', $piece->pluck('id'))
                        ->update(['n_saisie' => $nouveau]);
                }
            }
        };

        // En simulation rien n'est écrit : inutile d'ouvrir une transaction.
        $appliquer ? DB::transaction($renumeroter) : $renumeroter();

        return [$pieces->count(), $lignes->count(), $ecarts];
    }
}
