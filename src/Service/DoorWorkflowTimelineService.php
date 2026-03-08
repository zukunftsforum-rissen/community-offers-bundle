<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Service;

use Doctrine\DBAL\Connection;

final class DoorWorkflowTimelineService
{
    public function __construct(private readonly Connection $db)
    {
    }

    /**
     * @return list<array{
     *   id:int,
     *   tstamp:int,
     *   correlationId:string,
     *   memberId:int,
     *   memberName:string,
     *   area:string,
     *   action:string,
     *   result:string,
     *   actionLabel:string,
     *   resultLabel:string,
     *   resultIcon:string,
     *   phaseLabel:string,
     *   phaseClass:string,
     *   message:string,
     *   context:string|null
     * }>
     */
    public function getTimeline(string $correlationId): array
    {
        $rows = $this->db->fetchAllAssociative(
            'SELECT l.id, l.tstamp, l.correlationId, l.memberId, l.area, l.action, l.result, l.message, l.context,
                    TRIM(CONCAT(COALESCE(m.firstname, \'\'), \' \', COALESCE(m.lastname, \'\'))) AS memberName
             FROM tl_co_door_log l
             LEFT JOIN tl_member m ON m.id = l.memberId
             WHERE l.correlationId = :cid
             ORDER BY l.tstamp ASC, l.id ASC',
            ['cid' => $correlationId],
        );

        return array_map(
            function (array $row): array {
                $action = (string) ($row['action'] ?? '');
                $result = (string) ($row['result'] ?? '');
                $phase = $this->determinePhase($action);

                return [
                    'id' => (int) ($row['id'] ?? 0),
                    'tstamp' => (int) ($row['tstamp'] ?? 0),
                    'correlationId' => (string) ($row['correlationId'] ?? ''),
                    'memberId' => (int) ($row['memberId'] ?? 0),
                    'memberName' => '' !== trim((string) ($row['memberName'] ?? ''))
                        ? trim((string) ($row['memberName'] ?? ''))
                        : ((int) ($row['memberId'] ?? 0) > 0 ? '#'.(int) ($row['memberId'] ?? 0) : 'Gast/Unbekannt'),
                    'area' => (string) ($row['area'] ?? ''),
                    'action' => $action,
                    'result' => $result,
                    'actionLabel' => $this->mapActionLabel($action),
                    'resultLabel' => $this->mapResultLabel($result),
                    'resultIcon' => $this->mapResultIcon($result),
                    'phaseLabel' => $phase['label'],
                    'phaseClass' => $phase['class'],
                    'message' => (string) ($row['message'] ?? ''),
                    'context' => isset($row['context']) ? (string) $row['context'] : null,
                ];
            },
            $rows,
        );
    }

    /**
     * @return list<array{
     *   correlationId:string,
     *   startedAt:int,
     *   finishedAt:int,
     *   durationMs:int,
     *   area:string,
     *   memberId:int,
     *   memberName:string,
     *   finalAction:string,
     *   finalResult:string,
     *   flow:string,
     *   flowClass:string,
     *   trafficLight:string,
     *   trafficLightLabel:string,
     *   statusLabel:string,
     *   statusIcon:string
     * }>
     */
    public function getRecent(int $limit = 50): array
    {
        $limit = max(1, min(200, $limit));

        $sql = <<<SQL
                SELECT
                    l.correlationId,
                    MIN(l.tstamp) AS startedAt,
                    MAX(l.tstamp) AS finishedAt,
                    MAX(l.area) AS area,
                    MAX(l.memberId) AS memberId,
                    TRIM(CONCAT(COALESCE(MAX(m.firstname), ''), ' ', COALESCE(MAX(m.lastname), ''))) AS memberName,
                    SUBSTRING_INDEX(GROUP_CONCAT(l.action ORDER BY l.tstamp ASC, l.id ASC), ',', 1) AS firstAction,
                    SUBSTRING_INDEX(GROUP_CONCAT(l.result ORDER BY l.tstamp ASC, l.id ASC), ',', 1) AS firstResult,
                    SUBSTRING_INDEX(GROUP_CONCAT(l.action ORDER BY l.tstamp DESC, l.id DESC), ',', 1) AS finalAction,
                    SUBSTRING_INDEX(GROUP_CONCAT(l.result ORDER BY l.tstamp DESC, l.id DESC), ',', 1) AS finalResult,
                    COALESCE(MAX(
                        CASE
                            WHEN j.createdAtMs > 0 AND j.executedAtMs > 0 THEN (j.executedAtMs - j.createdAtMs)
                            WHEN j.createdAt > 0 AND j.executedAt > 0 THEN (j.executedAt - j.createdAt) * 1000
                            WHEN j.createdAtMs > 0 AND j.dispatchedAtMs > 0 THEN (j.dispatchedAtMs - j.createdAtMs)
                            WHEN j.createdAt > 0 AND j.dispatchedAt > 0 THEN (j.dispatchedAt - j.createdAt) * 1000
                            ELSE 0
                        END
                    ), 0) AS durationMs,
                    MAX(CASE WHEN l.action = 'door_open' AND l.result = 'granted' THEN 1 ELSE 0 END) AS hasOpen,
                    MAX(CASE WHEN l.action = 'door_dispatch' AND l.result = 'dispatched' THEN 1 ELSE 0 END) AS hasDispatch,
                    MAX(CASE WHEN l.action = 'door_confirm' AND l.result = 'confirmed' THEN 1 ELSE 0 END) AS hasConfirm,
                    MAX(CASE WHEN l.result IN ('failed', 'timeout', 'error', 'forbidden') THEN 1 ELSE 0 END) AS hasFailure
                FROM tl_co_door_log l
                LEFT JOIN tl_member m ON m.id = l.memberId
                LEFT JOIN tl_co_door_job j ON j.correlationId = l.correlationId
                WHERE l.correlationId <> ''
                GROUP BY l.correlationId
                ORDER BY finishedAt DESC
                LIMIT $limit
            SQL;

        $rows = $this->db->fetchAllAssociative($sql);

        return array_map(
            function (array $row): array {
                $memberId = (int) ($row['memberId'] ?? 0);
                $memberName = trim((string) ($row['memberName'] ?? ''));

                $hasOpen = 1 === (int) ($row['hasOpen'] ?? 0);
                $hasDispatch = 1 === (int) ($row['hasDispatch'] ?? 0);
                $hasConfirm = 1 === (int) ($row['hasConfirm'] ?? 0);
                $hasFailure = 1 === (int) ($row['hasFailure'] ?? 0);

                $flow = $this->buildFlow($hasOpen, $hasDispatch, $hasConfirm);
                $traffic = $this->buildTrafficLight($hasOpen, $hasDispatch, $hasConfirm, $hasFailure);
                $status = $this->buildStatusLabel(
                    (string) ($row['finalAction'] ?? ''),
                    (string) ($row['finalResult'] ?? ''),
                    $hasOpen,
                    $hasDispatch,
                    $hasConfirm,
                    $hasFailure,
                );

                return [
                    'correlationId' => (string) ($row['correlationId'] ?? ''),
                    'startedAt' => (int) ($row['startedAt'] ?? 0),
                    'finishedAt' => (int) ($row['finishedAt'] ?? 0),
                    'durationMs' => (int) ($row['durationMs'] ?? 0),
                    'area' => (string) ($row['area'] ?? ''),
                    'memberId' => $memberId,
                    'memberName' => '' !== $memberName ? $memberName : ($memberId > 0 ? '#'.$memberId : 'Gast/Unbekannt'),
                    'finalAction' => (string) ($row['finalAction'] ?? ''),
                    'finalResult' => (string) ($row['finalResult'] ?? ''),
                    'flow' => $flow['label'],
                    'flowClass' => $flow['class'],
                    'trafficLight' => $traffic['class'],
                    'trafficLightLabel' => $traffic['label'],
                    'statusLabel' => $status['label'],
                    'statusIcon' => $status['icon'],
                ];
            },
            $rows,
        );
    }

    public function getDurationMs(string $correlationId): int
    {
        $row = $this->db->fetchAssociative(
            'SELECT createdAtMs, dispatchedAtMs, executedAtMs, createdAt, dispatchedAt, executedAt
             FROM tl_co_door_job
             WHERE correlationId = :cid
             ORDER BY id DESC
             LIMIT 1',
            ['cid' => $correlationId],
        );

        if (!$row) {
            return 0;
        }

        $createdAtMs = (int) ($row['createdAtMs'] ?? 0);
        $dispatchedAtMs = (int) ($row['dispatchedAtMs'] ?? 0);
        $executedAtMs = (int) ($row['executedAtMs'] ?? 0);
        $createdAt = (int) ($row['createdAt'] ?? 0);
        $dispatchedAt = (int) ($row['dispatchedAt'] ?? 0);
        $executedAt = (int) ($row['executedAt'] ?? 0);

        if ($createdAtMs > 0 && $executedAtMs > 0 && $executedAtMs >= $createdAtMs) {
            return $executedAtMs - $createdAtMs;
        }

        if ($createdAt > 0 && $executedAt > 0 && $executedAt >= $createdAt) {
            return ($executedAt - $createdAt) * 1000;
        }

        if ($createdAtMs > 0 && $dispatchedAtMs > 0 && $dispatchedAtMs >= $createdAtMs) {
            return $dispatchedAtMs - $createdAtMs;
        }

        if ($createdAt > 0 && $dispatchedAt > 0 && $dispatchedAt >= $createdAt) {
            return ($dispatchedAt - $createdAt) * 1000;
        }

        return 0;
    }

    /**
     * @param list<array{action:string, result:string}> $timeline
     *
     * @return list<string>
     */
    public function analyzeWorkflow(array $timeline): array
    {
        $hasOpen = false;
        $hasDispatch = false;
        $hasConfirm = false;

        foreach ($timeline as $event) {
            if ('door_open' === $event['action'] && 'granted' === $event['result']) {
                $hasOpen = true;
            }

            if ('door_dispatch' === $event['action'] && 'dispatched' === $event['result']) {
                $hasDispatch = true;
            }

            if ('door_confirm' === $event['action'] && 'confirmed' === $event['result']) {
                $hasConfirm = true;
            }
        }

        $warnings = [];

        if ($hasDispatch && !$hasConfirm) {
            $warnings[] = 'Job wurde vom Gerät abgeholt, aber die Entriegelung wurde nicht bestätigt.';
        }

        if ($hasConfirm && !$hasDispatch) {
            $warnings[] = 'Entriegelung bestätigt, aber kein Abhol-Log vorhanden.';
        }

        if ($hasOpen && !$hasDispatch && !$hasConfirm) {
            $warnings[] = 'Zugriff erlaubt, aber der Job wurde noch nicht vom Gerät abgeholt.';
        }

        return $warnings;
    }

    /**
     * @return array{label:string, class:string}
     */
    private function determinePhase(string $action): array
    {
        return match ($action) {
            'door_open' => ['label' => '👤 Anfrage', 'class' => 'phase-app'],
            'door_dispatch' => ['label' => '📡 Gerät', 'class' => 'phase-device'],
            'door_confirm' => ['label' => '🔓 Tür', 'class' => 'phase-door'],
            default => ['label' => '⚙ System', 'class' => 'phase-system'],
        };
    }

    private function mapActionLabel(string $action): string
    {
        return match ($action) {
            'door_open' => 'Türöffnung anfragen',
            'door_dispatch' => 'Job abholen',
            'door_confirm' => 'Türstatus melden',
            'request_access' => 'Zugang beantragen',
            default => $action,
        };
    }

    private function mapResultLabel(string $result): string
    {
        return match ($result) {
            'attempt' => 'Öffnung angefragt',
            'granted' => 'Auftrag erstellt',
            'forbidden' => 'Kein Zugriff',
            'unknown_area' => 'Unbekannter Bereich',
            'unauthenticated' => 'Nicht angemeldet',
            'rate_limited' => 'Zu viele Anfragen',
            'dispatched' => 'Vom Gerät abgeholt',
            'confirmed' => 'Entriegelung bestätigt',
            'failed' => 'Fehlgeschlagen',
            'timeout' => 'Zeitüberschreitung',
            'error' => 'Fehler',
            default => $result,
        };
    }

    private function mapResultIcon(string $result): string
    {
        return match ($result) {
            'confirmed', 'granted', 'dispatched' => '✔',
            'failed', 'forbidden', 'error' => '✖',
            'timeout', 'rate_limited' => '⚠',
            default => '•',
        };
    }

    /**
     * @return array{label:string, class:string}
     */
    private function buildFlow(bool $hasOpen, bool $hasDispatch, bool $hasConfirm): array
    {
        $steps = [];

        if ($hasOpen) {
            $steps[] = 'OPEN';
        }

        if ($hasDispatch) {
            $steps[] = 'DISPATCH';
        }

        if ($hasConfirm) {
            $steps[] = 'CONFIRM';
        }

        $label = $steps ? implode(' → ', $steps) : '–';
        $class = $hasConfirm ? 'flow-complete' : ($hasDispatch ? 'flow-in-progress' : 'flow-pending');

        return ['label' => $label, 'class' => $class];
    }

    /**
     * @return array{class:string, label:string}
     */
    private function buildTrafficLight(bool $hasOpen, bool $hasDispatch, bool $hasConfirm, bool $hasFailure): array
    {
        if ($hasFailure) {
            return ['class' => 'ampel-red', 'label' => 'Fehler'];
        }

        if ($hasOpen && $hasDispatch && $hasConfirm) {
            return ['class' => 'ampel-green', 'label' => 'Tür entriegelt'];
        }

        if ($hasOpen && ($hasDispatch || $hasConfirm)) {
            return ['class' => 'ampel-yellow', 'label' => 'Wird bearbeitet'];
        }

        return ['class' => 'ampel-red', 'label' => 'Auftrag nicht ausgeführt'];
    }

    /**
     * @return array{label:string, icon:string}
     */
    private function buildStatusLabel(string $finalAction, string $finalResult, bool $hasOpen, bool $hasDispatch, bool $hasConfirm, bool $hasFailure): array
    {
        if ($hasFailure || \in_array($finalResult, ['failed', 'timeout', 'error', 'forbidden'], true)) {
            return ['label' => $this->mapResultLabel($finalResult), 'icon' => $this->mapResultIcon($finalResult)];
        }

        if ($hasConfirm) {
            return ['label' => 'Tür entriegelt', 'icon' => '✔'];
        }

        if ($hasDispatch) {
            return ['label' => 'Vom Gerät abgeholt', 'icon' => '📡'];
        }

        if ($hasOpen) {
            return ['label' => 'Auftrag erstellt', 'icon' => '⏳'];
        }

        return [
            'label' => trim($this->mapActionLabel($finalAction).' / '.$this->mapResultLabel($finalResult), ' /'),
            'icon' => $this->mapResultIcon($finalResult),
        ];
    }
}
