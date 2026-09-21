<?php

namespace Tests\Feature;

use App\Services\AnalyseFluxTresorerie;
use App\Services\ClassificationFlux;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Le tableau des flux doit dire ce que la comptabilité dit : des encaissements
 * et des décaissements bruts, rangés selon la nature de leur contrepartie.
 *
 * Ces cas n'ont besoin d'aucune base : l'analyse travaille sur des lignes
 * porteuses d'un compte, d'une date et d'un montant.
 */
class AnalyseFluxTresorerieTest extends TestCase
{
    private int $suivant = 1;

    /**
     * Une ligne d'écriture, réduite à ce que l'analyse lit.
     */
    private function ligne(string $numero, float $debit, float $credit, string $date, string $nSaisie = 'ECR-1', ?string $codeSyscohada = null): object
    {
        $compte = (object) ['numero_de_compte' => $numero, 'intitule' => 'Compte ' . $numero];

        $ligne = new class extends \stdClass {
            public $planComptable;
            public $posteTresorerie;
        };

        $ligne->id = $this->suivant++;
        $ligne->n_saisie = $nSaisie;
        $ligne->date = $date;
        $ligne->debit = $debit;
        $ligne->credit = $credit;
        $ligne->plan_comptable_id = crc32($numero);
        $ligne->planComptable = $compte;
        $ligne->posteTresorerie = $codeSyscohada
            ? (object) ['syscohada_line_id' => $codeSyscohada]
            : null;

        return $ligne;
    }

    /**
     * @return array<string, array{encaissements: float, decaissements: float}>
     */
    private function parSection(Collection $lignes): array
    {
        $totaux = [];

        foreach (AnalyseFluxTresorerie::mouvements($lignes) as $m) {
            $totaux[$m->section] ??= ['encaissements' => 0.0, 'decaissements' => 0.0];
            $totaux[$m->section][$m->sens] += $m->montant;
        }

        return $totaux;
    }

    public function test_un_encaissement_et_un_decaissement_sous_le_meme_numero_restent_distincts(): void
    {
        // Le défaut d'origine : trois pièces derrière ECR_000000000020.
        $lignes = collect([
            $this->ligne('57100000', 100000, 0, '2026-03-01'),
            $this->ligne('70100000', 0, 100000, '2026-03-01'),
            $this->ligne('60500000', 40000, 0, '2026-03-02'),
            $this->ligne('57100000', 0, 40000, '2026-03-02'),
            $this->ligne('60500000', 30000, 0, '2026-03-03'),
            $this->ligne('57100000', 0, 30000, '2026-03-03'),
        ]);

        $totaux = $this->parSection($lignes);

        // L'ancien calcul ne gardait que 30 000 d'encaissement et rien en sortie.
        $this->assertEqualsWithDelta(100000, $totaux[ClassificationFlux::OPERATIONNELLE]['encaissements'], 0.01);
        $this->assertEqualsWithDelta(70000, $totaux[ClassificationFlux::OPERATIONNELLE]['decaissements'], 0.01);
    }

    public function test_chaque_piece_part_dans_sa_propre_section(): void
    {
        $lignes = collect([
            $this->ligne('57100000', 100000, 0, '2026-03-01'),   // vente
            $this->ligne('70100000', 0, 100000, '2026-03-01'),
            $this->ligne('24100000', 40000, 0, '2026-04-05'),     // matériel
            $this->ligne('57100000', 0, 40000, '2026-04-05'),
            $this->ligne('16200000', 30000, 0, '2026-05-10'),     // emprunt
            $this->ligne('57100000', 0, 30000, '2026-05-10'),
        ]);

        $totaux = $this->parSection($lignes);

        $this->assertEqualsWithDelta(100000, $totaux[ClassificationFlux::OPERATIONNELLE]['encaissements'], 0.01);
        $this->assertEqualsWithDelta(40000, $totaux[ClassificationFlux::INVESTISSEMENT]['decaissements'], 0.01);
        $this->assertEqualsWithDelta(30000, $totaux[ClassificationFlux::FINANCEMENT]['decaissements'], 0.01);
    }

    public function test_un_virement_de_caisse_a_banque_n_est_pas_un_flux(): void
    {
        $lignes = collect([
            $this->ligne('52110000', 500000, 0, '2026-06-01'),
            $this->ligne('57100000', 0, 500000, '2026-06-01'),
        ]);

        $this->assertSame([], $this->parSection($lignes));
    }

    public function test_un_virement_avec_frais_ne_laisse_que_les_frais(): void
    {
        // Caisse -502 000, banque +500 000, commission 2 000.
        $lignes = collect([
            $this->ligne('52110000', 500000, 0, '2026-06-01'),
            $this->ligne('63100000', 2000, 0, '2026-06-01'),
            $this->ligne('57100000', 0, 502000, '2026-06-01'),
        ]);

        $totaux = $this->parSection($lignes);

        $this->assertEqualsWithDelta(2000, $totaux[ClassificationFlux::OPERATIONNELLE]['decaissements'], 0.01);
        $this->assertArrayNotHasKey('encaissements', array_filter($totaux[ClassificationFlux::OPERATIONNELLE], fn ($v) => $v > 0.01));
    }

    public function test_une_piece_qui_paie_un_fournisseur_et_une_machine_se_partage(): void
    {
        // L'ancien calcul basculait la totalité en investissement.
        $lignes = collect([
            $this->ligne('40100000', 30000, 0, '2026-07-01'),
            $this->ligne('24100000', 70000, 0, '2026-07-01'),
            $this->ligne('57100000', 0, 100000, '2026-07-01'),
        ]);

        $totaux = $this->parSection($lignes);

        $this->assertEqualsWithDelta(30000, $totaux[ClassificationFlux::OPERATIONNELLE]['decaissements'], 0.01);
        $this->assertEqualsWithDelta(70000, $totaux[ClassificationFlux::INVESTISSEMENT]['decaissements'], 0.01);
    }

    public function test_les_comptes_non_monetaires_ne_recoivent_aucun_flux(): void
    {
        // Cession d'un matériel : la valeur comptable et l'amortissement
        // n'expliquent aucun mouvement d'argent, seul le prix encaissé compte.
        $lignes = collect([
            $this->ligne('57100000', 12000, 0, '2026-08-01'),
            $this->ligne('28240000', 8000, 0, '2026-08-01'),
            $this->ligne('81000000', 5000, 0, '2026-08-01'),
            $this->ligne('24100000', 0, 13000, '2026-08-01'),
            $this->ligne('82200000', 0, 12000, '2026-08-01'),
        ]);

        $totaux = $this->parSection($lignes);

        $this->assertArrayNotHasKey(ClassificationFlux::OPERATIONNELLE, $totaux);
        $this->assertEqualsWithDelta(12000, $totaux[ClassificationFlux::INVESTISSEMENT]['encaissements'], 0.01);
    }

    public function test_un_poste_de_tresorerie_explicitement_classe_l_emporte(): void
    {
        $lignes = collect([
            $this->ligne('52110000', 10000000, 0, '2026-09-01', 'ECR-1', 'FIN_EMP'),
            $this->ligne('40100000', 0, 10000000, '2026-09-01'),
        ]);

        $totaux = $this->parSection($lignes);

        $this->assertEqualsWithDelta(10000000, $totaux[ClassificationFlux::FINANCEMENT]['encaissements'], 0.01);
    }

    public function test_une_piece_jamais_equilibree_reste_entiere_et_compte_en_brut(): void
    {
        // Le cas redouté : une saisie incomplète. Rien n'est découpé, mais les
        // montants restent bruts — le tableau ne perd aucun mouvement.
        $lignes = collect([
            $this->ligne('57100000', 50000, 0, '2026-10-01'),
            $this->ligne('70100000', 0, 30000, '2026-10-01'),
        ]);

        $totaux = $this->parSection($lignes);

        $this->assertEqualsWithDelta(50000, $totaux[ClassificationFlux::OPERATIONNELLE]['encaissements'], 0.01);
    }

    public function test_une_ligne_de_tresorerie_sans_contrepartie_n_est_pas_perdue(): void
    {
        $lignes = collect([
            $this->ligne('57100000', 4000, 0, '2026-11-01'),
            $this->ligne('28240000', 0, 4000, '2026-11-01'),
        ]);

        $mouvements = AnalyseFluxTresorerie::mouvements($lignes);

        $this->assertCount(1, $mouvements);
        $this->assertEqualsWithDelta(4000, $mouvements->first()->montant, 0.01);
        $this->assertStringContainsString('sans contrepartie', $mouvements->first()->libelle);
    }

    public function test_la_classification_suit_le_plan_syscohada(): void
    {
        $this->assertSame(ClassificationFlux::INVESTISSEMENT, ClassificationFlux::section('24100000'));
        $this->assertSame(ClassificationFlux::INVESTISSEMENT, ClassificationFlux::section('48100000'));
        $this->assertSame(ClassificationFlux::FINANCEMENT, ClassificationFlux::section('16200000'));
        $this->assertSame(ClassificationFlux::FINANCEMENT, ClassificationFlux::section('10100000'));
        $this->assertSame(ClassificationFlux::FINANCEMENT, ClassificationFlux::section('46500000'));
        $this->assertSame(ClassificationFlux::OPERATIONNELLE, ClassificationFlux::section('40100000'));
        $this->assertSame(ClassificationFlux::OPERATIONNELLE, ClassificationFlux::section('60500000'));

        // Amortissements et dépréciations ne sont pas des acquisitions.
        $this->assertTrue(ClassificationFlux::estNonMonetaire('28240000'));
        $this->assertTrue(ClassificationFlux::estNonMonetaire('29100000'));
        $this->assertSame(ClassificationFlux::OPERATIONNELLE, ClassificationFlux::section('28240000'));
    }
}
