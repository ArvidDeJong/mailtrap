<?php

namespace Darvis\Mailtrap\Models;

use Illuminate\Database\Eloquent\Model;

class MailLog extends Model
{
    protected $fillable = [
        'message_id',
        'sender',
        'recipient',
        'subject',
        'status_code',
        'error_message',
        'source_file',
        'source_line',
        'type',
        'model',
        'model_id'
    ];

    protected $casts = [
        'model_id' => 'integer',
        'source_line' => 'integer'
    ];

    /**
     * Create a MailLog entry with automatic source file and line tracking
     *
     * @param array $data The MailLog data
     * @return static
     */
    public static function createWithSource(array $data): static
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = $backtrace[1] ?? $backtrace[0];

        $sourceFile = $caller['file'] ?? null;
        if ($sourceFile) {
            // Store relative path from base_path
            $basePath = base_path() . DIRECTORY_SEPARATOR;
            if (str_starts_with($sourceFile, $basePath)) {
                $sourceFile = substr($sourceFile, strlen($basePath));
            }
        }

        $data['source_file'] = $sourceFile;
        $data['source_line'] = $caller['line'] ?? null;

        // Generate unique message_id if not provided
        if (empty($data['message_id'])) {
            $prefix = match (true) {
                ($data['status_code'] ?? null) === 400 => 'VALIDATION_ERROR_',
                ($data['status_code'] ?? null) === 500 => 'TRANSPORT_ERROR_',
                ($data['status_code'] ?? null) === 550 => 'BLOCKED_',
                default => 'ERROR_',
            };
            $data['message_id'] = $prefix . uniqid();
        }

        return static::create($data);
    }

    /**
     * Scope voor succesvolle emails
     */
    public function scopeSuccessful($query)
    {
        return $query->where('status_code', '200');
    }

    /**
     * Scope voor gefaalde emails
     */
    public function scopeFailed($query)
    {
        return $query->where('status_code', '!=', '200')
                    ->whereNotNull('status_code');
    }

    /**
     * Scope voor emails van een specifiek model
     */
    public function scopeForModel($query, string $model, int $modelId = null)
    {
        $query->where('model', $model);
        
        if ($modelId) {
            $query->where('model_id', $modelId);
        }
        
        return $query;
    }

    /**
     * Scope voor emails naar een specifieke ontvanger
     */
    public function scopeToRecipient($query, string $email)
    {
        return $query->where('recipient', $email);
    }

    /**
     * Scope voor emails van een specifieke afzender
     */
    public function scopeFromSender($query, string $email)
    {
        return $query->where('sender', $email);
    }
}
