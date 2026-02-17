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
        Schema::table('mail_logs', function (Blueprint $table) {
            if (!Schema::hasColumn('mail_logs', 'error_message')) {
                $table->text('error_message')->nullable()->after('status_code');
            }
            if (!Schema::hasColumn('mail_logs', 'source_file')) {
                $table->string('source_file')->nullable()->after('error_message');
            }
            if (!Schema::hasColumn('mail_logs', 'source_line')) {
                $table->unsignedInteger('source_line')->nullable()->after('source_file');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mail_logs', function (Blueprint $table) {
            $table->dropColumn(['error_message', 'source_file', 'source_line']);
        });
    }
};
