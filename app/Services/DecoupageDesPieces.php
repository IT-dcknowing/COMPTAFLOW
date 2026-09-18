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
 * Les lignes sont donc parcourues dans leur ordre d'enregistrement, et une
 * pièce se ferme chaque fois que le solde cumulé revient à zéro.
 *
 * Ni la date ni le journal ne servent de frontière : des écritures réelles
 * portent une ligne à une date et sa contrepartie au lendemain (une ligne au
 * 01/01 à +59 718 827, la contrepartie au 02/01), et couper là-dessus revient
 * encore à fabriquer deux moitiés déséquilibrées.
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
        // L'ordre des identifiants est celui de l'enregistrement : les lignes
        // d'une même pièce se suivent, qu'elles partagent ou non leur date.
        return collect(self::couperSurLEquilibre($lignes->sortBy('id')->values()));
    }

    /**
     * Ferme une pièce chaque fois que le solde cumulé revient à zéro.
     *
     * @return array<int, Collection>
     */
    private static function couperSurLEquilibre(Collection $lignes): array
    {
        $pieces = [];
        $courante = [];
        $solde = 0.0;

        foreach ($lignes as $ligne) {
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
