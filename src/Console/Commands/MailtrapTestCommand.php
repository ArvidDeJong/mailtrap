<?php

namespace Darvis\Mailtrap\Console\Commands;

use Darvis\Mailtrap\Models\MailLog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class MailtrapTestCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'mailtrap:test
        {email : The address to send the test mail to}
        {--mailer= : The mailer to use (defaults to mail.default)}';

    /**
     * @var string
     */
    protected $description = 'Send a test mail and report the result. Suitable as a CI health check (exit code 0 = ok).';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $mailer = (string) ($this->option('mailer') ?: config('mail.default'));

        $beforeId = (int) (MailLog::max('id') ?? 0);

        $this->components->info("Sending test mail to {$email} through mailer '{$mailer}'…");

        try {
            Mail::mailer($mailer)->raw(
                'Mailtrap CLI test mail — '.now()->toDateTimeString().'.',
                function ($message) use ($email): void {
                    $message->to($email)
                        ->subject('Mailtrap CLI test '.now()->format('H:i:s'));
                }
            );
        } catch (\Throwable $e) {
            $this->components->error('Sending failed: '.$e->getMessage());
            $this->renderLog($beforeId);

            return self::FAILURE;
        }

        $log = $this->latestLogSince($beforeId);

        if ($log === null) {
            $this->components->warn('Mail sent, but no log found (is logging disabled?).');

            return self::SUCCESS;
        }

        $this->renderLog($beforeId);

        // A recorded status other than success (e.g. blocked) counts as a failure.
        if ($log->status_code !== null && (string) $log->status_code !== MailLog::STATUS_SENT) {
            $this->components->error("Mail was not delivered successfully (status {$log->status_code}).");

            return self::FAILURE;
        }

        $this->components->info('Mail sent successfully.');

        return self::SUCCESS;
    }

    protected function latestLogSince(int $beforeId): ?MailLog
    {
        return MailLog::where('id', '>', $beforeId)->latest('id')->first();
    }

    protected function renderLog(int $beforeId): void
    {
        $log = $this->latestLogSince($beforeId);

        if ($log === null) {
            return;
        }

        $this->table(['Field', 'Value'], [
            ['Message-ID', $log->message_id],
            ['Sender', $log->sender],
            ['Recipient', $log->recipient],
            ['Subject', $log->subject],
            ['Status', $log->status_code ?? 'pending'],
            ['Error', $log->error_message ?? '—'],
        ]);
    }
}
