<?php

namespace Tests\Feature;

use App\Models\Cabinet;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * « Mon Espace » : ce qui s'affiche doit pouvoir s'ouvrir.
 *
 * La liste des dossiers et le contrôle d'accès étaient écrits séparément. Un
 * dossier créé avant la fonction cabinet — donc sans `cabinet_id` — pouvait
 * s'afficher dans l'espace, puis refuser l'accès : « Accès non autorisé à
 * cette entreprise ». Les deux suivent maintenant la même règle.
 */
class EspaceCabinetAccesTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
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

    private function ouvrir(User $user, Company $company)
    {
        return $this->actingAs($user)->get(route('accountant.space.switch', $company->id));
    }

    /**
     * La page elle-même doit s'afficher.
     *
     * Le refactor qui a unifié la règle d'accès avait supprimé une variable
     * encore utilisée plus bas : « Undefined variable $cabinetGere », erreur
     * 500 sur Mon Espace. Les tests d'alors n'ouvraient que switchCompany, pas
     * la page. Celui-ci la charge.
     */
    public function test_la_page_mon_espace_saffiche(): void
    {
        $user = User::factory()->create();
        $this->entreprise('Dossier Cree', ['user_id' => $user->id]);

        $this->actingAs($user)->get(route('accountant.space'))->assertOk();
    }

    public function test_la_page_saffiche_pour_un_gerant_de_cabinet(): void
    {
        $user = User::factory()->create();
        $cabinet = Cabinet::create(['nom' => 'Cabinet Gore', 'code' => 'CAB-' . uniqid(), 'user_id' => $user->id]);
        $this->entreprise('Dossier Cabinet', ['cabinet_id' => $cabinet->id]);

        $this->actingAs($user)->get(route('accountant.space'))->assertOk();
    }

    public function test_la_page_saffiche_pour_un_espace_vide(): void
    {
        $user = User::factory()->create(['company_id' => null]);

        $this->actingAs($user)->get(route('accountant.space'))->assertOk();
    }

    public function test_un_dossier_dont_je_suis_le_createur_souvre(): void
    {
        $user = User::factory()->create();
        $dossier = $this->entreprise('Dossier Cree', ['user_id' => $user->id]);

        $this->ouvrir($user, $dossier);

        $this->assertSame($dossier->id, session('current_company_id'));
    }

    public function test_un_dossier_du_cabinet_que_je_gere_souvre(): void
    {
        $user = User::factory()->create();
        $cabinet = Cabinet::create(['nom' => 'Cabinet Gore', 'code' => 'CAB-' . uniqid(), 'user_id' => $user->id]);
        $dossier = $this->entreprise('Dossier Cabinet', ['cabinet_id' => $cabinet->id]);

        $this->ouvrir($user, $dossier);

        $this->assertSame($dossier->id, session('current_company_id'));
    }

    public function test_un_dossier_anterieur_au_cabinet_souvre_par_le_rattachement_direct(): void
    {
        // Le cas signalé : créé avant la fonction cabinet, donc cabinet_id vide.
        $user = User::factory()->create();
        Cabinet::create(['nom' => 'Cabinet Gore', 'code' => 'CAB-' . uniqid(), 'user_id' => $user->id]);

        $ancien = $this->entreprise('Dossier Ancien', ['cabinet_id' => null]);
        DB::table('company_user')->insert([
            'company_id' => $ancien->id, 'user_id' => $user->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->ouvrir($user, $ancien);

        $this->assertSame($ancien->id, session('current_company_id'));
    }

    public function test_une_filiale_de_mon_entreprise_souvre(): void
    {
        $mere = $this->entreprise('Societe Mere');
        $user = User::factory()->create(['company_id' => $mere->id]);
        $filiale = $this->entreprise('Filiale', ['parent_company_id' => $mere->id]);

        $this->ouvrir($user, $filiale);

        $this->assertSame($filiale->id, session('current_company_id'));
    }

    public function test_un_dossier_etranger_reste_refuse(): void
    {
        $user = User::factory()->create();
        $autre = User::factory()->create();
        $etranger = $this->entreprise('Dossier Etranger', ['user_id' => $autre->id]);

        $reponse = $this->ouvrir($user, $etranger);

        $reponse->assertRedirect(route('accountant.space'));
        $this->assertNotSame($etranger->id, session('current_company_id'));
    }

    public function test_ce_qui_saffiche_dans_lespace_souvre(): void
    {
        $user = User::factory()->create();
        $cabinet = Cabinet::create(['nom' => 'Cabinet Gore', 'code' => 'CAB-' . uniqid(), 'user_id' => $user->id]);

        $dossiers = collect([
            $this->entreprise('Par creation', ['user_id' => $user->id]),
            $this->entreprise('Par cabinet', ['cabinet_id' => $cabinet->id]),
        ]);

        $lie = $this->entreprise('Par affectation');
        DB::table('company_user')->insert([
            'company_id' => $lie->id, 'user_id' => $user->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $dossiers->push($lie);

        foreach ($dossiers as $dossier) {
            session()->forget('current_company_id');
            $this->ouvrir($user, $dossier);

            $this->assertSame(
                $dossier->id,
                session('current_company_id'),
                "« {$dossier->company_name} » figure dans l'espace : il doit s'ouvrir."
            );
        }
    }
}
