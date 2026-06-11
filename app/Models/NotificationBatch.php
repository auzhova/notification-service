<?php

namespace App\Models;

use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use Eloquent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 *
 * @property string $id
 * @property string $idempotency_key
 * @property NotificationType $type
 * @property NotificationChannel $channel
 * @property string $message
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Notification> $notifications
 * @property-read int|null $notifications_count
 * @method static Builder<static>|NotificationBatch newModelQuery()
 * @method static Builder<static>|NotificationBatch newQuery()
 * @method static Builder<static>|NotificationBatch query()
 * @mixin Eloquent
 */
class NotificationBatch extends Model
{
    use HasUuids;
    use HasFactory;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
       'idempotency_key', 'type', 'channel', 'message'
    ];

    protected $casts = [
        'channel' => NotificationChannel::class,
        'type' => NotificationType::class,
    ];

    public function notifications(): HasMany
    {
        return $this->hasMany(Notification::class, 'batch_id');
    }
}
