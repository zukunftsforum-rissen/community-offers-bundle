<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Door;

final class DoorGatewayResolver
{
    /**
     * @param iterable<DoorGatewayInterface> $gateways
     */
    public function __construct(
        private readonly iterable $gateways,
    ) {
    }

    public function resolve(string $mode): DoorGatewayInterface
    {
        foreach ($this->gateways as $gateway) {
            if ($gateway->supports($mode)) {
                return $gateway;
            }
        }

        throw new \RuntimeException(sprintf(
            'No door gateway found for mode "%s".',
            $mode,
        ));
    }
}
