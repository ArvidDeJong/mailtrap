<?php

/**
 * Voorbeeld van hoe Mailtrap webhook response_code verwerking werkt
 * 
 * Dit bestand toont de verwachte werking van de MailtrapWebhookController
 * bij het verwerken van response_code informatie uit Mailtrap webhooks.
 * 
 * @package Darvis\Mailtrap
 */

// Dit is een voorbeeld bestand - niet uitvoeren in productie
// require_once __DIR__ . '/../vendor/autoload.php';

// Simuleer de voorbeelddata van Mailtrap
$webhookData = [
    "events" => [
        [
            "event" => "bounce",
            "response" => "[CS01] Message rejected due to local policy",
            "response_code" => 555,
            "bounce_category" => "spam",
            "category" => "Password reset",
            "custom_variables" => [
                "variable_a" => "value",
                "variable_b" => "value2"
            ],
            "message_id" => "1df37d17-0286-4d8b-8edf-bc4ec5be86e6",
            "email" => "receiver@example.com",
            "event_id" => "bede7236-2284-43d6-a953-1fdcafd0fdbc",
            "timestamp" => 1733497282,
            "sending_domain_name" => "examplesender.com",
            "sending_stream" => "transactional"
        ],
        [
            "event" => "bounce",
            "response" => "5.5.1 User Unknown",
            "response_code" => 550,
            "bounce_category" => "badrecipient",
            "category" => "Email confirmation",
            "custom_variables" => [
                "foo" => "bar",
                "baz" => 123
            ],
            "message_id" => "ca7974af-7212-42aa-99fb-cc4742d0658b",
            "email" => "another@example.com",
            "event_id" => "657b8544-6a95-4c47-997f-6e47922a5052",
            "timestamp" => 1733497341,
            "sending_domain_name" => "examplesender.com",
            "sending_stream" => "transactional"
        ],
        [
            "event" => "delivery",
            "category" => "Welcome email",
            "message_id" => "test-delivery-123",
            "email" => "success@example.com",
            "event_id" => "delivery-test-456",
            "timestamp" => 1733497400,
            "sending_domain_name" => "examplesender.com",
            "sending_stream" => "transactional"
            // Geen response_code bij delivery events
        ]
    ]
];

echo "Test Mailtrap Webhook Response Code Verwerking\n";
echo "===============================================\n\n";

echo "Voorbeelddata:\n";
echo json_encode($webhookData, JSON_PRETTY_PRINT) . "\n\n";

echo "Verwachte resultaten:\n";
echo "- receiver@example.com: status_code = 555, reason = '[CS01] Message rejected due to local policy'\n";
echo "- another@example.com: status_code = 550, reason = '5.5.1 User Unknown'\n";
echo "- success@example.com: status_code = 200 (default voor delivery), reason = 'Email validated successfully'\n\n";

echo "De webhook controller zal nu:\n";
echo "1. De response_code uit de Mailtrap events extraheren\n";
echo "2. De response tekst gebruiken als reason (fallback naar event.reason)\n";
echo "3. Voor delivery events een default status_code van 200 gebruiken\n";
echo "4. Alle informatie opslaan in de email_validations tabel\n";
echo "5. MailLog records updaten met de juiste status_code op basis van message_id\n\n";

echo "MailLog updates:\n";
echo "- message_id '1df37d17-0286-4d8b-8edf-bc4ec5be86e6': status_code = 555 (bounce)\n";
echo "- message_id 'ca7974af-7212-42aa-99fb-cc4742d0658b': status_code = 550 (bounce)\n";
echo "- message_id 'test-delivery-123': status_code = 200 (delivery success)\n\n";

echo "Test voltooid! Controleer de logs en database voor de juiste verwerking.\n";
