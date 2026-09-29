<?php

namespace Tests\Feature;

use App\Models\Cabinet;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Rattacher des personnes à des comptabilités et à des cabinets, et changer
 * son mot de passe.
 */
class SuperAdminAffectationsTest extends TestCase
{
    use DatabaseTransactions;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');

        $this->superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'password' => Hash::make('ancien-mot-de-passe-1'),
        ]);
        $this->actingAs($this->superAdmin);
    }

    private function entreprise(string $nom): Company
    {
        return Company::create([
            'company_name' => $nom,
            'activity' => 'Test',
            'juridique_form' => 'SARL',
            'email_adresse' => strtolower(str_replace(' ', '', $nom)) . uniqid() . '@test.ci',
        ]);
    }

    private function cabinet(): Cabinet
    {
        return Cabinet::create([
            'nom' => 'Cabinet Gore',
            'code' => 'CAB-' . uniqid(),
            'user_id' => User::factory()->create()->id,
        ]);
    }

    public function test_une_personne_connue_est_rattachee_a_une_comptabilite(): void
    {
        $company = $this->entreprise('Dossier Client');
        $collaborateur = User::factory()->create(['email_adresse' => 'jean@societe.ci']);

        $this->post(route('superadmin.affectations.comptabilite'), [
            'company_id' => $company->id,
            'email_adresse' => 'jean@societe.ci',
            'role' => 'comptable',
        ]);

        $this->assertSame(1, DB::table('company_user')
            ->where('company_id', $company->id)->where('user_id', $collaborateur->id)->count());
    }

    public function test_une_adresse_inconnue_cree_le_compte(): void
    {
        $company = $this->entreprise('Dossier Client');

        $this->post(route('superadmin.affectations.comptabilite'), [
            'company_id' => $company->id,
            'email_adresse' => 'nouveau@societe.ci',
            'role' => 'comptable',
            'name' => 'Jean',
            'last_name' => 'DUPONT',
        ]);

        $cree = User::where('email_adresse', 'nouveau@societe.ci')->first();

        $this->assertNotNull($cree, 'Le compte doit être créé.');
        $this->assertSame('Jean', $cree->name);
        $this->assertSame(1, DB::table('company_user')
            ->where('company_id', $company->id)->where('user_id', $cree->id)->count());
    }

    public function test_un_rattachement_en_double_est_refuse(): void
    {
        $company = $this->entreprise('Dossier Client');
        User::factory()->create(['email_adresse' => 'jean@societe.ci']);

        $donnees = [
            'company_id' => $company->id,
            'email_adresse' => 'jean@societe.ci',
            'role' => 'comptable',
        ];

        $this->post(route('superadmin.affectations.comptabilite'), $donnees);
        $this->post(route('superadmin.affectations.comptabilite'), $donnees);

        $this->assertSame(1, DB::table('company_user')->where('company_id', $company->id)->count());
    }

    public function test_retirer_un_acces_coupe_aussi_le_rattachement_historique(): void
    {
        $company = $this->entreprise('Dossier Client');
        $collaborateur = User::factory()->create([
            'email_adresse' => 'jean@societe.ci',
            'company_id' => $company->id,
        ]);

        $this->post(route('superadmin.affectations.comptabilite'), [
            'company_id' => $company->id,
            'email_adresse' => 'jean@societe.ci',
            'role' => 'comptable',
        ]);

        $this->delete(route('superadmin.affectations.comptabilite.retirer'), [
            'company_id' => $company->id,
            'user_id' => $collaborateur->id,
        ]);

        $this->assertSame(0, DB::table('company_user')
            ->where('company_id', $company->id)->where('user_id', $collaborateur->id)->count());
        $this->assertNull($collaborateur->fresh()->company_id,
            'Sans cela, la personne continue de voir le dossier par users.company_id.');
    }

    public function test_une_personne_est_rattachee_a_un_cabinet(): void
    {
        $cabinet = $this->cabinet();

        $this->post(route('superadmin.affectations.cabinet'), [
            'cabinet_id' => $cabinet->id,
            'email_adresse' => 'membre@cabinet.ci',
        ]);

        $cree = User::where('email_adresse', 'membre@cabinet.ci')->first();

        $this->assertNotNull($cree);
        $this->assertSame(1, DB::table('cabinet_user')
            ->where('cabinet_id', $cabinet->id)->where('user_id', $cree->id)->count());
    }

    public function test_le_cabinet_ne_donne_acces_a_aucune_comptabilite(): void
    {
        $cabinet = $this->cabinet();
        $dossier = $this->entreprise('Dossier Du Cabinet');
        $dossier->update(['cabinet_id' => $cabinet->id]);

        $this->post(route('superadmin.affectations.cabinet'), [
            'cabinet_id' => $cabinet->id,
            'email_adresse' => 'membre@cabinet.ci',
        ]);

        $membre = User::where('email_adresse', 'membre@cabinet.ci')->first();

        // Il appartient au cabinet, mais ne voit rien tant qu'on ne lui a rien affecté.
        $this->assertSame(0, DB::table('company_user')->where('user_id', $membre->id)->count());

        $reponse = $this->actingAs($membre)->get(route('accountant.space.switch', $dossier->id));
        $reponse->assertRedirect(route('accountant.space'));
    }

    public function test_le_mot_de_passe_change(): void
    {
        $this->post(route('superadmin.mot_de_passe.enregistrer'), [
            'mot_de_passe_actuel' => 'ancien-mot-de-passe-1',
            'nouveau' => 'nouveau-secret-2026',
            'nouveau_confirmation' => 'nouveau-secret-2026',
        ]);

        $this->assertTrue(Hash::check('nouveau-secret-2026', $this->superAdmin->fresh()->password));
    }

    public function test_un_mauvais_mot_de_passe_actuel_refuse_le_changement(): void
    {
        $this->post(route('superadmin.mot_de_passe.enregistrer'), [
            'mot_de_passe_actuel' => 'ce-nest-pas-le-bon',
            'nouveau' => 'nouveau-secret-2026',
            'nouveau_confirmation' => 'nouveau-secret-2026',
        ])->assertSessionHasErrors('mot_de_passe_actuel');

        $this->assertTrue(Hash::check('ancien-mot-de-passe-1', $this->superAdmin->fresh()->password));
    }

    public function test_les_deux_saisies_doivent_correspondre(): void
    {
        $this->post(route('superadmin.mot_de_passe.enregistrer'), [
            'mot_de_passe_actuel' => 'ancien-mot-de-passe-1',
            'nouveau' => 'nouveau-secret-2026',
            'nouveau_confirmation' => 'autre-chose-2026',
        ])->assertSessionHasErrors('nouveau');

        $this->assertTrue(Hash::check('ancien-mot-de-passe-1', $this->superAdmin->fresh()->password));
    }

    public function test_le_nouveau_ne_peut_pas_etre_lancien(): void
    {
        $this->post(route('superadmin.mot_de_passe.enregistrer'), [
            'mot_de_passe_actuel' => 'ancien-mot-de-passe-1',
            'nouveau' => 'ancien-mot-de-passe-1',
            'nouveau_confirmation' => 'ancien-mot-de-passe-1',
        ])->assertSessionHasErrors('nouveau');
    }
}
