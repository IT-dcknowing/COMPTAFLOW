<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Le mail d'une société devient facultatif, pour de bon.
 *
 * Le formulaire l'annonçait « facultatif », la validation l'acceptait vide —
 * mais la colonne, elle, le refusait. Créer une entreprise sans adresse
 * échouait donc sur une erreur SQL brute, affichée telle quelle.
 *
 * L'unicité reste : deux sociétés ne peuvent pas partager une adresse. Mais
 * plusieurs peuvent n'en avoir aucune, une contrainte unique laissant passer
 * autant de valeurs nulles qu'on veut.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('email_adresse', 191)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('email_adresse', 191)->nullable(false)->change();
        });
    }
};
