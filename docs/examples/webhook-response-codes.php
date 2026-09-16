<?php

/**
 * Example of how the webhook controller handles Mailtrap's response_code.
 *
 * This file shows what MailtrapWebhookController does with the response_code
 * information in Mailtrap webhook events.
 */

// This is an example file - do not run it in production
// require_once __DIR__ . '/../vendor/autoload.php';

// Sample data as Mailtrap sends it
$webhookData = [
    'events' => [
        [
            'event' => 'bounce',
            'response' => '[CS01] Message rejected due to local policy',
            'response_code' => 555,
            'bounce_category' => 'spam',
            'category' => 'Password reset',
            'custom_variables' => [
                'variable_a' => 'value',
                'variable_b' => 'value2',
            ],
            'message_id' => '1df37d17-0286-4d8b-8edf-bc4ec5be86e6',
            'email' => 'receiver@example.com',
            'event_id' => 'bede7236-2284-43d6-a953-1fdcafd0fdbc',
            'timestamp' => 1733497282,
            'sending_domain_name' => 'examplesender.com',
            'sending_stream' => 'transactional',
        ],
        [
            'event' => 'bounce',
            'response' => '5.5.1 User Unknown',
            'response_code' => 550,
            'bounce_category' => 'badrecipient',
            'category' => 'Email confirmation',
            'custom_variables' => [
                'foo' => 'bar',
                'baz' => 123,
            ],
            'message_id' => 'ca7974af-7212-42aa-99fb-cc4742d0658b',
            'email' => 'another@example.com',
            'event_id' => '657b8544-6a95-4c47-997f-6e47922a5052',
            'timestamp' => 1733497341,
            'sending_domain_name' => 'examplesender.com',
            'sending_stream' => 'transactional',
        ],
        [
            'event' => 'delivery',
            'category' => 'Welcome email',
            'message_id' => 'test-delivery-123',
            'email' => 'success@example.com',
            'event_id' => 'delivery-test-456',
            'timestamp' => 1733497400,
            'sending_domain_name' => 'examplesender.com',
            'sending_stream' => 'transactional',
            // Delivery events carry no response_code
        ],
    ],
];

echo "Mailtrap webhook response code handling\n";
echo "=======================================\n\n";

echo "Sample data:\n";
echo json_encode($webhookData, JSON_PRETTY_PRINT)."\n\n";

echo "Expected results:\n";
echo "- receiver@example.com: status_code = 555, reason = '[CS01] Message rejected due to local policy'\n";
echo "- another@example.com: status_code = 550, reason = '5.5.1 User Unknown'\n";
echo "- success@example.com: status_code = 200 (default for delivery), reason = 'Email validated successfully'\n\n";

echo "The webhook controller will:\n";
echo "1. Read the response_code from the Mailtrap events\n";
echo "2. Use the response text as the reason (falling back to event.reason)\n";
echo "3. Use a default status_code of 200 for delivery events\n";
echo "4. Store everything in the email_validations table\n";
echo "5. Update the MailLog row matching the message_id and recipient with the status_code\n\n";

echo "MailLog updates:\n";
echo "- message_id '1df37d17-0286-4d8b-8edf-bc4ec5be86e6': status_code = 555 (bounce)\n";
echo "- message_id 'ca7974af-7212-42aa-99fb-cc4742d0658b': status_code = 550 (bounce)\n";
echo "- message_id 'test-delivery-123': status_code = 200 (delivery success)\n\n";

echo "Done. Check the logs and the database for the result.\n";
