<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rendez_vous', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('users');
            $table->foreignId('medecin_id')->constrained('medecins');
            $table->timestamp('date_heure');
            $table->smallInteger('duree_min')->unsigned()->default(30);
            $table->enum('statut', ['en_attente', 'confirme', 'honore', 'annule', 'absent'])->default('en_attente');
            $table->string('motif', 255)->nullable();
            $table->foreignId('cree_par')->constrained('users');
            $table->timestamp('annule_le')->nullable();
            $table->string('motif_annulation', 255)->nullable();
            $table->timestamps();

            $table->unique(['medecin_id', 'date_heure']);
            $table->index('patient_id');
            $table->index('medecin_id');
            $table->index('date_heure');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rendez_vous');
    }
};
