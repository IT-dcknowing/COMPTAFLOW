<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\EcritureComptable;
use App\Models\PlanComptable;
use App\Traits\HandlesTreasuryPosts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Rattache à un poste de trésorerie les lignes de banque et de caisse qui n'en
 * ont pas.
 *
 * L'import ne créait pas les postes manquants : il se contentait d'en chercher
 * un. Une entreprise alimentée uniquement par import n'en avait donc aucun, et
 * ses banques et caisses n'apparaissaient ni dans le module Trésorerie ni dans
 * le rapprochement bancaire. L'import est corrigé ; cette commande reprend les
 * écritures déjà enregistrées.
 *
 * Elle ne touche que des lignes dont le poste est vide : aucun choix déjà fait
 * n'est écrasé. Par sécurité elle ne modifie rien sans --appliquer.
 */
class RattacherPostesTresorerie extends Command
{
    use HandlesTreasuryPosts;

    protected $signature = 'tresorerie:rattacher-postes
                            {--company= : Ne traiter que cette entreprise}
                            {--appliquer : Enregistre les rattachements (sans cette option, simple simulation)}';

    protected $description = "Donne un poste de trésorerie aux lignes de classe 5 qui n'en ont pas";

    public function handle(): int
    {
        $appliquer = (bool) $this->option('appliquer');

        DB::connection()->disableQueryLog();

        if (!$appliquer) {
            $this->warn('Mode simulation : aucune écriture ne sera modifiée. Ajoutez --appliquer pour enregistrer.');
        }

        $this->line('Recherche des lignes de trésorerie sans poste…');

        // Un comptage par compte plutôt que ligne à ligne : une entreprise
        // peut porter des dizaines de milliers de lignes de caisse.
        $manquants = EcritureComptable::query()
            ->join('plan_comptables', 'plan_comptables.id', '=', 'ecriture_comptables.plan_comptable_id')
            ->when($this->option('company'), fn ($q) => $q->where('ecriture_comptables.company_id', $this->option('company')))
            ->where('plan_comptables.numero_de_compte', 'like', '5%')
            ->whereNull('ecriture_comptables.poste_tresorerie_id')
            ->groupBy('ecriture_comptables.company_id', 'ecriture_comptables.plan_comptable_id')
            ->select(
                'ecriture_comptables.company_id',
                'ecriture_comptables.plan_comptable_id',
                DB::raw('COUNT(*) as lignes')
            )
            ->get();

        if ($manquants->isEmpty()) {
            $this->info('Toutes les lignes de banque et de caisse ont déjà un poste : rien à faire.');
            return self::SUCCESS;
        }

        $noms = Company::whereIn('id', $manquants->pluck('company_id')->unique())
            ->pluck('company_name', 'id');

        $comptes = PlanComptable::whereIn('id', $manquants->pluck('plan_comptable_id')->unique())
            ->get(['id', 'numero_de_compte', 'intitule'])
            ->keyBy('id');

        $totalLignes = 0;
        $totalComptes = 0;

        foreach ($manquants->groupBy('company_id') as $companyId => $lignes) {
            $this->newLine();
            $this->line(sprintf('Entreprise %-4s %s', $companyId, $noms[$companyId] ?? '?'));

            foreach ($lignes as $manquant) {
                $compte = $comptes[$manquant->plan_comptable_id] ?? null;

                if (!$compte) {
                    continue;
                }

                $posteId = $appliquer
                    ? $this->resolveTreasuryPost((int) $companyId, $manquant->plan_comptable_id)
                    : null;

                if ($appliquer && !$posteId) {
                    $this->warn(sprintf('      %-12s %s : aucun poste n\'a pu être créé.',
                        $compte->numero_de_compte, $compte->intitule));
                    continue;
                }

                if ($appliquer) {
                    EcritureComptable::where('company_id', $companyId)
                        ->where('plan_comptable_id', $manquant->plan_comptable_id)
                        ->whereNull('poste_tresorerie_id')
                        ->update(['poste_tresorerie_id' => $posteId]);
                }

                $this->line(sprintf('      %-12s %-34s %6d ligne(s)',
                    $compte->numero_de_compte,
                    mb_strimwidth($compte->intitule, 0, 34, '…'),
                    $manquant->lignes));

                $totalLignes += $manquant->lignes;
                $totalComptes++;
            }
        }

        $this->newLine();
        $verbe = $appliquer ? 'rattachée(s)' : 'à rattacher';
        $this->info("$totalLignes ligne(s) $verbe, sur $totalComptes compte(s) de trésorerie.");

        if (!$appliquer) {
            $this->line('Relancez avec --appliquer pour enregistrer.');
        } else {
            $this->line('Les banques et caisses concernées apparaissent désormais dans le module Trésorerie.');
        }

        return self::SUCCESS;
    }
}
