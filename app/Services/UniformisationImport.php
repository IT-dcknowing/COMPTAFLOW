<?php

namespace App\Services;

use App\Http\Controllers\Admin\AdminConfigController;
use App\Models\CodeJournal;
use App\Models\Company;
use App\Models\PlanComptable;
use App\Models\PlanTiers;

/**
 * Retrouver, dans un dossier, la ligne qu'un numéro de Selflow désigne.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * Ce que cette classe ne fait plus
 * ─────────────────────────────────────────────────────────────────────────
 *
 * Elle portait sa propre copie de la normalisation : un code journal trop
 * court y était complété par des zéros, `VTE` devenait `VTE0`. **Ce n'est pas
 * la règle de Comptaflow**, qui donne `VTE1` et `OD01`, et génère une séquence
 * quand le code est déjà pris. Deux règles pour la même chose donnaient deux
 * codes pour le même journal, selon qu'il arrivait par l'écran d'importation
 * ou par Selflow.
 *
 * Le référentiel de Selflow passe désormais par l'import lui-même
 * (`ImportCommitJob`). Ce qui reste ici ne normalise rien : les deux fonctions
 * de normalisation sont celles de `AdminConfigController`, appelées telles
 * quelles.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * Ce qu'elle fait
 * ─────────────────────────────────────────────────────────────────────────
 *
 * Les écritures de Selflow désignent les comptes, journaux et tiers **à la
 * manière de Selflow**. L'import a rangé le numéro de Selflow dans
 * `numero_original`, sous celui que Comptaflow a donné : c'est par lui qu'on
 * retrouve la ligne.
 */
final class UniformisationImport
{
    /** Les valeurs par défaut de Comptaflow, quand le dossier ne dit rien. */
    public const CHIFFRES_COMPTE = 8;
    public const CARACTERES_JOURNAL = 4;

    public static function chiffresDeCompte(?Company $company): int
    {
        return (int) ($company?->account_digits ?: self::CHIFFRES_COMPTE);
    }

    public static function caracteresDeJournal(?Company $company): int
    {
        return (int) ($company?->journal_code_digits ?: self::CARACTERES_JOURNAL);
    }

    /** La normalisation de l'écran d'importation, sans copie. */
    public static function numeroDeCompte(string $numero, int $chiffres): string
    {
        return (string) app(AdminConfigController::class)->standardizeAccountNumber($numero, $chiffres);
    }

    /** La normalisation de l'écran d'importation, sans copie : `VTE` donne `VTE1`. */
    public static function codeJournal(string $code, int $caracteres): string
    {
        return (string) app(AdminConfigController::class)->standardizeJournalCode($code, $caracteres);
    }

    /**
     * Le compte désigné par un numéro de Selflow.
     *
     * Le numéro à la convention du dossier d'abord — c'est ce que fait
     * l'import des écritures —, puis le numéro d'origine, puis le numéro brut
     * pour les dossiers liés avant que le déversement ne passe par l'import.
     */
    public static function compte(Company $company, string $brut): ?PlanComptable
    {
        $brut = trim($brut);

        if ($brut === '') {
            return null;
        }

        return self::premierTrouve(PlanComptable::class, $company, [
            ['numero_de_compte', self::numeroDeCompte($brut, self::chiffresDeCompte($company))],
            ['numero_original', $brut],
            ['numero_de_compte', $brut],
        ]);
    }

    /**
     * Le journal désigné par un code de Selflow.
     *
     * **Le code d'origine d'abord**, et non le code normalisé : si le
     * comptable a déjà un `VTE1`, l'import range le `VTE` de Selflow en `VTE2`.
     * Chercher `VTE1` en premier rattacherait les ventes de Selflow au journal
     * du comptable.
     */
    public static function journal(Company $company, string $brut): ?CodeJournal
    {
        $brut = strtoupper(trim($brut));

        if ($brut === '') {
            return null;
        }

        return self::premierTrouve(CodeJournal::class, $company, [
            ['numero_original', $brut],
            ['code_journal', $brut],
            ['code_journal', self::codeJournal($brut, self::caracteresDeJournal($company))],
        ]);
    }

    /** Le tiers désigné par un numéro de Selflow : l'import régénère le sien. */
    public static function tiers(Company $company, string $brut): ?PlanTiers
    {
        $brut = strtoupper(trim($brut));

        if ($brut === '') {
            return null;
        }

        return self::premierTrouve(PlanTiers::class, $company, [
            ['numero_original', $brut],
            ['numero_de_tiers', $brut],
        ]);
    }

    /**
     * La première ligne trouvée, **clé après clé et dans l'ordre donné**.
     *
     * Un seul `OR` rend la ligne que la base veut bien rendre : si deux lignes
     * répondent, c'est l'ordre des clés qui doit trancher, pas le hasard.
     *
     * @param class-string $modele
     * @param array<int, array{0: string, 1: string}> $cles
     */
    private static function premierTrouve(string $modele, Company $company, array $cles)
    {
        foreach ($cles as [$colonne, $valeur]) {
            if ($valeur === '') {
                continue;
            }

            $trouve = $modele::where('company_id', $company->id)
                ->where($colonne, $valeur)
                ->first();

            if ($trouve) {
                return $trouve;
            }
        }

        return null;
    }
}
