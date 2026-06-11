<?php

namespace Database\Factories;

use App\Enums\NotificationChannel;
use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\NotificationBatch;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class NotificationBatchFactory extends Factory
{
    protected $model = NotificationBatch::class;

    public function definition(): array
    {
        return [
            'id' => (string) Str::uuid(),
            'idempotency_key' => $this->faker->uuid(),
            'type' => NotificationType::TRANSACTIONAL,
            'channel' => NotificationChannel::EMAIL,
            'message' => $this->faker->sentence(),
        ];
    }

    public function withNotifications(int $count = 1): static
    {
        return $this->afterCreating(function ($batch) use ($count) {
            Notification::factory()
                ->count($count)
                ->create([
                    'batch_id' => $batch->id,
                ]);
        });
    }
}
