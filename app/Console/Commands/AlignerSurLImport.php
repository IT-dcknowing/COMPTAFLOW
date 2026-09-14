<?php

namespace App\Console\Commands;

use App\Http\Controllers\Admin\AdminConfigController;
use App\Models\CodeJournal;
use App\Models\Company;
use App\Models\ImportStaging;
use App\Models\PlanComptable;
use App\Models\PlanTiers;
use App\Models\User;
use App\Services\UniformisationImport;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Faire repasser par l'import ce que Selflow a déversé avant qu'il n'y passe.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * Pourquoi
 * ─────────────────────────────────────────────────────────────────────────
 *
 * Jusqu'au lot 24 de Selflow, le déversement rangeait les numéros tels que
 * Selflow les nomme : `VTE` et `OD` dans un dossier réglé sur quatre
 * caractères, `411100` dans un dossier réglé sur huit chiffres. Le déversement
 * passe maintenant par l'import ; les lignes déjà reçues, elles, gardaient
 * l'ancienne forme, et un même dossier aurait porté deux conventions.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * Ce que la commande fait, et ce qu'elle ne fait pas
 * ─────────────────────────────────────────────────────────────────────────
 *
 * Elle **ne réécrit pas la règle** : les numéros sortent de la préparation
 * de l'import (`AdminConfigController::importStaging`), la même qui sert à
 * l'écran. Pour que l'import ne reconnaisse pas la ligne qu'on lui soumet et
 * ne la déclare pas « déjà présente », son numéro est remplacé le temps de la
 * préparation par un repère qu'aucune règle ne produit, dans une transaction.
 *
 * L'ancien numéro reste rangé dessous, dans `numero_original` : c'est par lui
 * que les écritures de Selflow retrouvent la ligne. Les écritures, elles, ne
 * bougent pas — elles désignent les lignes par leur identifiant.
 *
 * Ne sont concernées que les lignes **sans numéro d'origine** des dossiers
 * **liés à Selflow** : une ligne qui en a un est déjà passée par l'import, et
 * celles d'un dossier non lié ne viennent pas de Selflow. Une ligne que la
 * règle laisse telle quelle — `MOOV` sur quatre caractères, `410001` sur six —
 * n'est pas touchée.
 */
class AlignerSurLImport extends Command
{
    protected $signature = 'selflow:aligner-sur-import
        {--dossier= : un seul dossier, par son identifiant}
        {--simuler : montrer ce qui changerait, sans rien écrire}';

    protected $description = "Renuméroter, par la règle de l'import, les comptes, journaux et tiers déjà déversés par Selflow";

    public function handle(): int
    {
        $dossiers = Company::whereNotNull('selflow_company_id')
            ->when($this->option('dossier'), fn ($q, $id) => $q->where('id', $id))
            ->get();

        if ($dossiers->isEmpty()) {
            $this->info('Aucun dossier lié à Selflow.');

            return self::SUCCESS;
        }

        $simuler = (bool) $this->option('simuler');

        foreach ($dossiers as $dossier) {
            $admin = User::find($dossier->user_id)
                ?? User::where('company_id', $dossier->id)->where('role', 'admin')->first();

            if (!$admin) {
                $this->warn("Dossier {$dossier->id} — {$dossier->company_name} : aucun administrateur, ignoré.");
                continue;
            }

            Auth::setUser($admin);
            session()->put('current_company_id', $dossier->id);

            $changements = [];
            $ecartes = [];

            DB::beginTransaction();

            try {
                $this->comptes($dossier, $changements, $ecartes);
                $this->journaux($dossier, $admin, $changements, $ecartes);
                $this->tiers($dossier, $admin, $changements, $ecartes);

                $simuler ? DB::rollBack() : DB::commit();
            } catch (\Throwable $e) {
                DB::rollBack();
                $this->error("Dossier {$dossier->id} : {$e->getMessage()}");
                continue;
            }

            $this->line('');
            $this->info(sprintf('Dossier %d — %s (comptes %d, journaux %d, tiers %d)%s',
                $dossier->id, $dossier->company_name,
                UniformisationImport::chiffresDeCompte($dossier),
                UniformisationImport::caracteresDeJournal($dossier),
                (int) ($dossier->tier_digits ?: 6),
                $simuler ? ' — simulation, rien n\'est écrit' : ''));

            $changements
                ? $this->table(['Nature', 'Ancien', 'Nouveau'], $changements)
                : $this->line('  Déjà conforme.');

            foreach ($ecartes as $motif) {
                $this->warn('  ' . $motif);
            }
        }

        return self::SUCCESS;
    }

    /**
     * Les comptes : la normalisation de l'import est une fonction pure, il n'y
     * a pas de séquence à générer ni de préparation à faire tourner.
     */
    private function comptes(Company $dossier, array &$changements, array &$ecartes): void
    {
        $chiffres = UniformisationImport::chiffresDeCompte($dossier);

        $candidats = PlanComptable::where('company_id', $dossier->id)
            ->where(fn ($q) => $q->whereNull('numero_original')->orWhere('numero_original', ''))
            ->get();

        foreach ($candidats as $compte) {
            $nouveau = UniformisationImport::numeroDeCompte($compte->numero_de_compte, $chiffres);

            if ($nouveau === '' || $nouveau === $compte->numero_de_compte) {
                continue;
            }

            // Deux comptes pour un même numéro : on ne fusionne pas. Des tables
            // entières renvoient à un compte par clé étrangère, parfois en
            // cascade ; supprimer l'un pour garder l'autre peut effacer des
            // données. Le comptable tranche.
            if (PlanComptable::where('company_id', $dossier->id)->where('numero_de_compte', $nouveau)->exists()) {
                $ecartes[] = "Compte {$compte->numero_de_compte} : {$nouveau} existe déjà dans le dossier, rapprochez-les à la main.";
                continue;
            }

            $compte->forceFill([
                'numero_original'  => $compte->numero_de_compte,
                'numero_de_compte' => $nouveau,
            ])->save();

            $changements[] = ['Compte', $compte->numero_original, $nouveau];
        }
    }

    private function journaux(Company $dossier, User $admin, array &$changements, array &$ecartes): void
    {
        $candidats = CodeJournal::where('company_id', $dossier->id)
            ->where(fn ($q) => $q->whereNull('numero_original')->orWhere('numero_original', ''))
            ->get();

        if ($candidats->isEmpty()) {
            return;
        }

        $lignes = $candidats->map(fn (CodeJournal $j) => [
            $j->code_journal,
            $j->intitule,
            $j->type ?? '',
            (string) PlanComptable::whereKey($j->compte_de_tresorerie)->value('numero_de_compte'),
        ])->all();

        $resultats = $this->preparer($dossier, $admin, 'journals', $candidats,
            'code_journal', ['code_journal', 'intitule', 'type', 'compte_de_tresorerie'], $lignes);

        foreach ($candidats->values() as $i => $journal) {
            $ancien = $lignes[$i][0];
            [$statut, $donnees, $erreurs] = $resultats[$i] ?? ['error', [], ['aucun résultat']];
            $nouveau = strtoupper(trim((string) ($donnees['code_journal'] ?? '')));

            if ($statut !== 'valid' || $nouveau === '') {
                $journal->forceFill(['code_journal' => $ancien])->save();
                $ecartes[] = "Journal {$ancien} : l'import l'écarte — " . implode(' ', (array) $erreurs);
                continue;
            }

            $journal->forceFill(['code_journal' => $nouveau, 'numero_original' => $ancien === $nouveau ? null : $ancien])->save();

            if ($ancien !== $nouveau) {
                // La trésorerie recopie le code du journal au lieu de le
                // désigner par son identifiant : elle doit suivre.
                if (Schema::hasTable('tresorerie')) {
                    DB::table('tresorerie')->where('company_id', $dossier->id)
                        ->where('code_journal', $ancien)->update(['code_journal' => $nouveau]);
                }

                $changements[] = ['Journal', $ancien, $nouveau];
            }
        }
    }

    private function tiers(Company $dossier, User $admin, array &$changements, array &$ecartes): void
    {
        $candidats = PlanTiers::where('company_id', $dossier->id)
            ->where(fn ($q) => $q->whereNull('numero_original')->orWhere('numero_original', ''))
            ->get();

        if ($candidats->isEmpty()) {
            return;
        }

        $lignes = $candidats->map(fn (PlanTiers $t) => [
            $t->numero_de_tiers,
            $t->intitule,
            (string) PlanComptable::whereKey($t->compte_general)->value('numero_de_compte'),
        ])->all();

        $resultats = $this->preparer($dossier, $admin, 'tiers', $candidats,
            'numero_de_tiers', ['numero_de_tiers', 'intitule', 'compte_general'], $lignes);

        foreach ($candidats->values() as $i => $tiers) {
            $ancien = $lignes[$i][0];
            [$statut, $donnees, $erreurs] = $resultats[$i] ?? ['error', [], ['aucun résultat']];
            $nouveau = strtoupper(trim((string) ($donnees['numero_de_tiers'] ?? '')));

            if ($statut !== 'valid' || $nouveau === '' || $nouveau === 'NON GÉNÉRÉ') {
                $tiers->forceFill(['numero_de_tiers' => $ancien])->save();
                $ecartes[] = "Tiers {$ancien} : l'import l'écarte — " . implode(' ', (array) $erreurs);
                continue;
            }

            $tiers->forceFill(['numero_de_tiers' => $nouveau, 'numero_original' => $ancien === $nouveau ? null : $ancien])->save();

            if ($ancien !== $nouveau) {
                $changements[] = ['Tiers', $ancien, $nouveau];
            }
        }
    }

    /**
     * Faire tourner la préparation de l'import sur des lignes existantes.
     *
     * Le numéro de chaque ligne est d'abord remplacé par un repère : sans cela,
     * l'import la retrouve en base et la déclare « déjà présente », sans rien
     * générer. Le repère commence par `~`, qu'aucune règle de numérotation ne
     * produit et qu'aucune recherche par préfixe ne rencontre.
     *
     * @return array<int, array{0: string, 1: array, 2: array}> statut, données, erreurs — dans l'ordre des lignes
     */
    private function preparer(Company $dossier, User $admin, string $type, $candidats, string $colonne,
        array $champs, array $lignes): array
    {
        foreach ($candidats as $ligne) {
            $ligne->forceFill([$colonne => '~' . $ligne->id])->save();
        }

        $mapping = ['_header_index' => 0];
        foreach ($champs as $index => $champ) {
            $mapping[$champ] = $index;
        }

        $import = ImportStaging::create([
            'company_id' => $dossier->id,
            'user_id'    => $admin->id,
            'source'     => 'SELFLOW',
            'type'       => $type,
            'file_name'  => 'Alignement des lignes déjà déversées',
            'raw_data'   => array_merge([$champs], $lignes),
            'mapping'    => $mapping,
            'status'     => 'staging',
        ]);

        $apercu = app(AdminConfigController::class)->importStaging($import->id, true);
        $import->delete();

        $resultats = [];
        foreach ((array) ($apercu['rowsWithStatus'] ?? []) as $ligne) {
            $resultats[((int) $ligne['index']) - 1] = [$ligne['status'], (array) $ligne['data'], (array) $ligne['errors']];
        }

        return $resultats;
    }
}
