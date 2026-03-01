<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Tests;

use PHPUnit\Framework\TestCase;
use ZukunftsforumRissen\CommunityOffersBundle\Backend\AccessRequestBackend;
use ZukunftsforumRissen\CommunityOffersBundle\Service\AccessService;
use ZukunftsforumRissen\CommunityOffersBundle\Service\ApprovalMailer;

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
        $approvalMailer = $this->createMock(ApprovalMailer::class);
        $accessService = $this->createMock(AccessService::class);

        $backend = $this->createTestableBackend($approvalMailer, $accessService);
        $backend->input = ['key' => 'approve', 'id' => '77'];
        $backend->row = [
            'emailConfirmed' => '1',
            'approved' => '',
            'email' => '  MAX@EXAMPLE.ORG ',
            'firstname' => 'Max',
            'lastname' => 'Mustermann',
            'street' => 'Musterweg 1',
            'postal' => '22559',
            'city' => 'Hamburg',
            'mobile' => '12345',
            'requestedAreas' => serialize(['depot', 'swap-house']),
        ];

        $member = new class {
            public mixed $tstamp = null;
            public mixed $email = null;
            public mixed $username = null;
            public mixed $login = null;
            public mixed $password = null;
            public mixed $firstname = null;
            public mixed $lastname = null;
            public mixed $street = null;
            public mixed $postal = null;
            public mixed $city = null;
            public mixed $mobile = null;
            public mixed $groups = null;
            public mixed $disable = null;
            public int $saveCalls = 0;

            public function save(): void
            {
                ++$this->saveCalls;
            }
        };
        $member->groups = serialize([2]);
        $backend->createdMember = $member;

        $accessService->expects($this->once())
            ->method('getGroupIdsForAreas')
            ->with(['depot', 'swap-house'])
            ->willReturn([4, 7])
        ;

        $approvalMailer->expects($this->once())
            ->method('sendApprovalMail')
            ->with(
                'max@example.org',
                'Max',
                'Mustermann',
                ['Lebensmittel-Depot', 'Tauschhaus'],
            )
        ;

        $backend->handleActions();

        $this->assertSame(77, $backend->markedApprovedId);
        $this->assertSame('Der Antrag wurde erfolgreich freigegeben.', $backend->confirmationMessage);
        $this->assertSame(1, $backend->redirectCalls);
        $this->assertSame(1, $member->saveCalls);
        $this->assertSame('max@example.org', $member->email);
        $this->assertSame('max@example.org', $member->username);
        $this->assertTrue((bool) $member->login);
        $this->assertFalse((bool) $member->disable);
        $this->assertIsString($member->password);

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
            ['id' => 42, 'emailConfirmed' => '1', 'approved' => ''],
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
        return new AccessRequestBackend(
            $this->createMock(ApprovalMailer::class),
            $this->createMock(AccessService::class),
        );
    }

    private function createTestableBackend(ApprovalMailer|null $approvalMailer = null, AccessService|null $accessService = null): TestableAccessRequestBackend
    {
        $approvalMailer ??= $this->createMock(ApprovalMailer::class);
        $accessService ??= $this->createMock(AccessService::class);

        return new TestableAccessRequestBackend($approvalMailer, $accessService);
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

    public object|null $existingMember = null;

    public object|null $createdMember = null;

    protected function getInputValue(string $key): string|null
    {
        return $this->input[$key] ?? null;
    }

    protected function fetchAccessRequestRow(int $id): array|false
    {
        $this->fetchRowCalled = true;

        return $this->row;
    }

    protected function findMemberByEmail(string $email): object|null
    {
        return $this->existingMember;
    }

    protected function createMember(): object
    {
        if (null !== $this->createdMember) {
            return $this->createdMember;
        }

        return new \stdClass();
    }

    protected function markRequestApproved(int $id): void
    {
        $this->markedApprovedId = $id;
    }

    protected function addConfirmation(string $message): void
    {
        $this->confirmationMessage = $message;
    }

    protected function redirectToRequestList(): void
    {
        ++$this->redirectCalls;
    }
}
