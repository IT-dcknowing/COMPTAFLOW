<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\EcritureComptable;
use App\Models\ExerciceComptable;
use App\Models\PlanComptable;
use App\Services\ComptesDeResultat;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Remet du bon côté le résultat porté dans un report à nouveau.
 *
 * La clôture ajoutait le résultat aux soldes reportés avec son propre signe.
 * Or ces soldes sont algébriques, et leur somme sur les classes 1 à 5 vaut
 * déjà le résultat : un bénéfice partait donc au débit du compte 13, et le
 * report se retrouvait déséquilibré du double.
 *
 * Réparer revient à basculer cette seule ligne de l'autre côté. Rien d'autre
 * n'est touché : ni les soldes de bilan reportés, ni les écritures de
 * l'exercice clos.
 *
 * Par prudence, la commande ne travaille que sur les reports PRODUITS PAR LA
 * CLÔTURE — numéro « RAN-<année> » et référence « RAN-<exercice> ». Un report
 * saisi à la main ou arrivé par import a pu être déséquilibré pour d'autres
 * raisons, et ce n'est pas à une commande d'en décider.
 *
 * Elle ne fait rien sans --appliquer, et refuse tout cas qu'elle ne sait pas
 * expliquer entièrement.
 */
class ReparerLeResultatDesReports extends Command
{
    protected $signature = 'saisies:reparer-resultat-reports
                            {--company= : Ne traiter que cette entreprise}
                            {--appliquer : Enregistre la correction (sans cette option, simple simulation)}';

    protected $description = "Remet au bon côté la ligne de résultat des reports à nouveau produits par la clôture";

    /** Tolérance d'arrondi, en unités monétaires. */
    private const TOLERANCE = 0.01;

    public function handle(): int
    {
        $appliquer = (bool) $this->option('appliquer');

        DB::connection()->disableQueryLog();

        if (!$appliquer) {
            $this->warn('Mode simulation : aucune écriture ne sera modifiée. Ajoutez --appliquer pour enregistrer.');
        }

        $this->line('Recherche des reports produits par la clôture…');

        $lignes = EcritureComptable::query()
            ->when($this->option('company'), fn ($q) => $q->where('company_id', $this->option('company')))
            ->where('n_saisie', 'like', 'RAN-%')
            ->where('reference_piece', 'like', 'RAN-%')
            ->with('planComptable:id,numero_de_compte,intitule')
            ->get(['id', 'company_id', 'exercices_comptables_id', 'n_saisie', 'plan_comptable_id',
                   'date', 'debit', 'credit']);

        if ($lignes->isEmpty()) {
            $this->info('Aucun report produit par la clôture : rien à réparer.');
            return self::SUCCESS;
        }

        $noms = Company::whereIn('id', $lignes->pluck('company_id')->unique())
            ->pluck('company_name', 'id');
        $exercices = ExerciceComptable::whereIn('id', $lignes->pluck('exercices_comptables_id')->unique())
            ->pluck('intitule', 'id');

        $reparees = 0;
        $intacts = 0;
        $refuses = 0;

        foreach ($lignes->groupBy(fn ($e) => $e->company_id . '|' . $e->exercices_comptables_id . '|' . $e->n_saisie) as $cle => $duLot) {
            [$companyId, $exerciceId, $numero] = explode('|', $cle, 3);

            $debit = round($duLot->sum(fn ($e) => (float) $e->debit), 2);
            $credit = round($duLot->sum(fn ($e) => (float) $e->credit), 2);
            $ecart = round($debit - $credit, 2);

            $entete = sprintf('Entreprise %-4s %-30s %-14s %s',
                $companyId,
                mb_strimwidth($noms[$companyId] ?? '? (entreprise supprimée)', 0, 30, '…'),
                mb_strimwidth($exercices[$exerciceId] ?? ('#' . $exerciceId), 0, 14, '…'),
                $numero);

            if (abs($ecart) < self::TOLERANCE) {
                $intacts++;
                continue;
            }

            $this->newLine();
            $this->line($entete);

            // Une entreprise effacée n'a plus de fiche : on ne touche pas à des
            // écritures que plus personne ne peut ouvrir.
            if (!isset($noms[$companyId])) {
                $this->line('    → entreprise supprimée : laissée telle quelle.');
                $refuses++;
                continue;
            }

            $ligneResultat = $duLot->first(fn ($e) => $e->planComptable
                && ComptesDeResultat::estCompteDeResultat($e->planComptable->numero_de_compte));

            if (!$ligneResultat) {
                $this->warn('    → aucune ligne sur un compte de classe 13 : écart inexpliqué, rien touché.');
                $refuses++;
                continue;
            }

            $porte = round((float) $ligneResultat->debit - (float) $ligneResultat->credit, 2);

            // La signature du défaut : la ligne de résultat vaut exactement la
            // moitié de l'écart, et du même côté. Tout autre cas sort du cadre.
            if (abs($porte * 2 - $ecart) >= self::TOLERANCE) {
                $this->warn(sprintf('    → la ligne de résultat (%s) ne vaut pas la moitié de l\'écart (%s) : rien touché.',
                    number_format(abs($porte), 0, ',', ' '), number_format(abs($ecart), 0, ',', ' ')));
                $refuses++;
                continue;
            }

            $montant = abs($porte);
            $compte = $ligneResultat->planComptable->numero_de_compte;

            $this->line(sprintf('    %s  %-12s  %s %s  →  %s %s',
                \Carbon\Carbon::parse($ligneResultat->date)->format('d/m/Y'),
                $compte,
                number_format($montant, 0, ',', ' '),
                $porte > 0 ? 'au débit' : 'au crédit',
                number_format($montant, 0, ',', ' '),
                $porte > 0 ? 'au CRÉDIT' : 'au DÉBIT'));

            $this->line(sprintf('    écart de %s ramené à 0.', number_format(abs($ecart), 0, ',', ' ')));

            // Le compte doit suivre le sens : un bénéfice va sur 1301, une
            // perte sur 1309. Bascule du côté opposé, le compte change aussi.
            $resultat = $porte > 0 ? $montant : -$montant;
            $bonCompte = ComptesDeResultat::pour((int) $companyId, $resultat);

            if ($bonCompte && $bonCompte->id !== $ligneResultat->plan_comptable_id) {
                $this->line(sprintf('    compte corrigé : %s → %s (%s)',
                    $compte, $bonCompte->numero_de_compte, $resultat >= 0 ? 'bénéfice' : 'perte'));
            }

            if ($appliquer) {
                $ligneResultat->update([
                    'debit' => $porte > 0 ? 0 : $montant,
                    'credit' => $porte > 0 ? $montant : 0,
                    'plan_comptable_id' => $bonCompte?->id ?? $ligneResultat->plan_comptable_id,
                ]);
            }

            $reparees++;
        }

        $this->newLine();

        if ($intacts > 0) {
            $this->line("$intacts report(s) déjà équilibré(s) : laissés tels quels.");
        }

        if ($reparees === 0) {
            $this->info('Aucun report à réparer.');
            return self::SUCCESS;
        }

        $verbe = $appliquer ? 'réparé(s)' : 'à réparer';
        $this->info("$reparees report(s) $verbe.");

        if ($refuses > 0) {
            $this->warn("$refuses report(s) écarté(s) : l'écart ne correspond pas au défaut connu.");
            $this->line('Ceux-là demandent un examen à la main.');
        }

        if (!$appliquer) {
            $this->line('Relancez avec --appliquer pour enregistrer.');
        } else {
            $this->line('Vérifiez avec : php artisan saisies:reports-existants');
        }

        return self::SUCCESS;
    }
}
