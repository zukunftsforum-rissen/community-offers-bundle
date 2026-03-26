<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Service;

use Contao\Database;
use Contao\MemberModel;

final class PasswordSetupService implements PasswordSetupServiceInterface
{
    private const TOKEN_TTL = 86400;

    public function createSetupTokenForRequest(int $requestId): string
    {
        $token = bin2hex(random_bytes(32));
        $expires = time() + self::TOKEN_TTL;

        Database::getInstance()
            ->prepare('
                UPDATE tl_co_access_request
                SET passwordSetupToken=?,
                    passwordSetupExpiresAt=?,
                    tstamp=?
                WHERE id=?
            ')
            ->execute($token, $expires, time(), $requestId)
        ;

        return $token;
    }

    /**
     * @return array<string, mixed>
     */
    public function getValidRequestByToken(string $token): array
    {
        $row = Database::getInstance()
            ->prepare('
                SELECT *
                FROM tl_co_access_request
                WHERE passwordSetupToken=?
                LIMIT 1
            ')
            ->execute($token)
            ->fetchAssoc()
        ;

        if (!$row) {
            throw new \RuntimeException('Ungültiger Token.');
        }

        if ((int) $row['passwordSetupExpiresAt'] < time()) {
            throw new \RuntimeException('Token abgelaufen.');
        }

        if ((int) $row['memberId'] <= 0) {
            throw new \RuntimeException('Kein Member verknüpft.');
        }

        return $row;
    }

    public function setPasswordFromToken(string $token, string $plainPassword): void
    {
        $request = $this->getValidRequestByToken($token);

        $member = MemberModel::findById((int) $request['memberId']);

        if (!$member) {
            throw new \RuntimeException('Member nicht gefunden.');
        }

        $member->password = password_hash($plainPassword, PASSWORD_DEFAULT);
        $member->tstamp = time();
        $member->save();

        Database::getInstance()
            ->prepare("
                UPDATE tl_co_access_request
                SET passwordSetupToken='',
                    passwordSetupExpiresAt=0,
                    passwordSetAt=?,
                    tstamp=?
                WHERE id=?
            ")
            ->execute(
                time(),
                time(),
                (int) $request['id'],
            )
        ;
    }
}
