<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\EcritureComptable;
use App\Models\ExerciceComptable;
use App\Models\PlanComptable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Où et QUAND la trésorerie décroche-t-elle ?
 *
 * Deux anomalies se voient sur une balance mais sans date, ce qui ne dit pas
 * par où commencer :
 *
 *   - un compte de caisse ou de monnaie électronique au solde CRÉDITEUR. On
 *     ne peut pas payer avec de l'argent qu'on n'a pas : il manque une
 *     alimentation, ou une sortie a été saisie deux fois.
 *
 *   - un compte de virement de fonds (585) qui ne revient pas à zéro. C'est
 *     un compte de passage : tout ce qui y entre doit en ressortir. Un solde
 *     veut dire qu'un transfert n'a qu'une face.
 *
 * Cette commande donne le JOUR où chaque anomalie apparaît, et la pièce à
 * regarder. Elle ne modifie rien.
 */
class DiagnostiquerLaTresorerie extends Command
{
    protected $signature = 'tresorerie:anomalies
                            {--company= : Ne diagnostiquer que cette entreprise}
                            {--exercice= : Ne regarder que cet exercice}
                            {--detail : Lister chaque jour en écart sur les comptes de passage}';

    protected $description = "Dit à quelle date un compte de trésorerie passe au négatif, et où un virement de fonds reste à moitié";

    /** Tolérance d'arrondi, en unités monétaires. */
    private const TOLERANCE = 0.01;

    public function handle(): int
    {
        DB::connection()->disableQueryLog();

        $exercices = ExerciceComptable::query()
            ->when($this->option('company'), fn ($q) => $q->where('company_id', $this->option('company')))
            ->when($this->option('exercice'), fn ($q) => $q->where('id', $this->option('exercice')))
            ->orderBy('company_id')->orderBy('date_debut')
            ->get(['id', 'company_id', 'intitule', 'date_debut', 'date_fin']);

        if ($exercices->isEmpty()) {
            $this->error('Aucun exercice à examiner.');
            return self::FAILURE;
        }

        $noms = Company::whereIn('id', $exercices->pluck('company_id')->unique())
            ->pluck('company_name', 'id');

        $total = 0;

        foreach ($exercices as $exercice) {
            $anomalies = $this->examiner($exercice);

            if ($anomalies === 0) {
                continue;
            }

            $total += $anomalies;
        }

        $this->newLine();

        if ($total === 0) {
            $this->info('Aucune anomalie de trésorerie : les soldes tiennent, les virements sont complets.');
        } else {
            $this->warn("$total anomalie(s) au total.");
            $this->line('Aucune écriture n\'a été modifiée : ce diagnostic ne fait que lire.');
        }

        return self::SUCCESS;
    }

    private function examiner(ExerciceComptable $exercice): int
    {
        $comptes = PlanComptable::where('company_id', $exercice->company_id)
            ->where('numero_de_compte', 'like', '5%')
            ->orderBy('numero_de_compte')
            ->get(['id', 'numero_de_compte', 'intitule']);

        if ($comptes->isEmpty()) {
            return 0;
        }

        $lignes = EcritureComptable::where('company_id', $exercice->company_id)
            ->where('exercices_comptables_id', $exercice->id)
            ->whereIn('plan_comptable_id', $comptes->pluck('id'))
            ->where(fn ($q) => $q->whereNull('statut')->orWhere('statut', '!=', 'rejected'))
            ->orderBy('date')->orderBy('id')
            ->get(['plan_comptable_id', 'date', 'debit', 'credit', 'n_saisie', 'description_operation']);

        if ($lignes->isEmpty()) {
            return 0;
        }

        $anomalies = [];

        foreach ($lignes->groupBy('plan_comptable_id') as $planId => $duCompte) {
            $compte = $comptes->firstWhere('id', $planId);

            if (!$compte) {
                continue;
            }

            $anomalies = array_merge(
                $anomalies,
                str_starts_with($compte->numero_de_compte, '58')
                    ? $this->comptePassage($compte, $duCompte)
                    : $this->compteDeTresorerie($compte, $duCompte)
            );
        }

        if ($anomalies === []) {
            return 0;
        }

        $this->newLine();
        $this->line(sprintf('Entreprise %-4s %s — %s',
            $exercice->company_id,
            mb_strimwidth(Company::find($exercice->company_id)?->company_name ?? '?', 0, 34, '…'),
            $exercice->intitule));

        foreach ($anomalies as $a) {
            $this->warn('    ' . $a);
        }

        return count($anomalies);
    }

    /**
     * Un compte de caisse ou de banque : à quel jour passe-t-il au négatif ?
     *
     * @return array<int, string>
     */
    private function compteDeTresorerie(PlanComptable $compte, $duCompte): array
    {
        $solde = 0.0;
        $premierJourNegatif = null;
        $pireSolde = 0.0;
        $pieceEnCause = null;

        foreach ($duCompte as $l) {
            $solde += (float) $l->debit - (float) $l->credit;

            if ($solde < -self::TOLERANCE) {
                if ($premierJourNegatif === null) {
                    $premierJourNegatif = $l->date;
                    $pieceEnCause = $l->n_saisie;
                }
                $pireSolde = min($pireSolde, $solde);
            }
        }

        $messages = [];

        if ($premierJourNegatif !== null) {
            $messages[] = sprintf(
                '%-10s %-26s passe au négatif le %s (pièce %s), au plus bas %s ; solde de clôture %s',
                $compte->numero_de_compte,
                mb_strimwidth(trim((string) $compte->intitule), 0, 26, '…'),
                $this->jour($premierJourNegatif),
                $pieceEnCause,
                number_format($pireSolde, 0, ',', ' '),
                number_format($solde, 0, ',', ' ')
            );
        }

        return $messages;
    }

    /**
     * Un compte de virement de fonds : ce qui entre doit ressortir.
     *
     * @return array<int, string>
     */
    private function comptePassage(PlanComptable $compte, $duCompte): array
    {
        $debit = $duCompte->sum(fn ($l) => (float) $l->debit);
        $credit = $duCompte->sum(fn ($l) => (float) $l->credit);
        $solde = round($debit - $credit, 2);

        if (abs($solde) < self::TOLERANCE) {
            return [];
        }

        $messages = [sprintf(
            '%-10s %-26s ne revient pas à zéro : %s %s (débit %s, crédit %s)',
            $compte->numero_de_compte,
            mb_strimwidth(trim((string) $compte->intitule), 0, 26, '…'),
            number_format(abs($solde), 0, ',', ' '),
            $solde > 0 ? 'au débit' : 'au crédit',
            number_format($debit, 0, ',', ' '),
            number_format($credit, 0, ',', ' ')
        )];

        // Les jours où le compte de passage n'est pas soldé : ce sont eux qui
        // portent les transferts à une seule face.
        $parJour = $duCompte->groupBy('date')
            ->map(fn ($duJour) => round(
                $duJour->sum(fn ($l) => (float) $l->debit) - $duJour->sum(fn ($l) => (float) $l->credit), 2
            ))
            ->filter(fn ($ecart) => abs($ecart) >= self::TOLERANCE);

        if ($parJour->isEmpty()) {
            return $messages;
        }

        $messages[] = sprintf('           %d jour(s) en écart. Les voici, du plus gros au plus petit :',
            $parJour->count());

        $aMontrer = $this->option('detail') ? $parJour->count() : 8;

        foreach ($parJour->sortByDesc(fn ($e) => abs($e))->take($aMontrer) as $date => $ecart) {
            $messages[] = sprintf('           %s   %s %s',
                $this->jour($date),
                number_format(abs($ecart), 0, ',', ' '),
                $ecart > 0 ? 'sorti sans être arrivé' : 'arrivé sans être sorti');
        }

        if (!$this->option('detail') && $parJour->count() > $aMontrer) {
            $messages[] = sprintf('           … et %d autre(s). Ajoutez --detail pour tout voir.',
                $parJour->count() - $aMontrer);
        }

        return $messages;
    }

    private function jour($date): string
    {
        return \Carbon\Carbon::parse($date)->format('d/m/Y');
    }
}
