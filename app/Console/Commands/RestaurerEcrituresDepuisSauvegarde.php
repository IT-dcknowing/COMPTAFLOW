<?php

namespace App\Console\Commands;

use App\Models\CodeJournal;
use App\Models\Company;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Remet les écritures qui manquent, en les relisant dans une sauvegarde.
 *
 * Des suppressions sont passées sans laisser de trace : les chemins de
 * suppression en masse court-circuitaient les événements Eloquent, donc ni
 * l'archive des suppressions ni le journal d'audit n'ont rien retenu. Cette
 * commande répare le passé ; SuppressionTracee empêche que cela se reproduise.
 *
 * Elle travaille sur une base de sauvegarde restaurée à côté de la base de
 * production, jamais sur un fichier .sql : importer le fichier dans une base
 * temporaire est plus sûr que d'en analyser le texte.
 *
 *   1. créer une base temporaire, par exemple cp..._sauvegarde ;
 *   2. y importer le fichier .sql ;
 *   3. lancer cette commande en simulation, puis avec --appliquer.
 *
 * Elle n'ajoute que ce qui manque, à l'identifiant près, et ne supprime ni ne
 * modifie jamais une ligne existante.
 */
class RestaurerEcrituresDepuisSauvegarde extends Command
{
    protected $signature = 'saisies:restaurer
                            {--source= : Nom de la base de sauvegarde restaurée à côté de la production}
                            {--company= : Limiter à cette entreprise}
                            {--journal= : Limiter à ce code journal, par exemple CAI}
                            {--depuis= : Ne considérer que les écritures à partir de cette date (AAAA-MM-JJ)}
                            {--jusqua= : Date de fin du périmètre (AAAA-MM-JJ)}
                            {--appliquer : Réinsère réellement les écritures manquantes}';

    protected $description = "Réinsère les écritures présentes dans une sauvegarde et absentes de la base";

    public function handle(): int
    {
        $source = $this->option('source');

        if (!$source) {
            $this->error('Indiquez la base de sauvegarde : --source=nom_de_la_base');
            return self::FAILURE;
        }

        $appliquer = (bool) $this->option('appliquer');

        if (!$appliquer) {
            $this->warn('Mode simulation : rien ne sera réinséré. Ajoutez --appliquer pour restaurer.');
        }

        // Même serveur, mêmes identifiants, autre base.
        config(['database.connections.sauvegarde' => array_merge(
            config('database.connections.' . config('database.default')),
            ['database' => $source]
        )]);

        try {
            DB::connection('sauvegarde')->getPdo();
        } catch (\Throwable $e) {
            $this->error("Base de sauvegarde « $source » inaccessible : " . $e->getMessage());
            return self::FAILURE;
        }

        $colonnes = array_intersect(
            DB::getSchemaBuilder()->getColumnListing('ecriture_comptables'),
            DB::connection('sauvegarde')->getSchemaBuilder()->getColumnListing('ecriture_comptables')
        );

        if ($colonnes === []) {
            $this->error("La table ecriture_comptables est introuvable dans « $source ».");
            return self::FAILURE;
        }

        $sauvegarde = $this->requete(DB::connection('sauvegarde')->table('ecriture_comptables'));

        $this->line('Lecture de la sauvegarde…');
        $idsSauvegarde = $sauvegarde->pluck('id');
        $this->line($idsSauvegarde->count() . ' écriture(s) dans le périmètre demandé.');

        if ($idsSauvegarde->isEmpty()) {
            $this->info('Rien à comparer.');
            return self::SUCCESS;
        }

        $presents = DB::table('ecriture_comptables')
            ->whereIn('id', $idsSauvegarde)
            ->pluck('id')
            ->flip();

        $manquants = $idsSauvegarde->reject(fn ($id) => $presents->has($id))->values();

        $this->line($manquants->count() . ' écriture(s) présentes dans la sauvegarde et absentes de la base.');

        if ($manquants->isEmpty()) {
            $this->info('Aucune écriture à restaurer.');
            return self::SUCCESS;
        }

        $noms = Company::pluck('company_name', 'id');
        $journaux = CodeJournal::pluck('code_journal', 'id');
        $restaurees = 0;
        $debit = 0.0;
        $credit = 0.0;

        foreach ($manquants->chunk(500) as $paquet) {
            $lignes = DB::connection('sauvegarde')->table('ecriture_comptables')
                ->whereIn('id', $paquet)
                ->orderBy('id')
                ->get();

            $aInserer = [];

            foreach ($lignes as $ligne) {
                $valeurs = array_intersect_key((array) $ligne, array_flip($colonnes));

                $this->line(sprintf('  #%-8s %s  %-6s  %-34s D %12s  C %12s  [%s]',
                    $ligne->id,
                    $ligne->date,
                    $journaux[$ligne->code_journal_id] ?? '?',
                    mb_strimwidth((string) $ligne->description_operation, 0, 34, '…'),
                    number_format((float) $ligne->debit, 0, ',', ' '),
                    number_format((float) $ligne->credit, 0, ',', ' '),
                    $noms[$ligne->company_id] ?? ('entreprise ' . $ligne->company_id)
                ));

                $debit += (float) $ligne->debit;
                $credit += (float) $ligne->credit;
                $aInserer[] = $valeurs;
                $restaurees++;
            }

            if ($appliquer && $aInserer !== []) {
                DB::table('ecriture_comptables')->insert($aInserer);
            }
        }

        $this->newLine();
        $this->info(sprintf('%d écriture(s) %s — débit %s, crédit %s.',
            $restaurees,
            $appliquer ? 'restaurées' : 'à restaurer',
            number_format($debit, 0, ',', ' '),
            number_format($credit, 0, ',', ' ')
        ));

        if (!$appliquer) {
            $this->line('Relancez avec --appliquer pour restaurer.');
        }

        return self::SUCCESS;
    }

    /**
     * Applique les restrictions demandées au périmètre de comparaison.
     */
    private function requete($requete)
    {
        if ($this->option('company')) {
            $requete->where('company_id', $this->option('company'));
        }

        if ($this->option('journal')) {
            $ids = CodeJournal::where('code_journal', 'like', $this->option('journal') . '%')->pluck('id');
            $requete->whereIn('code_journal_id', $ids);
        }

        if ($this->option('depuis')) {
            $requete->whereDate('date', '>=', $this->option('depuis'));
        }

        if ($this->option('jusqua')) {
            $requete->whereDate('date', '<=', $this->option('jusqua'));
        }

        return $requete->orderBy('id');
    }
}
