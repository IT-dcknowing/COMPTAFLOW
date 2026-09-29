<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\CompteTresorerie;
use App\Models\EcritureComptable;
use App\Models\PlanComptable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Retire les postes de trésorerie créés sur des comptes qui n'en sont pas.
 *
 * Un poste de trésorerie représente une banque ou une caisse : quelque chose
 * qui se rapproche d'un relevé. La création automatique acceptait toute la
 * classe 5, et fabriquait donc aussi un poste « VIREMENT DE FONDS » sur le
 * compte 585 — un compte de PASSAGE, qui ne se rapproche de rien — et sur les
 * dépréciations en 59.
 *
 * La création est corrigée ; cette commande reprend les postes déjà en place.
 * Elle détache d'abord les écritures concernées, puis supprime le poste.
 * Un poste modifié à la main — nom changé, ligne SYSCOHADA renseignée — n'est
 * jamais touché : c'est un choix, et il se respecte.
 */
class NettoyerLesPostesDeTresorerie extends Command
{
    protected $signature = 'tresorerie:nettoyer-postes
                            {--company= : Ne traiter que cette entreprise}
                            {--appliquer : Enregistre le nettoyage (sans cette option, simple simulation)}';

    protected $description = "Retire les postes de trésorerie créés à tort sur les comptes de passage (58) et de dépréciation (59)";

    public function handle(): int
    {
        $appliquer = (bool) $this->option('appliquer');

        DB::connection()->disableQueryLog();

        if (!$appliquer) {
            $this->warn('Mode simulation : rien ne sera modifié. Ajoutez --appliquer pour enregistrer.');
        }

        $this->line('Recherche des postes posés sur un compte de passage ou de dépréciation…');

        $comptesHorsSujet = PlanComptable::query()
            ->when($this->option('company'), fn ($q) => $q->where('company_id', $this->option('company')))
            ->where(fn ($q) => $q->where('numero_de_compte', 'like', '58%')
                ->orWhere('numero_de_compte', 'like', '59%'))
            ->pluck('numero_de_compte', 'id');

        if ($comptesHorsSujet->isEmpty()) {
            $this->info('Aucun compte de passage ni de dépréciation : rien à nettoyer.');
            return self::SUCCESS;
        }

        $postes = CompteTresorerie::query()
            ->when($this->option('company'), fn ($q) => $q->where('company_id', $this->option('company')))
            ->whereIn('plan_comptable_id', $comptesHorsSujet->keys())
            ->get();

        if ($postes->isEmpty()) {
            $this->info('Aucun poste de trésorerie posé sur ces comptes : rien à nettoyer.');
            return self::SUCCESS;
        }

        $noms = Company::whereIn('id', $postes->pluck('company_id')->unique())
            ->pluck('company_name', 'id');

        $retires = 0;
        $gardes = 0;
        $lignesDetachees = 0;

        foreach ($postes->groupBy('company_id') as $companyId => $duDossier) {
            $this->newLine();
            $this->line(sprintf('Entreprise %-4s %s', $companyId,
                mb_strimwidth($noms[$companyId] ?? '?', 0, 40, '…')));

            foreach ($duDossier as $poste) {
                $numero = $comptesHorsSujet[$poste->plan_comptable_id] ?? '?';

                // Un poste que quelqu'un a classé lui-même porte une décision :
                // on ne l'efface pas sans le lui demander.
                if ($poste->syscohada_line_id) {
                    $gardes++;
                    $this->line(sprintf('      %-12s %-30s  gardé : il porte un code SYSCOHADA (%s)',
                        $numero, mb_strimwidth((string) $poste->name, 0, 30, '…'), $poste->syscohada_line_id));
                    continue;
                }

                $lignes = EcritureComptable::where('poste_tresorerie_id', $poste->id)->count();

                $this->warn(sprintf('      %-12s %-30s  à retirer%s',
                    $numero, mb_strimwidth((string) $poste->name, 0, 30, '…'),
                    $lignes > 0 ? sprintf(' (%d ligne(s) à détacher)', $lignes) : ''));

                if ($appliquer) {
                    EcritureComptable::where('poste_tresorerie_id', $poste->id)
                        ->update(['poste_tresorerie_id' => null, 'compte_tresorerie_id' => null]);
                    $poste->delete();
                }

                $retires++;
                $lignesDetachees += $lignes;
            }
        }

        $this->newLine();
        $verbe = $appliquer ? 'retiré(s)' : 'à retirer';
        $this->info("$retires poste(s) $verbe, $lignesDetachees ligne(s) détachée(s).");

        if ($gardes > 0) {
            $this->line("$gardes poste(s) gardé(s) : ils portent un code SYSCOHADA choisi à la main.");
        }

        if (!$appliquer) {
            $this->line('Relancez avec --appliquer pour enregistrer.');
        }

        return self::SUCCESS;
    }
}
