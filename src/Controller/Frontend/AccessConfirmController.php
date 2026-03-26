<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Controller\Frontend;

use Contao\StringUtil;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Attribute\Route;
use ZukunftsforumRissen\CommunityOffersBundle\Service\AccessRequestService;
use ZukunftsforumRissen\CommunityOffersBundle\Service\InternalNotificationMailer;
use ZukunftsforumRissen\CommunityOffersBundle\Service\MemberProvisioningServiceInterface;
use ZukunftsforumRissen\CommunityOffersBundle\Service\PasswordSetupServiceInterface;

final class AccessConfirmController
{
    public function __construct(
        private readonly AccessRequestService $service,
        private readonly MemberProvisioningServiceInterface $memberProvisioningService,
        private readonly PasswordSetupServiceInterface $passwordSetupService,
        private readonly InternalNotificationMailer $internalNotificationMailer,
    ) {
    }

    #[Route(
        '/access/confirm/{token}',
        name: 'community_offers_access_confirm',
        methods: ['GET'],
        defaults: ['_scope' => 'frontend'],
        requirements: ['token' => '[a-f0-9]{64}']
    )]
    public function confirm(string $token): RedirectResponse
    {
        $requestId = $this->service->confirmTokenAndGetRequestId($token);

        if (null === $requestId) {
            return new RedirectResponse('/zugangsanfrage-ungueltig');
        }

        $row = $this->service->getRequestRow($requestId);

        if (null === $row) {
            return new RedirectResponse('/zugangsanfrage-ungueltig');
        }

        $provisioningResult = $this->memberProvisioningService->createMemberFromConfirmedRequest($requestId);

        $offers = $this->formatAreasHuman((string) ($row['requestedAreas'] ?? ''));

        $this->internalNotificationMailer->sendConfirmedNotification(
            (string) ($row['firstname'] ?? ''),
            (string) ($row['lastname'] ?? ''),
            (string) ($row['street'] ?? ''),
            (string) ($row['postal'] ?? ''),
            (string) ($row['city'] ?? ''),
            (string) ($row['mobile'] ?? ''),
            (string) ($row['email'] ?? ''),
            $offers,
        );

        if (!$provisioningResult->createdNewMember) {
            return new RedirectResponse('/zugangsanfrage-bestaetigt');
        }

        $setupToken = $this->passwordSetupService->createSetupTokenForRequest($requestId);

        return new RedirectResponse('/access/set-password/'.$setupToken);
    }

    /**
     * @return list<string>
     */
    private function formatAreasHuman(string $serializedAreas): array
    {
        $areas = StringUtil::deserialize($serializedAreas, true);
        $areas = array_map('strval', $areas);

        $map = [
            'workshop' => 'Werkstatt',
            'sharing' => 'Sharingstation',
            'depot' => 'Lebensmittel-Depot',
            'swap-house' => 'Tauschhaus',
        ];

        $out = [];

        foreach ($areas as $area) {
            $out[] = $map[$area] ?? $area;
        }

        return $out;
    }
}
