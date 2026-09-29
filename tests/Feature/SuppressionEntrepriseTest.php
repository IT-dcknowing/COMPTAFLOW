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
 * Suppression d'une entreprise par le super administrateur.
 *
 * Deux règles, et elles tirent en sens opposé :
 *
 *   - la comptabilité part avec la fiche. La laisser produisait des écritures
 *     orphelines, sans entreprise pour les ouvrir — des dossiers entiers
 *     invisibles dans l'application mais toujours dans la base.
 *
 *   - les comptes utilisateurs, eux, survivent. Un collaborateur qui travaille
 *     sur plusieurs dossiers perdait son accès à tous parce qu'un seul avait
 *     été fermé.
 */
class SuppressionEntrepriseTest extends TestCase
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
            'email_adresse' => strtolower(str_replace(' ', '', $nom)) . '@test.ci',
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
                'date' => '2026-03-01', 'n_saisie' => 'ECR-00000' . $i,
                'description_operation' => 'Test', 'plan_comptable_id' => $compte->id,
                'debit' => 1000, 'credit' => 0, 'statut' => 'approved',
            ]);
        }
    }

    private function supprimer(Company $company)
    {
        return $this->delete(route('superadmin.companies.destroy', $company->id));
    }

    public function test_lentreprise_part_en_corbeille_avec_sa_comptabilite(): void
    {
        $company = $this->entreprise('A Supprimer');
        $this->comptabilite($company, 4);

        $this->supprimer($company);

        $this->assertSame(0, Company::where('id', $company->id)->count(),
            "L'entreprise doit disparaître des listes.");
        $this->assertSame(1, Company::onlyTrashed()->where('id', $company->id)->count(),
            'Mais rester en corbeille, récupérable trente jours.');

        // La comptabilité ne bouge pas : c'est ce qui rend la remise en place
        // possible. Elle ne part qu'à l'effacement définitif.
        $this->assertSame(4, EcritureComptable::where('company_id', $company->id)->count());
        $this->assertSame(1, ExerciceComptable::where('company_id', $company->id)->count());
    }

    public function test_les_comptes_utilisateurs_survivent(): void
    {
        $company = $this->entreprise('A Supprimer');
        $collaborateur = User::factory()->create(['company_id' => $company->id]);

        $this->supprimer($company);

        $apres = User::find($collaborateur->id);

        $this->assertNotNull($apres, 'La personne reste : elle travaille peut-être ailleurs.');
        $this->assertNull($apres->company_id, "Mais elle n'a plus accès à ce dossier.");
    }

    public function test_le_rattachement_multi_dossiers_est_coupe_pour_ce_dossier_seulement(): void
    {
        $ferme = $this->entreprise('Dossier Ferme');
        $autre = $this->entreprise('Dossier Garde');
        $collaborateur = User::factory()->create();

        foreach ([$ferme, $autre] as $c) {
            DB::table('company_user')->insert([
                'company_id' => $c->id, 'user_id' => $collaborateur->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->supprimer($ferme);

        $this->assertSame(0, DB::table('company_user')
            ->where('company_id', $ferme->id)->where('user_id', $collaborateur->id)->count());
        $this->assertSame(1, DB::table('company_user')
            ->where('company_id', $autre->id)->where('user_id', $collaborateur->id)->count(),
            "L'autre dossier ne doit pas être touché.");
    }

    public function test_une_filiale_part_avec_sa_mere(): void
    {
        $mere = $this->entreprise('Societe Mere');
        $filiale = $this->entreprise('Filiale', ['parent_company_id' => $mere->id]);
        $this->comptabilite($filiale, 2);

        $this->supprimer($mere);

        $this->assertSame(0, Company::where('id', $filiale->id)->count());
        $this->assertSame(1, Company::onlyTrashed()->where('id', $filiale->id)->count());
    }

    public function test_lapercu_annonce_ce_qui_va_partir(): void
    {
        $company = $this->entreprise('A Supprimer');
        $this->comptabilite($company, 5);

        $apercu = $this->getJson(route('superadmin.companies.apercu_suppression', $company->id))
            ->assertOk()->json();

        $this->assertSame('A Supprimer', $apercu['entreprise']);
        $this->assertSame(5, $apercu['ecritures']);
        $this->assertSame(1, $apercu['exercices']);
        $this->assertSame(1, $apercu['journaux']);
        $this->assertGreaterThan(0, $apercu['utilisateurs']);
    }

    public function test_un_autre_dossier_nest_jamais_touche(): void
    {
        $cible = $this->entreprise('Cible');
        $this->comptabilite($cible, 2);

        $voisin = $this->entreprise('Voisin');
        $this->comptabilite($voisin, 7);

        $this->supprimer($cible);

        $this->assertSame(7, EcritureComptable::where('company_id', $voisin->id)->count());
        $this->assertSame(1, Company::where('id', $voisin->id)->count());
        $this->assertSame(0, Company::onlyTrashed()->where('id', $voisin->id)->count());
    }
}
