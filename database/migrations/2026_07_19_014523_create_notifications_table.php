<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('rendez_vous_id')->nullable()->constrained('rendez_vous')->nullOnDelete();
            $table->enum('type', ['confirmation', 'rappel', 'annulation', 'info']);
            $table->enum('canal', ['email', 'sms', 'in_app']);
            $table->text('contenu');
            $table->timestamp('lu_le')->nullable();
            $table->timestamp('envoye_le')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
