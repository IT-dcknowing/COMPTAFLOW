<?php

namespace Tests\Feature;

use App\Models\CodeJournal;
use App\Models\Company;
use App\Models\EcritureComptable;
use App\Models\ExerciceComptable;
use App\Models\PlanComptable;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Rattrapage des reports à nouveau enregistrés avant que le drapeau `is_ran`
 * ne soit assignable.
 */
class MarquerLesReportsANouveauTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;
    private User $user;
    private ExerciceComptable $exercice;
    private CodeJournal $ran;
    private CodeJournal $achats;
    private PlanComptable $compte;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');

        $this->company = Company::create([
            'company_name' => 'Report SARL',
            'activity' => 'Test',
            'juridique_form' => 'SARL',
            'email_adresse' => 'report@test.ci',
        ]);

        $this->user = User::factory()->create(['company_id' => $this->company->id]);

        $this->exercice = ExerciceComptable::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'intitule' => 'EXERCICE 2026',
            'date_debut' => '2026-01-01',
            'date_fin' => '2026-12-31',
            'is_active' => true,
        ]);

        $this->ran = CodeJournal::create([
            'company_id' => $this->company->id, 'user_id' => $this->user->id,
            'code_journal' => 'RAN', 'intitule' => 'Report à nouveau', 'traitement_analytique' => 0,
        ]);

        $this->achats = CodeJournal::create([
            'company_id' => $this->company->id, 'user_id' => $this->user->id,
            'code_journal' => 'ACH1', 'intitule' => 'Achats', 'traitement_analytique' => 0,
        ]);

        $this->compte = PlanComptable::create([
            'company_id' => $this->company->id, 'user_id' => $this->user->id,
            'numero_de_compte' => '57100000', 'intitule' => 'CAISSE',
        ]);

        $this->actingAs($this->user);
        session(['current_company_id' => $this->company->id]);
    }

    private function ligne(CodeJournal $journal, string $numero, string $libelle, ?string $reference = null): EcritureComptable
    {
        return EcritureComptable::create([
            'company_id' => $this->company->id,
            'code_journal_id' => $journal->id,
            'user_id' => $this->user->id,
            'exercices_comptables_id' => $this->exercice->id,
            'date' => '2026-01-01',
            'n_saisie' => $numero,
            'description_operation' => $libelle,
            'reference_piece' => $reference,
            'plan_comptable_id' => $this->compte->id,
            'debit' => 100000,
            'credit' => 0,
            'statut' => 'approved',
            'is_ran' => false,
        ]);
    }

    public function test_les_reports_reconnus_sont_marques(): void
    {
        $report = $this->ligne($this->ran, 'RAN-2025', 'REPORT À NOUVEAU EXERCICE 2025', 'RAN-12');

        $this->artisan('saisies:marquer-reports', [
            '--company' => $this->company->id,
            '--appliquer' => true,
        ])->assertSuccessful();

        $this->assertTrue((bool) $report->fresh()->is_ran);
    }

    public function test_la_simulation_ne_modifie_rien(): void
    {
        $report = $this->ligne($this->ran, 'RAN-2025', 'REPORT À NOUVEAU EXERCICE 2025', 'RAN-12');

        $this->artisan('saisies:marquer-reports', ['--company' => $this->company->id])
            ->assertSuccessful();

        $this->assertFalse((bool) $report->fresh()->is_ran);
    }

    public function test_une_ecriture_ordinaire_nest_jamais_marquee(): void
    {
        $achat = $this->ligne($this->achats, 'ECR-010126-000001', 'ACHAT DE CARBURANT');

        $this->artisan('saisies:marquer-reports', [
            '--company' => $this->company->id,
            '--appliquer' => true,
        ])->assertSuccessful();

        $this->assertFalse((bool) $achat->fresh()->is_ran);
    }

    public function test_un_seul_signe_ne_suffit_pas(): void
    {
        // Le libellé parle de report, mais le journal est celui des achats et
        // le numéro est ordinaire : trop peu pour trancher.
        $douteuse = $this->ligne($this->achats, 'ECR-010126-000002', 'REPORT DE CHARGES SUR 2027');

        $this->artisan('saisies:marquer-reports', [
            '--company' => $this->company->id,
            '--appliquer' => true,
        ])->assertSuccessful();

        $this->assertFalse((bool) $douteuse->fresh()->is_ran);
    }

    public function test_un_journal_renomme_reste_reconnu(): void
    {
        // Journal d'opérations diverses, mais numéro ET libellé de report.
        $report = $this->ligne($this->achats, 'RAN-2025', 'REPORT À NOUVEAU EXERCICE 2025');

        $this->artisan('saisies:marquer-reports', [
            '--company' => $this->company->id,
            '--appliquer' => true,
        ])->assertSuccessful();

        $this->assertTrue((bool) $report->fresh()->is_ran);
    }

    public function test_une_ligne_deja_marquee_nest_pas_retouchee(): void
    {
        $report = $this->ligne($this->ran, 'RAN-2025', 'REPORT À NOUVEAU EXERCICE 2025');
        $report->update(['is_ran' => true]);

        $this->artisan('saisies:marquer-reports', ['--company' => $this->company->id])
            ->expectsOutputToContain('rien à faire')
            ->assertSuccessful();
    }
}
