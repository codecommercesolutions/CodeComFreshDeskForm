<?php

declare(strict_types=1);

namespace CodeCom\FreshDeskForm\Core\Api;

use CodeCom\FreshDeskForm\Service\FreshdeskService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
class FreshdeskTicketsController extends AbstractController
{
    public function __construct(
        private readonly FreshdeskService $freshdeskService
    ) {
    }

    #[Route(
        path: '/api/_action/freshdesk/tickets',
        name: 'api.action.freshdesk.tickets',
        methods: ['GET']
    )]
    public function getTickets(): JsonResponse
    {
        $tickets = $this->freshdeskService->getAllTickets();
        return new JsonResponse($tickets);
    }
}
