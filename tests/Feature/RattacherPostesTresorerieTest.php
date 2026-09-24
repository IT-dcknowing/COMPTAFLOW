<?php

namespace Tests\Feature;

use App\Models\CodeJournal;
use App\Models\Company;
use App\Models\CompteTresorerie;
use App\Models\EcritureComptable;
use App\Models\ExerciceComptable;
use App\Models\PlanComptable;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Les lignes de banque et de caisse issues d'un import restaient sans poste de
 * trésorerie : leurs comptes n'apparaissaient donc ni dans le module
 * Trésorerie ni dans le rapprochement bancaire.
 */
class RattacherPostesTresorerieTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;
    private User $user;
    private ExerciceComptable $exercice;
    private CodeJournal $journal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');

        $this->company = Company::create([
            'company_name' => 'Import SARL',
            'activity' => 'Test',
            'juridique_form' => 'SARL',
            'email_adresse' => 'import@test.ci',
        ]);

        $this->user = User::factory()->create(['company_id' => $this->company->id]);
        $this->actingAs($this->user);

        $this->exercice = ExerciceComptable::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'date_debut' => Carbon::now()->startOfYear()->toDateString(),
            'date_fin' => Carbon::now()->endOfYear()->toDateString(),
            'is_active' => true,
            'libelle' => 'Exercice ' . Carbon::now()->year,
        ]);

        $this->journal = CodeJournal::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'code_journal' => 'CAI',
            'intitule' => 'Caisse',
            'traitement_analytique' => 0,
        ]);
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

    private function ligne(PlanComptable $compte, float $debit, float $credit): EcritureComptable
    {
        return EcritureComptable::create([
            'company_id' => $this->company->id,
            'code_journal_id' => $this->journal->id,
            'user_id' => $this->user->id,
            'exercices_comptables_id' => $this->exercice->id,
            'date' => Carbon::now()->startOfYear()->addMonth()->toDateString(),
            'n_saisie' => 'ECR-000001',
            'description_operation' => 'Import',
            'plan_comptable_id' => $compte->id,
            'debit' => $debit,
            'credit' => $credit,
            'statut' => 'approved',
        ]);
    }

    public function test_les_lignes_de_caisse_sans_poste_en_recoivent_un(): void
    {
        $caisse = $this->compte('57100000', 'CAISSE PRINCIPALE');
        $ligne = $this->ligne($caisse, 50000, 0);

        $this->assertNull($ligne->poste_tresorerie_id);
        $this->assertSame(0, CompteTresorerie::where('company_id', $this->company->id)->count());

        $this->artisan('tresorerie:rattacher-postes', [
            '--company' => $this->company->id,
            '--appliquer' => true,
        ])->assertSuccessful();

        $poste = CompteTresorerie::where('company_id', $this->company->id)->first();

        $this->assertNotNull($poste, 'Le poste doit être créé, comme à la saisie manuelle.');
        $this->assertSame('CAISSE PRINCIPALE', $poste->name);
        $this->assertSame($caisse->id, $poste->plan_comptable_id);
        $this->assertSame($poste->id, $ligne->fresh()->poste_tresorerie_id);
    }

    public function test_la_simulation_ne_modifie_rien(): void
    {
        $caisse = $this->compte('57100000', 'CAISSE');
        $ligne = $this->ligne($caisse, 50000, 0);

        $this->artisan('tresorerie:rattacher-postes', ['--company' => $this->company->id])
            ->assertSuccessful();

        $this->assertNull($ligne->fresh()->poste_tresorerie_id);
        $this->assertSame(0, CompteTresorerie::where('company_id', $this->company->id)->count());
    }

    public function test_un_poste_deja_choisi_nest_jamais_ecrase(): void
    {
        $banque = $this->compte('52110000', 'BANQUE BOA');

        $choisi = CompteTresorerie::create([
            'company_id' => $this->company->id,
            'name' => 'Ligne de crédit BOA',
            'type' => 'banque',
            'syscohada_line_id' => 'FIN_EMP',
            'solde_initial' => 0,
            'solde_actuel' => 0,
        ]);

        $ligne = $this->ligne($banque, 10000000, 0);
        $ligne->update(['poste_tresorerie_id' => $choisi->id]);

        $this->artisan('tresorerie:rattacher-postes', [
            '--company' => $this->company->id,
            '--appliquer' => true,
        ])->assertSuccessful();

        $this->assertSame($choisi->id, $ligne->fresh()->poste_tresorerie_id);
    }

    public function test_les_comptes_hors_tresorerie_sont_ignores(): void
    {
        $achats = $this->compte('60100000', 'ACHATS');
        $ligne = $this->ligne($achats, 300000, 0);

        $this->artisan('tresorerie:rattacher-postes', [
            '--company' => $this->company->id,
            '--appliquer' => true,
        ])->assertSuccessful();

        $this->assertNull($ligne->fresh()->poste_tresorerie_id);
        $this->assertSame(0, CompteTresorerie::where('company_id', $this->company->id)->count());
    }
}
