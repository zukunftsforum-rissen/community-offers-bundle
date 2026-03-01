<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\EventListener;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\FrontendUser;
use Symfony\Bundle\SecurityBundle\Security;
use ZukunftsforumRissen\CommunityOffersBundle\Service\AccessRequestService;
use ZukunftsforumRissen\CommunityOffersBundle\Service\AccessService;

#[AsHook('processFormData')]
class ProcessFormDataListener
{
    public function __construct(
        private readonly AccessRequestService $accessRequestService,
        private readonly Security $security,
        private readonly AccessService $accessService,
    ) {
    }

    /**
     * @param array<string, mixed> $submittedData
     * @param array<string, mixed> $labels
     * @param array<string, mixed> $files
     * @param array<string, mixed> $formData
     */
    public function __invoke(array $submittedData, array $formData, array $files, array $labels): void
    {
        $formId = (string) ($formData['formID'] ?? '');

        if ('access_request' !== $formId && 'additional_access_request' !== $formId) {
            return;
        }

        // Areas normalisieren
        $requestedAreas = $submittedData['requestedAreas'] ?? [];
        if (!\is_array($requestedAreas)) {
            $requestedAreas = [$requestedAreas];
        }
        $requestedAreas = array_values(array_filter(array_map('strval', $requestedAreas)));

        // ===== Zusatzformular: Member-Daten aus Session =====
        if ('additional_access_request' === $formId) {
            $user = $this->security->getUser();

            if (!$user instanceof FrontendUser) {
                // nicht eingeloggt -> ignorieren oder Exception/Logging
                return;
            }

            // Doppelt absichern: bereits vorhandene Areas rausfiltern
            $granted = $this->accessService->getGrantedAreasForMemberId((int) $user->id);
            $requestedAreas = array_values(array_diff($requestedAreas, $granted));

            // Wenn nichts mehr übrig bleibt, keine Anfrage erzeugen
            if ([] === $requestedAreas) {
                return;
            }

            $this->accessRequestService->createRequestAndSendDoiMail(
                firstname: (string) ($user->firstname ?? ''),
                lastname: (string) ($user->lastname ?? ''),
                email: (string) ($user->email ?? ''),
                street: '',
                postal: '',
                city: '',
                mobile: '',
                requestedAreas: $requestedAreas);

            return;
        }

        // ===== Erstantrag: Daten aus Formular =====
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
            requestedAreas: $requestedAreas);
    }
}
