<?php

namespace App\Traits;

use App\Models\CompteTresorerie;
use App\Models\PlanComptable;
use App\Models\TreasuryCategory;

trait HandlesTreasuryPosts
{
    /**
     * Logique centrale pour résoudre ou créer un poste de trésorerie par défaut
     */
    public function resolveTreasuryPost($companyId, $planComptableId)
    {
        if (empty($planComptableId)) return null;

        $account = PlanComptable::find($planComptableId);
        if (!$account || !str_starts_with($account->numero_de_compte, '5')) {
            return null;
        }

        // Tous les comptes de classe 5 ne sont pas des caisses ni des banques.
        // 58 est un compte de PASSAGE — les virements de fonds d'un compte a
        // l'autre — et 59 porte des depreciations. Ni l'un ni l'autre ne se
        // rapproche d'un releve : ils n'ont rien a faire dans la liste des
        // postes de tresorerie, ou ils apparaissaient sous le nom
        // « VIREMENT DE FONDS ».
        foreach (['58', '59'] as $prefixe) {
            if (str_starts_with($account->numero_de_compte, $prefixe)) {
                return null;
            }
        }

        // 1. Chercher par plan_comptable_id exact
        $poste = CompteTresorerie::where('company_id', $companyId)
            ->where('plan_comptable_id', $planComptableId)
            ->first();

        if ($poste) return $poste->id;

        // 2. Chercher par nom identique
        $posteByName = CompteTresorerie::where('company_id', $companyId)
            ->where('name', $account->intitule)
            ->first();
            
        if ($posteByName) {
            $posteByName->update(['plan_comptable_id' => $planComptableId]);
            return $posteByName->id;
        }

        // 3. Création automatique par défaut
        $defaultCategory = TreasuryCategory::where('company_id', $companyId)
            ->where('name', 'like', 'I.%')
            ->first() ?? TreasuryCategory::where('company_id', $companyId)->first();

        // Si aucune catégorie n'existe pour la compagnie, en chercher une globale ou créer une par défaut
        if (!$defaultCategory) {
            $defaultCategory = TreasuryCategory::first();
        }

        $newPost = CompteTresorerie::create([
            'company_id' => $companyId,
            'name' => $account->intitule,
            'type' => 'banque',
            'category_id' => $defaultCategory ? $defaultCategory->id : null,
            'plan_comptable_id' => $planComptableId,
            'solde_initial' => 0,
            'solde_actuel' => 0,
        ]);

        return $newPost->id;
    }
}
