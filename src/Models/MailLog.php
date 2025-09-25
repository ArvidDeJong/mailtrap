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
        'type',
        'model',
        'model_id'
    ];

    protected $casts = [
        'model_id' => 'integer'
    ];

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
