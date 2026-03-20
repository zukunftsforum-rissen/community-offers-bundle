<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Service;

final class DoorOpenObserverResolver
{
    public function __construct(
        private readonly DoorOpenObserverInterface $workflowObserver,
    ) {
    }

    public function resolve(string $mode): DoorOpenObserverInterface
    {
        return $this->workflowObserver;
    }
}
