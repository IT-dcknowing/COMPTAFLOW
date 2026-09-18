<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index sur les numéros de saisie.
 *
 * `n_saisie` et `n_saisie_user` n'en avaient aucun, alors que l'application
 * les interroge sans arrêt : ouverture d'une écriture, suppression d'une
 * pièce, liste des écritures, attribution du prochain numéro. Chacune de ces
 * requêtes relisait toute la table. Sur un dossier de plusieurs dizaines de
 * milliers de lignes, la commande de réparation des numéros n'arrivait
 * simplement jamais à son terme.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecriture_comptables', function (Blueprint $table) {
            $table->index(['company_id', 'n_saisie'], 'ecr_company_n_saisie');
            $table->index(['company_id', 'n_saisie_user'], 'ecr_company_n_saisie_user');
        });
    }

    public function down(): void
    {
        Schema::table('ecriture_comptables', function (Blueprint $table) {
            $table->dropIndex('ecr_company_n_saisie');
            $table->dropIndex('ecr_company_n_saisie_user');
        });
    }
};
