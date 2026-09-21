<?php

use Darvis\Mailtrap\Events\MailBlocked;
use Darvis\Mailtrap\Models\EmailValidation;
use Darvis\Mailtrap\Models\MailLog;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\Mailer\Exception\TransportException;

beforeEach(function (): void {
    config()->set('mail.default', 'array');
    config()->set('mail.mailers.array', ['transport' => 'array']);
    config()->set('mail.from', ['address' => 'sender@example.org', 'name' => 'Sender']);
});

/**
 * @return array<int, string>
 */
function sentRecipients(): array
{
    return app('mailer')->getSymfonyTransport()->messages()
        ->flatMap(fn ($sent) => array_map(fn ($address) => $address->getAddress(), $sent->getOriginalMessage()->getTo()))
        ->all();
}

it('sends and logs a mail without a subject', function (): void {
    EmailValidation::markAsValid('nosubject@example.org');

    Mail::raw('Body', fn ($message) => $message->to('nosubject@example.org'));

    expect(sentRecipients())->toBe(['nosubject@example.org'])
        ->and(MailLog::where('recipient', 'nosubject@example.org')->value('subject'))->toBe('')
        ->and(MailLog::where('recipient', 'nosubject@example.org')->value('status_code'))->toBe(MailLog::STATUS_SENT);
});

it('still sends the mail when the log cannot be written', function (): void {
    EmailValidation::markAsValid('nolog@example.org');
    Schema::drop('mail_logs');

    $reported = [];
    app(ExceptionHandler::class)->reportable(function (Throwable $e) use (&$reported): void {
        $reported[] = $e;
    });

    Mail::raw('Body', fn ($message) => $message->to('nolog@example.org')->subject('No log table'));

    expect(sentRecipients())->toBe(['nolog@example.org'])
        ->and($reported)->not->toBeEmpty();
});

it('still aborts a blocked send when the log cannot be written', function (): void {
    EmailValidation::markAsBlocked('dead@example.org', 'Manually blocked');
    Schema::drop('mail_logs');

    expect(fn () => Mail::raw('Body', fn ($message) => $message->to('dead@example.org')->subject('Blocked')))
        ->toThrow(TransportException::class, 'Email address dead@example.org is blocked: Manually blocked');

    expect(sentRecipients())->toBe([]);
});

it('leaves no pending rows when a later recipient is blocked', function (): void {
    Event::fake([MailBlocked::class]);
    EmailValidation::markAsValid('first@example.org');
    EmailValidation::markAsBlocked('dead@example.org', 'Manually blocked');
    EmailValidation::markAsValid('last@example.org');

    expect(fn () => Mail::raw('Body', function ($message): void {
        $message->to(['first@example.org', 'dead@example.org', 'last@example.org'])->subject('Partly blocked');
    }))->toThrow(TransportException::class, 'Email address dead@example.org is blocked: Manually blocked');

    expect(MailLog::pending()->count())->toBe(0)
        ->and(MailLog::count())->toBe(3)
        ->and(MailLog::blocked()->count())->toBe(3)
        ->and(MailLog::where('recipient', 'dead@example.org')->value('error_message'))->toBe('Manually blocked')
        ->and(MailLog::where('recipient', 'first@example.org')->value('error_message'))
        ->toBe('Not sent: dead@example.org is blocked (Manually blocked)')
        ->and(MailLog::where('recipient', 'last@example.org')->value('error_message'))
        ->toBe('Not sent: dead@example.org is blocked (Manually blocked)')
        ->and(MailLog::query()->distinct()->pluck('message_id'))->toHaveCount(1);

    Event::assertDispatchedTimes(MailBlocked::class, 1);
});

it('writes no rows for the other recipients when log_failed is off', function (): void {
    config()->set('manta_mailtrap.logging.log_failed', false);
    EmailValidation::markAsValid('first@example.org');
    EmailValidation::markAsBlocked('dead@example.org', 'Manually blocked');

    expect(fn () => Mail::raw('Body', function ($message): void {
        $message->to(['first@example.org', 'dead@example.org'])->subject('Partly blocked');
    }))->toThrow(TransportException::class);

    expect(MailLog::count())->toBe(0);
});

it('records the file and line of the code that sent a blocked mail', function (): void {
    EmailValidation::markAsBlocked('dead@example.org', 'Manually blocked');

    try {
        $line = __LINE__ + 1;
        Mail::raw('Body', fn ($message) => $message->to('dead@example.org')->subject('Source'));
    } catch (TransportException) {
        // Expected.
    }

    $log = MailLog::where('recipient', 'dead@example.org')->firstOrFail();

    expect($log->source_file)->toEndWith('tests/MailLoggingRobustnessTest.php')
        ->and($log->source_file)->not->toContain('vendor')
        ->and($log->source_line)->toBe($line);
});

it('keeps the source empty when no frame outside vendor and the package exists', function (): void {
    $source = (fn (array $backtrace): array => static::callerOutsidePackage($backtrace))->bindTo(null, MailLog::class)([
        ['file' => base_path('vendor/laravel/framework/src/Illuminate/Events/Dispatcher.php'), 'line' => 496],
        ['file' => dirname(__DIR__).'/src/Providers/MailServiceProvider.php', 'line' => 70],
        ['file' => base_path('artisan'), 'line' => 13],
    ]);

    expect($source)->toBe([null, null]);
});
