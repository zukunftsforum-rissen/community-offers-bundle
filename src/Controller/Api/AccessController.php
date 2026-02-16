<?php

declare(strict_types=1);

namespace ZukunftsforumRissen\CommunityOffersBundle\Controller\Api;

use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/door', defaults: ['_scope' => 'frontend', '_token_check' => false])]
class AccessController
{
    public function __construct(private readonly Security $security) {}

    /**
     * Returns information about the currently authenticated user.
     * This endpoint provides details about the user's authentication status, including their identifier and roles if they are authenticated. If the user is not authenticated, it simply indicates that the user is not authenticated. This can be useful for frontend applications to determine the user's state and adjust the UI accordingly.
     * Test with a GET request to /api/door/whoami. The response will indicate whether the user is authenticated and provide their identifier and roles if they are.
     * 
     *  @return JsonResponse  */
    #[Route('/whoami', name: 'community_offers_whoami', methods: ['GET'])]
    public function whoami(): JsonResponse
    {
        $user = $this->security->getUser();

        if (!$user) {
            return new JsonResponse([
                'authenticated' => false,
            ], 200);
        }

        return new JsonResponse([
            'authenticated' => true,
            'identifier' => $user->getUserIdentifier(),
            'roles' => $user->getRoles(),
        ], 200);
    }

    #[Route('/open/{slug}', name: 'community_offers_open_door', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
    public function open(string $slug): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
            'door' => $slug,
        ]);
    }
}




// Liefert immer 403, egal ob eingeloggt
// #[Route('/api/door')]
// class AccessController
// {
//     public function __construct(
//         private readonly AccessService $accessService,
//         private readonly Security $security,
//     ) {}

//     #[Route('/open/{slug}', name: 'community_offers_open_door', methods: ['POST'])]
//     #[IsGranted('ROLE_MEMBER')]
//     public function open(string $slug): JsonResponse
//     {
//         $user = $this->security->getUser(); // eingeloggter FE-User (Objekt)

//         $success = $this->accessService->openDoor($slug);

//         return new JsonResponse([
//             'success' => $success,
//             'door' => $slug,
//             'user' => $user?->getUserIdentifier(), // meist username / email
//         ]);
//     }
// }

/**
 * Opens a door based on the provided slug.
 * This endpoint is designed to trigger the opening of a door identified by its slug. In a real implementation, this would include authentication and authorization checks to ensure that only authorized users can open the door. Additionally, it would interact with hardware or a service responsible for controlling the door mechanism.
 * Teste mit einem POST-Request an /api/door/open/{slug}, wobei {slug} durch die Kennung der Tür ersetzt wird, die geöffnet werden soll. Die Antwort gibt an, ob der Vorgang erfolgreich war.
 * Example usage: in terminal:
 * curl -X POST https://zukunftwohnen.ddev.site/api/door/open/workshop

 * Response:
 * {
 * "success": true,
 *  "door": "main-entrance"
 * }
 * 
 * @param string $slug 
 * @param Request $request 
 * @return JsonResponse 
 */
//     #[Route('/open/{slug}', name: 'community_offers_open_door', methods: ['POST'])]
//     public function open(string $slug, Request $request): JsonResponse
//     {
//         $success = $this->accessService->openDoor($slug);

//         return new JsonResponse([
//             'success' => $success,
//             'door' => $slug
//         ]);
//     }
// }

/** @package ZukunftsforumRissen\CommunityOffersBundle\Controller\Api 
 * 
 * This controller serves as a simple API endpoint for testing purposes. It can be expanded in the future to include more complex API functionalities related to community offers.
 * The '/ping' endpoint can be used to verify that the API is up and running, returning a JSON response indicating success.
 * Example usage:
 * GET /api/ping
 * Response:
 * {
 *  "success": true,
 * "message": "Community Offers API is working"
 * }
 * 
 * im Browser: http://localhost:8000/api/ping
 * 
 */
// #[Route('/api')]
// class AccessController
// {
//     #[Route('/ping', name: 'community_offers_ping', methods: ['GET'])]
//     public function ping(): JsonResponse
//     {
//         return new JsonResponse([
//             'success' => true,
//             'message' => 'Community Offers API is working'
//         ]);
//     }
// }
