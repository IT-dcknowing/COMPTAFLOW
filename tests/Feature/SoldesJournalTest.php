<?php

namespace Tests\Feature;

use App\Models\CodeJournal;
use App\Models\Company;
use App\Models\EcritureComptable;
use App\Models\ExerciceComptable;
use App\Models\PlanComptable;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Le cadre des soldes, en haut de la page de saisie, doit dire la même chose
 * que la balance.
 *
 * Il ne comptait auparavant que les écritures DU JOURNAL ouvert. Une caisse
 * partagée entre le journal de caisse et les opérations diverses n'affichait
 * donc qu'une tranche, sous un libellé — « Solde août » — qui annonce un solde
 * de compte. Le chiffre était invérifiable, et pouvait paraître créditeur
 * alors que la caisse était pleine.
 */
class SoldesJournalTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;
    private User $user;
    private ExerciceComptable $exercice;
    private CodeJournal $caisse;
    private CodeJournal $od;
    private PlanComptable $compteCaisse;
    private PlanComptable $compteWave;
    private PlanComptable $compteAchats;
    private int $piece = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');

        $this->company = Company::create([
            'company_name' => 'Soldes SARL',
            'activity' => 'Test',
            'juridique_form' => 'SARL',
            'email_adresse' => 'soldes@test.ci',
        ]);

        $this->user = User::factory()->create(['company_id' => $this->company->id]);

        $this->exercice = ExerciceComptable::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'date_debut' => Carbon::create(2026, 1, 1)->toDateString(),
            'date_fin' => Carbon::create(2026, 12, 31)->toDateString(),
            'is_active' => true,
            'libelle' => 'Exercice 2026',
        ]);

        $this->compteCaisse = $this->compte('57100000', 'CAISSE');
        $this->compteWave = $this->compte('55200400', 'MONNAIE ELECTRONIQUE WAVE');
        $this->compteAchats = $this->compte('60100000', 'ACHATS');

        $this->caisse = CodeJournal::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'code_journal' => 'CAI1',
            'intitule' => 'Caisse',
            'type' => 'Caisse',
            'compte_de_tresorerie' => $this->compteCaisse->id,
            'traitement_analytique' => 0,
        ]);

        $this->od = CodeJournal::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'code_journal' => 'OD01',
            'intitule' => 'Opérations diverses',
            'type' => 'Divers',
            'traitement_analytique' => 0,
        ]);

        $this->actingAs($this->user);
        session([
            'current_company_id' => $this->company->id,
            'current_exercice_id' => $this->exercice->id,
        ]);
    }

    private function compte(string $numero, string $intitule): PlanComptable
    {
        return PlanComptable::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'numero_de_compte' => $numero,
            'intitule' => $intitule,
        ]);
    }

    /**
     * @param  array<int, array{0:PlanComptable,1:float,2:float}>  $lignes
     */
    private function piece(CodeJournal $journal, string $date, array $lignes, string $statut = 'approved'): void
    {
        $numero = 'ECR-' . str_pad((string) ++$this->piece, 6, '0', STR_PAD_LEFT);

        foreach ($lignes as [$compte, $debit, $credit]) {
            EcritureComptable::create([
                'company_id' => $this->company->id,
                'code_journal_id' => $journal->id,
                'user_id' => $this->user->id,
                'exercices_comptables_id' => $this->exercice->id,
                'date' => $date,
                'n_saisie' => $numero,
                'description_operation' => 'Test',
                'plan_comptable_id' => $compte->id,
                'debit' => $debit,
                'credit' => $credit,
                'statut' => $statut,
            ]);
        }
    }

    private function soldes(CodeJournal $journal, ?int $mois = null): array
    {
        return $this->getJson(route('ecriture.soldes_journal', array_filter([
            'journal_id' => $journal->id,
            'exercice_id' => $this->exercice->id,
            'mois' => $mois,
        ])))->assertOk()->json();
    }

    public function test_le_solde_compte_les_mouvements_des_autres_journaux(): void
    {
        // Janvier : la caisse est alimentée par le journal de caisse.
        $this->piece($this->caisse, '2026-01-10', [
            [$this->compteCaisse, 500000, 0],
            [$this->compteWave, 0, 500000],
        ]);

        // Janvier toujours : une sortie de caisse passée en OD. Elle appartient
        // à la caisse, même si elle n'est pas passée par le journal de caisse.
        $this->piece($this->od, '2026-01-20', [
            [$this->compteAchats, 200000, 0],
            [$this->compteCaisse, 0, 200000],
        ]);

        // Février : on se place sur le mois suivant pour lire le solde à fin janvier.
        $json = $this->soldes($this->caisse, 2);

        $caisse = collect($json['comptes'])->firstWhere('numero', '57100000');

        $this->assertNotNull($caisse, 'Le compte de caisse doit avoir sa propre ligne.');
        $this->assertEqualsWithDelta(
            300000,
            $caisse['ancien'],
            0.01,
            'Le solde doit valoir 500 000 − 200 000, la sortie en OD comprise.'
        );
    }

    public function test_chaque_compte_a_sa_ligne(): void
    {
        $this->piece($this->caisse, '2026-03-05', [
            [$this->compteCaisse, 100000, 0],
            [$this->compteWave, 0, 100000],
        ]);

        $json = $this->soldes($this->caisse, 3);

        $numeros = collect($json['comptes'])->pluck('numero')->sort()->values()->all();

        $this->assertSame(['55200400', '57100000'], $numeros);
        $this->assertTrue($json['tous_journaux']);
    }

    public function test_le_total_egale_la_somme_des_lignes(): void
    {
        $this->piece($this->caisse, '2026-03-05', [
            [$this->compteCaisse, 100000, 0],
            [$this->compteWave, 0, 100000],
        ]);

        $json = $this->soldes($this->caisse, 3);
        $comptes = collect($json['comptes']);

        $this->assertEqualsWithDelta($comptes->sum('debit'), $json['mouvements']['debit'], 0.01);
        $this->assertEqualsWithDelta($comptes->sum('credit'), $json['mouvements']['credit'], 0.01);
        $this->assertEqualsWithDelta($comptes->sum('nouveau'), $json['nouveau_solde'], 0.01);
    }

    public function test_le_nouveau_solde_est_lancien_plus_les_mouvements(): void
    {
        $this->piece($this->caisse, '2026-01-10', [
            [$this->compteCaisse, 500000, 0],
            [$this->compteWave, 0, 500000],
        ]);
        $this->piece($this->caisse, '2026-02-08', [
            [$this->compteAchats, 120000, 0],
            [$this->compteCaisse, 0, 120000],
        ]);

        $json = $this->soldes($this->caisse, 2);
        $caisse = collect($json['comptes'])->firstWhere('numero', '57100000');

        $this->assertEqualsWithDelta(500000, $caisse['ancien'], 0.01);
        $this->assertEqualsWithDelta(120000, $caisse['credit'], 0.01);
        $this->assertEqualsWithDelta(380000, $caisse['nouveau'], 0.01);
    }

    public function test_les_ecritures_rejetees_ne_comptent_pas(): void
    {
        $this->piece($this->caisse, '2026-01-10', [
            [$this->compteCaisse, 500000, 0],
            [$this->compteWave, 0, 500000],
        ]);
        $this->piece($this->caisse, '2026-01-15', [
            [$this->compteCaisse, 999999, 0],
            [$this->compteWave, 0, 999999],
        ], 'rejected');

        $json = $this->soldes($this->caisse, 2);
        $caisse = collect($json['comptes'])->firstWhere('numero', '57100000');

        $this->assertEqualsWithDelta(500000, $caisse['ancien'], 0.01);
    }

    public function test_un_journal_sans_tresorerie_garde_ses_propres_totaux(): void
    {
        $this->piece($this->od, '2026-04-02', [
            [$this->compteAchats, 75000, 0],
            [$this->compteCaisse, 0, 75000],
        ]);

        $json = $this->soldes($this->od, 4);

        $this->assertFalse($json['tous_journaux']);
        $this->assertSame([], $json['comptes']);
        $this->assertEqualsWithDelta(75000, $json['mouvements']['debit'], 0.01);
        $this->assertEqualsWithDelta(75000, $json['mouvements']['credit'], 0.01);
    }
}
