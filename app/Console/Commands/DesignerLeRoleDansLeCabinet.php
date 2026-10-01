<?php

namespace App\Console\Commands;

use App\Models\Cabinet;
use App\Models\Company;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Le titre d'une personne dans un cabinet.
 *
 * Trois titres, et un seul d'entre eux donne quelque chose :
 *
 *   - GÉRANT : il tient la maison, et voit tout le portefeuille. C'est
 *     `cabinets.user_id` qui le décide, et lui seul ; il n'y en a qu'un.
 *   - ADMIN : un employé du cabinet qui gère des comptabilités comme les
 *     autres. Il ne voit que les dossiers qu'il a ouverts ou qu'on lui a
 *     confiés — exactement comme un collaborateur.
 *   - COLLABORATEUR : la même chose, dit autrement.
 *
 * Admin et collaborateur ne se distinguent donc que par le mot affiché : aucun
 * droit ne s'y attache. C'est voulu — le titre décrit la place de la personne
 * dans l'organisation, pas ce que l'application lui ouvre.
 *
 * On ne retire pas le titre de gérant par cette commande : il faudrait désigner
 * son successeur dans le même geste, et c'est le travail de `cabinets:gerant`.
 */
class DesignerLeRoleDansLeCabinet extends Command
{
    protected $signature = 'cabinets:role
                            {--cabinet= : Le cabinet (identifiant ou nom exact)}
                            {--email= : L\'adresse de la personne}
                            {--role=admin : admin ou collaborateur}
                            {--appliquer : Enregistre le titre (sans cette option, simple simulation)}';

    protected $description = "Donne son titre à une personne dans un cabinet (admin, collaborateur)";

    public function handle(): int
    {
        DB::connection()->disableQueryLog();

        $role = (string) $this->option('role');

        if (!in_array($role, ['admin', 'collaborateur'], true)) {
            $this->error('Le titre doit être « admin » ou « collaborateur ».');
            $this->line('Pour désigner celui qui tient la maison : cabinets:gerant.');
            return self::FAILURE;
        }

        if (!$this->option('cabinet') || !$this->option('email')) {
            $this->error('Il faut --cabinet et --email.');
            $this->line('Les membres connus, cabinet par cabinet :');

            foreach (Cabinet::orderBy('nom')->get(['id', 'nom', 'user_id']) as $c) {
                $this->line('    ' . $c->nom . ' (#' . $c->id . ')');

                $membres = DB::table('cabinet_user')
                    ->join('users', 'cabinet_user.user_id', '=', 'users.id')
                    ->where('cabinet_user.cabinet_id', $c->id)
                    ->orderBy('users.last_name')
                    ->get(['cabinet_user.role', 'users.name', 'users.last_name', 'users.email_adresse']);

                foreach ($membres as $m) {
                    $this->line(sprintf('        %-14s %-28s %s',
                        $m->role ?: 'collaborateur',
                        mb_strimwidth(trim($m->name . ' ' . $m->last_name), 0, 28, '…'),
                        $m->email_adresse));
                }
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

        $ancien = DB::table('cabinet_user')
            ->where('cabinet_id', $cabinet->id)->where('user_id', $personne->id)
            ->value('role');

        $estLeGerant = (int) $cabinet->user_id === (int) $personne->id;

        $this->newLine();
        $this->line('Cabinet  : ' . $cabinet->nom);
        $this->line('Personne : ' . trim($personne->name . ' ' . $personne->last_name)
            . ' — ' . $personne->email_adresse);
        $this->line('Titre    : ' . ($ancien ?: 'pas encore membre') . '  →  ' . $role);

        if ($estLeGerant) {
            $this->newLine();
            $this->error('Cette personne tient le cabinet (cabinets.user_id).');
            $this->line('Lui retirer le titre de gérant sans successeur laisserait la maison');
            $this->line('sans responsable. Désignez d\'abord le vrai gérant :');
            $this->line('    php artisan cabinets:gerant --cabinet="' . $cabinet->nom
                . '" --email="adresse@du.gerant" --appliquer');
            $this->line('Elle sera alors rétrogradée, et cette commande pourra la nommer ' . $role . '.');
            return self::FAILURE;
        }

        // Ce que le titre change, et ce qu'il ne change pas : le dire évite
        // d'attendre de cette commande des accès qu'elle n'accorde pas.
        $siens = Company::where('user_id', $personne->id)->count();
        $confies = DB::table('company_user')->where('user_id', $personne->id)->count();

        $this->newLine();
        $this->line('Le titre décrit sa place dans la maison : il n\'ouvre et ne ferme aucun');
        $this->line('dossier. Elle garde ses ' . $siens . ' comptabilité(s) ouverte(s) et les '
            . $confies . ' qu\'on lui a confiée(s).');

        if (!$this->option('appliquer')) {
            $this->newLine();
            $this->warn('Simulation : rien n\'a été modifié. Ajoutez --appliquer pour enregistrer.');
            return self::SUCCESS;
        }

        DB::table('cabinet_user')->updateOrInsert(
            ['cabinet_id' => $cabinet->id, 'user_id' => $personne->id],
            ['role' => $role, 'updated_at' => now(), 'created_at' => now()]
        );

        $this->newLine();
        $this->info(trim($personne->name . ' ' . $personne->last_name)
            . ' est ' . ($role === 'admin' ? 'admin du cabinet' : 'collaborateur')
            . ' « ' . $cabinet->nom . ' ».');

        return self::SUCCESS;
    }
}
