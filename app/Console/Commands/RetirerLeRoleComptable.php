<?php

namespace App\Console\Commands;

use App\Models\Company;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Retire le role « comptable », qui ne servait que de remplissage.
 *
 * La colonne `users.role` exigeait une valeur. Faute de mieux, on y posait
 * « comptable » : une personne qu'on venait de creer, rattachee a aucune
 * comptabilite, s'affichait donc partout comme comptable alors qu'elle ne
 * pouvait rien faire. Le titre annoncait des droits que le compte n'avait pas.
 *
 * Desormais un compte peut n'avoir aucun role, et ce qu'il peut faire se lit
 * sur la comptabilite : celui qui ouvre un dossier en est l'administrateur,
 * celui a qui on en confie un n'a que les cases cochees.
 *
 * Cette commande vide donc le role de ces comptes. Avant de le vider, elle
 * s'assure que RIEN n'est perdu : pour chaque comptabilite qu'une personne a
 * ouverte, elle pose la ligne de liaison « administrateur » qui manquait. Les
 * dossiers crees avant la table pivot n'en avaient pas, et c'est le role global
 * qui tenait lieu de titre.
 *
 * Les administrateurs et les super administrateurs ne sont pas touches.
 */
class RetirerLeRoleComptable extends Command
{
    protected $signature = 'comptes:role-comptable
                            {--appliquer : Vide le role (sans cette option, simple constat)}';

    protected $description = "Retire le role « comptable » des comptes qui le portent, sans leur retirer un seul acces";

    public function handle(): int
    {
        DB::connection()->disableQueryLog();

        $appliquer = (bool) $this->option('appliquer');

        if (!$appliquer) {
            $this->warn('Simple constat : rien ne sera modifie. Ajoutez --appliquer pour enregistrer.');
        }

        $concernes = User::where('role', 'comptable')
            ->orderBy('last_name')->orderBy('name')
            ->get(['id', 'name', 'last_name', 'email_adresse']);

        if ($concernes->isEmpty()) {
            $this->newLine();
            $this->info('Aucun compte ne porte le role « comptable » : rien a faire.');
            return self::SUCCESS;
        }

        $this->newLine();
        $this->line($concernes->count() . ' compte(s) portent le role « comptable » :');
        $this->newLine();

        $liaisonsAPoser = [];

        foreach ($concernes as $personne) {
            $siennes = Company::where('user_id', $personne->id)->pluck('id');
            $confiees = DB::table('company_user')->where('user_id', $personne->id)->pluck('company_id');

            // Les dossiers qu'elle a ouverts sans ligne de liaison : c'est la
            // seule chose a reparer avant de vider le role.
            $sansLiaison = $siennes->diff($confiees);

            foreach ($sansLiaison as $companyId) {
                $liaisonsAPoser[] = ['company_id' => $companyId, 'user_id' => $personne->id];
            }

            $this->line(sprintf('    %-30s %-34s %s',
                mb_strimwidth(trim($personne->name . ' ' . $personne->last_name), 0, 30, '...'),
                $personne->email_adresse,
                $siennes->count() === 0 && $confiees->count() === 0
                    ? 'aucune comptabilite : aucun role'
                    : sprintf('%d ouverte(s), %d confiee(s)%s',
                        $siennes->count(), $confiees->count(),
                        $sansLiaison->count()
                            ? ' — ' . $sansLiaison->count() . ' liaison(s) admin a poser'
                            : '')));
        }

        $this->newLine();
        $this->line('Ce que la commande fait :');
        $this->line('    - pose ' . count($liaisonsAPoser) . ' ligne(s) « administrateur » sur les dossiers ouverts');
        $this->line('      sans liaison, pour qu\'aucun acces ne depende plus du role global ;');
        $this->line('    - vide ensuite le role de ces ' . $concernes->count() . ' compte(s).');
        $this->newLine();
        $this->line('Aucun acces n\'est retire. Les habilitations deja enregistrees restent.');

        if (!$appliquer) {
            $this->newLine();
            $this->warn('Rien n\'a ete modifie. Relancez avec --appliquer.');
            return self::SUCCESS;
        }

        DB::transaction(function () use ($liaisonsAPoser, $concernes) {
            foreach (array_chunk($liaisonsAPoser, 200) as $lot) {
                DB::table('company_user')->insert(array_map(fn ($l) => $l + [
                    'role' => 'admin',
                    'created_at' => now(),
                    'updated_at' => now(),
                ], $lot));
            }

            User::whereIn('id', $concernes->pluck('id'))->update(['role' => null]);
        });

        $this->newLine();
        $this->info('C\'est fait : ' . $concernes->count() . ' compte(s) sans role, '
            . count($liaisonsAPoser) . ' liaison(s) administrateur posee(s).');

        return self::SUCCESS;
    }
}
