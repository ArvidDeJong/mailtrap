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
        if (!Schema::hasTable('email_validations')) {
            Schema::create('email_validations', function (Blueprint $table) {
                $table->id();
                $table->string('email')->nullable();
                $table->string('domain')->nullable();
                $table->enum('status', ['valid', 'invalid', 'blocked']);
                $table->string('reason');
                $table->string('status_code')->nullable();
                $table->timestamp('last_checked_at')->useCurrent()->useCurrentOnUpdate();
                $table->timestamps();
                
                // Unique constraint op email en domain combinatie
                $table->unique(['email', 'domain'], 'email_blacklist_email_domain_unique');
                
                // Indexes voor betere performance
                $table->index('email');
                $table->index('domain');
                $table->index('status');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_validations');
    }
};
