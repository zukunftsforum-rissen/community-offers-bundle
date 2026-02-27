<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class ApprovalMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $from,
        private readonly string $replyTo,
        private readonly string $appUrl,
        private readonly string $resetPasswordUrl,
    ) {
    }

    /**
     * @param list<string> $areasHumanReadable
     */
    public function sendApprovalMail(string $toEmail, string $firstname, string $lastname, array $areasHumanReadable): void
    {
        $name = trim($firstname.' '.$lastname);
        $areasText = $areasHumanReadable ? implode(', ', $areasHumanReadable) : '-';

        $text = <<<TXT
            Hallo {$name},

            Sie sind jetzt für Zukunftwohnen freigeschaltet. 🎉

            Ihr Login:
            - Benutzername: {$toEmail}
            - Passwort: Wenn Sie noch keines gesetzt haben oder es vergessen haben, dann können Sie es jederzeit zurücksetzen (siehe unten).

            App öffnen:
            {$this->appUrl}

            Passwort setzen / zurücksetzen:
            {$this->resetPasswordUrl}

            Freigeschaltete Bereiche:
            {$areasText}

            Viele Grüße
            Zukunftwohnen / Zukunftsforum Rissen
            TXT;

        $mail = (new Email())
            ->from($this->from)
            ->replyTo($this->replyTo)
            ->to($toEmail)
            ->subject('Freigeschaltet: Zugang zur Zukunftwohnen-App')
            ->text($text)
        ;

        $this->mailer->send($mail);
    }
}
