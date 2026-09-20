<?php

namespace App\Database\Eloquent;

use App\Services\SuppressionTracee;
use Illuminate\Database\Eloquent\Builder;

/**
 * Constructeur de requêtes qui refuse de supprimer sans laisser de trace.
 *
 * `Modele::where(...)->delete()` part directement en SQL : aucun événement
 * Eloquent ne se déclenche, donc ni l'archive des suppressions ni le journal
 * d'audit n'en savent rien. C'est ainsi que des écritures ont disparu sans
 * qu'on puisse dire qui les avait supprimées, ni quoi.
 *
 * Tous les modèles qui utilisent le trait LogsActivity passent désormais par
 * ici : une suppression de masse est redirigée vers SuppressionTracee, qui
 * supprime ligne à ligne et archive chacune d'elles. Le comportement d'appel
 * ne change pas — on récupère toujours le nombre de lignes supprimées.
 *
 * La suppression d'un modèle déjà chargé (`$compte->delete()`) n'est pas
 * détournée : elle passe par ses propres événements, qui archivent déjà. Le
 * trait lève un drapeau le temps de l'opération pour qu'on le sache ici.
 */
class ConstructeurTracant extends Builder
{
    public function delete()
    {
        if (SuppressionTracee::enCours()) {
            return parent::delete();
        }

        return SuppressionTracee::supprimer($this);
    }
}
