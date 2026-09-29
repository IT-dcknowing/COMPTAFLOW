<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Une entreprise supprimée part en corbeille, pas au néant.
 *
 * La suppression emportait tout, sans retour possible. Une erreur de clic
 * coûtait une comptabilité entière. L'entreprise est désormais marquée
 * supprimée : elle disparaît de toutes les listes, sa comptabilité reste
 * intacte, et trente jours durant on peut la remettre en place.
 *
 * Passé ce délai, la purge l'efface pour de bon.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->softDeletes();
            $table->index('deleted_at', 'companies_deleted_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropIndex('companies_deleted_at_index');
            $table->dropSoftDeletes();
        });
    }
};
