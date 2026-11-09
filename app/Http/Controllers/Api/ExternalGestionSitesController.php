<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;

class ExternalGestionSitesController extends Controller
{
    private function secretValide(Request $request): bool
    {
        $attendu = config('external_sync.external_sync_secret');
        $fourni = $request->input('secret') ?? $request->header('X-Sync-Secret');

        return is_string($attendu) && $attendu !== ''
            && is_string($fourni) && $fourni !== ''
            && hash_equals($attendu, $fourni);
    }

    /**
     * Rattache un dossier COMPTAFLOW existant à une entreprise GESTION-DE-SITES.
     * La liaison ne crée aucun utilisateur et ne modifie aucun paramétrage comptable.
     */
    public function provision(Request $request)
    {
        if (!$this->secretValide($request)) {
            return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 401);
        }

        $data = $request->validate([
            'gestionsites_company_id' => ['required', 'integer', 'min:1'],
            'comptaflow_company_id' => ['required', 'integer', 'min:1'],
        ]);

        $company = Company::query()->find($data['comptaflow_company_id']);
        if (!$company) {
            return response()->json(['success' => false, 'message' => 'Dossier COMPTAFLOW introuvable.'], 404);
        }

        $dejaLiee = Company::query()
            ->where('gestionsites_company_id', $data['gestionsites_company_id'])
            ->first();

        if ($dejaLiee && (int) $dejaLiee->id !== (int) $company->id) {
            return response()->json([
                'success' => false,
                'message' => 'Cette entreprise GESTION-DE-SITES est déjà liée à un autre dossier COMPTAFLOW.',
            ], 409);
        }

        if ($company->selflow_company_id !== null) {
            return response()->json([
                'success' => false,
                'message' => 'Ce dossier COMPTAFLOW est déjà rattaché à SELFLOW. Choisissez un dossier distinct.',
            ], 409);
        }

        if ($company->gestionsites_company_id !== null
            && (int) $company->gestionsites_company_id !== (int) $data['gestionsites_company_id']) {
            return response()->json([
                'success' => false,
                'message' => 'Ce dossier COMPTAFLOW est déjà lié à une autre entreprise GESTION-DE-SITES.',
            ], 409);
        }

        $company->forceFill(['gestionsites_company_id' => $data['gestionsites_company_id']])->save();

        $cle = $company->cleGestionSitesEnClair();
        if (!$cle) {
            $cle = $company->poserUneCleGestionSites();
        }

        Log::info('Liaison GESTION-DE-SITES établie', [
            'comptaflow_company_id' => $company->id,
            'gestionsites_company_id' => $company->gestionsites_company_id,
        ]);

        return response()->json([
            'success' => true,
            'company_id' => $company->id,
            'sync_key' => $cle,
            'message' => 'Liaison GESTION-DE-SITES établie.',
        ]);
    }

    /**
     * Liste les tiers déjà créés dans le dossier COMPTAFLOW lié.
     * GESTION-DE-SITES ne crée pas de tiers comptables : il choisit parmi ceux-ci.
     */
    public function listerTiers(Request $request)
    {
        $company = $request->attributes->get('entreprise_liee');
        if (!$company || $request->attributes->get('integration_origine') !== 'GESTION_DE_SITES') {
            return response()->json(['success' => false, 'message' => 'Liaison GESTION-DE-SITES introuvable.'], 404);
        }

        $tiers = \\App\\Models\\PlanTiers::query()
            ->where('company_id', $company->id)
            ->orderBy('intitule')
            ->get(['id', 'numero_de_tiers', 'intitule', 'type_de_tiers', 'compte_general', 'ncc', 'rccm', 'email', 'telephone']);

        return response()->json(['success' => true, 'company_id' => $company->id, 'tiers' => $tiers]);
    }

    /** Vérifie une liaison existante à partir de sa clé propre. */
    public function verify(Request $request)
    {
        if (!$this->secretValide($request)) {
            return response()->json(['success' => false, 'message' => 'Accès non autorisé.'], 401);
        }

        $company = $request->attributes->get('entreprise_liee');
        if (!$company || $request->attributes->get('integration_origine') !== 'GESTION_DE_SITES') {
            return response()->json(['success' => false, 'message' => 'Liaison GESTION-DE-SITES introuvable.'], 404);
        }

        if ($request->filled('gestionsites_company_id')
            && (int) $request->input('gestionsites_company_id') !== (int) $company->gestionsites_company_id) {
            return response()->json(['success' => false, 'message' => 'Identifiant GESTION-DE-SITES incorrect.'], 403);
        }

        return response()->json([
            'success' => true,
            'company_id' => $company->id,
            'gestionsites_company_id' => $company->gestionsites_company_id,
            'nom' => $company->company_name,
            'linked_at' => $company->gestionsites_linked_at,
            'last_deposit_at' => $company->gestionsites_last_deposit_at,
        ]);
    }
}
