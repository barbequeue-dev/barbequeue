<?php

declare(strict_types=1);

namespace App\Controller\Queue;

use App\Controller\AbstractController;
use App\Entity\DeploymentQueue;
use App\Entity\DeploymentQueueSettings;
use App\Entity\Queue;
use App\Enum\Queue as QueueEnum;
use App\Form\Queue\QueueType;
use App\Repository\QueueRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route(path: '/queue', name: 'queue_')]
#[OA\Tag(
    name: 'queue',
    description: 'Queue API',
)]
#[OA\Response(
    response: Response::HTTP_UNAUTHORIZED,
    description: 'Unauthorized',
    content: new OA\JsonContent(ref: '#/components/schemas/UnauthorizedResponse')
)]
class QueueController extends AbstractController
{
    public function __construct(
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        private readonly QueueRepositoryInterface $queueRepository,
    ) {
        parent::__construct($entityManager, $validator);
    }

    #[Route(path: '/', name: 'index', methods: ['GET'])]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'Returns a list of queues for the current user\'s workspace.',
        content: new OA\JsonContent(
            type: 'array',
            items: new OA\Items(anyOf: [
                new OA\Schema(ref: new Model(type: Queue::class, groups: ['queue'], name: 'SimpleQueue')),
                new OA\Schema(ref: new Model(type: DeploymentQueue::class, groups: ['queue'])),
            ])
        )
    )]
    public function index(): JsonResponse
    {
        $queues = $this->queueRepository->findBy([
            'workspace' => $this->getUser()->getWorkspace(),
        ]);

        return $this->json($queues);
    }

    #[Route(path: '/{name}', name: 'show', methods: ['GET'])]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'Returns a queue by name.',
        content: new OA\JsonContent(oneOf: [
            new OA\Schema(ref: new Model(type: Queue::class, groups: ['queue'], name: 'SimpleQueue')),
            new OA\Schema(ref: new Model(type: DeploymentQueue::class, groups: ['queue'])),
        ])
    )]
    #[OA\Response(
        response: Response::HTTP_NOT_FOUND,
        description: 'Not found',
        content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')
    )]
    public function show(string $name): JsonResponse
    {
        $queue = $this->queueRepository->findOneByNameAndWorkspace($name, $this->getUser()->getWorkspace())
            ?? throw new NotFoundHttpException();

        return $this->json($queue);
    }

    #[Route(path: '/{name}', name: 'update', methods: ['PATCH', 'PUT'])]
    #[IsGranted('ROLE_ADMINISTRATOR')]
    #[OA\RequestBody(
        content: new OA\JsonContent(ref: new Model(type: QueueType::class)),
    )]
    #[OA\Response(
        response: Response::HTTP_OK,
        description: 'Returns a queue by name.',
        content: new OA\JsonContent(oneOf: [
            new OA\Schema(ref: new Model(type: Queue::class, groups: ['queue'], name: 'SimpleQueue')),
            new OA\Schema(ref: new Model(type: DeploymentQueue::class, groups: ['queue'])),
        ])
    )]
    #[OA\Response(
        response: Response::HTTP_NOT_FOUND,
        description: 'Not found',
        content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')
    )]
    public function update(string $name, Request $request): JsonResponse
    {
        $queue = $this->queueRepository->findOneByNameAndWorkspace($name, $this->getUser()->getWorkspace())
            ?? throw new NotFoundHttpException();

        $form = $this->createForm(QueueType::class, $queue);

        return $this->handleUpdateOrCreate($queue, $form, $request);
    }

    #[Route(path: '/', name: 'create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMINISTRATOR')]
    #[OA\RequestBody(
        content: new OA\JsonContent(ref: new Model(type: QueueType::class)),
    )]
    #[OA\Response(
        response: Response::HTTP_CREATED,
        description: 'Returns a queue by name.',
        content: new OA\JsonContent(oneOf: [
            new OA\Schema(ref: new Model(type: Queue::class, groups: ['queue'], name: 'SimpleQueue')),
            new OA\Schema(ref: new Model(type: DeploymentQueue::class, groups: ['queue'])),
        ])
    )]
    #[OA\Response(
        response: Response::HTTP_NOT_FOUND,
        description: 'Not found',
        content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')
    )]
    public function create(Request $request): JsonResponse
    {
        $type = QueueEnum::tryFrom($request->request->getString('type'));

        $queue = match ($type) {
            QueueEnum::DEPLOYMENT => new DeploymentQueue()->setSettings(new DeploymentQueueSettings()),
            default => new Queue(),
        };

        $queue->setWorkspace($this->getUser()->getWorkspace());

        $form = $this->createForm(QueueType::class, $queue);

        return $this->handleUpdateOrCreate($queue, $form, $request);
    }

    #[Route(path: '/{name}', name: 'delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_ADMINISTRATOR')]
    #[OA\Response(
        response: Response::HTTP_NOT_FOUND,
        description: 'Not found',
        content: new OA\JsonContent(ref: '#/components/schemas/NotFoundResponse')
    )]
    #[OA\Response(
        response: Response::HTTP_NO_CONTENT,
        description: 'No content',
    )]
    public function delete(string $name): JsonResponse
    {
        $queue = $this->queueRepository->findOneByNameAndWorkspace($name, $this->getUser()->getWorkspace())
            ?? throw new NotFoundHttpException();

        $errors = $this->validator->validate($queue, groups: ['delete']);

        if ($errors->count() > 0) {
            return $this->json($errors, Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $this->entityManager->remove($queue);
        $this->entityManager->flush();

        return $this->json(null, Response::HTTP_NO_CONTENT);
    }

    protected function json(mixed $data, int $status = 200, array $headers = [], array $context = []): JsonResponse
    {
        return parent::json($data, $status, $headers, array_merge($context, ['groups' => 'queue']));
    }
}
