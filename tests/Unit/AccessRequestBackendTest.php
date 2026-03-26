<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Tests;

use Contao\MemberModel;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use ZukunftsforumRissen\CommunityOffersBundle\Backend\AccessRequestBackend;
use ZukunftsforumRissen\CommunityOffersBundle\Service\AccessService;
use ZukunftsforumRissen\CommunityOffersBundle\Service\ApprovalMailer;
use ZukunftsforumRissen\CommunityOffersBundle\Service\InternalNotificationMailer;

class AccessRequestBackendTest extends TestCase
{
    /**
     * Verifies non-approve backend actions are ignored without DB work or redirect.
     */
    public function testHandleActionsReturnsImmediatelyWhenKeyIsNotApprove(): void
    {
        $backend = $this->createTestableBackend();
        $backend->input = ['key' => 'other', 'id' => '5'];

        $backend->handleActions();

        $this->assertFalse($backend->fetchRowCalled);
        $this->assertSame(0, $backend->redirectCalls);
    }

    /**
     * Verifies approve action redirects when the request row is missing or not eligible.
     */
    public function testHandleActionsRedirectsWhenRequestIsMissingOrNotEligible(): void
    {
        $backend = $this->createTestableBackend();
        $backend->input = ['key' => 'approve', 'id' => '7'];
        $backend->row = false;

        $backend->handleActions();

        $this->assertTrue($backend->fetchRowCalled);
        $this->assertSame(1, $backend->redirectCalls);
        $this->assertNull($backend->markedApprovedId);
    }

    /**
     * Verifies approve action creates/updates member data, marks request approved, and sends mail.
     */
    public function testHandleActionsCreatesNewMemberMarksApprovedAndSendsMail(): void
    {
        $accessService = $this->createMock(AccessService::class);
        $approvalMailerTransport = $this->createMock(MailerInterface::class);
        $approvalMailerTransport->expects($this->once())->method('send');
        $approvalMailer = new ApprovalMailer(
            $approvalMailerTransport,
            'noreply@example.org',
            'reply@example.org',
            'https://app.example.org',
            'https://app.example.org/reset',
            '/login',
            'email',
        );
        $internalMailerTransport = $this->createMock(MailerInterface::class);
        $internalMailerTransport->expects($this->once())->method('send');
        $internalNotificationMailer = new InternalNotificationMailer($internalMailerTransport, 'info@example.org', 'noreply@example.org');

        $backend = $this->createTestableBackend($approvalMailer, $accessService, $internalNotificationMailer);
        $backend->input = ['key' => 'approve', 'id' => '77'];
        $backend->row = [
            'emailConfirmed' => '1',
            'approved' => '',
            'memberId' => 77,
            'email' => '  MAX@EXAMPLE.ORG ',
            'firstname' => 'Max',
            'lastname' => 'Mustermann',
            'street' => 'Musterweg 1',
            'postal' => '22559',
            'city' => 'Hamburg',
            'mobile' => '12345',
            'requestedAreas' => serialize(['depot', 'swap-house']),
        ];

        $member = new class extends MemberModel {
            public mixed $tstamp = null;
            public mixed $username = null;
            public mixed $groups = null;
            public mixed $disable = null;
            public int $saveCalls = 0;

            public function __construct()
            {
            }

            public function save(): static
            {
                ++$this->saveCalls;

                return $this;
            }
        };
        $member->groups = serialize([2]);
        $member->username = 'max@example.org';
        $backend->memberByPk = $member;

        $accessService->expects($this->once())
            ->method('getGroupIdsForAreas')
            ->with(['depot', 'swap-house'])
            ->willReturn([4, 7])
        ;

        $backend->handleActions();

        $this->assertSame(77, $backend->markedApprovedId);
        $this->assertSame('Der Antrag wurde erfolgreich freigegeben.', $backend->confirmationMessage);
        $this->assertSame(1, $backend->redirectCalls);
        $this->assertSame(1, $member->saveCalls);
        $this->assertFalse((bool) $member->disable);

        $groups = @unserialize((string) $member->groups, ['allowed_classes' => false]);
        $this->assertIsArray($groups);
        $this->assertSame([2, 4, 7], $groups);
    }

    /**
     * Verifies no approve button is rendered for unconfirmed or already approved rows.
     */
    public function testGenerateApproveButtonReturnsEmptyWhenNotEligible(): void
    {
        $backend = $this->createBackend();

        $notConfirmed = $backend->generateApproveButton(
            ['id' => 1, 'emailConfirmed' => '', 'approved' => ''],
            null,
            'Freigeben',
            'Freigeben',
            'check.svg',
            '',
        );

        $alreadyApproved = $backend->generateApproveButton(
            ['id' => 2, 'emailConfirmed' => '1', 'approved' => '1'],
            null,
            'Freigeben',
            'Freigeben',
            'check.svg',
            '',
        );

        $this->assertSame('', $notConfirmed);
        $this->assertSame('', $alreadyApproved);
    }

    /**
     * Verifies eligible rows render an approve action link with expected parameters.
     */
    public function testGenerateApproveButtonReturnsApproveLinkWhenEligible(): void
    {
        $backend = $this->createBackend();

        $button = $backend->generateApproveButton(
            ['id' => 42, 'emailConfirmed' => '1', 'approved' => '', 'memberId' => 42],
            null,
            'Freigeben',
            'Freigeben',
            null,
            '',
        );

        $this->assertStringContainsString('key=approve&id=42', $button);
        $this->assertStringContainsString('Freigeben', $button);
        $this->assertStringContainsString('<a href="contao?do=co_access_request', $button);
    }

    private function createBackend(): AccessRequestBackend
    {
        $approvalMailer = new ApprovalMailer(
            $this->createStub(MailerInterface::class),
            'noreply@example.org',
            'reply@example.org',
            'https://app.example.org',
            'https://app.example.org/reset',
            '/login',
            'email',
        );
        $internalMailerTransport = $this->createStub(MailerInterface::class);
        $internalNotificationMailer = new InternalNotificationMailer($internalMailerTransport, 'info@example.org', 'noreply@example.org');

        return new AccessRequestBackend(
            $approvalMailer,
            $this->createMock(AccessService::class),
            $internalNotificationMailer,
        );
    }

    private function createTestableBackend(ApprovalMailer|null $approvalMailer = null, AccessService|null $accessService = null, InternalNotificationMailer|null $internalNotificationMailer = null): TestableAccessRequestBackend
    {
        if (null === $approvalMailer) {
            $approvalMailer = new ApprovalMailer(
                $this->createStub(MailerInterface::class),
                'noreply@example.org',
                'reply@example.org',
                'https://app.example.org',
                'https://app.example.org/reset',
                '/login',
                'email',
            );
        }
        $accessService ??= $this->createMock(AccessService::class);
        if (null === $internalNotificationMailer) {
            $internalNotificationMailer = new InternalNotificationMailer($this->createStub(MailerInterface::class), 'info@example.org', 'noreply@example.org');
        }

        return new TestableAccessRequestBackend($approvalMailer, $accessService, $internalNotificationMailer);
    }
}

class TestableAccessRequestBackend extends AccessRequestBackend
{
    /** @var array<string, string|null> */
    public array $input = [];

    /** @var array<string, mixed>|false */
    public array|false $row = false;

    public bool $fetchRowCalled = false;

    public int $redirectCalls = 0;

    public int|null $markedApprovedId = null;

    public string|null $confirmationMessage = null;

    public string|null $errorMessage = null;

    public object|null $memberByPk = null;

    protected function getInputValue(string $key): string|null
    {
        return $this->input[$key] ?? null;
    }

    protected function fetchAccessRequestRow(int $id): array|false
    {
        $this->fetchRowCalled = true;

        return $this->row;
    }

    protected function findMemberByPk(int $memberId): ?MemberModel
    {
        /** @var MemberModel|null $member */
        $member = $this->memberByPk;

        return $member;
    }

    protected function markRequestApproved(int $id): void
    {
        $this->markedApprovedId = $id;
    }

    protected function addConfirmation(string $message): void
    {
        $this->confirmationMessage = $message;
    }

    protected function addError(string $message): void
    {
        $this->errorMessage = $message;
    }

    protected function redirectToRequestList(): void
    {
        ++$this->redirectCalls;
    }
}
