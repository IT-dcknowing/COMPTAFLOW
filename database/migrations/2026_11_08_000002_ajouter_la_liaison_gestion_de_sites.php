<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Database\\Schema\\Blueprint;
use Illuminate\\Support\\Facades\\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            if (!Schema::hasColumn('companies', 'gestionsites_company_id')) {
                $table->unsignedBigInteger('gestionsites_company_id')->nullable()->unique();
            }
            if (!Schema::hasColumn('companies', 'gestionsites_sync_key_hash')) {
                $table->string('gestionsites_sync_key_hash', 64)->nullable()->unique();
            }
            if (!Schema::hasColumn('companies', 'gestionsites_sync_key_chiffree')) {
                $table->text('gestionsites_sync_key_chiffree')->nullable();
            }
            if (!Schema::hasColumn('companies', 'gestionsites_sync_key_revoked_at')) {
                $table->timestamp('gestionsites_sync_key_revoked_at')->nullable();
            }
            if (!Schema::hasColumn('companies', 'gestionsites_linked_at')) {
                $table->timestamp('gestionsites_linked_at')->nullable();
            }
            if (!Schema::hasColumn('companies', 'gestionsites_last_deposit_at')) {
                $table->timestamp('gestionsites_last_deposit_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $columns = [
                'gestionsites_company_id',
                'gestionsites_sync_key_hash',
                'gestionsites_sync_key_chiffree',
                'gestionsites_sync_key_revoked_at',
                'gestionsites_linked_at',
                'gestionsites_last_deposit_at',
            ];
            foreach ($columns as $column) {
                if (Schema::hasColumn('companies', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
