<?php

namespace App\Http\Controllers\Docs;

use App\Http\Controllers\Controller;

/**
 * @OA\Info(
 *     title="Notification Service API",
 *     version="1.0.0",
 *     description="Сервис массовых уведомлений с приоритезацией",
 *     @OA\Contact(
 *         email="admin@example.com"
 *     )
 * )
 *
 * @OA\Server(
 *     url=L5_SWAGGER_CONST_HOST,
 *     description="API Server"
 * )
 *
 * @OA\Tag(
 *     name="Notifications",
 *     description="Отправка и управление уведомлениями"
 * )
 *
 * @OA\Tag(
 *     name="Subscribers",
 *     description="Информация о подписчиках"
 * )
 *
 * @OA\PathItem(
 *     path="/api/notifications/send"
 * )
 *
 * @OA\Post(
 *     path="/api/notifications/send",
 *     tags={"Notifications"},
 *     summary="Отправить массовое уведомление",
 *     @OA\RequestBody(
 *         required=true,
 *         @OA\JsonContent(
 *             ref="#/components/schemas/SendNotificationRequest",
 *             example={
 *                 "channel": "sms",
 *                 "message": "Hello!",
 *                 "recipient_ids": {"user1", "user2"},
 *                 "priority": "high",
 *                 "idempotency_key": "unique-key-123"
 *             }
 *         )
 *     ),
 *     @OA\Response(
 *         response=202,
 *         description="Уведомления поставлены в очередь",
 *         @OA\JsonContent(ref="#/components/schemas/SendNotificationResponse")
 *     ),
 *     @OA\Response(
 *         response=422,
 *         description="Ошибка валидации",
 *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
 *     )
 * )
 *
 * @OA\PathItem(
 *     path="/api/subscribers/{subscriberId}/notifications"
 * )
 *
 * @OA\Get(
 *     path="/api/subscribers/{subscriberId}/notifications",
 *     tags={"Subscribers"},
 *     summary="История уведомлений подписчика",
 *     @OA\Parameter(
 *         name="subscriberId",
 *         in="path",
 *         required=true,
 *         @OA\Schema(type="string", example="user1")
 *     ),
 *     @OA\Response(
 *         response=200,
 *         description="Список уведомлений",
 *         @OA\JsonContent(ref="#/components/schemas/HistoryResponse")
 *     ),
 *     @OA\Response(
 *         response=404,
 *         description="Подписчик не найден",
 *         @OA\JsonContent(ref="#/components/schemas/ErrorResponse")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="Notification",
 *     @OA\Property(property="id", type="string", format="uuid", example="a1cb9993-5bcd-460b-ace0-556f6a276a62"),
 *     @OA\Property(property="subscriber_id", type="string", example="user123"),
 *     @OA\Property(property="channel", type="string", enum={"sms","email"}, example="sms"),
 *     @OA\Property(property="message", type="string", example="Hello from API!"),
 *     @OA\Property(property="priority", type="string", enum={"high","low"}, example="high"),
 *     @OA\Property(property="status", type="string", enum={"queued","sent","delivered","failed"}, example="delivered"),
 *     @OA\Property(property="idempotency_key", type="string", example="unique-key-123"),
 *     @OA\Property(property="error_message", type="string", nullable=true),
 *     @OA\Property(property="retry_count", type="integer", example=0),
 *     @OA\Property(property="queued_at", type="string", format="date-time"),
 *     @OA\Property(property="sent_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="delivered_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="SendNotificationRequest",
 *     required={"channel", "message", "recipient_ids"},
 *     @OA\Property(property="channel", type="string", enum={"sms","email"}, description="Канал доставки"),
 *     @OA\Property(property="message", type="string", maxLength=1000, description="Текст сообщения"),
 *     @OA\Property(property="recipient_ids", type="array", @OA\Items(type="string"), description="Массив ID получателей"),
 *     @OA\Property(property="priority", type="string", enum={"high","low"}, description="Приоритет", example="low"),
 *     @OA\Property(property="idempotency_key", type="string", description="Ключ идемпотентности", example="unique-key-123")
 * )
 *
 * @OA\Schema(
 *     schema="SendNotificationResponse",
 *     @OA\Property(property="batch_id", type="string", format="uuid", description="ID пачки отправки"),
 *     @OA\Property(property="message", type="string", example="Notifications queued successfully")
 * )
 *
 * @OA\Schema(
 *     schema="HistoryResponse",
 *     @OA\Property(property="subscriber_id", type="string", description="ID подписчика"),
 *     @OA\Property(property="notifications", type="array", @OA\Items(ref="#/components/schemas/Notification"))
 * )
 *
 * @OA\Schema(
 *     schema="ErrorResponse",
 *     @OA\Property(property="message", type="string"),
 *     @OA\Property(property="errors", type="object", additionalProperties=true)
 * )
 */
class SwaggerController extends Controller
{
    // Этот контроллер только для хранения аннотаций
    // Методы не нужны, так как документация генерируется из аннотаций
}
