<?php

namespace App\Services;

/**
 * À quelle section du tableau des flux appartient un compte ?
 *
 * Le classement d'un mouvement de trésorerie ne se lit pas sur le compte de
 * trésorerie lui-même : une même caisse sert à payer un fournisseur, à acheter
 * une machine et à rembourser un emprunt. Ce qui distingue ces trois flux,
 * c'est la CONTREPARTIE. C'est donc elle qu'on interroge ici, exactement comme
 * la balance interroge le numéro de compte pour ranger un solde.
 *
 * Trois sections, au sens du SYSCOHADA révisé :
 *   - investissement : acquisitions et cessions d'immobilisations
 *   - financement    : capital, subventions d'investissement, emprunts,
 *                      dividendes versés
 *   - opérationnelle : tout le reste (exploitation courante)
 *
 * Certains comptes ne correspondent à aucun mouvement d'argent — dotations,
 * amortissements, dépréciations, valeurs comptables de cession. Ils peuvent
 * figurer dans une pièce qui touche la trésorerie sans en expliquer le
 * montant : les répartir au prorata reviendrait à inventer un flux. Ils sont
 * donc écartés (voir estNonMonetaire).
 */
class ClassificationFlux
{
    public const OPERATIONNELLE = 'operationnelle';
    public const INVESTISSEMENT = 'investissement';
    public const FINANCEMENT = 'financement';

    /**
     * Comptes de trésorerie : classe 5.
     */
    public static function estTresorerie(?string $numero): bool
    {
        return $numero !== null && str_starts_with($numero, '5');
    }

    /**
     * Comptes qui ne traduisent aucun encaissement ni décaissement.
     *
     * 19 provisions pour risques · 28 amortissements · 29 dépréciations
     * 68/69 dotations · 78/79 reprises · 81/85 valeurs comptables de cession
     */
    public static function estNonMonetaire(?string $numero): bool
    {
        if ($numero === null) {
            return true;
        }

        foreach (['19', '28', '29', '68', '69', '78', '79', '81', '85'] as $prefixe) {
            if (str_starts_with($numero, $prefixe)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Section du tableau des flux pour un compte de contrepartie.
     */
    public static function section(?string $numero): string
    {
        if ($numero === null) {
            return self::OPERATIONNELLE;
        }

        if (self::estInvestissement($numero)) {
            return self::INVESTISSEMENT;
        }

        if (self::estFinancement($numero)) {
            return self::FINANCEMENT;
        }

        return self::OPERATIONNELLE;
    }

    /**
     * Acquisitions et cessions d'immobilisations.
     *
     * 20 à 27 immobilisations, amortissements et dépréciations exclus
     * 481/482 fournisseurs d'investissement · 485 créances sur cessions
     * 822/826/827 produits de cessions d'immobilisations
     */
    private static function estInvestissement(string $numero): bool
    {
        if (self::estNonMonetaire($numero)) {
            return false;
        }

        // Classe 2 hors amortissements (28) et dépréciations (29), déjà
        // écartés au-dessus : il reste les immobilisations elles-mêmes.
        if (str_starts_with($numero, '2')) {
            return true;
        }

        foreach (['481', '482', '485', '822', '826', '827'] as $prefixe) {
            if (str_starts_with($numero, $prefixe)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Capitaux propres et capitaux étrangers.
     *
     * 10 capital · 14 subventions d'investissement · 16/17/18 emprunts et
     * autres dettes financières · 461 apports des associés
     * 465 dividendes à payer · 4619 capital à rembourser
     *
     * Les comptes 11 réserves, 12 report à nouveau, 13 résultat et 15
     * provisions réglementées ne donnent lieu à aucun mouvement de trésorerie
     * par eux-mêmes : ils restent en opérationnel, où ils ne pèsent rien.
     */
    private static function estFinancement(string $numero): bool
    {
        foreach (['10', '14', '16', '17', '18', '461', '465', '4619'] as $prefixe) {
            if (str_starts_with($numero, $prefixe)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Section déduite d'un code SYSCOHADA porté par un poste de trésorerie.
     *
     * Ce code est le seul choix explicitement fait par un humain : quand il
     * existe, il l'emporte sur la lecture des contreparties.
     */
    public static function sectionDuCodeSyscohada(?string $code): ?string
    {
        if (!$code) {
            return null;
        }

        if (str_starts_with($code, 'INV_')) {
            return self::INVESTISSEMENT;
        }

        if (str_starts_with($code, 'FIN_')) {
            return self::FINANCEMENT;
        }

        if (str_starts_with($code, 'OP_') || str_starts_with($code, 'EXP_')) {
            return self::OPERATIONNELLE;
        }

        return null;
    }
}
