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
use Tests\TestCase;

/**
 * La corbeille des entreprises.
 *
 * Une entreprise supprimée garde sa comptabilité intacte trente jours. Elle
 * disparaît de toutes les listes, mais rien n'est perdu : la remettre en place
 * la restitue entière. Une erreur de clic ne coûte plus une comptabilité.
 */
class CorbeilleEntreprisesTest extends TestCase
{
    use DatabaseTransactions;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');

        $this->superAdmin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($this->superAdmin);
    }

    private function entreprise(string $nom, array $champs = []): Company
    {
        return Company::create(array_merge([
            'company_name' => $nom,
            'activity' => 'Test',
            'juridique_form' => 'SARL',
            'email_adresse' => strtolower(str_replace(' ', '', $nom)) . uniqid() . '@test.ci',
        ], $champs));
    }

    private function comptabilite(Company $company, int $lignes = 3): void
    {
        $user = User::factory()->create(['company_id' => $company->id]);

        $exercice = ExerciceComptable::create([
            'company_id' => $company->id, 'user_id' => $user->id,
            'intitule' => 'EXERCICE 2026', 'date_debut' => '2026-01-01',
            'date_fin' => '2026-12-31', 'is_active' => true,
        ]);

        $journal = CodeJournal::create([
            'company_id' => $company->id, 'user_id' => $user->id,
            'code_journal' => 'ACH1', 'intitule' => 'Achats', 'traitement_analytique' => 0,
        ]);

        $compte = PlanComptable::create([
            'company_id' => $company->id, 'user_id' => $user->id,
            'numero_de_compte' => '60100000', 'intitule' => 'ACHATS',
        ]);

        for ($i = 0; $i < $lignes; $i++) {
            EcritureComptable::create([
                'company_id' => $company->id, 'code_journal_id' => $journal->id,
                'user_id' => $user->id, 'exercices_comptables_id' => $exercice->id,
                'date' => '2026-03-01', 'n_saisie' => 'ECR-0000' . $i,
                'description_operation' => 'Test', 'plan_comptable_id' => $compte->id,
                'debit' => 1000, 'credit' => 0, 'statut' => 'approved',
            ]);
        }
    }

    public function test_la_comptabilite_survit_a_la_suppression(): void
    {
        $company = $this->entreprise('A Supprimer');
        $this->comptabilite($company, 5);

        $this->delete(route('superadmin.companies.destroy', $company->id));

        $this->assertSame(0, Company::where('id', $company->id)->count(),
            "L'entreprise doit disparaître des listes.");
        $this->assertSame(1, Company::onlyTrashed()->where('id', $company->id)->count(),
            'Mais rester en corbeille.');
        $this->assertSame(5, EcritureComptable::where('company_id', $company->id)->count(),
            'Et sa comptabilité doit rester intacte.');
    }

    public function test_remettre_en_place_restitue_tout(): void
    {
        $company = $this->entreprise('A Restaurer');
        $this->comptabilite($company, 4);

        $this->delete(route('superadmin.companies.destroy', $company->id));
        $this->post(route('superadmin.corbeille.restaurer', $company->id));

        $this->assertSame(1, Company::where('id', $company->id)->count());
        $this->assertSame(4, EcritureComptable::where('company_id', $company->id)->count());
        $this->assertSame(1, ExerciceComptable::where('company_id', $company->id)->count());
    }

    public function test_une_filiale_part_et_revient_avec_sa_mere(): void
    {
        $mere = $this->entreprise('Societe Mere');
        $filiale = $this->entreprise('Filiale', ['parent_company_id' => $mere->id]);

        $this->delete(route('superadmin.companies.destroy', $mere->id));
        $this->assertSame(0, Company::where('id', $filiale->id)->count());

        $this->post(route('superadmin.corbeille.restaurer', $mere->id));
        $this->assertSame(1, Company::where('id', $filiale->id)->count());
    }

    public function test_les_comptes_utilisateurs_survivent(): void
    {
        $company = $this->entreprise('A Supprimer');
        $collaborateur = User::factory()->create(['company_id' => $company->id]);

        $this->delete(route('superadmin.companies.destroy', $company->id));

        $apres = User::find($collaborateur->id);
        $this->assertNotNull($apres);
        $this->assertNull($apres->company_id);
    }

    public function test_leffacement_definitif_emporte_la_comptabilite(): void
    {
        $company = $this->entreprise('A Effacer');
        $this->comptabilite($company, 3);

        $this->delete(route('superadmin.companies.destroy', $company->id));
        $this->delete(route('superadmin.corbeille.definitif', $company->id));

        $this->assertSame(0, Company::withTrashed()->where('id', $company->id)->count());
        $this->assertSame(0, EcritureComptable::where('company_id', $company->id)->count());
    }

    public function test_la_purge_epargne_une_corbeille_recente(): void
    {
        $company = $this->entreprise('Recente');
        $this->comptabilite($company, 2);
        $this->delete(route('superadmin.companies.destroy', $company->id));

        $this->artisan('dossiers:purger-corbeille', ['--appliquer' => true])->assertSuccessful();

        $this->assertSame(1, Company::onlyTrashed()->where('id', $company->id)->count(),
            'Trente jours ne sont pas passés : elle doit rester.');
    }

    public function test_la_purge_efface_une_corbeille_expiree(): void
    {
        $company = $this->entreprise('Expiree');
        $this->comptabilite($company, 2);
        $this->delete(route('superadmin.companies.destroy', $company->id));

        DB::table('companies')->where('id', $company->id)
            ->update(['deleted_at' => now()->subDays(Company::CORBEILLE_JOURS + 1)]);

        $this->artisan('dossiers:purger-corbeille', ['--appliquer' => true])->assertSuccessful();

        $this->assertSame(0, Company::withTrashed()->where('id', $company->id)->count());
        $this->assertSame(0, EcritureComptable::where('company_id', $company->id)->count());
    }

    public function test_la_simulation_de_purge_neffaces_rien(): void
    {
        $company = $this->entreprise('Expiree');
        $this->comptabilite($company, 2);
        $this->delete(route('superadmin.companies.destroy', $company->id));

        DB::table('companies')->where('id', $company->id)
            ->update(['deleted_at' => now()->subDays(Company::CORBEILLE_JOURS + 1)]);

        $this->artisan('dossiers:purger-corbeille')->assertSuccessful();

        $this->assertSame(1, Company::onlyTrashed()->where('id', $company->id)->count());
    }

    public function test_une_entreprise_en_corbeille_nest_plus_listee(): void
    {
        $gardee = $this->entreprise('Gardee');
        $jetee = $this->entreprise('Jetee');

        $this->delete(route('superadmin.companies.destroy', $jetee->id));

        $listees = Company::pluck('id');

        $this->assertTrue($listees->contains($gardee->id));
        $this->assertFalse($listees->contains($jetee->id));
    }
}
