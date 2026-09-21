<?php

namespace App\Services;

use Illuminate\Support\Collection;

/**
 * Transforme des écritures en mouvements de trésorerie exploitables.
 *
 * Trois principes, dans cet ordre d'importance :
 *
 * 1. LE MONTANT ET LE SENS SE LISENT SUR LA LIGNE, comme dans la balance.
 *    Un débit sur un compte de classe 5 est un encaissement de ce montant ;
 *    un crédit est un décaissement. Rien n'est compensé. L'ancien calcul
 *    additionnait les lignes de trésorerie d'une pièce et ne gardait que la
 *    différence : un numéro de saisie partagé par cent pièces faisait donc
 *    disparaître cent encaissements et cent décaissements au profit d'un seul
 *    solde. Présenter les flux bruts est aussi ce qu'attend le SYSCOHADA.
 *
 * 2. LA PIÈCE NE SERT PLUS QU'À DÉSIGNER LA CONTREPARTIE, donc la section et
 *    le libellé. Et elle est reconstituée sur l'équilibre (DecoupageDesPieces),
 *    pas lue sur le numéro de saisie : un numéro partagé ne peut plus tout
 *    ranger sous la nature de la première pièce venue.
 *
 * 3. LES VIREMENTS INTERNES NE SONT PAS DES FLUX. Un transfert caisse vers
 *    banque n'est ni un encaissement ni un décaissement. L'ancien calcul les
 *    neutralisait par accident, puisqu'il ne gardait que le solde. En brut, il
 *    faut les reconnaître explicitement, sans quoi chaque transfert gonflerait
 *    les deux colonnes à la fois.
 */
class AnalyseFluxTresorerie
{
    /** Tolérance d'arrondi, en unités monétaires. */
    private const TOLERANCE = 0.01;

    /**
     * Décompose des écritures en mouvements de trésorerie.
     *
     * Les écritures doivent porter la relation planComptable ; posteTresorerie
     * est utilisée si elle est chargée.
     *
     * @param  Collection  $ecritures  lignes d'un exercice, report à nouveau exclu
     * @return Collection<int, object>  date, section, sens, montant,
     *                                  compte et libelle de la contrepartie
     */
    public static function mouvements(Collection $ecritures): Collection
    {
        $mouvements = collect();

        foreach ($ecritures->groupBy('n_saisie') as $lignes) {
            foreach (DecoupageDesPieces::decouper($lignes) as $piece) {
                foreach (self::mouvementsDeLaPiece($piece) as $mouvement) {
                    $mouvements->push($mouvement);
                }
            }
        }

        return $mouvements;
    }

    /**
     * @return array<int, object>
     */
    private static function mouvementsDeLaPiece(Collection $piece): array
    {
        $tresorerie = $piece->filter(fn ($l) => ClassificationFlux::estTresorerie(self::numero($l)));

        if ($tresorerie->isEmpty()) {
            return [];
        }

        $contreparties = $piece->reject(fn ($l) => ClassificationFlux::estTresorerie(self::numero($l)))
            ->reject(fn ($l) => ClassificationFlux::estNonMonetaire(self::numero($l)));

        $bruts = self::horsVirementsInternes($tresorerie);

        if ($bruts === []) {
            return [];
        }

        $mouvements = [];

        foreach ($bruts as $brut) {
            foreach (self::repartir($brut, $contreparties) as $mouvement) {
                $mouvements[] = $mouvement;
            }
        }

        return $mouvements;
    }

    /**
     * Montants réellement entrés ou sortis, transferts internes déduits.
     *
     * Chaque compte de trésorerie est d'abord soldé à l'intérieur de la pièce :
     * une caisse qui reçoit puis rend dans la même opération n'a bougé que du
     * net. La part qui se retrouve à la fois en entrée sur un compte et en
     * sortie sur un autre est un virement de fonds : elle est retirée des deux
     * côtés. Ce qui dépasse est le flux réel.
     *
     * @return array<int, object{ligne: object, sens: string, montant: float}>
     */
    private static function horsVirementsInternes(Collection $tresorerie): array
    {
        $nets = [];

        foreach ($tresorerie as $ligne) {
            $cle = $ligne->plan_comptable_id ?? self::numero($ligne);
            $nets[$cle] ??= ['ligne' => $ligne, 'solde' => 0.0];
            $nets[$cle]['solde'] += (float) $ligne->debit - (float) $ligne->credit;
        }

        $entrees = array_filter($nets, fn ($n) => $n['solde'] > self::TOLERANCE);
        $sorties = array_filter($nets, fn ($n) => $n['solde'] < -self::TOLERANCE);

        $totalEntrees = array_sum(array_column($entrees, 'solde'));
        $totalSorties = -array_sum(array_column($sorties, 'solde'));

        // Un virement suppose deux comptes de trésorerie distincts. Sur un
        // compte unique, il n'y a rien à transférer : le net est le flux.
        $interne = ($entrees && $sorties) ? min($totalEntrees, $totalSorties) : 0.0;

        $bruts = [];

        foreach ([['encaissements', $entrees, $totalEntrees], ['decaissements', $sorties, $totalSorties]] as [$sens, $cotes, $total]) {
            $reste = $total - $interne;

            if ($reste < self::TOLERANCE || $total <= 0) {
                continue;
            }

            foreach ($cotes as $net) {
                $part = abs($net['solde']) / $total * $reste;

                if ($part >= self::TOLERANCE) {
                    $bruts[] = (object) ['ligne' => $net['ligne'], 'sens' => $sens, 'montant' => $part];
                }
            }
        }

        return $bruts;
    }

    /**
     * Ventile un montant de trésorerie sur les contreparties de la pièce.
     *
     * Une pièce peut payer à la fois un fournisseur et une machine : chaque
     * part part alors dans sa propre section, au lieu que l'opération entière
     * bascule en investissement comme le faisait l'ancien calcul.
     *
     * Un poste de trésorerie explicitement rattaché à une ligne SYSCOHADA est
     * un choix humain : il l'emporte sur la lecture des contreparties.
     *
     * @return array<int, object>
     */
    private static function repartir(object $brut, Collection $contreparties): array
    {
        $ligne = $brut->ligne;
        $imposee = ClassificationFlux::sectionDuCodeSyscohada(
            optional($ligne->posteTresorerie ?? null)->syscohada_line_id
        );

        $poids = $contreparties
            ->map(fn ($c) => (object) [
                'compte' => $c->planComptable,
                'montant' => abs((float) $c->debit - (float) $c->credit),
            ])
            ->filter(fn ($c) => $c->compte && $c->montant >= self::TOLERANCE);

        $total = $poids->sum('montant');

        // Aucune contrepartie exploitable : le flux existe quand même, il est
        // rattaché au compte de trésorerie lui-même plutôt que perdu.
        if ($total < self::TOLERANCE) {
            $compte = $ligne->planComptable ?? null;

            return [(object) [
                'date' => $ligne->date,
                'section' => $imposee ?? ClassificationFlux::OPERATIONNELLE,
                'sens' => $brut->sens,
                'montant' => $brut->montant,
                'compte' => $compte->numero_de_compte ?? '',
                'libelle' => trim(($compte->numero_de_compte ?? '') . ' - ' . ($compte->intitule ?? ''))
                    . ' (sans contrepartie)',
            ]];
        }

        $mouvements = [];

        foreach ($poids as $contrepartie) {
            $mouvements[] = (object) [
                'date' => $ligne->date,
                'section' => $imposee ?? ClassificationFlux::section($contrepartie->compte->numero_de_compte),
                'sens' => $brut->sens,
                'montant' => $contrepartie->montant / $total * $brut->montant,
                'compte' => $contrepartie->compte->numero_de_compte,
                'libelle' => $contrepartie->compte->numero_de_compte . ' - ' . $contrepartie->compte->intitule,
            ];
        }

        return $mouvements;
    }

    private static function numero(object $ligne): ?string
    {
        return optional($ligne->planComptable ?? null)->numero_de_compte;
    }
}
