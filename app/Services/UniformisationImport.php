<?php

namespace App\Services;

use App\Models\CodeJournal;
use App\Models\Company;
use App\Models\PlanComptable;
use App\Models\PlanTiers;

/**
 * La machine d'uniformisation des imports, mise en commun.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * Ce qu'elle fait
 * ─────────────────────────────────────────────────────────────────────────
 *
 * Tout ce qui entre dans un dossier Comptaflow — par un fichier chargé à
 * l'écran d'importation comme par le déversement de Selflow — doit ressortir
 * **à la convention du dossier** : longueur des numéros de compte, longueur
 * des codes journaux, longueur et forme des numéros de tiers. Et le numéro
 * d'origine reste rangé **sous** le nouveau, dans `numero_original` : c'est
 * lui qui sert d'index quand les écritures arrivent ensuite en désignant les
 * comptes tels que la source les nomme.
 *
 * ─────────────────────────────────────────────────────────────────────────
 * Pourquoi cette classe existe
 * ─────────────────────────────────────────────────────────────────────────
 *
 * La règle était écrite **quatre fois** : `MasterPlanImport`,
 * `MasterTiersImport`, `MasterJournalImport` et `ImportCommitJob` portaient
 * chacun sa copie — et elles avaient déjà divergé. `MasterJournalImport`
 * complétait un code journal trop court avec des zéros ; `ImportCommitJob` se
 * contentait de tronquer le trop long. Un journal `OD` devenait donc `OD00` à
 * l'import du référentiel, et restait `OD` à l'import des écritures, qui ne le
 * retrouvait plus que par son numéro d'origine.
 *
 * Le déversement de Selflow, lui, n'en avait aucune copie : il rangeait les
 * numéros **tels quels**. Un dossier réglé sur huit chiffres recevait des
 * comptes à six. Rien ne cassait tout de suite, parce que les écritures de
 * Selflow désignent les mêmes numéros et les retrouvaient. Cela cassait au
 * premier compte créé à la main ou au premier fichier importé : Comptaflow
 * appliquait alors **sa** convention et produisait `41110000` à côté de
 * `411100` — deux plans dans un même dossier, et un grand livre coupé en deux.
 *
 * C'est la décision du propriétaire : **un déversement est un import.** Il
 * passe par la même machine, il obéit à la même configuration, et Selflow ne
 * change rien chez lui.
 *
 * Les quatre copies d'origine n'ont pas encore été rebranchées ici : elles
 * servent l'écran d'importation, qui marche, et les toucher sortait du lot. Le
 * seul écart qu'elles portent — le journal que `ImportCommitJob` ne complète
 * pas — se rattrape déjà par `numero_original`.
 */
final class UniformisationImport
{
    /** Les valeurs par défaut de Comptaflow, quand le dossier ne dit rien. */
    public const CHIFFRES_COMPTE = 8;
    public const CARACTERES_JOURNAL = 4;
    public const CARACTERES_TIERS = 8;

    /**
     * La catégorie d'un tiers, déduite des deux premiers chiffres de son
     * numéro d'origine — la table qu'affiche l'écran d'importation.
     */
    public const CATEGORIES_TIERS = [
        '40' => 'Fournisseur',
        '41' => 'Client',
        '42' => 'Personnel',
        '43' => 'Organisme sociaux / CNPS',
        '44' => 'Impôt',
        '45' => 'Organisme international',
        '46' => 'Associé',
        '47' => 'Divers Tiers',
    ];

    // ─── Les conventions du dossier ──────────────────────────────────

    public static function chiffresDeCompte(?Company $company): int
    {
        return (int) ($company?->account_digits ?: self::CHIFFRES_COMPTE);
    }

    public static function caracteresDeJournal(?Company $company): int
    {
        return (int) ($company?->journal_code_digits ?: self::CARACTERES_JOURNAL);
    }

    public static function caracteresDeTiers(?Company $company): int
    {
        return (int) ($company?->tier_digits ?: self::CARACTERES_TIERS);
    }

    // ─── L'uniformisation proprement dite ────────────────────────────

    /**
     * Un numéro de compte à la longueur du dossier.
     *
     * Le complément se fait **à droite** : `4111` sur huit chiffres donne
     * `41110000`, et non `00004111`. C'est la règle du plan comptable — les
     * chiffres de gauche portent la classe et le sous-compte, ceux de droite
     * détaillent. Compléter à gauche déplacerait le compte de classe.
     */
    public static function numeroDeCompte(string $numero, int $chiffres): string
    {
        $numero = preg_replace('/[^0-9]/', '', trim($numero));

        if ($numero === '' || $numero === null) {
            return '';
        }

        if (strlen($numero) < $chiffres) {
            return str_pad($numero, $chiffres, '0', STR_PAD_RIGHT);
        }

        return substr($numero, 0, $chiffres);
    }

    /**
     * Un code journal à la longueur du dossier.
     *
     * Complété à droite lui aussi, et par des zéros : `OD` sur quatre
     * caractères donne `OD00`. C'est ce que fait déjà l'import du modèle des
     * journaux, et c'est la moitié qu'il fallait retenir des deux — tronquer
     * sans compléter laissait les codes courts hors convention.
     */
    public static function codeJournal(string $code, int $caracteres): string
    {
        $code = strtoupper(trim($code));

        if ($code === '' || $code === 'AUTO') {
            return '';
        }

        if (strlen($code) < $caracteres) {
            return str_pad($code, $caracteres, '0', STR_PAD_RIGHT);
        }

        return substr($code, 0, $caracteres);
    }

    /**
     * Le préfixe de génération d'un numéro de tiers, et sa catégorie.
     *
     * @return array{0: string, 1: string|null} le préfixe, la catégorie
     */
    public static function prefixeDeTiers(string $numeroImporte, ?string $type = null): array
    {
        $numeroImporte = strtoupper(trim($numeroImporte));

        if (str_starts_with($numeroImporte, '4')) {
            $prefixe = substr($numeroImporte, 0, 2);

            return [$prefixe, self::CATEGORIES_TIERS[$prefixe] ?? $type];
        }

        // Sans numéro exploitable, le type annoncé tranche — c'est tout ce
        // que Selflow transmet pour un tiers créé hors des séries 40 et 41.
        $type = strtolower(trim((string) $type));

        if (str_contains($type, 'fourn')) {
            return ['40', 'Fournisseur'];
        }

        if (str_contains($type, 'client')) {
            return ['41', 'Client'];
        }

        return ['40', null];
    }

    /**
     * Le numéro de tiers suivant, à la convention du dossier.
     *
     * Reprend la génération de `ExternalSyncController::generateNextTierNumber`
     * et celle de l'écran d'importation, qui disaient la même chose deux fois.
     */
    public static function numeroDeTiers(Company $company, string $prefixe, string $intitule): string
    {
        $caracteres = self::caracteresDeTiers($company);

        if (($company->tier_id_type ?? 'numeric') === 'numeric') {
            $base = $prefixe;
        } else {
            $lettres = strtoupper(preg_replace(
                '/[^a-zA-Z]/', '',
                (string) iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $intitule)
            ));
            $base = $prefixe . (substr($lettres, 0, 3) ?: 'XXX');
        }

        $place = max(0, $caracteres - strlen($base));

        if ($place === 0) {
            return substr($base, 0, $caracteres);
        }

        $suite = 0;

        foreach (PlanTiers::where('company_id', $company->id)
            ->where('numero_de_tiers', 'like', $base . '%')
            ->pluck('numero_de_tiers') as $numero) {
            $reste = substr($numero, strlen($base));

            if (is_numeric($reste)) {
                $suite = max($suite, (int) $reste);
            }
        }

        $numero = $base . str_pad((string) ($suite + 1), $place, '0', STR_PAD_LEFT);

        return strlen($numero) > $caracteres ? substr($numero, 0, $caracteres) : $numero;
    }

    // ─── La fixation : retrouver par l'ancien ce qui est rangé sous le nouveau

    /**
     * Le compte désigné par un numéro de source, s'il est déjà chez nous.
     *
     * **Les deux clés sont interrogées, dans cet ordre** : le numéro
     * uniformisé, puis le numéro d'origine. C'est le principe de fixation : ce
     * que Selflow appelle `411100` est rangé sous `41110000`, et seul
     * `numero_original` fait le lien. Ne chercher que sur la première clé
     * créait un second compte à chaque écriture.
     */
    public static function compte(Company $company, string $brut): ?PlanComptable
    {
        $brut = trim($brut);

        if ($brut === '') {
            return null;
        }

        $uniforme = self::numeroDeCompte($brut, self::chiffresDeCompte($company));

        return self::premierTrouve(PlanComptable::class, $company, [
            ['numero_de_compte', $uniforme],
            ['numero_original', $brut],
            ['numero_de_compte', $brut],
        ]);
    }

    public static function journal(Company $company, string $brut): ?CodeJournal
    {
        $brut = strtoupper(trim($brut));

        if ($brut === '') {
            return null;
        }

        $uniforme = self::codeJournal($brut, self::caracteresDeJournal($company));

        return self::premierTrouve(CodeJournal::class, $company, [
            ['code_journal', $uniforme],
            ['numero_original', $brut],
            ['code_journal', $brut],
        ]);
    }

    public static function tiers(Company $company, string $brut): ?PlanTiers
    {
        $brut = strtoupper(trim($brut));

        if ($brut === '') {
            return null;
        }

        // Le numéro de tiers n'est pas dérivable : Comptaflow le régénère. Les
        // deux clés utiles sont donc le numéro d'origine et, pour les dossiers
        // d'avant cette règle, le numéro tel quel.
        return self::premierTrouve(PlanTiers::class, $company, [
            ['numero_original', $brut],
            ['numero_de_tiers', $brut],
        ]);
    }

    /**
     * La première ligne trouvée, **clé après clé et dans l'ordre donné**.
     *
     * Deux raisons de ne pas tout mettre dans un seul `OR` :
     *
     * - **les dossiers liés avant cette règle.** Le déversement y rangeait les
     *   numéros bruts, sans numéro d'origine : `VTE` dans un dossier à quatre
     *   caractères, `411000` dans un dossier à huit chiffres. Chercher sur le
     *   seul numéro uniformisé et sur `numero_original` ne les retrouvait plus
     *   — chaque écriture tombait en « journal inconnu », et chaque compte se
     *   créait une seconde fois à côté du premier. La dernière clé, le numéro
     *   brut dans la colonne principale, les rattrape ;
     * - **l'ordre départage.** Un `OR` rend la ligne que la base veut bien
     *   rendre. Si `VTE0` et `VTE` coexistent, c'est celui qui est à la
     *   convention du dossier qui doit gagner.
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
