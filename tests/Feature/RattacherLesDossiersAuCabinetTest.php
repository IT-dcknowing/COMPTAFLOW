<?php

namespace Tests\Feature;

use App\Models\Cabinet;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Ranger les comptabilités sous le cabinet de celui qui les a ouvertes.
 */
class RattacherLesDossiersAuCabinetTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    private function cabinet(string $nom, User $gerant): Cabinet
    {
        return Cabinet::create(['nom' => $nom, 'code' => 'CAB-' . uniqid(), 'user_id' => $gerant->id]);
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

    public function test_un_dossier_du_gerant_rejoint_son_cabinet(): void
    {
        $gerant = User::factory()->create();
        $cabinet = $this->cabinet('DC-KNOWING', $gerant);
        $dossier = $this->entreprise('Client A', ['user_id' => $gerant->id]);

        $this->artisan('cabinets:rattacher-dossiers', ['--appliquer' => true])->assertSuccessful();

        $this->assertSame($cabinet->id, $dossier->fresh()->cabinet_id);
    }

    public function test_un_dossier_ouvert_par_un_membre_rejoint_le_cabinet(): void
    {
        $gerant = User::factory()->create();
        $cabinet = $this->cabinet('DC-KNOWING', $gerant);

        $membre = User::factory()->create();
        DB::table('cabinet_user')->insert([
            'cabinet_id' => $cabinet->id, 'user_id' => $membre->id,
            'role' => 'collaborateur', 'created_at' => now(), 'updated_at' => now(),
        ]);

        $dossier = $this->entreprise('Client B', ['user_id' => $membre->id]);

        $this->artisan('cabinets:rattacher-dossiers', ['--appliquer' => true])->assertSuccessful();

        $this->assertSame($cabinet->id, $dossier->fresh()->cabinet_id,
            "Un dossier ouvert par un collaborateur appartient au cabinet.");
    }

    public function test_un_dossier_deja_rattache_nest_jamais_deplace(): void
    {
        $gerantA = User::factory()->create();
        $cabinetA = $this->cabinet('Cabinet A', $gerantA);
        $gerantB = User::factory()->create();
        $cabinetB = $this->cabinet('Cabinet B', $gerantB);

        // Créé par le gérant de A, mais déjà rangé sous B : on respecte.
        $dossier = $this->entreprise('Client C', [
            'user_id' => $gerantA->id, 'cabinet_id' => $cabinetB->id,
        ]);

        $this->artisan('cabinets:rattacher-dossiers', ['--appliquer' => true])->assertSuccessful();

        $this->assertSame($cabinetB->id, $dossier->fresh()->cabinet_id);
    }

    public function test_un_createur_sans_cabinet_laisse_le_dossier_de_cote(): void
    {
        $solitaire = User::factory()->create();
        $dossier = $this->entreprise('Client D', ['user_id' => $solitaire->id]);

        $this->artisan('cabinets:rattacher-dossiers', ['--appliquer' => true])
            ->expectsOutputToContain("n'appartient à aucun cabinet")
            ->assertSuccessful();

        $this->assertNull($dossier->fresh()->cabinet_id);
    }

    public function test_loption_cabinet_ramasse_les_orphelins(): void
    {
        $gerant = User::factory()->create();
        $cabinet = $this->cabinet('DC-KNOWING', $gerant);

        $solitaire = User::factory()->create();
        $dossier = $this->entreprise('Client E', ['user_id' => $solitaire->id]);

        $this->artisan('cabinets:rattacher-dossiers', [
            '--cabinet' => $cabinet->id,
            '--appliquer' => true,
        ])->assertSuccessful();

        $this->assertSame($cabinet->id, $dossier->fresh()->cabinet_id);
    }

    public function test_la_simulation_ne_rattache_rien(): void
    {
        $gerant = User::factory()->create();
        $this->cabinet('DC-KNOWING', $gerant);
        $dossier = $this->entreprise('Client F', ['user_id' => $gerant->id]);

        $this->artisan('cabinets:rattacher-dossiers')->assertSuccessful();

        $this->assertNull($dossier->fresh()->cabinet_id);
    }

    public function test_un_cabinet_inconnu_est_refuse(): void
    {
        $this->artisan('cabinets:rattacher-dossiers', ['--cabinet' => 999999, '--appliquer' => true])
            ->assertFailed();
    }
}
