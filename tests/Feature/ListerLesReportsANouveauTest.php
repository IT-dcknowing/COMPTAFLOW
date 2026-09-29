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
 * Recensement des reports à nouveau déjà passés.
 */
class ListerLesReportsANouveauTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;
    private User $user;
    private ExerciceComptable $exercice;
    private CodeJournal $ran;
    private PlanComptable $compte;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');

        $this->company = Company::create([
            'company_name' => 'Recense SARL',
            'activity' => 'Test',
            'juridique_form' => 'SARL',
            'email_adresse' => 'recense@test.ci',
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

        $this->compte = PlanComptable::create([
            'company_id' => $this->company->id, 'user_id' => $this->user->id,
            'numero_de_compte' => '57100000', 'intitule' => 'CAISSE',
        ]);

        $this->actingAs($this->user);
        session(['current_company_id' => $this->company->id]);
    }

    private function ligne(float $debit, float $credit, bool $marque = false): void
    {
        EcritureComptable::create([
            'company_id' => $this->company->id,
            'code_journal_id' => $this->ran->id,
            'user_id' => $this->user->id,
            'exercices_comptables_id' => $this->exercice->id,
            'date' => '2026-01-01',
            'n_saisie' => 'RAN-2025',
            'description_operation' => 'REPORT À NOUVEAU EXERCICE 2025',
            'plan_comptable_id' => $this->compte->id,
            'debit' => $debit,
            'credit' => $credit,
            'statut' => 'approved',
            'is_ran' => $marque,
        ]);
    }

    public function test_un_dossier_sans_report_nest_pas_liste(): void
    {
        $this->artisan('saisies:reports-existants', ['--company' => $this->company->id])
            ->expectsOutputToContain('Aucune comptabilité')
            ->assertSuccessful();
    }

    public function test_un_report_equilibre_et_marque_est_signale_comme_sain(): void
    {
        $this->ligne(500000, 0, true);
        $this->ligne(0, 500000, true);

        $this->artisan('saisies:reports-existants', ['--company' => $this->company->id])
            ->expectsOutputToContain('Recense SARL')
            ->expectsOutputToContain('1 comptabilité(s) ont déjà passé un report à nouveau.')
            ->assertSuccessful();
    }

    public function test_un_report_desequilibre_est_signale_avec_son_ecart(): void
    {
        // Le cas réel : le résultat parti du mauvais côté.
        $this->ligne(29180004, 0, true);
        $this->ligne(0, 17107904, true);

        $this->artisan('saisies:reports-existants', ['--company' => $this->company->id])
            ->expectsOutputToContain('ÉCART 12 072 100')
            ->expectsOutputToContain('ne sont pas équilibrés')
            ->assertSuccessful();
    }

    public function test_un_report_non_marque_est_signale(): void
    {
        $this->ligne(500000, 0, false);
        $this->ligne(0, 500000, false);

        $this->artisan('saisies:reports-existants', ['--company' => $this->company->id])
            ->expectsOutputToContain('non marqué')
            ->expectsOutputToContain('saisies:marquer-reports --appliquer')
            ->assertSuccessful();
    }

    public function test_loption_ne_garde_que_les_desequilibres(): void
    {
        $this->ligne(500000, 0, true);
        $this->ligne(0, 500000, true);

        $this->artisan('saisies:reports-existants', [
            '--company' => $this->company->id,
            '--desequilibres' => true,
        ])->expectsOutputToContain('0 comptabilité(s)')->assertSuccessful();
    }

    public function test_le_recensement_ne_modifie_rien(): void
    {
        $this->ligne(500000, 0, false);
        $this->ligne(0, 500000, false);

        $this->artisan('saisies:reports-existants', ['--company' => $this->company->id])
            ->assertSuccessful();

        $this->assertSame(
            0,
            EcritureComptable::where('company_id', $this->company->id)->where('is_ran', true)->count(),
            'Le recensement ne doit poser aucun drapeau.'
        );
    }
}
