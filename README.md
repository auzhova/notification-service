text
# Notification Service

Микросервис уведомлений с массовой рассылкой, приоритезацией, идемпотентностью и автоматическими повторами (retry).

---

## Технологии

- PHP 8.2 + Laravel 12
- PostgreSQL 15
- Redis (кэш идемпотентности)
- RabbitMQ (приоритетная очередь)
- Docker & Docker Compose
- Swagger UI (документация API)

---

## Быстрый старт

Все команды централизованы в **Makefile**.

```
1. Полная установка проекта (сборка, запуск, миграции, сидеры)
make install

2. Запустить воркер (в отдельном окне терминала)
make queue-work
```

После успешного запуска сервисы будут доступны:

Сервис	URL

```
API	http://localhost:8000
Swagger UI	http://localhost:8080
RabbitMQ Management	http://localhost:15672 (guest/guest)
```

## API
### Массовая рассылка
POST /api/notifications/send

Обязательный заголовок: Idempotency-Key: <уникальный ключ>

Тело запроса (JSON):

```
json
{
  "type": "transactional",
  "channel": "email",
  "message": "Текст уведомления",
  "recipients": ["user@ex.com", "+71234567890"]
}
Ответ (202 Accepted):
```

```
json
{
  "batch_id": "019eb2...",
  "message": "Рассылка принята"
}
```

### История уведомлений
GET /api/notifications/{recipient}/history

Пример:
GET /api/notifications/user@ex.com/history

Ответ – пагинированный список с полями:

```
json
{
  "data": [
    {
      "id": 1,
      "batch_id": "019eb2...",
      "channel": "email",
      "message": "Текст",
      "status": "delivered",
      "status_label": "Доставлено",
      "priority": 10,
      "sent_at": "2025-...",
      "delivered_at": "2025-...",
      "created_at": "2025-..."
    }
  ],
  "current_page": 1,
  "total": 1
}
```

## Архитектура и ключевые решения
### Приоритезация очереди
Очередь RabbitMQ создаётся с параметром x-max-priority=10.
Транзакционные уведомления получают приоритет 10 и обрабатываются раньше маркетинговых (приоритет 1).

### Идемпотентность
Заголовок Idempotency-Key проверяется сначала в Redis (TTL 1 час), затем в БД.
Повторный запрос возвращает тот же batch_id без повторной обработки.

## Exactly‑once (атомарная блокировка)
Перед отправкой каждого уведомления выполняется атомарный UPDATE:

```
sql
UPDATE notifications 
SET processing_locked_at = NOW()
WHERE id = ? AND status = 'queued' AND processing_locked_at IS NULL;
```
Это гарантирует, что только один воркер обработает конкретное уведомление.

### Retry‑механизм
При временной ошибке провайдера задача автоматически повторяется с задержками:
$backoff = [5, 15, 30] (секунд). После 3 неудачных попыток статус меняется на failed.

### Денормализация
Поля channel и message дублируются в таблице notifications – это устраняет лишние JOIN‑запросы при обработке очереди и в истории.

### Заглушки провайдеров
Классы SmsProviderStub и EmailProviderStub эмулируют внешние шлюзы. В тестах можно задать isSuccess = false для симуляции ошибки.

## Тестирование
Запуск всех интеграционных тестов:

```
bash
make test
```
Проверяемые сценарии:
 - успешная рассылка и смена статуса на Отправлено
 - идемпотентность (через Redis и БД)
 - ошибка провайдера и переход в failed после 3 попыток
 - приоритеты (10 для transactional, 1 для marketing)
 - история уведомлений (структура ответа, пагинация)

## Команды Makefile
Команда	Описание
make install	Полная установка: сборка, запуск, миграции, seed
make build	Собрать образы Docker
make up	Запустить все контейнеры
make down	Остановить контейнеры
make restart	Перезапустить контейнеры
make logs	Показать логи всех сервисов
make bash	Войти в контейнер app
make migrate	Выполнить миграции базы данных
make test	Запустить интеграционные тесты
make queue-work	Запустить воркер для обработки очереди
make rabbit-setup	Создать очередь notifications с поддержкой приоритетов

## Документация Swagger
После запуска контейнеров документация доступна по адресу: http://localhost:8080

Исходный файл спецификации: swagger/swagger.yaml

## Очистка
Остановить и удалить все контейнеры вместе с томами (база данных будет стёрта):

```
bash
docker-compose down -v
```
