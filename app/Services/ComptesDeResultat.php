<?php

namespace App\Services;

use App\Models\PlanComptable;

/**
 * Les comptes qui portent le résultat de l'exercice.
 *
 * SYSCOHADA révisé, classe 13 « Résultat net de l'exercice » :
 *   1301  Résultat en instance d'affectation — Bénéfice   (soldé au CRÉDIT)
 *   1309  Résultat en instance d'affectation — Perte      (soldé au DÉBIT)
 *
 * Un bénéfice enrichit les capitaux propres : il se porte au crédit. Une perte
 * les diminue : elle se porte au débit. C'est l'inverse de l'intuition qu'on a
 * en regardant un relevé bancaire, et c'est la source d'erreur la plus
 * fréquente sur cette écriture.
 *
 * Les numéros sont complétés à la longueur du dossier (1301 devient 13010000
 * sur huit chiffres), on cherche donc par préfixe.
 */
class ComptesDeResultat
{
    public const BENEFICE = '1301';
    public const PERTE = '1309';

    /** Toute la classe 13 : résultat net de l'exercice. */
    public static function estCompteDeResultat(?string $numero): bool
    {
        return $numero !== null && str_starts_with($numero, '13');
    }

    /**
     * Le compte à mouvementer pour un résultat donné.
     *
     * @param  float  $resultat  positif = bénéfice, négatif = perte
     */
    public static function pour(int $companyId, float $resultat): ?PlanComptable
    {
        $prefixe = $resultat >= 0 ? self::BENEFICE : self::PERTE;

        $compte = PlanComptable::where('company_id', $companyId)
            ->where('numero_de_compte', 'like', $prefixe . '%')
            ->orderBy('numero_de_compte')
            ->first();

        if ($compte) {
            return $compte;
        }

        // Repli sur les comptes 131 / 139 « Résultat net », que certains plans
        // utilisent à la place des 1301 / 1309.
        $repli = $resultat >= 0 ? '131' : '139';

        return PlanComptable::where('company_id', $companyId)
            ->where('numero_de_compte', 'like', $repli . '%')
            ->orderBy('numero_de_compte')
            ->first();
    }

    /**
     * Le sens et le montant à porter sur ce compte.
     *
     * @return array{debit: float, credit: float}
     */
    public static function sens(float $resultat): array
    {
        return $resultat >= 0
            ? ['debit' => 0.0, 'credit' => round($resultat, 2)]
            : ['debit' => round(abs($resultat), 2), 'credit' => 0.0];
    }

    /**
     * Une phrase lisible pour l'écran de clôture.
     */
    public static function annonce(float $resultat): string
    {
        $montant = number_format(abs($resultat), 0, ',', ' ');

        return $resultat >= 0
            ? "Bénéfice de $montant : il sera porté au CRÉDIT du compte de résultat (1301)."
            : "Perte de $montant : elle sera portée au DÉBIT du compte de résultat (1309).";
    }
}
