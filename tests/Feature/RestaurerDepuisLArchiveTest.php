<?php

namespace Tests\Feature;

use App\Models\ArchivedRecord;
use App\Models\Company;
use App\Models\PlanComptable;
use App\Models\User;
use App\Services\RestaurationDArchive;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Récupérer ce qui a été supprimé.
 *
 * L'archive annonçait la donnée « récupérable trente jours », mais rien ne la
 * récupérait : l'écran montrait son contenu et s'arrêtait là. Il fallait la
 * ressaisir à la main, ce qui n'est pas récupérer.
 *
 * Deux pièges couverts ici :
 *
 *   - une archive dont `expires_at` était resté vide n'était ni purgée ni
 *     affichée : présente en base, invisible à l'écran. La page pouvait donc
 *     paraître vide alors qu'elle avait tout ;
 *   - une entreprise encore en corbeille se RELÈVE au lieu de se réécrire :
 *     ses écritures ne sont jamais parties avec elle, et les réécrire les
 *     doublerait.
 */
class RestaurerDepuisLArchiveTest extends TestCase
{
    use DatabaseTransactions;

    private User $superAdmin;
    private Company $dossier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');

        $this->superAdmin = User::factory()->create(['role' => 'super_admin']);
        $this->actingAs($this->superAdmin);

        $this->dossier = Company::create([
            'company_name' => 'Client Archive', 'activity' => 'Test', 'juridique_form' => 'SARL',
        ]);
    }

    private function archive(array $champs = []): ArchivedRecord
    {
        return ArchivedRecord::create(array_merge([
            'company_id' => $this->dossier->id,
            'user_id' => $this->superAdmin->id,
            'model_type' => PlanComptable::class,
            'model_id' => 999001,
            'label' => 'Compte 411000 — Clients',
            'data' => [
                'id' => 999001,
                'company_id' => $this->dossier->id,
                'user_id' => $this->superAdmin->id,
                'numero_de_compte' => '411000',
                'intitule' => 'Clients',
            ],
            'deleted_at' => now()->subDay(),
            'expires_at' => now()->addDays(29),
        ], $champs));
    }

    public function test_une_ligne_revient_sous_son_identifiant_dorigine(): void
    {
        $archive = $this->archive();

        $issue = RestaurationDArchive::restaurer($archive);

        $this->assertTrue($issue['fait'], $issue['raison']);
        $this->assertSame('411000', PlanComptable::withoutGlobalScopes()
            ->where('id', 999001)->value('numero_de_compte'),
            "Sans son identifiant d'origine, tout ce qui la désignait pointerait dans le vide.");
        $this->assertNotNull($archive->fresh()->restored_at);
    }

    public function test_une_ligne_deja_presente_nest_pas_doublee(): void
    {
        $archive = $this->archive();
        RestaurationDArchive::restaurer($archive);

        // Rejouée à la main, sur une archive qu'on n'a pas marquée.
        $seconde = $this->archive();
        $issue = RestaurationDArchive::restaurer($seconde);

        $this->assertFalse($issue['fait']);
        $this->assertStringContainsString('déjà présente', $issue['raison']);
        $this->assertSame(1, PlanComptable::withoutGlobalScopes()->where('id', 999001)->count());
    }

    public function test_une_ligne_dont_le_dossier_a_disparu_est_refusee(): void
    {
        $archive = $this->archive([
            'company_id' => 999999,
            'data' => [
                'id' => 999002, 'company_id' => 999999, 'user_id' => $this->superAdmin->id,
                'numero_de_compte' => '401000', 'intitule' => 'Fournisseurs',
            ],
        ]);

        $issue = RestaurationDArchive::restaurer($archive);

        $this->assertFalse($issue['fait']);
        $this->assertStringContainsString("n'existe plus", $issue['raison']);
        $this->assertSame(0, PlanComptable::withoutGlobalScopes()->where('id', 999002)->count(),
            'Une écriture qui revient dans un dossier fantôme ne se retrouve plus.');
    }

    public function test_une_entreprise_en_corbeille_se_releve_au_lieu_de_se_reecrire(): void
    {
        $aSupprimer = Company::create([
            'company_name' => 'Client Corbeille', 'activity' => 'Test', 'juridique_form' => 'SARL',
        ]);
        $id = $aSupprimer->id;
        $aSupprimer->delete();

        $this->assertSame(0, Company::where('id', $id)->count(), 'Elle est bien en corbeille.');

        $archive = ArchivedRecord::create([
            'company_id' => $id,
            'user_id' => $this->superAdmin->id,
            'model_type' => Company::class,
            'model_id' => $id,
            'label' => 'Entreprise Client Corbeille',
            'data' => ['id' => $id, 'company_name' => 'Client Corbeille'],
            'deleted_at' => now(),
            'expires_at' => now()->addDays(30),
        ]);

        $issue = RestaurationDArchive::restaurer($archive);

        $this->assertTrue($issue['fait'], $issue['raison']);
        $this->assertStringContainsString('corbeille', $issue['raison']);
        $this->assertSame(1, Company::where('id', $id)->count());
    }

    public function test_un_lot_entier_revient_dun_seul_geste(): void
    {
        $lot = ArchivedRecord::nouveauLot();

        foreach ([999010, 999011, 999012] as $i => $id) {
            $this->archive([
                'batch_id' => $lot,
                'batch_size' => 3,
                'model_id' => $id,
                'data' => [
                    'id' => $id, 'company_id' => $this->dossier->id,
                    'user_id' => $this->superAdmin->id,
                    'numero_de_compte' => '60100' . $i, 'intitule' => 'Achat ' . $i,
                ],
            ]);
        }

        $resultat = RestaurationDArchive::restaurerLeLot($lot);

        $this->assertSame(3, $resultat['remises']);
        $this->assertSame(0, $resultat['refusees']);
        $this->assertSame(3, PlanComptable::withoutGlobalScopes()
            ->whereIn('id', [999010, 999011, 999012])->count());
    }

    public function test_une_archive_sans_echeance_reste_visible(): void
    {
        // Ni purgée (`expires_at <= maintenant` est faux pour un vide) ni
        // affichée (`expires_at > maintenant` l'est aussi) : elle restait en
        // base, invisible, et l'écran paraissait vide alors qu'il avait tout.
        $sansEcheance = $this->archive(['expires_at' => null, 'label' => 'Ligne sans echeance']);

        $this->assertSame(1, ArchivedRecord::enConservation()
            ->where('id', $sansEcheance->id)->count());

        $this->get(route('superadmin.archives'))
            ->assertOk()
            ->assertSee('Ligne sans echeance', false);
    }

    public function test_lapercu_dit_ce_qui_reviendrait_sans_rien_ecrire(): void
    {
        $archive = $this->archive();

        $this->getJson(route('superadmin.archives.apercu_restauration', $archive->id))
            ->assertOk()
            ->assertJson(['possible' => true]);

        $this->assertSame(0, PlanComptable::withoutGlobalScopes()->where('id', 999001)->count(),
            "L'aperçu annonce, il n'écrit pas.");
    }

    public function test_lecran_remet_la_ligne_en_place(): void
    {
        $archive = $this->archive();

        $this->post(route('superadmin.archives.restaurer', $archive->id))
            ->assertRedirect();

        $this->assertSame(1, PlanComptable::withoutGlobalScopes()->where('id', 999001)->count());
    }

    public function test_le_filtre_des_dossiers_supprimes_ne_casse_plus_la_requete(): void
    {
        // Un `orWhere` sans parenthèses s'échappait et annulait tous les autres
        // filtres, scope de conservation compris : la page montrait alors des
        // archives expirées, et le filtre ne filtrait rien.
        $this->archive(['label' => 'Ligne du dossier vivant']);

        $this->archive([
            'company_id' => 999998,
            'model_id' => 999020,
            'label' => 'Ligne du dossier disparu',
            'data' => ['id' => 999020, 'company_id' => 999998, 'user_id' => $this->superAdmin->id, 'numero_de_compte' => '521000'],
        ]);

        $this->get(route('superadmin.archives', ['orphelines' => 1]))
            ->assertOk()
            ->assertSee('Ligne du dossier disparu', false)
            ->assertDontSee('Ligne du dossier vivant', false);
    }

    public function test_la_commande_simule_avant_de_remettre(): void
    {
        $lot = ArchivedRecord::nouveauLot();
        $this->archive(['batch_id' => $lot]);

        $this->artisan('archives:restaurer', ['--lot' => $lot])->assertSuccessful();

        $this->assertSame(0, PlanComptable::withoutGlobalScopes()->where('id', 999001)->count(),
            'Sans --appliquer, rien ne bouge.');

        $this->artisan('archives:restaurer', ['--lot' => $lot, '--appliquer' => true])
            ->assertSuccessful();

        $this->assertSame(1, PlanComptable::withoutGlobalScopes()->where('id', 999001)->count());
    }

    public function test_la_commande_refuse_deux_cibles_a_la_fois(): void
    {
        $this->artisan('archives:restaurer', ['--lot' => 'x', '--dossier' => 1])
            ->expectsOutputToContain('exactement une cible')
            ->assertFailed();
    }
}
