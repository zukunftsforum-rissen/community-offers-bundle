<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Service;

interface PasswordSetupServiceInterface
{
    public function createSetupTokenForRequest(int $requestId): string;

    /**
     * @return array<string, mixed>
     */
    public function getValidRequestByToken(string $token): array;

    public function setPasswordFromToken(string $token, string $plainPassword): void;
}
