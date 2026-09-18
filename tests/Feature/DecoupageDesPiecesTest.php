<?php

namespace Tests\Feature;

use App\Services\DecoupageDesPieces;
use Illuminate\Support\Collection;
use Tests\TestCase;

/**
 * Reconstitution des pièces derrière un numéro de saisie partagé.
 *
 * Une première version découpait sur la référence de pièce : elle coupait en
 * deux des écritures saines dont les deux lignes portaient des références
 * différentes, et produisait des moitiés symétriques (+290 000 / −290 000).
 * Le découpage ne doit reposer que sur l'équilibre.
 */
class DecoupageDesPiecesTest extends TestCase
{
    /** @param array<int, array{0:string,1:int,2:float,3:float}> $lignes date, journal, débit, crédit */
    private function lignes(array $lignes): Collection
    {
        return collect($lignes)->map(fn ($l, $i) => (object) [
            'id' => $i + 1,
            'date' => $l[0],
            'code_journal_id' => $l[1],
            'debit' => $l[2],
            'credit' => $l[3],
        ]);
    }

    public function test_une_ecriture_a_deux_lignes_reste_entiere(): void
    {
        $pieces = DecoupageDesPieces::decouper($this->lignes([
            ['2025-01-29', 4, 290000, 0],
            ['2025-01-29', 4, 0, 290000],
        ]));

        $this->assertCount(1, $pieces, "Une écriture équilibrée ne doit jamais être découpée.");
    }

    public function test_une_ecriture_a_trois_lignes_reste_entiere(): void
    {
        // Achat HT + TVA + dette fournisseur.
        $pieces = DecoupageDesPieces::decouper($this->lignes([
            ['2025-01-30', 4, 2615603, 0],
            ['2025-01-30', 4, 470808, 0],
            ['2025-01-30', 4, 0, 3086411],
        ]));

        $this->assertCount(1, $pieces);
    }

    public function test_des_ecritures_empilees_sous_un_numero_sont_separees(): void
    {
        $pieces = DecoupageDesPieces::decouper($this->lignes([
            ['2025-01-29', 4, 290000, 0],
            ['2025-01-29', 4, 0, 290000],
            ['2025-01-29', 4, 76000, 0],
            ['2025-01-29', 4, 0, 76000],
            ['2025-01-29', 4, 115000, 0],
            ['2025-01-29', 4, 0, 115000],
        ]));

        $this->assertCount(3, $pieces);
        $pieces->each(fn ($p) => $this->assertTrue(DecoupageDesPieces::estEquilibree($p)));
        $this->assertSame([2, 2, 2], $pieces->map->count()->all());
    }

    public function test_des_pieces_de_dates_et_journaux_differents_sont_separees(): void
    {
        $pieces = DecoupageDesPieces::decouper($this->lignes([
            ['2025-01-29', 4, 1000, 0],
            ['2025-01-29', 4, 0, 1000],
            ['2025-01-30', 4, 2000, 0],
            ['2025-01-30', 4, 0, 2000],
            ['2025-01-30', 7, 3000, 0],
            ['2025-01-30', 7, 0, 3000],
        ]));

        $this->assertCount(3, $pieces);
    }

    /**
     * Cas rencontre en production (ECR_000000004580) : une ligne au 01/01 et
     * sa contrepartie au 02/01. Découper sur la date fabriquait deux moitiés
     * à +59 718 827 et −59 718 827.
     */
    public function test_une_ecriture_a_cheval_sur_deux_jours_reste_entiere(): void
    {
        $pieces = DecoupageDesPieces::decouper($this->lignes([
            ['2024-01-01', 4, 59718827, 0],
            ['2024-01-02', 4, 0, 59718827],
        ]));

        $this->assertCount(1, $pieces);
        $this->assertTrue(DecoupageDesPieces::estEquilibree($pieces->first()));
    }

    /** Cas rencontre en production (ECR_000000001108) : 1 ligne au 30/07, 6 au 31/07. */
    public function test_une_ecriture_dont_les_contreparties_sont_au_lendemain_reste_entiere(): void
    {
        $pieces = DecoupageDesPieces::decouper($this->lignes([
            ['2024-07-30', 4, 302674, 0],
            ['2024-07-31', 4, 0, 50000],
            ['2024-07-31', 4, 0, 50000],
            ['2024-07-31', 4, 0, 50000],
            ['2024-07-31', 4, 0, 50000],
            ['2024-07-31', 4, 0, 50000],
            ['2024-07-31', 4, 0, 52674],
        ]));

        $this->assertCount(1, $pieces);
        $this->assertCount(7, $pieces->first());
    }

    /** Une piece a cheval au milieu d'une pile ne doit pas entrainer ses voisines. */
    public function test_une_piece_a_cheval_au_milieu_dune_pile(): void
    {
        $pieces = DecoupageDesPieces::decouper($this->lignes([
            ['2024-01-01', 4, 1000, 0],
            ['2024-01-01', 4, 0, 1000],
            ['2024-01-01', 4, 5000, 0],
            ['2024-01-02', 4, 0, 5000],
            ['2024-01-02', 4, 2000, 0],
            ['2024-01-02', 4, 0, 2000],
        ]));

        $this->assertCount(3, $pieces);
        $this->assertSame([2, 2, 2], $pieces->map->count()->all());
        $pieces->each(fn ($p) => $this->assertTrue(DecoupageDesPieces::estEquilibree($p)));
    }

    public function test_un_bloc_jamais_equilibre_reste_intact(): void
    {
        // Mieux vaut un numéro partagé qu'une écriture mutilée.
        $pieces = DecoupageDesPieces::decouper($this->lignes([
            ['2025-01-29', 4, 500, 0],
            ['2025-01-29', 4, 300, 0],
        ]));

        $this->assertCount(1, $pieces);
        $this->assertFalse(DecoupageDesPieces::estEquilibree($pieces->first()));
    }

    public function test_un_reliquat_desequilibre_est_recolle_a_la_piece_precedente(): void
    {
        $pieces = DecoupageDesPieces::decouper($this->lignes([
            ['2025-01-29', 4, 1000, 0],
            ['2025-01-29', 4, 0, 1000],
            ['2025-01-29', 4, 250, 0],
        ]));

        $this->assertCount(1, $pieces, 'Le reliquat ne doit pas devenir une pièce bancale à lui seul.');
        $this->assertCount(3, $pieces->first());
    }

    public function test_les_centimes_ne_font_pas_echouer_lequilibre(): void
    {
        $pieces = DecoupageDesPieces::decouper($this->lignes([
            ['2025-01-29', 4, 33.33, 0],
            ['2025-01-29', 4, 33.33, 0],
            ['2025-01-29', 4, 33.34, 0],
            ['2025-01-29', 4, 0, 100.00],
        ]));

        $this->assertCount(1, $pieces);
    }

    public function test_un_import_de_plusieurs_journees_est_reconstitue(): void
    {
        // Le cas reel : des dizaines de pieces sous un seul numero.
        $brut = [];
        foreach (['2026-02-27', '2026-02-28'] as $date) {
            for ($i = 1; $i <= 20; $i++) {
                $brut[] = [$date, 4, 1000 * $i, 0];
                $brut[] = [$date, 4, 0, 1000 * $i];
            }
        }

        $pieces = DecoupageDesPieces::decouper($this->lignes($brut));

        $this->assertCount(40, $pieces);
        $pieces->each(fn ($p) => $this->assertTrue(DecoupageDesPieces::estEquilibree($p)));
    }
}
