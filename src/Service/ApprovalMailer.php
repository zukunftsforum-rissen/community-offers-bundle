<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class ApprovalMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $from,
        private readonly string $replyTo,
        private readonly string $appUrl,
        private readonly string $resetPasswordUrl,
        private readonly string $loginPath,
        private readonly string $loginIdentifier,
    ) {
    }

    /**
     * @param list<string> $areasHuman
     */
    public function sendApprovalMail(string $email, string $firstname, string $lastname, array $areasHuman, string $username): void
    {
        $appUrl = $this->normalizeAbsoluteUrl($this->appUrl);
        $loginUrl = $this->buildLoginUrl($email);
        $resetPasswordUrl = $this->normalizeAbsoluteUrl($this->resetPasswordUrl);

        $loginHint = 'Sie können sich mit Ihrer E-Mail-Adresse anmelden.';

        if ('username' === $this->loginIdentifier) {
            $loginHint = \sprintf('Ihr Benutzername lautet: %s', $username);
        }

        $text = <<<TXT
            Hallo {$firstname} {$lastname},

            Ihre Zugangsanfrage wurde freigegeben.

            Freigegebene Bereiche:
            - {$this->formatAreas($areasHuman)}

            {$loginHint}

            Falls Sie bereits eingeloggt sind, melden Sie sich bitte einmal ab und wieder an, damit die neuen Bereiche in der App sichtbar werden.

            Direkt zum Login:
            {$loginUrl}

            Zur App:
            {$appUrl}

            Falls Sie Ihr Passwort zurücksetzen möchten:
            {$resetPasswordUrl}

            Viele Grüße
            Zukunftwohnen / Zukunftsforum Rissen
            TXT;

        $mail = (new Email())
            ->from($this->from)
            ->replyTo($this->replyTo)
            ->to($email)
            ->subject('Ihre Zugangsanfrage wurde freigegeben')
            ->text($text)
        ;

        $this->mailer->send($mail);
    }

    /**
     * @param list<string> $areasHuman
     */
    private function formatAreas(array $areasHuman): string
    {
        return implode(', ', $areasHuman);
    }

    private function buildLoginUrl(string $email): string
    {
        $baseUrl = $this->extractBaseUrl($this->appUrl);
        $loginPath = '/'.ltrim(trim($this->loginPath), '/');

        $query = [
            'redirect' => '/app',
        ];

        if ('email' === $this->loginIdentifier) {
            $query['username'] = $email;
        }

        return $baseUrl.$loginPath.'?'.http_build_query($query);
    }

    private function extractBaseUrl(string $absoluteUrl): string
    {
        $url = $this->normalizeAbsoluteUrl($absoluteUrl);
        $parts = parse_url($url);

        if (false === $parts || !isset($parts['scheme'], $parts['host'])) {
            throw new \RuntimeException(\sprintf('Invalid absolute URL "%s".', $absoluteUrl));
        }

        $base = $parts['scheme'].'://'.$parts['host'];

        if (isset($parts['port'])) {
            $base .= ':'.$parts['port'];
        }

        return $base;
    }

    private function normalizeAbsoluteUrl(string $url): string
    {
        $url = trim($url);

        if ('' === $url) {
            throw new \RuntimeException('Empty URL configured.');
        }

        $parts = parse_url($url);

        if (false === $parts || !isset($parts['scheme'], $parts['host'])) {
            throw new \RuntimeException(\sprintf('Invalid absolute URL "%s".', $url));
        }

        $normalized = $parts['scheme'].'://'.$parts['host'];

        if (isset($parts['port'])) {
            $normalized .= ':'.$parts['port'];
        }

        $path = $parts['path'] ?? '';
        if ('' !== $path) {
            $normalized .= '/'.ltrim($path, '/');
        }

        if (isset($parts['query']) && '' !== $parts['query']) {
            $normalized .= '?'.$parts['query'];
        }

        if (isset($parts['fragment']) && '' !== $parts['fragment']) {
            $normalized .= '#'.$parts['fragment'];
        }

        return $normalized;
    }
}
