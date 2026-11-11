<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\EcritureComptable;
use App\Models\Company;
use App\Models\ExerciceComptable;
use App\Models\PlanTiers;
use Carbon\Carbon;
use Illuminate\Http\Request;

class PerformanceController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $currentCompanyId = session('current_company_id', $user->company_id);
        $currentCompany = Company::find($currentCompanyId);

        // Si l'utilisateur n'est pas Admin, il ne devrait pas voir cette page (sécurité sup)
        // Mais middleware gère déjà.

        // Données Globales (Tous les utilisateurs de l'entreprise)
        $dashboardData = $this->getGlobalDashboardData($currentCompanyId);

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

        return view('admin.performance.index', array_merge($dashboardData, [
            'currentCompany'  => $currentCompany,
            'exerciceEnCours' => $exerciceEnCours
        ]));
    }

    private function getGlobalDashboardData($companyId)
    {
        // Récupérer l'exercice en cours résolu par le middleware
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

        $exerciceId = $currentExercice ? $currentExercice->id : null;

        // KPI 1: Total Revenus (Global)
        $totalRevenue = EcritureComptable::where('company_id', $companyId)
            ->where('statut', 'approved')
            ->when($exerciceId, function($query) use ($exerciceId) {
                return $query->where('exercices_comptables_id', $exerciceId);
            })
            ->whereHas('planComptable', function($query) {
                $query->whereRaw('SUBSTRING(numero_de_compte, 1, 1) = ?', ['7']);
            })
            ->sum('credit');

        // KPI 2: Total Charges (Global)
        $totalExpenses = EcritureComptable::where('company_id', $companyId)
            ->where('statut', 'approved')
            ->when($exerciceId, function($query) use ($exerciceId) {
                return $query->where('exercices_comptables_id', $exerciceId);
            })
            ->whereHas('planComptable', function($query) {
                $query->whereRaw('SUBSTRING(numero_de_compte, 1, 1) = ?', ['6']);
            })
            ->sum('debit');

        $netResult = $totalRevenue - $totalExpenses;

        // Le chiffre d'affaires est isolé des autres produits de la classe 7.
        $turnover = EcritureComptable::where('company_id', $companyId)
            ->where('statut', 'approved')
            ->when($exerciceId, fn ($query) => $query->where('exercices_comptables_id', $exerciceId))
            ->whereHas('planComptable', fn ($query) => $query->where('numero_de_compte', 'like', '70%'))
            ->sum('credit');

        // KPI de pilotage supplémentaires selon le plan SYSCOHADA.
        $salaryExpenses = EcritureComptable::where('company_id', $companyId)
            ->where('statut', 'approved')
            ->when($exerciceId, fn ($query) => $query->where('exercices_comptables_id', $exerciceId))
            ->whereHas('planComptable', fn ($query) => $query->where('numero_de_compte', 'like', '66%'))
            ->sum('debit');

        $vatCollected = EcritureComptable::where('company_id', $companyId)
            ->where('statut', 'approved')
            ->when($exerciceId, fn ($query) => $query->where('exercices_comptables_id', $exerciceId))
            ->whereHas('planComptable', fn ($query) => $query->where('numero_de_compte', 'like', '443%'))
            ->sum('credit');

        $vatDeductible = EcritureComptable::where('company_id', $companyId)
            ->where('statut', 'approved')
            ->when($exerciceId, fn ($query) => $query->where('exercices_comptables_id', $exerciceId))
            ->whereHas('planComptable', fn ($query) => $query->where('numero_de_compte', 'like', '445%'))
            ->sum('debit');

        $vatNetToPay = $vatCollected - $vatDeductible;
        $expenseRatio = $turnover > 0 ? round(($totalExpenses / $turnover) * 100, 1) : null;

        // KPI 4: Écritures du mois (Global)
        $monthlyEntries = EcritureComptable::where('company_id', $companyId)
            ->where('statut', 'approved')
            ->whereMonth('date', Carbon::now()->month)
            ->whereYear('date', Carbon::now()->year)
            ->count();

        // KPI 5: Solde Trésorerie (filtré sur l'exercice actif uniquement)
        $cashBalance = EcritureComptable::where('company_id', $companyId)
            ->where('statut', 'approved')
            ->when($currentExercice, function($query) use ($currentExercice) {
                return $query
                    ->where('exercices_comptables_id', $currentExercice->id)
                    ->where('date', '>=', $currentExercice->date_debut)
                    ->where('date', '<=', $currentExercice->date_fin);
            })
            ->whereHas('planComptable', function($query) {
                $query->where('numero_de_compte', 'like', '5%');
            })
            ->selectRaw('SUM(debit) - SUM(credit) as balance')
            ->first()
            ->balance ?? 0;

        // KPI 6 & 7: Tiers (Global par définition)
        $clientCount = PlanTiers::where('company_id', $companyId)
            ->where(function($q) {
                $q->where('type_de_tiers', 'client')
                  ->orWhere('numero_de_tiers', 'like', '411%');
            })->count();

        $supplierCount = PlanTiers::where('company_id', $companyId)
            ->where(function($q) {
                $q->where('type_de_tiers', 'fournisseur')
                  ->orWhere('numero_de_tiers', 'like', '401%');
            })->count();

        // Progression Exercice
        $exerciceYear = $currentExercice ? Carbon::parse($currentExercice->date_debut)->year : date('Y');
        $exerciceProgress = 0;
        if ($currentExercice) {
            $start = Carbon::parse($currentExercice->date_debut);
            $end = Carbon::parse($currentExercice->date_fin);
            
            // Trouver la date de la dernière écriture approuvée pour cet exercice
            $latestEntryDate = EcritureComptable::where('company_id', $companyId)
                ->where('exercices_comptables_id', $currentExercice->id)
                ->where('statut', 'approved')
                ->max('date');
                
            $referenceDate = $latestEntryDate ? Carbon::parse($latestEntryDate) : Carbon::now();
            
            if ($referenceDate->greaterThanOrEqualTo($end)) {
                $exerciceProgress = 100;
            } elseif ($referenceDate->lessThanOrEqualTo($start)) {
                $exerciceProgress = 0;
            } else {
                $totalDays = $start->diffInDays($end);
                $elapsedDays = $start->diffInDays($referenceDate);
                if ($totalDays > 0) {
                    $exerciceProgress = min(100, max(0, round(($elapsedDays / $totalDays) * 100)));
                }
            }
        }

        // Charts Helpers (Global)
        $revenueChartData = $this->getGlobalRevenueChartData($companyId, $exerciceId);
        $expenseChartData = $this->getGlobalExpenseChartData($companyId, $exerciceId);

        return [
            'totalRevenue' => $totalRevenue,
            'turnover' => $turnover,
            'totalExpenses' => $totalExpenses,
            'netResult' => $netResult,
            'monthlyEntries' => $monthlyEntries,
            'salaryExpenses' => $salaryExpenses,
            'vatCollected' => $vatCollected,
            'vatDeductible' => $vatDeductible,
            'vatNetToPay' => $vatNetToPay,
            'expenseRatio' => $expenseRatio,
            'cashBalance' => $cashBalance,
            'clientCount' => $clientCount,
            'supplierCount' => $supplierCount,
            'exerciceYear' => $exerciceYear,
            'exerciceProgress' => $exerciceProgress,
            'revenueChartData' => $revenueChartData,
            'expenseChartData' => $expenseChartData,
            'recentEntries' => $recentEntries,
        ];
    }

    private function getGlobalRevenueChartData($companyId, $exerciceId)
    {
        $revenues = EcritureComptable::where('company_id', $companyId)
            ->where('statut', 'approved')
            ->when($exerciceId, function($query) use ($exerciceId) {
                return $query->where('exercices_comptables_id', $exerciceId);
            })
            ->whereHas('planComptable', function($query) {
                $query->whereRaw('SUBSTRING(numero_de_compte, 1, 1) = ?', ['7']);
            })
            ->selectRaw('MONTH(date) as month, SUM(credit) as total')
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $monthlyData = array_fill(1, 12, 0);

        foreach ($revenues as $revenue) {
            $monthlyData[$revenue->month] = $revenue->total;
        }

        return [
            'labels' => ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin', 'Juil', 'Août', 'Sep', 'Oct', 'Nov', 'Déc'],
            'data' => array_values($monthlyData)
        ];
    }

    private function getGlobalExpenseChartData($companyId, $exerciceId)
    {
        $expenses = EcritureComptable::where('ecriture_comptables.company_id', $companyId)
            ->where('ecriture_comptables.statut', 'approved')
            ->when($exerciceId, function($query) use ($exerciceId) {
                return $query->where('exercices_comptables_id', $exerciceId);
            })
            ->whereHas('planComptable', function($query) {
                $query->whereRaw('SUBSTRING(numero_de_compte, 1, 1) = ?', ['6']);
            })
            ->selectRaw('SUBSTRING(plan_comptables.numero_de_compte, 1, 2) as category, SUM(debit) as total')
            ->join('plan_comptables', 'ecriture_comptables.plan_comptable_id', '=', 'plan_comptables.id')
            ->groupBy('category')
            ->orderByDesc('total')
            ->limit(5)
            ->get();

        $categories = [
            '60' => 'Achats', '61' => 'Transports', '62' => 'Services extérieurs A',
            '63' => 'Services extérieurs B', '64' => 'Impôts et taxes', '65' => 'Autres charges',
            '66' => 'Charges de personnel', '67' => 'Charges financières', '68' => 'Dotations amortissements', '69' => 'Dotations provisions'
        ];

        $chartData = [];
        foreach ($expenses as $expense) {
            $category = $categories[$expense->category] ?? 'Autres';
            $chartData[] = [
                'category' => $category,
                'total' => $expense->total
            ];
        }

        return $chartData;
    }
}
