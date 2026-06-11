<?php

namespace Database\Factories;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Models\Notification;
use App\Models\NotificationBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'batch_id' => null,
            'recipient' => $this->faker->safeEmail(),
            'channel' => NotificationChannel::EMAIL,
            'message' => $this->faker->sentence(),
            'status' => NotificationStatus::QUEUED,
            'priority' => 1,
            'attempts' => 0,
            'processing_locked_at' => null,
        ];
    }

    public function queued(): static
    {
        return $this->state(fn () => [
            'status' => NotificationStatus::QUEUED,
        ]);
    }

    public function sent(): static
    {
        return $this->state(fn () => [
            'status' => NotificationStatus::SENT,
        ]);
    }

    public function failed(): static
    {
        return $this->state(fn () => [
            'status' => NotificationStatus::FAILED,
        ]);
    }

    public function forBatch(NotificationBatch $batch): static
    {
        return $this->state(fn () => [
            'batch_id' => $batch->id,
        ]);
    }
}
