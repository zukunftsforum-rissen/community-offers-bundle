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
            static fn (array $row): array => [
                'id' => (int) ($row['id'] ?? 0),
                'tstamp' => (int) ($row['tstamp'] ?? 0),
                'correlationId' => (string) ($row['correlationId'] ?? ''),
                'memberId' => (int) ($row['memberId'] ?? 0),
                'memberName' => '' !== trim((string) ($row['memberName'] ?? ''))
                    ? trim((string) ($row['memberName'] ?? ''))
                    : ((int) ($row['memberId'] ?? 0) > 0 ? '#'.(int) ($row['memberId'] ?? 0) : 'Gast/Unbekannt'),
                'area' => (string) ($row['area'] ?? ''),
                'action' => (string) ($row['action'] ?? ''),
                'result' => (string) ($row['result'] ?? ''),
                'message' => (string) ($row['message'] ?? ''),
                'context' => isset($row['context']) ? (string) $row['context'] : null,
            ],
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
     *   finalResult:string
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
                    SUBSTRING_INDEX(GROUP_CONCAT(l.action ORDER BY l.tstamp DESC, l.id DESC), ',', 1) AS finalAction,
                    SUBSTRING_INDEX(GROUP_CONCAT(l.result ORDER BY l.tstamp DESC, l.id DESC), ',', 1) AS finalResult,
                    COALESCE(MAX(
                        CASE
                            WHEN j.createdAt > 0 AND j.executedAt > 0 THEN (j.executedAt - j.createdAt) * 1000
                            ELSE 0
                        END
                    ), 0) AS durationMs
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
            static function (array $row): array {
                $memberId = (int) ($row['memberId'] ?? 0);
                $memberName = trim((string) ($row['memberName'] ?? ''));

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
                ];
            },
            $rows,
        );
    }

    public function getDurationMs(string $correlationId): int
    {
        $row = $this->db->fetchAssociative(
            'SELECT createdAt, executedAt
             FROM tl_co_door_job
             WHERE correlationId = :cid
             ORDER BY id DESC
             LIMIT 1',
            ['cid' => $correlationId],
        );

        if (!$row) {
            return 0;
        }

        $createdAt = (int) ($row['createdAt'] ?? 0);
        $executedAt = (int) ($row['executedAt'] ?? 0);

        if ($createdAt > 0 && $executedAt > 0 && $executedAt >= $createdAt) {
            return ($executedAt - $createdAt) * 1000;
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

            if ('door_dispatch' === $event['action']) {
                $hasDispatch = true;
            }

            if ('door_confirm' === $event['action'] && 'confirmed' === $event['result']) {
                $hasConfirm = true;
            }
        }

        $warnings = [];

        if ($hasDispatch && !$hasConfirm) {
            $warnings[] = 'Dispatch ohne Confirm (Tür evtl. offline)';
        }

        if ($hasConfirm && !$hasDispatch) {
            $warnings[] = 'Confirm ohne Dispatch (API inkonsistent)';
        }

        if ($hasOpen && !$hasDispatch) {
            $warnings[] = 'Job wurde erstellt aber nie ausgeliefert';
        }

        return $warnings;
    }
}
