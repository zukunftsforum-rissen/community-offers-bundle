<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Tests;

use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use ZukunftsforumRissen\CommunityOffersBundle\Security\DeviceApiUser;
use ZukunftsforumRissen\CommunityOffersBundle\Security\DeviceTokenAuthenticator;
use ZukunftsforumRissen\CommunityOffersBundle\Service\DeviceAuthService;

class DeviceTokenAuthenticatorTest extends TestCase
{
    /**
     * Verifies supports() matches device API routes.
     */
    public function testSupportsReturnsTrueForDeviceApiPaths(): void
    {
        $authenticator = $this->createAuthenticatorWithStubConnection();

        $request = Request::create('/api/device/poll', 'POST');

        $this->assertTrue((bool) $authenticator->supports($request));
    }

    /**
     * Verifies supports() ignores non-device routes.
     */
    public function testSupportsReturnsFalseForOtherPaths(): void
    {
        $authenticator = $this->createAuthenticatorWithStubConnection();

        $request = Request::create('/api/door/whoami', 'GET');

        $this->assertFalse((bool) $authenticator->supports($request));
    }

    /**
     * Verifies Bearer token auth builds a DeviceApiUser from authenticated device payload.
     */
    public function testAuthenticateUsesBearerTokenAndBuildsDeviceApiUser(): void
    {
        $authenticator = $this->createAuthenticatorExpectingToken('token-123', [
            'deviceId' => 'device-a',
            'enabled' => '1',
            'areas' => serialize(['depot', 'sharing']),
        ]);

        $request = Request::create('/api/device/poll', 'POST');
        $request->headers->set('Authorization', 'Bearer token-123');

        $passport = $authenticator->authenticate($request);
        $badge = $passport->getBadge(UserBadge::class);

        $this->assertNotNull($badge);
        $this->assertSame('device-a', $badge->getUserIdentifier());

        $user = $passport->getUser();
        $this->assertInstanceOf(DeviceApiUser::class, $user);
        /** @var DeviceApiUser $user */
        $this->assertSame('device-a', $user->getDeviceId());
        $this->assertSame(['depot', 'sharing'], $user->getAreas());
    }

    /**
     * Verifies authentication falls back to X-Device-Token header when Bearer token is absent.
     */
    public function testAuthenticateFallsBackToXDeviceTokenHeader(): void
    {
        $authenticator = $this->createAuthenticatorExpectingToken('token-from-header', [
            'deviceId' => 'device-b',
            'enabled' => '1',
            'areas' => serialize(['workshop']),
        ]);

        $request = Request::create('/api/device/poll', 'POST');
        $request->headers->set('X-Device-Token', ' token-from-header ');

        $passport = $authenticator->authenticate($request);
        $user = $passport->getUser();

        $this->assertInstanceOf(DeviceApiUser::class, $user);
        /** @var DeviceApiUser $user */
        $this->assertSame('device-b', $user->getDeviceId());
        $this->assertSame(['workshop'], $user->getAreas());
    }

    /**
     * Verifies Authorization Bearer token takes precedence over X-Device-Token.
     */
    public function testAuthenticatePrefersBearerAuthorizationOverXDeviceToken(): void
    {
        $authenticator = $this->createAuthenticatorExpectingToken('bearer-token', [
            'deviceId' => 'device-priority',
            'enabled' => '1',
            'areas' => serialize(['depot']),
        ]);

        $request = Request::create('/api/device/poll', 'POST');
        $request->headers->set('Authorization', 'Bearer bearer-token');
        $request->headers->set('X-Device-Token', 'x-header-token');

        $user = $authenticator->authenticate($request)->getUser();

        $this->assertInstanceOf(DeviceApiUser::class, $user);
        /** @var DeviceApiUser $user */
        $this->assertSame('device-priority', $user->getDeviceId());
    }

    /**
     * Verifies Bearer token values are trimmed before authentication lookup.
     */
    public function testAuthenticateTrimsBearerTokenBeforeAuthentication(): void
    {
        $authenticator = $this->createAuthenticatorExpectingToken('trimmed-token', [
            'deviceId' => 'device-trim',
            'enabled' => '1',
            'areas' => serialize(['sharing']),
        ]);

        $request = Request::create('/api/device/poll', 'POST');
        $request->headers->set('Authorization', 'Bearer   trimmed-token   ');

        $user = $authenticator->authenticate($request)->getUser();

        $this->assertInstanceOf(DeviceApiUser::class, $user);
        /** @var DeviceApiUser $user */
        $this->assertSame('device-trim', $user->getDeviceId());
        $this->assertSame(['sharing'], $user->getAreas());
    }

    /**
     * Verifies non-Bearer Authorization header still allows X-Device-Token fallback.
     */
    public function testAuthenticateFallsBackToXDeviceTokenWhenAuthorizationHeaderIsNotBearer(): void
    {
        $authenticator = $this->createAuthenticatorExpectingToken('x-fallback-token', [
            'deviceId' => 'device-x-fallback',
            'enabled' => '1',
            'areas' => serialize(['depot']),
        ]);

        $request = Request::create('/api/device/poll', 'POST');
        $request->headers->set('Authorization', 'Basic abc123');
        $request->headers->set('X-Device-Token', 'x-fallback-token');

        $user = $authenticator->authenticate($request)->getUser();

        $this->assertInstanceOf(DeviceApiUser::class, $user);
        /** @var DeviceApiUser $user */
        $this->assertSame('device-x-fallback', $user->getDeviceId());
    }

    /**
     * Verifies blank X-Device-Token input is treated as invalid authentication.
     */
    public function testAuthenticateThrowsWhenXDeviceTokenContainsOnlyWhitespace(): void
    {
        $db = $this->createConnectionExpectingNoLookup();
        $authenticator = new DeviceTokenAuthenticator(new DeviceAuthService($db));

        $request = Request::create('/api/device/poll', 'POST');
        $request->headers->set('X-Device-Token', '   ');

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('Invalid device token');

        $authenticator->authenticate($request);
    }

    /**
     * Verifies missing/invalid token input raises a user-facing authentication exception.
     */
    public function testAuthenticateThrowsWhenTokenIsInvalid(): void
    {
        $db = $this->createConnectionExpectingNoLookup();
        $authenticator = new DeviceTokenAuthenticator(new DeviceAuthService($db));

        $this->expectException(CustomUserMessageAuthenticationException::class);
        $this->expectExceptionMessage('Invalid device token');

        $authenticator->authenticate(Request::create('/api/device/poll', 'POST'));
    }

    /**
     * Verifies successful authentication does not override response handling.
     */
    public function testOnAuthenticationSuccessReturnsNull(): void
    {
        $authenticator = $this->createAuthenticatorWithStubConnection();

        $response = $authenticator->onAuthenticationSuccess(
            Request::create('/api/device/poll', 'POST'),
            $this->createStub(TokenInterface::class),
            'main',
        );

        $this->assertNull($response);
    }

    /**
     * Verifies failed authentication returns standard unauthorized JSON payload.
     */
    public function testOnAuthenticationFailureReturnsUnauthorizedJsonResponse(): void
    {
        $authenticator = $this->createAuthenticatorWithStubConnection();

        $response = $authenticator->onAuthenticationFailure(
            Request::create('/api/device/poll', 'POST'),
            new AuthenticationException('fail'),
        );
        $data = json_decode((string) $response->getContent(), true);

        $this->assertNotNull($response);
        $this->assertSame(401, $response->getStatusCode());
        $this->assertIsArray($data);
        $this->assertSame(['error' => 'unauthorized'], $data);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function createAuthenticatorExpectingToken(string $rawToken, array $row): DeviceTokenAuthenticator
    {
        $db = $this->createMock(Connection::class);
        $db->expects($this->once())
            ->method('fetchAssociative')
            ->with(
                $this->stringContains('FROM tl_co_device'),
                $this->callback(static fn (array $params): bool => isset($params['hash']) && hash('sha256', $rawToken) === $params['hash']),
            )
            ->willReturn($row)
        ;

        return new DeviceTokenAuthenticator(new DeviceAuthService($db));
    }

    private function createAuthenticatorWithStubConnection(): DeviceTokenAuthenticator
    {
        return new DeviceTokenAuthenticator(new DeviceAuthService($this->createStub(Connection::class)));
    }

    private function createConnectionExpectingNoLookup(): Connection
    {
        $db = $this->createMock(Connection::class);
        $db->expects($this->never())->method('fetchAssociative');

        return $db;
    }
}
