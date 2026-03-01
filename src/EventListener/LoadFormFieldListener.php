<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\EventListener;

use Contao\CoreBundle\DependencyInjection\Attribute\AsHook;
use Contao\Form;
use Contao\FrontendUser;
use Contao\Widget;
use Symfony\Bundle\SecurityBundle\Security;
use ZukunftsforumRissen\CommunityOffersBundle\Service\AccessService;

#[AsHook('loadFormField')]
class LoadFormFieldListener
{
    public function __construct(
        private readonly Security $security,
        private readonly AccessService $accessService,
    ) {
    }

    /**
     * @param array<string, mixed> $formData
     */
    public function __invoke(Widget $widget, string $formId, array $formData, Form $form): Widget
    {
        // Contao übergibt "auto_<FORMULAR-ID>"
        if ('auto_additional_access_request' !== $formId) {
            return $widget;
        }

        $user = $this->security->getUser();
        if (!$user instanceof FrontendUser) {
            return $widget;
        }

        // Textfield for User's Name.
        if (($widget->name ?? null) === 'fullName') {
            $widget->value = trim(($user->firstname ?? '').' '.($user->lastname ?? ''));
            $widget->readonly = true;
            $widget->disabled = true; // optional: verhindert Mitsenden

            return $widget;
        }

        // Checkbox-Optionen für bereits vorhandene Areas entfernen
        if ('requestedAreas' === $widget->name) {
            $granted = $this->accessService->getGrantedAreasForMemberId((int) $user->id);

            $options = [];
            $rawOptions = $widget->options;
            if (\is_array($rawOptions)) {
                $options = $rawOptions;
            }

            if (!\is_array($options)) {
                return $widget;
            }

            $options = array_values(array_filter(
                $options,
                static function ($opt) use ($granted): bool {
                    if (!\is_array($opt) || !isset($opt['value'])) {
                        return true;
                    }

                    return !\in_array((string) $opt['value'], $granted, true);
                },
            ));

            $widget->options = $options;
        }

        return $widget;
    }
}
