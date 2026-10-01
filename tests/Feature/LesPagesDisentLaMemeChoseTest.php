<?php

namespace Tests\Feature;

use App\Models\Cabinet;
use App\Models\Company;
use App\Models\User;
use App\Services\Rattachements;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Quatre écrans, une seule vérité.
 *
 * Le constat de départ : YAN venait d'ouvrir une entreprise, et la gestion des
 * utilisateurs affichait « N/A » dans sa colonne entreprise — il semblait
 * n'avoir rien fait. La gestion des entités comptait zéro utilisateur sur ce
 * même dossier. Son espace, lui, le voyait très bien.
 *
 * La raison : chaque page lisait une table différente. Trois rattachements
 * existent, et les trois comptent — `companies.user_id` (le créateur, qui est
 * le premier), `company_user` (ceux à qui on a confié le dossier) et
 * `users.company_id` (l'ancien rattachement). Ils sont désormais lus au même
 * endroit, par le service Rattachements.
 */
class LesPagesDisentLaMemeChoseTest extends TestCase
{
    use DatabaseTransactions;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');

        $this->superAdmin = User::factory()->create(['role' => 'super_admin']);
    }

    public function test_celui_qui_cree_un_dossier_y_figure_sur_les_quatre_ecrans(): void
    {
        $yan = User::factory()->create([
            'role' => null, 'name' => 'Yan', 'last_name' => 'KOUAME',
            'email_adresse' => 'yan@societe.ci', 'pack' => 'cabinet',
        ]);

        // Il ouvre son dossier depuis son espace : aucune ligne n'est posée sur
        // users.company_id, et c'est précisément là que les écrans divergeaient.
        $this->actingAs($yan)->post(route('accountant.space.company.store'), [
            'company_name' => 'Entreprise de Yan',
            'activity' => 'Commerce',
            'juridique_form' => 'SARL',
        ])->assertRedirect();

        $dossier = Company::where('company_name', 'Entreprise de Yan')->first();
        $this->assertNotNull($dossier);
        $this->assertNull($yan->fresh()->company_id, 'La colonne historique reste vide.');

        // 1. La règle elle-même
        $this->assertContains($dossier->id, Rattachements::comptabilitesDe($yan));
        $this->assertTrue(Rattachements::personnesDe($dossier)->contains('id', $yan->id));
        $this->assertSame(Rattachements::CREATEUR, Rattachements::roleSur($dossier, $yan));

        // 2. Son espace
        $this->actingAs($yan)->get(route('accountant.space', ['page' => 'companies']))
            ->assertOk()->assertSee('Entreprise de Yan', false);

        // 3. La gestion des utilisateurs : plus de « N/A »
        $this->actingAs($this->superAdmin)->get(route('superadmin.users', ['search' => 'yan@societe.ci']))
            ->assertOk()
            ->assertSee('Entreprise de Yan', false);

        // 4. La page d'affectations
        $this->actingAs($this->superAdmin)->get(route('superadmin.affectations'))
            ->assertOk()
            ->assertSee('Entreprise de Yan', false)
            ->assertSee('yan@societe.ci', false);
    }

    public function test_la_gestion_des_entites_compte_le_createur(): void
    {
        $createur = User::factory()->create(['role' => null, 'pack' => 'cabinet']);

        $dossier = Company::create([
            'company_name' => 'Dossier Compte', 'activity' => 'Test',
            'juridique_form' => 'SARL', 'user_id' => $createur->id,
        ]);

        $this->assertSame(0, $dossier->users()->count(),
            'users.company_id ne dit rien : la page annonçait donc zéro.');
        $this->assertSame(1, Rattachements::personnesDe($dossier)->count());

        $this->actingAs($this->superAdmin)->get(route('superadmin.entities'))
            ->assertOk()
            ->assertSee($createur->email_adresse, false);
    }

    public function test_le_filtre_par_entreprise_trouve_le_createur(): void
    {
        $createur = User::factory()->create([
            'role' => null, 'email_adresse' => 'createur@societe.ci',
        ]);

        $dossier = Company::create([
            'company_name' => 'Dossier Filtre', 'activity' => 'Test',
            'juridique_form' => 'SARL', 'user_id' => $createur->id,
        ]);

        $etranger = User::factory()->create(['email_adresse' => 'etranger@ailleurs.ci']);

        $this->actingAs($this->superAdmin)
            ->get(route('superadmin.users', ['company_id' => $dossier->id]))
            ->assertOk()
            ->assertSee('createur@societe.ci', false)
            ->assertDontSee('etranger@ailleurs.ci', false);
    }

    public function test_un_compte_sans_comptabilite_est_dit_collaborateur(): void
    {
        // « Aucun rôle » se lisait comme un compte vide, voire cassé. Personne
        // n'est rien : il appartient à la maison, et l'accès aux dossiers ne
        // lui est pas donné d'office. Il sera administrateur du premier
        // dossier qu'il ouvrira.
        $nouveau = User::factory()->create([
            'role' => null, 'email_adresse' => 'nouveau@societe.ci',
        ]);

        $this->assertSame('Collaborateur', Rattachements::libelleDuRole($nouveau));

        $this->actingAs($this->superAdmin)
            ->get(route('superadmin.users', ['role' => 'aucun']))
            ->assertOk()
            ->assertSee('nouveau@societe.ci', false)
            ->assertSee('Collaborateur', false);
    }

    public function test_celui_qui_tient_un_dossier_est_dit_administrateur(): void
    {
        $tenant = User::factory()->create(['role' => null]);

        Company::create([
            'company_name' => 'Dossier Tenu', 'activity' => 'Test',
            'juridique_form' => 'SARL', 'user_id' => $tenant->id,
        ]);

        $this->assertSame('Administrateur de ses comptabilités',
            Rattachements::libelleDuRole($tenant));
    }

    public function test_le_role_dans_le_cabinet_se_lit_a_lecran(): void
    {
        $gerant = User::factory()->create([
            'role' => 'admin', 'pack' => 'cabinet', 'email_adresse' => 'gerant@dcknowing.ci',
        ]);

        $cabinet = Cabinet::create([
            'nom' => 'DC-KNOWING', 'code' => 'CAB-' . uniqid(), 'user_id' => $gerant->id,
        ]);

        $kablan = User::factory()->create(['email_adresse' => 'kablan@dcknowing.ci']);

        foreach ([[$gerant->id, 'gerant'], [$kablan->id, 'admin']] as [$userId, $role]) {
            DB::table('cabinet_user')->insert([
                'cabinet_id' => $cabinet->id, 'user_id' => $userId, 'role' => $role,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->actingAs($this->superAdmin)->get(route('superadmin.affectations'))
            ->assertOk()
            ->assertSee('gérant', false)
            ->assertSee('admin du cabinet', false)
            ->assertSee('kablan@dcknowing.ci', false);
    }

    public function test_un_admin_du_cabinet_nest_pas_le_gerant(): void
    {
        $gerant = User::factory()->create([
            'role' => 'admin', 'pack' => 'cabinet', 'email_adresse' => 'constant@dcknowing.ci',
        ]);

        $cabinet = Cabinet::create([
            'nom' => 'DC-KNOWING', 'code' => 'CAB-' . uniqid(), 'user_id' => $gerant->id,
        ]);

        DB::table('cabinet_user')->insert([
            'cabinet_id' => $cabinet->id, 'user_id' => $gerant->id, 'role' => 'gerant',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $kablan = User::factory()->create([
            'role' => 'admin', 'pack' => 'cabinet', 'email_adresse' => 'kablan@dcknowing.ci',
        ]);
        DB::table('cabinet_user')->insert([
            'cabinet_id' => $cabinet->id, 'user_id' => $kablan->id, 'role' => 'collaborateur',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        Company::create([
            'company_name' => 'Portefeuille du Cabinet', 'activity' => 'Test',
            'juridique_form' => 'SARL', 'cabinet_id' => $cabinet->id,
        ]);

        $this->artisan('cabinets:role', [
            '--cabinet' => 'DC-KNOWING',
            '--email' => 'kablan@dcknowing.ci',
            '--role' => 'admin',
            '--appliquer' => true,
        ])->assertSuccessful();

        $this->assertSame('admin', DB::table('cabinet_user')
            ->where('cabinet_id', $cabinet->id)->where('user_id', $kablan->id)->value('role'));

        $this->assertSame($gerant->id, $cabinet->fresh()->user_id,
            'Le titre ne change pas qui tient la maison.');

        // Un admin du cabinet n'est pas le gérant : le portefeuille ne s'ouvre
        // pas à lui, seuls ses dossiers le font.
        $this->actingAs($kablan)->get(route('accountant.space', ['page' => 'companies']))
            ->assertOk()
            ->assertDontSee('Portefeuille du Cabinet', false);
    }

    public function test_la_commande_refuse_de_degrader_le_gerant_sans_successeur(): void
    {
        $gerant = User::factory()->create(['email_adresse' => 'gerant@dcknowing.ci']);

        $cabinet = Cabinet::create([
            'nom' => 'DC-KNOWING', 'code' => 'CAB-' . uniqid(), 'user_id' => $gerant->id,
        ]);

        $this->artisan('cabinets:role', [
            '--cabinet' => 'DC-KNOWING',
            '--email' => 'gerant@dcknowing.ci',
            '--role' => 'admin',
            '--appliquer' => true,
        ])->expectsOutputToContain('tient le cabinet')->assertFailed();

        $this->assertSame($gerant->id, $cabinet->fresh()->user_id);
    }

    public function test_la_simulation_ne_change_aucun_titre(): void
    {
        $gerant = User::factory()->create();
        $cabinet = Cabinet::create([
            'nom' => 'DC-KNOWING', 'code' => 'CAB-' . uniqid(), 'user_id' => $gerant->id,
        ]);

        $membre = User::factory()->create(['email_adresse' => 'membre@dcknowing.ci']);
        DB::table('cabinet_user')->insert([
            'cabinet_id' => $cabinet->id, 'user_id' => $membre->id, 'role' => 'collaborateur',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('cabinets:role', [
            '--cabinet' => 'DC-KNOWING',
            '--email' => 'membre@dcknowing.ci',
            '--role' => 'admin',
        ])->assertSuccessful();

        $this->assertSame('collaborateur', DB::table('cabinet_user')
            ->where('cabinet_id', $cabinet->id)->where('user_id', $membre->id)->value('role'));
    }

    public function test_designer_le_gerant_fait_de_lancien_un_admin_du_cabinet(): void
    {
        $kablan = User::factory()->create([
            'role' => 'admin', 'pack' => 'cabinet', 'email_adresse' => 'kablan@dcknowing.ci',
        ]);

        $cabinet = Cabinet::create([
            'nom' => 'DC-KNOWING', 'code' => 'CAB-' . uniqid(), 'user_id' => $kablan->id,
        ]);
        DB::table('cabinet_user')->insert([
            'cabinet_id' => $cabinet->id, 'user_id' => $kablan->id, 'role' => 'gerant',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $constant = User::factory()->create([
            'role' => 'admin', 'pack' => 'cabinet', 'email_adresse' => 'constant@dcknowing.ci',
        ]);

        $this->artisan('cabinets:gerant', [
            '--cabinet' => 'DC-KNOWING',
            '--email' => 'constant@dcknowing.ci',
            '--appliquer' => true,
        ])->assertSuccessful();

        $this->assertSame($constant->id, $cabinet->fresh()->user_id);
        $this->assertSame('admin', DB::table('cabinet_user')
            ->where('cabinet_id', $cabinet->id)->where('user_id', $kablan->id)->value('role'),
            "L'ancien gérant garde ses dossiers : il devient admin du cabinet, pas simple collaborateur.");
    }
}
