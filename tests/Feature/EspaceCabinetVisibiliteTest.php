<?php

namespace Tests\Feature;

use App\Models\Cabinet;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Ce que montre l'espace d'un cabinet.
 *
 * Deux manques se répondaient : le gérant ne voyait pas qu'il était dans un
 * espace de cabinet, et la liste des collaborateurs ne retenait que les
 * personnes rattachées à une comptabilité — quelqu'un qu'on venait de faire
 * entrer dans la maison n'y figurait pas, alors que le compteur des
 * affectations le comptait. On ne pouvait même pas lire son adresse pour lui
 * confier un dossier.
 */
class EspaceCabinetVisibiliteTest extends TestCase
{
    use DatabaseTransactions;

    private Cabinet $cabinet;
    private User $gerant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');

        $this->gerant = User::factory()->create(['role' => 'admin', 'pack' => 'cabinet']);
        $this->cabinet = Cabinet::create([
            'nom' => 'DC-KNOWING',
            'code' => 'CAB-' . uniqid(),
            'user_id' => $this->gerant->id,
        ]);

        DB::table('cabinet_user')->insert([
            'cabinet_id' => $this->cabinet->id, 'user_id' => $this->gerant->id,
            'role' => 'gerant', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function membre(User $user): void
    {
        DB::table('cabinet_user')->insert([
            'cabinet_id' => $this->cabinet->id, 'user_id' => $user->id,
            'role' => 'collaborateur', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_le_titre_nomme_le_cabinet(): void
    {
        $this->actingAs($this->gerant)
            ->get(route('accountant.space'))
            ->assertOk()
            ->assertSee('Espace Cabinet', false)
            ->assertSee('DC-KNOWING', false);
    }

    public function test_un_membre_sans_dossier_figure_parmi_les_collaborateurs(): void
    {
        $nouveau = User::factory()->create([
            'name' => 'Jean', 'last_name' => 'DUPONT',
            'email_adresse' => 'jean@cabinet.ci',
        ]);
        $this->membre($nouveau);

        $this->actingAs($this->gerant)
            ->get(route('accountant.space', ['page' => 'collaborators']))
            ->assertOk()
            ->assertSee('jean@cabinet.ci', false)
            ->assertSee('Membre du cabinet', false);
    }

    public function test_une_personne_etrangere_au_cabinet_ny_figure_pas(): void
    {
        $etranger = User::factory()->create(['email_adresse' => 'etranger@ailleurs.ci']);

        $this->actingAs($this->gerant)
            ->get(route('accountant.space', ['page' => 'collaborators']))
            ->assertOk()
            ->assertDontSee('etranger@ailleurs.ci', false);
    }

    public function test_le_gerant_voit_tout_le_portefeuille_du_cabinet(): void
    {
        foreach (['Client A', 'Client B'] as $nom) {
            Company::create([
                'company_name' => $nom, 'activity' => 'Test', 'juridique_form' => 'SARL',
                'cabinet_id' => $this->cabinet->id,
            ]);
        }

        $this->actingAs($this->gerant)
            ->get(route('accountant.space', ['page' => 'companies']))
            ->assertOk()
            ->assertSee('Client A', false)
            ->assertSee('Client B', false);
    }

    public function test_un_membre_ne_voit_pas_le_portefeuille(): void
    {
        $membre = User::factory()->create(['role' => 'admin', 'pack' => 'cabinet']);
        $this->membre($membre);

        Company::create([
            'company_name' => 'Client Confidentiel', 'activity' => 'Test',
            'juridique_form' => 'SARL', 'cabinet_id' => $this->cabinet->id,
        ]);

        $this->actingAs($membre)
            ->get(route('accountant.space', ['page' => 'companies']))
            ->assertOk()
            ->assertDontSee('Client Confidentiel', false);
    }

    public function test_designer_un_gerant_lui_donne_le_portefeuille(): void
    {
        $repreneur = User::factory()->create([
            'role' => 'admin', 'pack' => 'cabinet',
            'email_adresse' => 'constant@dcknowing.ci',
        ]);

        Company::create([
            'company_name' => 'Client Repris', 'activity' => 'Test',
            'juridique_form' => 'SARL', 'cabinet_id' => $this->cabinet->id,
        ]);

        $this->artisan('cabinets:gerant', [
            '--cabinet' => 'DC-KNOWING',
            '--email' => 'constant@dcknowing.ci',
            '--appliquer' => true,
        ])->assertSuccessful();

        $this->assertSame($repreneur->id, $this->cabinet->fresh()->user_id);

        $this->actingAs($repreneur)
            ->get(route('accountant.space', ['page' => 'companies']))
            ->assertOk()
            ->assertSee('Client Repris', false);
    }

    public function test_la_simulation_ne_change_pas_le_gerant(): void
    {
        $autre = User::factory()->create(['email_adresse' => 'autre@dcknowing.ci']);

        $this->artisan('cabinets:gerant', [
            '--cabinet' => 'DC-KNOWING',
            '--email' => 'autre@dcknowing.ci',
        ])->assertSuccessful();

        $this->assertSame($this->gerant->id, $this->cabinet->fresh()->user_id);
    }
}
