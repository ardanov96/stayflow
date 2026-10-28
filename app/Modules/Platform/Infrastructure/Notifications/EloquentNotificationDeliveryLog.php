<?php

declare(strict_types=1);

namespace App\Modules\Platform\Infrastructure\Notifications;

use App\Modules\Platform\Contracts\DTOs\NotificationDeliveryDTO;
use App\Modules\Platform\Contracts\NotificationDeliveryLog;
use Illuminate\Support\Facades\DB;

/**
 * The delivery log, on the database.
 */
final class EloquentNotificationDeliveryLog implements NotificationDeliveryLog
{
    public function record(
        int $organizationId,
        ?int $propertyId,
        string $channel,
        string $template,
        string $locale,
        string $recipient,
        bool $sent,
        ?string $providerMessageId = null,
        ?string $error = null,
    ): NotificationDeliveryDTO {
        $now = now();

        $id = DB::table('notification_deliveries')->insertGetId([
            'organization_id' => $organizationId,
            'property_id' => $propertyId,
            'channel' => $channel,
            'template' => $template,
            'locale' => $locale,
            'recipient' => $recipient,
            'status' => $sent ? 'sent' : 'failed',
            'provider_message_id' => $providerMessageId,
            'error' => $error !== null ? mb_substr($error, 0, 255) : null,
            'delivered_at' => $sent ? $now : null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return new NotificationDeliveryDTO(
            id: (int) $id,
            organizationId: $organizationId,
            propertyId: $propertyId,
            channel: $channel,
            template: $template,
            locale: $locale,
            recipient: $recipient,
            status: $sent ? 'sent' : 'failed',
            providerMessageId: $providerMessageId,
            error: $error,
        );
    }

    /**
     * @return list<NotificationDeliveryDTO>
     */
    public function failedFor(int $organizationId, int $limit = 50): array
    {
        return DB::table('notification_deliveries')
            ->where('organization_id', $organizationId)
            ->whereIn('status', ['failed', 'bounced', 'complained'])
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (object $row): NotificationDeliveryDTO => $this->toDto($row))
            ->all();
    }

    public function markProviderFailure(
        string $providerMessageId,
        string $recipient,
        string $status,
    ): ?NotificationDeliveryDTO {
        $row = DB::table('notification_deliveries')
            ->where('provider_message_id', $providerMessageId)
            ->where('recipient', $recipient)
            ->orderByDesc('id')
            ->first();

        if ($row === null) {
            return null;
        }

        DB::table('notification_deliveries')
            ->where('id', $row->id)
            ->update(['status' => $status, 'updated_at' => now()]);

        return $this->toDto($row, $status);
    }

    private function toDto(object $row, ?string $status = null): NotificationDeliveryDTO
    {
        return new NotificationDeliveryDTO(
            id: (int) $row->id,
            organizationId: (int) $row->organization_id,
            propertyId: $row->property_id !== null ? (int) $row->property_id : null,
            channel: (string) $row->channel,
            template: (string) $row->template,
            locale: (string) $row->locale,
            recipient: (string) $row->recipient,
            status: $status ?? (string) $row->status,
            providerMessageId: $row->provider_message_id !== null ? (string) $row->provider_message_id : null,
            error: $row->error !== null ? (string) $row->error : null,
        );
    }
}
