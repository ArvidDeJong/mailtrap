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
        if (! Schema::hasTable('mail_logs')) {
            return;
        }

        Schema::table('mail_logs', function (Blueprint $table): void {
            // Make message_id nullable for blocked emails
            if (Schema::hasColumn('mail_logs', 'message_id')) {
                $table->string('message_id')->nullable()->change();
            }

            // Make sender nullable for blocked emails
            if (Schema::hasColumn('mail_logs', 'sender')) {
                $table->string('sender')->nullable()->change();
            }
        });

        /**
         * Schema::getIndexes() works on every driver Laravel supports. The
         * earlier "SHOW INDEX FROM" was MySQL only and broke the migration on
         * SQLite, which is what most host applications run their tests on.
         */
        $indexes = collect(Schema::getIndexes('mail_logs'))
            ->pluck('name')
            ->filter()
            ->map(fn (string $name): string => strtolower($name));

        if ($indexes->contains('mail_logs_message_id_unique')) {
            Schema::table('mail_logs', function (Blueprint $table): void {
                $table->dropUnique('mail_logs_message_id_unique');
            });
        }

        if (! $indexes->contains('mail_logs_message_id_index')) {
            Schema::table('mail_logs', function (Blueprint $table): void {
                $table->index('message_id', 'mail_logs_message_id_index');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('mail_logs')) {
            return;
        }

        Schema::table('mail_logs', function (Blueprint $table): void {
            // Revert to non-nullable (will fail if null values exist)
            if (Schema::hasColumn('mail_logs', 'message_id')) {
                $table->string('message_id')->nullable(false)->change();
            }

            if (Schema::hasColumn('mail_logs', 'sender')) {
                $table->string('sender')->nullable(false)->change();
            }
        });
    }
};
