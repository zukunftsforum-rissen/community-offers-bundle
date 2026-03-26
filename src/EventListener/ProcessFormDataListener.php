<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\EventListener;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use ZukunftsforumRissen\CommunityOffersBundle\Service\AccessRequestService;

#[AsHook('processFormData')]
class ProcessFormDataListener
{
    public function __construct(
        private readonly AccessRequestService $accessRequestService,
    ) {
    }

    /**
     * @param array<string, mixed> $submittedData
     * @param array<string, mixed> $formData
     * @param array<string, mixed> $files
     * @param array<string, mixed> $labels
     */
    public function __invoke(array $submittedData, array $formData, array $files, array $labels): void
    {
        $formId = (string) ($formData['formID'] ?? '');

        if ('access_request' !== $formId) {
            return;
        }

        $requestedAreas = $submittedData['requestedAreas'] ?? [];

        if (!\is_array($requestedAreas)) {
            $requestedAreas = [$requestedAreas];
        }

        $requestedAreas = array_values(array_filter(array_map('strval', $requestedAreas)));

        $firstname = (string) ($submittedData['firstname'] ?? '');
        $lastname = (string) ($submittedData['lastname'] ?? '');
        $email = (string) ($submittedData['email'] ?? '');
        $street = (string) ($submittedData['street'] ?? '');
        $postal = (string) ($submittedData['postal'] ?? '');
        $city = (string) ($submittedData['city'] ?? '');
        $mobile = (string) ($submittedData['mobile'] ?? '');

        $this->accessRequestService->createRequestAndSendDoiMail(
            firstname: $firstname,
            lastname: $lastname,
            email: $email,
            street: $street,
            postal: $postal,
            city: $city,
            mobile: $mobile,
            requestedAreas: $requestedAreas,
        );
    }
}
