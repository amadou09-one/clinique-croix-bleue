<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('dossiers_medicaux', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('groupe_sanguin', 4)->nullable();
            $table->decimal('poids_kg', 5, 2)->nullable();
            $table->smallInteger('taille_cm')->nullable();
            $table->string('tension', 10)->nullable();
            $table->text('allergies')->nullable();
            $table->foreignId('maj_par')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('maj_le')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('dossiers_medicaux');
    }
};
