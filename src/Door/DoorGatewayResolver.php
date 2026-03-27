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
        $matches = [];

        foreach ($this->gateways as $gateway) {
            if ($gateway->supports($mode)) {
                $matches[] = $gateway;
            }
        }

        if ([] === $matches) {
            throw new \RuntimeException(\sprintf('No door gateway found for mode "%s".', $mode));
        }

        if (1 !== \count($matches)) {
            throw new \LogicException(\sprintf('Expected exactly one gateway for mode "%s", got %d (%s)', $mode, \count($matches), implode(', ', array_map(static fn ($gateway): string => $gateway::class, $matches))));
        }

        return $matches[0];
    }
}
