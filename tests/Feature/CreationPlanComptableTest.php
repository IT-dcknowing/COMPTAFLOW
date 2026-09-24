<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\PlanComptable;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Création d'un compte général depuis le bouton « + » de la saisie.
 *
 * Le numéro était complété à la longueur du dossier et coupé en silence quand
 * il dépassait : « 4011760001 » devenait « 40117600 ». Le compte existait, mais
 * pas sous le numéro demandé — il semblait donc ne pas s'être enregistré.
 *
 * La vérification, elle, comparait le numéro brut aux numéros enregistrés, qui
 * sont complétés : « 605300 » ne ressemblait jamais à « 60530000 », et un
 * numéro déjà pris était annoncé libre.
 */
class CreationPlanComptableTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');

        $this->company = Company::create([
            'company_name' => 'Plan SARL',
            'activity' => 'Test',
            'juridique_form' => 'SARL',
            'email_adresse' => 'plan@test.ci',
            'account_digits' => 8,
        ]);

        $this->user = User::factory()->create(['company_id' => $this->company->id]);

        $this->actingAs($this->user);
        session(['current_company_id' => $this->company->id]);
    }

    private function creer(string $numero, string $intitule)
    {
        return $this->postJson(route('plan_comptable.store'), [
            'numero_de_compte' => $numero,
            'intitule' => $intitule,
        ]);
    }

    private function verifier(string $numero): array
    {
        return $this->postJson(route('verifierNumeroCompte'), [
            'numero_de_compte' => $numero,
        ])->assertOk()->json();
    }

    public function test_un_numero_court_est_complete_a_droite(): void
    {
        $this->creer('6053', 'Carburant')->assertOk()->assertJson(['success' => true]);

        $this->assertDatabaseHas('plan_comptables', [
            'company_id' => $this->company->id,
            'numero_de_compte' => '60530000',
        ]);
    }

    public function test_un_numero_trop_long_est_refuse_et_non_tronque(): void
    {
        $reponse = $this->creer('4011760001', 'Fournisseur divers');

        $reponse->assertStatus(422)->assertJson(['success' => false]);
        $this->assertStringContainsString('8 chiffres', $reponse->json('error'));

        // Ni sous le numéro demandé, ni sous une version coupée.
        $this->assertDatabaseMissing('plan_comptables', ['numero_de_compte' => '4011760001']);
        $this->assertDatabaseMissing('plan_comptables', ['numero_de_compte' => '40117600']);
    }

    public function test_la_verification_reconnait_un_numero_deja_pris(): void
    {
        PlanComptable::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'numero_de_compte' => '60530000',
            'intitule' => 'Carburant',
        ]);

        // L'utilisateur tape la forme courte : elle désigne le même compte.
        $avis = $this->verifier('6053');

        $this->assertTrue($avis['exists'], 'Le numéro complété désigne un compte existant.');
        $this->assertSame('60530000', $avis['numero_formatte']);
        $this->assertSame('Carburant', $avis['intitule_existant']);
    }

    public function test_la_verification_annonce_la_forme_finale_dun_numero_libre(): void
    {
        $avis = $this->verifier('6053');

        $this->assertFalse($avis['exists']);
        $this->assertSame('60530000', $avis['numero_formatte']);
        $this->assertSame(8, $avis['longueur']);
        $this->assertFalse($avis['trop_long']);
    }

    public function test_la_verification_signale_un_numero_trop_long(): void
    {
        $avis = $this->verifier('4011760001');

        $this->assertTrue($avis['trop_long']);
        $this->assertSame(8, $avis['longueur']);
    }

    public function test_le_message_nomme_le_compte_qui_bloque(): void
    {
        PlanComptable::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'numero_de_compte' => '60530000',
            'intitule' => 'Carburant',
        ]);

        $erreur = $this->creer('6053', 'Gasoil')->assertStatus(422)->json('error');

        $this->assertStringContainsString('60530000', $erreur);
        $this->assertStringContainsString('Carburant', $erreur);
    }

    public function test_le_compte_va_dans_le_dossier_ouvert_en_mode_switch(): void
    {
        $autre = Company::create([
            'company_name' => 'Dossier client',
            'activity' => 'Test',
            'juridique_form' => 'SARL',
            'email_adresse' => 'client@test.ci',
            'account_digits' => 8,
        ]);

        session(['current_company_id' => $autre->id]);

        $this->creer('6053', 'Carburant')->assertOk();

        $this->assertDatabaseHas('plan_comptables', [
            'company_id' => $autre->id,
            'numero_de_compte' => '60530000',
        ]);
        $this->assertDatabaseMissing('plan_comptables', [
            'company_id' => $this->company->id,
            'numero_de_compte' => '60530000',
        ]);
    }
}
