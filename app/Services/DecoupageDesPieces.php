<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * Reconstitue les pièces cachées derrière un numéro de saisie unique.
 *
 * Une pièce comptable est équilibrée : la somme de ses débits égale la somme
 * de ses crédits. C'est le seul critère sûr pour savoir où une pièce s'arrête
 * et où la suivante commence. Les attributs de ligne ne le disent pas : les
 * deux lignes d'une même écriture peuvent porter des références de pièce
 * différentes, et les découper là-dessus revient à casser des écritures
 * saines en moitiés déséquilibrées.
 *
 * Le découpage se fait donc en deux temps :
 *   1. par date et par journal — une pièce ne chevauche ni l'un ni l'autre ;
 *   2. à l'intérieur, en coupant chaque fois que le solde cumulé revient à zéro.
 *
 * Si le solde ne revient jamais à zéro, rien n'est découpé : mieux vaut un
 * numéro partagé qu'une écriture mutilée.
 */
class DecoupageDesPieces
{
    /** Tolérance d'arrondi sur l'équilibre d'une pièce. */
    private const TOLERANCE = 0.01;

    /**
     * @param  Collection  $lignes  lignes partageant le même numéro de saisie
     * @return Collection<int, Collection>  une entrée par pièce reconstituée
     */
    public static function decouper(Collection $lignes): Collection
    {
        $pieces = collect();

        $blocs = $lignes
            ->sortBy([['date', 'asc'], ['id', 'asc']])
            ->groupBy(fn ($e) => $e->date . '|' . $e->code_journal_id);

        foreach ($blocs as $bloc) {
            foreach (self::decouperUnBloc($bloc->values()) as $piece) {
                $pieces->push($piece);
            }
        }

        return $pieces;
    }

    /**
     * Découpe un bloc d'une même date et d'un même journal sur le retour à zéro
     * du solde cumulé.
     *
     * @return array<int, Collection>
     */
    private static function decouperUnBloc(Collection $bloc): array
    {
        $pieces = [];
        $courante = [];
        $solde = 0.0;

        foreach ($bloc as $ligne) {
            $courante[] = $ligne;
            $solde += (float) $ligne->debit - (float) $ligne->credit;

            if (abs($solde) < self::TOLERANCE) {
                $pieces[] = collect($courante);
                $courante = [];
                $solde = 0.0;
            }
        }

        if ($courante === []) {
            return $pieces;
        }

        // Reliquat déséquilibré : on ne fabrique pas une pièce bancale.
        if ($pieces === []) {
            return [collect($courante)];
        }

        $dernier = array_pop($pieces);
        $pieces[] = $dernier->concat($courante);

        return $pieces;
    }

    /**
     * Une pièce est-elle équilibrée ?
     */
    public static function estEquilibree(Collection $piece): bool
    {
        return abs(self::solde($piece)) < self::TOLERANCE;
    }

    public static function solde(Collection $piece): float
    {
        return $piece->sum(fn ($e) => (float) $e->debit) - $piece->sum(fn ($e) => (float) $e->credit);
    }
}
