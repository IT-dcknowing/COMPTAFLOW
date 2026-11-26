<?php

namespace App\Http\Controllers;

use App\Models\ExerciceComptable;
use App\Models\JournalSaisi;
use App\Models\CodeJournal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Models\Company;
use App\Models\EcritureComptable;
use App\Services\AccountingReportingService;
use App\Services\ComptesDeResultat;
use App\Services\ImmobilisationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use App\Models\InternalNotification;
use App\Models\PlanComptable;
use RuntimeException;

class ExerciceComptableController extends Controller
{
    protected $reportingService;
    protected $immobilisationService;

    public function __construct(AccountingReportingService $reportingService, ImmobilisationService $immobilisationService)
    {
        $this->middleware('auth');
        $this->reportingService = $reportingService;
        $this->immobilisationService = $immobilisationService;
    }
public function index()
{
    $user = Auth::user();

    if (!$user) {
        return redirect()->route('login');
    }

    // Récupérer l'ID de la société switchée
    $companyId = session('current_company_id', $user->company_id);

    if (!$companyId) {
        return redirect()->route('login')->with('error', 'Aucune entreprise associée.');
    }

    // Vérifier les échéances
    $this->checkUpcomingDeadlines($companyId);

    $exercices = ExerciceComptable::where('company_id', $companyId)
        ->orderBy('date_debut', 'desc')
        ->get()
        ->map(function ($exercice) {
            $exercice->nb_mois = $this->calculerNbMois($exercice->date_debut, $exercice->date_fin);
            return $exercice;
        });

    // RÉCUPÉRATION DES JOURNAUX
    $code_journaux = CodeJournal::withoutGlobalScopes()
        ->where('company_id', $companyId)
        ->get();
    
    // Définir l'exercice par défaut pour le modal
    $exerciceActif = $exercices->first();

    // IMPORTANT: On passe bien les 3 variables à la vue
    return view('exercice_comptable', compact('exercices', 'code_journaux', 'exerciceActif'));
}
    public function getData(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // Utilisation de la session ici aussi pour que le tableau se mette à jour au switch
        $companyId = session('current_company_id', $user->company_id);

        if (!$companyId) {
            return response()->json(['data' => []]);
        }

        $query = ExerciceComptable::where('company_id', $companyId);

        if ($request->filled('date_debut')) {
            try {
                $dateDebut = Carbon::parse($request->input('date_debut'))->startOfDay();
                $query->whereDate('date_debut', '>=', $dateDebut);
            } catch (\Exception $e) {
                // Ignorer un format invalide
            }
        }

        if ($request->filled('date_fin')) {
            try {
                $dateFin = Carbon::parse($request->input('date_fin'))->endOfDay();
                $query->whereDate('date_fin', '<=', $dateFin);
            } catch (\Exception $e) {
                // Ignorer un format invalide
            }
        }

        $exercices = $query->orderBy('date_debut', 'desc')
            ->get()
            ->map(function ($exercice) {
                $dateDebut = Carbon::parse($exercice->date_debut);
                $dateFin = Carbon::parse($exercice->date_fin);

                return [
                    'id' => $exercice->id,
                    // Chaînes "Y-m-d" pures : pas d'horodatage UTC, donc pas de décalage
                    // d'un jour à l'affichage selon le fuseau du navigateur.
                    'date_debut' => $dateDebut->format('Y-m-d'),
                    'date_fin' => $dateFin->format('Y-m-d'),
                    'date_debut_fr' => $dateDebut->format('d/m/Y'),
                    'date_fin_fr' => $dateFin->format('d/m/Y'),
                    // Repli pour les anciens exercices enregistrés sans intitulé
                    'intitule' => $exercice->intitule ?: $this->genererIntitule($dateDebut, $dateFin),
                    'nb_mois' => $this->calculerNbMois($exercice->date_debut, $exercice->date_fin),
                    'is_active' => (bool) $exercice->is_active,
                    'nombre_journaux_saisis' => $exercice->nombre_journaux_saisis ?? 0,
                    'cloturer' => (bool) $exercice->cloturer,
                    'resultat_passe' => $this->resultatEstPasse($exercice),
                    'resultat_recalculable' => EcritureComptable::where('company_id', $exercice->company_id)
                        ->where('exercices_comptables_id', $exercice->id)->where('reference_piece', 'RES-'.$exercice->id)->exists()
                ];
            });

        return response()->json(['data' => $exercices]);
    }

    public function store(Request $request)
    {
        if (!Auth::user()->hasPermission('compta.create')) {
            return response()->json(['success' => false, 'message' => 'Action non autorisée : habilitation « Ouvrir un exercice comptable » requise.'], 403);
        }
        try {
            $user = Auth::user();
            $companyId = session('current_company_id', $user->company_id);

            $request->validate([
                'date_debut' => 'required|date',
                'date_fin' => 'required|date|after:date_debut',
            ], [
                'date_debut.required' => 'La date de début est obligatoire.',
                'date_fin.required' => 'La date de fin est obligatoire.',
                'date_fin.after' => 'La date de fin doit être postérieure à la date de début.',
            ]);

            // Dates normalisées : on ne garde que la partie calendaire (aucune heure,
            // donc aucun risque de glissement d'un jour au stockage ou à l'affichage).
            $dateDebut = Carbon::parse($request->date_debut)->startOfDay();
            $dateFin = Carbon::parse($request->date_fin)->startOfDay();

            // LOGIQUE DE RESTRICTION : On ne peut pas créer un exercice de plus que celui dans lequel nous sommes (+1 an max)
            $limitYear = Carbon::now()->year + 1;

            if ($dateDebut->year > $limitYear) {
                return response()->json([
                    'success' => false, 
                    'message' => "La logique comptable interdit de créer un exercice au-delà de l'année $limitYear."
                ], 422);
            }

            // VÉRIFICATION DES DOUBLONS
            // VÉRIFICATION DES CHEVAUCHEMENTS DE DATES
            // Logique : (DebutNEW <= FinEXISTING) ET (FinNEW >= DebutEXISTING)
            $overlap = ExerciceComptable::where('company_id', $companyId)
                ->where(function ($query) use ($dateDebut, $dateFin) {
                    $query->where('date_debut', '<=', $dateFin->format('Y-m-d'))
                          ->where('date_fin', '>=', $dateDebut->format('Y-m-d'));
                })
                ->first();

            if ($overlap) {
                return response()->json([
                    'success' => false,
                    'message' => "Impossible de créer cet exercice : Les dates chevauchent l'exercice existant \"{$overlap->intitule}\" ({$overlap->date_debut->format('d/m/Y')} au {$overlap->date_fin->format('d/m/Y')})."
                ], 422);
            }

            DB::beginTransaction();

            // Création de l'exercice
            $exercice = new ExerciceComptable();
            $exercice->date_debut = $dateDebut->format('Y-m-d');
            $exercice->date_fin = $dateFin->format('Y-m-d');

            // Génération automatique de l'intitulé (l'intitulé posté par le formulaire
            // est ignoré : seule l'année de la date de début fait foi)
            $exercice->intitule = $this->genererIntitule($dateDebut, $dateFin);
            $exercice->user_id = $user->id;
            $exercice->company_id = $companyId;
            $exercice->nombre_journaux_saisis = 0;
            $exercice->cloturer = 0;
            $exercice->is_active = 0; // Pas actif par défaut
            $exercice->save();

            if (method_exists($exercice, 'syncJournaux')) {
                $exercice->syncJournaux();
            }

            // Rattrapage des amortissements qui attendaient cet exercice
            if ($this->immobilisationService) {
                $this->immobilisationService->syncOrphanAmortissements($exercice);
            }

            DB::commit();

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Exercice « ' . $exercice->intitule . ' » créé du '
                        . $dateDebut->format('d/m/Y') . ' au ' . $dateFin->format('d/m/Y') . '.',
                    'exercice' => [
                        'id' => $exercice->id,
                        'intitule' => $exercice->intitule,
                        'date_debut' => $dateDebut->format('Y-m-d'),
                        'date_fin' => $dateFin->format('Y-m-d'),
                    ]
                ]);
            }

            return redirect()->route('exercice_comptable')->with('success', 'Exercice créé avec succès');

        } catch (ValidationException $e) {
            if ($request->ajax() || $request->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'errors' => $e->errors(),
                    'message' => 'Veuillez corriger les erreurs dans le formulaire.'
                ], 422);
            }
            throw $e;
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erreur insertion exercice : ' . $e->getMessage());

            if ($request->ajax() || $request->wantsJson()) {
                return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
            }

            return redirect()->back()->withInput()->with('error', 'Erreur lors de la création : ' . $e->getMessage());
        }
    }

    public function activate($id)
    {
        if (!Auth::user()->hasPermission('exercice_comptable')) {
            return back()->with('error', 'Action non autorisée.');
        }

        $user = Auth::user();
        $companyId = session('current_company_id', $user->company_id);

        try {
            DB::beginTransaction();

            // Désactiver tous les autres exercices de la société
            ExerciceComptable::where('company_id', $companyId)->update(['is_active' => false]);

            // Activer l'exercice sélectionné
            $exercice = ExerciceComptable::where('company_id', $companyId)->findOrFail($id);
            $exercice->is_active = true;
            $exercice->save();

            DB::commit();

            return back()->with('success', "L'exercice \"{$exercice->intitule}\" est désormais l'exercice actif PAR DÉFAUT de l'entreprise.");
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Erreur lors de l\'activation.');
        }
    }

    /**
     * Basculer sur un exercice spécifique (Contextuel par Session)
     * Si $id = 0 ou null, quitte le contexte et revient à l'exercice actif par défaut
     */
    public function switch($id)
    {
        $user = Auth::user();
        $companyId = session('current_company_id', $user->company_id);

        // Si l'utilisateur veut quitter le contexte d'exercice
        if ($id == 0 || $id == 'default') {
            session()->forget('current_exercice_id');
            
            // Récupérer l'exercice actif par défaut
            $exerciceActif = ExerciceComptable::where('company_id', $companyId)
                ->where('is_active', 1)
                ->first();
            
            $message = $exerciceActif 
                ? "Vous êtes revenu à l'exercice par défaut : {$exerciceActif->intitule}"
                : "Contexte d'exercice réinitialisé.";
            
            // Support AJAX
            if (request()->ajax() || request()->wantsJson()) {
                return response()->json([
                    'success' => true,
                    'message' => $message,
                    'exercice' => $exerciceActif
                ]);
            }
            
            return back()->with('success', $message);
        }

        // Sinon, basculer vers l'exercice sélectionné
        $exercice = ExerciceComptable::where('id', $id)
            ->where('company_id', $companyId)
            ->first();

        if (!$exercice) {
            if (request()->ajax() || request()->wantsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Exercice introuvable ou non autorisé.'
                ], 404);
            }
            return back()->with('error', 'Exercice introuvable ou non autorisé.');
        }

        // Mise à jour de la session contextuelle
        session(['current_exercice_id' => $exercice->id]);

        $message = "Vous travaillez maintenant sur l'exercice : {$exercice->intitule}";
        
        // Support AJAX
        if (request()->ajax() || request()->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
                'exercice' => $exercice
            ]);
        }

        return back()->with('success', $message);
    }


    /**
     * Passe ou recalcule le résultat système sans clôturer l'exercice.
     * Le recalcul ne supprime que les lignes explicitement marquées système.
     */
    public function passerResultat(Request $request, $id)
    {
        if (!Auth::user()->hasPermission('exercice_comptable')) {
            return back()->with('error', 'Action non autorisée : habilitation « Exercice comptable » requise.');
        }

        $user = Auth::user();
        $companyId = session('current_company_id', $user->company_id);
        $exercice = ExerciceComptable::where('company_id', $companyId)->findOrFail($id);

        if ($exercice->cloturer) {
            return back()->with('error', 'Impossible de passer le résultat : cet exercice est clôturé.');
        }
        if ($this->hasResultatManuel($exercice)) {
            return back()->with('error', 'Une écriture manuelle sur 1301/1309 existe. Vérifiez-la et régularisez-la avant le passage automatique du résultat.');
        }

        try {
            $resultat = DB::transaction(function () use ($request, $exercice, $companyId, $user) {
                $locked = ExerciceComptable::where('company_id', $companyId)
                    ->whereKey($exercice->id)->lockForUpdate()->firstOrFail();
                if ($locked->cloturer) {
                    throw new RuntimeException("L’exercice vient d’être clôturé. Le passage du résultat est annulé.");
                }
                if ($this->hasResultatManuel($locked)) {
                    throw new RuntimeException("Une écriture manuelle sur 1301/1309 est détectée. Le passage automatique est bloqué.");
                }

                $reference = 'RES-' . $locked->id;
                $existing = EcritureComptable::where('company_id', $companyId)
                    ->where('exercices_comptables_id', $locked->id)
                    ->where('reference_piece', $reference)->get();

                if ($existing->isNotEmpty() && !$request->boolean('recalculer')) {
                    throw new RuntimeException("Une pièce RES existe déjà. Confirmez le recalcul pour la remplacer.");
                }

                if ($existing->isNotEmpty()) {
                    if ($existing->contains(fn ($line) => !(bool) $line->is_resultat_systeme)) {
                        throw new RuntimeException("La pièce RES contient des lignes non marquées comme système. Suppression automatique refusée pour protéger les écritures manuelles.");
                    }
                    EcritureComptable::where('company_id', $companyId)
                        ->where('exercices_comptables_id', $locked->id)
                        ->where('reference_piece', $reference)
                        ->where('is_resultat_systeme', true)
                        ->delete();
                    $locked->update(['resultat_passe' => false, 'resultat_passe_le' => null]);
                    $locked->refresh();
                }

                $result = $this->creerEcritureResultat($locked, $companyId, $user);
                $this->completerContrepartiesResultat($locked, $companyId, $user);
                $locked->refresh();

                if (!$this->pieceResultatSystemeValide($locked)) {
                    throw new RuntimeException("La pièce de résultat créée n'est pas équilibrée ou ne solde pas correctement les classes 6, 7 et 8. La transaction est annulée.");
                }
                return $result;
            }, 5);

            $message = $resultat['sans_ligne']
                ? 'Résultat nul : une pièce OD système équilibrée a été créée et les soldes des classes 6, 7 et 8 ont été contrôlés. L’exercice reste ouvert.'
                : ComptesDeResultat::annonce($resultat['montant']) . ' Le résultat est enregistré dans OD ; l’exercice reste ouvert.';
            return back()->with('success', $message);
        } catch (\Throwable $e) {
            Log::warning('Passage/recalcul du résultat refusé', [
                'exercice_id' => $exercice->id,
                'company_id' => $companyId,
                'erreur' => $e->getMessage()
            ]);
            return back()->with('error', $e->getMessage());
        }
    }

    public function cloturer($id)
    {
        if (!Auth::user()->hasPermission('exercice_comptable')) {
            return back()->with('error', 'Action non autorisée : habilitation « Exercice comptable » requise.');
        }

        $user = Auth::user();
        $companyId = session('current_company_id', $user->company_id);
        $exercice = ExerciceComptable::where('company_id', $companyId)->findOrFail($id);
        if ($exercice->cloturer) return back()->with('error', 'L’exercice est déjà clôturé.');
        if (Carbon::parse($exercice->date_debut)->gt(now()->startOfDay())) {
            return back()->with('error', 'Impossible de clôturer avant la date de début.');
        }
        if (Carbon::parse($exercice->date_fin)->gt(now()->startOfDay())) {
            return back()->with('error', 'Clôture impossible : la date de fin réelle de l’exercice n’est pas encore atteinte. La date de l’exercice ne sera pas raccourcie automatiquement.');
        }

        $pending = EcritureComptable::where('company_id', $companyId)
            ->where('exercices_comptables_id', $id)->where('statut', 'pending')->count();
        if ($pending) {
            return back()->with('error', "Clôture bloquée : {$pending} écriture(s) en attente de validation.");
        }

        try {
            DB::transaction(function () use ($exercice, $companyId, $user) {
                $source = ExerciceComptable::where('company_id', $companyId)
                    ->whereKey($exercice->id)->lockForUpdate()->firstOrFail();

                if ($source->cloturer) {
                    throw new RuntimeException("L’exercice est déjà clôturé.");
                }
                $dateFin = Carbon::parse($source->date_fin)->startOfDay();
                if ($dateFin->gt(now()->startOfDay())) {
                    throw new RuntimeException("Clôture bloquée : la date de fin réelle de l’exercice n’est pas atteinte.");
                }

                $pendingNow = EcritureComptable::where('company_id', $companyId)
                    ->where('exercices_comptables_id', $source->id)
                    ->where('statut', 'pending')->exists();
                if ($pendingNow) {
                    throw new RuntimeException("Clôture bloquée : des écritures sont encore en attente de validation.");
                }
                if ($this->hasResultatManuel($source)) {
                    throw new RuntimeException("Une écriture manuelle sur 1301/1309 est détectée. Vérifiez-la avant la clôture automatique.");
                }

                $referenceResultat = 'RES-' . $source->id;
                $resultatRows = EcritureComptable::where('company_id', $companyId)
                    ->where('exercices_comptables_id', $source->id)
                    ->where('reference_piece', $referenceResultat)->get();

                if ($resultatRows->isEmpty()) {
                    $this->creerEcritureResultat($source, $companyId, $user);
                    $this->completerContrepartiesResultat($source, $companyId, $user);
                    $source->refresh();
                } elseif (!$this->pieceResultatSystemeValide($source)) {
                    throw new RuntimeException("La pièce système RES existante est incomplète ou invalide. Utilisez Résultat > Recalculer avant la clôture. Aucune ligne existante n’a été supprimée.");
                }

                if (!$this->pieceResultatSystemeValide($source)) {
                    throw new RuntimeException("Clôture annulée : la pièce de résultat système est absente, incomplète ou déséquilibrée.");
                }

                $startNext = $dateFin->copy()->addDay()->startOfDay();
                $next = ExerciceComptable::where('company_id', $companyId)
                    ->whereDate('date_debut', $startNext->toDateString())->first();
                if (!$next) {
                    throw new RuntimeException("Clôture bloquée : l’exercice suivant immédiat (ouverture le " . $startNext->format('d/m/Y') . ") n’existe pas. Créez-le explicitement avant de clôturer.");
                }
                $next = ExerciceComptable::where('company_id', $companyId)
                    ->whereKey($next->id)->lockForUpdate()->firstOrFail();
                if ($this->exerciceEstValideOuCloture($next)) {
                    throw new RuntimeException("Clôture bloquée : l’exercice suivant est déjà clôturé ou validé.");
                }

                $referenceRan = 'RAN-' . $source->id;
                if (EcritureComptable::where('company_id', $companyId)
                    ->where('reference_piece', $referenceRan)->exists()) {
                    throw new RuntimeException("Clôture bloquée : une pièce de report à nouveau {$referenceRan} existe déjà. Aucun doublon ne sera créé.");
                }

                $journal = CodeJournal::where('company_id', $companyId)
                    ->whereIn('code_journal', ['RAN', 'AN'])
                    ->orderByRaw("CASE WHEN code_journal = 'RAN' THEN 0 ELSE 1 END")
                    ->first();
                if (!$journal) {
                    $journal = CodeJournal::create([
                        'company_id' => $companyId,
                        'code_journal' => 'RAN',
                        'intitule' => 'REPORT À NOUVEAU',
                        'type' => 'Opérations Diverses',
                        'traitement_analytique' => 0,
                        'user_id' => $user->id
                    ]);
                }

                $rows = EcritureComptable::where('company_id', $companyId)
                    ->where('exercices_comptables_id', $source->id)
                    ->where(function ($q) {
                        $q->whereNull('statut')->orWhereIn('statut', ['approved', 'valid']);
                    })
                    ->with('planComptable')->get();

                $balances = [];
                foreach ($rows as $line) {
                    $account = $line->planComptable;
                    $class = substr((string) ($account?->numero_de_compte ?? ''), 0, 1);
                    if (!in_array($class, ['1', '2', '3', '4', '5'], true)) continue;

                    // Conserver séparément chaque dimension auxiliaire, y compris le flux.
                    $key = implode('|', [
                        (string) $line->plan_comptable_id,
                        (string) $line->plan_tiers_id,
                        (string) $line->compte_tresorerie_id,
                        (string) $line->poste_tresorerie_id,
                        (string) $line->type_flux
                    ]);
                    $balances[$key] ??= [
                        'plan_comptable_id' => $line->plan_comptable_id,
                        'plan_tiers_id' => $line->plan_tiers_id,
                        'compte_tresorerie_id' => $line->compte_tresorerie_id,
                        'poste_tresorerie_id' => $line->poste_tresorerie_id,
                        'type_flux' => $line->type_flux,
                        'balance' => 0.0
                    ];
                    $balances[$key]['balance'] += (float) $line->debit - (float) $line->credit;
                }

                $debitTotal = 0.0;
                $creditTotal = 0.0;
                foreach ($balances as $balance) {
                    $amount = round($balance['balance'], 2);
                    if (abs($amount) < 0.01) continue;

                    $debit = $amount > 0 ? $amount : 0.0;
                    $credit = $amount < 0 ? abs($amount) : 0.0;
                    $debitTotal += $debit;
                    $creditTotal += $credit;

                    EcritureComptable::create([
                        'date' => $next->date_debut->format('Y-m-d'),
                        'n_saisie' => 'RAN-' . $dateFin->format('Y'),
                        'n_saisie_user' => 'RAN-' . $dateFin->format('Y'),
                        'description_operation' => $this->libelleReportAN($source),
                        'reference_piece' => $referenceRan,
                        'plan_comptable_id' => $balance['plan_comptable_id'],
                        'plan_tiers_id' => $balance['plan_tiers_id'],
                        'compte_tresorerie_id' => $balance['compte_tresorerie_id'],
                        'poste_tresorerie_id' => $balance['poste_tresorerie_id'],
                        'type_flux' => $balance['type_flux'],
                        'code_journal_id' => $journal->id,
                        'exercices_comptables_id' => $next->id,
                        'debit' => $debit,
                        'credit' => $credit,
                        'company_id' => $companyId,
                        'user_id' => $user->id,
                        'is_ran' => true,
                        'plan_analytique' => 0,
                        'statut' => 'approved'
                    ]);
                }

                if (abs(round($debitTotal - $creditTotal, 2)) >= 0.01) {
                    throw new RuntimeException(sprintf(
                        "Report à nouveau non équilibré (débit %.2f / crédit %.2f). Clôture annulée.",
                        $debitTotal,
                        $creditTotal
                    ));
                }

                $source->update(['cloturer' => true]);
            }, 5);

            return back()->with('success', 'Clôture terminée : résultat système vérifié dans OD, REPORT À NOUVEAU détaillé créé dans le journal RAN/AN à la date d’ouverture de l’exercice suivant et contrôlé équilibré.');
        } catch (\Throwable $e) {
            Log::error('Erreur clôture exercice', [
                'exercice_id' => $id,
                'company_id' => $companyId,
                'message' => $e->getMessage()
            ]);
            return back()->with('error', 'Clôture annulée : ' . $e->getMessage());
        }
    }

    public function reouvrir($id)
    {
        if (!Auth::user()->hasPermission('exercice_comptable')) {
            return back()->with('error', 'Action non autorisée.');
        }
        $companyId = session('current_company_id', Auth::user()->company_id);
        $exercice = ExerciceComptable::where('company_id', $companyId)->findOrFail($id);
        if (!$exercice->cloturer) return back()->with('error', 'L’exercice est déjà ouvert.');

        try {
            DB::transaction(function () use ($exercice, $companyId) {
                $source = ExerciceComptable::where('company_id', $companyId)
                    ->whereKey($exercice->id)->lockForUpdate()->firstOrFail();
                if (!$source->cloturer) {
                    throw new RuntimeException("L’exercice est déjà ouvert.");
                }

                $referenceRan = 'RAN-' . $source->id;
                $dateNext = Carbon::parse($source->date_fin)->addDay()->toDateString();
                $next = ExerciceComptable::where('company_id', $companyId)
                    ->whereDate('date_debut', $dateNext)->first();

                if ($next) {
                    $next = ExerciceComptable::where('company_id', $companyId)
                        ->whereKey($next->id)->lockForUpdate()->firstOrFail();
                    $ranRows = EcritureComptable::where('company_id', $companyId)
                        ->where('exercices_comptables_id', $next->id)
                        ->where('reference_piece', $referenceRan)->get();
                    if ($ranRows->contains(fn ($line) => !(bool) $line->is_ran)) {
                        throw new RuntimeException("Réouverture bloquée : la référence du REPORT À NOUVEAU contient des lignes non identifiées comme RAN.");
                    }

                    $otherMovements = EcritureComptable::where('company_id', $companyId)
                        ->where('exercices_comptables_id', $next->id)
                        ->where(function ($q) use ($referenceRan) {
                            $q->whereNull('is_ran')->orWhere('is_ran', false)
                                ->orWhereNull('reference_piece')->orWhere('reference_piece', '!=', $referenceRan);
                        })->exists();
                    if ($otherMovements) {
                        throw new RuntimeException("Réouverture bloquée : l’exercice suivant contient des mouvements autres que le REPORT À NOUVEAU de cette clôture. Aucun mouvement n’a été supprimé.");
                    }
                    if ($this->exerciceEstValideOuCloture($next)) {
                        throw new RuntimeException("Réouverture bloquée : l’exercice suivant est déjà clôturé ou validé.");
                    }

                    EcritureComptable::where('company_id', $companyId)
                        ->where('exercices_comptables_id', $next->id)
                        ->where('reference_piece', $referenceRan)
                        ->where('is_ran', true)->delete();
                } elseif (EcritureComptable::where('company_id', $companyId)
                    ->where('reference_piece', $referenceRan)->exists()) {
                    throw new RuntimeException("Réouverture bloquée : des lignes RAN existent mais l’exercice cible immédiat est introuvable. Aucune suppression n’a été effectuée.");
                }

                $referenceResultat = 'RES-' . $source->id;
                $resultRows = EcritureComptable::where('company_id', $companyId)
                    ->where('exercices_comptables_id', $source->id)
                    ->where('reference_piece', $referenceResultat)->get();
                if ($resultRows->contains(fn ($line) => !(bool) $line->is_resultat_systeme)) {
                    throw new RuntimeException("Réouverture bloquée : la pièce RES contient des lignes non identifiées comme système. Les écritures sont conservées.");
                }

                EcritureComptable::where('company_id', $companyId)
                    ->where('exercices_comptables_id', $source->id)
                    ->where('reference_piece', $referenceResultat)
                    ->where('is_resultat_systeme', true)->delete();

                $source->update([
                    'cloturer' => false,
                    'resultat_passe' => false,
                    'resultat_passe_le' => null
                ]);
            }, 5);

            return back()->with('success', 'Exercice rouvert. Le REPORT À NOUVEAU système et la pièce de résultat système ont été supprimés après vérification ; les mouvements manuels sont conservés.');
        } catch (\Throwable $e) {
            Log::warning('Réouverture exercice refusée', [
                'exercice_id' => $exercice->id,
                'company_id' => $companyId,
                'erreur' => $e->getMessage()
            ]);
            return back()->with('error', $e->getMessage());
        }
    }

    public function destroy($id)
    {
        if (!Auth::user()->hasPermission('exercice_comptable')) {
            return back()->with('error', 'Action non autorisée : habilitation « Exercice comptable » requise.');
        }
        $exercice = ExerciceComptable::findOrFail($id);
        $exercice->delete();

        return redirect()->back()->with('success', 'L\'exercice comptable a été supprimé avec succès.');
    }

    public function show($id)
    {
        $user = Auth::user();
        $companyId = session('current_company_id', $user->company_id);

        $exercice = ExerciceComptable::where('id', $id)
            ->where('company_id', $companyId)
            ->firstOrFail();

        // Récupérer les journaux saisis pour cet exercice
        $journauxSaisis = JournalSaisi::where('exercices_comptables_id', $id)
            ->where('company_id', $companyId)
            ->with(['codeJournal'])
            ->get();

        return view('exercice_comptable_show', compact('exercice', 'journauxSaisis'));
    }

    public function edit($id)
    {
        $user = Auth::user();
        $companyId = session('current_company_id', $user->company_id);

        $exercice = ExerciceComptable::where('id', $id)
            ->where('company_id', $companyId)
            ->firstOrFail();

        // Empêcher l'édition si l'exercice est clôturé
        if ($exercice->cloturer) {
            return back()->with('error', 'Impossible de modifier un exercice clôturé.');
        }

        return view('exercice_comptable_edit', compact('exercice'));
    }

    public function update(Request $request, $id)
    {
        if (!Auth::user()->hasPermission('exercice_comptable')) {
            return back()->with('error', 'Action non autorisée : habilitation « Exercice comptable » requise.');
        }
        $user = Auth::user();
        $companyId = session('current_company_id', $user->company_id);

        $exercice = ExerciceComptable::where('id', $id)
            ->where('company_id', $companyId)
            ->firstOrFail();

        // Empêcher la mise à jour si l'exercice est clôturé
        if ($exercice->cloturer) {
            return back()->with('error', 'Impossible de modifier un exercice clôturé.');
        }

        $request->validate([
            'date_debut' => 'required|date',
            'date_fin' => 'required|date|after:date_debut',
        ], [
            'date_fin.after' => 'La date de fin doit être postérieure à la date de début.',
        ]);

        $dateDebut = Carbon::parse($request->date_debut)->startOfDay();
        $dateFin = Carbon::parse($request->date_fin)->startOfDay();

        // On ne met à jour que les colonnes maîtrisées ici : l'intitulé reste dérivé
        // de l'année de début, comme à la création.
        $exercice->update([
            'date_debut' => $dateDebut->format('Y-m-d'),
            'date_fin' => $dateFin->format('Y-m-d'),
            'intitule' => $this->genererIntitule($dateDebut, $dateFin),
        ]);

        return redirect()->route('exercice_comptable')
            ->with('success', 'Exercice mis à jour avec succès.');
    }

    /**
     * Durée de l'exercice en mois, bornes incluses.
     * Ex : 01/01/2026 -> 31/12/2026 = 12 mois (et non 11).
     */
    private function calculerNbMois($dateDebut, $dateFin)
    {
        $debut = Carbon::parse($dateDebut)->startOfDay();
        $fin = Carbon::parse($dateFin)->startOfDay()->addDay();

        $mois = (int) $debut->diffInMonths($fin);

        return $mois > 0 ? $mois : 1; // Minimum 1 mois
    }

    /**
     * Intitulé normalisé d'un exercice, toujours basé sur l'année de la date de début.
     * Le calcul est fait ici (côté serveur) et non dans le navigateur, afin qu'aucun
     * fuseau horaire client ne puisse décaler l'année.
     */
    private function genererIntitule($dateDebut, $dateFin = null)
    {
        $debut = Carbon::parse($dateDebut);
        $fin = $dateFin ? Carbon::parse($dateFin) : $debut->copy();
        $annees = $debut->year === $fin->year ? (string) $debut->year : $debut->year . '-' . $fin->year;

        return 'EXERCICE ' . $annees;
    }

    private function checkUpcomingDeadlines($companyId)
    {
        $activeExercice = ExerciceComptable::where('company_id', $companyId)
            ->where('cloturer', 0)
            ->where('is_active', 1)
            ->first();

        if ($activeExercice) {
            $dateFin = Carbon::parse($activeExercice->date_fin);
            $daysRemaining = (int) now()->diffInDays($dateFin, false);

            if ($daysRemaining <= 30 && $daysRemaining >= -1) {
                $exists = InternalNotification::where('company_id', $companyId)
                    ->where('receiver_id', Auth::id())
                    ->where('title', 'Échéance d\'exercice')
                    ->whereDate('created_at', now()->toDateString())
                    ->exists();

                if (!$exists) {
                    $type = 'warning';
                    $msg = "L'exercice '{$activeExercice->intitule}' se termine bientôt (J-{$daysRemaining}).";
                    if ($daysRemaining <= 7) $type = 'danger';
                    if ($daysRemaining < 0) $msg = "L'exercice '{$activeExercice->intitule}' est arrivé à échéance le " . $dateFin->format('d/m/Y') . ".";

                    // sender_id est obligatoire en base : une notification d'échéance
                    // sans expéditeur faisait planter l'affichage de la page.
                    // Un incident sur la notification ne doit jamais empêcher
                    // la consultation des exercices.
                    try {
                        InternalNotification::create([
                            'title' => 'Échéance d\'exercice',
                            'message' => $msg,
                            'type' => $type,
                            'sender_id' => Auth::id(),
                            'receiver_id' => Auth::id(),
                            'company_id' => $companyId
                        ]);
                    } catch (\Exception $e) {
                        Log::warning('Notification échéance exercice non créée : ' . $e->getMessage());
                    }
                }
            }
        }
    }
    /** Libellé RAN calculé exclusivement à partir des dates réelles de l'exercice clôturé. */
    private function libelleReportAN(ExerciceComptable $exercice): string
    {
        $debut = Carbon::parse($exercice->date_debut);
        $fin = Carbon::parse($exercice->date_fin);
        $periode = $debut->year === $fin->year ? (string)$debut->year : $debut->year.'-'.$fin->year;
        return 'Report à nouveau exercice '.$periode;
    }

    /** Un exercice cible est bloqué s'il porte un indicateur de clôture/validation disponible dans le schéma. */
    private function exerciceEstValideOuCloture(ExerciceComptable $exercice): bool
    {
        if ((bool)$exercice->cloturer) return true;
        $attributes = $exercice->getAttributes();
        foreach (['valide','validated','is_validated','validation','validation_status','statut','status'] as $field) {
            if (!array_key_exists($field, $attributes)) continue;
            $value = $attributes[$field];
            if (in_array(strtolower((string)$value), ['1','true','validé','validée','valide','validated','approved','closed','cloture','clôturé'], true)) return true;
        }
        return false;
    }

    /** Toute écriture 1301/1309 non explicitement marquée système constitue une intervention manuelle. */
    private function hasResultatManuel(ExerciceComptable $exercice): bool
    {
        return EcritureComptable::where('company_id', $exercice->company_id)
            ->where('exercices_comptables_id', $exercice->id)
            ->where(function ($q) {
                $q->whereNull('statut')->orWhereNotIn('statut', ['rejected', 'refused']);
            })
            ->where(function ($q) {
                $q->whereNull('is_ran')->orWhere('is_ran', false);
            })
            ->where(function ($q) {
                $q->whereNull('is_resultat_systeme')->orWhere('is_resultat_systeme', false);
            })
            ->whereHas('planComptable', function ($q) {
                $q->where('numero_de_compte', 'like', '1301%')
                    ->orWhere('numero_de_compte', 'like', '1309%');
            })->exists();
    }

    /** Vérifie le marqueur système, l’équilibre de la pièce et le solde inverse de toutes les classes 6, 7 et 8. */
    private function pieceResultatSystemeValide(ExerciceComptable $exercice): bool
    {
        if (!(bool) $exercice->resultat_passe) return false;

        $reference = 'RES-' . $exercice->id;
        $piece = EcritureComptable::where('company_id', $exercice->company_id)
            ->where('exercices_comptables_id', $exercice->id)
            ->where('reference_piece', $reference)
            ->with(['planComptable', 'codeJournal'])->get();

        if ($piece->isEmpty()) return false;
        if ($piece->contains(fn ($line) => !(bool) $line->is_resultat_systeme)) return false;
        if ($piece->contains(fn ($line) => ($line->codeJournal?->code_journal ?? null) !== 'OD')) return false;
        if ($piece->contains(fn ($line) => Carbon::parse($line->date)->toDateString() !== Carbon::parse($exercice->date_fin)->toDateString())) return false;
        if ($piece->contains(fn ($line) => !in_array($line->statut, [null, 'approved', 'valid'], true))) return false;

        $debit = round((float) $piece->sum('debit'), 2);
        $credit = round((float) $piece->sum('credit'), 2);
        if (abs($debit - $credit) >= 0.01) return false;

        $source = EcritureComptable::where('company_id', $exercice->company_id)
            ->where('exercices_comptables_id', $exercice->id)
            ->where(function ($q) {
                $q->whereNull('statut')->orWhereIn('statut', ['approved', 'valid']);
            })
            ->where(function ($q) {
                $q->whereNull('is_ran')->orWhere('is_ran', false);
            })
            ->where(function ($q) use ($reference) {
                $q->whereNull('reference_piece')->orWhere('reference_piece', '!=', $reference);
            })
            ->with('planComptable')->get();

        $before = [];
        $after = [];
        $net = 0.0;
        foreach ($source as $line) {
            $numero = (string) ($line->planComptable?->numero_de_compte ?? '');
            if (!in_array(substr($numero, 0, 1), ['6', '7', '8'], true)) continue;
            $balance = (float) $line->debit - (float) $line->credit;
            $before[$line->plan_comptable_id] = ($before[$line->plan_comptable_id] ?? 0.0) + $balance;
            $net -= $balance;
        }
        foreach ($piece as $line) {
            $numero = (string) ($line->planComptable?->numero_de_compte ?? '');
            if (in_array(substr($numero, 0, 1), ['6', '7', '8'], true)) {
                $after[$line->plan_comptable_id] = ($after[$line->plan_comptable_id] ?? 0.0)
                    + (float) $line->debit - (float) $line->credit;
            }
        }

        foreach ($before as $id => $balance) {
            if (abs(round($balance + ($after[$id] ?? 0.0), 2)) >= 0.01) return false;
        }
        foreach ($after as $id => $balance) {
            if (!array_key_exists($id, $before) && abs(round($balance, 2)) >= 0.01) return false;
        }

        $net = round($net, 2);
        $resultAccounts = $piece->filter(function ($line) {
            $numero = (string) ($line->planComptable?->numero_de_compte ?? '');
            return str_starts_with($numero, '1301') || str_starts_with($numero, '1309');
        });
        $resultBalance = round((float) $resultAccounts->sum(
            fn ($line) => (float) $line->credit - (float) $line->debit
        ), 2);
        if (abs($resultBalance - $net) >= 0.01) return false;

        if (abs($net) >= 0.01) {
            $expectedPrefix = $net > 0 ? '1301' : '1309';
            if ($resultAccounts->isEmpty()
                || $resultAccounts->contains(fn ($line) => !str_starts_with((string) ($line->planComptable?->numero_de_compte ?? ''), $expectedPrefix))) {
                return false;
            }
        } elseif ($resultAccounts->contains(fn ($line) => abs((float) $line->debit) >= 0.01 || abs((float) $line->credit) >= 0.01)) {
            return false;
        }

        return true;
    }

    /**
     * Crée la ligne de résultat à la date exacte de fin de l’exercice.
     * La création des contreparties est centralisée dans completerContrepartiesResultat().
     */
    private function creerEcritureResultat(ExerciceComptable $exercice, int $companyId, $user): array
    {
        $resultatData = $this->reportingService->getSIGData($exercice->id, $companyId);
        $montant = round((float) ($resultatData['resultat_net'] ?? 0), 2);

        $journal = CodeJournal::where('company_id', $companyId)
            ->where('code_journal', 'OD')->first();
        if (!$journal) {
            $journal = CodeJournal::create([
                'company_id' => $companyId,
                'code_journal' => 'OD',
                'intitule' => 'OPÉRATIONS DIVERSES',
                'type' => 'Opérations Diverses',
                'traitement_analytique' => 0,
                'user_id' => $user->id
            ]);
        }

        // Ne jamais remplacer la date réelle de fin par la date du jour.
        $date = Carbon::parse($exercice->date_fin)->startOfDay();
        $journalSaisi = JournalSaisi::firstOrCreate([
            'annee' => $date->year,
            'mois' => $date->month,
            'exercices_comptables_id' => $exercice->id,
            'code_journals_id' => $journal->id,
            'company_id' => $companyId
        ], ['user_id' => $user->id]);

        if (abs($montant) < 0.01) {
            // Ligne technique à zéro : elle matérialise la pièce système sans effet comptable.
            $compte = PlanComptable::where('company_id', $companyId)
                ->where('numero_de_compte', 'like', '1301%')->first();
            if (!$compte) {
                $compte = ComptesDeResultat::pourOuCreer($companyId, 0, $user->id);
            }
            EcritureComptable::create([
                'date' => $date->toDateString(),
                'n_saisie' => 'RES-' . $date->format('Y'),
                'n_saisie_user' => 'RES-' . $date->format('Y'),
                'description_operation' => 'AFFECTATION DU RESULTAT ' . $exercice->intitule,
                'reference_piece' => 'RES-' . $exercice->id,
                'plan_comptable_id' => $compte->id,
                'code_journal_id' => $journal->id,
                'exercices_comptables_id' => $exercice->id,
                'journaux_saisis_id' => $journalSaisi->id,
                'debit' => 0,
                'credit' => 0,
                'company_id' => $companyId,
                'user_id' => $user->id,
                'is_ran' => false,
                'is_resultat_systeme' => true,
                'plan_analytique' => 0,
                'statut' => 'approved'
            ]);
            $exercice->update(['resultat_passe' => true, 'resultat_passe_le' => now()]);
            return ['montant' => 0.0, 'sans_ligne' => true];
        }

        $compte = ComptesDeResultat::pourOuCreer($companyId, $montant, $user->id);
        $sens = ComptesDeResultat::sens($montant);
        EcritureComptable::create([
            'date' => $date->toDateString(),
            'n_saisie' => 'RES-' . $date->format('Y'),
            'n_saisie_user' => 'RES-' . $date->format('Y'),
            'description_operation' => 'AFFECTATION DU RESULTAT ' . $exercice->intitule,
            'reference_piece' => 'RES-' . $exercice->id,
            'plan_comptable_id' => $compte->id,
            'code_journal_id' => $journal->id,
            'exercices_comptables_id' => $exercice->id,
            'journaux_saisis_id' => $journalSaisi->id,
            'debit' => $sens['debit'],
            'credit' => $sens['credit'],
            'company_id' => $companyId,
            'user_id' => $user->id,
            'is_ran' => false,
            'is_resultat_systeme' => true,
            'plan_analytique' => 0,
            'statut' => 'approved'
        ]);

        $exercice->update(['resultat_passe' => true, 'resultat_passe_le' => now()]);
        return ['montant' => $montant, 'sans_ligne' => false];
    }

    /**
     * Reconstruit intégralement les contreparties de la pièce RES.
     * Chaque appel repart des soldes sources : aucun ancien solde résiduel ne subsiste.
     */
    private function completerContrepartiesResultat(ExerciceComptable $exercice, int $companyId, $user): void
    {
        $reference = 'RES-' . $exercice->id;
        $resultatLine = EcritureComptable::where('company_id', $companyId)
            ->where('exercices_comptables_id', $exercice->id)
            ->where('reference_piece', $reference)
            ->where('is_resultat_systeme', true)
            ->where('description_operation', 'like', 'AFFECTATION DU RESULTAT%')
            ->first();

        if (!$resultatLine) {
            throw new RuntimeException("Impossible de compléter la pièce RES : sa ligne système d’affectation du résultat est absente.");
        }

        // Les anciennes contreparties système sont remplacées dans la même transaction.
        EcritureComptable::where('company_id', $companyId)
            ->where('exercices_comptables_id', $exercice->id)
            ->where('reference_piece', $reference)
            ->where('is_resultat_systeme', true)
            ->where('description_operation', 'like', 'CLÔTURE RESULTAT:%')
            ->delete();

        $source = EcritureComptable::where('company_id', $companyId)
            ->where('exercices_comptables_id', $exercice->id)
            ->where(function ($q) {
                $q->whereNull('statut')->orWhereIn('statut', ['approved', 'valid']);
            })
            ->where(function ($q) {
                $q->whereNull('is_ran')->orWhere('is_ran', false);
            })
            ->where(function ($q) use ($reference) {
                $q->whereNull('reference_piece')->orWhere('reference_piece', '!=', $reference);
            })
            ->with('planComptable')->get();

        $soldes = [];
        foreach ($source as $ligne) {
            $plan = $ligne->planComptable;
            $numero = (string) ($plan?->numero_de_compte ?? '');
            if ($numero === '' || !in_array(substr($numero, 0, 1), ['6', '7', '8'], true)) continue;
            $soldes[$plan->id] = ($soldes[$plan->id] ?? 0.0)
                + (float) $ligne->debit - (float) $ligne->credit;
        }

        foreach ($soldes as $planId => $soldeSource) {
            $soldeCible = round(-$soldeSource, 2);
            if (abs($soldeCible) < 0.01) continue;

            EcritureComptable::create([
                'date' => Carbon::parse($exercice->date_fin)->toDateString(),
                'n_saisie' => $resultatLine->n_saisie,
                'n_saisie_user' => $resultatLine->n_saisie_user,
                'description_operation' => 'CLÔTURE RESULTAT: solde du compte de gestion ' . $planId,
                'reference_piece' => $reference,
                'plan_comptable_id' => $planId,
                'code_journal_id' => $resultatLine->code_journal_id,
                'exercices_comptables_id' => $exercice->id,
                'journaux_saisis_id' => $resultatLine->journaux_saisis_id,
                'debit' => $soldeCible > 0 ? $soldeCible : 0,
                'credit' => $soldeCible < 0 ? abs($soldeCible) : 0,
                'company_id' => $companyId,
                'user_id' => $user->id,
                'is_ran' => false,
                'is_resultat_systeme' => true,
                'plan_analytique' => 0,
                'statut' => 'approved'
            ]);
        }
    }

    /** Résultat déjà passé uniquement si l'indicateur et la pièce système sont valides. */
    private function resultatEstPasse(ExerciceComptable $exercice): bool
    {
        return $this->pieceResultatSystemeValide($exercice);
    }
}
