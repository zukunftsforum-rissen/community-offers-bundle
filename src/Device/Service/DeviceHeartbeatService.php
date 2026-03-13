<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Device\Service;

use Doctrine\DBAL\Connection;

final class DeviceHeartbeatService implements DeviceHeartbeatInterface
{
    public function __construct(
        private readonly Connection $connection,
        private readonly DoorAuditLogger $audit,
    ) {
    }

    /**
     * @param list<string> $areas
     */
    public function registerPoll(int|string $deviceId, array $areas = []): void
    {
        $device = $this->connection->fetchAssociative(
            'SELECT id, lastSeen
                    FROM tl_co_device
                    WHERE deviceId = :deviceId
                    LIMIT 1',
            ['deviceId' => (string) $deviceId],
        );
        if (!\is_array($device)) {
            return;
        }

        $now = time();
        $lastSeen = (int) ($device['lastSeen'] ?? 0);

        // nur etwa alle 30 Sekunden schreiben und auditieren
        if ($lastSeen > $now - 30) {
            return;
        }

        $this->connection->update(
            'tl_co_device',
            [
                'lastSeen' => $now,
                'tstamp' => $now,
            ],
            [
                'id' => (int) $device['id'],
            ],
        );

        $this->audit->audit(
            action: 'device_poll',
            area: '',
            result: 'ok',
            message: 'Device poll received',
            context: [
                'deviceId' => $deviceId,
                'areas' => $areas,
            ],
            correlationId: '',
            memberId: 0,
        );
    }
}
