<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $canonical = 'super_admin_page_rules';
        $legacy = 'superadmin_page_rules';

        // La première migration utilisait un nom sans underscore après "super".
        // Renommer la table si elle existe, sans perdre les règles déjà enregistrées.
        if (!Schema::hasTable($canonical) && Schema::hasTable($legacy)) {
            Schema::rename($legacy, $canonical);
            return;
        }

        if (!Schema::hasTable($canonical)) {
            Schema::create($canonical, function (Blueprint $table) {
                $table->id();
                $table->string('route_name', 190);
                $table->foreignId('company_id')->nullable()->constrained('companies')->cascadeOnDelete();
                $table->boolean('is_visible')->default(true);
                $table->text('reason')->nullable();
                $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->unique(['route_name', 'company_id']);
                $table->index(['company_id', 'is_visible']);
            });
        }
    }

    public function down(): void
    {
        // Migration corrective non destructive : on conserve les règles de visibilité.
    }
};
