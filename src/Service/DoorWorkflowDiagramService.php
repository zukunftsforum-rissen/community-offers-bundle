<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Service;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

final class DoorWorkflowDiagramService
{
    /**
     * @param list<array{
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
     * }> $timeline
     */
    public function buildPlantUml(string $correlationId, array $timeline): string
    {
        $lines = [];
        $lines[] = '@startuml';
        $lines[] = 'title Door Workflow '.$correlationId;
        $lines[] = 'actor Member';
        $lines[] = 'participant AccessController';
        $lines[] = 'participant DoorJobService';
        $lines[] = 'participant DeviceController';
        $lines[] = 'participant Device';
        $lines[] = 'database AuditLog';
        $lines[] = '';

        foreach ($timeline as $event) {
            $action = $event['action'];
            $result = $event['result'];

            if ('door_open' === $action && 'attempt' === $result) {
                $lines[] = 'Member -> AccessController: open request';
            } elseif ('door_open' === $action && 'granted' === $result) {
                $lines[] = 'AccessController -> DoorJobService: createOpenJob';
                $lines[] = 'DoorJobService -> AuditLog: door_open / granted';
            } elseif ('door_dispatch' === $action && 'dispatched' === $result) {
                $lines[] = 'Device -> DeviceController: poll';
                $lines[] = 'DeviceController -> DoorJobService: dispatchJobs';
                $lines[] = 'DoorJobService -> AuditLog: door_dispatch / dispatched';
            } elseif ('door_confirm' === $action && 'attempt' === $result) {
                $lines[] = 'Device -> DeviceController: confirm';
                $lines[] = 'DeviceController -> DoorJobService: confirmJob';
            } elseif ('door_confirm' === $action) {
                $lines[] = 'DoorJobService -> AuditLog: door_confirm / '.$result;
            }
        }

        $lines[] = '@enduml';

        return implode("\n", $lines)."\n";
    }

    public function renderSvg(string $plantUml): string
    {
        $process = new Process(['plantuml', '-tsvg', '-pipe']);
        $process->setInput($plantUml);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }

        return $process->getOutput();
    }
}
