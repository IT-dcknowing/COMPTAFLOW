<?php

namespace Tests\Feature;

use App\Models\Cabinet;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Personne ne reçoit le rôle « comptable » par défaut.
 *
 * Le constat de départ : une personne créée par la page de liaison, rattachée à
 * aucune comptabilité, s'affichait partout comme « Comptable ». Le titre
 * annonçait des droits que le compte n'avait pas — il ne pouvait rien faire.
 *
 * La règle retenue : aucun rôle tant que la personne n'a rien ouvert, et
 * administrateur de la comptabilité dès qu'elle en crée une. Le rôle du compte
 * ne dit plus rien ; ce qui compte se lit sur le dossier.
 */
class AucunRoleParDefautTest extends TestCase
{
    use DatabaseTransactions;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');

        $this->superAdmin = User::factory()->create(['role' => 'super_admin']);
    }

    public function test_une_personne_creee_par_la_page_de_liaison_na_aucun_role(): void
    {
        $this->actingAs($this->superAdmin)
            ->post(route('superadmin.affectations.personne'), [
                'name' => 'Yan',
                'last_name' => 'KOUAME',
                'email_adresse' => 'yan@societe.ci',
                'password' => 'motdepasse2026',
            ])->assertRedirect();

        $yan = User::where('email_adresse', 'yan@societe.ci')->first();

        $this->assertNotNull($yan);
        $this->assertNull($yan->role, "On ne colle pas un titre à quelqu'un qui n'a encore rien.");
        $this->assertSame([], $yan->habilitations ?? [], 'Et aucune habilitation.');
    }

    public function test_rattacher_quelquun_a_un_dossier_ne_lui_donne_pas_de_role_global(): void
    {
        $dossier = Company::create([
            'company_name' => 'Client A', 'activity' => 'Test', 'juridique_form' => 'SARL',
        ]);

        // Le rôle demandé porte sur la COMPTABILITÉ. Le recopier sur le compte
        // donnait un titre global à quelqu'un qu'on venait seulement d'affecter.
        $this->actingAs($this->superAdmin)
            ->post(route('superadmin.affectations.comptabilite'), [
                'company_id' => $dossier->id,
                'email_adresse' => 'nouveau@societe.ci',
                'role' => 'admin',
            ])->assertRedirect();

        $nouveau = User::where('email_adresse', 'nouveau@societe.ci')->first();

        $this->assertNotNull($nouveau);
        $this->assertNull($nouveau->role);
        $this->assertSame('admin', DB::table('company_user')
            ->where('company_id', $dossier->id)->where('user_id', $nouveau->id)->value('role'),
            "Le rôle vit sur le dossier, et c'est là qu'il compte.");
    }

    public function test_rattacher_quelquun_a_un_cabinet_ne_lui_donne_pas_de_role(): void
    {
        $cabinet = Cabinet::create([
            'nom' => 'DC-KNOWING', 'code' => 'CAB-' . uniqid(),
            'user_id' => $this->superAdmin->id,
        ]);

        $this->actingAs($this->superAdmin)
            ->post(route('superadmin.affectations.cabinet'), [
                'cabinet_id' => $cabinet->id,
                'email_adresse' => 'membre@societe.ci',
            ])->assertRedirect();

        $this->assertNull(User::where('email_adresse', 'membre@societe.ci')->value('role'));
    }

    public function test_celui_qui_ouvre_une_comptabilite_en_est_ladministrateur(): void
    {
        $personne = User::factory()->create(['role' => null, 'pack' => 'cabinet']);

        $this->actingAs($personne)->post(route('accountant.space.company.store'), [
            'company_name' => 'Son Premier Dossier',
            'activity' => 'Commerce',
            'juridique_form' => 'SARL',
        ])->assertRedirect();

        $dossier = Company::where('company_name', 'Son Premier Dossier')->first();

        $this->assertNotNull($dossier);
        $this->assertSame($personne->id, (int) $dossier->user_id,
            'Celui qui crée la comptabilité en est le premier utilisateur.');
        $this->assertSame('admin', DB::table('company_user')
            ->where('company_id', $dossier->id)->where('user_id', $personne->id)->value('role'));
    }

    public function test_un_compte_sans_role_rejoint_son_espace_a_la_connexion(): void
    {
        // Sans rôle, l'ancien routage renvoyait sur « non autorisé » : la
        // personne était enfermée dehors alors que son espace l'attendait.
        $personne = User::factory()->create([
            'role' => null,
            'email_adresse' => 'sansrole@societe.ci',
            'password' => bcrypt('motdepasse2026'),
            'is_active' => true,
        ]);

        $this->post(route('login.post'), [
            'email_adresse' => 'sansrole@societe.ci',
            'password' => 'motdepasse2026',
        ])->assertRedirect(route('accountant.space'));

        $this->assertAuthenticatedAs($personne);
    }

    public function test_la_commande_vide_le_role_sans_retirer_un_seul_acces(): void
    {
        $ancien = User::factory()->create(['role' => 'comptable']);

        // Un dossier ouvert AVANT la table pivot : aucune ligne de liaison, et
        // c'est le rôle global qui tenait lieu de titre.
        $sien = Company::create([
            'company_name' => 'Dossier Ancien', 'activity' => 'Test',
            'juridique_form' => 'SARL', 'user_id' => $ancien->id,
        ]);

        $this->artisan('comptes:role-comptable', ['--appliquer' => true])->assertSuccessful();

        $this->assertNull($ancien->fresh()->role);
        $this->assertSame('admin', DB::table('company_user')
            ->where('company_id', $sien->id)->where('user_id', $ancien->id)->value('role'),
            "La liaison manquante est posée AVANT que le rôle ne parte : aucun accès ne se perd.");
    }

    public function test_la_simulation_ne_vide_aucun_role(): void
    {
        $ancien = User::factory()->create(['role' => 'comptable']);

        $this->artisan('comptes:role-comptable')->assertSuccessful();

        $this->assertSame('comptable', $ancien->fresh()->role);
    }

    public function test_un_compte_cree_par_adresse_peut_se_connecter(): void
    {
        // Le vrai barrage : le mot de passe etait aleatoire et jamais affiche.
        // Personne ne le connaissait, la connexion repondait « identifiants
        // incorrects », et changer le role n'y changeait rien.
        $dossier = Company::create([
            'company_name' => 'Client B', 'activity' => 'Test', 'juridique_form' => 'SARL',
        ]);

        $this->actingAs($this->superAdmin)
            ->post(route('superadmin.affectations.comptabilite'), [
                'company_id' => $dossier->id,
                'email_adresse' => 'brou@dc-knowing.com',
                'role' => 'admin',
            ])->assertRedirect();

        auth()->logout();

        $this->post(route('login.post'), [
            'email_adresse' => 'brou@dc-knowing.com',
            'password' => \App\Http\Controllers\Super\SuperAdminAffectationController::MOT_DE_PASSE_PROVISOIRE,
        ])->assertRedirect(route('accountant.space'));

        $this->assertAuthenticated();
    }

    public function test_donner_un_mot_de_passe_rouvre_la_connexion(): void
    {
        $bloque = User::factory()->create([
            'email_adresse' => 'bloque@dc-knowing.com',
            'password' => bcrypt(Str::random(32)),
            'is_active' => false,
        ]);

        $this->actingAs($this->superAdmin)
            ->post(route('superadmin.affectations.mot_de_passe'), [
                'user_id' => $bloque->id,
                'password' => 'motdepasse2026',
            ])->assertRedirect();

        auth()->logout();

        // Le compte etait aussi desactive : un mot de passe neuf sur un compte
        // ferme n'aurait rien ouvert.
        $this->post(route('login.post'), [
            'email_adresse' => 'bloque@dc-knowing.com',
            'password' => 'motdepasse2026',
        ])->assertRedirect(route('accountant.space'));

        $this->assertAuthenticatedAs($bloque->fresh());
    }

    public function test_le_diagnostic_nomme_le_mot_de_passe_pose_doffice(): void
    {
        User::factory()->create([
            'email_adresse' => 'pose@dc-knowing.com',
            'password' => bcrypt(\App\Http\Controllers\Super\SuperAdminAffectationController::MOT_DE_PASSE_PROVISOIRE),
        ]);

        $this->artisan('comptes:diagnostic', ['--email' => 'pose@dc-knowing.com'])
            ->expectsOutputToContain('pose d')
            ->assertSuccessful();
    }

    public function test_le_diagnostic_dit_quand_le_compte_est_desactive(): void
    {
        User::factory()->create([
            'email_adresse' => 'ferme@dc-knowing.com',
            'is_active' => false,
        ]);

        $this->artisan('comptes:diagnostic', ['--email' => 'ferme@dc-knowing.com'])
            ->expectsOutputToContain('DESACTIVE')
            ->assertFailed();
    }

    public function test_une_personne_sans_comptabilite_est_dite_collaborateur(): void
    {
        // « Aucun rôle » se lisait comme un compte vide. Personne n'est rien :
        // il appartient à la maison, et l'accès aux dossiers ne lui est pas
        // donné d'office.
        $nouveau = User::factory()->create(['role' => null]);

        $this->assertSame('Collaborateur',
            \App\Services\Rattachements::libelleDuRole($nouveau));
    }

    public function test_modifier_un_super_administrateur_ne_le_degrade_pas(): void
    {
        // Le formulaire ne propose plus « super administrateur » : enregistrer
        // la fiche aurait vide le role, et fait perdre la gouvernance.
        $second = User::factory()->create([
            'role' => 'super_admin',
            'super_admin_type' => 'secondary',
            'email_adresse' => 'second@comptaflow.ci',
        ]);

        $dossier = Company::create([
            'company_name' => 'Dossier Quelconque', 'activity' => 'Test',
            'juridique_form' => 'SARL',
        ]);

        $this->actingAs($this->superAdmin)
            ->put(route('superadmin.users.update', $second->id), [
                'name' => 'Second',
                'last_name' => 'GOUVERNANCE',
                'email_adresse' => 'second@comptaflow.ci',
                'company_id' => $dossier->id,
                'role' => '',
                'is_active' => 1,
            ])->assertRedirect();

        $this->assertSame('super_admin', $second->fresh()->role);
    }

    public function test_les_administrateurs_ne_sont_pas_touches(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->artisan('comptes:role-comptable', ['--appliquer' => true])->assertSuccessful();

        $this->assertSame('admin', $admin->fresh()->role);
        $this->assertSame('super_admin', $this->superAdmin->fresh()->role);
    }
}
