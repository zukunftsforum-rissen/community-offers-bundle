<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Tests;

use PHPUnit\Framework\TestCase;
use ZukunftsforumRissen\CommunityOffersBundle\DataContainer\TlCoDevice;

class TlCoDeviceTest extends TestCase
{
    /**
     * Verifies label formatter outputs joined areas and enabled status for active devices.
     */
    public function testFormatLabelShowsAreasAndEnabledStatus(): void
    {
        $dataContainer = new TlCoDevice();

        $row = [
            'areas' => serialize(['workshop', 'depot']),
            'enabled' => '1',
        ];

        $args = ['Device Name', 'device-1', '', ''];

        $result = $dataContainer->formatLabel($row, 'Label', null, $args);

        $this->assertSame('workshop, depot', $result[2]);
        $this->assertSame('Ja', $result[3]);
    }

    /**
     * Verifies label formatter falls back for empty areas and disabled status.
     */
    public function testFormatLabelUsesFallbackForEmptyAreasAndDisabledDevice(): void
    {
        $dataContainer = new TlCoDevice();

        $row = [
            'areas' => null,
            'enabled' => '0',
        ];

        $args = ['Device Name', 'device-2', '', ''];

        $result = $dataContainer->formatLabel($row, 'Label', null, $args);

        $this->assertSame('–', $result[2]);
        $this->assertSame('Nein', $result[3]);
    }
}
