<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * 
     * This migration makes message_id and sender nullable to support
     * logging blocked emails that were never sent (no message_id available).
     */
    public function up(): void
    {
        if (Schema::hasTable('mail_logs')) {
            Schema::table('mail_logs', function (Blueprint $table) {
                // Make message_id nullable for blocked emails
                if (Schema::hasColumn('mail_logs', 'message_id')) {
                    $table->string('message_id')->nullable()->change();
                }
                
                // Make sender nullable for blocked emails
                if (Schema::hasColumn('mail_logs', 'sender')) {
                    $table->string('sender')->nullable()->change();
                }
            });

            // Drop unique constraint if it exists (check first to avoid error)
            $uniqueExists = collect(
                \Illuminate\Support\Facades\DB::select("SHOW INDEX FROM mail_logs WHERE Key_name = 'mail_logs_message_id_unique'")
            )->isNotEmpty();

            if ($uniqueExists) {
                Schema::table('mail_logs', function (Blueprint $table) {
                    $table->dropUnique('mail_logs_message_id_unique');
                });
            }

            // Add index if it doesn't exist (Laravel 11+ compatible)
            $indexExists = collect(
                \Illuminate\Support\Facades\DB::select("SHOW INDEX FROM mail_logs WHERE Key_name = 'mail_logs_message_id_index'")
            )->isNotEmpty();

            if (!$indexExists) {
                Schema::table('mail_logs', function (Blueprint $table) {
                    $table->index('message_id', 'mail_logs_message_id_index');
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('mail_logs')) {
            Schema::table('mail_logs', function (Blueprint $table) {
                // Revert to non-nullable (will fail if null values exist)
                if (Schema::hasColumn('mail_logs', 'message_id')) {
                    $table->string('message_id')->nullable(false)->change();
                }
                
                if (Schema::hasColumn('mail_logs', 'sender')) {
                    $table->string('sender')->nullable(false)->change();
                }
            });
        }
    }
};
