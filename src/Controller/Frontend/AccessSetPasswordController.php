<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Controller\Frontend;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use ZukunftsforumRissen\CommunityOffersBundle\Service\PasswordSetupServiceInterface;

final class AccessSetPasswordController extends AbstractController
{
    public function __construct(
        private readonly PasswordSetupServiceInterface $passwordSetupService,
    ) {
    }

    #[Route(
        '/access/set-password/{token}',
        name: 'co_access_set_password',
        methods: ['GET', 'POST'],
        defaults: ['_scope' => 'frontend'],
        requirements: ['token' => '[a-f0-9]{64}'],
    )]
    public function __invoke(Request $request, string $token): Response
    {
        $errors = [];
        $success = false;

        try {
            if ($request->isMethod('POST')) {
                $password = (string) $request->request->get('password');
                $confirm = (string) $request->request->get('password_confirm');

                if ($password !== $confirm) {
                    $errors[] = 'Die Passwörter stimmen nicht überein.';
                }

                if (mb_strlen($password) < 10) {
                    $errors[] = 'Das Passwort muss mindestens 10 Zeichen lang sein.';
                }

                if ([] === $errors) {
                    $this->passwordSetupService->setPasswordFromToken($token, $password);
                    $success = true;
                }
            }

            if (!$success) {
                $this->passwordSetupService->getValidRequestByToken($token);
            }
        } catch (\Throwable $e) {
            $errors[] = $e->getMessage();
        }

        return $this->render('@CommunityOffers/frontend/access_set_password.html.twig', [
            'title' => 'Passwort vergeben',
            'errors' => $errors,
            'success' => $success,
        ]);
    }
}
