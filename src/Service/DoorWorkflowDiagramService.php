<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Service;

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

    public function buildServerSvgUrl(string $plantUml, string $baseUrl = 'https://www.plantuml.com/plantuml/svg/'): string
    {
        return rtrim($baseUrl, '/').'/'.$this->encodeForPlantUmlServer($plantUml);
    }

    private function encodeForPlantUmlServer(string $text): string
    {
        $compressed = gzdeflate($text, 9);

        if (false === $compressed) {
            throw new \RuntimeException('Could not compress PlantUML text.');
        }

        return $this->encode64($compressed);
    }

    private function encode64(string $data): string
    {
        $res = '';
        $length = \strlen($data);

        for ($i = 0; $i < $length; $i += 3) {
            $b1 = \ord($data[$i]);
            $b2 = $i + 1 < $length ? \ord($data[$i + 1]) : 0;
            $b3 = $i + 2 < $length ? \ord($data[$i + 2]) : 0;

            $res .= $this->append3bytes($b1, $b2, $b3);
        }

        return $res;
    }

    private function append3bytes(int $b1, int $b2, int $b3): string
    {
        $c1 = $b1 >> 2;
        $c2 = (($b1 & 0x3) << 4) | ($b2 >> 4);
        $c3 = (($b2 & 0xF) << 2) | ($b3 >> 6);
        $c4 = $b3 & 0x3F;

        $r = '';
        $r .= $this->encode6bit($c1 & 0x3F);
        $r .= $this->encode6bit($c2 & 0x3F);
        $r .= $this->encode6bit($c3 & 0x3F);
        $r .= $this->encode6bit($c4 & 0x3F);

        return $r;
    }

    private function encode6bit(int $b): string
    {
        if ($b < 10) {
            return \chr(48 + $b);
        }

        $b -= 10;
        if ($b < 26) {
            return \chr(65 + $b);
        }

        $b -= 26;
        if ($b < 26) {
            return \chr(97 + $b);
        }

        $b -= 26;
        if (0 === $b) {
            return '-';
        }

        if (1 === $b) {
            return '_';
        }

        return '?';
    }
}
