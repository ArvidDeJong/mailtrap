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
        // Controleer of de tabel al bestaat
        if (!Schema::hasTable('mail_logs')) {
            Schema::create('mail_logs', function (Blueprint $table) {
                $table->id();
                $table->string('message_id');
                $table->string('sender');
                $table->string('recipient');
                $table->string('subject');
                $table->string('status_code')->nullable();
                $table->timestamps();
                $table->string('type')->nullable();
                $table->string('model')->nullable();
                $table->unsignedBigInteger('model_id')->nullable();
                
                // Unique constraint op message_id
                $table->unique('message_id', 'mail_logs_message_id_unique');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mail_logs');
    }
};
