<?php

namespace Tests\Feature;

use App\Models\CodeJournal;
use App\Models\Company;
use App\Models\EcritureComptable;
use App\Models\ExerciceComptable;
use App\Models\PlanComptable;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Nettoyage des données restées sans entreprise.
 *
 * La règle qui compte : la commande ne doit atteindre QUE des lignes dont le
 * company_id ne désigne plus aucune entreprise. Un dossier vivant doit rester
 * intact, même voisin d'un dossier disparu.
 */
class PurgerLesDossiersOrphelinsTest extends TestCase
{
    use DatabaseTransactions;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
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

    private function comptabilite(int $companyId, int $lignes = 3): void
    {
        session(['current_company_id' => $companyId]);

        $exercice = ExerciceComptable::create([
            'company_id' => $companyId, 'user_id' => $this->user->id,
            'intitule' => 'EXERCICE 2026', 'date_debut' => '2026-01-01',
            'date_fin' => '2026-12-31', 'is_active' => true,
        ]);

        $journal = CodeJournal::create([
            'company_id' => $companyId, 'user_id' => $this->user->id,
            'code_journal' => 'ACH1', 'intitule' => 'Achats', 'traitement_analytique' => 0,
        ]);

        $compte = PlanComptable::create([
            'company_id' => $companyId, 'user_id' => $this->user->id,
            'numero_de_compte' => '60100000', 'intitule' => 'ACHATS',
        ]);

        for ($i = 0; $i < $lignes; $i++) {
            EcritureComptable::create([
                'company_id' => $companyId, 'code_journal_id' => $journal->id,
                'user_id' => $this->user->id, 'exercices_comptables_id' => $exercice->id,
                'date' => '2026-03-01', 'n_saisie' => 'ECR-0000' . $i,
                'description_operation' => 'Test', 'plan_comptable_id' => $compte->id,
                'debit' => 1000, 'credit' => 0, 'statut' => 'approved',
            ]);
        }
    }

    /**
     * Fabrique l'état orphelin sur une table qui le permet.
     *
     * Toutes les tables ne portent pas la même contrainte : `liasse_data` a un
     * simple `company_id`, sans clé étrangère. C'est précisément dans ces
     * tables-là que des lignes survivent à la suppression de leur entreprise —
     * et c'est pour elles que la commande existe.
     */
    private function liasseOrpheline(int $companyId, int $lignes = 3): void
    {
        for ($i = 0; $i < $lignes; $i++) {
            DB::table('liasse_data')->insert([
                'company_id' => $companyId,
                'exercice_id' => 1,
                'page_code' => 'NOTE_1',
                'field_code' => 'N1_TEST_' . $i,
                'value' => '1000',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /** Un identifiant d'entreprise qui n'a jamais existé. */
    private function dossierDisparu(): int
    {
        return (int) (Company::max('id') ?? 0) + 5000;
    }

    public function test_les_donnees_dun_dossier_disparu_sont_effacees(): void
    {
        $orphelin = $this->dossierDisparu();
        $this->liasseOrpheline($orphelin, 4);

        $this->assertSame(4, DB::table('liasse_data')->where('company_id', $orphelin)->count());

        $this->artisan('dossiers:purger-orphelins', ['--appliquer' => true])->assertSuccessful();

        $this->assertSame(0, DB::table('liasse_data')->where('company_id', $orphelin)->count());
    }

    public function test_un_dossier_vivant_nest_jamais_touche(): void
    {
        $orphelin = $this->dossierDisparu();
        $this->liasseOrpheline($orphelin, 2);

        $vivant = $this->entreprise('Dossier Vivant');
        $this->comptabilite($vivant->id, 7);
        $this->liasseOrpheline($vivant->id, 5);

        $this->artisan('dossiers:purger-orphelins', ['--appliquer' => true])->assertSuccessful();

        $this->assertSame(7, DB::table('ecriture_comptables')->where('company_id', $vivant->id)->count(),
            'Le dossier vivant doit rester intact.');
        $this->assertSame(5, DB::table('liasse_data')->where('company_id', $vivant->id)->count());
        $this->assertSame(0, DB::table('liasse_data')->where('company_id', $orphelin)->count());
    }

    public function test_la_simulation_neffaces_rien(): void
    {
        $orphelin = $this->dossierDisparu();
        $this->liasseOrpheline($orphelin, 3);

        $this->artisan('dossiers:purger-orphelins')->assertSuccessful();

        $this->assertSame(3, DB::table('liasse_data')->where('company_id', $orphelin)->count());
    }

    public function test_sans_orphelin_la_commande_le_dit(): void
    {
        $vivant = $this->entreprise('Dossier Vivant');
        $this->comptabilite($vivant->id, 2);

        $this->artisan('dossiers:purger-orphelins')
            ->expectsOutputToContain('Aucune donnée orpheline')
            ->assertSuccessful();
    }
}
