<?php

namespace App\Console\Commands;

use App\Models\Cabinet;
use App\Models\Company;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Désigne qui tient un cabinet.
 *
 * Quand le gérant d'un cabinet est supprimé, le modèle promeut d'office le
 * plus ancien de ses membres : la maison ne reste jamais sans responsable.
 * C'est la bonne règle par défaut, mais elle désigne parfois quelqu'un
 * d'autre que celui qu'on attendait — et le gérant voit tout le portefeuille.
 *
 * Cette commande remet la main dessus, en nommant explicitement la personne.
 * Elle la fait aussi entrer dans le cabinet si elle n'y figurait pas : on ne
 * peut pas tenir une maison sans en être membre.
 */
class DesignerLeGerant extends Command
{
    protected $signature = 'cabinets:gerant
                            {--cabinet= : Le cabinet (identifiant ou nom exact)}
                            {--email= : L\'adresse de la personne qui le tient}
                            {--appliquer : Enregistre la désignation (sans cette option, simple simulation)}';

    protected $description = "Désigne la personne qui tient un cabinet";

    public function handle(): int
    {
        DB::connection()->disableQueryLog();

        if (!$this->option('cabinet') || !$this->option('email')) {
            $this->error('Il faut --cabinet et --email.');
            $this->line('Les cabinets connus :');

            foreach (Cabinet::orderBy('nom')->get(['id', 'nom', 'user_id']) as $c) {
                $g = User::find($c->user_id);
                $this->line(sprintf('    %-5s %-38s  gérant : %s',
                    $c->id, mb_strimwidth($c->nom, 0, 38, '…'),
                    $g ? trim($g->name . ' ' . $g->last_name) . ' (' . $g->email_adresse . ')' : 'aucun'));
            }

            return self::FAILURE;
        }

        $designation = (string) $this->option('cabinet');

        $cabinet = ctype_digit($designation)
            ? Cabinet::find($designation)
            : Cabinet::where('nom', $designation)->first();

        if (!$cabinet) {
            $this->error('Aucun cabinet ne correspond à « ' . $designation . ' ».');
            return self::FAILURE;
        }

        $personne = User::where('email_adresse', $this->option('email'))->first();

        if (!$personne) {
            $this->error('Aucun compte ne porte l\'adresse ' . $this->option('email') . '.');
            return self::FAILURE;
        }

        $ancien = User::find($cabinet->user_id);
        $dossiers = Company::where('cabinet_id', $cabinet->id)->count();

        $this->newLine();
        $this->line('Cabinet  : ' . $cabinet->nom . ' (' . $dossiers . ' dossier(s))');
        $this->line('Gérant   : ' . ($ancien
            ? trim($ancien->name . ' ' . $ancien->last_name) . ' — ' . $ancien->email_adresse
            : 'aucun'));
        $this->line('Devient  : ' . trim($personne->name . ' ' . $personne->last_name)
            . ' — ' . $personne->email_adresse);

        $this->newLine();
        $this->line('Le gérant voit tout le portefeuille du cabinet ; l\'ancien devient');
        $this->line('« admin du cabinet » et garde les dossiers qu\'il a ouverts ou qu\'on');
        $this->line('lui a confiés nommément, et ceux-là seulement.');

        if (!$this->option('appliquer')) {
            $this->newLine();
            $this->warn('Simulation : rien n\'a été modifié. Ajoutez --appliquer pour enregistrer.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($cabinet, $personne) {
            $cabinet->user_id = $personne->id;
            $cabinet->save();

            // On ne tient pas une maison sans en être membre.
            DB::table('cabinet_user')->updateOrInsert(
                ['cabinet_id' => $cabinet->id, 'user_id' => $personne->id],
                ['role' => 'gerant', 'updated_at' => now(), 'created_at' => now()]
            );

            // L'ancien reste membre, et devient « admin du cabinet » : un
            // employé qui gère des comptabilités comme les autres. Le dire
            // « collaborateur » laissait croire qu'on lui avait retiré quelque
            // chose, alors qu'il garde ses dossiers — seul le portefeuille
            // complet, réservé au gérant, lui échappe.
            DB::table('cabinet_user')
                ->where('cabinet_id', $cabinet->id)
                ->where('user_id', '!=', $personne->id)
                ->where('role', 'gerant')
                ->update(['role' => 'admin', 'updated_at' => now()]);
        });

        $this->newLine();
        $this->info('« ' . $cabinet->nom .' » est désormais tenu par '
            . trim($personne->name . ' ' . $personne->last_name) . '.');

        return self::SUCCESS;
    }
}
