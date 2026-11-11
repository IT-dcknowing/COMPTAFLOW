<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Company;
use App\Models\EcritureComptable;
use App\Models\ExerciceComptable;
use Carbon\Carbon;

class ComptaDashboardController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        if (!$user) {
            abort(403, 'Unauthorized access.');
        }

        $currentCompanyId = session('current_company_id', $user->company_id);
        $currentCompany = Company::find($currentCompanyId);

        $habilitations = $this->getUserHabilitations($user, $currentCompanyId);

        // Récupérer les données comptables réelles
        $dashboardData = $this->getDashboardData($currentCompanyId);
        
        // Récupérer l'exercice en cours résolu par le middleware
        $exerciceId = session('exercice_actif_id') ?? session('current_exercice_id');
        $exerciceEnCours = null;

        if ($exerciceId) {
            $exerciceEnCours = ExerciceComptable::where('id', $exerciceId)
                ->where('company_id', $currentCompanyId)
                ->first();
        }

        if (!$exerciceEnCours) {
             $exerciceEnCours = ExerciceComptable::where('company_id', $currentCompanyId)
                ->where('is_active', 1)
                ->first()
                ?? ExerciceComptable::where('company_id', $currentCompanyId)
                ->where('cloturer', 0)
                ->orderBy('date_debut', 'desc')
                ->first();
        }

        return view('comptable.comptdashboard', array_merge($dashboardData, [
            'currentCompany' => $currentCompany,
            'habilitations' => $habilitations,
            'exerciceEnCours' => $exerciceEnCours
        ]));
    }

    private function getDashboardData($companyId)
    {
        // Le tableau comptable ne présente volontairement aucun KPI financier.
        // Il conserve uniquement le contexte d'exercice, l'activité de trésorerie
        // récente et les alertes utiles à l'utilisateur connecté.
        $exerciceId = session('exercice_actif_id') ?? session('current_exercice_id');
        $currentExercice = null;

        if ($exerciceId) {
            $currentExercice = ExerciceComptable::where('id', $exerciceId)
                ->where('company_id', $companyId)
                ->first();
        }

        if (!$currentExercice) {
            $currentExercice = ExerciceComptable::where('company_id', $companyId)
                ->where('is_active', 1)
                ->first()
                ?? ExerciceComptable::where('company_id', $companyId)
                    ->where('cloturer', 0)
                    ->orderBy('date_debut', 'desc')
                    ->first();
        }

        $exerciceId = $currentExercice?->id;
        $exerciceYear = $currentExercice
            ? Carbon::parse($currentExercice->date_debut)->year
            : (int) date('Y');

        $exerciceProgress = 0;
        if ($currentExercice) {
            $start = Carbon::parse($currentExercice->date_debut);
            $end = Carbon::parse($currentExercice->date_fin);
            $latestEntryDate = EcritureComptable::where('company_id', $companyId)
                ->where('exercices_comptables_id', $currentExercice->id)
                ->where('statut', 'approved')
                ->max('date');

            $referenceDate = $latestEntryDate ? Carbon::parse($latestEntryDate) : Carbon::now();
            if ($referenceDate->greaterThanOrEqualTo($end)) {
                $exerciceProgress = 100;
            } elseif ($referenceDate->greaterThan($start)) {
                $totalDays = max(1, $start->diffInDays($end));
                $elapsedDays = $start->diffInDays($referenceDate);
                $exerciceProgress = min(100, max(0, (int) round(($elapsedDays / $totalDays) * 100)));
            }
        }

        $recentTreasuryEntries = EcritureComptable::with(['planComptable', 'codeJournal'])
            ->where('company_id', $companyId)
            ->where('user_id', auth()->id())
            ->where('statut', 'approved')
            ->whereHas('planComptable', function ($query) {
                $query->where('numero_de_compte', 'like', '5%');
            })
            ->when($exerciceId, fn ($query) => $query->where('exercices_comptables_id', $exerciceId))
            ->orderByDesc('date')
            ->limit(3)
            ->get()
            ->map(function ($entry) {
                $isCredit = $entry->credit > 0;

                return [
                    'title' => $entry->planComptable->intitule ?? 'Opération de trésorerie',
                    'poste' => $entry->description_operation,
                    'date' => Carbon::parse($entry->date)->translatedFormat('d M Y'),
                    'amount' => $isCredit ? -$entry->credit : $entry->debit,
                    'icon' => str_contains(strtolower($entry->planComptable->intitule ?? ''), 'banque')
                        ? 'university'
                        : 'money-bill-wave',
                ];
            });

        return [
            'exerciceYear' => $exerciceYear,
            'exerciceProgress' => $exerciceProgress,
            'recentTreasuryEntries' => $recentTreasuryEntries,
            'alerts' => $this->getAccountingAlerts($companyId, $exerciceId),
        ];
    }

    private function getAccountingAlerts($companyId, $exerciceId)
    {
        $alerts = [];

        // Alerte 1: Écritures sans pièce justificative
        $entriesWithoutDocs = EcritureComptable::where('company_id', $companyId)
            ->where('user_id', auth()->id()) // Filtre utilisateur
            ->when($exerciceId, function($query) use ($exerciceId) {
                return $query->where('exercices_comptables_id', $exerciceId);
            })
            ->whereNull('piece_justificatif')
            ->count();

        if ($entriesWithoutDocs > 0) {
            $alerts[] = [
                'title' => 'Pièces justificatives manquantes',
                'description' => "$entriesWithoutDocs écritures sans pièce justificative",
                'priority' => $entriesWithoutDocs > 10 ? 'high' : 'medium',
                'icon' => 'file-exclamation'
            ];
        }

        // Alerte 2: Exercice proche de la clôture (Globale)
        if ($currentExercice = ExerciceComptable::where('company_id', $companyId)->find($exerciceId)) {
            $endDate = Carbon::parse($currentExercice->date_fin);
            $daysUntilEnd = $endDate->diffInDays(Carbon::now());

            if ($daysUntilEnd <= 30) {
                $alerts[] = [
                    'title' => 'Clôture de l\'exercice',
                    'description' => "L'exercice se termine dans $daysUntilEnd jours",
                    'priority' => $daysUntilEnd <= 7 ? 'high' : 'medium',
                    'icon' => 'calendar-check'
                ];
            }
        }

        // Alerte 3: Solde des comptes de trésorerie négatif (Global ou Perso? "Tous ce que l'admin passe" -> Perso pour le dashboard standard)
        $negativeBalances = EcritureComptable::where('company_id', $companyId)
            ->where('user_id', auth()->id()) // Filtre utilisateur
            ->when($exerciceId, function($query) use ($exerciceId) {
                return $query->where('exercices_comptables_id', $exerciceId);
            })
            ->whereHas('planComptable', function($query) {
                $query->whereRaw('SUBSTRING(numero_de_compte, 1, 1) IN (?, ?)', ['5', '6']);
            })
            ->selectRaw('plan_comptable_id, SUM(credit) - SUM(debit) as balance')
            ->groupBy('plan_comptable_id')
            ->having('balance', '<', 0)
            ->count();

        if ($negativeBalances > 0) {
            $alerts[] = [
                'title' => 'Soldes négatifs (Vos écritures)',
                'description' => "$negativeBalances comptes présentent un solde négatif",
                'priority' => 'high',
                'icon' => 'exclamation-triangle'
            ];
        }

        return $alerts;
    }

    protected function getUserHabilitations($user, $companyId)
    {
        // Si l'utilisateur est SuperAdmin, il voit tout
        if ($user->isSuperAdmin()) {
            return ['dashboard', 'plan_comptable', 'plan_tiers', 'accounting_journals', 'indextresorerie',
                    'modal_saisie_direct', 'exercice_comptable', 'accounting_entry_real',
                    'gestion_tresorerie', 'accounting_ledger', 'accounting_ledger_tiers',
                    'accounting_balance', 'accounting_balance_tiers', 'compte_exploitation',
                    'flux_tresorerie', 'tableau_amortissements', 'etat_tiers', 'compte_resultat',
                    'bilan', 'etats_analytiques', 'etats_previsionnels', 'user_management', 'compagny_information'];
        }

        // Si l'utilisateur est l'Admin de la compagnie, il a tous les accès comptables.
        if ($user->isAdmin()) {
             return ['dashboard', 'plan_comptable', 'plan_tiers', 'accounting_journals', 'indextresorerie',
                    'modal_saisie_direct', 'exercice_comptable', 'accounting_entry_real',
                    'gestion_tresorerie', 'accounting_ledger', 'accounting_ledger_tiers',
                    'accounting_balance', 'accounting_balance_tiers', 'compte_exploitation',
                    'flux_tresorerie', 'tableau_amortissements', 'etat_tiers', 'compte_resultat',
                    'bilan', 'etats_analytiques', 'etats_previsionnels', 'user_management', 'compagny_information'];
        }

        // Pour les utilisateurs créés (Comptable, standard, etc.):
        $habilitations = $user->habilitations;

        // Si le champ est une chaîne JSON, il faut le décoder.
        if (is_string($habilitations)) {
            $habilitations = json_decode($habilitations, true);
        }

        // Assurer que c'est un tableau valide
        if (!is_array($habilitations)) {
            $habilitations = [];
        }

        // Les habilitations retournées restent strictement celles accordées au collaborateur.

        // Assurez-vous que c'est un tableau valide, sinon retournez un tableau vide.
        return $habilitations;
    }
}
