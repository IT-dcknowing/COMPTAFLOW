<?php

namespace App\Services;

use App\Models\EcritureComptable;
use Illuminate\Support\Carbon;

/**
 * Numérotation des saisies comptables.
 *
 * Deux numéros coexistent sur chaque ligne d'écriture :
 *   - le numéro global attribué par le système  : ECR-180926-000001
 *   - le numéro de l'utilisateur qui a saisi    : CPT-AG-180926-000001
 *
 * Dans les deux cas : un préfixe, la date du jour (JJMMAA), puis une séquence
 * qui repart à 1 chaque jour. Les deux numérotations sont propres à une
 * entreprise : deux dossiers n'empiètent jamais l'un sur l'autre.
 *
 * Les anciens numéros (ECR_000000000020, CPT-AG_000000000012) ne sont pas
 * réécrits : ils restent tels quels, seule la génération des nouveaux change.
 */
class NumerotationSaisie
{
    /** Longueur de la partie séquentielle. */
    private const LONGUEUR_SEQUENCE = 6;

    /** Les deux seules colonnes numérotées. */
    private const COLONNES = ['n_saisie', 'n_saisie_user'];

    /**
     * Numéros déjà attribués pendant le traitement en cours.
     *
     * Un import accumule ses lignes et ne les écrit en base que par paquets de
     * mille : la base ne connaît donc pas encore les numéros distribués depuis
     * le début du paquet. Sans cette réservation, toutes les pièces du paquet
     * reçoivent le même numéro — c'est ce qui a figé un import entier sur
     * ECR_000000000020.
     */
    private static array $reserves = [];

    /**
     * Numéro global d'une nouvelle pièce.
     *
     * $exerciceId n'entre pas dans le calcul : la date étant dans le numéro,
     * la séquence est déjà propre à une journée. Le paramètre est conservé
     * parce que les appelants le passent, et parce qu'un numéro unique sur
     * tout le dossier vaut mieux qu'un numéro unique par exercice.
     */
    public static function global(int $companyId, $exerciceId = null, $date = null): string
    {
        return self::attribuer('ECR-', 'n_saisie', $companyId, $date);
    }

    /**
     * Numéro de saisie affiché à l'utilisateur, bâti sur ses initiales.
     */
    public static function utilisateur(int $companyId, $user, $date = null): string
    {
        return self::attribuer('CPT-' . self::initiales($user) . '-', 'n_saisie_user', $companyId, $date);
    }

    /**
     * Préfixe d'un utilisateur, sans la date ni la séquence.
     * Sert aux écrans qui affichent « vos saisies commencent par … ».
     */
    public static function prefixeUtilisateur($user): string
    {
        return 'CPT-' . self::initiales($user) . '-';
    }

    /**
     * Oublie les numéros réservés. À appeler entre deux traitements
     * indépendants au sein d'un même processus (tests, jobs en file).
     */
    public static function oublierReservations(): void
    {
        self::$reserves = [];
    }

    /**
     * Initiales exploitables dans un numéro : lettres et chiffres seulement.
     */
    private static function initiales($user): string
    {
        $brut = is_string($user) ? $user : ($user->initiales ?? '');
        $propres = preg_replace('/[^A-Z0-9]/', '', strtoupper((string) $brut));

        return $propres !== '' ? substr($propres, 0, 6) : 'XX';
    }

    private static function attribuer(string $prefixe, string $colonne, int $companyId, $date): string
    {
        $jour = $date ? Carbon::parse($date) : Carbon::now();
        $racine = $prefixe . $jour->format('dmy') . '-';

        $sequence = self::derniereSequence($racine, $colonne, $companyId) + 1;

        do {
            $numero = $racine . str_pad((string) $sequence, self::LONGUEUR_SEQUENCE, '0', STR_PAD_LEFT);
            $cle = $companyId . '|' . $colonne . '|' . $numero;

            $pris = isset(self::$reserves[$cle])
                || EcritureComptable::where('company_id', $companyId)->where($colonne, $numero)->exists();

            $sequence++;
        } while ($pris);

        self::$reserves[$cle] = true;

        return $numero;
    }

    /**
     * Plus grande séquence déjà utilisée ce jour-là, en base comme parmi les
     * numéros réservés depuis le début du traitement.
     */
    private static function derniereSequence(string $racine, string $colonne, int $companyId): int
    {
        if (!in_array($colonne, self::COLONNES, true)) {
            throw new \InvalidArgumentException("Colonne de numérotation inconnue : $colonne");
        }

        // La séquence est cadrée à largeur fixe derrière une racine commune :
        // le plus grand numéro au sens alphabétique est donc aussi le plus
        // grand au sens numérique. Pas de SUBSTRING en SQL, et donc aucune
        // dépendance au moteur de base de données.
        $plusGrand = EcritureComptable::where('company_id', $companyId)
            ->where($colonne, 'like', addcslashes($racine, '%_\\') . '%')
            ->max($colonne);

        $dernier = $plusGrand ? (int) substr($plusGrand, strlen($racine)) : 0;

        $prefixeReserve = $companyId . '|' . $colonne . '|' . $racine;
        foreach (array_keys(self::$reserves) as $cle) {
            if (str_starts_with($cle, $prefixeReserve)) {
                $dernier = max($dernier, (int) substr($cle, strlen($prefixeReserve)));
            }
        }

        return $dernier;
    }
}
