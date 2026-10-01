<?php

namespace App\Http\Controllers\Super;

use App\Http\Controllers\Controller;
use App\Models\ArchivedRecord;
use App\Models\Company;
use App\Services\RestaurationDArchive;
use Illuminate\Http\Request;

/**
 * L'archive des suppressions, vue de haut.
 *
 * L'écran d'entreprise ne montre que les archives du dossier ouvert. C'est
 * suffisant au quotidien, mais laisse un trou : quand une ENTREPRISE est
 * supprimée, ses archives restent en base sous son identifiant — et plus
 * personne ne peut ouvrir ce dossier pour les consulter. Ce qu'on annonçait
 * récupérable trente jours devenait donc inatteignable.
 *
 * Cet écran lit toutes les archives, dossiers supprimés compris.
 */
class SuperAdminArchiveController extends Controller
{
    public function index(Request $request)
    {
        // La conservation s'arrête à trente jours : ce qui a dépassé part ici
        // comme il part sur l'écran d'entreprise.
        ArchivedRecord::where('expires_at', '<=', now())->delete();

        $query = ArchivedRecord::with('user')->enConservation()->orderByDesc('deleted_at');

        if ($request->filled('company_id')) {
            $query->where('company_id', $request->integer('company_id'));
        }

        if ($request->filled('module')) {
            $query->where('model_type', 'App\\Models\\' . $request->module);
        }

        if ($request->filled('search')) {
            $query->where('label', 'like', '%' . $request->input('search') . '%');
        }

        // Les dossiers disparus sont le cas qui justifie cet écran : on peut
        // ne montrer qu'eux.
        // La corbeille compte : un dossier qui y attend est recuperable, et le
        // nommer vaut mieux que l'annoncer « supprime ».
        $vivantes = Company::pluck('company_name', 'id');
        $enCorbeille = Company::onlyTrashed()->pluck('company_name', 'id');

        if ($request->boolean('orphelines')) {
            // Les deux conditions tiennent ensemble : sans parentheses, le
            // « ou » s'echappait et annulait tous les autres filtres, scope de
            // conservation compris.
            $query->where(fn ($q) => $q
                ->whereNotIn('company_id', $vivantes->keys())
                ->orWhereNull('company_id'));
        }

        $perPage = in_array((int) $request->input('per_page'), [25, 50, 100], true)
            ? (int) $request->input('per_page') : 25;

        $archives = $query->paginate($perPage)->withQueryString();

        $modules = ArchivedRecord::enConservation()->distinct()->pluck('model_type')
            ->filter()->map(fn ($type) => class_basename($type))->unique()->sort()->values();

        // Le décompte par dossier, dossiers supprimés compris.
        $parDossier = ArchivedRecord::enConservation()
            ->selectRaw('company_id, COUNT(*) as lignes')
            ->groupBy('company_id')
            ->orderByDesc('lignes')
            ->get()
            ->map(fn ($l) => [
                'company_id' => $l->company_id,
                'nom' => $vivantes[$l->company_id] ?? $enCorbeille[$l->company_id] ?? null,
                'supprimee' => !isset($vivantes[$l->company_id]),
                'en_corbeille' => isset($enCorbeille[$l->company_id]),
                'lignes' => $l->lignes,
            ]);

        $stats = [
            'total' => ArchivedRecord::enConservation()->count(),
            'lots' => ArchivedRecord::enConservation()->whereNotNull('batch_id')->distinct()->count('batch_id'),
            'expire_7j' => ArchivedRecord::enConservation()->where('expires_at', '<=', now()->addDays(7))->count(),
            'orphelines' => ArchivedRecord::enConservation()->whereNotIn('company_id', $vivantes->keys())->count(),
            'remises' => ArchivedRecord::enConservation()->remises()->count(),
            'retention' => ArchivedRecord::RETENTION_JOURS,
        ];

        return view('superadmin.archives', compact(
            'archives', 'modules', 'parDossier', 'stats', 'perPage', 'vivantes', 'enCorbeille'
        ));
    }

    /**
     * Ce que donnerait la remise en place, sans rien écrire.
     */
    public function apercuRestauration($id)
    {
        $archive = ArchivedRecord::findOrFail($id);
        $apercu = RestaurationDArchive::apercu($archive);

        // Un lot vient rarement seul : dire combien de lignes l'accompagnent
        // évite de les remettre une par une.
        $duLot = $archive->batch_id
            ? ArchivedRecord::where('batch_id', $archive->batch_id)->whereNull('restored_at')->count()
            : 0;

        return response()->json($apercu + [
            'lot' => $archive->batch_id,
            'lignes_du_lot' => $duLot,
            'supprime_le' => optional($archive->deleted_at)->format('d/m/Y H:i'),
        ]);
    }

    /**
     * Remet une ligne en place.
     */
    public function restaurer($id)
    {
        $archive = ArchivedRecord::findOrFail($id);
        $issue = RestaurationDArchive::restaurer($archive);

        return back()->with($issue['fait'] ? 'success' : 'error', $issue['raison']);
    }

    /**
     * Remet en place tout un lot : la suppression groupée, telle qu'elle a eu lieu.
     */
    public function restaurerLeLot(Request $request)
    {
        $donnees = $request->validate([
            'batch_id' => ['required', 'string', 'max:64'],
        ]);

        $resultat = RestaurationDArchive::restaurerLeLot($donnees['batch_id']);

        if ($resultat['remises'] === 0 && $resultat['refusees'] === 0) {
            return back()->with('error', 'Ce lot ne contient plus rien à remettre.');
        }

        $message = $resultat['remises'] . ' ligne(s) remise(s) en place.';

        if ($resultat['refusees'] > 0) {
            $message .= ' ' . $resultat['refusees'] . ' refusée(s) : '
                . collect($resultat['raisons'])
                    ->map(fn ($n, $raison) => $raison . ' (' . $n . ')')
                    ->implode(' ');
        }

        return back()->with($resultat['remises'] > 0 ? 'success' : 'error', $message);
    }

    /**
     * Le contenu complet d'une donnée supprimée.
     */
    public function show($id)
    {
        $archive = ArchivedRecord::with('user')->findOrFail($id);

        return response()->json([
            'id' => $archive->id,
            'libelle' => $archive->label,
            'modele' => class_basename($archive->model_type),
            'entreprise' => Company::find($archive->company_id)?->company_name
                ?? ('Dossier supprimé (#' . $archive->company_id . ')'),
            'supprime_le' => optional($archive->deleted_at)->format('d/m/Y H:i'),
            'expire_le' => optional($archive->expires_at)->format('d/m/Y'),
            'par' => trim(($archive->user->name ?? '') . ' ' . ($archive->user->last_name ?? '')) ?: 'Inconnu',
            'lot' => $archive->batch_id,
            'contenu' => $archive->data,
        ]);
    }
}
