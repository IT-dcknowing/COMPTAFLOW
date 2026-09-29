<?php

namespace Tests\Feature;

use App\Models\CodeJournal;
use App\Models\Company;
use App\Models\EcritureComptable;
use App\Models\ExerciceComptable;
use App\Models\PlanComptable;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Remise du résultat au bon côté dans un report à nouveau.
 *
 * La commande ne touche qu'un cas, et le vérifie avant : la ligne de classe 13
 * doit valoir exactement la moitié de l'écart, du même côté. C'est la
 * signature du défaut. Tout le reste est écarté.
 */
class ReparerLeResultatDesReportsTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;
    private User $user;
    private ExerciceComptable $exercice;
    private CodeJournal $ran;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');

        $this->company = Company::create([
            'company_name' => 'Repar SARL',
            'activity' => 'Test',
            'juridique_form' => 'SARL',
            'email_adresse' => 'repar@test.ci',
        ]);

        $this->user = User::factory()->create(['company_id' => $this->company->id]);

        $this->exercice = ExerciceComptable::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'intitule' => 'EXERCICE 2026',
            'date_debut' => '2026-01-01',
            'date_fin' => '2026-12-31',
            'is_active' => true,
        ]);

        $this->ran = CodeJournal::create([
            'company_id' => $this->company->id, 'user_id' => $this->user->id,
            'code_journal' => 'RAN', 'intitule' => 'Report à nouveau', 'traitement_analytique' => 0,
        ]);

        foreach ([
            '57100000' => 'CAISSE',
            '13010000' => 'RESULTAT - BENEFICE',
            '13090000' => 'RESULTAT - PERTE',
        ] as $numero => $intitule) {
            PlanComptable::create([
                'company_id' => $this->company->id, 'user_id' => $this->user->id,
                'numero_de_compte' => $numero, 'intitule' => $intitule,
            ]);
        }

        $this->actingAs($this->user);
        session(['current_company_id' => $this->company->id]);
    }

    private function compte(string $numero): PlanComptable
    {
        return PlanComptable::where('company_id', $this->company->id)
            ->where('numero_de_compte', $numero)->firstOrFail();
    }

    private function ligne(string $numero, string $compte, float $debit, float $credit, ?string $reference = 'RAN-1'): EcritureComptable
    {
        return EcritureComptable::create([
            'company_id' => $this->company->id,
            'code_journal_id' => $this->ran->id,
            'user_id' => $this->user->id,
            'exercices_comptables_id' => $this->exercice->id,
            'date' => '2026-01-01',
            'n_saisie' => $numero,
            'reference_piece' => $reference,
            'description_operation' => 'REPORT À NOUVEAU EXERCICE 2025',
            'plan_comptable_id' => $this->compte($compte)->id,
            'debit' => $debit,
            'credit' => $credit,
            'statut' => 'approved',
            'is_ran' => true,
        ]);
    }

    private function reparer(bool $appliquer = true)
    {
        return $this->artisan('saisies:reparer-resultat-reports', array_filter([
            '--company' => $this->company->id,
            '--appliquer' => $appliquer ?: null,
        ]));
    }

    public function test_un_benefice_parti_au_debit_repasse_au_credit(): void
    {
        // Caisse 5 000 000 au débit, résultat 5 000 000 au débit : écart 10 000 000.
        $this->ligne('RAN-2025', '57100000', 5000000, 0);
        $resultat = $this->ligne('RAN-2025', '13010000', 5000000, 0);

        $this->reparer()->assertSuccessful();

        $apres = $resultat->fresh();
        $this->assertEqualsWithDelta(0, (float) $apres->debit, 0.01);
        $this->assertEqualsWithDelta(5000000, (float) $apres->credit, 0.01);
    }

    public function test_le_report_retombe_juste(): void
    {
        $this->ligne('RAN-2025', '57100000', 5000000, 0);
        $this->ligne('RAN-2025', '13010000', 5000000, 0);

        $this->reparer()->assertSuccessful();

        $lignes = EcritureComptable::where('n_saisie', 'RAN-2025')->get();

        $this->assertEqualsWithDelta(
            $lignes->sum(fn ($l) => (float) $l->debit),
            $lignes->sum(fn ($l) => (float) $l->credit),
            0.01,
            'Le report doit être équilibré après réparation.'
        );
    }

    public function test_la_simulation_ne_modifie_rien(): void
    {
        $this->ligne('RAN-2025', '57100000', 5000000, 0);
        $resultat = $this->ligne('RAN-2025', '13010000', 5000000, 0);

        $this->reparer(false)->assertSuccessful();

        $this->assertEqualsWithDelta(5000000, (float) $resultat->fresh()->debit, 0.01);
    }

    public function test_un_report_deja_equilibre_nest_pas_touche(): void
    {
        $this->ligne('RAN-2025', '57100000', 5000000, 0);
        $resultat = $this->ligne('RAN-2025', '13010000', 0, 5000000);

        $this->reparer()->expectsOutputToContain('Aucun report à réparer')->assertSuccessful();

        $this->assertEqualsWithDelta(5000000, (float) $resultat->fresh()->credit, 0.01);
    }

    public function test_une_ligne_de_resultat_absente_est_ajoutee(): void
    {
        // Le cas d'A & I VENTURE : la clôture n'a jamais écrit la ligne de
        // résultat, faute de compte 1301 dans le plan. L'écart vaut alors le
        // résultat lui-même, et non le double.
        $this->ligne('RAN-2025', '57100000', 12072100, 0);

        $this->reparer()->assertSuccessful();

        $lignes = EcritureComptable::where('n_saisie', 'RAN-2025')->with('planComptable')->get();

        $this->assertCount(2, $lignes, 'La ligne de résultat doit avoir été ajoutée.');

        $resultat = $lignes->first(fn ($l) => str_starts_with($l->planComptable->numero_de_compte, '13'));
        $this->assertNotNull($resultat);
        $this->assertEqualsWithDelta(12072100, (float) $resultat->credit, 0.01, 'Un bénéfice va au crédit.');
        $this->assertSame('13010000', $resultat->planComptable->numero_de_compte);
        $this->assertTrue((bool) $resultat->is_ran);
    }

    public function test_le_compte_de_resultat_est_cree_sil_manque_au_plan(): void
    {
        // Un plan sans compte 13 du tout : la clôture passait son chemin.
        PlanComptable::where('company_id', $this->company->id)
            ->where('numero_de_compte', 'like', '13%')->delete();

        $this->ligne('RAN-2025', '57100000', 0, 4000000);

        $this->reparer()->assertSuccessful();

        $cree = PlanComptable::where('company_id', $this->company->id)
            ->where('numero_de_compte', 'like', '1309%')->first();

        $this->assertNotNull($cree, 'Le compte de perte doit être créé.');

        $lignes = EcritureComptable::where('n_saisie', 'RAN-2025')->get();
        $this->assertEqualsWithDelta(
            $lignes->sum(fn ($l) => (float) $l->debit),
            $lignes->sum(fn ($l) => (float) $l->credit),
            0.01
        );
    }

    public function test_la_simulation_najoute_aucune_ligne(): void
    {
        $this->ligne('RAN-2025', '57100000', 12072100, 0);

        $this->reparer(false)->assertSuccessful();

        $this->assertCount(1, EcritureComptable::where('n_saisie', 'RAN-2025')->get());
    }

    public function test_un_ecart_qui_ne_vient_pas_du_resultat_est_ecarte(): void
    {
        // Écart de 300 000, mais la ligne de résultat n'en vaut pas la moitié.
        $this->ligne('RAN-2025', '57100000', 5000000, 0);
        $this->ligne('RAN-2025', '13010000', 0, 4700000);

        $this->reparer()
            ->expectsOutputToContain('ne vaut pas la moitié')
            ->assertSuccessful();
    }

    public function test_un_report_saisi_a_la_main_nest_pas_touche(): void
    {
        // Pas de référence « RAN- » : ce n'est pas la clôture qui l'a produit.
        $this->ligne('RAN-2025', '57100000', 5000000, 0, 'PIECE-12');
        $resultat = $this->ligne('RAN-2025', '13010000', 5000000, 0, 'PIECE-12');

        $this->reparer()->assertSuccessful();

        $this->assertEqualsWithDelta(5000000, (float) $resultat->fresh()->debit, 0.01);
    }

    public function test_une_perte_partie_au_credit_repasse_au_debit_et_change_de_compte(): void
    {
        // Caisse 2 000 000 au crédit, résultat 2 000 000 au crédit : écart 4 000 000.
        $this->ligne('RAN-2025', '57100000', 0, 2000000);
        $resultat = $this->ligne('RAN-2025', '13010000', 0, 2000000);

        $this->reparer()->assertSuccessful();

        $apres = $resultat->fresh();
        $this->assertEqualsWithDelta(2000000, (float) $apres->debit, 0.01, 'Une perte va au débit.');
        $this->assertSame('13090000', $apres->planComptable->numero_de_compte, 'Et sur le compte de perte.');
    }
}
