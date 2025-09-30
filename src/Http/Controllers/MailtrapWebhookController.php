<?php

namespace Darvis\Mailtrap\Http\Controllers;

use App\Http\Controllers\Controller;
use Darvis\Mailtrap\Models\EmailValidation;
use Darvis\Mailtrap\Models\MailLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class MailtrapWebhookController extends Controller
{
    /**
     * Verwerkt de inkomende webhook verzoeken van Mailtrap.
     *
     * Mailtrap stuurt events in batches (tot 500 per keer) elke 30 seconden.
     * Elke batch bevat een array van events met informatie over e-mailbezorging.
     *
     * @param Request $request
     * @return \Illuminate\Http\Response
     */
    public function handle(Request $request)
    {
        // Start de timer voor webhook verwerking
        $startTime = microtime(true);

        // Log de inkomende webhook voor debugging
        Log::info('Mailtrap webhook ontvangen', [
            'payload' => $request->all()
        ]);

        // Valideer dat we een geldig webhook verzoek hebben ontvangen
        if (!$request->has('events') || !is_array($request->input('events'))) {
            return response()->json([
                'status' => 'error',
                'message' => 'Geen geldige events gevonden in webhook data'
            ], 400);
        }

        $events = $request->input('events');
        $processedEmails = [];
        $validCount = 0;
        $invalidCount = 0;
        $skippedCount = 0;

        foreach ($events as $event) {
            // Controleer of het event de benodigde velden bevat
            if (!isset($event['email']) || !isset($event['event'])) {
                $skippedCount++;
                continue;
            }

            $email = $event['email'];
            $eventType = $event['event'];
            $messageId = $event['message_id'] ?? null;
            $category = $event['category'] ?? null;
            $timestamp = $event['timestamp'] ?? null;
            $sendingStream = $event['sending_stream'] ?? null;
            $responseCode = $event['response_code'] ?? null;
            $response = $event['response'] ?? null;

            // Voorkom dubbele verwerking van hetzelfde e-mailadres in dezelfde webhook call
            if (in_array($email, $processedEmails)) {
                $skippedCount++;
                continue;
            }

            $processedEmails[] = $email;

            try {
                // Verschillende eventTypes verwerken
                switch ($eventType) {
                    case 'delivery':
                        // Een succesvolle aflevering betekent dat het e-mailadres geldig is
                        // Bij delivery events stuurt Mailtrap geen response_code, dus gebruiken we 200
                        EmailValidation::markAsValidWithCode($email, $responseCode ?? 200);
                        
                        // Update MailLog met succesvolle status
                        if ($messageId) {
                            MailLog::where('message_id', $messageId)
                                ->update(['status_code' => $responseCode ?? 200]);
                        }
                        
                        Log::info("Email {$email} gemarkeerd als geldig via Mailtrap webhook (delivery event)", [
                            'message_id' => $messageId,
                            'category' => $category,
                            'timestamp' => $timestamp,
                            'sending_stream' => $sendingStream,
                            'response_code' => $responseCode ?? 200
                        ]);
                        $validCount++;
                        break;

                    case 'bounce':
                        // Hard bounce - e-mailadres bestaat niet of domein is ongeldig
                        $reason = $response ?? $event['reason'] ?? 'E-mail kon niet worden afgeleverd (bounce)';
                        EmailValidation::markAsInvalid($email, $reason, $responseCode ?? 550);
                        
                        // Update MailLog met bounce status
                        if ($messageId) {
                            MailLog::where('message_id', $messageId)
                                ->update(['status_code' => $responseCode ?? 550]);
                        }
                        
                        Log::info("Email {$email} gemarkeerd als ongeldig via Mailtrap webhook (bounce event)", [
                            'message_id' => $messageId,
                            'category' => $category,
                            'reason' => $reason,
                            'response' => $response,
                            'response_code' => $responseCode,
                            'bounce_category' => $event['bounce_category'] ?? null,
                            'timestamp' => $timestamp,
                            'sending_stream' => $sendingStream
                        ]);
                        $invalidCount++;
                        break;

                    case 'spam':
                        // E-mail is als spam gemarkeerd door ontvanger
                        $reason = $response ?? $event['reason'] ?? 'E-mail is als spam gemarkeerd';
                        EmailValidation::markAsInvalid($email, $reason, $responseCode ?? 400);
                        
                        // Update MailLog met spam status
                        if ($messageId) {
                            MailLog::where('message_id', $messageId)
                                ->update(['status_code' => $responseCode ?? 400]);
                        }
                        
                        Log::info("Email {$email} gemarkeerd als ongeldig via Mailtrap webhook (spam event)", [
                            'message_id' => $messageId,
                            'category' => $category,
                            'reason' => $reason,
                            'response' => $response,
                            'response_code' => $responseCode,
                            'timestamp' => $timestamp,
                            'sending_stream' => $sendingStream
                        ]);
                        $invalidCount++;
                        break;

                    case 'reject':
                        // E-mail is geweigerd door ontvanger of provider
                        $reason = $response ?? $event['reason'] ?? 'E-mail is geweigerd door ontvanger';
                        EmailValidation::markAsInvalid($email, $reason, $responseCode ?? 450);
                        
                        // Update MailLog met reject status
                        if ($messageId) {
                            MailLog::where('message_id', $messageId)
                                ->update(['status_code' => $responseCode ?? 450]);
                        }
                        
                        Log::info("Email {$email} gemarkeerd als ongeldig via Mailtrap webhook (reject event)", [
                            'message_id' => $messageId,
                            'category' => $category,
                            'reason' => $reason,
                            'response' => $response,
                            'response_code' => $responseCode,
                            'timestamp' => $timestamp,
                            'sending_stream' => $sendingStream
                        ]);
                        $invalidCount++;
                        break;

                    case 'open':
                    case 'click':
                        // Deze events betekenen impliciet dat het e-mailadres geldig is
                        // (gebruiker heeft e-mail geopend of op een link geklikt)
                        EmailValidation::markAsValidWithCode($email, $responseCode ?? 200);
                        
                        // Update MailLog met succesvolle status (open/click betekent succesvolle aflevering)
                        if ($messageId) {
                            MailLog::where('message_id', $messageId)
                                ->update(['status_code' => $responseCode ?? 200]);
                        }
                        
                        Log::info("Email {$email} gemarkeerd als geldig via Mailtrap webhook ({$eventType} event)", [
                            'message_id' => $messageId,
                            'category' => $category,
                            'timestamp' => $timestamp,
                            'sending_stream' => $sendingStream,
                            'response_code' => $responseCode ?? 200
                        ]);
                        $validCount++;
                        break;

                    // Andere events kunnen worden toegevoegd indien nodig
                    default:
                        // Voor andere events doen we niets met de e-mailvalidatie
                        Log::info("Mailtrap event {$eventType} ontvangen voor {$email}, geen actie ondernomen", [
                            'message_id' => $messageId,
                            'category' => $category,
                            'timestamp' => $timestamp,
                            'sending_stream' => $sendingStream
                        ]);
                        $skippedCount++;
                        break;
                }
            } catch (\Exception $e) {
                Log::error("Fout bij verwerken van Mailtrap webhook voor {$email}", [
                    'error' => $e->getMessage(),
                    'event_type' => $eventType,
                    'message_id' => $messageId,
                    'timestamp' => $timestamp,
                    'sending_stream' => $sendingStream
                ]);
                $skippedCount++;
            }
        }

        // Bereken de verwerkingstijd
        $processingTime = round((microtime(true) - $startTime) * 1000); // in milliseconds

        // Altijd een succesrespons terugsturen naar Mailtrap binnen de 30 seconden timeout
        return response()->json([
            'status' => 'success',
            'message' => 'Webhook verwerkt',
            'stats' => [
                'valid_emails' => $validCount,
                'invalid_emails' => $invalidCount,
                'skipped' => $skippedCount,
                'total_processed' => count($processedEmails),
                'total_events' => count($events),
                'processing_time_ms' => $processingTime
            ]
        ], 200);
    }
}
