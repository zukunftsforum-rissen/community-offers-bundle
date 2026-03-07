<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Workflow;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Workflow\Event\GuardEvent;
use Symfony\Component\Workflow\Event\TransitionEvent;

final class DoorJobWorkflowSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly int $confirmWindowSeconds = 30,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Side-effects
            'workflow.door_job.transition.dispatch' => 'onDispatch',

            // Guard
            'workflow.door_job.guard.execute' => 'guardExecute',
        ];
    }

    public function onDispatch(TransitionEvent $event): void
    {
        $subject = $event->getSubject();
        if (!$subject instanceof DoorJob) {
            return;
        }

        $now = new \DateTimeImmutable();

        if (null === $subject->getDispatchedAt()) {
            $subject->setDispatchedAt($now);
        }

        if (null === $subject->getConfirmExpiresAt()) {
            $base = $subject->getDispatchedAt() ?? $now;
            $subject->setConfirmExpiresAt($base->modify('+'.$this->confirmWindowSeconds.' seconds'));
        }
    }

    public function guardExecute(GuardEvent $event): void
    {
        $subject = $event->getSubject();
        if (!$subject instanceof DoorJob) {
            return;
        }

        $expires = $subject->getConfirmExpiresAt();
        if (null === $expires) {
            // Strict: if missing, block execution.
            $event->setBlocked(true, 'confirm_window_missing');

            return;
        }

        if (new \DateTimeImmutable() > $expires) {
            $event->setBlocked(true, 'confirm_window_expired');
        }
    }
}
