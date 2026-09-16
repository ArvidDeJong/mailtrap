<?php

namespace Darvis\Mailtrap\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One row per recipient of an outgoing mail, kept up to date by Mailtrap webhook events.
 *
 * `status_code` is null while a mail is pending, "200" once sent and an SMTP-style
 * code (such as "550" for a blocked address) when it failed.
 *
 * @property int $id
 * @property string|null $message_id
 * @property string|null $sender
 * @property string $recipient
 * @property string $subject
 * @property string|null $status_code
 * @property string|null $error_message
 * @property string|null $source_file
 * @property int|null $source_line
 * @property string|null $type Value of the X-Mail-Type header, or "webhook".
 * @property string|null $model Value of the X-Mail-Model header: a class name or morph alias.
 * @property int|null $model_id Value of the X-Mail-Model-ID header.
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Model|null $related
 *
 * @method static Builder<static> successful()
 * @method static Builder<static> failed()
 * @method static Builder<static> pending()
 * @method static Builder<static> blocked()
 * @method static Builder<static> forModel(string $model, ?int $modelId = null)
 * @method static Builder<static> toRecipient(string $email)
 * @method static Builder<static> fromSender(string $email)
 */
class MailLog extends Model
{
    use MassPrunable;

    public const STATUS_SENT = '200';

    public const STATUS_BLOCKED = '550';

    /**
     * Header through which Mailtrap accepts custom variables, and the variable
     * that carries the log's message id back in webhook events.
     */
    public const CUSTOM_VARIABLES_HEADER = 'X-MT-Custom-Variables';

    public const CUSTOM_VARIABLE = 'x_message_id';

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
        'model_id',
    ];

    protected $casts = [
        'model_id' => 'integer',
        'source_line' => 'integer',
    ];

    /**
     * Create a MailLog entry with automatic source file and line tracking
     *
     * @param  array<string, mixed>  $data  The MailLog data
     */
    public static function createWithSource(array $data): static
    {
        $backtrace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $caller = $backtrace[1] ?? $backtrace[0];

        $sourceFile = $caller['file'] ?? null;
        if ($sourceFile) {
            // Store relative path from base_path
            $basePath = base_path().DIRECTORY_SEPARATOR;
            if (str_starts_with($sourceFile, $basePath)) {
                $sourceFile = substr($sourceFile, strlen($basePath));
            }
        }

        $data['source_file'] = $sourceFile;
        $data['source_line'] = $caller['line'] ?? null;

        // Generate unique message_id if not provided
        if (empty($data['message_id'])) {
            $prefix = match ((int) ($data['status_code'] ?? 0)) {
                400 => 'VALIDATION_ERROR_',
                500 => 'TRANSPORT_ERROR_',
                550 => 'BLOCKED_',
                default => 'ERROR_',
            };
            $data['message_id'] = $prefix.uniqid();
        }

        return static::query()->create($data);
    }

    /**
     * The model the mail was about, from the X-Mail-Model and X-Mail-Model-ID headers.
     *
     * @return MorphTo<Model, $this>
     */
    public function related(): MorphTo
    {
        return $this->morphTo(__FUNCTION__, 'model', 'model_id');
    }

    /**
     * Logs older than logging.cleanup_after_days, for `php artisan model:prune`.
     *
     * @return Builder<self>
     */
    public function prunable(): Builder
    {
        $days = (int) config('manta_mailtrap.logging.cleanup_after_days', 30);

        return $days > 0
            ? static::where('created_at', '<', now()->subDays($days))
            : static::whereKey([]);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeSuccessful(Builder $query)
    {
        return $query->where('status_code', self::STATUS_SENT);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeFailed(Builder $query)
    {
        return $query->whereNotNull('status_code')
            ->where('status_code', '!=', self::STATUS_SENT);
    }

    /**
     * Mails handed to the mailer but not yet confirmed as sent.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('status_code');
    }

    /**
     * Mails stopped before sending because the recipient is blocked.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeBlocked(Builder $query): Builder
    {
        return $query->where('status_code', self::STATUS_BLOCKED);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeForModel(Builder $query, string $model, ?int $modelId = null)
    {
        return $query->where('model', $model)
            ->when($modelId !== null, fn (Builder $query) => $query->where('model_id', $modelId));
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeToRecipient(Builder $query, string $email)
    {
        return $query->where('recipient', $email);
    }

    /**
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    public function scopeFromSender(Builder $query, string $email)
    {
        return $query->where('sender', $email);
    }
}
