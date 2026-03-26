<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

final class InternalNotificationMailer
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $infoAddress,
        private readonly string $from,
    ) {
    }

    /**
     * @param list<string> $offers
     */
    public function sendConfirmedNotification(string $firstname, string $lastname, string $street, string $postal, string $city, string $phone, string $email, array $offers): void
    {
        $mail = (new Email())
            ->from($this->from)
            ->to($this->infoAddress)
            ->subject('Zugangsanfrage bestätigt')
            ->text(
                "Eine Zugangsanfrage wurde bestätigt.\n\n"
                .$this->buildRequestDataBlock(
                    $firstname,
                    $lastname,
                    $street,
                    $postal,
                    $city,
                    $phone,
                    $email,
                    $offers,
                ),
            )
        ;

        $this->mailer->send($mail);
    }

    /**
     * @param list<string> $offers
     */
    public function sendApprovedNotification(string $firstname, string $lastname, string $street, string $postal, string $city, string $phone, string $email, array $offers): void
    {
        $mail = (new Email())
            ->from($this->from)
            ->to($this->infoAddress)
            ->subject('Zugangsanfrage freigegeben')
            ->text(
                "Eine Zugangsanfrage wurde freigegeben.\n\n"
                .$this->buildRequestDataBlock(
                    $firstname,
                    $lastname,
                    $street,
                    $postal,
                    $city,
                    $phone,
                    $email,
                    $offers,
                ),
            )
        ;

        $this->mailer->send($mail);
    }

    /**
     * @param list<string> $offers
     */
    private function buildRequestDataBlock(string $firstname, string $lastname, string $street, string $postal, string $city, string $phone, string $email, array $offers): string
    {
        return \sprintf(
            "Name: %s %s\n"
            ."Adresse: %s\n"
            ."PLZ/Ort: %s %s\n"
            ."Telefon: %s\n"
            ."E-Mail: %s\n"
            ."Angebote: %s\n",
            $firstname,
            $lastname,
            $street,
            $postal,
            $city,
            $phone,
            $email,
            implode(', ', $offers),
        );
    }
}
