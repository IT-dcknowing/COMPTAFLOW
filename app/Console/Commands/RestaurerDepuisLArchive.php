<?php

namespace App\Console\Commands;

use App\Models\ArchivedRecord;
use App\Models\Company;
use App\Services\RestaurationDArchive;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Remettre en place ce qui a ete supprime, depuis le serveur.
 *
 * L'ecran du super administrateur le fait ligne par ligne, et lot par lot.
 * Pour un gros volume — un dossier entier, des milliers d'ecritures — passer
 * par le navigateur n'a pas de sens : cette commande fait le meme travail, en
 * annoncant d'abord ce qui reviendrait.
 *
 * Sans --appliquer, elle ne touche a rien.
 */
class RestaurerDepuisLArchive extends Command
{
    protected $signature = 'archives:restaurer
                            {--lot= : Un identifiant de lot (une suppression groupee)}
                            {--dossier= : Toutes les archives d\'une comptabilite}
                            {--ligne= : Une seule ligne d\'archive, par son identifiant}
                            {--appliquer : Remet en place (sans cette option, simple simulation)}';

    protected $description = "Remet en place des donnees supprimees, depuis l'archive des trente jours";

    public function handle(): int
    {
        DB::connection()->disableQueryLog();

        $appliquer = (bool) $this->option('appliquer');

        $choix = collect(['lot', 'dossier', 'ligne'])
            ->filter(fn ($o) => $this->option($o))
            ->values();

        if ($choix->count() !== 1) {
            $this->error('Choisissez exactement une cible : --lot, --dossier ou --ligne.');
            $this->newLine();
            $this->lesLotsConnus();

            return self::FAILURE;
        }

        $lignes = $this->cibler();

        if ($lignes->isEmpty()) {
            $this->warn('Aucune archive ne correspond, ou tout a deja ete remis en place.');

            return self::SUCCESS;
        }

        if (!$appliquer) {
            $this->warn('Simulation : rien ne sera modifie. Ajoutez --appliquer pour remettre en place.');
        }

        $this->newLine();
        $this->line($lignes->count() . ' ligne(s) d\'archive visee(s).');
        $this->newLine();

        // On annonce par type et par issue : une liste de milliers de lignes
        // n'apprend rien, le decompte des refus si.
        $parType = [];
        $refus = [];
        $possibles = 0;

        foreach ($lignes as $ligne) {
            $type = class_basename($ligne->model_type);
            $parType[$type] = ($parType[$type] ?? 0) + 1;

            $apercu = RestaurationDArchive::apercu($ligne);

            if ($apercu['possible']) {
                $possibles++;
                continue;
            }

            $refus[$apercu['raison']] = ($refus[$apercu['raison']] ?? 0) + 1;
        }

        $this->line('Par type :');

        foreach ($parType as $type => $nombre) {
            $this->line(sprintf('    %-22s %d', $type, $nombre));
        }

        $this->newLine();
        $this->line($possibles . ' ligne(s) peuvent revenir.');

        if ($refus !== []) {
            $this->newLine();
            $this->warn((array_sum($refus)) . ' ligne(s) ne peuvent pas :');

            foreach ($refus as $raison => $nombre) {
                $this->line(sprintf('    %-5s %s', $nombre, $raison));
            }
        }

        if (!$appliquer) {
            $this->newLine();
            $this->warn('Rien n\'a ete modifie. Relancez avec --appliquer.');

            return self::SUCCESS;
        }

        // Les comptabilites passent d'abord : le reste y revient.
        $ordonnees = $lignes->sortBy(fn ($a) => $a->model_type === Company::class ? 0 : 1)->values();

        $remises = 0;
        $barre = $this->output->createProgressBar($ordonnees->count());
        $barre->start();

        foreach ($ordonnees as $ligne) {
            if (RestaurationDArchive::restaurer($ligne)['fait']) {
                $remises++;
            }

            $barre->advance();
        }

        $barre->finish();
        $this->newLine(2);
        $this->info($remises . ' ligne(s) remise(s) en place.');

        return self::SUCCESS;
    }

    /** Les archives visees par les options. */
    private function cibler()
    {
        $query = ArchivedRecord::whereNull('restored_at');

        if ($this->option('ligne')) {
            return $query->whereKey($this->option('ligne'))->get();
        }

        if ($this->option('lot')) {
            return $query->where('batch_id', $this->option('lot'))->get();
        }

        return $query->where('company_id', (int) $this->option('dossier'))->get();
    }

    /** De quoi choisir : les lots les plus gros, et leur dossier. */
    private function lesLotsConnus(): void
    {
        $lots = ArchivedRecord::whereNull('restored_at')
            ->whereNotNull('batch_id')
            ->selectRaw('batch_id, company_id, COUNT(*) as lignes, MAX(deleted_at) as quand')
            ->groupBy('batch_id', 'company_id')
            ->orderByDesc('lignes')
            ->limit(20)
            ->get();

        if ($lots->isEmpty()) {
            $this->line('Aucun lot a remettre en place.');

            return;
        }

        $noms = Company::withTrashed()->pluck('company_name', 'id');

        $this->line('Les vingt plus gros lots encore absents :');

        foreach ($lots as $l) {
            $this->line(sprintf('    %-38s %-30s %6d  %s',
                $l->batch_id,
                mb_strimwidth($noms[$l->company_id] ?? ('dossier #' . $l->company_id), 0, 30, '...'),
                $l->lignes,
                $l->quand));
        }
    }
}
