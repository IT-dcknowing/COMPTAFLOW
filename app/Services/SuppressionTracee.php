<?php

namespace App\Services;

use App\Models\ArchivedRecord;
use Illuminate\Database\Eloquent\Builder;

/**
 * Suppression en masse qui laisse une trace.
 *
 * `Modele::where(...)->delete()` s'exécute directement en SQL : les événements
 * Eloquent ne se déclenchent pas, donc ni l'archive des suppressions ni le
 * journal d'audit n'en savent rien. Des écritures ont ainsi disparu sans
 * laisser la moindre trace, alors que l'application affiche une archive de
 * trente jours censée tout retenir.
 *
 * Ce service supprime les mêmes lignes une par une, à travers le modèle, de
 * sorte que le trait LogsActivity archive chaque ligne et inscrive la
 * suppression au journal. Toutes les lignes d'un même appel partagent un
 * identifiant de lot : l'archive les présente comme une seule suppression
 * groupée, et non comme cent suppressions isolées.
 */
class SuppressionTracee
{
    /** Nombre de lignes chargées à la fois. */
    private const PAQUET = 500;

    /**
     * Profondeur des suppressions de modèle en cours.
     *
     * Quand un modèle déjà chargé se supprime, Eloquent finit par appeler
     * `delete()` sur un constructeur de requêtes : sans ce compteur, le
     * constructeur tracé y verrait une suppression de masse et rechargerait la
     * ligne pour la supprimer une seconde fois — elle serait archivée deux fois.
     */
    private static int $profondeur = 0;

    public static function entrer(): void
    {
        self::$profondeur++;
    }

    public static function sortir(): void
    {
        self::$profondeur = max(0, self::$profondeur - 1);
    }

    public static function enCours(): bool
    {
        return self::$profondeur > 0;
    }

    /**
     * Supprime tout ce que la requête désigne, en archivant chaque ligne.
     *
     * @param  Builder  $requete  requête Eloquent (et non une requête brute)
     * @return int  nombre de lignes supprimées
     */
    public static function supprimer(Builder $requete, ?string $lot = null): int
    {
        $total = (clone $requete)->count();

        if ($total === 0) {
            return 0;
        }

        $lot ??= ArchivedRecord::nouveauLot();
        $cle = $requete->getModel()->getKeyName();
        $table = $requete->getModel()->getTable();

        $lotPrecedent = app()->bound('archive.batch_id') ? app('archive.batch_id') : null;
        $taillePrecedente = app()->bound('archive.batch_size') ? app('archive.batch_size') : null;

        app()->instance('archive.batch_id', $lot);
        app()->instance('archive.batch_size', $total);

        $supprimes = 0;
        $dernier = 0;

        try {
            // Parcours par clé croissante : chaque tour repart après la
            // dernière ligne traitée, la boucle ne peut donc pas s'emballer
            // même si une suppression échouait.
            while (true) {
                $lignes = (clone $requete)
                    ->where("$table.$cle", '>', $dernier)
                    ->orderBy("$table.$cle")
                    ->limit(self::PAQUET)
                    ->get();

                if ($lignes->isEmpty()) {
                    break;
                }

                $dernier = $lignes->last()->getKey();

                foreach ($lignes as $ligne) {
                    $ligne->delete();
                    $supprimes++;
                }
            }
        } finally {
            // On rend au conteneur l'état qu'il avait : une suppression
            // imbriquée ne doit pas voler le lot de celle qui l'englobe.
            $lotPrecedent === null
                ? app()->forgetInstance('archive.batch_id')
                : app()->instance('archive.batch_id', $lotPrecedent);

            $taillePrecedente === null
                ? app()->forgetInstance('archive.batch_size')
                : app()->instance('archive.batch_size', $taillePrecedente);
        }

        return $supprimes;
    }
}
