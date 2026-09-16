<?php

use Darvis\Mailtrap\Models\EmailValidation;
use Darvis\Mailtrap\Models\MailLog;

function mailLog(array $attributes = []): MailLog
{
    return MailLog::create($attributes + [
        'sender' => 'sender@example.org',
        'recipient' => 'recipient@example.org',
        'subject' => 'Subject',
    ]);
}

it('splits logs by status through its scopes', function (): void {
    mailLog(['status_code' => MailLog::STATUS_SENT]);
    mailLog(['status_code' => null]);
    mailLog(['status_code' => MailLog::STATUS_BLOCKED]);
    mailLog(['status_code' => '552']);

    expect(MailLog::successful()->count())->toBe(1)
        ->and(MailLog::pending()->count())->toBe(1)
        ->and(MailLog::blocked()->count())->toBe(1)
        ->and(MailLog::failed()->count())->toBe(2);
});

it('prunes logs older than the retention period', function (): void {
    config()->set('manta_mailtrap.logging.cleanup_after_days', 30);

    $old = mailLog();
    $old->forceFill(['created_at' => now()->subDays(31)])->save();
    mailLog();

    $this->artisan('model:prune', ['--model' => [MailLog::class]])->assertSuccessful();

    expect(MailLog::count())->toBe(1)
        ->and(MailLog::find($old->id))->toBeNull();
});

it('prunes nothing when the retention period is zero', function (): void {
    config()->set('manta_mailtrap.logging.cleanup_after_days', 0);

    mailLog()->forceFill(['created_at' => now()->subYears(2)])->save();

    expect((new MailLog)->prunable()->count())->toBe(0);
});

it('resolves the model named in the mail headers', function (): void {
    $validation = EmailValidation::markAsValid('related@example.org');

    $log = mailLog(['model' => EmailValidation::class, 'model_id' => $validation->id]);

    expect($log->related)->toBeInstanceOf(EmailValidation::class)
        ->and($log->related->is($validation))->toBeTrue();
});
