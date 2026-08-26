Сервис массовых уведомлений с приоритезацией, дедубликацией и гарантированной доставкой.

## Технологии

- PHP 8.2 + Laravel 10
- PostgreSQL 15
- Redis 7 (дедубликация)
- RabbitMQ 3.12 (очереди)
- Docker Compose

## Быстрый старт

```bash
# Скопировать .env
cp .env.example .env

# Запустить контейнеры
docker-compose up -d

# Выполнить миграции
docker-compose exec app php artisan migrate

# Запустить воркер очередей
docker-compose exec app php artisan queue:work rabbitmq --queue=high_priority,marketing,default

# Объявить очереди RabbitMQ (создаются и автоматически при первой публикации,
# но явное объявление избавляет воркер от ошибок no queue в логах)
docker-compose exec app php artisan rabbitmq:queue-declare high_priority
docker-compose exec app php artisan rabbitmq:queue-declare marketing
docker-compose exec app php artisan rabbitmq:queue-declare default
```

> ⚠️ Очереди RabbitMQ хранятся в volume `rabbitmq_data`, но при первом старте
> (или после `docker compose down -v`) их нужно объявить заново командой выше.

### Реализовано тестирование основных сценариев:
```bash
docker-compose exec app php artisan test tests/Feature/NotificationTest.php
```

### После запуска проекта Swagger документация доступна по адресу:
```bash
Важно! Необходимо выполнить команду для генерации документации:
docker-compose exec app php artisan l5-swagger:generate
```
http://localhost:8080/api/documentation

Документация содержит:

POST /api/notifications/send — массовая отправка уведомлений

GET /api/subscribers/{subscriber_id}/notifications — история уведомлений подписчика

### Примеры запросов

Отправка уведомлений
```bash
curl -X POST http://localhost:8080/api/notifications/send \
  -H "Content-Type: application/json" \
  -d '{
    "channel": "sms",
    "message": "Hello!",
    "recipient_ids": ["user1", "user2"],
    "priority": "high",
    "idempotency_key": "unique-key-123"
  }'
```

> ⚠️ `idempotency_key` уникален для пары «ключ + получатель» и действует вечно
> (unique-индекс в БД). Повторный запрос с тем же ключом вернёт 202, но все
> получатели будут пропущены (`"skipped": 2, "created": 0`) — для повторного
> теста используйте новый ключ или опустите его.

Получение истории
```bash
curl http://localhost:8080/api/subscribers/user1/notifications
```
