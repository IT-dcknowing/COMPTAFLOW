<?php

namespace Tests\Unit;

use App\Services\Import\EntryGroupingStrategy;
use PHPUnit\Framework\TestCase;

class EntryGroupingStrategyTest extends TestCase
{
    public function test_it_groups_multiple_invoice_references_under_one_balanced_piece(): void
    {
        $groupsBySaisie = [
            '2026-10|CSE1|CSE12610024' => ['d' => 239000, 'c' => 239000],
        ];

        $groupsByReference = [
            '2026-10|CSE1|ENC0110' => ['d' => 239000, 'c' => 0],
            '2026-10|CSE1|MB2979' => ['d' => 0, 'c' => 20000],
            '2026-10|CSE1|MB2980' => ['d' => 0, 'c' => 20000],
            '2026-10|CSE1|PROS2981' => ['d' => 0, 'c' => 50000],
            '2026-10|CSE1|CYTO0110-01' => ['d' => 0, 'c' => 20000],
            '2026-10|CSE1|IHC2703' => ['d' => 0, 'c' => 129000],
        ];

        $this->assertSame(
            'n_saisie',
            EntryGroupingStrategy::choose($groupsBySaisie, $groupsByReference, true, true)
        );
    }

    public function test_it_uses_reference_when_reference_groups_are_more_balanced(): void
    {
        $groupsBySaisie = [
            'piece-A' => ['d' => 100, 'c' => 0],
            'piece-B' => ['d' => 0, 'c' => 100],
        ];
        $groupsByReference = [
            'invoice-A' => ['d' => 100, 'c' => 100],
        ];

        $this->assertSame(
            'reference',
            EntryGroupingStrategy::choose($groupsBySaisie, $groupsByReference, true, true)
        );
    }

    public function test_it_defaults_to_n_saisie_when_both_strategies_have_the_same_score(): void
    {
        $groups = [
            'piece-A' => ['d' => 100, 'c' => 100],
        ];

        $this->assertSame('n_saisie', EntryGroupingStrategy::choose($groups, $groups, true, true));
    }

    public function test_it_uses_reference_when_no_n_saisie_column_is_mapped(): void
    {
        $this->assertSame('reference', EntryGroupingStrategy::choose([], [], false, true));
    }
}
