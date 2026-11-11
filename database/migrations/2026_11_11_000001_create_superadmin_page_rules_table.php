<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('superadmin_page_rules', function (Blueprint $table) {
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

    public function down(): void
    {
        Schema::dropIfExists('superadmin_page_rules');
    }
};
