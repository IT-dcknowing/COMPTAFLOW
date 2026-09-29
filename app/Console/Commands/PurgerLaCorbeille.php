<?php

namespace App\Console\Commands;

use App\Models\ArchivedRecord;
use App\Models\CodeJournal;
use App\Models\Company;
use App\Models\EcritureComptable;
use App\Models\ExerciceComptable;
use App\Models\PlanComptable;
use App\Services\SuppressionTracee;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Efface les entreprises dont le délai de récupération est passé.
 *
 * Une entreprise supprimée reste en corbeille trente jours, comptabilité
 * intacte. Passé ce délai, elle part pour de bon — ses écritures rejoignent
 * l'archive des suppressions, où elles restent lisibles trente jours de plus.
 *
 * À programmer une fois par jour. Sans --appliquer, elle se contente de dire
 * ce qui partirait.
 */
class PurgerLaCorbeille extends Command
{
    protected $signature = 'dossiers:purger-corbeille
                            {--appliquer : Efface pour de bon (sans cette option, simple simulation)}';

    protected $description = "Efface définitivement les entreprises en corbeille depuis plus de 30 jours";

    public function handle(): int
    {
        $appliquer = (bool) $this->option('appliquer');

        DB::connection()->disableQueryLog();

        $limite = now()->subDays(Company::CORBEILLE_JOURS);

        $expirees = Company::onlyTrashed()
            ->where('deleted_at', '<=', $limite)
            ->get(['id', 'company_name', 'deleted_at', 'parent_company_id']);

        if ($expirees->isEmpty()) {
            $this->info(sprintf(
                'Aucune entreprise en corbeille depuis plus de %d jours.',
                Company::CORBEILLE_JOURS
            ));
            return self::SUCCESS;
        }

        if (!$appliquer) {
            $this->warn('Mode simulation : rien ne sera effacé. Ajoutez --appliquer pour enregistrer.');
        }

        $total = 0;

        foreach ($expirees as $company) {
            $ids = Company::onlyTrashed()
                ->where(fn ($q) => $q->where('id', $company->id)->orWhere('parent_company_id', $company->id))
                ->pluck('id');

            $ecritures = EcritureComptable::whereIn('company_id', $ids)->count();
            $total += $ecritures;

            $this->newLine();
            $this->warn(sprintf('%-40s supprimée le %s — %s écriture(s)',
                mb_strimwidth($company->company_name, 0, 40, '…'),
                $company->deleted_at->format('d/m/Y'),
                number_format($ecritures, 0, ',', ' ')));

            if (!$appliquer) {
                continue;
            }

            DB::transaction(function () use ($ids) {
                $lot = ArchivedRecord::nouveauLot();

                SuppressionTracee::supprimer(EcritureComptable::whereIn('company_id', $ids), $lot);

                ExerciceComptable::whereIn('company_id', $ids)->delete();
                CodeJournal::whereIn('company_id', $ids)->delete();
                PlanComptable::whereIn('company_id', $ids)->delete();

                Company::onlyTrashed()->whereIn('id', $ids)->forceDelete();
            });
        }

        $this->newLine();
        $verbe = $appliquer ? 'effacée(s)' : 'à effacer';
        $this->info(sprintf('%d entreprise(s) %s, soit %s écriture(s).',
            $expirees->count(), $verbe, number_format($total, 0, ',', ' ')));

        if (!$appliquer) {
            $this->line('Relancez avec --appliquer pour enregistrer.');
        } else {
            $this->line('Les écritures restent lisibles 30 jours dans l\'archive des suppressions.');
        }

        return self::SUCCESS;
    }
}
