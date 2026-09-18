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

    public function test_une_piece_ne_chevauche_ni_date_ni_journal(): void
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
