<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\DataContainer;

use Contao\DataContainer;
use Contao\StringUtil;

final class TlCoDevice
{
    /**
     * Contao label_callback: return modified $args array.
     *
     * @param array<string, mixed> $row
     * @param array<int, mixed>    $args
     *
     * @return array<int, mixed>
     */
    public function formatLabel(array $row, string $label, DataContainer|null $dc, array $args): array
    {
        // $args entspricht den Feldern aus list.label.fields in gleicher Reihenfolge:
        // [name, deviceId, areas, enabled]

        $areas = StringUtil::deserialize($row['areas'] ?? null, true);
        $areas = array_values(array_filter(array_map('strval', (array) $areas)));

        // hübsche Anzeige
        $args[2] = $areas ? implode(', ', $areas) : '–';

        // enabled als Ja/Nein
        $args[3] = ($row['enabled'] ?? '') === '1' ? 'Ja' : 'Nein';

        return $args;
    }
}
