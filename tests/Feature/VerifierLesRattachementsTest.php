<?php

namespace Tests\Feature;

use App\Models\Cabinet;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Confrontation des rattachements entre les quatre écrans.
 */
class VerifierLesRattachementsTest extends TestCase
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
            'company_name' => $nom, 'activity' => 'Test', 'juridique_form' => 'SARL',
        ], $champs));
    }

    private function membre(Cabinet $cabinet, User $user, string $role = 'collaborateur'): void
    {
        DB::table('cabinet_user')->insert([
            'cabinet_id' => $cabinet->id, 'user_id' => $user->id, 'role' => $role,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_tout_est_coherent_quand_rien_na_bouge(): void
    {
        $gerant = User::factory()->create();
        $cabinet = $this->cabinet('DC-KNOWING', $gerant);
        $this->membre($cabinet, $gerant, 'gerant');

        $this->artisan('cabinets:verifier')
            ->expectsOutputToContain('rien à signaler')
            ->assertSuccessful();
    }

    public function test_un_gerant_disparu_est_signale_et_remplace(): void
    {
        $gerant = User::factory()->create();
        $cabinet = $this->cabinet('DC-KNOWING', $gerant);

        $second = User::factory()->create();
        $this->membre($cabinet, $second);

        // On efface la personne sans passer par le modèle : c'est l'état
        // qu'on retrouve en base après d'anciennes suppressions.
        DB::table('users')->where('id', $gerant->id)->delete();

        $this->artisan('cabinets:verifier', ['--appliquer' => true])
            ->expectsOutputToContain('gérant a été supprimé')
            ->assertSuccessful();

        $this->assertSame($second->id, $cabinet->fresh()->user_id,
            'Le plus ancien membre reprend la maison.');
    }

    public function test_un_cabinet_sans_personne_pour_le_reprendre_est_dit(): void
    {
        $gerant = User::factory()->create();
        $cabinet = $this->cabinet('Cabinet Seul', $gerant);
        DB::table('users')->where('id', $gerant->id)->delete();

        $this->artisan('cabinets:verifier', ['--appliquer' => true])
            ->expectsOutputToContain('aucun membre pour le reprendre')
            ->assertSuccessful();

        $this->assertSame($gerant->id, $cabinet->fresh()->user_id,
            "Rien n'est inventé : l'identifiant reste, et le constat est posé.");
    }

    public function test_les_lignes_dappartenance_orphelines_sont_effacees(): void
    {
        $gerant = User::factory()->create();
        $cabinet = $this->cabinet('DC-KNOWING', $gerant);
        $this->membre($cabinet, $gerant, 'gerant');

        $parti = User::factory()->create();
        $this->membre($cabinet, $parti);
        DB::table('users')->where('id', $parti->id)->delete();

        $this->artisan('cabinets:verifier', ['--appliquer' => true])
            ->expectsOutputToContain('ne désignent plus personne')
            ->assertSuccessful();

        $this->assertSame(0, DB::table('cabinet_user')->where('user_id', $parti->id)->count());
        $this->assertSame(1, DB::table('cabinet_user')->where('user_id', $gerant->id)->count(),
            'Le gérant, lui, reste membre.');
    }

    public function test_tenir_un_dossier_du_cabinet_sans_y_appartenir_est_signale(): void
    {
        $gerant = User::factory()->create();
        $cabinet = $this->cabinet('DC-KNOWING', $gerant);
        $this->membre($cabinet, $gerant, 'gerant');

        $dossier = $this->entreprise('Client A', ['cabinet_id' => $cabinet->id]);

        $etranger = User::factory()->create(['email_adresse' => 'etranger@test.ci']);
        DB::table('company_user')->insert([
            'company_id' => $dossier->id, 'user_id' => $etranger->id, 'role' => 'admin',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->artisan('cabinets:verifier', ['--appliquer' => true])
            ->expectsOutputToContain('sans y appartenir')
            ->assertSuccessful();

        $this->assertSame(1, DB::table('cabinet_user')
            ->where('cabinet_id', $cabinet->id)->where('user_id', $etranger->id)->count(),
            'La personne entre dans le cabinet dont elle tient un dossier.');
    }

    public function test_le_constat_ne_modifie_rien(): void
    {
        $gerant = User::factory()->create();
        $cabinet = $this->cabinet('DC-KNOWING', $gerant);
        $parti = User::factory()->create();
        $this->membre($cabinet, $parti);
        DB::table('users')->where('id', $parti->id)->delete();

        $this->artisan('cabinets:verifier')->assertSuccessful();

        $this->assertSame(1, DB::table('cabinet_user')->where('user_id', $parti->id)->count());
    }
}
