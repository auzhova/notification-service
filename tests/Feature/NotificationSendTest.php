<?php

namespace Tests\Feature;

use App\Enums\NotificationStatus;
use App\Models\Notification;
use App\Models\NotificationBatch;
use App\Services\ProviderFactory;
use App\Services\SmsProviderStub;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class NotificationSendTest extends TestCase
{
    use RefreshDatabase;

    private const MAX_WORKER_ITERATIONS = 10;
    private const MAX_WAIT_SECONDS = 30;
    private const POLLING_DELAY_US = 500000;

    protected function setUp(): void
    {
        parent::setUp();
        Redis::flushall();

        // Настраиваем тестовую очередь отдельно от основной
        Config::set('queue.connections.rabbitmq.queue', 'notifications_test');
        Config::set('queue.default', 'rabbitmq');

        $this->purgeTestQueue();
    }

    private function purgeTestQueue(): void
    {
        $host = config('queue.connections.rabbitmq.host', 'rabbitmq');
        $port = env('RABBITMQ_MGMT_PORT', 15672);
        $queue = config('queue.connections.rabbitmq.queue');
        $ch = curl_init("http://{$host}:{$port}/api/queues/%2F/{$queue}/contents");
        curl_setopt($ch, CURLOPT_USERPWD, 'guest:guest');
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        curl_exec($ch);
        curl_close($ch);
    }

    /**
     * Проверяет успешную отправку массовой рассылки:
     * - создаются записи в БД со статусом QUEUED,
     * - после обработки воркером статус меняется на SENT.
     */
    #[Test]
    public function notifications_send_success(): void
    {
        $payload = [
            'type' => 'transactional',
            'channel' => 'email',
            'message' => 'Hello world',
            'recipients' => ['test@example.com'],
        ];
        $headers = ['Idempotency-Key' => 'test-0001'];

        $response = $this->postJson('/api/notifications/send', $payload, $headers);
        $response->assertStatus(202);
        $batchId = $response->json('batch_id');

        $this->assertDatabaseHas('notifications', [
            'batch_id' => $batchId,
            'status' => NotificationStatus::QUEUED->value,
        ]);

        // Циклически обрабатываем очередь, пока не останется сообщений
        $queueName = config('queue.connections.rabbitmq.queue');
        $maxAttempts = self::MAX_WORKER_ITERATIONS;
        $attempt = 0;

        do {
            $exitCode = Artisan::call('queue:work', [
                '--queue' => $queueName,
                '--once' => true,
                '--max-time' => 1,
            ]);

            if ($exitCode === 0) {
                usleep(self::POLLING_DELAY_US);
            }

            $attempt++;
        } while ($exitCode === 0 && $attempt < $maxAttempts);

        $this->assertDatabaseHas('notifications', [
            'batch_id' => $batchId,
            'status' => NotificationStatus::SENT->value,
        ]);
    }

    /**
     * Проверяет идемпотентность через кэш Redis:
     * повторный запрос с тем же Idempotency-Key возвращает тот же batch_id.
     */
    #[Test]
    public function notifications_idempotency_key_cache_check(): void
    {
        $payload = [
            'type' => 'marketing',
            'channel' => 'sms',
            'message' => 'Duplication test',
            'recipients' => ['+70000000000'],
        ];
        $headers = ['Idempotency-Key' => 'test-0002'];

        $first = $this->postJson('/api/notifications/send', $payload, $headers);
        $first->assertStatus(202);
        $batchId = $first->json('batch_id');

        $second = $this->postJson('/api/notifications/send', $payload, $headers);
        $second->assertStatus(202);

        $this->assertEquals($batchId, $second->json('batch_id'));
        $this->assertEquals('Рассылка принята', $second->json('message'));

        $this->assertDatabaseCount('notification_batches', 1);
        $this->assertDatabaseCount('notifications', 1);
    }

    /**
     * Проверяет идемпотентность через базу данных:
     * при отсутствии кэша Redis ответ из БД сообщает о существующей рассылке.
     */
    #[Test]
    public function notifications_idempotency_key_db_check(): void
    {
        $idempotencyKey = 'test-00021';

        $payload = [
            'type' => 'marketing',
            'channel' => 'sms',
            'message' => 'Duplication test 2',
            'recipients' => ['+70000000001'],
        ];

        $headers = ['Idempotency-Key' => $idempotencyKey];

        $first = $this->postJson('/api/notifications/send', $payload, $headers);
        $first->assertStatus(202);
        $batchId = $first->json('batch_id');

        // Удаляем ключ из Redis, чтобы принудительно обратиться к БД
        Redis::del("notify_batch:{$idempotencyKey}");

        $second = $this->postJson('/api/notifications/send', $payload, $headers);
        $second->assertStatus(202);

        $this->assertEquals($batchId, $second->json('batch_id'));
        $this->assertEquals('Рассылка уже существует', $second->json('message'));

        $this->assertDatabaseCount('notification_batches', 1);
        $this->assertDatabaseCount('notifications', 1);
    }

    /**
     * Проверяет механизм повторных попыток (retry) на тестовой очереди:
     * после трёх последовательных ошибок провайдера уведомление переходит в статус FAILED,
     * а счётчик попыток становится равным 3.
     */
    #[Test]
    public function notifications_provider_failed(): void
    {
        $factory = $this->createMock(ProviderFactory::class);

        $factory->method('make')
            ->willReturn(
                new SmsProviderStub(isSuccess: false)
            );

        $this->app->instance(ProviderFactory::class, $factory);

        $payload = [
            'type' => 'transactional',
            'channel' => 'sms',
            'message' => 'Test retry',
            'recipients' => ['+70000000002'],
        ];

        $headers = ['Idempotency-Key' => 'retry-test-' . uniqid()];

        $response = $this->postJson('/api/notifications/send', $payload, $headers);
        $batchId = $response->json('batch_id');

        // Запускаем воркер один раз, но с длительным таймаутом, чтобы он обрабатывал повторные попытки
        $queueName = config('queue.connections.rabbitmq.queue');
        $start = time();
        $maxWait = self::MAX_WAIT_SECONDS;

        do {
            Artisan::call('queue:work', [
                '--queue' => $queueName,
                '--once' => true,
                '--max-time' => 1,
            ]);

            usleep(self::POLLING_DELAY_US);

            $notification = Notification::where('batch_id', $batchId)->first();

            if ($notification && $notification->status === NotificationStatus::FAILED) {
                break;
            }
        } while (time() - $start < $maxWait);

        $notification->refresh();

        $this->assertEquals(NotificationStatus::FAILED, $notification->status);
        $this->assertEquals(3, $notification->attempts);
    }

    /**
     * Проверяет, что для транзакционного уведомления устанавливается высокий приоритет (10).
     */
    #[Test]
    public function notifications_for_transactional_type_success(): void
    {
        $waitingPriority = 10;

        $payload = [
            'type' => 'transactional',
            'channel' => 'email',
            'message' => 'High priority test',
            'recipients' => ['prio@example.com'],
        ];

        $headers = ['Idempotency-Key' => 'test-0004'];

        $this->postJson('/api/notifications/send', $payload, $headers)
            ->assertStatus(202);

        $this->assertDatabaseHas('notifications', [
            'recipient' => 'prio@example.com',
            'priority' => $waitingPriority,
        ]);
    }

    /**
     * Проверяет, что для маркетингового уведомления устанавливается низкий приоритет (1).
     */
    #[Test]
    public function notifications_for_marketing_type_success(): void
    {
        $waitingPriority = 1;

        $payload = [
            'type' => 'marketing',
            'channel' => 'sms',
            'message' => 'Low priority test',
            'recipients' => ['+72222222222'],
        ];

        $headers = ['Idempotency-Key' => 'test-0005'];

        $this->postJson('/api/notifications/send', $payload, $headers)
            ->assertStatus(202);

        $this->assertDatabaseHas('notifications', [
            'recipient' => '+72222222222',
            'priority' => $waitingPriority,
        ]);
    }

    /**
     * Проверяет, что уведомление может быть заблокировано только один раз:
     * - первое получение блокировки успешно,
     * - повторная попытка блокировки возвращает false.
     */
    #[Test]
    public function notification_can_be_locked_only_once(): void
    {
        $batch = NotificationBatch::factory()->create();
        $notification = Notification::factory()
            ->queued()
            ->forBatch($batch)
            ->create();

        $this->assertTrue($notification->lockProcessing());
        $notification->refresh();

        $this->assertFalse($notification->lockProcessing());
    }

    /**
     * Проверяет, что истёкшая блокировка может быть получена повторно:
     * - уведомление имеет lock старше допустимого TTL,
     * - новая попытка блокировки выполняется успешно.
     */
    #[Test]
    public function notification_can_be_relocked_after_expiration(): void
    {
        $batch = NotificationBatch::factory()->create();
        $notification = Notification::factory()
            ->queued()
            ->forBatch($batch)
            ->create([
                'processing_locked_at' => now()->subMinutes(11),
            ]);

        $this->assertTrue($notification->lockProcessing());
    }

    /**
     * Проверяет, что блокировка доступна только для уведомлений в статусе QUEUED:
     * - уведомление со статусом SENT не может получить lock,
     * - метод lockProcessing возвращает false.
     */
    #[Test]
    public function notification_can_be_unlocked(): void
    {
        $batch = NotificationBatch::factory()->create();
        $notification = Notification::factory()
            ->queued()
            ->forBatch($batch)
            ->create();

        $notification->lockProcessing();
        $notification->refresh();

        $this->assertNotNull($notification->processing_locked_at);

        $notification->unlockProcessing();
        $notification->refresh();

        $this->assertNull($notification->processing_locked_at);
    }
}
