<?php

namespace App\Services\Import;

/**
 * Choisit la clé qui représente réellement une pièce comptable dans un lot.
 *
 * Le numéro de saisie identifie le mouvement comptable complet ; la référence
 * peut être une facture différente sur chacune de ses lignes. On compare donc
 * le nombre de groupes déséquilibrés des deux stratégies au lieu de déduire la
 * clé du seul nombre de valeurs distinctes.
 */
class EntryGroupingStrategy
{
    public static function choose(
        array $groupsBySaisie,
        array $groupsByReference,
        bool $hasNSaisie,
        bool $hasReference
    ): string {
        if ($hasReference && !$hasNSaisie) {
            return 'reference';
        }

        if (!$hasNSaisie) {
            return 'n_saisie';
        }

        if (!$hasReference) {
            return 'n_saisie';
        }

        $unbalancedNSaisie = self::countUnbalanced($groupsBySaisie);
        $unbalancedReference = self::countUnbalanced($groupsByReference);

        // En cas d'égalité, conserver le numéro de saisie : c'est la clé
        // comptable principale du fichier d'import.
        return $unbalancedReference < $unbalancedNSaisie
            ? 'reference'
            : 'n_saisie';
    }

    private static function countUnbalanced(array $groups): int
    {
        $count = 0;

        foreach ($groups as $totals) {
            if (abs((float) ($totals['d'] ?? 0) - (float) ($totals['c'] ?? 0)) > 0.01) {
                $count++;
            }
        }

        return $count;
    }
}
