<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('disponibilites', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medecin_id')->constrained('medecins')->cascadeOnDelete();
            $table->smallInteger('jour_semaine')->unsigned();
            $table->time('heure_debut');
            $table->time('heure_fin');
            $table->smallInteger('duree_creneau_min')->unsigned()->default(30);
            $table->timestamps();

            $table->index(['medecin_id', 'jour_semaine']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('disponibilites');
    }
};
