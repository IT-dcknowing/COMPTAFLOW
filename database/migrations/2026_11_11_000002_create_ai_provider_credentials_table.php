<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('ai_provider_credentials', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 120);
            $table->string('model', 160);
            $table->text('api_key');
            $table->boolean('is_active')->default(true);
            $table->text('notes')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['provider', 'model']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_provider_credentials');
    }
};
