<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\PlanComptable;
use App\Models\EcritureComptable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use App\Traits\ManagesCompany;
use Yajra\DataTables\Facades\DataTables;

class PlanComptableController extends Controller
{
    use ManagesCompany;
  public function index()
{
    try {
        $user = Auth::user();
        
        // 1. Récupérer l'ID de la société active (gestion du switch admin)
        $companyId = session('current_company_id', $user->company_id);

        // 2. Récupérer les statistiques pour les KPI
        $totalComptes = PlanComptable::where('company_id', $companyId)->count();
        $comptesManuels = PlanComptable::where('company_id', $companyId)
            ->where('adding_strategy', 'manuel')
            ->count();
        $comptesSysteme = PlanComptable::where('company_id', $companyId)
            ->where('adding_strategy', 'auto')
            ->count();
        $comptesImportes = PlanComptable::where('company_id', $companyId)
            ->where('adding_strategy', 'imported')
            ->count();

        // 3. Récupérer TOUS les plans de cette société (auto + manuel)
        $query = PlanComptable::where('company_id', $companyId);

        $plansComptables = PlanComptable::where('company_id', $companyId)
            ->orderByRaw("LPAD(numero_de_compte, 20, '0')")
            ->get(['id', 'numero_de_compte', 'intitule', 'adding_strategy', 'classe', 'created_at', 'user_id', 'company_id', 'numero_original']);

        // 3. CALCUL DES STATISTIQUES RÉELLES
        // Nombre total
        $totalPlans = $plansComptables->count();
        
        // Nombre de plans créés MANUELLEMENT (votre indicateur vert)
        $plansByUser = $plansComptables->where('adding_strategy', 'manuel')->count();
        
        // Nombre de plans créés AUTOMATIQUEMENT (Système)
        $plansSys = $plansComptables->where('adding_strategy', 'auto')->count();

        // Nombre de plans IMPORTÉS
        $plansImported = $plansComptables->where('adding_strategy', 'imported')->count();
        
        $hasAutoStrategy = $plansSys > 0;

        return view('plan_comptable', [
            'plansComptables' => $plansComptables,
            'totalPlans' => $totalPlans,
            'plansByUser' => $plansByUser,
            'plansSys' => $plansSys,
            'plansImported' => $plansImported,
            'hasAutoStrategy' => $hasAutoStrategy,
            'isDefaultView' => true,
            // Variables pour les KPI
            'totalComptes' => $totalComptes,
            'comptesManuels' => $comptesManuels,
            'comptesSysteme' => $comptesSysteme,
            'comptesImportes' => $comptesImportes
        ]);

    } catch (\Exception $e) {
        return redirect()->back()->with('error', 'Erreur : ' . $e->getMessage());
    }
}

    /**
     * Détermine le type de compte en fonction du numéro
     */
    private function determinerTypeCompte($numero) {
        $premierChiffre = substr($numero, 0, 1);
        
        switch($premierChiffre) {
            case '1':
            case '2':
            case '3':
            case '4':
                return 'actif';
            case '5':
            case '6':
                return 'passif';
            case '7':
                return 'produit';
            case '8':
                return 'charge';
            default:
                return 'divers';
        }
    }

    /**
     * Détermine la classe du compte en fonction du numéro
     */
    private function determinerClasse($numero) {
        $premierChiffre = substr($numero, 0, 1);
        
        switch($premierChiffre) {
            case '1':
                return 1; // Classe 1: Comptes de capitaux
            case '2':
                return 2; // Classe 2: Comptes d'immobilisations
            case '3':
                return 3; // Classe 3: Comptes de stocks
            case '4':
                return 4; // Classe 4: Comptes de tiers
            case '5':
                return 5; // Classe 5: Comptes financiers
            case '6':
                return 6; // Classe 6: Comptes de charges
            case '7':
                return 7; // Classe 7: Comptes de produits
            case '8':
                return 8; // Classe 8: Comptes spéciaux
            default:
                return 1; // Autres - par défaut classe 1
        }
    }

    /**
     * Le numero saisi est-il libre ?
     *
     * La verification comparait le numero brut aux numeros enregistres, qui
     * sont completes a la longueur du dossier : « 605300 » ne ressemblait donc
     * jamais a « 60530000 », et un numero deja pris etait annonce libre. On
     * applique ici exactement le meme formatage que l'enregistrement.
     */
    public function verifierNumeroCompte(Request $request)
    {
        try {
            $user = Auth::user();
            $companyId = session('current_company_id', $user->company_id);
            $digits = (int) (\App\Models\Company::find($companyId)?->account_digits ?? 8);

            $saisi = trim((string) $request->numero_de_compte);
            $formate = $this->formaterNumeroCompte($saisi, $digits);

            $existant = $formate === null ? null : PlanComptable::where('company_id', $companyId)
                ->where('numero_de_compte', $formate)
                ->first(['id', 'numero_de_compte', 'intitule']);

            return response()->json([
                'exists' => (bool) $existant,
                'numero_formatte' => $formate ?? $saisi,
                'numero_saisi' => $saisi,
                'intitule_existant' => $existant?->intitule,
                'longueur' => $digits,
                'trop_long' => $formate === null,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Erreur lors de la vérification du numéro de compte : ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Complete un numero a la longueur du dossier, ou rend null s'il deborde.
     *
     * Les sous-comptes SYSCOHADA se lisent de gauche a droite : « 6053 » dans
     * un dossier a huit chiffres designe « 60530000 ». Le complement se pose
     * donc a droite.
     */
    private function formaterNumeroCompte(string $numero, int $digits): ?string
    {
        if ($numero === '' || strlen($numero) > $digits) {
            return null;
        }

        return str_pad($numero, $digits, '0', STR_PAD_RIGHT);
    }

  public function store(Request $request)
{
    try {
        $request->validate([
            'numero_de_compte' => 'required',
            'intitule' => 'required',
        ]);

        // 1. RÉCUPÉRER L'ID DE LA SOCIÉTÉ EN SESSION (Switch)
        $companyId = session('current_company_id', Auth::user()->company_id);
        $company = \App\Models\Company::find($companyId);
        $digits = $company->account_digits ?? 8;

        // Un numero trop long etait coupe en silence : « 4011760001 » devenait
        // « 40117600 », et le compte cherche ensuite par l'utilisateur restait
        // introuvable. On refuse, en disant pourquoi.
        $numero_formate = $this->formaterNumeroCompte(trim((string) $request->numero_de_compte), $digits);

        if ($numero_formate === null) {
            $message = "Le numero de compte de ce dossier fait $digits chiffres : "
                . trim((string) $request->numero_de_compte) . ' est trop long.';

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'error' => $message], 422);
            }

            return redirect()->back()->with('error', $message);
        }

        $intitule_formate = ucfirst(strtolower($request->intitule));

        // 2. Vérifier l'existence au sein de CETTE société uniquement
        // « Ce numero ou cet intitule existe deja » laissait chercher lequel
        // des deux : on nomme le compte en cause.
        $memeNumero = PlanComptable::where('company_id', $companyId)
            ->where('numero_de_compte', $numero_formate)->first(['numero_de_compte', 'intitule']);
        $memeIntitule = $memeNumero ? null : PlanComptable::where('company_id', $companyId)
            ->where('intitule', $intitule_formate)->first(['numero_de_compte', 'intitule']);

        if ($memeNumero || $memeIntitule) {
            $pris = $memeNumero ?: $memeIntitule;
            $message = $memeNumero
                ? "Le numero $numero_formate est deja pris par « {$pris->intitule} »."
                : "L'intitule « $intitule_formate » est deja porte par le compte {$pris->numero_de_compte}.";

            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['success' => false, 'error' => $message], 422);
            }
            return redirect()->back()->with('error', $message);
        }

        $user = Auth::user();
        
        // 3. ENREGISTRER AVEC LE BON company_id
        $plan = PlanComptable::create([
            'numero_de_compte' => $numero_formate,
            'intitule' => $intitule_formate,
            'adding_strategy' => 'manuel',
            'user_id' => $user->id,
            'company_id' => $companyId, // Utilise l'ID switché
            'classe' => $this->determinerClasse($numero_formate), // Utilise la classe au lieu du type
        ]);

        if ($request->expectsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Plan comptable ajouté avec succès à la comptabilité actuelle.',
                'id' => $plan->id,
                'numero_de_compte' => $plan->numero_de_compte,
                'intitule' => $plan->intitule
            ]);
        }

        return redirect()->back()->with('success', 'Plan comptable ajouté avec succès à la comptabilité actuelle.');
    } catch (\Exception $e) {
        return redirect()->back()->with('error', 'Erreur lors de l\'ajout : ' . $e->getMessage());
    }
}

    public function useDefault(Request $request)
    {
        if ($request->input('use_default') === 'true') {
            try {
                $user = Auth::user();

                $jsonPath = storage_path('app/sous_compte.json');

                if (!File::exists($jsonPath)) {
                    return redirect()->back()->with('error', 'Fichier de plan comptable introuvable.');
                }

                $data = json_decode(File::get($jsonPath), true);

                if (!is_array($data)) {
                    return redirect()->back()->with('error', 'Format du fichier JSON invalide.');
                }

                $companyId = session('current_company_id', $user->company_id);
                $company = \App\Models\Company::find($companyId);
                $digits = $company->account_digits ?? 8;

                foreach ($data as $numero => $intitule) {
                    if (strlen($numero) < $digits) {
                        $numero_formate = str_pad($numero, $digits, '0', STR_PAD_RIGHT);
                    } else {
                        $numero_formate = substr($numero, 0, $digits);
                    }

                    $existe = PlanComptable::where('numero_de_compte', $numero_formate)
                        ->where('company_id', $companyId)
                        ->exists();

                    if (!$existe) {
                        PlanComptable::create([
                            'numero_de_compte' => $numero_formate,
                            'intitule' => $intitule,
                            'classe' => $this->determinerClasse($numero_formate),
                            'adding_strategy' => 'auto',
                            'user_id' => $user->id,
                            'company_id' => $companyId,
                        ]);
                    }
                }

                return redirect()->back()->with('success', 'Plan comptable par défaut chargé avec succès.');
            } catch (\Exception $e) {
                return redirect()->back()->with('error', 'Erreur lors du chargement du plan par défaut : ' . $e->getMessage());
            }
        }

        return redirect()->back()->with('error', 'Action non autorisée.');
    }

    public function update(Request $request, $id)
    {

        if (Auth::check() && Auth::user()->role !== 'admin') {
        // Renvoie une erreur 403 (Accès interdit)
        abort(403, 'Seul un administrateur est autorisé à modifier le plan comptable.');
    }
        try {
            $request->validate([
                'numero_de_compte' => 'required|string',
                'intitule' => 'required|string',
                'classe' => 'nullable|string',
                'poste' => 'nullable|string',
                'extrait_du_compte' => 'nullable|in:oui,non',
                'traitement_analytique' => 'nullable|in:oui,non',
            ]);

            $user = Auth::user();
            $companyId = session('current_company_id', $user->company_id);
            $company = \App\Models\Company::find($companyId);
            $digits = $company->account_digits ?? 8;
            $plan = PlanComptable::where('company_id', $companyId)->findOrFail($id);

            $numero = $request->input('numero_de_compte');
            if (strlen($numero) < $digits) {
                $numero = str_pad($numero, $digits, "0", STR_PAD_RIGHT);
            } elseif (strlen($numero) > $digits) {
                $numero = substr($numero, 0, $digits);
            }

            $intitule_formate = ucfirst(strtolower($request->intitule));

            $plan->update([
                'numero_de_compte' => $numero,
                'intitule' => $intitule_formate,
                'classe' => $request->classe,
                'poste' => $request->poste,
                'extrait_du_compte' => $request->extrait_du_compte === 'oui',
                'traitement_analytique' => $request->traitement_analytique === 'oui',
            ]);

            return redirect()->back()->with('success', 'Plan comptable mis à jour avec succès.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Erreur lors de la mise à jour du plan comptable : ' . $e->getMessage());
        }
    }

    public function destroy($id)
    {
        // dd($id);
        try {
            $user = Auth::user();
            $companyId = session('current_company_id', $user->company_id);
            $plan = PlanComptable::where('company_id', $companyId)->findOrFail($id);

            $utilise = EcritureComptable::where('plan_comptable_id', $id)->exists();

            if ($utilise) {
                return redirect()->back()->with('error', 'Impossible de supprimer ce compte car il contient des écritures. Veuillez supprimer toutes les écritures associées avant de tenter la suppression.');
            }

            $plan->delete();

            return redirect()->back()->with('success', 'Le plan comptable a été supprimé avec succès.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Erreur lors de la suppression du plan comptable : ' . $e->getMessage());
        }
    }

    /**
     * Retourne les données pour DataTables
     */
    public function datatable()
    {
        $user = Auth::user();
        $companyId = session('current_company_id', $user->company_id);
        
        $query = PlanComptable::where('company_id', $companyId)
            ->select([
                'id',
                'numero_de_compte',
                'intitule',
                // 'classe',
                'created_at',
                'adding_strategy',
                'numero_original'
            ]);
        
        // Gestion du filtrage
        $filterType = request()->get('filter_type');
        if ($filterType === 'user') {
            $query->where('adding_strategy', 'manuel');
        } elseif ($filterType === 'system') {
            $query->where('adding_strategy', 'auto');
        }
            
        return DataTables::of($query)
            ->addColumn('actions', function($plan) {
                return view('components.actions-plan-comptable', compact('plan'))->render();
            })
            ->editColumn('created_at', function($plan) {
                return $plan->created_at ? $plan->created_at->format('Y-m-d H:i:s') : null;
            })
            ->rawColumns(['actions'])
            ->make(true);
    }
}
