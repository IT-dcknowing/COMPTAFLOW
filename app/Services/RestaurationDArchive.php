<?php

namespace App\Services;

use App\Models\ArchivedRecord;
use App\Models\Company;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Remettre en place ce qui a été supprimé.
 *
 * L'archive garde trente jours le contenu complet de chaque ligne supprimée.
 * On l'annonçait « récupérable », mais rien ne la récupérait : l'écran montrait
 * le contenu et s'arrêtait là. Il fallait donc le ressaisir à la main, ce qui
 * n'est pas récupérer.
 *
 * Remettre une ligne, c'est la réécrire telle quelle, avec son identifiant
 * d'origine — sinon tout ce qui la désignait pointerait dans le vide. Quatre
 * raisons de refuser, et on les dit plutôt que de deviner :
 *
 *   - la ligne est déjà là (restauration rejouée, ou suppression annulée) ;
 *   - sa comptabilité n'existe plus : il faut la remettre d'abord, sinon la
 *     ligne reviendrait dans un dossier fantôme ;
 *   - l'archive ne dit pas sous quel identifiant la rendre ;
 *   - la classe du modèle n'existe plus dans l'application.
 *
 * Une entreprise encore en corbeille se relève au lieu de se réécrire : ses
 * écritures ne sont jamais parties avec elle, et les réécrire les doublerait.
 */
class RestaurationDArchive
{
    /**
     * Ce que donnerait la restauration, sans rien écrire.
     *
     * @return array{possible: bool, raison: string, modele: string, libelle: string}
     */
    public static function apercu(ArchivedRecord $archive): array
    {
        $reponse = [
            'possible' => false,
            'raison' => '',
            'modele' => class_basename($archive->model_type),
            'libelle' => (string) $archive->label,
        ];

        if ($archive->restored_at) {
            $reponse['raison'] = 'Déjà remise en place le '
                . $archive->restored_at->format('d/m/Y à H:i') . '.';

            return $reponse;
        }

        $classe = $archive->model_type;

        if (!is_string($classe) || !class_exists($classe) || !is_subclass_of($classe, Model::class)) {
            $reponse['raison'] = "Cette donnée vient d'une partie de l'application qui n'existe plus.";

            return $reponse;
        }

        $donnees = $archive->data;

        if (!is_array($donnees) || $donnees === []) {
            $reponse['raison'] = "L'archive ne contient aucun contenu à remettre.";

            return $reponse;
        }

        /** @var Model $instance */
        $instance = new $classe;
        $table = $instance->getTable();
        $cle = $instance->getKeyName();
        $identifiant = $donnees[$cle] ?? $archive->model_id;

        if (!$identifiant) {
            $reponse['raison'] = "L'archive ne dit pas quel identifiant rendre à cette ligne.";

            return $reponse;
        }

        // Une entreprise en corbeille se relève : c'est le cas le plus courant,
        // et le seul où la ligne existe encore.
        if ($classe === Company::class) {
            $enCorbeille = Company::withTrashed()->whereKey($identifiant)->first();

            if ($enCorbeille && $enCorbeille->trashed()) {
                $reponse['possible'] = true;
                $reponse['raison'] = 'La comptabilité est en corbeille : elle sera simplement relevée, '
                    . 'avec toutes ses écritures, qui ne sont jamais parties.';

                return $reponse;
            }
        }

        if (DB::table($table)->where($cle, $identifiant)->exists()) {
            $reponse['raison'] = 'Cette ligne est déjà présente : rien à remettre.';

            return $reponse;
        }

        // La comptabilité d'accueil doit exister, corbeille comprise : une
        // écriture qui revient dans un dossier disparu ne se retrouve plus.
        $companyId = $donnees['company_id'] ?? null;

        if ($companyId && $classe !== Company::class
            && !Company::withTrashed()->whereKey($companyId)->exists()) {
            $reponse['raison'] = 'Sa comptabilité (#' . $companyId . ") n'existe plus : "
                . "remettez-la d'abord, puis recommencez.";

            return $reponse;
        }

        $reponse['possible'] = true;
        $reponse['raison'] = "Sera réécrite telle quelle, sous son identifiant d'origine ("
            . $cle . ' = ' . $identifiant . ').';

        return $reponse;
    }

    /**
     * Remet une ligne en place. Renvoie ce qui s'est passé.
     *
     * @return array{fait: bool, raison: string}
     */
    public static function restaurer(ArchivedRecord $archive): array
    {
        $apercu = self::apercu($archive);

        if (!$apercu['possible']) {
            return ['fait' => false, 'raison' => $apercu['raison']];
        }

        $classe = $archive->model_type;
        /** @var Model $instance */
        $instance = new $classe;
        $table = $instance->getTable();
        $cle = $instance->getKeyName();
        $donnees = $archive->data;
        $identifiant = $donnees[$cle] ?? $archive->model_id;

        // Une entreprise en corbeille se relève.
        if ($classe === Company::class) {
            $enCorbeille = Company::withTrashed()->whereKey($identifiant)->first();

            if ($enCorbeille && $enCorbeille->trashed()) {
                $enCorbeille->restore();
                $archive->forceFill(['restored_at' => now()])->save();

                return ['fait' => true, 'raison' => 'Comptabilité relevée de la corbeille.'];
            }
        }

        // On n'écrit que les colonnes qui existent encore : une colonne ajoutée
        // ou retirée depuis la suppression ne doit pas faire échouer le retour.
        $colonnes = Schema::getColumnListing($table);
        $aEcrire = array_intersect_key($donnees, array_flip($colonnes));
        $aEcrire[$cle] = $identifiant;

        // Une ligne supprimée en douceur revient visible : la remettre avec sa
        // date de suppression la renverrait aussitôt à la corbeille.
        if (in_array(SoftDeletes::class, class_uses_recursive($classe), true)) {
            $aEcrire[$instance->getDeletedAtColumn()] = null;
        }

        // Une archive peut dater d'avant une colonne devenue obligatoire : elle
        // ne porte alors pas la valeur que la table exige. On le dit au lieu de
        // laisser remonter une erreur de base de donnees brute.
        try {
            DB::transaction(function () use ($table, $aEcrire, $archive) {
                DB::table($table)->insert($aEcrire);
                $archive->forceFill(['restored_at' => now()])->save();
            });
        } catch (QueryException $e) {
            return [
                'fait' => false,
                'raison' => "La base refuse cette ligne : son contenu ne suffit plus à ce que la table "
                    . "exige aujourd'hui (" . ($e->errorInfo[2] ?? $e->getMessage()) . ').',
            ];
        }

        return ['fait' => true, 'raison' => "Ligne remise en place sous son identifiant d'origine."];
    }

    /**
     * Remet en place tout un lot — une suppression groupée, telle qu'elle a eu
     * lieu. Les comptabilités passent d'abord : le reste y revient.
     *
     * @return array{remises: int, refusees: int, raisons: array<string, int>}
     */
    public static function restaurerLeLot(string $batchId): array
    {
        $lignes = ArchivedRecord::where('batch_id', $batchId)
            ->whereNull('restored_at')
            ->get()
            ->sortBy(fn ($a) => $a->model_type === Company::class ? 0 : 1)
            ->values();

        $resultat = ['remises' => 0, 'refusees' => 0, 'raisons' => []];

        foreach ($lignes as $ligne) {
            $issue = self::restaurer($ligne);

            if ($issue['fait']) {
                $resultat['remises']++;
                continue;
            }

            $resultat['refusees']++;
            $resultat['raisons'][$issue['raison']] = ($resultat['raisons'][$issue['raison']] ?? 0) + 1;
        }

        return $resultat;
    }
}
