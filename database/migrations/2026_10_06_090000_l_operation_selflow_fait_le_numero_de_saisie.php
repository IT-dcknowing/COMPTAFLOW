<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * L'opération Selflow fait le numéro de saisie.
 *
 * Le déversement rangeait `cle_selflow` — l'identité d'UNE LIGNE chez Selflow —
 * dans `n_saisie`. Chaque ligne devenait ainsi une pièce à elle seule : une
 * vente avec TVA et timbre arrivait en quatre pièces d'une ligne chacune,
 * aucune équilibrée, et la balance par pièce ne tombait jamais (constat du
 * propriétaire, 05/10/2026).
 *
 * `operation_selflow` retient l'opération d'origine. Toutes les lignes d'une
 * même opération partagent un seul `n_saisie`, attribué à la convention du
 * dossier — c'est lui qui fait le couple, pas la position dans la liste.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecriture_comptables', function (Blueprint $table) {
            if (!Schema::hasColumn('ecriture_comptables', 'operation_selflow')) {
                $table->string('operation_selflow', 64)->nullable()->after('cle_selflow');
                $table->index(['company_id', 'operation_selflow'], 'ecr_operation_selflow_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ecriture_comptables', function (Blueprint $table) {
            if (Schema::hasColumn('ecriture_comptables', 'operation_selflow')) {
                $table->dropIndex('ecr_operation_selflow_index');
                $table->dropColumn('operation_selflow');
            }
        });
    }
};
