<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use ZukunftsforumRissen\CommunityOffersBundle\Service\ApprovalMailer;

class ApprovalMailerTest extends TestCase
{
    /**
     * Verifies approval mails include sender metadata, URLs, and readable area list.
     */
    public function testSendApprovalMailIncludesMetadataUrlsAndAreaList(): void
    {
        $capturedMail = null;

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(static function (Email $mail) use (&$capturedMail): bool {
                $capturedMail = $mail;

                return true;
            }))
        ;

        $service = new ApprovalMailer(
            $mailer,
            'admin@example.org',
            'reply@example.org',
            'https://app.example.org',
            'https://app.example.org/reset',
        );

        $service->sendApprovalMail('user@example.org', 'Max', 'Mustermann', ['Werkstatt', 'Tauschhaus']);

        $this->assertInstanceOf(Email::class, $capturedMail);
        $this->assertNotNull($capturedMail);
        /** @var Email $capturedMail */
        $this->assertSame('admin@example.org', $capturedMail->getFrom()[0]->getAddress());
        $this->assertSame('reply@example.org', $capturedMail->getReplyTo()[0]->getAddress());
        $this->assertSame('user@example.org', $capturedMail->getTo()[0]->getAddress());
        $this->assertSame('Freigeschaltet: Zugang zur Zukunftwohnen-App', $capturedMail->getSubject());

        $text = $capturedMail->getTextBody() ?? '';
        $this->assertStringContainsString('Hallo Max Mustermann,', $text);
        $this->assertStringContainsString('https://app.example.org', $text);
        $this->assertStringContainsString('https://app.example.org/reset', $text);
        $this->assertStringContainsString('Werkstatt, Tauschhaus', $text);
    }

    /**
     * Verifies empty area input is rendered as a dash placeholder.
     */
    public function testSendApprovalMailUsesDashPlaceholderWhenAreaListIsEmpty(): void
    {
        $capturedMail = null;

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(static function (Email $mail) use (&$capturedMail): bool {
                $capturedMail = $mail;

                return true;
            }))
        ;

        $service = new ApprovalMailer(
            $mailer,
            'admin@example.org',
            'reply@example.org',
            'https://app.example.org',
            'https://app.example.org/reset',
        );

        $service->sendApprovalMail('user@example.org', 'Max', '', []);

        $this->assertInstanceOf(Email::class, $capturedMail);
        $this->assertNotNull($capturedMail);
        /** @var Email $capturedMail */
        $text = $capturedMail->getTextBody() ?? '';

        $this->assertStringContainsString('Hallo Max,', $text);
        $this->assertStringContainsString("Freigeschaltete Bereiche:\n-", $text);
    }

    /**
     * Verifies greeting uses the trimmed full name when firstname is empty.
     */
    public function testSendApprovalMailUsesTrimmedNameWhenFirstnameIsEmpty(): void
    {
        $capturedMail = null;

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('send')
            ->with($this->callback(static function (Email $mail) use (&$capturedMail): bool {
                $capturedMail = $mail;

                return true;
            }))
        ;

        $service = new ApprovalMailer(
            $mailer,
            'admin@example.org',
            'reply@example.org',
            'https://app.example.org',
            'https://app.example.org/reset',
        );

        $service->sendApprovalMail('user@example.org', '', 'Mustermann', ['Werkstatt']);

        $this->assertInstanceOf(Email::class, $capturedMail);
        $this->assertNotNull($capturedMail);
        /** @var Email $capturedMail */
        $text = $capturedMail->getTextBody() ?? '';

        $this->assertStringContainsString('Hallo Mustermann,', $text);
    }
}
