<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Tests;

use Contao\CoreBundle\Framework\ContaoFramework;
use Contao\Database;
use Contao\Database\Result;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use ZukunftsforumRissen\CommunityOffersBundle\Controller\AccessConfirmController;
use ZukunftsforumRissen\CommunityOffersBundle\Service\AccessRequestService;

/**
 * Unit test suite for AccessRequestService.
 *
 * Note on test level:
 * - These tests remain unit tests because all external boundaries are replaced
 *   by test doubles (Contao Database singleton, Router, Mailer, Framework).
 * - The workflow tests are integration-like at method-flow level, but they do
 *   not boot a Symfony kernel, do not use a real database, and do not perform
 *   real HTTP requests.
 */
class AccessRequestServiceTest extends TestCase
{
    /**
     * Resets the static Contao database singleton between tests.
     */
    protected function tearDown(): void
    {
        $this->setDatabaseSingleton(null);
    }

    /**
     * Verifies invalid email input is rejected before any DB access or mail sending.
     */
    public function testTryCreateRequestAndSendDoiMailReturnsInvalidEmailForInvalidAddress(): void
    {
        $framework = $this->createMock(ContaoFramework::class);
        $framework->expects($this->once())->method('initialize');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->never())->method('send');

        $db = $this->createPrepareOnlyDatabaseMock();
        $db->expects($this->never())->method('prepare');
        $this->setDatabaseSingleton($db);

        $service = $this->createService($framework, null, $mailer);

        $result = $service->tryCreateRequestAndSendDoiMail('Max', 'Mustermann', 'not-an-email', '', '', '', '', ['workshop']);

        $this->assertSame('invalid_email', $result);
    }

    /**
     * Verifies an existing unapproved request returns already_open and does not send mail.
     */
    public function testTryCreateRequestAndSendDoiMailReturnsAlreadyOpenForExistingUnapprovedRequest(): void
    {
        $framework = $this->createMock(ContaoFramework::class);
        $framework->expects($this->once())->method('initialize');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->never())->method('send');

        $db = $this->createPrepareOnlyDatabaseMock();
        $db->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('SELECT id FROM tl_co_access_request'))
            ->willReturn($this->statementReturning(new Result([['id' => 42]], 'existing')))
        ;
        $this->setDatabaseSingleton($db);

        $service = $this->createService($framework, null, $mailer);

        $result = $service->tryCreateRequestAndSendDoiMail('Max', 'Mustermann', 'MAX@Example.org', '', '', '', '', ['workshop']);

        $this->assertSame('already_open', $result);
    }

    /**
     * Verifies the happy path creates a request, sanitizes payload fields, and sends DOI mail.
     */
    public function testTryCreateRequestAndSendDoiMailCreatesRequestAndSendsMailOnSuccess(): void
    {
        $framework = $this->createMock(ContaoFramework::class);
        $framework->expects($this->once())->method('initialize');

        $router = $this->createMock(RouterInterface::class);
        $router->expects($this->once())
            ->method('generate')
            ->with(
                'community_offers_access_confirm',
                $this->arrayHasKey('token'),
                UrlGeneratorInterface::ABSOLUTE_URL,
            )
            ->willReturn('https://example.org/confirm/create')
        ;

        $capturedMail = null;
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(static function (Email $mail) use (&$capturedMail): bool {
                $capturedMail = $mail;

                return true;
            }))
        ;

        $insertCalls = [];

        $db = $this->createPrepareOnlyDatabaseMock();
        $db->method('prepare')->willReturnCallback(function (string $query) use (&$insertCalls): object {
            if (str_contains($query, 'SELECT id FROM tl_co_access_request')) {
                return $this->statementReturning(new Result([], 'not-open'));
            }

            if (str_contains($query, 'INSERT INTO tl_co_access_request')) {
                return new class(static function (array $args) use (&$insertCalls): void {
                    $insertCalls[] = $args;
                }) {
                    /** @var callable(array<int, mixed>): void */
                    private $onExecute;

                    /** @param callable(array<int, mixed>): void $onExecute */
                    public function __construct(callable $onExecute)
                    {
                        $this->onExecute = $onExecute;
                    }

                    public function execute(mixed ...$args): Result
                    {
                        ($this->onExecute)($args);

                        return new Result([], 'inserted');
                    }
                };
            }

            throw new \RuntimeException('Unexpected query: '.$query);
        });
        $this->setDatabaseSingleton($db);

        $service = $this->createService($framework, $router, $mailer);

        $result = $service->tryCreateRequestAndSendDoiMail(
            '  Max   <b>M.</b> ',
            ' Mustermann ',
            ' MAX@Example.ORG ',
            '  Hauptstrasse   12  ',
            ' 22587 ',
            ' Hamburg ',
            ' +49 (0)40-12x3 ',
            ['depot', 'invalid-area', 'sharing'],
        );

        $this->assertSame('ok', $result);
        $this->assertCount(1, $insertCalls);
        $this->assertSame('Max M.', $insertCalls[0][1]);
        $this->assertSame('Mustermann', $insertCalls[0][2]);
        $this->assertSame('max@example.org', $insertCalls[0][3]);
        $this->assertSame('+49 040-123', $insertCalls[0][4]);
        $this->assertSame('Hauptstrasse 12', $insertCalls[0][5]);
        $this->assertSame('22587', $insertCalls[0][6]);
        $this->assertSame('Hamburg', $insertCalls[0][7]);
        $this->assertSame(serialize(['depot', 'sharing']), $insertCalls[0][8]);

        $this->assertInstanceOf(Email::class, $capturedMail);
        $this->assertNotNull($capturedMail);
        /** @var Email $capturedMail */
        $text = $capturedMail->getTextBody() ?? '';
        $this->assertStringContainsString('https://example.org/confirm/create', $text);
        $this->assertStringContainsString('Lebensmittel-Depot, Sharingstation', $text);
    }

    /**
     * Verifies unconfirmed matching requests inside cooldown return cooldown with retryAfterSeconds.
     */
    public function testSendOrResendDoiForAreaReturnsCooldownForRecentUnconfirmedRequest(): void
    {
        $framework = $this->createMock(ContaoFramework::class);
        $framework->expects($this->once())->method('initialize');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->never())->method('send');

        $row = [[
            'id' => 7,
            'tstamp' => time() - 100,
            'requestedAreas' => serialize(['depot']),
            'emailConfirmed' => '',
            'approved' => '',
        ]];

        $db = $this->createPrepareOnlyDatabaseMock();
        $db->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('SELECT id, tstamp, requestedAreas, emailConfirmed, approved'))
            ->willReturn($this->statementReturning(new Result($row, 'pending')))
        ;
        $this->setDatabaseSingleton($db);

        $service = $this->createService($framework, null, $mailer);

        $result = $service->sendOrResendDoiForArea('Max', 'Mustermann', 'max@example.org', 'depot');

        $this->assertSame('cooldown', $result['code']);
        $this->assertArrayHasKey('retryAfterSeconds', $result);
        $this->assertGreaterThan(0, $result['retryAfterSeconds']);
        $this->assertLessThanOrEqual(600, $result['retryAfterSeconds']);
    }

    /**
     * Verifies confirmed-but-unapproved requests return pending_confirmed without resending mail.
     */
    public function testSendOrResendDoiForAreaReturnsPendingConfirmedWhenRequestIsAlreadyConfirmed(): void
    {
        $framework = $this->createMock(ContaoFramework::class);
        $framework->expects($this->once())->method('initialize');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->never())->method('send');

        $row = [[
            'id' => 13,
            'tstamp' => time() - 300,
            'requestedAreas' => serialize(['workshop']),
            'emailConfirmed' => '1',
            'approved' => '',
        ]];

        $db = $this->createPrepareOnlyDatabaseMock();
        $db->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('SELECT id, tstamp, requestedAreas, emailConfirmed, approved'))
            ->willReturn($this->statementReturning(new Result($row, 'pending-confirmed')))
        ;
        $this->setDatabaseSingleton($db);

        $service = $this->createService($framework, null, $mailer);

        $result = $service->sendOrResendDoiForArea('Max', 'Mustermann', 'max@example.org', 'workshop');

        $this->assertSame(['code' => 'pending_confirmed'], $result);
    }

    /**
     * Verifies unknown areas are rejected as invalid_email safety response.
     */
    public function testSendOrResendDoiForAreaReturnsInvalidEmailCodeForUnknownArea(): void
    {
        $framework = $this->createMock(ContaoFramework::class);
        $framework->expects($this->once())->method('initialize');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->never())->method('send');

        $db = $this->createPrepareOnlyDatabaseMock();
        $db->expects($this->never())->method('prepare');
        $this->setDatabaseSingleton($db);

        $service = $this->createService($framework, null, $mailer);

        $result = $service->sendOrResendDoiForArea('Max', 'Mustermann', 'max@example.org', 'unknown-area');

        $this->assertSame(['code' => 'invalid_email'], $result);
    }

    /**
     * Verifies invalid email input is rejected before area lookup and DB access.
     */
    public function testSendOrResendDoiForAreaReturnsInvalidEmailCodeForInvalidEmail(): void
    {
        $framework = $this->createMock(ContaoFramework::class);
        $framework->expects($this->once())->method('initialize');

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->never())->method('send');

        $db = $this->createPrepareOnlyDatabaseMock();
        $db->expects($this->never())->method('prepare');
        $this->setDatabaseSingleton($db);

        $service = $this->createService($framework, null, $mailer);

        $result = $service->sendOrResendDoiForArea('Max', 'Mustermann', 'not-an-email', 'depot');

        $this->assertSame(['code' => 'invalid_email'], $result);
    }

    /**
     * Verifies a missing matching open request triggers INSERT plus DOI mail send.
     */
    public function testSendOrResendDoiForAreaCreatesNewRequestAndSendsMailWhenNoMatchExists(): void
    {
        $framework = $this->createMock(ContaoFramework::class);
        $framework->expects($this->once())->method('initialize');

        $router = $this->createMock(RouterInterface::class);
        $router->expects($this->once())
            ->method('generate')
            ->with(
                'community_offers_access_confirm',
                $this->arrayHasKey('token'),
                UrlGeneratorInterface::ABSOLUTE_URL,
            )
            ->willReturn('https://example.org/confirm/new-token')
        ;

        $capturedMail = null;
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(static function (Email $mail) use (&$capturedMail): bool {
                $capturedMail = $mail;

                return true;
            }))
        ;

        $insertCalls = [];

        $db = $this->createPrepareOnlyDatabaseMock();
        $db->method('prepare')->willReturnCallback(function (string $query) use (&$insertCalls): object {
            if (str_contains($query, 'SELECT id, tstamp, requestedAreas, emailConfirmed, approved')) {
                return $this->statementReturning(new Result([], 'no-match'));
            }

            if (str_contains($query, 'INSERT INTO tl_co_access_request')) {
                return new class(static function (array $args) use (&$insertCalls): void {
                    $insertCalls[] = $args;
                }) {
                    /** @var callable(array<int, mixed>): void */
                    private $onExecute;

                    /** @param callable(array<int, mixed>): void $onExecute */
                    public function __construct(callable $onExecute)
                    {
                        $this->onExecute = $onExecute;
                    }

                    public function execute(mixed ...$args): Result
                    {
                        ($this->onExecute)($args);

                        return new Result([], 'inserted');
                    }
                };
            }

            throw new \RuntimeException('Unexpected query: '.$query);
        });
        $this->setDatabaseSingleton($db);

        $service = $this->createService($framework, $router, $mailer);

        $result = $service->sendOrResendDoiForArea('Max', 'Mustermann', 'max@example.org', 'depot');

        $this->assertSame(['code' => 'ok'], $result);
        $this->assertCount(1, $insertCalls);
        $this->assertSame('max@example.org', $insertCalls[0][3]);
        $this->assertSame(serialize(['depot']), $insertCalls[0][8]);

        $this->assertInstanceOf(Email::class, $capturedMail);
        $this->assertNotNull($capturedMail);
        /** @var Email $capturedMail */
        $text = $capturedMail->getTextBody() ?? '';
        $this->assertStringContainsString('https://example.org/confirm/new-token', $text);
        $this->assertStringContainsString('Lebensmittel-Depot', $text);
    }

    /**
     * Verifies expired cooldown on an unconfirmed request performs token refresh and resend.
     */
    public function testSendOrResendDoiForAreaResendsMailAfterCooldownAndUpdatesToken(): void
    {
        $framework = $this->createMock(ContaoFramework::class);
        $framework->expects($this->once())->method('initialize');

        $router = $this->createMock(RouterInterface::class);
        $router->expects($this->once())
            ->method('generate')
            ->with(
                'community_offers_access_confirm',
                $this->arrayHasKey('token'),
                UrlGeneratorInterface::ABSOLUTE_URL,
            )
            ->willReturn('https://example.org/confirm/token')
        ;

        $capturedMail = null;
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(static function (Email $mail) use (&$capturedMail): bool {
                $capturedMail = $mail;

                return true;
            }))
        ;

        $row = [[
            'id' => 19,
            'tstamp' => time() - 1000,
            'requestedAreas' => serialize(['swap-house']),
            'emailConfirmed' => '',
            'approved' => '',
        ]];

        $updateCalls = [];

        $db = $this->createPrepareOnlyDatabaseMock();
        $db->method('prepare')->willReturnCallback(function (string $query) use ($row, &$updateCalls): object {
            if (str_contains($query, 'SELECT id, tstamp, requestedAreas, emailConfirmed, approved')) {
                return $this->statementReturning(new Result($row, 'pending'));
            }

            if (str_contains($query, 'UPDATE tl_co_access_request')) {
                return new class(static function (array $args) use (&$updateCalls): void {
                    $updateCalls[] = $args;
                }) {
                    /** @var callable(array<int, mixed>): void */
                    private $onExecute;

                    /** @param callable(array<int, mixed>): void $onExecute */
                    public function __construct(callable $onExecute)
                    {
                        $this->onExecute = $onExecute;
                    }

                    public function execute(mixed ...$args): Result
                    {
                        ($this->onExecute)($args);

                        return new Result([], 'update');
                    }
                };
            }

            throw new \RuntimeException('Unexpected query: '.$query);
        });
        $this->setDatabaseSingleton($db);

        $service = $this->createService($framework, $router, $mailer);

        $result = $service->sendOrResendDoiForArea('Max', 'Mustermann', 'max@example.org', 'swap-house');

        $this->assertSame(['code' => 'ok'], $result);
        $this->assertCount(1, $updateCalls);
        $this->assertSame(19, $updateCalls[0][3]);

        $this->assertInstanceOf(Email::class, $capturedMail);
        $this->assertNotNull($capturedMail);
        /** @var Email $capturedMail */
        $text = $capturedMail->getTextBody() ?? '';
        $this->assertStringContainsString('https://example.org/confirm/token', $text);
        $this->assertStringContainsString('Tauschhaus', $text);
        $this->assertSame('Bitte bestätigen Sie Ihre E-Mail-Adresse', $capturedMail->getSubject());
    }

    /**
     * Verifies unknown confirmation tokens return false.
     */
    public function testConfirmTokenReturnsFalseWhenTokenDoesNotExist(): void
    {
        $framework = $this->createMock(ContaoFramework::class);
        $framework->expects($this->once())->method('initialize');

        $db = $this->createPrepareOnlyDatabaseMock();
        $db->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('SELECT * FROM tl_co_access_request WHERE token=?'))
            ->willReturn($this->statementReturning(new Result([], 'not-found')))
        ;
        $this->setDatabaseSingleton($db);

        $service = $this->createService($framework);

        $this->assertFalse($service->confirmToken('missing-token'));
    }

    /**
     * Verifies already confirmed rows are not confirmed again.
     */
    public function testConfirmTokenReturnsFalseWhenAlreadyConfirmed(): void
    {
        $framework = $this->createMock(ContaoFramework::class);
        $framework->expects($this->once())->method('initialize');

        $db = $this->createPrepareOnlyDatabaseMock();
        $db->method('prepare')->willReturnCallback(function (string $query): object {
            if (str_contains($query, 'SELECT * FROM tl_co_access_request WHERE token=?')) {
                return $this->statementReturning(new Result([[
                    'id' => 7,
                    'emailConfirmed' => '1',
                    'tokenExpiresAt' => time() + 3600,
                ]], 'already-confirmed'));
            }

            throw new \RuntimeException('Unexpected query: '.$query);
        });
        $this->setDatabaseSingleton($db);

        $service = $this->createService($framework);

        $this->assertFalse($service->confirmToken('already-confirmed-token'));
    }

    /**
     * Verifies expired confirmation tokens are rejected.
     */
    public function testConfirmTokenReturnsFalseWhenExpired(): void
    {
        $framework = $this->createMock(ContaoFramework::class);
        $framework->expects($this->once())->method('initialize');

        $db = $this->createPrepareOnlyDatabaseMock();
        $db->method('prepare')->willReturnCallback(function (string $query): object {
            if (str_contains($query, 'SELECT * FROM tl_co_access_request WHERE token=?')) {
                return $this->statementReturning(new Result([[
                    'id' => 8,
                    'emailConfirmed' => '',
                    'tokenExpiresAt' => time() - 1,
                ]], 'expired'));
            }

            throw new \RuntimeException('Unexpected query: '.$query);
        });
        $this->setDatabaseSingleton($db);

        $service = $this->createService($framework);

        $this->assertFalse($service->confirmToken('expired-token'));
    }

    /**
     * Verifies valid confirmation updates the row and returns true.
     */
    public function testConfirmTokenMarksRowAsConfirmedWhenValid(): void
    {
        $framework = $this->createMock(ContaoFramework::class);
        $framework->expects($this->once())->method('initialize');

        $updateCalls = [];

        $db = $this->createPrepareOnlyDatabaseMock();
        $db->method('prepare')->willReturnCallback(function (string $query) use (&$updateCalls): object {
            if (str_contains($query, 'SELECT * FROM tl_co_access_request WHERE token=?')) {
                return $this->statementReturning(new Result([[
                    'id' => 9,
                    'emailConfirmed' => '',
                    'tokenExpiresAt' => time() + 3600,
                ]], 'valid'));
            }

            if (str_contains($query, "UPDATE tl_co_access_request SET emailConfirmed='1'")) {
                return new class(static function (array $args) use (&$updateCalls): void {
                    $updateCalls[] = $args;
                }) {
                    /** @var callable(array<int, mixed>): void */
                    private $onExecute;

                    /** @param callable(array<int, mixed>): void $onExecute */
                    public function __construct(callable $onExecute)
                    {
                        $this->onExecute = $onExecute;
                    }

                    public function execute(mixed ...$args): Result
                    {
                        ($this->onExecute)($args);

                        return new Result([], 'updated');
                    }
                };
            }

            throw new \RuntimeException('Unexpected query: '.$query);
        });
        $this->setDatabaseSingleton($db);

        $service = $this->createService($framework);

        $this->assertTrue($service->confirmToken('valid-token'));
        $this->assertCount(1, $updateCalls);
        $this->assertSame(9, $updateCalls[0][1]);
    }

    /**
     * Verifies empty tokenExpiresAt is treated as non-expiring and still confirms successfully.
     */
    public function testConfirmTokenMarksRowAsConfirmedWhenExpiryIsEmpty(): void
    {
        $framework = $this->createMock(ContaoFramework::class);
        $framework->expects($this->once())->method('initialize');

        $updateCalls = [];

        $db = $this->createPrepareOnlyDatabaseMock();
        $db->method('prepare')->willReturnCallback(function (string $query) use (&$updateCalls): object {
            if (str_contains($query, 'SELECT * FROM tl_co_access_request WHERE token=?')) {
                return $this->statementReturning(new Result([[
                    'id' => 14,
                    'emailConfirmed' => '',
                    'tokenExpiresAt' => '',
                ]], 'no-expiry'));
            }

            if (str_contains($query, "UPDATE tl_co_access_request SET emailConfirmed='1'")) {
                return new class(static function (array $args) use (&$updateCalls): void {
                    $updateCalls[] = $args;
                }) {
                    /** @var callable(array<int, mixed>): void */
                    private $onExecute;

                    /** @param callable(array<int, mixed>): void $onExecute */
                    public function __construct(callable $onExecute)
                    {
                        $this->onExecute = $onExecute;
                    }

                    public function execute(mixed ...$args): Result
                    {
                        ($this->onExecute)($args);

                        return new Result([], 'updated-no-expiry');
                    }
                };
            }

            throw new \RuntimeException('Unexpected query: '.$query);
        });
        $this->setDatabaseSingleton($db);

        $service = $this->createService($framework);

        $this->assertTrue($service->confirmToken('token-without-expiry'));
        $this->assertCount(1, $updateCalls);
        $this->assertSame(14, $updateCalls[0][1]);
    }

    /**
     * Verifies invalid email in pending lookup short-circuits to an empty map.
     */
    public function testGetPendingRequestsForEmailReturnsEmptyArrayForInvalidEmail(): void
    {
        $framework = $this->createMock(ContaoFramework::class);
        $framework->expects($this->once())->method('initialize');

        $db = $this->createPrepareOnlyDatabaseMock();
        $db->expects($this->never())->method('prepare');
        $this->setDatabaseSingleton($db);

        $service = $this->createService($framework);

        $this->assertSame([], $service->getPendingRequestsForEmail('not-an-email'));
    }

    /**
     * Verifies pending map composition per area, including confirmed/unconfirmed states.
     */
    public function testGetPendingRequestsForEmailBuildsPendingStatusMapPerArea(): void
    {
        $framework = $this->createMock(ContaoFramework::class);
        $framework->expects($this->once())->method('initialize');

        $rows = [
            [
                'tstamp' => time() - 100,
                'requestedAreas' => serialize(['depot', 'invalid-area']),
                'emailConfirmed' => '',
                'approved' => '',
            ],
            [
                'tstamp' => time() - 1000,
                'requestedAreas' => serialize(['sharing']),
                'emailConfirmed' => '1',
                'approved' => '',
            ],
            [
                'tstamp' => time() - 500,
                'requestedAreas' => serialize(['depot']),
                'emailConfirmed' => '1',
                'approved' => '',
            ],
        ];

        $db = $this->createPrepareOnlyDatabaseMock();
        $db->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('SELECT tstamp, requestedAreas, emailConfirmed, approved'))
            ->willReturn($this->statementReturning(new Result($rows, 'pending-map')))
        ;
        $this->setDatabaseSingleton($db);

        $service = $this->createService($framework);

        $result = $service->getPendingRequestsForEmail('MAX@EXAMPLE.ORG');

        $this->assertArrayHasKey('depot', $result);
        $this->assertSame('pending_unconfirmed', $result['depot']['state']);
        $this->assertArrayHasKey('retryAfterSeconds', $result['depot']);
        $this->assertGreaterThanOrEqual(450, $result['depot']['retryAfterSeconds']);
        $this->assertLessThanOrEqual(600, $result['depot']['retryAfterSeconds']);

        $this->assertArrayHasKey('sharing', $result);
        $this->assertSame('pending_confirmed', $result['sharing']['state']);
        $this->assertArrayNotHasKey('retryAfterSeconds', $result['sharing']);
    }

    /**
     * Verifies retryAfterSeconds is clamped to 0 for old unconfirmed requests.
     */
    public function testGetPendingRequestsForEmailClampsRetryAfterToZeroForOldUnconfirmedRequest(): void
    {
        $framework = $this->createMock(ContaoFramework::class);
        $framework->expects($this->once())->method('initialize');

        $rows = [[
            'tstamp' => time() - 5000,
            'requestedAreas' => serialize(['workshop']),
            'emailConfirmed' => '',
            'approved' => '',
        ]];

        $db = $this->createPrepareOnlyDatabaseMock();
        $db->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('SELECT tstamp, requestedAreas, emailConfirmed, approved'))
            ->willReturn($this->statementReturning(new Result($rows, 'pending-old-unconfirmed')))
        ;
        $this->setDatabaseSingleton($db);

        $service = $this->createService($framework);

        $result = $service->getPendingRequestsForEmail('max@example.org');

        $this->assertArrayHasKey('workshop', $result);
        $this->assertSame('pending_unconfirmed', $result['workshop']['state']);
        $this->assertSame(0, $result['workshop']['retryAfterSeconds']);
    }

    /**
     * Verifies invalid/unreadable requestedAreas payloads are skipped safely.
     */
    public function testGetPendingRequestsForEmailSkipsEntriesWithInvalidRequestedAreasPayload(): void
    {
        $framework = $this->createMock(ContaoFramework::class);
        $framework->expects($this->once())->method('initialize');

        $rows = [[
            'tstamp' => time() - 200,
            'requestedAreas' => null,
            'emailConfirmed' => '',
            'approved' => '',
        ]];

        $db = $this->createPrepareOnlyDatabaseMock();
        $db->expects($this->once())
            ->method('prepare')
            ->with($this->stringContains('SELECT tstamp, requestedAreas, emailConfirmed, approved'))
            ->willReturn($this->statementReturning(new Result($rows, 'pending-invalid-payload')))
        ;
        $this->setDatabaseSingleton($db);

        $service = $this->createService($framework);

        $result = $service->getPendingRequestsForEmail('max@example.org');

        $this->assertSame([], $result);
    }

    /**
     * Verifies tryCreate stores an empty area list when all requested areas are invalid.
     */
    public function testTryCreateRequestAndSendDoiMailStoresEmptyAreaListWhenAllAreasAreInvalid(): void
    {
        $framework = $this->createMock(ContaoFramework::class);
        $framework->expects($this->once())->method('initialize');

        $router = $this->createMock(RouterInterface::class);
        $router->expects($this->once())
            ->method('generate')
            ->with(
                'community_offers_access_confirm',
                $this->arrayHasKey('token'),
                UrlGeneratorInterface::ABSOLUTE_URL,
            )
            ->willReturn('https://example.org/confirm/no-areas')
        ;

        $capturedMail = null;
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(static function (Email $mail) use (&$capturedMail): bool {
                $capturedMail = $mail;

                return true;
            }))
        ;

        $insertCalls = [];

        $db = $this->createPrepareOnlyDatabaseMock();
        $db->method('prepare')->willReturnCallback(function (string $query) use (&$insertCalls): object {
            if (str_contains($query, 'SELECT id FROM tl_co_access_request')) {
                return $this->statementReturning(new Result([], 'not-open'));
            }

            if (str_contains($query, 'INSERT INTO tl_co_access_request')) {
                return new class(static function (array $args) use (&$insertCalls): void {
                    $insertCalls[] = $args;
                }) {
                    /** @var callable(array<int, mixed>): void */
                    private $onExecute;

                    /** @param callable(array<int, mixed>): void $onExecute */
                    public function __construct(callable $onExecute)
                    {
                        $this->onExecute = $onExecute;
                    }

                    public function execute(mixed ...$args): Result
                    {
                        ($this->onExecute)($args);

                        return new Result([], 'inserted-no-areas');
                    }
                };
            }

            throw new \RuntimeException('Unexpected query: '.$query);
        });
        $this->setDatabaseSingleton($db);

        $service = $this->createService($framework, $router, $mailer);

        $result = $service->tryCreateRequestAndSendDoiMail(
            'Max',
            'Mustermann',
            'max@example.org',
            'Straße 1',
            '22587',
            'Hamburg',
            '+49 40 1234',
            ['unknown-1', 'unknown-2'],
        );

        $this->assertSame('ok', $result);
        $this->assertCount(1, $insertCalls);
        $this->assertSame(serialize([]), $insertCalls[0][8]);

        $this->assertInstanceOf(Email::class, $capturedMail);
        $this->assertNotNull($capturedMail);
        /** @var Email $capturedMail */
        $text = $capturedMail->getTextBody() ?? '';
        $this->assertStringContainsString('- Sie möchten nutzen: -', $text);
    }

    /**
     * Verifies the workflow create request -> confirm DOI -> pending_confirmed for same area.
     */
    public function testWorkflowCreateConfirmAndResendFlowReturnsPendingConfirmed(): void
    {
        $framework = $this->createMock(ContaoFramework::class);
        $framework->expects($this->exactly(3))->method('initialize');

        $router = $this->createMock(RouterInterface::class);
        $router->expects($this->once())
            ->method('generate')
            ->willReturnCallback(static function (string $route, array $params): string {
                return 'https://example.org/access/confirm/'.($params['token'] ?? '');
            })
        ;

        $capturedMail = null;
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(static function (Email $mail) use (&$capturedMail): bool {
                $capturedMail = $mail;

                return true;
            }))
        ;

        $rows = [];
        $nextId = 1;

        $db = $this->createPrepareOnlyDatabaseMock();
        $db->method('prepare')->willReturnCallback(function (string $query) use (&$rows, &$nextId): object {
            if (str_contains($query, "SELECT id FROM tl_co_access_request WHERE email=? AND approved='' LIMIT 1")) {
                return new class(static function (array $args) use (&$rows): Result {
                    $email = (string) ($args[0] ?? '');
                    $matches = [];

                    foreach ($rows as $row) {
                        if ((string) ($row['email'] ?? '') === $email && '' === (string) ($row['approved'] ?? '')) {
                            $matches[] = ['id' => (int) $row['id']];
                            break;
                        }
                    }

                    return new Result($matches, 'existing');
                }) {
                    /** @var callable(array<int, mixed>): Result */
                    private $onExecute;

                    /** @param callable(array<int, mixed>): Result $onExecute */
                    public function __construct(callable $onExecute)
                    {
                        $this->onExecute = $onExecute;
                    }

                    public function execute(mixed ...$args): Result
                    {
                        return ($this->onExecute)($args);
                    }
                };
            }

            if (str_contains($query, 'INSERT INTO tl_co_access_request')) {
                return new class(static function (array $args) use (&$rows, &$nextId): Result {
                    $rows[] = [
                        'id' => $nextId++,
                        'tstamp' => (int) ($args[0] ?? 0),
                        'firstname' => (string) ($args[1] ?? ''),
                        'lastname' => (string) ($args[2] ?? ''),
                        'email' => (string) ($args[3] ?? ''),
                        'mobile' => (string) ($args[4] ?? ''),
                        'street' => (string) ($args[5] ?? ''),
                        'postal' => (string) ($args[6] ?? ''),
                        'city' => (string) ($args[7] ?? ''),
                        'requestedAreas' => (string) ($args[8] ?? ''),
                        'token' => (string) ($args[9] ?? ''),
                        'emailConfirmed' => (string) ($args[10] ?? ''),
                        'approved' => (string) ($args[11] ?? ''),
                        'tokenExpiresAt' => (int) ($args[12] ?? 0),
                    ];

                    return new Result([], 'inserted-workflow');
                }) {
                    /** @var callable(array<int, mixed>): Result */
                    private $onExecute;

                    /** @param callable(array<int, mixed>): Result $onExecute */
                    public function __construct(callable $onExecute)
                    {
                        $this->onExecute = $onExecute;
                    }

                    public function execute(mixed ...$args): Result
                    {
                        return ($this->onExecute)($args);
                    }
                };
            }

            if (str_contains($query, 'SELECT * FROM tl_co_access_request WHERE token=? LIMIT 1')) {
                return new class(static function (array $args) use (&$rows): Result {
                    $tokenHash = (string) ($args[0] ?? '');

                    foreach ($rows as $row) {
                        if ((string) ($row['token'] ?? '') === $tokenHash) {
                            return new Result([$row], 'confirm-hit');
                        }
                    }

                    return new Result([], 'confirm-miss');
                }) {
                    /** @var callable(array<int, mixed>): Result */
                    private $onExecute;

                    /** @param callable(array<int, mixed>): Result $onExecute */
                    public function __construct(callable $onExecute)
                    {
                        $this->onExecute = $onExecute;
                    }

                    public function execute(mixed ...$args): Result
                    {
                        return ($this->onExecute)($args);
                    }
                };
            }

            if (str_contains($query, "UPDATE tl_co_access_request SET emailConfirmed='1'")) {
                return new class(static function (array $args) use (&$rows): Result {
                    $id = (int) ($args[1] ?? 0);

                    foreach ($rows as &$row) {
                        if ((int) ($row['id'] ?? 0) === $id) {
                            $row['emailConfirmed'] = '1';
                            $row['tstamp'] = (int) ($args[0] ?? $row['tstamp']);
                        }
                    }
                    unset($row);

                    return new Result([], 'confirmed');
                }) {
                    /** @var callable(array<int, mixed>): Result */
                    private $onExecute;

                    /** @param callable(array<int, mixed>): Result $onExecute */
                    public function __construct(callable $onExecute)
                    {
                        $this->onExecute = $onExecute;
                    }

                    public function execute(mixed ...$args): Result
                    {
                        return ($this->onExecute)($args);
                    }
                };
            }

            if (str_contains($query, 'SELECT id, tstamp, requestedAreas, emailConfirmed, approved')) {
                return new class(static function (array $args) use (&$rows): Result {
                    $email = (string) ($args[0] ?? '');
                    $matches = [];

                    foreach ($rows as $row) {
                        if ((string) ($row['email'] ?? '') === $email && '' === (string) ($row['approved'] ?? '')) {
                            $matches[] = [
                                'id' => (int) $row['id'],
                                'tstamp' => (int) $row['tstamp'],
                                'requestedAreas' => (string) $row['requestedAreas'],
                                'emailConfirmed' => (string) $row['emailConfirmed'],
                                'approved' => (string) $row['approved'],
                            ];
                        }
                    }

                    usort($matches, static fn (array $a, array $b): int => (int) $b['id'] <=> (int) $a['id']);

                    return new Result($matches, 'pending-workflow');
                }) {
                    /** @var callable(array<int, mixed>): Result */
                    private $onExecute;

                    /** @param callable(array<int, mixed>): Result $onExecute */
                    public function __construct(callable $onExecute)
                    {
                        $this->onExecute = $onExecute;
                    }

                    public function execute(mixed ...$args): Result
                    {
                        return ($this->onExecute)($args);
                    }
                };
            }

            throw new \RuntimeException('Unexpected query in workflow test: '.$query);
        });
        $this->setDatabaseSingleton($db);

        $service = $this->createService($framework, $router, $mailer);

        $createResult = $service->tryCreateRequestAndSendDoiMail(
            'Max',
            'Mustermann',
            'max@example.org',
            'Musterweg 1',
            '22559',
            'Hamburg',
            '+49 123',
            ['depot'],
        );

        $this->assertSame('ok', $createResult);
        $this->assertInstanceOf(Email::class, $capturedMail);
        $this->assertNotNull($capturedMail);
        /** @var Email $capturedMail */
        $text = $capturedMail->getTextBody() ?? '';

        $this->assertMatchesRegularExpression('~https://example\.org/access/confirm/([a-f0-9]{64})~', $text);
        preg_match('~https://example\.org/access/confirm/([a-f0-9]{64})~', $text, $m);
        $token = $m[1] ?? '';
        $this->assertNotSame('', $token);

        $confirmController = new AccessConfirmController($service);
        $confirmResponse = $confirmController->confirm($token);

        $this->assertSame('/zugangsanfrage-bestaetigt', $confirmResponse->getTargetUrl());

        $resendResult = $service->sendOrResendDoiForArea('Max', 'Mustermann', 'max@example.org', 'depot');

        $this->assertSame(['code' => 'pending_confirmed'], $resendResult);
    }

    /**
     * Verifies workflow behavior for expired DOI token and resend after cooldown.
     *
     * Flow:
     * - create request (DOI mail is sent),
     * - confirm with expired token (invalid redirect),
     * - resend for same area after cooldown (returns ok and sends a new DOI mail).
     */
    public function testWorkflowExpiredTokenThenResendReturnsOkAfterCooldown(): void
    {
        $framework = $this->createMock(ContaoFramework::class);
        $framework->expects($this->exactly(3))->method('initialize');

        $router = $this->createMock(RouterInterface::class);
        $router->expects($this->exactly(2))
            ->method('generate')
            ->willReturnCallback(static function (string $route, array $params): string {
                return 'https://example.org/access/confirm/'.($params['token'] ?? '');
            })
        ;

        $capturedMails = [];
        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->exactly(2))
            ->method('send')
            ->with($this->callback(static function (Email $mail) use (&$capturedMails): bool {
                $capturedMails[] = $mail;

                return true;
            }))
        ;

        $rows = [];
        $nextId = 1;

        $db = $this->createPrepareOnlyDatabaseMock();
        $db->method('prepare')->willReturnCallback(function (string $query) use (&$rows, &$nextId): object {
            if (str_contains($query, "SELECT id FROM tl_co_access_request WHERE email=? AND approved='' LIMIT 1")) {
                return new class(static function (array $args) use (&$rows): Result {
                    $email = (string) ($args[0] ?? '');
                    $matches = [];

                    foreach ($rows as $row) {
                        if ((string) ($row['email'] ?? '') === $email && '' === (string) ($row['approved'] ?? '')) {
                            $matches[] = ['id' => (int) $row['id']];
                            break;
                        }
                    }

                    return new Result($matches, 'existing-expired-flow');
                }) {
                    /** @var callable(array<int, mixed>): Result */
                    private $onExecute;

                    /** @param callable(array<int, mixed>): Result $onExecute */
                    public function __construct(callable $onExecute)
                    {
                        $this->onExecute = $onExecute;
                    }

                    public function execute(mixed ...$args): Result
                    {
                        return ($this->onExecute)($args);
                    }
                };
            }

            if (str_contains($query, 'INSERT INTO tl_co_access_request')) {
                return new class(static function (array $args) use (&$rows, &$nextId): Result {
                    $rows[] = [
                        'id' => $nextId++,
                        'tstamp' => time() - 1000,
                        'firstname' => (string) ($args[1] ?? ''),
                        'lastname' => (string) ($args[2] ?? ''),
                        'email' => (string) ($args[3] ?? ''),
                        'mobile' => (string) ($args[4] ?? ''),
                        'street' => (string) ($args[5] ?? ''),
                        'postal' => (string) ($args[6] ?? ''),
                        'city' => (string) ($args[7] ?? ''),
                        'requestedAreas' => (string) ($args[8] ?? ''),
                        'token' => (string) ($args[9] ?? ''),
                        'emailConfirmed' => (string) ($args[10] ?? ''),
                        'approved' => (string) ($args[11] ?? ''),
                        'tokenExpiresAt' => time() - 1,
                    ];

                    return new Result([], 'inserted-expired-flow');
                }) {
                    /** @var callable(array<int, mixed>): Result */
                    private $onExecute;

                    /** @param callable(array<int, mixed>): Result $onExecute */
                    public function __construct(callable $onExecute)
                    {
                        $this->onExecute = $onExecute;
                    }

                    public function execute(mixed ...$args): Result
                    {
                        return ($this->onExecute)($args);
                    }
                };
            }

            if (str_contains($query, 'SELECT * FROM tl_co_access_request WHERE token=? LIMIT 1')) {
                return new class(static function (array $args) use (&$rows): Result {
                    $tokenHash = (string) ($args[0] ?? '');

                    foreach ($rows as $row) {
                        if ((string) ($row['token'] ?? '') === $tokenHash) {
                            return new Result([$row], 'confirm-expired-flow-hit');
                        }
                    }

                    return new Result([], 'confirm-expired-flow-miss');
                }) {
                    /** @var callable(array<int, mixed>): Result */
                    private $onExecute;

                    /** @param callable(array<int, mixed>): Result $onExecute */
                    public function __construct(callable $onExecute)
                    {
                        $this->onExecute = $onExecute;
                    }

                    public function execute(mixed ...$args): Result
                    {
                        return ($this->onExecute)($args);
                    }
                };
            }

            if (str_contains($query, 'SELECT id, tstamp, requestedAreas, emailConfirmed, approved')) {
                return new class(static function (array $args) use (&$rows): Result {
                    $email = (string) ($args[0] ?? '');
                    $matches = [];

                    foreach ($rows as $row) {
                        if ((string) ($row['email'] ?? '') === $email && '' === (string) ($row['approved'] ?? '')) {
                            $matches[] = [
                                'id' => (int) $row['id'],
                                'tstamp' => (int) $row['tstamp'],
                                'requestedAreas' => (string) $row['requestedAreas'],
                                'emailConfirmed' => (string) $row['emailConfirmed'],
                                'approved' => (string) $row['approved'],
                            ];
                        }
                    }

                    usort($matches, static fn (array $a, array $b): int => (int) $b['id'] <=> (int) $a['id']);

                    return new Result($matches, 'pending-expired-flow');
                }) {
                    /** @var callable(array<int, mixed>): Result */
                    private $onExecute;

                    /** @param callable(array<int, mixed>): Result $onExecute */
                    public function __construct(callable $onExecute)
                    {
                        $this->onExecute = $onExecute;
                    }

                    public function execute(mixed ...$args): Result
                    {
                        return ($this->onExecute)($args);
                    }
                };
            }

            if (str_contains($query, 'UPDATE tl_co_access_request') && str_contains($query, 'SET tstamp=?, token=?, tokenExpiresAt=?, emailConfirmed')) {
                return new class(static function (array $args) use (&$rows): Result {
                    $id = (int) ($args[3] ?? 0);

                    foreach ($rows as &$row) {
                        if ((int) ($row['id'] ?? 0) === $id) {
                            $row['tstamp'] = (int) ($args[0] ?? $row['tstamp']);
                            $row['token'] = (string) ($args[1] ?? $row['token']);
                            $row['tokenExpiresAt'] = (int) ($args[2] ?? $row['tokenExpiresAt']);
                            $row['emailConfirmed'] = '';
                        }
                    }
                    unset($row);

                    return new Result([], 'resend-updated-expired-flow');
                }) {
                    /** @var callable(array<int, mixed>): Result */
                    private $onExecute;

                    /** @param callable(array<int, mixed>): Result $onExecute */
                    public function __construct(callable $onExecute)
                    {
                        $this->onExecute = $onExecute;
                    }

                    public function execute(mixed ...$args): Result
                    {
                        return ($this->onExecute)($args);
                    }
                };
            }

            throw new \RuntimeException('Unexpected query in expired workflow test: '.$query);
        });
        $this->setDatabaseSingleton($db);

        $service = $this->createService($framework, $router, $mailer);

        $createResult = $service->tryCreateRequestAndSendDoiMail(
            'Max',
            'Mustermann',
            'max@example.org',
            'Musterweg 1',
            '22559',
            'Hamburg',
            '+49 123',
            ['depot'],
        );

        $this->assertSame('ok', $createResult);
        $this->assertCount(1, $capturedMails);

        $firstMailText = $capturedMails[0]->getTextBody() ?? '';
        $this->assertMatchesRegularExpression('~https://example\.org/access/confirm/([a-f0-9]{64})~', $firstMailText);
        preg_match('~https://example\.org/access/confirm/([a-f0-9]{64})~', $firstMailText, $m);
        $token = $m[1] ?? '';
        $this->assertNotSame('', $token);

        $confirmController = new AccessConfirmController($service);
        $confirmResponse = $confirmController->confirm($token);
        $this->assertSame('/zugangsanfrage-ungueltig', $confirmResponse->getTargetUrl());

        $resendResult = $service->sendOrResendDoiForArea('Max', 'Mustermann', 'max@example.org', 'depot');

        $this->assertSame(['code' => 'ok'], $resendResult);
        $this->assertCount(2, $capturedMails);
    }

    private function statementReturning(Result $result): object
    {
        return new class($result) {
            public function __construct(
                private readonly Result $result,
            ) {
            }

            public function execute(mixed ...$args): Result
            {
                return $this->result;
            }
        };
    }

    private function createPrepareOnlyDatabaseMock(): Database&MockObject
    {
        return $this->getMockBuilder(Database::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['prepare'])
            ->getMock()
        ;
    }

    private function createService(ContaoFramework $framework, RouterInterface|null $router = null, MailerInterface|null $mailer = null): AccessRequestService
    {
        $router ??= $this->createStub(RouterInterface::class);
        $mailer ??= $this->createStub(MailerInterface::class);

        return new AccessRequestService($router, $mailer, $framework);
    }

    private function setDatabaseSingleton(Database|null $database): void
    {
        $ref = new \ReflectionClass(Database::class);
        $property = $ref->getProperty('objInstance');
        $property->setAccessible(true);
        $property->setValue(null, $database);
    }
}
