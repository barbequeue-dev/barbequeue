<?php

declare(strict_types=1);

namespace App\Tests\Feature;

use App\Entity\Administrator;
use App\Entity\Deployment;
use App\Entity\DeploymentQueue;
use App\Entity\QueuedUser;
use App\Entity\Repository;
use App\Entity\User;
use App\Entity\Workspace;
use App\Enum\Queue;
use App\Enum\QueueBehaviour;
use App\Message\Queue\PopQueuesMessage;
use App\Repository\QueueRepositoryInterface;
use App\Repository\RepositoryRepositoryInterface;
use App\Repository\WorkspaceRepositoryInterface;
use App\Slack\BlockElement\BlockElement;
use App\Slack\Client\Factory\ClientFactory;
use App\Slack\Command\Command;
use App\Slack\Command\SubCommand;
use App\Slack\Interaction\Interaction;
use App\Slack\Interaction\InteractionType;
use App\Slack\Surface\Component\Modal;
use App\Slack\Surface\Component\ModalArgument;
use App\Slack\Surface\Surface;
use Carbon\CarbonImmutable;
use Doctrine\ORM\EntityManagerInterface;
use JoliCode\Slack\Api\Client;
use JoliCode\Slack\Api\Model\ConversationsOpenPostResponse200;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FeatureTestCase extends WebTestCase
{
    protected KernelBrowser $client;

    /** @var SentRequest[] */
    private array $requestsSent = [];

    /** @var OpenedView[] */
    private array $viewsOpened = [];

    /** @var UpdatedView[] */
    private array $viewsUpdated = [];

    /** @var SentPrivateMessage[] */
    private array $privateMessagesSent = [];

    /** @var SentPrivateMessage[] */
    private array $ephemeralMessagesSent = [];

    private Workspace $workspace;

    private QueueRepositoryInterface $queueRepository;

    private RepositoryRepositoryInterface $repositoryRepository;

    /** @var int[] */
    private array $repositoryIdMap = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = static::createClient();

        $httpClient = $this->createStub(HttpClientInterface::class);
        $httpClient->method('request')
            ->willReturnCallback(function ($method, $url, $options) {
                $this->assertIsString($method);
                $this->assertIsString($url);
                $this->assertIsArray($options);

                $this->requestsSent[] = new SentRequest($method, $url, $options);
            });

        $slackClient = $this->createStub(Client::class);
        $slackClient->method('viewsOpen')
            ->willReturnCallback(function ($view) {
                $this->assertIsArray($view);
                $this->assertArrayHasKey('trigger_id', $view);
                $this->assertEquals('triggerId', $view['trigger_id']);
                $this->assertArrayHasKey('view', $view);
                $this->assertIsString($view['view']);

                $view = json_decode($view['view'], true);

                $this->assertIsArray($view);

                $this->assertArrayHasKey('title', $view);
                $this->assertIsArray($view['title']);
                $this->assertArrayHasKey('text', $view['title']);
                $this->assertIsString($title = $view['title']['text']);

                $this->assertArrayHasKey('blocks', $view);
                $this->assertIsArray($blocks = $view['blocks']);

                $this->assertArrayHasKey('type', $view);
                $this->assertIsString($view['type']);

                $surface = Surface::tryFrom($view['type']);

                $this->assertInstanceOf(Surface::class, $surface);

                $this->viewsOpened[] = new OpenedView($surface, $blocks, $title);
            });

        $slackClient->method('viewsUpdate')
            ->willReturnCallback(function ($view) {
                $this->assertIsArray($view);
                $this->assertArrayNotHasKey('trigger_id', $view);
                $this->assertArrayHasKey('view_id', $view);
                $this->assertIsString($viewId = $view['view_id']);
                $this->assertArrayHasKey('view', $view);
                $this->assertIsString($view['view']);

                $view = json_decode($view['view'], true);

                $this->assertIsArray($view);

                $this->assertArrayHasKey('title', $view);
                $this->assertIsArray($view['title']);
                $this->assertArrayHasKey('text', $view['title']);
                $this->assertIsString($title = $view['title']['text']);

                $this->assertArrayHasKey('blocks', $view);
                $this->assertIsArray($blocks = $view['blocks']);

                $this->assertArrayHasKey('type', $view);
                $this->assertIsString($view['type']);

                $surface = Surface::tryFrom($view['type']);

                $this->assertInstanceOf(Surface::class, $surface);

                $this->viewsUpdated[] = new UpdatedView(
                    $viewId,
                    $surface,
                    $blocks,
                    $title,
                );
            });

        $conversationsOpenResponse = $this->createStub(ConversationsOpenPostResponse200::class);
        $conversationsOpenResponse->method('getChannel')
            ->willReturn(['id' => 'channelId']);

        $slackClient->method('conversationsOpen')
            ->willReturnCallback(function ($arguments) use ($conversationsOpenResponse, $slackClient) {
                $this->assertIsArray($arguments);
                $this->assertArrayHasKey('users', $arguments);
                $this->assertIsString($userId = $arguments['users']);

                $slackClient->method('chatPostMessage')
                    ->willReturnCallback(function ($message) use ($userId) {
                        $this->assertIsArray($message);
                        $this->assertArrayHasKey('channel', $message);
                        $this->assertEquals('channelId', $message['channel']);
                        $this->assertArrayHasKey('blocks', $message);
                        $this->assertIsString($blocks = $message['blocks']);

                        $blocks = json_decode($blocks, true);

                        $this->assertIsArray($blocks);

                        $this->privateMessagesSent[] = new SentPrivateMessage($userId, $blocks);
                    });

                return $conversationsOpenResponse;
            });

        $slackClient->method('chatPostEphemeral')
            ->willReturnCallback(function ($message) {
                $this->assertIsArray($message);
                $this->assertArrayHasKey('channel', $message);
                $this->assertEquals('channelId', $message['channel']);
                $this->assertArrayHasKey('user', $message);
                $this->assertIsString($userId = $message['user']);
                $this->assertArrayHasKey('blocks', $message);
                $this->assertIsString($blocks = $message['blocks']);

                $blocks = json_decode($blocks, true);

                $this->assertIsArray($blocks);

                $this->ephemeralMessagesSent[] = new SentPrivateMessage($userId, $blocks);
            });

        $slackClientFactory = $this->createStub(ClientFactory::class);
        $slackClientFactory->method('create')
            ->willReturn($slackClient);

        $this->client->disableReboot();

        static::getContainer()->set(HttpClientInterface::class, $httpClient);
        static::getContainer()->set(ClientFactory::class, $slackClientFactory);

        $this->workspace = $this->createWorkspace();
        $this->createAdministrator();
    }

    protected function sendAdminCommand(
        ?SubCommand $subCommand,
        array $arguments = [],
        string $userId = 'administrator',
    ): static {
        return $this->sendCommand(Command::BBQ_ADMIN, $subCommand, $arguments, $userId);
    }

    protected function sendUserCommand(
        ?SubCommand $subCommand,
        array $arguments = [],
        string $userId = 'test',
    ): static {
        return $this->sendCommand(Command::BBQ, $subCommand, $arguments, $userId);
    }

    private function sendCommand(Command $command, ?SubCommand $subCommand, array $arguments, string $userId): static
    {
        $commandText = [
            $subCommand?->value,
            implode(' ', $arguments),
        ];

        $this->client->request('POST', '/api/slack/command', [
            'response_url' => 'responseUrl',
            'command' => '/'.$command->value,
            'text' => trim(implode(' ', $commandText)),
            'team_id' => 'test',
            'user_id' => $userId,
            'trigger_id' => 'triggerId',
        ]);

        $this->assertResponseIsSuccessful();

        $response = $this->client->getResponse();

        $this->assertEmpty($response->getContent());

        return $this;
    }

    protected function sendBlockActionInteraction(
        Interaction $interaction,
        string $value,
        string $userId = 'test',
    ): static {
        $payload = json_encode([
            'type' => InteractionType::BLOCK_ACTIONS->value,
            'actions' => [
                [
                    'action_id' => $interaction->value,
                    'value' => $value,
                ],
            ],
            'response_url' => 'responseUrl',
            'user' => [
                'id' => $userId,
            ],
            'team' => [
                'id' => 'test',
            ],
            'trigger_id' => 'triggerId',
        ]);

        $this->assertIsString($payload);

        return $this->sendInteraction($payload);
    }

    protected function sendInputSelectionInteraction(
        Interaction $interaction,
        string $value,
        string $viewId,
        string $userId = 'test',
    ): static {
        $payload = json_encode([
            'type' => InteractionType::BLOCK_ACTIONS->value,
            'view' => [
                'id' => $viewId,
            ],
            'actions' => [
                [
                    'action_id' => $interaction->value,
                    'selected_option' => [
                        'value' => $value,
                    ],
                ],
            ],
            'response_url' => 'responseUrl',
            'user' => [
                'id' => $userId,
            ],
            'team' => [
                'id' => 'test',
            ],
            'trigger_id' => 'triggerId',
        ]);

        return $this->sendInteraction($payload);
    }

    /** @param StateArgument[] $stateArguments */
    protected function sendViewSubmission(
        Interaction $interaction,
        array $stateArguments,
        array $privateMetadataArguments = [],
        string $userId = 'test',
    ): static {
        $privateMetadata = array_merge([
            'action' => $interaction->value,
            'response_url' => 'responseUrl',
        ], $privateMetadataArguments);

        $state = [];

        foreach ($stateArguments as $stateArgument) {
            $state[] = $stateArgument->toArray();
        }

        $payload = json_encode([
            'type' => InteractionType::VIEW_SUBMISSION->value,
            'view' => [
                'private_metadata' => json_encode($privateMetadata),
                'state' => [
                    'values' => $state,
                ],
            ],
            'team' => [
                'id' => 'test',
            ],
            'user' => [
                'id' => $userId,
            ],
            'trigger_id' => 'triggerId',
        ]);

        return $this->sendInteraction($payload);
    }

    private function sendInteraction(string $payload): static
    {
        $this->client->request('POST', '/api/slack/interaction', [
            'payload' => $payload,
        ]);

        $this->assertResponseIsSuccessful();

        $response = $this->client->getResponse();

        $this->assertEmpty($response->getContent());

        return $this;
    }

    protected function assertInteractionResponseSentContainingMessage(string $message, bool $keepMessage = false): static
    {
        $sent = false;

        $receivedMessages = [];

        foreach ($this->requestsSent as $key => $request) {
            $body = json_encode($request->getOptions());

            $receivedMessages[] = $body;

            $this->assertIsString($body);

            if (str_contains($body, $message)) {
                $this->assertEquals('responseUrl', $request->getUrl());
                $this->assertEquals('POST', $request->getMethod());

                $sent = true;

                if (!$keepMessage) {
                    unset($this->requestsSent[$key]);
                }

                break;
            }
        }

        $this->assertTrue($sent, 'Received messages: '.implode(',', $receivedMessages));

        return $this;
    }

    protected function assertEphemeralMessageSentContainingMessage(
        string $message,
        string $userId = 'test',
        bool $keepMessage = false,
    ): static {
        $sent = false;

        $receivedMessages = [];

        foreach ($this->ephemeralMessagesSent as $key => $privateMessage) {
            $body = json_encode($privateMessage->getBlocks());

            $receivedMessages[] = $body;

            $this->assertIsString($body);

            if (str_contains($body, $message)) {
                $this->assertEquals($userId, $privateMessage->getUserId());
                $sent = true;

                if (!$keepMessage) {
                    unset($this->ephemeralMessagesSent[$key]);
                }

                break;
            }
        }

        $this->assertTrue($sent, 'Received messages: '.implode(',', $receivedMessages));

        return $this;
    }

    protected function assertPrivateMessageSentContainingMessage(
        string $message,
        string $userId = 'test',
        bool $keepMessage = false,
    ): static {
        $sent = false;

        $receivedMessages = [];

        foreach ($this->privateMessagesSent as $key => $privateMessage) {
            $body = json_encode($privateMessage->getBlocks());

            $receivedMessages[] = $body;

            $this->assertIsString($body);

            if (str_contains($body, $message)) {
                $this->assertEquals($userId, $privateMessage->getUserId());
                $sent = true;

                if (!$keepMessage) {
                    unset($this->privateMessagesSent[$key]);
                }

                break;
            }
        }

        $this->assertTrue($sent, 'Received messages: '.implode(',', $receivedMessages));

        return $this;
    }

    protected function assertModalOpened(Modal $modal): static
    {
        $opened = false;

        foreach ($this->viewsOpened as $key => $view) {
            if ($view->getTitle() === $modal->getTitle()) {
                $this->assertEquals(Surface::MODAL, $view->getType());
                $this->assertNotEmpty($view->getBlocks());

                $opened = true;

                unset($this->viewsOpened[$key]);

                break;
            }
        }

        $this->assertTrue($opened);

        return $this;
    }

    protected function assertModalUpdated(Modal $modal, string $viewId): static
    {
        $updated = false;

        foreach ($this->viewsUpdated as $key => $view) {
            if ($view->getViewId() === $viewId) {
                $this->assertEquals($modal->getTitle(), $view->getTitle());
                $this->assertEquals(Surface::MODAL, $view->getType());
                $this->assertNotEmpty($view->getBlocks());

                $updated = true;

                unset($this->viewsUpdated[$key]);

                break;
            }
        }

        $this->assertTrue($updated);

        return $this;
    }

    protected function createRepository(string $name, ?string $url = null): static
    {
        $arguments = [
            $this->getPlainTextArgument(ModalArgument::REPOSITORY_NAME, BlockElement::PLAIN_TEXT_INPUT, $name),
        ];

        if (null !== $url) {
            $arguments[] = $this->getPlainTextArgument(ModalArgument::REPOSITORY_URL, BlockElement::PLAIN_TEXT_INPUT, $url);
        }

        return $this
            ->assertRepositoryNotExists($name)
            ->sendAdminCommand(SubCommand::ADD_REPOSITORY)
            ->assertModalOpened(Modal::ADD_REPOSITORY)
            ->sendViewSubmission(
                Interaction::ADD_REPOSITORY,
                $arguments,
                userId: 'administrator'
            )
            ->assertInteractionResponseSentContainingMessage("Repository `$name` has been added to your workspace!")
            ->assertRepositoryExists($name, $url);
    }

    protected function assertRepositoryExists(
        string $repositoryName,
        ?string $repositoryUrl = null,
    ): static {
        $repositories = $this->getRepositoryRepository()->findBy([
            'name' => $repositoryName,
            'url' => $repositoryUrl,
            'workspace' => $this->getWorkspace(),
        ]);

        $this->assertNotEmpty($repositories);
        $this->assertCount(1, $repositories);

        return $this;
    }

    protected function assertRepositoryNotExists(
        string $repositoryName,
        ?string $repositoryUrl = null,
    ): static {
        $repositories = $this->getRepositoryRepository()->findBy([
            'name' => $repositoryName,
            'url' => $repositoryUrl,
            'workspace' => $this->getWorkspace(),
        ]);

        $this->assertEmpty($repositories);

        return $this;
    }

    protected function createDeploymentQueue(
        string $queueName,
        array $repositoryNames,
        QueueBehaviour $behaviour,
        ?int $maxEntries = null,
        ?int $expiryMinutes = null,
    ): static {
        $arguments = [
            $this->getPlainTextArgument(ModalArgument::QUEUE_NAME, BlockElement::PLAIN_TEXT_INPUT, $queueName),
            $this->getSingleSelectArgument(ModalArgument::QUEUE_TYPE, Queue::DEPLOYMENT->value),
            $this->getSingleSelectArgument(ModalArgument::QUEUE_BEHAVIOUR, $behaviour->value),
            $this->getMultiStaticSelectArgument(ModalArgument::QUEUE_REPOSITORIES, array_map(function ($repositoryName): string {
                return (string) $this->getRepositoryId($repositoryName);
            }, $repositoryNames)),
        ];

        if (null !== $maxEntries) {
            $arguments[] = $this->getNumberArgument(ModalArgument::QUEUE_MAXIMUM_ENTRIES_PER_USER, $maxEntries);
        }

        if (null !== $expiryMinutes) {
            $arguments[] = $this->getNumberArgument(ModalArgument::QUEUE_EXPIRY_MINUTES, $expiryMinutes);
        }

        return $this
            ->sendAdminCommand(SubCommand::ADD_QUEUE)
            ->assertModalOpened(Modal::ADD_QUEUE)
            ->sendInputSelectionInteraction(
                Interaction::QUEUE_TYPE,
                Queue::DEPLOYMENT->value,
                $viewId = 'add-deployment-queue-view',
                'administrator',
            )
            ->assertModalUpdated(Modal::ADD_QUEUE_DEPLOYMENT, $viewId)
            ->sendViewSubmission(
                Interaction::ADD_DEPLOYMENT_QUEUE,
                $arguments,
                userId: 'administrator'
            )
            ->assertDeploymentQueueExists(
                $queueName,
                $behaviour,
                $repositoryNames,
                $maxEntries,
                $expiryMinutes,
            )
            ->assertInteractionResponseSentContainingMessage("A deployment queue called `$queueName` has been created!");
    }

    /**
     * @param string $description Make sure this is unique on the queue you are joining as the code uses it to determine
     *                            the ID of a deployment on a specific queue. In the real world this will not be unique.
     *
     * @see FeatureTestCase::getDeploymentId
     */
    protected function joinDeploymentQueue(
        string $queueName,
        string $repositoryName,
        string $description,
        string $link,
        string $userId = 'test',
    ): static {
        return $this
            ->sendUserCommand(SubCommand::JOIN, [$queueName], $userId)
            ->assertModalOpened(Modal::JOIN_QUEUE_DEPLOYMENT)
            ->sendViewSubmission(
                Interaction::JOIN_QUEUE_DEPLOYMENT,
                [
                    $this->getPlainTextArgument(ModalArgument::DEPLOYMENT_DESCRIPTION, BlockElement::PLAIN_TEXT_INPUT, $description),
                    $this->getPlainTextArgument(ModalArgument::DEPLOYMENT_LINK, BlockElement::URL_INPUT, $link),
                    $this->getSingleSelectArgument(ModalArgument::DEPLOYMENT_REPOSITORY, (string) $this->getRepositoryId($repositoryName)),
                ],
                [
                    'join_queue_name' => $queueName,
                ],
                $userId
            );
    }

    protected function assertDeploymentExists(
        string $queueName,
        string $repositoryName,
        string $description,
        string $link,
        bool $draft = false,
        bool $pending = false,
        bool $active = false,
        bool $completed = false,
        string $userId = 'test',
        ?int $expiryMinutes = null,
        bool $hasExpiry = false,
    ): static {
        $queue = $this->getQueueRepository()->findOneByNameAndTeamId($queueName, 'test');

        $this->assertNotNull($queue);

        $deployment = $queue->getQueuedUsers()->findFirst(function (int $key, QueuedUser $queuedUser) use ($repositoryName, $description, $link, $draft, $pending, $active, $completed, $userId) {
            $this->assertInstanceOf(Deployment::class, $queuedUser);

            if ($repositoryName !== $queuedUser->getRepository()?->getName()) {
                return false;
            }

            if ($description !== $queuedUser->getDescription()) {
                return false;
            }

            if ($link !== $queuedUser->getLink()) {
                return false;
            }

            if ($draft !== $queuedUser->isDraft()) {
                return false;
            }

            if ($pending !== $queuedUser->isPending()) {
                return false;
            }

            if ($active !== $queuedUser->isActive()) {
                return false;
            }

            if ($completed !== $queuedUser->isCompleted()) {
                return false;
            }

            return $userId === $queuedUser->getUser()?->getSlackId();
        });

        $this->assertNotNull($deployment);
        $this->assertEquals($expiryMinutes, $deployment->getExpiryMinutes());

        if ($hasExpiry) {
            $this->assertNotNull($deployment->getExpiresAt());
        } else {
            $this->assertNull($deployment->getExpiresAt());
        }

        return $this;
    }

    protected function assertQueueExists(
        string $queueName,
        ?int $maxEntries = null,
        ?int $expiryMinutes = null,
    ): static {
        $queues = $this->getQueueRepository()->findBy([
            'name' => $queueName,
            'workspace' => $this->getWorkspace(),
            'maximumEntriesPerUser' => $maxEntries,
            'expiryMinutes' => $expiryMinutes,
        ]);

        $this->assertNotEmpty($queues);
        $this->assertCount(1, $queues);

        return $this;
    }

    protected function assertQueueNotExists(
        string $queueName,
        ?int $maxEntries = null,
        ?int $expiryMinutes = null,
    ): static {
        $queues = $this->getQueueRepository()->findBy([
            'name' => $queueName,
            'workspace' => $this->getWorkspace(),
            'maximumEntriesPerUser' => $maxEntries,
            'expiryMinutes' => $expiryMinutes,
        ]);

        $this->assertEmpty($queues);

        return $this;
    }

    protected function assertDeploymentQueueExists(
        string $queueName,
        QueueBehaviour $behaviour,
        array $repositoryNames,
        ?int $maxEntries = null,
        ?int $expiryMinutes = null,
    ): static {
        $queues = $this->getQueueRepository()->findBy([
            'name' => $queueName,
            'workspace' => $this->getWorkspace(),
            'maximumEntriesPerUser' => $maxEntries,
            'expiryMinutes' => $expiryMinutes,
        ]);

        $this->assertNotEmpty($queues);
        $this->assertCount(1, $queues);

        $queue = $queues[0];

        $this->assertInstanceOf(DeploymentQueue::class, $queue);
        $this->assertEquals($behaviour, $queue->getBehaviour());

        $this->assertCount(count($repositoryNames), $queue->getRepositories());

        foreach ($queue->getRepositories() as $repository) {
            $this->assertContains($repository->getName(), $repositoryNames);
        }

        foreach ($repositoryNames as $repositoryName) {
            $this->assertNotNull(
                $queue
                    ->getRepositories()
                    ->findFirst(fn (int $key, Repository $repository) => ($repository->getName() === $repositoryName))
            );
        }

        return $this;
    }

    protected function assertQueuedUserCount(string $queueName, int $count): static
    {
        $queue = $this->getQueueRepository()->findOneByNameAndTeamId($queueName, 'test');

        $this->assertNotNull($queue);

        $this->assertCount($count, $queue->getQueuedUsers());

        return $this;
    }

    protected function assertUserNotInQueue(string $queueName, string $userId = 'test'): static
    {
        return $this->assertQueuedUserPositionsInQueueCorrect($queueName, [], $userId);
    }

    protected function assertQueuedUserPositionsInQueueCorrect(
        string $queueName,
        array $expectedPositions,
        string $userId = 'test',
    ): static {
        $queue = $this->getQueueRepository()->findOneByNameAndTeamId($queueName, 'test');
        $this->assertNotNull($queue);

        $queuedUsers = $queue->getSortedUsers();

        $position = 0;
        $actualPositions = [];

        foreach ($queuedUsers as $queuedUser) {
            ++$position;

            if ($queuedUser->getUser()?->getSlackId() === $userId) {
                $actualPositions[] = $position;
            }
        }

        if (empty($expectedPositions)) {
            $this->assertEmpty($actualPositions);

            return $this;
        }

        foreach ($actualPositions as $position) {
            $this->assertContains($position, $expectedPositions);
        }

        foreach ($expectedPositions as $position) {
            $this->assertContains($position, $actualPositions);
        }

        return $this;
    }

    protected function assertQueuedUserInformationAtPosition(
        string $queueName,
        int $position,
        string $userId = 'test',
        ?int $expiryMinutes = null,
        bool $hasExpiresAt = false,
    ): static {
        $queue = $this->getQueueRepository()->findOneByNameAndTeamId($queueName, 'test');

        $this->assertNotNull($queue);

        $place = 0;

        $checksMade = false;

        foreach ($queue->getSortedUsers() as $user) {
            if (++$place === $position) {
                $this->assertEquals($userId, $user->getUser()?->getSlackId());
                $this->assertEquals($expiryMinutes, $user->getExpiryMinutes());

                if ($hasExpiresAt) {
                    $this->assertInstanceOf(CarbonImmutable::class, $user->getExpiresAt());
                } else {
                    $this->assertNull($user->getExpiresAt());
                }

                $checksMade = true;
            }
        }

        $this->assertTrue($checksMade, 'Could not find user at position '.$position);

        return $this;
    }

    protected function getQueuedUserIdForPosition(string $queueName, int $position): ?int
    {
        $queue = $this->getQueueRepository()->findOneByNameAndTeamId($queueName, 'test');

        $place = 0;

        foreach ($queue->getSortedUsers() as $queuedUser) {
            if (++$place === $position) {
                return $queuedUser->getId();
            }
        }

        return null;
    }

    /**
     * @param string $description This will have to be unique on the queue to work properly
     *
     * @see FeatureTestCase::joinDeploymentQueue
     */
    protected function getDeploymentId(string $queueName, string $description): ?int
    {
        $queue = $this->getQueueRepository()->findOneByNameAndTeamId($queueName, 'test');

        foreach ($queue->getQueuedUsers() as $queuedUser) {
            $this->assertInstanceOf(Deployment::class, $queuedUser);

            if ($description === $queuedUser->getDescription()) {
                return $queuedUser->getId();
            }
        }

        return null;
    }

    protected function getQueueId(string $queueName): int
    {
        $queue = $this->getQueueRepository()->findOneByNameAndTeamId($queueName, 'test');

        $this->assertNotNull($queue);

        return $queue->getId() ?? 1;
    }

    protected function getRepositoryId(string $repositoryName): int
    {
        if (array_key_exists($repositoryName, $this->repositoryIdMap)) {
            return $this->repositoryIdMap[$repositoryName];
        }

        $repository = $this->getRepositoryRepository()->findOneByNameAndTeamId($repositoryName, 'test');

        $this->assertNotNull($repository);

        return $this->repositoryIdMap[$repositoryName] = $repository->getId() ?? 1;
    }

    private function getWorkspace(): Workspace
    {
        if (isset($this->workspace)) {
            return $this->workspace;
        }

        /** @var WorkspaceRepositoryInterface $workspaceRepository */
        $workspaceRepository = static::getContainer()->get(WorkspaceRepositoryInterface::class);

        $workspace = $workspaceRepository->findOneBy(['slackId' => 'test']);

        if ($workspace instanceof Workspace) {
            return $workspace;
        }

        return $this->createWorkspace();
    }

    private function createWorkspace(): Workspace
    {
        $workspace = new Workspace()
            ->setSlackId('test')
            ->setName('test')
            ->setBotToken('test');

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $entityManager->persist($workspace);
        $entityManager->flush();

        return $workspace;
    }

    private function createAdministrator(): void
    {
        $user = new User()
            ->setSlackId('administrator')
            ->setWorkspace($this->workspace);

        $administrator = new Administrator()
            ->setWorkspace($this->workspace)
            ->setUser($user);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $entityManager->persist($administrator);
        $entityManager->flush();
    }

    private function getQueueRepository(): QueueRepositoryInterface
    {
        if (isset($this->queueRepository)) {
            return $this->queueRepository;
        }

        /** @var QueueRepositoryInterface $repository */
        $repository = static::getContainer()->get(QueueRepositoryInterface::class);

        return $this->queueRepository = $repository;
    }

    private function getRepositoryRepository(): RepositoryRepositoryInterface
    {
        if (isset($this->repositoryRepository)) {
            return $this->repositoryRepository;
        }

        /** @var RepositoryRepositoryInterface $repository */
        $repository = static::getContainer()->get(RepositoryRepositoryInterface::class);

        return $this->repositoryRepository = $repository;
    }

    protected function getMultiStaticSelectArgument(ModalArgument $argument, array $selectedOptions): StateArgument
    {
        return new StateArgument(
            BlockElement::MULTI_STATIC_SELECT,
            $argument->value,
            [
                'selected_options' => array_map(fn ($selectedOption) => ['value' => (string) $selectedOption], $selectedOptions),
            ],
        );
    }

    protected function getMultiUserSelectArgument(ModalArgument $argument, array $selectedUsers): StateArgument
    {
        return new StateArgument(
            BlockElement::MULTI_USERS_SELECT,
            $argument->value,
            [
                'selected_users' => $selectedUsers,
            ],
        );
    }

    protected function getSingleSelectArgument(ModalArgument $argument, string $selectedOption): StateArgument
    {
        return new StateArgument(
            BlockElement::STATIC_SELECT,
            $argument->value,
            [
                'selected_option' => [
                    'value' => $selectedOption,
                ],
            ],
        );
    }

    protected function getNumberArgument(ModalArgument $argument, int $value): StateArgument
    {
        return new StateArgument(
            BlockElement::NUMBER_INPUT,
            $argument->value,
            [
                'value' => (string) $value,
            ],
        );
    }

    protected function getPlainTextArgument(ModalArgument $argument, BlockElement $type, string $value): StateArgument
    {
        return new StateArgument(
            $type,
            $argument->value,
            [
                'value' => $value,
            ],
        );
    }

    protected function setQueuedUserExpired(string $queueName, ?int $position = null, ?string $description = null): static
    {
        $queue = $this->getQueueRepository()->findOneByNameAndTeamId($queueName, 'test');

        $this->assertNotNull($queue);

        if (null !== $position) {
            $this->assertArrayHasKey($position - 1, $queue->getSortedUsers(), 'Could not find user at position '.$position);

            $queuedUser = $queue->getSortedUsers()[$position - 1];
        } else {
            $queuedUser = $queue->getQueuedUsers()->findFirst(function (int $key, QueuedUser $deployment) use ($description) {
                $this->assertInstanceOf(Deployment::class, $deployment);

                return $deployment->getDescription() === $description;
            });

            $this->assertNotNull($queuedUser, 'Could not find user with description '.$description);
        }

        $queuedUser->setExpiresAt(CarbonImmutable::now()->subHour());

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $entityManager->persist($queuedUser);
        $entityManager->flush();

        return $this;
    }

    protected function sendAutomaticPopQueuesMessage(): static
    {
        /** @var MessageBusInterface $messageBus */
        $messageBus = static::getContainer()->get(MessageBusInterface::class);

        $messageBus->dispatch(new PopQueuesMessage());

        return $this;
    }
}
