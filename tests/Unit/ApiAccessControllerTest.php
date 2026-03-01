<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Tests;

use Contao\FrontendUser;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use ZukunftsforumRissen\CommunityOffersBundle\Controller\Api\AccessController;
use ZukunftsforumRissen\CommunityOffersBundle\Service\AccessRequestService;
use ZukunftsforumRissen\CommunityOffersBundle\Service\AccessService;
use ZukunftsforumRissen\CommunityOffersBundle\Service\DoorJobService;
use ZukunftsforumRissen\CommunityOffersBundle\Service\LoggingService;

class ApiAccessControllerTest extends TestCase
{
    /**
     * Verifies whoami returns anonymous payload when no frontend user is authenticated.
     */
    public function testWhoamiReturnsAnonymousPayloadWhenUserIsNotFrontendUser(): void
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn(null);

        $accessService = $this->createMock(AccessService::class);
        $accessService->expects($this->never())->method('getGrantedAreasForMemberId');

        $accessRequestService = $this->createMock(AccessRequestService::class);
        $accessRequestService->expects($this->never())->method('getPendingRequestsForEmail');

        $logging = $this->createMock(LoggingService::class);
        $logging->expects($this->once())->method('initiateLogging')->with('door', 'community-offers');
        $logging->expects($this->once())->method('start')->with('whoami');

        $controller = $this->createController($security, $accessService, $accessRequestService, $logging);
        $request = Request::create('/api/door/whoami', 'GET', [], [], [], ['REMOTE_ADDR' => '127.0.0.1']);

        $response = $controller->whoami($request);
        $data = json_decode((string) $response->getContent(), true);

        $this->assertIsArray($data);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['authenticated' => false, 'areas' => []], $data);
    }

    /**
     * Verifies whoami returns member details, granted areas, and pending requests for authenticated users.
     */
    public function testWhoamiReturnsMemberAreasAndRequestsForAuthenticatedFrontendUser(): void
    {
        $user = $this->createFrontendUser(21, 'Ada', 'Lovelace', 'ada@example.org');

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $accessService = $this->createMock(AccessService::class);
        $accessService->expects($this->once())->method('getGrantedAreasForMemberId')->with(21)->willReturn(['depot']);

        $accessRequestService = $this->createMock(AccessRequestService::class);
        $accessRequestService->expects($this->once())->method('getPendingRequestsForEmail')->with('ada@example.org')->willReturn([
            'swap-house' => ['state' => 'pending_confirmed'],
        ]);

        $logging = $this->createMock(LoggingService::class);
        $logging->expects($this->once())->method('initiateLogging')->with('door', 'community-offers');
        $logging->expects($this->once())->method('start')->with('whoami');

        $controller = $this->createController($security, $accessService, $accessRequestService, $logging);

        $response = $controller->whoami(Request::create('/api/door/whoami', 'GET'));
        $data = json_decode((string) $response->getContent(), true);

        $this->assertIsArray($data);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($data['authenticated']);
        $this->assertSame(21, $data['member']['id']);
        $this->assertSame('Ada', $data['member']['firstname']);
        $this->assertSame(['depot'], $data['areas']);
        $this->assertSame('pending_confirmed', $data['requests']['swap-house']['state']);
    }

    /**
     * Verifies request endpoint returns 404 when the requested area slug is unknown.
     */
    public function testRequestReturnsUnknownAreaWhenSlugIsNotConfigured(): void
    {
        $user = $this->createFrontendUser(5, 'Max', 'Muster', 'max@example.org');

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $accessService = $this->createMock(AccessService::class);
        $accessService->expects($this->once())->method('getKnownAreas')->willReturn(['depot']);
        $accessService->expects($this->never())->method('getGrantedAreasForMemberId');

        $accessRequestService = $this->createMock(AccessRequestService::class);
        $accessRequestService->expects($this->never())->method('sendOrResendDoiForArea');

        $logging = $this->createMock(LoggingService::class);
        $logging->expects($this->once())->method('initiateLogging')->with('door', 'community-offers');
        $logging->expects($this->once())->method('start')->with('request_access', ['slug' => 'workshop']);

        $controller = $this->createController($security, $accessService, $accessRequestService, $logging);

        $response = $controller->request(Request::create('/api/door/request/workshop', 'POST'), 'workshop');
        $data = json_decode((string) $response->getContent(), true);

        $this->assertIsArray($data);
        $this->assertSame(404, $response->getStatusCode());
        $this->assertSame(['success' => false, 'message' => 'Unknown area'], $data);
    }

    /**
     * Verifies cooldown responses from the service are mapped to HTTP 429 with retry payload.
     */
    public function testRequestReturnsCooldownPayloadWhenServiceReportsCooldown(): void
    {
        $user = $this->createFrontendUser(7, 'Eva', 'Example', 'eva@example.org');

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $accessService = $this->createMock(AccessService::class);
        $accessService->method('getKnownAreas')->willReturn(['depot']);
        $accessService->method('getGrantedAreasForMemberId')->with(7)->willReturn([]);

        $accessRequestService = $this->createMock(AccessRequestService::class);
        $accessRequestService->expects($this->once())
            ->method('sendOrResendDoiForArea')
            ->with('Eva', 'Example', 'eva@example.org', 'depot')
            ->willReturn(['code' => 'cooldown', 'retryAfterSeconds' => 321])
        ;

        $logging = $this->createMock(LoggingService::class);

        $controller = $this->createController($security, $accessService, $accessRequestService, $logging);

        $response = $controller->request(Request::create('/api/door/request/depot', 'POST'), 'depot');
        $data = json_decode((string) $response->getContent(), true);

        $this->assertIsArray($data);
        $this->assertSame(429, $response->getStatusCode());
        $this->assertFalse($data['success']);
        $this->assertSame(321, $data['retryAfterSeconds']);
    }

    /**
     * Verifies already confirmed pending requests are returned as conflict responses.
     */
    public function testRequestReturnsConflictWhenAlreadyPendingConfirmed(): void
    {
        $user = $this->createFrontendUser(8, 'Eva', 'Example', 'eva@example.org');

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $accessService = $this->createMock(AccessService::class);
        $accessService->method('getKnownAreas')->willReturn(['depot']);
        $accessService->method('getGrantedAreasForMemberId')->with(8)->willReturn([]);

        $accessRequestService = $this->createMock(AccessRequestService::class);
        $accessRequestService->method('sendOrResendDoiForArea')->willReturn(['code' => 'pending_confirmed']);

        $controller = $this->createController(
            $security,
            $accessService,
            $accessRequestService,
            $this->createMock(LoggingService::class),
        );

        $response = $controller->request(Request::create('/api/door/request/depot', 'POST'), 'depot');
        $data = json_decode((string) $response->getContent(), true);

        $this->assertIsArray($data);
        $this->assertSame(409, $response->getStatusCode());
        $this->assertFalse($data['success']);
        $this->assertStringContainsString('bereits bestätigt', $data['message']);
    }

    /**
     * Verifies invalid email conditions are mapped to HTTP 400.
     */
    public function testRequestReturnsBadRequestWhenServiceReportsInvalidEmail(): void
    {
        $user = $this->createFrontendUser(9, 'Eva', 'Example', 'invalid-mail');

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $accessService = $this->createMock(AccessService::class);
        $accessService->method('getKnownAreas')->willReturn(['depot']);
        $accessService->method('getGrantedAreasForMemberId')->with(9)->willReturn([]);

        $accessRequestService = $this->createMock(AccessRequestService::class);
        $accessRequestService->method('sendOrResendDoiForArea')->willReturn(['code' => 'invalid_email']);

        $controller = $this->createController(
            $security,
            $accessService,
            $accessRequestService,
            $this->createMock(LoggingService::class),
        );

        $response = $controller->request(Request::create('/api/door/request/depot', 'POST'), 'depot');
        $data = json_decode((string) $response->getContent(), true);

        $this->assertIsArray($data);
        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(['success' => false, 'message' => 'Invalid email'], $data);
    }

    /**
     * Verifies unknown service result codes are handled as HTTP 500.
     */
    public function testRequestReturns500ForUnexpectedServiceCode(): void
    {
        $user = $this->createFrontendUser(10, 'Eva', 'Example', 'eva@example.org');

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $accessService = $this->createMock(AccessService::class);
        $accessService->method('getKnownAreas')->willReturn(['depot']);
        $accessService->method('getGrantedAreasForMemberId')->with(10)->willReturn([]);

        $accessRequestService = $this->createMock(AccessRequestService::class);
        $accessRequestService->method('sendOrResendDoiForArea')->willReturn(['code' => 'unknown_code']);

        $controller = $this->createController(
            $security,
            $accessService,
            $accessRequestService,
            $this->createMock(LoggingService::class),
        );

        $response = $controller->request(Request::create('/api/door/request/depot', 'POST'), 'depot');
        $data = json_decode((string) $response->getContent(), true);

        $this->assertIsArray($data);
        $this->assertSame(500, $response->getStatusCode());
        $this->assertSame(['success' => false, 'message' => 'Could not create request'], $data);
    }

    /**
     * Verifies open endpoint rejects areas not included in granted member areas.
     */
    public function testOpenReturnsForbiddenWhenAreaIsNotGranted(): void
    {
        $user = $this->createFrontendUser(12, 'No', 'Access', 'no-access@example.org');

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $accessService = $this->createMock(AccessService::class);
        $accessService->expects($this->once())->method('getGrantedAreasForMemberId')->with(12)->willReturn(['depot']);

        $accessRequestService = $this->createMock(AccessRequestService::class);
        $logging = $this->createMock(LoggingService::class);

        $controller = $this->createController($security, $accessService, $accessRequestService, $logging);

        $response = $controller->open(Request::create('/api/door/open/workshop', 'POST'), 'workshop');
        $data = json_decode((string) $response->getContent(), true);

        $this->assertIsArray($data);
        $this->assertSame(403, $response->getStatusCode());
        $this->assertSame(['success' => false, 'message' => 'Forbidden'], $data);
    }

    /**
     * Verifies HTTP 429 open responses forward Retry-After header from DoorJobService.
     */
    public function testOpenSetsRetryAfterHeaderWhenDoorJobServiceReturns429(): void
    {
        $user = $this->createFrontendUser(13, 'Rate', 'Limited', 'rate@example.org');

        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($user);

        $accessService = $this->createMock(AccessService::class);
        $accessService->method('getGrantedAreasForMemberId')->with(13)->willReturn(['depot']);

        $accessRequestService = $this->createMock(AccessRequestService::class);
        $logging = $this->createMock(LoggingService::class);

        $db = $this->createMock(Connection::class);
        $db->expects($this->exactly(3))->method('executeStatement')->willReturn(0);

        $rateItem = $this->createMock(CacheItemInterface::class);
        $rateItem->method('isHit')->willReturn(true);
        $rateItem->method('get')->willReturn([
            'count' => 3,
            'resetAt' => time() + 9,
        ]);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->expects($this->once())->method('getItem')->willReturn($rateItem);
        $cache->expects($this->never())->method('save');

        $doorJobs = new DoorJobService($db, $cache);

        $controller = $this->createController(
            $security,
            $accessService,
            $accessRequestService,
            $logging,
            $doorJobs,
        );

        $request = Request::create('/api/door/open/depot', 'POST', [], [], [], ['REMOTE_ADDR' => '127.0.0.1']);
        $response = $controller->open($request, 'depot');
        $data = json_decode((string) $response->getContent(), true);

        $this->assertIsArray($data);
        $this->assertSame(429, $response->getStatusCode());
        $this->assertFalse($data['success']);
        $this->assertSame('9', $response->headers->get('Retry-After'));
    }

    private function createController(
        Security $security,
        AccessService $accessService,
        AccessRequestService $accessRequestService,
        LoggingService $logging,
        DoorJobService|null $doorJobs = null,
    ): AccessController {
        $doorJobs ??= new DoorJobService(
            $this->createStub(Connection::class),
            $this->createStub(CacheItemPoolInterface::class),
        );

        return new AccessController($security, $accessService, $accessRequestService, $doorJobs, $logging);
    }

    private function createFrontendUser(int $id, string $firstname, string $lastname, string $email): FrontendUser
    {
        $user = $this->getMockBuilder(FrontendUser::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock()
        ;

        $user->id = $id;
        $user->firstname = $firstname;
        $user->lastname = $lastname;
        $user->email = $email;

        return $user;
    }
}
