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
     * Plus grande séquence attribuée pendant le traitement en cours, par
     * racine (« 1|n_saisie|ECR-180926- »).
     *
     * Un import accumule ses lignes et ne les écrit en base que par paquets de
     * mille : la base ne connaît donc pas encore les numéros distribués depuis
     * le début du paquet. Sans ce suivi, toutes les pièces du paquet reçoivent
     * le même numéro — c'est ce qui a figé un import entier sur
     * ECR_000000000020.
     *
     * On retient une séquence par racine, et non la liste des numéros : une
     * réparation qui renumérote des dizaines de milliers de pièces relisait
     * sinon toute la liste à chaque attribution.
     */
    private static array $dernieres = [];

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
        self::$dernieres = [];
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

        $cle = $companyId . '|' . $colonne . '|' . $racine;
        $sequence = self::derniereSequence($cle, $racine, $colonne, $companyId) + 1;

        do {
            $numero = $racine . str_pad((string) $sequence, self::LONGUEUR_SEQUENCE, '0', STR_PAD_LEFT);

            // La séquence en mémoire tient déjà compte de ce qui est en base ;
            // la vérification n'a de sens que sur le premier numéro de la
            // racine, au cas où un autre traitement aurait écrit entre-temps.
            $pris = !isset(self::$dernieres[$cle])
                && EcritureComptable::where('company_id', $companyId)->where($colonne, $numero)->exists();

            if ($pris) {
                $sequence++;
            }
        } while ($pris);

        self::$dernieres[$cle] = $sequence;

        return $numero;
    }

    /**
     * Plus grande séquence déjà utilisée ce jour-là, en base comme parmi les
     * numéros réservés depuis le début du traitement.
     */
    private static function derniereSequence(string $cle, string $racine, string $colonne, int $companyId): int
    {
        // Déjà servi cette racine dans ce traitement : la base n'a rien à
        // apprendre de plus, et la relire à chaque pièce rendrait une
        // réparation de plusieurs dizaines de milliers de pièces interminable.
        if (isset(self::$dernieres[$cle])) {
            return self::$dernieres[$cle];
        }

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

        return $plusGrand ? (int) substr($plusGrand, strlen($racine)) : 0;
    }
}
