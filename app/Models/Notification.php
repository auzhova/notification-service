<?php

namespace App\Models;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property integer $id
 * @property string $batch_id
 * @property string $recipient
 * @property NotificationChannel $channel
 * @property string $message
 * @property integer $priority
 * @property NotificationStatus $status
 * @property string|null $provider_message_id
 * @property integer $attempts
 * @property string|null $last_error
 * @property Carbon|null $sent_at
 * @property Carbon|null $delivered_at
 * @property Carbon|null $processing_locked_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @property-read NotificationBatch|null $batch
 * @method static Builder<static>|Notification newModelQuery()
 * @method static Builder<static>|Notification newQuery()
 * @method static Builder<static>|Notification query()
 * @mixin Eloquent
 */
class Notification extends Model
{
    use HasFactory;

    private const LOCK_TIMEOUT_MINUTES = 10;

    protected $fillable = [
        'batch_id', 'recipient', 'channel', 'message', 'priority', 'status', 'provider_message_id',
        'attempts', 'last_error', 'sent_at', 'delivered_at', 'processing_locked_at'
    ];

    protected $casts = [
        'channel' => NotificationChannel::class,
        'status' => NotificationStatus::class,
        'batch_id' => 'string',
        'sent_at' => 'datetime',
        'delivered_at' => 'datetime',
        'processing_locked_at' => 'datetime',
    ];

    public function markAsSent(string $providerMessageId): void
    {
        $this->update([
            'status' => NotificationStatus::SENT,
            'provider_message_id' => $providerMessageId,
            'sent_at' => now(),
            'last_error' => null,
        ]);
    }

    public function markAsDelivered(): void
    {
        $this->update([
            'status' => NotificationStatus::DELIVERED,
            'delivered_at' => now(),
            'last_error' => null,
        ]);
    }

    public function markAsFailed(string $error): void
    {
        $this->update([
            'status' => NotificationStatus::FAILED,
            'last_error' => $error,
        ]);
    }

    public function incrementAttempts(?string $error = null): void
    {
        $this->increment('attempts', 1, [
            'last_error' => $error,
        ]);

        $this->refresh();
    }

    public function lockProcessing(): bool
    {
        return static::query()
                ->whereKey($this->id)
                ->where('status', NotificationStatus::QUEUED)
                ->where(function ($query) {
                    $query->whereNull('processing_locked_at')
                        ->orWhere(
                            'processing_locked_at',
                            '<',
                            now()->subMinutes(
                                self::LOCK_TIMEOUT_MINUTES
                            )
                        );
                })
                ->update([
                    'processing_locked_at' => now(),
                ]) > 0;
    }

    public function unlockProcessing(): void
    {
        static::query()
            ->whereKey($this->id)
            ->update([
                'processing_locked_at' => null,
            ]);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(NotificationBatch::class, 'batch_id');
    }
}
