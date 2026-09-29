<?php

namespace Tests\Feature;

use App\Models\Cabinet;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Qui peut supprimer un dossier depuis « Mon Espace » ?
 *
 * Un dossier ouvert sous un cabinet appartient AU CABINET, pas à la personne
 * qui l'a saisi. Un collaborateur qui part — ou qu'on supprime — ne doit pas
 * emporter les dossiers avec lui : ils restent dans l'espace du gérant.
 *
 * Hors cabinet, la règle reste celle d'avant : le créateur, et lui seul.
 */
class SuppressionDossierCabinetTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    private function cabinet(User $gerant): Cabinet
    {
        return Cabinet::create([
            'nom' => 'Cabinet Gore',
            'code' => 'CAB-' . uniqid(),
            'user_id' => $gerant->id,
        ]);
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

    private function supprimer(User $user, Company $company)
    {
        return $this->actingAs($user)->delete(route('accountant.space.company.destroy', $company->id));
    }

    public function test_un_dossier_hors_cabinet_se_supprime_par_son_createur(): void
    {
        $user = User::factory()->create();
        $dossier = $this->entreprise('Dossier Perso', ['user_id' => $user->id, 'cabinet_id' => null]);

        $this->supprimer($user, $dossier);

        $this->assertSame(0, Company::where('id', $dossier->id)->count());
    }

    public function test_un_collaborateur_ne_supprime_pas_un_dossier_du_cabinet(): void
    {
        $gerant = User::factory()->create();
        $cabinet = $this->cabinet($gerant);
        $collaborateur = User::factory()->create();
        DB::table('cabinet_user')->insert(['cabinet_id' => $cabinet->id, 'user_id' => $collaborateur->id, 'created_at' => now(), 'updated_at' => now()]);

        // Le collaborateur l'a créé, mais sous le cabinet.
        $dossier = $this->entreprise('Dossier Client', [
            'user_id' => $collaborateur->id,
            'cabinet_id' => $cabinet->id,
        ]);

        $this->supprimer($collaborateur, $dossier);

        $this->assertSame(1, Company::where('id', $dossier->id)->count(),
            "Le dossier appartient au cabinet : il doit rester dans l'espace du gérant.");
    }

    public function test_le_gerant_peut_supprimer_un_dossier_de_son_cabinet(): void
    {
        $gerant = User::factory()->create();
        $cabinet = $this->cabinet($gerant);
        $collaborateur = User::factory()->create();
        DB::table('cabinet_user')->insert(['cabinet_id' => $cabinet->id, 'user_id' => $collaborateur->id, 'created_at' => now(), 'updated_at' => now()]);

        $dossier = $this->entreprise('Dossier Client', [
            'user_id' => $collaborateur->id,
            'cabinet_id' => $cabinet->id,
        ]);

        $this->supprimer($gerant, $dossier);

        $this->assertSame(0, Company::where('id', $dossier->id)->count());
    }

    public function test_le_dossier_survit_a_la_suppression_de_son_createur(): void
    {
        $gerant = User::factory()->create();
        $cabinet = $this->cabinet($gerant);
        $collaborateur = User::factory()->create();
        DB::table('cabinet_user')->insert(['cabinet_id' => $cabinet->id, 'user_id' => $collaborateur->id, 'created_at' => now(), 'updated_at' => now()]);

        $dossier = $this->entreprise('Dossier Client', [
            'user_id' => $collaborateur->id,
            'cabinet_id' => $cabinet->id,
        ]);

        $collaborateur->delete();

        $this->assertSame(1, Company::where('id', $dossier->id)->count());
        $this->assertSame(
            $cabinet->id,
            Company::find($dossier->id)->cabinet_id,
            "Le rattachement au cabinet doit survivre au départ de la personne."
        );
    }

    public function test_une_personne_simplement_affectee_ne_supprime_rien(): void
    {
        $proprietaire = User::factory()->create();
        $affecte = User::factory()->create();

        $dossier = $this->entreprise('Dossier Partage', ['user_id' => $proprietaire->id]);

        DB::table('company_user')->insert([
            'company_id' => $dossier->id, 'user_id' => $affecte->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->supprimer($affecte, $dossier);

        $this->assertSame(1, Company::where('id', $dossier->id)->count());
    }
}
