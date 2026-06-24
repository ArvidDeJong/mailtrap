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
        {email : Het e-mailadres waarnaar de testmail wordt verstuurd}
        {--mailer= : De te gebruiken mailer (standaard de actieve mail.default)}';

    /**
     * @var string
     */
    protected $description = 'Verstuur een testmail en rapporteer het resultaat. Geschikt als health-check in CI (exit-code 0 = ok).';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        $mailer = (string) ($this->option('mailer') ?: config('mail.default'));

        $beforeId = (int) (MailLog::max('id') ?? 0);

        $this->components->info("Testmail versturen naar {$email} via mailer '{$mailer}'…");

        try {
            Mail::mailer($mailer)->raw(
                'Mailtrap CLI testmail — '.now()->toDateTimeString().'.',
                function ($message) use ($email): void {
                    $message->to($email)
                        ->subject('Mailtrap CLI test '.now()->format('H:i:s'));
                }
            );
        } catch (\Throwable $e) {
            $this->components->error('Versturen mislukt: '.$e->getMessage());
            $this->renderLog($beforeId);

            return self::FAILURE;
        }

        $log = $this->latestLogSince($beforeId);

        if ($log === null) {
            $this->components->warn('Mail verstuurd, maar geen log gevonden (logging uitgeschakeld?).');

            return self::SUCCESS;
        }

        $this->renderLog($beforeId);

        // Een vastgelegde, niet-succesvolle status (bijv. geblokkeerd) telt als mislukt.
        if ($log->status_code !== null && (string) $log->status_code !== '200') {
            $this->components->error("Mail niet succesvol afgeleverd (status {$log->status_code}).");

            return self::FAILURE;
        }

        $this->components->info('Mail succesvol verstuurd.');

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

        $this->table(['Veld', 'Waarde'], [
            ['Message-ID', $log->message_id],
            ['Afzender', $log->sender],
            ['Ontvanger', $log->recipient],
            ['Onderwerp', $log->subject],
            ['Status', $log->status_code ?? 'in afwachting'],
            ['Foutmelding', $log->error_message ?? '—'],
        ]);
    }
}
