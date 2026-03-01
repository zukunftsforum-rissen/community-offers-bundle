<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Tests;

use Contao\FrontendUser;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Container\ContainerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use ZukunftsforumRissen\CommunityOffersBundle\Controller\Api\AccessController;
use ZukunftsforumRissen\CommunityOffersBundle\Controller\Api\DeviceController;
use ZukunftsforumRissen\CommunityOffersBundle\Security\DeviceApiUser;
use ZukunftsforumRissen\CommunityOffersBundle\Service\AccessRequestService;
use ZukunftsforumRissen\CommunityOffersBundle\Service\AccessService;
use ZukunftsforumRissen\CommunityOffersBundle\Service\DoorJobService;
use ZukunftsforumRissen\CommunityOffersBundle\Service\LoggingService;

/**
 * Unit tests for device API controller behavior.
 *
 * Covered workflow scenarios include:
 * - open -> poll -> confirm success (final executed)
 * - open -> poll -> confirm with ok=false (final failed)
 * - open -> poll -> delayed confirm after timeout (final expired/TIMEOUT)
 * - open -> poll by device A, confirm by device B (rejected)
 * - open in area X, poll by device with area Y (no dispatch)
 */
class DeviceControllerTest extends TestCase
{
    /**
     * Verifies end-to-end device workflow: member opens door, device polls job, then confirms execution.
     */
    public function testWorkflowMemberOpenThenDevicePollAndConfirmExecutesJob(): void
    {
        $state = (object) ['rows' => [], 'nextId' => 1];
        $db = $this->createStatefulDoorJobConnection($state);
        $cache = new InMemoryCachePool();
        $doorJobs = new DoorJobService($db, $cache);

        $accessController = $this->createAccessControllerForWorkflow($doorJobs, ['depot']);
        $deviceController = $this->createControllerWithUser($doorJobs, new DeviceApiUser('device-1', ['depot']));

        $openRequest = Request::create('/api/door/open/depot', 'POST', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_USER_AGENT' => 'phpunit-workflow',
        ]);
        $openResponse = $accessController->open($openRequest, 'depot');
        $openData = json_decode((string) $openResponse->getContent(), true);

        $this->assertIsArray($openData);
        $this->assertSame(202, $openResponse->getStatusCode());
        $this->assertTrue($openData['success']);
        $this->assertArrayHasKey('jobId', $openData);

        $pollRequest = Request::create('/api/device/poll', 'POST', [], [], [], [], json_encode(['limit' => 1]));
        $pollResponse = $deviceController->poll($pollRequest);
        $pollData = json_decode((string) $pollResponse->getContent(), true);

        $this->assertIsArray($pollData);
        $this->assertSame(200, $pollResponse->getStatusCode());
        $this->assertCount(1, $pollData['jobs']);
        $this->assertSame($openData['jobId'], $pollData['jobs'][0]['jobId']);
        $this->assertSame('depot', $pollData['jobs'][0]['area']);
        $this->assertSame('open', $pollData['jobs'][0]['action']);

        $confirmRequest = Request::create('/api/device/confirm', 'POST', [], [], [], [], json_encode([
            'jobId' => $pollData['jobs'][0]['jobId'],
            'nonce' => $pollData['jobs'][0]['nonce'],
            'ok' => true,
            'meta' => ['source' => 'workflow-test'],
        ]));
        $confirmResponse = $deviceController->confirm($confirmRequest);
        $confirmData = json_decode((string) $confirmResponse->getContent(), true);

        $this->assertIsArray($confirmData);
        $this->assertSame(200, $confirmResponse->getStatusCode());
        $this->assertSame(['accepted' => true], $confirmData);

        $pollAgainResponse = $deviceController->poll(Request::create('/api/device/poll', 'POST'));
        $pollAgainData = json_decode((string) $pollAgainResponse->getContent(), true);

        $this->assertIsArray($pollAgainData);
        $this->assertSame([], $pollAgainData['jobs']);
        $this->assertSame(800, $pollAgainData['nextPollInMs']);
    }

    /**
     * Verifies confirm rejects wrong nonce after poll and accepts only with the dispatched nonce.
     */
    public function testWorkflowConfirmRejectsWrongNonceThenAcceptsCorrectNonce(): void
    {
        $state = (object) ['rows' => [], 'nextId' => 1];
        $db = $this->createStatefulDoorJobConnection($state);
        $cache = new InMemoryCachePool();
        $doorJobs = new DoorJobService($db, $cache);

        $accessController = $this->createAccessControllerForWorkflow($doorJobs, ['depot']);
        $deviceController = $this->createControllerWithUser($doorJobs, new DeviceApiUser('device-1', ['depot']));

        $openResponse = $accessController->open(
            Request::create('/api/door/open/depot', 'POST', [], [], [], ['REMOTE_ADDR' => '127.0.0.1']),
            'depot',
        );
        $openData = json_decode((string) $openResponse->getContent(), true);
        $this->assertIsArray($openData);
        $this->assertSame(202, $openResponse->getStatusCode());

        $pollResponse = $deviceController->poll(Request::create('/api/device/poll', 'POST'));
        $pollData = json_decode((string) $pollResponse->getContent(), true);

        $this->assertIsArray($pollData);
        $this->assertCount(1, $pollData['jobs']);

        $jobId = (int) $pollData['jobs'][0]['jobId'];
        $correctNonce = (string) $pollData['jobs'][0]['nonce'];

        $wrongConfirmResponse = $deviceController->confirm(Request::create('/api/device/confirm', 'POST', [], [], [], [], json_encode([
            'jobId' => $jobId,
            'nonce' => 'wrong-'.$correctNonce,
            'ok' => true,
        ])));
        $wrongConfirmData = json_decode((string) $wrongConfirmResponse->getContent(), true);

        $this->assertIsArray($wrongConfirmData);
        $this->assertSame(200, $wrongConfirmResponse->getStatusCode());
        $this->assertSame(['accepted' => false], $wrongConfirmData);

        $correctConfirmResponse = $deviceController->confirm(Request::create('/api/device/confirm', 'POST', [], [], [], [], json_encode([
            'jobId' => $jobId,
            'nonce' => $correctNonce,
            'ok' => true,
        ])));
        $correctConfirmData = json_decode((string) $correctConfirmResponse->getContent(), true);

        $this->assertIsArray($correctConfirmData);
        $this->assertSame(['accepted' => true], $correctConfirmData);
    }

    /**
     * Verifies workflow maps confirm(ok=false) to an accepted response and stores failed job status.
     */
    public function testWorkflowConfirmWithOkFalseMarksJobAsFailed(): void
    {
        $state = (object) ['rows' => [], 'nextId' => 1];
        $db = $this->createStatefulDoorJobConnection($state);
        $cache = new InMemoryCachePool();
        $doorJobs = new DoorJobService($db, $cache);

        $accessController = $this->createAccessControllerForWorkflow($doorJobs, ['depot']);
        $deviceController = $this->createControllerWithUser($doorJobs, new DeviceApiUser('device-1', ['depot']));

        $openResponse = $accessController->open(
            Request::create('/api/door/open/depot', 'POST', [], [], [], ['REMOTE_ADDR' => '127.0.0.1']),
            'depot',
        );
        $openData = json_decode((string) $openResponse->getContent(), true);

        $this->assertIsArray($openData);
        $this->assertSame(202, $openResponse->getStatusCode());

        $pollResponse = $deviceController->poll(Request::create('/api/device/poll', 'POST'));
        $pollData = json_decode((string) $pollResponse->getContent(), true);

        $this->assertIsArray($pollData);
        $this->assertCount(1, $pollData['jobs']);

        $jobId = (int) $pollData['jobs'][0]['jobId'];
        $nonce = (string) $pollData['jobs'][0]['nonce'];

        $confirmResponse = $deviceController->confirm(Request::create('/api/device/confirm', 'POST', [], [], [], [], json_encode([
            'jobId' => $jobId,
            'nonce' => $nonce,
            'ok' => false,
            'meta' => ['reason' => 'door-blocked'],
        ])));
        $confirmData = json_decode((string) $confirmResponse->getContent(), true);

        $this->assertIsArray($confirmData);
        $this->assertSame(200, $confirmResponse->getStatusCode());
        $this->assertSame(['accepted' => true], $confirmData);

        $this->assertArrayHasKey($jobId, $state->rows);
        $this->assertSame('failed', $state->rows[$jobId]['status']);
        $this->assertSame('ERR', $state->rows[$jobId]['resultCode']);
    }

    /**
     * Verifies workflow rejects delayed confirmation after confirm window and expires the dispatched job.
     */
    public function testWorkflowConfirmAfterTimeoutReturnsNotAcceptedAndExpiresJob(): void
    {
        $state = (object) ['rows' => [], 'nextId' => 1];
        $db = $this->createStatefulDoorJobConnection($state);
        $cache = new InMemoryCachePool();
        $doorJobs = new DoorJobService($db, $cache);

        $accessController = $this->createAccessControllerForWorkflow($doorJobs, ['depot']);
        $deviceController = $this->createControllerWithUser($doorJobs, new DeviceApiUser('device-1', ['depot']));

        $openResponse = $accessController->open(
            Request::create('/api/door/open/depot', 'POST', [], [], [], ['REMOTE_ADDR' => '127.0.0.1']),
            'depot',
        );
        $openData = json_decode((string) $openResponse->getContent(), true);

        $this->assertIsArray($openData);
        $this->assertSame(202, $openResponse->getStatusCode());

        $pollResponse = $deviceController->poll(Request::create('/api/device/poll', 'POST'));
        $pollData = json_decode((string) $pollResponse->getContent(), true);

        $this->assertIsArray($pollData);
        $this->assertCount(1, $pollData['jobs']);

        $jobId = (int) $pollData['jobs'][0]['jobId'];
        $nonce = (string) $pollData['jobs'][0]['nonce'];

        $this->assertArrayHasKey($jobId, $state->rows);
        $state->rows[$jobId]['dispatchedAt'] = time() - $doorJobs->getConfirmWindowSeconds() - 1;

        $confirmResponse = $deviceController->confirm(Request::create('/api/device/confirm', 'POST', [], [], [], [], json_encode([
            'jobId' => $jobId,
            'nonce' => $nonce,
            'ok' => true,
        ])));
        $confirmData = json_decode((string) $confirmResponse->getContent(), true);

        $this->assertIsArray($confirmData);
        $this->assertSame(200, $confirmResponse->getStatusCode());
        $this->assertSame(['accepted' => false], $confirmData);
        $this->assertSame('expired', $state->rows[$jobId]['status']);
        $this->assertSame('TIMEOUT', $state->rows[$jobId]['resultCode']);
    }

    /**
     * Verifies workflow rejects confirmation from a different device than the dispatcher.
     */
    public function testWorkflowConfirmFromDifferentDeviceIsRejected(): void
    {
        $state = (object) ['rows' => [], 'nextId' => 1];
        $db = $this->createStatefulDoorJobConnection($state);
        $cache = new InMemoryCachePool();
        $doorJobs = new DoorJobService($db, $cache);

        $accessController = $this->createAccessControllerForWorkflow($doorJobs, ['depot']);
        $dispatchingDeviceController = $this->createControllerWithUser($doorJobs, new DeviceApiUser('device-1', ['depot']));
        $foreignDeviceController = $this->createControllerWithUser($doorJobs, new DeviceApiUser('device-2', ['depot']));

        $openResponse = $accessController->open(
            Request::create('/api/door/open/depot', 'POST', [], [], [], ['REMOTE_ADDR' => '127.0.0.1']),
            'depot',
        );
        $openData = json_decode((string) $openResponse->getContent(), true);

        $this->assertIsArray($openData);
        $this->assertSame(202, $openResponse->getStatusCode());

        $pollResponse = $dispatchingDeviceController->poll(Request::create('/api/device/poll', 'POST'));
        $pollData = json_decode((string) $pollResponse->getContent(), true);

        $this->assertIsArray($pollData);
        $this->assertCount(1, $pollData['jobs']);

        $jobId = (int) $pollData['jobs'][0]['jobId'];
        $nonce = (string) $pollData['jobs'][0]['nonce'];

        $confirmResponse = $foreignDeviceController->confirm(Request::create('/api/device/confirm', 'POST', [], [], [], [], json_encode([
            'jobId' => $jobId,
            'nonce' => $nonce,
            'ok' => true,
        ])));
        $confirmData = json_decode((string) $confirmResponse->getContent(), true);

        $this->assertIsArray($confirmData);
        $this->assertSame(200, $confirmResponse->getStatusCode());
        $this->assertSame(['accepted' => false], $confirmData);
        $this->assertSame('dispatched', $state->rows[$jobId]['status']);
        $this->assertSame('device-1', $state->rows[$jobId]['dispatchToDeviceId']);
    }

    /**
     * Verifies workflow keeps jobs undispatched when device polls without matching area permission.
     */
    public function testWorkflowPollWithDifferentAreaReturnsNoJobs(): void
    {
        $state = (object) ['rows' => [], 'nextId' => 1];
        $db = $this->createStatefulDoorJobConnection($state);
        $cache = new InMemoryCachePool();
        $doorJobs = new DoorJobService($db, $cache);

        $accessController = $this->createAccessControllerForWorkflow($doorJobs, ['depot']);
        $wrongAreaDeviceController = $this->createControllerWithUser($doorJobs, new DeviceApiUser('device-9', ['workshop']));

        $openResponse = $accessController->open(
            Request::create('/api/door/open/depot', 'POST', [], [], [], ['REMOTE_ADDR' => '127.0.0.1']),
            'depot',
        );
        $openData = json_decode((string) $openResponse->getContent(), true);

        $this->assertIsArray($openData);
        $this->assertSame(202, $openResponse->getStatusCode());
        $this->assertArrayHasKey('jobId', $openData);

        $jobId = (int) $openData['jobId'];
        $this->assertSame('pending', $state->rows[$jobId]['status']);

        $pollResponse = $wrongAreaDeviceController->poll(Request::create('/api/device/poll', 'POST'));
        $pollData = json_decode((string) $pollResponse->getContent(), true);

        $this->assertIsArray($pollData);
        $this->assertSame(200, $pollResponse->getStatusCode());
        $this->assertSame([], $pollData['jobs']);
        $this->assertSame(800, $pollData['nextPollInMs']);
        $this->assertSame('pending', $state->rows[$jobId]['status']);
        $this->assertSame('', $state->rows[$jobId]['dispatchToDeviceId']);
    }

    /**
     * Verifies poll endpoint returns 401 when no authenticated device user is available.
     */
    public function testPollReturnsUnauthorizedWhenNoDeviceUserIsAuthenticated(): void
    {
        $controller = $this->createControllerWithUser($this->createJobsServiceWithoutExpectations(), null);

        $response = $controller->poll(Request::create('/api/device/poll', 'POST'));
        $data = json_decode((string) $response->getContent(), true);

        $this->assertIsArray($data);
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame(['error' => 'unauthorized'], $data);
    }

    /**
     * Verifies poll endpoint returns dispatched jobs payload for authenticated devices.
     */
    public function testPollReturnsDispatchedJobsPayloadForAuthenticatedDevice(): void
    {
        $db = $this->createMock(Connection::class);
        $db->expects($this->once())->method('beginTransaction');
        $db->expects($this->once())->method('commit');
        $db->expects($this->exactly(3))
            ->method('executeStatement')
            ->willReturnOnConsecutiveCalls(1, 1, 1)
        ;
        $db->expects($this->once())
            ->method('fetchFirstColumn')
            ->willReturn([11])
        ;
        $db->expects($this->once())
            ->method('fetchAssociative')
            ->with($this->stringContains('SELECT id, area, nonce, expiresAt'), ['id' => 11])
            ->willReturn([
                'id' => 11,
                'jobId' => 11,
                'area' => 'depot',
                'nonce' => 'nonce-11',
                'expiresAt' => time() + 10,
                'expiresInMs' => 9000,
            ])
        ;

        $jobs = new DoorJobService($db, $this->createStub(CacheItemPoolInterface::class));
        $controller = $this->createControllerWithUser($jobs, new DeviceApiUser('device-1', ['depot']));

        $request = Request::create('/api/device/poll', 'POST', [], [], [], [], json_encode(['limit' => 2]));
        $response = $controller->poll($request);
        $data = json_decode((string) $response->getContent(), true);

        $this->assertIsArray($data);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertCount(1, $data['jobs']);
        $this->assertSame(11, $data['jobs'][0]['jobId']);
        $this->assertSame('depot', $data['jobs'][0]['area']);
        $this->assertSame('open', $data['jobs'][0]['action']);
        $this->assertSame('nonce-11', $data['jobs'][0]['nonce']);
        $this->assertSame(9000, $data['jobs'][0]['expiresInMs']);
        $this->assertSame(200, $data['nextPollInMs']);
    }

    /**
     * Verifies poll endpoint returns empty jobs and slower polling interval when queue is empty.
     */
    public function testPollReturnsNextPoll800WhenNoJobsAreAvailable(): void
    {
        $db = $this->createMock(Connection::class);
        $db->expects($this->once())->method('beginTransaction');
        $db->expects($this->once())->method('commit');
        $db->expects($this->exactly(2))->method('executeStatement')->willReturn(1);
        $db->expects($this->once())->method('fetchFirstColumn')->willReturn([]);
        $db->expects($this->never())->method('fetchAssociative');

        $jobs = new DoorJobService($db, $this->createStub(CacheItemPoolInterface::class));
        $controller = $this->createControllerWithUser($jobs, new DeviceApiUser('device-1', ['depot']));

        $request = Request::create('/api/device/poll', 'POST', [], [], [], [], json_encode(['limit' => 2]));
        $response = $controller->poll($request);
        $data = json_decode((string) $response->getContent(), true);

        $this->assertIsArray($data);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame([], $data['jobs']);
        $this->assertSame(800, $data['nextPollInMs']);
    }

    /**
     * Verifies confirm endpoint returns 401 when no authenticated device user is available.
     */
    public function testConfirmReturnsUnauthorizedWhenNoDeviceUserIsAuthenticated(): void
    {
        $controller = $this->createControllerWithUser($this->createJobsServiceWithoutExpectations(), null);

        $request = Request::create('/api/device/confirm', 'POST', [], [], [], [], json_encode([
            'jobId' => 7,
            'nonce' => 'nonce-7',
            'ok' => true,
        ]));
        $response = $controller->confirm($request);
        $data = json_decode((string) $response->getContent(), true);

        $this->assertIsArray($data);
        $this->assertSame(401, $response->getStatusCode());
        $this->assertSame(['error' => 'unauthorized'], $data);
    }

    /**
     * Verifies confirm endpoint validates required fields and returns 400 on malformed input.
     */
    public function testConfirmReturnsBadRequestForMissingFields(): void
    {
        $controller = $this->createControllerWithUser(
            $this->createJobsServiceWithoutExpectations(),
            new DeviceApiUser('device-1', ['depot']),
        );

        $request = Request::create('/api/device/confirm', 'POST', [], [], [], [], json_encode(['jobId' => 0, 'nonce' => '']));
        $response = $controller->confirm($request);
        $data = json_decode((string) $response->getContent(), true);

        $this->assertIsArray($data);
        $this->assertSame(400, $response->getStatusCode());
        $this->assertSame(['error' => 'bad_request'], $data);
    }

    /**
     * Verifies successful job confirmations are accepted and mapped to HTTP 200 payload.
     */
    public function testConfirmReturnsAcceptedFromServiceResult(): void
    {
        $db = $this->createMock(Connection::class);
        $db->expects($this->once())
            ->method('fetchAssociative')
            ->with($this->stringContains('FROM tl_co_door_job'), ['id' => 7])
            ->willReturn([
                'id' => 7,
                'status' => 'executed',
                'dispatchToDeviceId' => 'device-1',
                'nonce' => 'nonce-7',
                'dispatchedAt' => time(),
            ])
        ;

        $jobs = new DoorJobService($db, $this->createStub(CacheItemPoolInterface::class));
        $controller = $this->createControllerWithUser($jobs, new DeviceApiUser('device-1', ['depot']));

        $request = Request::create('/api/device/confirm', 'POST', [], [], [], [], json_encode([
            'jobId' => 7,
            'nonce' => 'nonce-7',
            'ok' => true,
            'meta' => ['source' => 'test'],
        ]));
        $response = $controller->confirm($request);
        $data = json_decode((string) $response->getContent(), true);

        $this->assertIsArray($data);
        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame(['accepted' => true], $data);
    }

    private function createControllerWithUser(DoorJobService $jobs, UserInterface|null $user): DeviceController
    {
        $controller = new DeviceController($jobs);

        $tokenStorage = $this->createMock(TokenStorageInterface::class);

        if (null === $user) {
            $tokenStorage->method('getToken')->willReturn(null);
        } else {
            $token = $this->createMock(TokenInterface::class);
            $token->method('getUser')->willReturn($user);
            $tokenStorage->method('getToken')->willReturn($token);
        }

        $container = new class($tokenStorage) implements ContainerInterface {
            public function __construct(
                private readonly TokenStorageInterface $tokenStorage,
            ) {
            }

            public function has(string $id): bool
            {
                return 'security.token_storage' === $id;
            }

            public function get(string $id): mixed
            {
                if ('security.token_storage' === $id) {
                    return $this->tokenStorage;
                }

                throw new \RuntimeException('Unknown service: '.$id);
            }
        };

        $controller->setContainer($container);

        return $controller;
    }

    private function createJobsServiceWithoutExpectations(): DoorJobService
    {
        $db = $this->createStub(Connection::class);
        return new DoorJobService($db, $this->createStub(CacheItemPoolInterface::class));
    }

    private function createAccessControllerForWorkflow(DoorJobService $doorJobs, array $grantedAreas): AccessController
    {
        $security = $this->createMock(Security::class);
        $security->method('getUser')->willReturn($this->createFrontendUser(42, 'Workflow', 'User', 'workflow@example.org'));

        $accessService = $this->createMock(AccessService::class);
        $accessService->method('getGrantedAreasForMemberId')->with(42)->willReturn($grantedAreas);

        $accessRequestService = $this->createMock(AccessRequestService::class);
        $logging = $this->createMock(LoggingService::class);

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

    private function createStatefulDoorJobConnection(object $state): Connection
    {
        $db = $this->createMock(Connection::class);

        $db->method('insert')->willReturnCallback(static function (string $table, array $data) use ($state): int {
            if ('tl_co_door_job' !== $table) {
                return 0;
            }

            $id = $state->nextId++;
            $state->rows[$id] = array_merge($data, ['id' => $id]);

            return 1;
        });

        $db->method('lastInsertId')->willReturnCallback(static fn (): string => (string) ($state->nextId - 1));

        $db->method('fetchFirstColumn')->willReturnCallback(static function (string $query, array $params = []) use ($state): array {
            if (!str_contains($query, "FROM tl_co_door_job") || !str_contains($query, "status='pending'")) {
                return [];
            }

            $areas = (array) ($params['areas'] ?? []);
            $now = (int) ($params['now'] ?? time());

            $ids = [];
            foreach ($state->rows as $row) {
                if ('pending' !== (string) ($row['status'] ?? '')) {
                    continue;
                }
                if (!\in_array((string) ($row['area'] ?? ''), $areas, true)) {
                    continue;
                }
                $expiresAt = (int) ($row['expiresAt'] ?? 0);
                if (0 !== $expiresAt && $expiresAt < $now) {
                    continue;
                }
                $ids[] = (int) ($row['id'] ?? 0);
            }

            usort($ids, static function (int $left, int $right) use ($state): int {
                $leftCreatedAt = (int) ($state->rows[$left]['createdAt'] ?? 0);
                $rightCreatedAt = (int) ($state->rows[$right]['createdAt'] ?? 0);

                return $leftCreatedAt <=> $rightCreatedAt;
            });

            if (preg_match('/LIMIT\s+(\d+)/', $query, $matches)) {
                $ids = array_slice($ids, 0, max(0, (int) $matches[1]));
            }

            return $ids;
        });

        $db->method('fetchAssociative')->willReturnCallback(static function (string $query, array $params = []) use ($state): array|false {
            if (str_contains($query, 'SELECT id, expiresAt, status')) {
                $memberId = (int) ($params['memberId'] ?? 0);
                $area = (string) ($params['area'] ?? '');
                $now = (int) ($params['now'] ?? time());
                $dispatchedCutoff = (int) ($params['dispatchedCutoff'] ?? ($now - 30));

                $candidates = [];
                foreach ($state->rows as $row) {
                    if ((int) ($row['requestedByMemberId'] ?? 0) !== $memberId) {
                        continue;
                    }
                    if ((string) ($row['area'] ?? '') !== $area) {
                        continue;
                    }

                    $status = (string) ($row['status'] ?? '');
                    if ('pending' === $status) {
                        $expiresAt = (int) ($row['expiresAt'] ?? 0);
                        if (0 !== $expiresAt && $expiresAt < $now) {
                            continue;
                        }
                        $candidates[] = $row;
                        continue;
                    }

                    if ('dispatched' === $status && (int) ($row['dispatchedAt'] ?? 0) >= $dispatchedCutoff) {
                        $candidates[] = $row;
                    }
                }

                if ([] === $candidates) {
                    return false;
                }

                usort($candidates, static fn (array $left, array $right): int => ((int) ($right['createdAt'] ?? 0)) <=> ((int) ($left['createdAt'] ?? 0)));
                $active = $candidates[0];

                return [
                    'id' => (int) $active['id'],
                    'expiresAt' => (int) ($active['expiresAt'] ?? 0),
                    'status' => (string) ($active['status'] ?? ''),
                ];
            }

            if (str_contains($query, 'SELECT id, area, nonce, expiresAt')) {
                $id = (int) ($params['id'] ?? 0);
                if (!isset($state->rows[$id])) {
                    return false;
                }

                $row = $state->rows[$id];

                return [
                    'id' => (int) $row['id'],
                    'jobId' => (int) $row['id'],
                    'area' => (string) ($row['area'] ?? ''),
                    'nonce' => (string) ($row['nonce'] ?? ''),
                    'expiresAt' => (int) ($row['expiresAt'] ?? 0),
                    'expiresInMs' => max(0, ((int) ($row['expiresAt'] ?? 0) - time()) * 1000),
                ];
            }

            if (str_contains($query, 'SELECT id, status, dispatchToDeviceId, nonce, dispatchedAt')) {
                $id = (int) ($params['id'] ?? 0);
                if (!isset($state->rows[$id])) {
                    return false;
                }

                $row = $state->rows[$id];

                return [
                    'id' => (int) $row['id'],
                    'status' => (string) ($row['status'] ?? ''),
                    'dispatchToDeviceId' => (string) ($row['dispatchToDeviceId'] ?? ''),
                    'nonce' => (string) ($row['nonce'] ?? ''),
                    'dispatchedAt' => (int) ($row['dispatchedAt'] ?? 0),
                ];
            }

            return false;
        });

        $db->method('executeStatement')->willReturnCallback(static function (string $query, array $params = []) use ($state): int {
            if (str_contains($query, "SET status='expired'") && str_contains($query, "status = 'pending'")) {
                $now = (int) ($params['now'] ?? time());
                $changed = 0;

                foreach ($state->rows as &$row) {
                    if ('pending' !== (string) ($row['status'] ?? '')) {
                        continue;
                    }
                    $expiresAt = (int) ($row['expiresAt'] ?? 0);
                    if ($expiresAt > 0 && $expiresAt < $now) {
                        $row['status'] = 'expired';
                        ++$changed;
                    }
                }
                unset($row);

                return $changed;
            }

            if (str_contains($query, "status ='pending'")) {
                $now = (int) ($params['now'] ?? time());
                $changed = 0;

                foreach ($state->rows as &$row) {
                    if ('pending' !== (string) ($row['status'] ?? '')) {
                        continue;
                    }
                    $expiresAt = (int) ($row['expiresAt'] ?? 0);
                    if ($expiresAt > 0 && $expiresAt < $now) {
                        $row['status'] = 'expired';
                        ++$changed;
                    }
                }
                unset($row);

                return $changed;
            }

            if (str_contains($query, "status ='dispatched'")) {
                $cutoff = (int) ($params['cutoff'] ?? (time() - 30));
                $changed = 0;

                foreach ($state->rows as &$row) {
                    if ('dispatched' === (string) ($row['status'] ?? '') && (int) ($row['dispatchedAt'] ?? 0) < $cutoff) {
                        $row['status'] = 'expired';
                        $row['resultCode'] = 'TIMEOUT';
                        $row['resultMessage'] = 'Confirm timeout';
                        ++$changed;
                    }
                }
                unset($row);

                return $changed;
            }

            if (str_contains($query, 'SET status=\'dispatched\'')) {
                $id = (int) ($params['id'] ?? 0);
                if (!isset($state->rows[$id])) {
                    return 0;
                }

                $row = $state->rows[$id];
                $now = (int) ($params['now'] ?? time());
                $expiresAt = (int) ($row['expiresAt'] ?? 0);

                if ('pending' !== (string) ($row['status'] ?? '')) {
                    return 0;
                }
                if (0 !== $expiresAt && $expiresAt < $now) {
                    return 0;
                }

                $state->rows[$id]['status'] = 'dispatched';
                $state->rows[$id]['dispatchToDeviceId'] = (string) ($params['deviceId'] ?? '');
                $state->rows[$id]['dispatchedAt'] = $now;
                $state->rows[$id]['nonce'] = (string) ($params['nonce'] ?? '');
                $state->rows[$id]['attempts'] = (int) ($row['attempts'] ?? 0) + 1;

                return 1;
            }

            if (str_contains($query, 'SET status=\'expired\'') && str_contains($query, 'WHERE id=:id AND status=\'dispatched\'')) {
                $id = (int) ($params['id'] ?? 0);
                if (!isset($state->rows[$id])) {
                    return 0;
                }
                if ('dispatched' !== (string) ($state->rows[$id]['status'] ?? '')) {
                    return 0;
                }

                $state->rows[$id]['status'] = 'expired';
                $state->rows[$id]['resultCode'] = 'TIMEOUT';
                $state->rows[$id]['resultMessage'] = 'Confirm timeout';

                return 1;
            }

            if (str_contains($query, 'SET status=:status') && str_contains($query, 'AND nonce=:nonce')) {
                $id = (int) ($params['id'] ?? 0);
                if (!isset($state->rows[$id])) {
                    return 0;
                }

                $row = $state->rows[$id];
                if ('dispatched' !== (string) ($row['status'] ?? '')) {
                    return 0;
                }
                if ((string) ($row['dispatchToDeviceId'] ?? '') !== (string) ($params['deviceId'] ?? '')) {
                    return 0;
                }
                if ((string) ($row['nonce'] ?? '') !== (string) ($params['nonce'] ?? '')) {
                    return 0;
                }

                $state->rows[$id]['status'] = (string) ($params['status'] ?? 'failed');
                $state->rows[$id]['executedAt'] = (int) ($params['now'] ?? time());
                $state->rows[$id]['resultCode'] = (string) ($params['resultCode'] ?? '');
                $state->rows[$id]['resultMessage'] = (string) ($params['resultMessage'] ?? '');

                return 1;
            }

            return 0;
        });

        return $db;
    }
}

final class InMemoryCachePool implements CacheItemPoolInterface
{
    /** @var array<string, InMemoryCacheItem> */
    private array $items = [];

    public function getItem(string $key): CacheItemInterface
    {
        if (!isset($this->items[$key])) {
            $this->items[$key] = new InMemoryCacheItem($key, null, false);
        }

        return $this->items[$key];
    }

    public function getItems(array $keys = []): iterable
    {
        $result = [];
        foreach ($keys as $key) {
            $result[$key] = $this->getItem((string) $key);
        }

        return $result;
    }

    public function hasItem(string $key): bool
    {
        return isset($this->items[$key]) && $this->items[$key]->isHit();
    }

    public function clear(): bool
    {
        $this->items = [];

        return true;
    }

    public function deleteItem(string $key): bool
    {
        unset($this->items[$key]);

        return true;
    }

    public function deleteItems(array $keys): bool
    {
        foreach ($keys as $key) {
            unset($this->items[(string) $key]);
        }

        return true;
    }

    public function save(CacheItemInterface $item): bool
    {
        if ($item instanceof InMemoryCacheItem) {
            $this->items[$item->getKey()] = $item;

            return true;
        }

        return false;
    }

    public function saveDeferred(CacheItemInterface $item): bool
    {
        return $this->save($item);
    }

    public function commit(): bool
    {
        return true;
    }
}

final class InMemoryCacheItem implements CacheItemInterface
{
    public function __construct(
        private readonly string $key,
        private mixed $value,
        private bool $hit,
        private int $expiresAt = 0,
    ) {
    }

    public function getKey(): string
    {
        return $this->key;
    }

    public function get(): mixed
    {
        if ($this->expiresAt > 0 && time() >= $this->expiresAt) {
            $this->hit = false;
            $this->value = null;
        }

        return $this->value;
    }

    public function isHit(): bool
    {
        if ($this->expiresAt > 0 && time() >= $this->expiresAt) {
            $this->hit = false;
            $this->value = null;
        }

        return $this->hit;
    }

    public function set(mixed $value): static
    {
        $this->value = $value;
        $this->hit = true;

        return $this;
    }

    public function expiresAt(\DateTimeInterface|null $expiration): static
    {
        $this->expiresAt = null === $expiration ? 0 : $expiration->getTimestamp();

        return $this;
    }

    public function expiresAfter(int|\DateInterval|null $time): static
    {
        if (null === $time) {
            $this->expiresAt = 0;

            return $this;
        }

        if ($time instanceof \DateInterval) {
            $dateTime = new \DateTimeImmutable();
            $this->expiresAt = $dateTime->add($time)->getTimestamp();

            return $this;
        }

        $this->expiresAt = time() + max(0, $time);

        return $this;
    }
}
