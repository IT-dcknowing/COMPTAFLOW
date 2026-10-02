<?php

namespace App\Services;

use App\Models\CodeJournal;
use App\Models\EcritureComptable;
use App\Models\ExerciceComptable;
use App\Models\JournalSaisi;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Recopier des écritures d'un journal vers un autre, et vers d'autres mois.
 *
 * Beaucoup d'écritures reviennent à l'identique d'un mois sur l'autre : loyer,
 * salaires, abonnements, dotations. Les ressaisir douze fois est long et, surtout,
 * c'est douze occasions de se tromper.
 *
 * Ce que la copie garde, et ce qu'elle change — c'est tout le sujet :
 *
 *   GARDE : le compte, le tiers, le libellé, la référence, les montants, le
 *           statut, le poste de trésorerie, le justificatif. Rien n'est
 *           réinterprété. Ce qui doit différer se corrige ensuite à la main.
 *
 *   CHANGE : le journal et le mois, puisque c'est ce qu'on a choisi ; le jour
 *           du mois est conservé. Et les NUMÉROS DE SAISIE, qui sont neufs.
 *
 * Le numéro de saisie est le point délicat. Il désigne la PIÈCE : toutes les
 * lignes qui le partagent forment une seule opération, et c'est sur lui que se
 * construit le tableau des flux de trésorerie. Recopier un numéro ferait de la
 * copie et de l'originale une même pièce, à deux dates et dans deux journaux —
 * le TFT y lirait des contreparties qui n'existent pas.
 *
 * Donc : une pièce d'origine donne une pièce nouvelle, par mois de destination.
 * Les lignes choisies d'une même pièce restent ensemble, et c'est ce qui permet
 * à la copie de rester équilibrée.
 *
 * Deux refus, jamais contournés :
 *
 *   - un mois de destination hors de tout exercice, ou dans un exercice clos :
 *     l'écriture y serait invisible, ou rouvrirait des comptes arrêtés ;
 *   - un report à nouveau : il naît de la clôture, il ne se recopie pas.
 *
 * Et une prudence : une ligne déjà présente à l'identique dans la destination
 * est signalée et passée. Un second clic ne doit pas doubler les livres.
 */
class CopieDesEcritures
{
    /**
     * Les écritures qu'on peut recopier, pour un journal et un mois donnés.
     *
     * Rendues groupées par pièce : on choisit des lignes, mais c'est la pièce
     * qui fait sens, et la voir entière évite d'en copier la moitié sans le
     * savoir.
     *
     * @return Collection<string, Collection<int, EcritureComptable>>
     */
    public static function source(int $companyId, int $journalId, string $mois): Collection
    {
        [$debut, $fin] = self::bornesDuMois($mois);

        return EcritureComptable::with(['planComptable', 'planTiers', 'codeJournal'])
            ->where('company_id', $companyId)
            ->where('code_journal_id', $journalId)
            ->whereBetween('date', [$debut->toDateString(), $fin->toDateString()])
            // Un report à nouveau naît de la clôture : le recopier ferait naître
            // une ouverture de comptes qui n'a pas eu lieu.
            ->where(fn ($q) => $q->whereNull('is_ran')->orWhere('is_ran', false))
            ->orderBy('date')
            ->orderBy('n_saisie')
            ->orderBy('id')
            ->get()
            ->groupBy(fn ($e) => (string) ($e->n_saisie ?: 'sans-numero-' . $e->id));
    }

    /**
     * Les mois qu'on peut viser, avec l'exercice qui les accueille.
     *
     * On ne propose pas un calendrier libre : un mois hors exercice n'accueille
     * rien, et le découvrir après coup coûte cher.
     *
     * @return array<int, array{valeur: string, libelle: string, exercice: string, clos: bool}>
     */
    public static function moisDisponibles(int $companyId): array
    {
        $exercices = ExerciceComptable::where('company_id', $companyId)
            ->orderBy('date_debut')
            ->get();

        $mois = [];

        foreach ($exercices as $exercice) {
            if (!$exercice->date_debut || !$exercice->date_fin) {
                continue;
            }

            $curseur = Carbon::parse($exercice->date_debut)->startOfMonth();
            $borne = Carbon::parse($exercice->date_fin)->startOfMonth();

            while ($curseur->lessThanOrEqualTo($borne)) {
                $mois[$curseur->format('Y-m')] = [
                    'valeur' => $curseur->format('Y-m'),
                    'libelle' => self::NOMS_DE_MOIS[(int) $curseur->format('n')] . ' ' . $curseur->format('Y'),
                    'exercice' => (string) ($exercice->intitule ?: ('Exercice #' . $exercice->id)),
                    'clos' => (bool) $exercice->cloturer,
                ];

                $curseur->addMonth();
            }
        }

        return array_values($mois);
    }

    /**
     * Ce que la copie produirait, sans rien écrire.
     *
     * @param  array<int, int>  $ids       lignes choisies
     * @param  array<int, string>  $moisCibles  « 2026-03 », …
     * @return array{lignes: int, pieces: int, creations: int, refus: array<int, string>,
     *               deja: int, desequilibrees: array<int, string>, detail: array<int, array>}
     */
    public static function apercu(int $companyId, array $ids, int $journalCibleId, array $moisCibles): array
    {
        $lignes = self::lignesChoisies($companyId, $ids);

        $resultat = [
            'lignes' => $lignes->count(),
            'pieces' => 0,
            'creations' => 0,
            'refus' => [],
            'deja' => 0,
            'desequilibrees' => [],
            'detail' => [],
        ];

        if ($lignes->isEmpty()) {
            $resultat['refus'][] = "Aucune ligne choisie : cochez celles à recopier.";

            return $resultat;
        }

        $journal = CodeJournal::where('company_id', $companyId)->find($journalCibleId);

        if (!$journal) {
            $resultat['refus'][] = "Le journal de destination n'appartient pas à cette comptabilité.";

            return $resultat;
        }

        $pieces = $lignes->groupBy(fn ($e) => (string) ($e->n_saisie ?: 'sans-numero-' . $e->id));
        $resultat['pieces'] = $pieces->count();

        // Une pièce dont on n'a coché qu'une partie arrive déséquilibrée. On ne
        // refuse pas — la personne sait peut-être ce qu'elle fait — mais on le
        // dit avant, pas après.
        foreach ($pieces as $numero => $duLot) {
            $ecart = round($duLot->sum('debit') - $duLot->sum('credit'), 2);

            if (abs($ecart) >= 0.01) {
                $resultat['desequilibrees'][] = sprintf('%s (écart %s)',
                    $numero, number_format($ecart, 0, ',', ' '));
            }
        }

        if ($moisCibles === []) {
            $resultat['refus'][] = "Aucun mois de destination : choisissez-en au moins un.";

            return $resultat;
        }

        foreach ($moisCibles as $mois) {
            $exercice = self::exercicePour($companyId, $mois);

            if (!$exercice) {
                $resultat['refus'][] = self::libelleDuMois($mois)
                    . " ne tombe dans aucun exercice comptable : l'écriture y serait invisible.";
                continue;
            }

            if ($exercice->cloturer) {
                $resultat['refus'][] = self::libelleDuMois($mois) . " appartient à l'exercice « "
                    . ($exercice->intitule ?: ('#' . $exercice->id)) . " », qui est clos.";
                continue;
            }

            $aCreer = 0;
            $deja = 0;

            foreach ($lignes as $ligne) {
                self::existeDeja($companyId, $ligne, $journal->id, self::dateCible($ligne->date, $mois))
                    ? $deja++
                    : $aCreer++;
            }

            $resultat['creations'] += $aCreer;
            $resultat['deja'] += $deja;

            $resultat['detail'][] = [
                'mois' => self::libelleDuMois($mois),
                'exercice' => (string) ($exercice->intitule ?: ('#' . $exercice->id)),
                'a_creer' => $aCreer,
                'deja' => $deja,
            ];
        }

        return $resultat;
    }

    /**
     * Recopie. Renvoie le même décompte que l'aperçu, une fois fait.
     *
     * @param  array<int, int>  $ids
     * @param  array<int, string>  $moisCibles
     * @return array{creees: int, passees: int, pieces: int, refus: array<int, string>}
     */
    public static function copier(
        int $companyId,
        array $ids,
        int $journalCibleId,
        array $moisCibles,
        ?int $userId = null,
        $utilisateur = null
    ): array {
        $apercu = self::apercu($companyId, $ids, $journalCibleId, $moisCibles);

        $issue = ['creees' => 0, 'passees' => 0, 'pieces' => 0, 'refus' => $apercu['refus']];

        if ($apercu['lignes'] === 0 || $apercu['creations'] === 0) {
            return $issue;
        }

        $lignes = self::lignesChoisies($companyId, $ids);
        $journal = CodeJournal::where('company_id', $companyId)->findOrFail($journalCibleId);
        $pieces = $lignes->groupBy(fn ($e) => (string) ($e->n_saisie ?: 'sans-numero-' . $e->id));

        DB::transaction(function () use (
            $companyId, $moisCibles, $pieces, $journal, $userId, $utilisateur, &$issue
        ) {
            foreach ($moisCibles as $mois) {
                $exercice = self::exercicePour($companyId, $mois);

                if (!$exercice || $exercice->cloturer) {
                    continue;
                }

                foreach ($pieces as $duLot) {
                    // La pièce de destination porte un numéro neuf, partagé par
                    // toutes ses lignes : c'est lui qui en fait une opération.
                    //
                    // Il se prend exactement comme celui d'une écriture saisie
                    // à la main : le prochain disponible du jour où l'on copie.
                    // Le numéro porte la date de SAISIE, jamais la date
                    // comptable — la dater du mois d'arrivée aurait fabriqué une
                    // seconde convention pour les seules copies.
                    $nSaisie = NumerotationSaisie::global($companyId, $exercice->id);
                    $nSaisieUser = $utilisateur
                        ? NumerotationSaisie::utilisateur($companyId, $utilisateur)
                        : $nSaisie;

                    $posees = 0;

                    foreach ($duLot as $ligne) {
                        $date = self::dateCible($ligne->date, $mois);

                        if (self::existeDeja($companyId, $ligne, $journal->id, $date)) {
                            $issue['passees']++;
                            continue;
                        }

                        $journalSaisi = JournalSaisi::firstOrCreate([
                            'annee' => (int) $date->format('Y'),
                            'mois' => (int) $date->format('n'),
                            'exercices_comptables_id' => $exercice->id,
                            'code_journals_id' => $journal->id,
                            'company_id' => $companyId,
                        ], ['user_id' => $userId]);

                        EcritureComptable::create([
                            'company_id' => $companyId,
                            'user_id' => $userId,
                            'n_saisie' => $nSaisie,
                            'n_saisie_user' => $nSaisieUser,
                            'code_journal_id' => $journal->id,
                            'exercices_comptables_id' => $exercice->id,
                            'journaux_saisis_id' => $journalSaisi->id,
                            'date' => $date->toDateString(),

                            // Recopié tel quel : c'est ce qu'on attend d'une copie.
                            'description_operation' => $ligne->description_operation,
                            'reference_piece' => $ligne->reference_piece,
                            'plan_comptable_id' => $ligne->plan_comptable_id,
                            'plan_tiers_id' => $ligne->plan_tiers_id,
                            'plan_analytique' => $ligne->plan_analytique,
                            'debit' => $ligne->debit,
                            'credit' => $ligne->credit,
                            'type_flux' => $ligne->type_flux,
                            'compte_tresorerie_id' => $ligne->compte_tresorerie_id,
                            'poste_tresorerie_id' => $ligne->poste_tresorerie_id,
                            'piece_justificatif' => $ligne->piece_justificatif,
                            'statut' => $ligne->statut,

                            // Jamais recopiés : le report à nouveau naît de la
                            // clôture, et la clé Selflow désigne UNE écriture —
                            // la dupliquer ferait passer la copie pour l'originale
                            // au prochain déversement.
                            'is_ran' => false,
                            'cle_selflow' => null,
                        ]);

                        $issue['creees']++;
                        $posees++;
                    }

                    if ($posees > 0) {
                        $issue['pieces']++;
                    }
                }
            }
        });

        return $issue;
    }

    /** Les lignes choisies, limitées à la comptabilité ouverte. */
    private static function lignesChoisies(int $companyId, array $ids): Collection
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));

        if ($ids === []) {
            return collect();
        }

        return EcritureComptable::where('company_id', $companyId)
            ->whereIn('id', $ids)
            ->where(fn ($q) => $q->whereNull('is_ran')->orWhere('is_ran', false))
            ->orderBy('date')
            ->orderBy('n_saisie')
            ->orderBy('id')
            ->get();
    }

    /**
     * La même ligne est-elle déjà dans la destination ?
     *
     * Un second clic, un retour arrière du navigateur, et les livres doublent.
     * On compare ce qui fait l'identité d'une ligne pour un lecteur : le jour,
     * le journal, le compte, les montants et le libellé.
     */
    private static function existeDeja(int $companyId, EcritureComptable $ligne, int $journalId, Carbon $date): bool
    {
        return EcritureComptable::where('company_id', $companyId)
            ->where('code_journal_id', $journalId)
            ->where('date', $date->toDateString())
            ->where('plan_comptable_id', $ligne->plan_comptable_id)
            ->where('debit', $ligne->debit)
            ->where('credit', $ligne->credit)
            ->where('description_operation', $ligne->description_operation)
            ->exists();
    }

    /**
     * La date d'arrivée : même jour du mois, mois choisi.
     *
     * Un 31 recopié en février n'existe pas : il se pose au dernier jour, plutôt
     * que de glisser en mars sans prévenir.
     */
    private static function dateCible($dateSource, string $mois): Carbon
    {
        $source = Carbon::parse($dateSource);
        $cible = Carbon::createFromFormat('Y-m-d', $mois . '-01')->startOfMonth();

        return $cible->copy()->day(min((int) $source->format('j'), (int) $cible->daysInMonth));
    }

    /** L'exercice qui accueille ce mois, s'il en est un. */
    private static function exercicePour(int $companyId, string $mois): ?ExerciceComptable
    {
        [$debut, $fin] = self::bornesDuMois($mois);

        return ExerciceComptable::where('company_id', $companyId)
            ->whereDate('date_debut', '<=', $fin->toDateString())
            ->whereDate('date_fin', '>=', $debut->toDateString())
            ->orderBy('cloturer')
            ->first();
    }

    /** @return array{0: Carbon, 1: Carbon} */
    private static function bornesDuMois(string $mois): array
    {
        $debut = Carbon::createFromFormat('Y-m-d', $mois . '-01')->startOfMonth();

        return [$debut, $debut->copy()->endOfMonth()];
    }

    public static function libelleDuMois(string $mois): string
    {
        $date = Carbon::createFromFormat('Y-m-d', $mois . '-01');

        return self::NOMS_DE_MOIS[(int) $date->format('n')] . ' ' . $date->format('Y');
    }

    public const NOMS_DE_MOIS = [
        1 => 'janvier', 2 => 'février', 3 => 'mars', 4 => 'avril',
        5 => 'mai', 6 => 'juin', 7 => 'juillet', 8 => 'août',
        9 => 'septembre', 10 => 'octobre', 11 => 'novembre', 12 => 'décembre',
    ];
}
