<?php

namespace Tests\Feature;

use App\Models\Cabinet;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Nettoyage des cabinets vides, et rattachement des dossiers d'une personne
 * quand elle entre dans une maison.
 *
 * La règle qui protège tout : un cabinet ne part QUE s'il ne porte aucune
 * comptabilité. Aucun dossier ne peut donc être perdu.
 */
class NettoyerLesCabinetsTest extends TestCase
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

    private function cabinet(string $nom, ?User $gerant = null): Cabinet
    {
        return Cabinet::create([
            'nom' => $nom,
            'code' => 'CAB-' . uniqid(),
            'user_id' => ($gerant ?: User::factory()->create())->id,
        ]);
    }

    private function entreprise(string $nom, array $champs = []): Company
    {
        return Company::create(array_merge([
            'company_name' => $nom,
            'activity' => 'Test',
            'juridique_form' => 'SARL',
        ], $champs));
    }

    public function test_un_cabinet_vide_est_retire(): void
    {
        $vide = $this->cabinet('Cabinet Fantome');

        $this->artisan('cabinets:nettoyer', ['--appliquer' => true])->assertSuccessful();

        $this->assertSame(0, Cabinet::where('id', $vide->id)->count());
    }

    public function test_un_cabinet_qui_porte_un_dossier_est_garde(): void
    {
        $vrai = $this->cabinet('DC-KNOWING');
        $this->entreprise('Client A', ['cabinet_id' => $vrai->id]);

        $this->artisan('cabinets:nettoyer', ['--appliquer' => true])->assertSuccessful();

        $this->assertSame(1, Cabinet::where('id', $vrai->id)->count());
    }

    public function test_aucune_comptabilite_nest_jamais_touchee(): void
    {
        $vide = $this->cabinet('Cabinet Fantome');
        $vrai = $this->cabinet('DC-KNOWING');

        $dossier = $this->entreprise('Client A', ['cabinet_id' => $vrai->id]);
        $libre = $this->entreprise('Client Sans Cabinet');

        $this->artisan('cabinets:nettoyer', ['--appliquer' => true])->assertSuccessful();

        $this->assertSame(1, Company::where('id', $dossier->id)->count());
        $this->assertSame($vrai->id, $dossier->fresh()->cabinet_id);
        $this->assertSame(1, Company::where('id', $libre->id)->count());
        $this->assertSame(0, Cabinet::where('id', $vide->id)->count());
    }

    public function test_loption_garder_protege_un_cabinet_vide(): void
    {
        $vide = $this->cabinet('DC-KNOWING');

        $this->artisan('cabinets:nettoyer', [
            '--garder' => 'DC-KNOWING',
            '--appliquer' => true,
        ])->assertSuccessful();

        $this->assertSame(1, Cabinet::where('id', $vide->id)->count());
    }

    public function test_les_liens_dappartenance_partent_avec_le_cabinet_vide(): void
    {
        $vide = $this->cabinet('Cabinet Fantome');
        $membre = User::factory()->create();

        DB::table('cabinet_user')->insert([
            'cabinet_id' => $vide->id, 'user_id' => $membre->id,
            'role' => 'collaborateur', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('cabinets:nettoyer', ['--appliquer' => true])->assertSuccessful();

        $this->assertSame(0, DB::table('cabinet_user')->where('cabinet_id', $vide->id)->count());
        $this->assertNotNull(User::find($membre->id), 'La personne, elle, reste.');
    }

    public function test_un_nom_qui_designe_plusieurs_cabinets_est_refuse(): void
    {
        // Le piège réel : trois cabinets portent le même nom, et « knowing »
        // en désigne deux. Ranger les dossiers d'une maison sous une autre ne
        // se rattrape pas : on refuse plutôt que de trancher au hasard.
        $this->cabinet('DC-KNOWING');
        $this->cabinet('Cabinet IT dc knowing');

        $this->artisan('cabinets:nettoyer', ['--garder' => 'knowing', '--appliquer' => true])
            ->expectsOutputToContain('Plusieurs cabinets portent')
            ->assertFailed();

        $this->assertSame(2, Cabinet::count(), 'Rien ne doit bouger tant que le choix est ambigu.');
    }

    public function test_le_nom_exact_lemporte_sur_lapproche(): void
    {
        $vrai = $this->cabinet('DC-KNOWING');
        $this->cabinet('Cabinet IT dc knowing');

        $this->artisan('cabinets:nettoyer', ['--garder' => 'DC-KNOWING', '--appliquer' => true])
            ->assertSuccessful();

        $this->assertSame(1, Cabinet::where('id', $vrai->id)->count(), 'Le nom exact est gardé.');
        $this->assertSame(1, Cabinet::count(), "L'autre est parti.");
    }

    public function test_les_membres_entrent_dans_le_cabinet_daccueil(): void
    {
        $garde = $this->cabinet('DC-KNOWING');
        $this->entreprise('Client A', ['cabinet_id' => $garde->id]);

        $vide = $this->cabinet('Cabinet Fantome');
        $membre = User::factory()->create();
        DB::table('cabinet_user')->insert([
            'cabinet_id' => $vide->id, 'user_id' => $membre->id,
            'role' => 'collaborateur', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('cabinets:nettoyer', [
            '--garder' => 'DC-KNOWING',
            '--transferer-vers' => 'DC-KNOWING',
            '--appliquer' => true,
        ])->assertSuccessful();

        $this->assertSame(1, DB::table('cabinet_user')
            ->where('cabinet_id', $garde->id)->where('user_id', $membre->id)->count(),
            "La personne entre dans la maison gardée au lieu de se retrouver sans cabinet.");
    }

    public function test_un_membre_deja_dans_laccueil_nest_pas_duplique(): void
    {
        $garde = $this->cabinet('DC-KNOWING');
        $this->entreprise('Client A', ['cabinet_id' => $garde->id]);

        $personne = User::factory()->create();
        $vide = $this->cabinet('Cabinet Fantome');

        foreach ([$garde->id, $vide->id] as $cabinetId) {
            DB::table('cabinet_user')->insert([
                'cabinet_id' => $cabinetId, 'user_id' => $personne->id,
                'role' => 'collaborateur', 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $this->artisan('cabinets:nettoyer', [
            '--garder' => 'DC-KNOWING',
            '--transferer-vers' => 'DC-KNOWING',
            '--appliquer' => true,
        ])->assertSuccessful();

        $this->assertSame(1, DB::table('cabinet_user')
            ->where('cabinet_id', $garde->id)->where('user_id', $personne->id)->count());
    }

    public function test_la_simulation_ne_supprime_rien(): void
    {
        $vide = $this->cabinet('Cabinet Fantome');

        $this->artisan('cabinets:nettoyer')->assertSuccessful();

        $this->assertSame(1, Cabinet::where('id', $vide->id)->count());
    }

    public function test_entrer_dans_un_cabinet_y_emmene_ses_dossiers(): void
    {
        $cabinet = $this->cabinet('DC-KNOWING');
        $personne = User::factory()->create(['email_adresse' => 'jean@societe.ci']);

        $sien = $this->entreprise('Son Dossier', ['user_id' => $personne->id]);

        $this->post(route('superadmin.affectations.cabinet'), [
            'cabinet_id' => $cabinet->id,
            'email_adresse' => 'jean@societe.ci',
        ]);

        $this->assertSame($cabinet->id, $sien->fresh()->cabinet_id,
            "Le dossier qu'il tenait déjà rejoint le cabinet.");
    }

    public function test_un_dossier_deja_range_ailleurs_ne_bouge_pas(): void
    {
        $ancien = $this->cabinet('Ancien Cabinet');
        $nouveau = $this->cabinet('DC-KNOWING');
        $personne = User::factory()->create(['email_adresse' => 'jean@societe.ci']);

        $dossier = $this->entreprise('Deja Range', [
            'user_id' => $personne->id, 'cabinet_id' => $ancien->id,
        ]);

        $this->post(route('superadmin.affectations.cabinet'), [
            'cabinet_id' => $nouveau->id,
            'email_adresse' => 'jean@societe.ci',
        ]);

        $this->assertSame($ancien->id, $dossier->fresh()->cabinet_id,
            'Un rattachement de personne ne déplace pas un dossier déjà rangé.');
    }
}
