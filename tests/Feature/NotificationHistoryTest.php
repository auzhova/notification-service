<?php

namespace Tests\Feature;

use App\Enums\NotificationChannel;
use App\Enums\NotificationStatus;
use App\Enums\NotificationType;
use App\Models\Notification;
use App\Models\NotificationBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotificationHistoryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Проверяет, что эндпоинт истории возвращает корректные данные уведомления
     * и правильную структуру пагинированного ответа.
     */
    #[Test]
    public function get_recipient_notification_history(): void
    {
        $batch = NotificationBatch::factory()->create([
            'idempotency_key' => 'history-test',
            'type' => NotificationType::TRANSACTIONAL,
            'channel' => NotificationChannel::EMAIL,
            'message' => 'History message',
        ]);

        $notification = Notification::factory()
            ->state([
                'batch_id' => $batch->id,
                'recipient' => 'history@example.com',
                'channel' => NotificationChannel::EMAIL,
                'message' => 'History message',
                'priority' => 10,
                'status' => NotificationStatus::DELIVERED,
                'sent_at' => now(),
                'delivered_at' => now(),
            ])
            ->create();

        // Проверяем, что уведомление действительно сохранено в БД
        $this->assertDatabaseHas('notifications', [
            'batch_id' => $batch->id,
            'recipient' => 'history@example.com',
            'priority' => 10,
        ]);

        // Вызываем историю для получателя history@example.com
        $response = $this->getJson('/api/notifications/history@example.com/history');
        $response->assertOk();

        // Проверяем структуру пагинированного ответа
        $response->assertJsonStructure([
            'current_page',
            'data' => [
                '*' => [
                    'id',
                    'batch_id',
                    'channel',
                    'message',
                    'status',
                    'status_label',
                    'priority',
                    'sent_at',
                    'delivered_at',
                    'created_at',
                ],
            ],
            'first_page_url',
            'from',
            'last_page',
            'last_page_url',
            'links',
            'next_page_url',
            'path',
            'per_page',
            'prev_page_url',
            'to',
            'total',
        ]);

        // Проверяем, что в ответе присутствует созданное уведомление
        $responseData = $response->json('data');
        $this->assertCount(1, $responseData);
        $this->assertEquals('History message', $responseData[0]['message']);
        $this->assertEquals(NotificationStatus::DELIVERED->value, $responseData[0]['status']);
        $this->assertEquals('Доставлено', $responseData[0]['status_label']);
        $this->assertEquals(10, $responseData[0]['priority']);
    }
}
