<?php

namespace App\Support;

/**
 * L'adresse de la pièce justificative d'une écriture, une seule règle pour
 * tous les écrans (24/11/2026).
 *
 * Les pièces déversées par Selflow sont rangées hors du dossier public
 * (`private/justificatifs/<dossier>/…`) et ne se servent que par la route
 * `ecriture.justificatif`, qui vérifie le dossier de la session. La liste des
 * écritures construisait pourtant `asset('justificatifs/' . $chemin)` : une
 * adresse publique qui n'existe pas, d'où le « 404 NOT FOUND ».
 */
final class PieceJustificative
{
    public static function url(object $ecriture): ?string
    {
        $chemin = (string) ($ecriture->piece_justificatif ?? '');
        if ($chemin === '') {
            return null;
        }

        if (str_starts_with($chemin, 'private/justificatifs/') && !empty($ecriture->id)) {
            return route('ecriture.justificatif', $ecriture->id);
        }

        if (str_starts_with($chemin, 'http://') || str_starts_with($chemin, 'https://')) {
            return $chemin;
        }

        // Les pièces saisies à la main : `justificatifs/x.pdf` ou `x.pdf`.
        return asset(str_starts_with($chemin, 'justificatifs/') ? $chemin : 'justificatifs/' . $chemin);
    }
}
