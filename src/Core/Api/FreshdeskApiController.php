<?php

declare(strict_types=1);

namespace CodeCom\FreshDeskForm\Core\Api;

use CodeCom\FreshDeskForm\Service\FreshdeskService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['api']])]
class FreshdeskApiController extends AbstractController
{
    public function __construct(
        private readonly FreshdeskService $freshdeskService
    ) {
    }

    #[Route(
        path: '/api/_action/freshdesk/test-connection',
        name: 'api.action.freshdesk.test_connection',
        methods: ['POST']
    )]
    public function testConnection(): JsonResponse
    {
        $result = $this->freshdeskService->testConnection();
        return new JsonResponse($result);
    }

    #[Route(
        path: '/api/_action/freshdesk/groups',
        name: 'api.action.freshdesk.groups',
        methods: ['GET']
    )]
    public function getGroups(): JsonResponse
    {
        $groups = $this->freshdeskService->getGroups();
        return new JsonResponse($groups);
    }

    #[Route(
        path: '/api/_action/freshdesk/products',
        name: 'api.action.freshdesk.products',
        methods: ['GET']
    )]
    public function getProducts(): JsonResponse
    {
        $products = $this->freshdeskService->getProducts();
        return new JsonResponse($products);
    }

    #[Route(
        path: '/api/_action/freshdesk/ticket-fields',
        name: 'api.action.freshdesk.ticket_fields',
        methods: ['GET']
    )]
    public function getTicketFields(): JsonResponse
    {
        $fields = $this->freshdeskService->getTicketFields();
        return new JsonResponse($fields);
    }

    #[Route(
        path: '/api/_action/freshdesk/agents',
        name: 'api.action.freshdesk.agents',
        methods: ['GET']
    )]
    public function getAgents(): JsonResponse
    {
        $agents = $this->freshdeskService->getAgents();
        return new JsonResponse($agents);
    }

    #[Route(
        path: '/api/_action/freshdesk/metadata',
        name: 'api.action.freshdesk.metadata',
        methods: ['GET']
    )]
    public function getAllMetadata(): JsonResponse
    {
        $metadata = $this->freshdeskService->getAllMetadata();
        return new JsonResponse($metadata);
    }
}
