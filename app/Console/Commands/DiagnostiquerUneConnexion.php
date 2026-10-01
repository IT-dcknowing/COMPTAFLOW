<?php

namespace App\Console\Commands;

use App\Http\Controllers\Super\SuperAdminAffectationController;
use App\Models\Company;
use App\Services\Rattachements;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Pourquoi cette personne n'arrive-t-elle pas a se connecter ?
 *
 * L'ecran de connexion ne repond qu'« identifiants incorrects » — a dessein,
 * pour ne pas dire a un inconnu quelles adresses existent. Mais cote serveur,
 * on peut et on doit le savoir : sans cela, on change le role, les acces, le
 * cabinet, et rien ne bouge, parce que le probleme etait ailleurs.
 *
 * Le cas le plus frequent : un compte cree par simple adresse depuis la page
 * de liaison recevait un mot de passe aleatoire que PERSONNE ne connaissait.
 * Aucun role, aucun acces, aucun cabinet n'y change quoi que ce soit.
 *
 * Avec --mot-de-passe, la commande essaie une proposition et dit si elle passe.
 */
class DiagnostiquerUneConnexion extends Command
{
    protected $signature = 'comptes:diagnostic
                            {--email= : L\'adresse du compte a examiner}
                            {--mot-de-passe= : Un mot de passe a essayer, pour verifier}
                            {--sans-mot-de-passe-connu : Liste tous les comptes dont le mot de passe est celui pose d\'office}';

    protected $description = "Dit pourquoi une connexion est refusee, au lieu du seul « identifiants incorrects »";

    public function handle(): int
    {
        DB::connection()->disableQueryLog();

        if ($this->option('sans-mot-de-passe-connu')) {
            return $this->ceuxQuiOntLeMotDePasseDOffice();
        }

        if (!$this->option('email')) {
            $this->error('Il faut --email, ou --sans-mot-de-passe-connu.');

            return self::FAILURE;
        }

        $user = User::where('email_adresse', $this->option('email'))->first();

        if (!$user) {
            $this->newLine();
            $this->error('Aucun compte ne porte cette adresse.');
            $this->line('La connexion repondra « identifiants incorrects », et c\'est exact.');

            $proches = User::where('email_adresse', 'like', '%' . explode('@', $this->option('email'))[0] . '%')
                ->limit(5)->pluck('email_adresse');

            if ($proches->isNotEmpty()) {
                $this->newLine();
                $this->line('Adresses proches :');

                foreach ($proches as $p) {
                    $this->line('    ' . $p);
                }
            }

            return self::FAILURE;
        }

        $this->newLine();
        $this->line('Compte   : ' . trim($user->name . ' ' . $user->last_name));
        $this->line('Adresse  : ' . $user->email_adresse);
        $this->line('Role     : ' . ($user->role ?: 'aucun (' . Rattachements::libelleDuRole($user) . ')'));
        $this->newLine();

        $barrages = [];

        if (!$user->is_active) {
            $barrages[] = 'Le compte est DESACTIVE (users.is_active = 0). La connexion repond « Compte desactive ».';
        }

        if ($user->is_blocked) {
            $barrages[] = 'Le compte est BLOQUE : ' . ($user->block_reason ?: 'sans raison enregistree') . '.';
        }

        $societe = $user->company_id ? Company::withTrashed()->find($user->company_id) : null;

        if ($societe && $societe->is_blocked) {
            $barrages[] = 'Son entreprise « ' . $societe->company_name . ' » est bloquee.';
        }

        // Le barrage qu'on ne voit jamais : un mot de passe que personne ne
        // detient. Rien a l'ecran ne le distingue d'un mot de passe mal tape.
        $dOffice = Hash::check(SuperAdminAffectationController::MOT_DE_PASSE_PROVISOIRE, $user->password);

        if ($dOffice) {
            $this->warn('Son mot de passe est celui pose d\'office : '
                . SuperAdminAffectationController::MOT_DE_PASSE_PROVISOIRE);
            $this->line('Elle peut se connecter avec, et devrait le changer.');
            $this->newLine();
        }

        if ($this->option('mot-de-passe')) {
            $passe = Hash::check($this->option('mot-de-passe'), $user->password);
            $passe
                ? $this->info('Le mot de passe propose est le BON.')
                : $this->error('Le mot de passe propose ne correspond pas.');
            $this->newLine();
        }

        if ($barrages === []) {
            $this->info('Rien ne barre la connexion de ce compte.');

            if (!$dOffice && !$this->option('mot-de-passe')) {
                $this->newLine();
                $this->line('Reste le mot de passe, que cette commande ne peut pas deviner.');
                $this->line('Un compte cree par simple adresse en avait un aleatoire, jamais');
                $this->line('affiche : personne ne le connaissait. Pour lui en donner un :');
                $this->line('    Super admin > Affectations > Donner un mot de passe');
            }
        } else {
            $this->error('Ce qui barre la connexion :');

            foreach ($barrages as $b) {
                $this->line('    - ' . $b);
            }
        }

        // Ou cette personne atterrit une fois connectee : un espace vide
        // ressemble a s'y meprendre a un refus.
        $dossiers = Rattachements::nomsDesComptabilitesDe($user);

        $this->newLine();
        $this->line('Une fois connectee, elle arrive sur Mon espace et y trouve :');

        if ($dossiers->isEmpty()) {
            $this->line('    aucune comptabilite — elle peut en ouvrir une, et en sera l\'administratrice.');
        } else {
            foreach ($dossiers as $nom) {
                $this->line('    ' . $nom);
            }
        }

        return $barrages === [] ? self::SUCCESS : self::FAILURE;
    }

    /** Les comptes qui portent encore le mot de passe pose d'office. */
    private function ceuxQuiOntLeMotDePasseDOffice(): int
    {
        $comptes = User::whereNotNull('password')
            ->orderBy('last_name')->orderBy('name')
            ->get(['id', 'name', 'last_name', 'email_adresse', 'password', 'is_active']);

        $trouves = $comptes->filter(fn ($u) => Hash::check(
            SuperAdminAffectationController::MOT_DE_PASSE_PROVISOIRE, $u->password
        ));

        $this->newLine();

        if ($trouves->isEmpty()) {
            $this->info('Aucun compte ne porte le mot de passe pose d\'office.');
            $this->newLine();
            $this->line('Les comptes crees par simple adresse AVANT cette correction ont');
            $this->line('un mot de passe aleatoire que personne ne connait : ils ne peuvent');
            $this->line('pas se connecter, et aucun ecran ne le dit. Donnez-leur un mot de');
            $this->line('passe depuis Super admin > Affectations > Donner un mot de passe.');

            return self::SUCCESS;
        }

        $this->warn($trouves->count() . ' compte(s) portent encore le mot de passe pose d\'office ('
            . SuperAdminAffectationController::MOT_DE_PASSE_PROVISOIRE . ') :');

        foreach ($trouves as $u) {
            $this->line(sprintf('    %-30s %-34s %s',
                mb_strimwidth(trim($u->name . ' ' . $u->last_name), 0, 30, '...'),
                $u->email_adresse,
                $u->is_active ? 'actif' : 'DESACTIVE'));
        }

        return self::SUCCESS;
    }
}
