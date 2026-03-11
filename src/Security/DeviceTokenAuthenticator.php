<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Security;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use ZukunftsforumRissen\CommunityOffersBundle\Service\DeviceAuthService;
use ZukunftsforumRissen\CommunityOffersBundle\Service\LoggingService;

final class DeviceTokenAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly DeviceAuthService $auth,
        private readonly LoggingService $logging,
    ) {
    }

    public function supports(Request $request): bool
    {
        return str_starts_with($request->getPathInfo(), '/api/device');
    }

    public function authenticate(Request $request): SelfValidatingPassport
    {
        $token = $this->extractToken($request);

        $this->logging->info('device_auth.authenticate_start', [
            'path' => $request->getPathInfo(),
            'hasAuthorizationHeader' => null !== $request->headers->get('Authorization'),
            'hasXDeviceTokenHeader' => null !== $request->headers->get('X-Device-Token'),
            'tokenPresent' => null !== $token && '' !== $token,
        ]);
        $this->logging->debug('device_auth.authenticate_start', [
            'tokenHashPrefix' => $token ? substr(hash('sha256', $token), 0, 12) : null,
        ]);

        $ctx = $this->auth->authenticate($token);

        if (!$ctx) {
            $this->logging->warning('device_auth.authenticate_failed', [
                'path' => $request->getPathInfo(),
                'tokenPresent' => null !== $token && '' !== $token,
            ]);

            throw new CustomUserMessageAuthenticationException('Invalid device token');
        }

        $deviceId = $ctx['deviceId'];
        $areas = $ctx['areas'];

        $this->logging->info('device_auth.authenticate_success', [
            'deviceId' => $deviceId,
            'areas' => $areas,
        ]);

        return new SelfValidatingPassport(
            new UserBadge($deviceId, static fn () => new DeviceApiUser($deviceId, $areas)),
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): JsonResponse|null
    {
        return null; // request läuft normal weiter
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): JsonResponse
    {
        return new JsonResponse(['error' => 'unauthorized'], 401);
    }

    private function extractToken(Request $request): string|null
    {
        $auth = $request->headers->get('Authorization');
        if ($auth && preg_match('~^Bearer\s+(.+)$~i', $auth, $m)) {
            return trim($m[1]);
        }

        $x = $request->headers->get('X-Device-Token');

        return $x ? trim($x) : null;
    }
}
