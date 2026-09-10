<?php

use Darvis\Mailtrap\Models\MailLog;
use Illuminate\Support\Facades\Schema;

it('runs every migration on a driver without SHOW INDEX support', function (): void {
    // TestCase already ran them against SQLite; reaching this point is the point.
    // Guards against reintroducing driver-specific SQL such as "SHOW INDEX",
    // which parses on MySQL only and aborts the migration everywhere else.
    expect(Schema::hasTable('mail_logs'))->toBeTrue()
        ->and(Schema::hasTable('email_validations'))->toBeTrue();
});

it('leaves message_id indexed but not unique', function (): void {
    $indexes = collect(Schema::getIndexes('mail_logs'))
        ->pluck('name')
        ->filter()
        ->map(fn (string $name): string => strtolower($name));

    expect($indexes)->toContain('mail_logs_message_id_index')
        ->and($indexes)->not->toContain('mail_logs_message_id_unique');
});

it('allows a blocked mail without a message id or sender', function (): void {
    $log = MailLog::create([
        'recipient' => 'blocked@example.test',
        'subject' => 'Blocked before sending',
        'status_code' => '550',
    ]);

    expect($log->message_id)->toBeNull()
        ->and($log->sender)->toBeNull();
});
