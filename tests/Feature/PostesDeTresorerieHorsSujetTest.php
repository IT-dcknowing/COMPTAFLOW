<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\CompteTresorerie;
use App\Models\PlanComptable;
use App\Models\User;
use App\Traits\HandlesTreasuryPosts;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Tous les comptes de classe 5 ne sont pas des banques.
 *
 * 58 « virements de fonds » est un compte de PASSAGE : l'argent y transite
 * d'un compte à l'autre. 59 porte des dépréciations. Ni l'un ni l'autre ne se
 * rapproche d'un relevé bancaire, et ils n'ont donc rien à faire dans la liste
 * des postes de trésorerie — où ils apparaissaient sous le nom
 * « VIREMENT DE FONDS ».
 */
class PostesDeTresorerieHorsSujetTest extends TestCase
{
    use DatabaseTransactions, HandlesTreasuryPosts;

    private Company $company;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');

        $this->company = Company::create([
            'company_name' => 'Postes SARL',
            'activity' => 'Test',
            'juridique_form' => 'SARL',
            'email_adresse' => 'postes@test.ci',
        ]);

        $this->user = User::factory()->create(['company_id' => $this->company->id]);
        $this->actingAs($this->user);
        session(['current_company_id' => $this->company->id]);
    }

    private function compte(string $numero, string $intitule): PlanComptable
    {
        return PlanComptable::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'numero_de_compte' => $numero,
            'intitule' => $intitule,
        ]);
    }

    public function test_une_caisse_recoit_bien_un_poste(): void
    {
        $caisse = $this->compte('57100000', 'CAISSE');

        $this->assertNotNull($this->resolveTreasuryPost($this->company->id, $caisse->id));
    }

    public function test_un_compte_de_virement_de_fonds_nen_recoit_pas(): void
    {
        $passage = $this->compte('58500000', 'VIREMENT DE FONDS');

        $this->assertNull(
            $this->resolveTreasuryPost($this->company->id, $passage->id),
            "Un compte de passage ne se rapproche d'aucun relevé."
        );
        $this->assertSame(0, CompteTresorerie::where('company_id', $this->company->id)->count());
    }

    public function test_un_compte_de_depreciation_nen_recoit_pas(): void
    {
        $depreciation = $this->compte('59100000', 'DEPRECIATIONS DES TITRES');

        $this->assertNull($this->resolveTreasuryPost($this->company->id, $depreciation->id));
    }

    public function test_le_nettoyage_retire_un_poste_pose_sur_un_compte_de_passage(): void
    {
        $passage = $this->compte('58500000', 'VIREMENT DE FONDS');

        CompteTresorerie::create([
            'company_id' => $this->company->id,
            'name' => 'VIREMENT DE FONDS',
            'type' => 'banque',
            'plan_comptable_id' => $passage->id,
            'solde_initial' => 0,
            'solde_actuel' => 0,
        ]);

        $this->artisan('tresorerie:nettoyer-postes', [
            '--company' => $this->company->id,
            '--appliquer' => true,
        ])->assertSuccessful();

        $this->assertSame(0, CompteTresorerie::where('company_id', $this->company->id)->count());
    }

    public function test_la_simulation_ne_retire_rien(): void
    {
        $passage = $this->compte('58500000', 'VIREMENT DE FONDS');

        CompteTresorerie::create([
            'company_id' => $this->company->id,
            'name' => 'VIREMENT DE FONDS',
            'type' => 'banque',
            'plan_comptable_id' => $passage->id,
            'solde_initial' => 0,
            'solde_actuel' => 0,
        ]);

        $this->artisan('tresorerie:nettoyer-postes', ['--company' => $this->company->id])
            ->assertSuccessful();

        $this->assertSame(1, CompteTresorerie::where('company_id', $this->company->id)->count());
    }

    public function test_un_poste_classe_a_la_main_est_garde(): void
    {
        $passage = $this->compte('58500000', 'COMPTE DE LIAISON');

        CompteTresorerie::create([
            'company_id' => $this->company->id,
            'name' => 'Liaison siège',
            'type' => 'banque',
            'plan_comptable_id' => $passage->id,
            'syscohada_line_id' => 'FIN_EMP',
            'solde_initial' => 0,
            'solde_actuel' => 0,
        ]);

        $this->artisan('tresorerie:nettoyer-postes', [
            '--company' => $this->company->id,
            '--appliquer' => true,
        ])->expectsOutputToContain('gardé')->assertSuccessful();

        $this->assertSame(1, CompteTresorerie::where('company_id', $this->company->id)->count());
    }
}
