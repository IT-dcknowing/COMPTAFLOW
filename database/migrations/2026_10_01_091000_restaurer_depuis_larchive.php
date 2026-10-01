<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Deux manques qui rendaient l'archive inutile.
 *
 * 1. On annonçait une donnée « récupérable trente jours », mais rien ne
 *    permettait de la récupérer : l'écran se contentait de montrer son
 *    contenu. `restored_at` note la remise en place, pour qu'on ne la rejoue
 *    pas et qu'on sache ce qui est revenu.
 *
 * 2. Une archive dont `expires_at` était resté vide n'était ni purgée
 *    (`expires_at <= maintenant` est faux pour un vide) ni affichée
 *    (`expires_at > maintenant` l'est aussi) : elle restait en base,
 *    invisible. L'écran pouvait donc paraître vide alors qu'il y avait tout.
 *    On leur donne la date qui leur manquait, à partir de leur suppression.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('archived_records', function (Blueprint $table) {
            $table->timestamp('restored_at')->nullable()->after('expires_at')->index();
        });

        // Les archives sans échéance redeviennent visibles : trente jours après
        // leur suppression, ou à défaut après leur enregistrement.
        DB::table('archived_records')->whereNull('expires_at')->update([
            'expires_at' => DB::raw(
                DB::getDriverName() === 'sqlite'
                    ? "datetime(COALESCE(deleted_at, created_at), '+30 days')"
                    : 'DATE_ADD(COALESCE(deleted_at, created_at), INTERVAL 30 DAY)'
            ),
        ]);
    }

    public function down(): void
    {
        Schema::table('archived_records', function (Blueprint $table) {
            $table->dropColumn('restored_at');
        });
    }
};
