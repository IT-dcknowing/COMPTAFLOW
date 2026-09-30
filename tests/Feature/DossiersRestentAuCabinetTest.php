<?php

namespace Tests\Feature;

use App\Models\Cabinet;
use App\Models\Company;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Un dossier ouvert sous un cabinet appartient au cabinet.
 *
 * Trois moments où la chaîne peut se rompre, et qu'on vérifie ici :
 *
 *   1. un membre du cabinet ouvre un dossier → il porte le cabinet ;
 *   2. ce membre fait entrer quelqu'un d'autre → cette personne entre aussi
 *      dans le cabinet, et les dossiers qu'elle ouvrira y resteront ;
 *   3. la personne part ou est supprimée → le cabinet garde tout.
 */
class DossiersRestentAuCabinetTest extends TestCase
{
    use DatabaseTransactions;

    private Cabinet $cabinet;
    private User $gerant;
    private User $membre;

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

        $this->membre = User::factory()->create(['role' => 'admin', 'pack' => 'cabinet']);
        DB::table('cabinet_user')->insert([
            'cabinet_id' => $this->cabinet->id, 'user_id' => $this->membre->id,
            'role' => 'collaborateur', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function creerUnDossier(User $auteur, string $nom)
    {
        $reponse = $this->actingAs($auteur)->post(route('accountant.space.company.store'), [
            'company_name' => $nom,
            'activity' => 'Négoce',
            'juridique_form' => 'SARL',
            'form_origin' => 'company',
        ]);

        // La creation rapporte ses refus par la session : sans cela, un test
        // qui echoue ne dit pas pourquoi.
        $erreur = $reponse->baseResponse->getSession()?->get('error');
        $this->assertNull($erreur, 'La creation a ete refusee : ' . (string) $erreur);

        return $reponse;
    }

    public function test_un_dossier_ouvert_par_un_membre_porte_le_cabinet(): void
    {
        $this->creerUnDossier($this->membre, 'Client Du Membre');

        $dossier = Company::where('company_name', 'Client Du Membre')->first();

        $this->assertNotNull($dossier, 'Le dossier doit être créé.');
        $this->assertSame($this->cabinet->id, $dossier->cabinet_id,
            "Un dossier ouvert par un membre appartient au cabinet, pas à la personne.");
    }

    public function test_un_dossier_ouvert_par_le_gerant_porte_le_cabinet(): void
    {
        $this->creerUnDossier($this->gerant, 'Client Du Gerant');

        $dossier = Company::where('company_name', 'Client Du Gerant')->first();

        $this->assertNotNull($dossier);
        $this->assertSame($this->cabinet->id, $dossier->cabinet_id);
    }

    public function test_le_cabinet_garde_le_dossier_apres_le_depart_de_son_auteur(): void
    {
        $this->creerUnDossier($this->membre, 'Client Conserve');
        $dossier = Company::where('company_name', 'Client Conserve')->firstOrFail();

        // La personne quitte le cabinet, puis son compte est supprimé.
        DB::table('cabinet_user')->where('user_id', $this->membre->id)->delete();
        $this->membre->delete();

        $apres = Company::find($dossier->id);

        $this->assertNotNull($apres, 'Le dossier survit au départ de son auteur.');
        $this->assertSame($this->cabinet->id, $apres->cabinet_id,
            'Et il reste rattaché au cabinet.');
    }

    public function test_un_membre_ne_peut_pas_supprimer_un_dossier_du_cabinet(): void
    {
        $this->creerUnDossier($this->membre, 'Client Protege');
        $dossier = Company::where('company_name', 'Client Protege')->firstOrFail();

        $this->actingAs($this->membre)
            ->delete(route('accountant.space.company.destroy', $dossier->id));

        $this->assertSame(1, Company::where('id', $dossier->id)->count(),
            "Seul le gérant peut supprimer un dossier du cabinet.");
    }

    public function test_un_dossier_hors_cabinet_ne_porte_aucun_cabinet(): void
    {
        $solitaire = User::factory()->create(['role' => 'admin', 'pack' => 'cabinet']);

        $this->creerUnDossier($solitaire, 'Client Solitaire');
        $dossier = Company::where('company_name', 'Client Solitaire')->first();

        $this->assertNotNull($dossier);
        $this->assertNull($dossier->cabinet_id);
    }
}
