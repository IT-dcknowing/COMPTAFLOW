<?php

namespace Tests\Feature;

use App\Models\CodeJournal;
use App\Models\Company;
use App\Models\EcritureComptable;
use App\Models\ExerciceComptable;
use App\Models\PlanComptable;
use App\Models\User;
use App\Services\CopieDesEcritures;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Recopier des écritures d'un journal vers un autre, et vers d'autres mois.
 *
 * Ce qui revient à l'identique chaque mois — loyer, salaires, abonnements — se
 * ressaisissait douze fois. Autant d'occasions de se tromper.
 *
 * Le point délicat n'est pas la copie elle-même mais le NUMÉRO DE SAISIE. Il
 * désigne la pièce : toutes les lignes qui le partagent forment une seule
 * opération, et c'est sur lui que se construit le tableau des flux de
 * trésorerie. Recopier un numéro ferait de la copie et de l'originale une même
 * pièce, à deux dates et dans deux journaux — le TFT y lirait des contreparties
 * qui n'existent pas.
 */
class CopieDesEcrituresTest extends TestCase
{
    use DatabaseTransactions;

    private Company $company;
    private User $user;
    private ExerciceComptable $exercice;
    private CodeJournal $achats;
    private CodeJournal $operations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');

        $this->company = Company::create([
            'company_name' => 'Copie SARL',
            'activity' => 'Test',
            'juridique_form' => 'SARL',
            'email_adresse' => 'copie@test.ci',
            'account_digits' => 8,
        ]);

        $this->user = User::factory()->create(['company_id' => $this->company->id]);

        $this->exercice = ExerciceComptable::create([
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'intitule' => 'EXERCICE 2026',
            'date_debut' => '2026-01-01',
            'date_fin' => '2026-12-31',
            'is_active' => true,
            'cloturer' => 0,
        ]);

        foreach ([['ACH', 'Achats'], ['OD', 'Opérations diverses']] as [$code, $intitule]) {
            $journal = CodeJournal::create([
                'company_id' => $this->company->id,
                'user_id' => $this->user->id,
                'code_journal' => $code,
                'intitule' => $intitule,
                'traitement_analytique' => 0,
            ]);

            $code === 'ACH' ? $this->achats = $journal : $this->operations = $journal;
        }

        foreach (['62200000' => 'LOCATIONS', '40100000' => 'FOURNISSEURS'] as $numero => $intitule) {
            PlanComptable::create([
                'company_id' => $this->company->id,
                'user_id' => $this->user->id,
                'numero_de_compte' => $numero,
                'intitule' => $intitule,
            ]);
        }

        $this->actingAs($this->user);
        session(['current_company_id' => $this->company->id, 'current_exercice_id' => $this->exercice->id]);
    }

    private function compte(string $numero): PlanComptable
    {
        return PlanComptable::where('company_id', $this->company->id)
            ->where('numero_de_compte', $numero)->firstOrFail();
    }

    /** Une pièce de loyer : charge au débit, fournisseur au crédit. */
    private function loyerDeJanvier(string $numeroDeSaisie = 'ECR-010126-000001', string $date = '2026-01-05'): array
    {
        $commun = [
            'company_id' => $this->company->id,
            'user_id' => $this->user->id,
            'n_saisie' => $numeroDeSaisie,
            'n_saisie_user' => 'CPT-XX-010126-000001',
            'code_journal_id' => $this->achats->id,
            'exercices_comptables_id' => $this->exercice->id,
            'date' => $date,
            'description_operation' => 'Loyer du mois',
            'reference_piece' => 'FACT-LOYER',
            'statut' => 'approved',
        ];

        return [
            EcritureComptable::create($commun + [
                'plan_comptable_id' => $this->compte('62200000')->id,
                'debit' => 500000, 'credit' => 0,
            ]),
            EcritureComptable::create($commun + [
                'plan_comptable_id' => $this->compte('40100000')->id,
                'debit' => 0, 'credit' => 500000,
            ]),
        ];
    }

    public function test_la_copie_garde_tout_sauf_le_journal_le_mois_et_le_numero(): void
    {
        [$charge] = $this->loyerDeJanvier();

        $issue = CopieDesEcritures::copier(
            $this->company->id,
            [$charge->id],
            $this->operations->id,
            ['2026-03'],
            $this->user->id,
            $this->user
        );

        $this->assertSame(1, $issue['creees'], implode(' ', $issue['refus']));

        $copie = EcritureComptable::where('company_id', $this->company->id)
            ->where('code_journal_id', $this->operations->id)->firstOrFail();

        $this->assertSame('2026-03-05', substr((string) $copie->date, 0, 10),
            'Le jour du mois est conservé, le mois est celui qu’on a choisi.');
        $this->assertSame($charge->plan_comptable_id, $copie->plan_comptable_id);
        $this->assertSame('Loyer du mois', $copie->description_operation);
        $this->assertSame('FACT-LOYER', $copie->reference_piece);
        $this->assertEquals(500000, $copie->debit);
        $this->assertSame('approved', $copie->statut);

        $this->assertNotSame($charge->n_saisie, $copie->n_saisie,
            "Recopier le numéro ferait de la copie et de l'originale une seule pièce.");
    }

    public function test_les_lignes_dune_meme_piece_restent_une_seule_piece(): void
    {
        [$charge, $fournisseur] = $this->loyerDeJanvier();

        CopieDesEcritures::copier(
            $this->company->id,
            [$charge->id, $fournisseur->id],
            $this->achats->id,
            ['2026-02'],
            $this->user->id,
            $this->user
        );

        $copies = EcritureComptable::where('company_id', $this->company->id)
            ->where('date', '2026-02-05')->get();

        $this->assertCount(2, $copies);
        $this->assertCount(1, $copies->pluck('n_saisie')->unique(),
            'Les deux lignes forment une seule opération : un seul numéro.');
        $this->assertEqualsWithDelta(0, $copies->sum('debit') - $copies->sum('credit'), 0.01,
            'Et la pièce recopiée reste équilibrée.');
    }

    public function test_chaque_mois_recoit_sa_propre_piece(): void
    {
        [$charge, $fournisseur] = $this->loyerDeJanvier();

        $issue = CopieDesEcritures::copier(
            $this->company->id,
            [$charge->id, $fournisseur->id],
            $this->achats->id,
            ['2026-02', '2026-03', '2026-04'],
            $this->user->id,
            $this->user
        );

        $this->assertSame(6, $issue['creees']);
        $this->assertSame(3, $issue['pieces']);

        $numeros = EcritureComptable::where('company_id', $this->company->id)
            ->where('date', '!=', '2026-01-05')->pluck('n_saisie')->unique();

        $this->assertCount(3, $numeros,
            'Trois mois, trois opérations distinctes — jamais une seule à trois dates.');
    }

    public function test_un_31_recopie_en_fevrier_se_pose_au_dernier_jour(): void
    {
        [$charge] = $this->loyerDeJanvier('ECR-010126-000009', '2026-01-31');

        CopieDesEcritures::copier(
            $this->company->id, [$charge->id], $this->achats->id, ['2026-02'],
            $this->user->id, $this->user
        );

        $copie = EcritureComptable::where('company_id', $this->company->id)
            ->where('id', '!=', $charge->id)->firstOrFail();

        $this->assertSame('2026-02-28', substr((string) $copie->date, 0, 10),
            'Glisser en mars sans prévenir serait pire que de se poser au dernier jour.');
    }

    public function test_une_ligne_deja_presente_nest_pas_doublee(): void
    {
        [$charge] = $this->loyerDeJanvier();

        CopieDesEcritures::copier($this->company->id, [$charge->id], $this->achats->id,
            ['2026-02'], $this->user->id, $this->user);

        $seconde = CopieDesEcritures::copier($this->company->id, [$charge->id], $this->achats->id,
            ['2026-02'], $this->user->id, $this->user);

        $this->assertSame(0, $seconde['creees'], 'Un second clic ne doit pas doubler les livres.');
        $this->assertSame(1, EcritureComptable::where('company_id', $this->company->id)
            ->where('date', '2026-02-05')->count());
    }

    public function test_un_mois_hors_exercice_est_refuse(): void
    {
        [$charge] = $this->loyerDeJanvier();

        $issue = CopieDesEcritures::copier($this->company->id, [$charge->id], $this->achats->id,
            ['2027-05'], $this->user->id, $this->user);

        $this->assertSame(0, $issue['creees']);
        $this->assertNotEmpty($issue['refus']);
        $this->assertStringContainsString('aucun exercice', implode(' ', $issue['refus']));
    }

    public function test_un_exercice_clos_est_refuse(): void
    {
        [$charge] = $this->loyerDeJanvier();

        ExerciceComptable::create([
            'company_id' => $this->company->id, 'user_id' => $this->user->id,
            'intitule' => 'EXERCICE 2025', 'date_debut' => '2025-01-01', 'date_fin' => '2025-12-31',
            'is_active' => false, 'cloturer' => 1,
        ]);

        $issue = CopieDesEcritures::copier($this->company->id, [$charge->id], $this->achats->id,
            ['2025-06'], $this->user->id, $this->user);

        $this->assertSame(0, $issue['creees']);
        $this->assertStringContainsString('clos', implode(' ', $issue['refus']));
    }

    public function test_un_report_a_nouveau_ne_se_recopie_pas(): void
    {
        // Il naît de la clôture : le recopier ferait naître une ouverture de
        // comptes qui n'a pas eu lieu, et le TFT compterait un encaissement.
        $report = EcritureComptable::create([
            'company_id' => $this->company->id, 'user_id' => $this->user->id,
            'n_saisie' => 'ECR-010126-000050', 'code_journal_id' => $this->achats->id,
            'exercices_comptables_id' => $this->exercice->id, 'date' => '2026-01-01',
            'description_operation' => 'Report à nouveau',
            'plan_comptable_id' => $this->compte('40100000')->id,
            'debit' => 0, 'credit' => 900000, 'statut' => 'approved', 'is_ran' => true,
        ]);

        $issue = CopieDesEcritures::copier($this->company->id, [$report->id], $this->achats->id,
            ['2026-02'], $this->user->id, $this->user);

        $this->assertSame(0, $issue['creees']);

        $source = CopieDesEcritures::source($this->company->id, $this->achats->id, '2026-01');

        $this->assertFalse($source->flatten()->contains('id', $report->id),
            "Un report à nouveau ne s'offre même pas à la copie.");
    }

    public function test_le_numero_se_prend_comme_pour_une_ecriture_saisie_a_la_main(): void
    {
        // Rien de particulier aux copies : le prochain numero disponible du
        // jour ou l'on saisit, exactement comme la saisie manuelle. Le dater du
        // mois d'arrivee aurait fabrique une seconde convention.
        [$charge] = $this->loyerDeJanvier();

        $attendu = 'ECR-' . now()->format('dmy') . '-';

        CopieDesEcritures::copier($this->company->id, [$charge->id], $this->achats->id,
            ['2026-07'], $this->user->id, $this->user);

        $copie = EcritureComptable::where('company_id', $this->company->id)
            ->where('date', '2026-07-05')->firstOrFail();

        $this->assertStringStartsWith($attendu, (string) $copie->n_saisie,
            'Le numero porte la date de SAISIE, pas la date comptable.');
        $this->assertStringStartsWith('CPT-', (string) $copie->n_saisie_user);
    }

    public function test_deux_copies_successives_ne_partagent_jamais_un_numero(): void
    {
        [$charge, $fournisseur] = $this->loyerDeJanvier();

        CopieDesEcritures::copier($this->company->id, [$charge->id, $fournisseur->id],
            $this->achats->id, ['2026-08'], $this->user->id, $this->user);

        CopieDesEcritures::copier($this->company->id, [$charge->id, $fournisseur->id],
            $this->achats->id, ['2026-09'], $this->user->id, $this->user);

        $numeros = EcritureComptable::where('company_id', $this->company->id)
            ->whereIn('date', ['2026-08-05', '2026-09-05'])
            ->pluck('n_saisie')->unique();

        $this->assertCount(2, $numeros,
            'Chaque copie prend le prochain numero disponible, et aucun ne se repete.');
    }

    public function test_lapercu_annonce_sans_rien_ecrire(): void
    {
        [$charge, $fournisseur] = $this->loyerDeJanvier();

        $apercu = CopieDesEcritures::apercu(
            $this->company->id, [$charge->id, $fournisseur->id], $this->achats->id,
            ['2026-02', '2026-03']
        );

        $this->assertSame(2, $apercu['lignes']);
        $this->assertSame(1, $apercu['pieces']);
        $this->assertSame(4, $apercu['creations']);
        $this->assertSame([], $apercu['refus']);
        $this->assertSame(2, EcritureComptable::where('company_id', $this->company->id)->count(),
            "L'aperçu annonce, il n'écrit pas.");
    }

    public function test_une_piece_a_moitie_cochee_est_signalee(): void
    {
        [$charge] = $this->loyerDeJanvier();

        $apercu = CopieDesEcritures::apercu(
            $this->company->id, [$charge->id], $this->achats->id, ['2026-02']
        );

        $this->assertNotEmpty($apercu['desequilibrees'],
            "On ne refuse pas, mais on le dit avant plutôt qu'après.");
    }

    public function test_seuls_les_mois_dun_exercice_sont_proposes(): void
    {
        $mois = CopieDesEcritures::moisDisponibles($this->company->id);

        $this->assertCount(12, $mois);
        $this->assertSame('2026-01', $mois[0]['valeur']);
        $this->assertSame('janvier 2026', $mois[0]['libelle']);
    }

    public function test_une_ligne_dune_autre_comptabilite_nest_jamais_recopiee(): void
    {
        $autre = Company::create([
            'company_name' => 'Autre SARL', 'activity' => 'Test',
            'juridique_form' => 'SARL', 'account_digits' => 8,
        ]);

        $etrangere = EcritureComptable::create([
            'company_id' => $autre->id, 'user_id' => $this->user->id,
            'n_saisie' => 'ECR-010126-000099', 'code_journal_id' => $this->achats->id,
            'exercices_comptables_id' => $this->exercice->id, 'date' => '2026-01-10',
            'description_operation' => 'Chez le voisin',
            'plan_comptable_id' => $this->compte('62200000')->id,
            'debit' => 1000, 'credit' => 0, 'statut' => 'approved',
        ]);

        $issue = CopieDesEcritures::copier($this->company->id, [$etrangere->id],
            $this->achats->id, ['2026-02'], $this->user->id, $this->user);

        $this->assertSame(0, $issue['creees']);
        $this->assertSame(0, EcritureComptable::where('company_id', $this->company->id)
            ->where('description_operation', 'Chez le voisin')->count());
    }

    public function test_la_page_montre_les_ecritures_du_journal_et_du_mois(): void
    {
        $this->loyerDeJanvier();

        $this->get(route('adjustment.copie', [
            'journal_source' => $this->achats->id,
            'mois_source' => '2026-01',
        ]))
            ->assertOk()
            ->assertSee('Loyer du mois', false)
            ->assertSee('Tout le journal', false);
    }

    public function test_la_page_ne_devine_aucun_journal(): void
    {
        $this->loyerDeJanvier();

        $this->get(route('adjustment.copie'))
            ->assertOk()
            ->assertDontSee('Loyer du mois', false);
    }

    public function test_lecran_recopie(): void
    {
        [$charge, $fournisseur] = $this->loyerDeJanvier();

        $this->post(route('adjustment.copie.appliquer'), [
            'ids' => [$charge->id, $fournisseur->id],
            'journal_cible' => $this->operations->id,
            'mois_cibles' => ['2026-05'],
        ])->assertRedirect();

        $this->assertSame(2, EcritureComptable::where('company_id', $this->company->id)
            ->where('date', '2026-05-05')->count());
    }

    public function test_lecran_refuse_une_copie_sans_mois(): void
    {
        [$charge] = $this->loyerDeJanvier();

        $this->post(route('adjustment.copie.appliquer'), [
            'ids' => [$charge->id],
            'journal_cible' => $this->operations->id,
        ])->assertSessionHasErrors('mois_cibles');

        $this->assertSame(2, EcritureComptable::where('company_id', $this->company->id)->count());
    }
}
