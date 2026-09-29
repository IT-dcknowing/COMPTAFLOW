<?php

namespace App\Console\Commands;

use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Efface ce qui reste des entreprises supprimées.
 *
 * La suppression d'une entreprise emportait la fiche et ses utilisateurs, mais
 * laissait la comptabilité derrière. Des dossiers entiers existaient donc en
 * base sans entreprise pour les ouvrir : invisibles dans l'application,
 * impossibles à corriger, et pourtant comptés par tous les diagnostics — ce
 * sont les « Entreprise 61 ? », « Entreprise 70 ? » des recensements.
 *
 * La suppression est corrigée ; cette commande reprend l'existant.
 *
 * Elle ne touche QUE des lignes dont le company_id ne désigne plus aucune
 * entreprise. Un dossier vivant ne peut pas être atteint, même par erreur.
 */
class PurgerLesDossiersOrphelins extends Command
{
    protected $signature = 'dossiers:purger-orphelins
                            {--appliquer : Enregistre la purge (sans cette option, simple simulation)}';

    protected $description = "Efface les données restées en base après la suppression de leur entreprise";

    /**
     * Les tables portant un company_id, de la plus dépendante à la plus large.
     *
     * @var array<int, string>
     */
    private const TABLES = [
        'ventilations_analytiques',
        'ecriture_comptables',
        'journaux_saisis',
        'brouillons',
        'ecriture_modeles',
        'plan_tiers',
        'plan_comptables',
        'code_journals',
        'exercices_comptables',
        'compte_tresoreries',
        'treasury_categories',
        'sections_analytiques',
        'axes_analytiques',
        'liasse_data',
        'company_user',
    ];

    public function handle(): int
    {
        $appliquer = (bool) $this->option('appliquer');

        DB::connection()->disableQueryLog();

        if (!$appliquer) {
            $this->warn('Mode simulation : rien ne sera effacé. Ajoutez --appliquer pour enregistrer.');
        }

        $vivantes = Company::pluck('id');

        $this->line(sprintf('%d entreprise(s) en base. Recherche de ce qui n\'en relève plus…', $vivantes->count()));

        $orphelins = [];
        $total = 0;

        foreach (self::TABLES as $table) {
            if (!Schema::hasTable($table) || !Schema::hasColumn($table, 'company_id')) {
                continue;
            }

            $ids = DB::table($table)
                ->whereNotNull('company_id')
                ->whereNotIn('company_id', $vivantes)
                ->distinct()
                ->pluck('company_id');

            if ($ids->isEmpty()) {
                continue;
            }

            $lignes = DB::table($table)->whereIn('company_id', $ids)->count();
            $orphelins[$table] = ['dossiers' => $ids, 'lignes' => $lignes];
            $total += $lignes;
        }

        if ($orphelins === []) {
            $this->info('Aucune donnée orpheline : chaque ligne relève d\'une entreprise existante.');
            return self::SUCCESS;
        }

        // Le décompte par dossier disparu : c'est ce qui parle à l'utilisateur.
        $parDossier = [];
        foreach ($orphelins as $table => $info) {
            foreach ($info['dossiers'] as $id) {
                $parDossier[$id][$table] = DB::table($table)->where('company_id', $id)->count();
            }
        }

        foreach ($parDossier as $id => $tables) {
            $this->newLine();
            $this->warn(sprintf('Dossier supprimé n° %s', $id));
            foreach ($tables as $table => $lignes) {
                if ($lignes > 0) {
                    $this->line(sprintf('      %-28s %8s ligne(s)', $table, number_format($lignes, 0, ',', ' ')));
                }
            }
        }

        $this->newLine();

        if (!$appliquer) {
            $this->info(sprintf('%s ligne(s) à effacer, sur %d dossier(s) disparu(s).',
                number_format($total, 0, ',', ' '), count($parDossier)));
            $this->line('Ces données n\'appartiennent plus à aucune entreprise : personne ne peut les ouvrir.');
            $this->line('Relancez avec --appliquer pour les effacer.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($orphelins) {
            foreach (self::TABLES as $table) {
                if (!isset($orphelins[$table])) {
                    continue;
                }
                DB::table($table)->whereIn('company_id', $orphelins[$table]['dossiers'])->delete();
            }
        });

        $this->info(sprintf('%s ligne(s) effacée(s), sur %d dossier(s) disparu(s).',
            number_format($total, 0, ',', ' '), count($parDossier)));
        $this->line('Les diagnostics ne les compteront plus.');

        return self::SUCCESS;
    }
}
