<?php

namespace Tests\Feature;

use App\Models\CodeJournal;
use App\Models\Company;
use App\Models\EcritureComptable;
use App\Models\ExerciceComptable;
use App\Models\PlanComptable;
use App\Models\User;
use App\Services\AccountingReportingService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Le tableau des flux de la page « Flux de Trésorerie » ne doit présenter que
 * de l'argent réellement encaissé ou décaissé.
 *
 * Le calcul précédent lisait les mouvements des comptes d'immobilisations et
 * d'emprunts : une machine achetée à crédit y figurait en décaissement
 * d'investissement le mois de la facture, alors que la banque n'avait pas
 * bougé, et le règlement du mois suivant n'y figurait pas du tout.
 */
class TftMatriceTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;
    private User $user;
    private ExerciceComptable $exercice;
    private CodeJournal $journal;
    private array $comptes = [];
    private int $piece = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');

        $this->company = Company::create([
            'company_name' => 'Flux SARL',
            'activity' => 'Test',
            'juridique_form' => 'SARL',
            'email_adresse' => 'flux@test.ci',
        ]);

        $this->user = User::factory()->create(['company_id' => $this->company->id]);
        $this->actingAs($this->user);

        $this->exercice = ExerciceComptable::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'date_debut' => Carbon::now()->startOfYear()->toDateString(),
            'date_fin' => Carbon::now()->endOfYear()->toDateString(),
            'is_active' => true,
            'libelle' => 'Exercice ' . Carbon::now()->year,
        ]);

        $this->journal = CodeJournal::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'code_journal' => 'OD',
            'intitule' => 'Opérations diverses',
            'traitement_analytique' => 0,
        ]);

        foreach ([
            '52110000' => 'Banque',
            '24100000' => 'Matériel',
            '48100000' => "Fournisseurs d'investissement",
            '16200000' => 'Emprunts',
            '60100000' => 'Achats',
        ] as $numero => $intitule) {
            $this->comptes[$numero] = PlanComptable::create([
                'company_id' => $this->company->id,
                'user_id' => $this->user->id,
                'numero_de_compte' => $numero,
                'intitule' => $intitule,
            ]);
        }
    }

    /**
     * Enregistre une pièce équilibrée.
     *
     * @param  array<int, array{0:string,1:float,2:float}>  $lignes  compte, débit, crédit
     */
    private function piece(string $mois, array $lignes): void
    {
        $numero = 'ECR-' . str_pad((string) ++$this->piece, 6, '0', STR_PAD_LEFT);
        $date = Carbon::now()->startOfYear()->month((int) $mois)->day(10)->toDateString();

        foreach ($lignes as [$compte, $debit, $credit]) {
            EcritureComptable::create([
                'company_id' => $this->company->id,
                'code_journal_id' => $this->journal->id,
                'user_id' => $this->user->id,
                'exercices_comptables_id' => $this->exercice->id,
                'date' => $date,
                'n_saisie' => $numero,
                'description_operation' => 'Test',
                'plan_comptable_id' => $this->comptes[$compte]->id,
                'debit' => $debit,
                'credit' => $credit,
                'statut' => 'approved',
            ]);
        }
    }

    public function test_une_acquisition_a_credit_ne_compte_qu_au_reglement(): void
    {
        // Mars : facture du matériel, aucun mouvement de banque.
        $this->piece('3', [
            ['24100000', 5000000, 0],
            ['48100000', 0, 5000000],
        ]);

        // Avril : règlement du fournisseur d'investissement.
        $this->piece('4', [
            ['48100000', 5000000, 0],
            ['52110000', 0, 5000000],
        ]);

        $matrice = (new AccountingReportingService())
            ->getTFTMatrixData($this->exercice->id, $this->company->id);

        $acquisitions = $matrice['flux']['investissement']['acquisitions'];

        $this->assertEqualsWithDelta(0, $acquisitions[2], 0.01, 'Mars ne doit rien porter : la banque n\'a pas bougé.');
        $this->assertEqualsWithDelta(5000000, $acquisitions[3], 0.01, 'Le décaissement appartient à avril.');
        $this->assertEqualsWithDelta(5000000, array_sum($acquisitions), 0.01, 'L\'opération ne doit être comptée qu\'une fois.');
    }

    public function test_un_emprunt_encaisse_va_en_financement(): void
    {
        $this->piece('5', [
            ['52110000', 10000000, 0],
            ['16200000', 0, 10000000],
        ]);

        $matrice = (new AccountingReportingService())
            ->getTFTMatrixData($this->exercice->id, $this->company->id);

        $this->assertEqualsWithDelta(10000000, array_sum($matrice['flux']['financement']['net']), 0.01);
        $this->assertEqualsWithDelta(0, array_sum($matrice['flux']['investissement']['acquisitions']), 0.01);
    }

    public function test_un_achat_paye_comptant_reste_en_exploitation(): void
    {
        $this->piece('6', [
            ['60100000', 300000, 0],
            ['52110000', 0, 300000],
        ]);

        $matrice = (new AccountingReportingService())
            ->getTFTMatrixData($this->exercice->id, $this->company->id);

        $this->assertEqualsWithDelta(0, array_sum($matrice['flux']['investissement']['acquisitions']), 0.01);
        $this->assertEqualsWithDelta(0, array_sum($matrice['flux']['financement']['net']), 0.01);
        $this->assertEqualsWithDelta(300000, array_sum($matrice['flux']['operationnel']['caf']['charges_decaissables']), 0.01);
    }

    public function test_la_variation_de_tresorerie_suit_la_banque(): void
    {
        // Emprunt encaissé, matériel réglé, achat payé : la banque bouge de
        // +10 000 000 − 5 000 000 − 300 000 = +4 700 000.
        $this->piece('3', [['24100000', 5000000, 0], ['48100000', 0, 5000000]]);
        $this->piece('4', [['48100000', 5000000, 0], ['52110000', 0, 5000000]]);
        $this->piece('5', [['52110000', 10000000, 0], ['16200000', 0, 10000000]]);
        $this->piece('6', [['60100000', 300000, 0], ['52110000', 0, 300000]]);

        $matrice = (new AccountingReportingService())
            ->getTFTMatrixData($this->exercice->id, $this->company->id);

        $this->assertEqualsWithDelta(
            4700000,
            array_sum($matrice['flux']['tresorerie']['variation']),
            0.01,
            'La variation annoncée par le tableau doit être celle de la banque.'
        );
    }
}
