<?php

declare(strict_types=1);

namespace App\Tests\Integration\Controller\Queue;

use App\Controller\Queue\QueuedUserController;
use App\Enum\QueueBehaviour;
use App\Tests\Integration\ApiTestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;

#[CoversClass(QueuedUserController::class)]
class QueuedUserControllerTest extends ApiTestCase
{
    #[Test]
    public function itShouldReturnListOfQueuedUsers(): void
    {
        $this->jsonGet('/api/queue/queueName/queued-user/');
        $this->assertNotFound();

        $this->jsonPost('/api/queue/', [
            'type' => 'simple',
            'name' => 'queueName',
        ], administrator: true);
        $this->assertCreated();

        $this->jsonGet('/api/queue/queueName/queued-user/');
        $this->assertOk();
        $this->assertEmpty($this->getJsonResponse());

        $this->jsonPost('/api/queue/queueName/queued-user', [
            'user' => $this->getUser()->getId(),
            'type' => 'simple',
        ]);
        $this->assertCreated();

        $this->jsonGet('/api/queue/queueName/queued-user/');
        $this->assertOk();
        $this->assertNotEmpty($response = $this->getJsonResponse());
        $this->assertCount(1, $response);

        $queuedUser = $response[0];

        $this->assertIsArray($queuedUser);

        $this->assertArrayHasKey('id', $queuedUser);
        $this->assertArrayHasKey('user', $queuedUser);
        $this->assertArrayHasKey('createdAt', $queuedUser);
        $this->assertArrayHaskey('expiresAt', $queuedUser);
        $this->assertArrayHaskey('queue', $queuedUser);
        $this->assertArrayHasKey('expiryMinutes', $queuedUser);
    }

    #[Test]
    public function itShouldShowQueuedUser(): void
    {
        $this->jsonGet('/api/queue/queueName/queued-user/0');
        $this->assertNotFound();

        $this->jsonPost('/api/queue/', [
            'type' => 'simple',
            'name' => 'queueName',
        ], administrator: true);
        $this->assertCreated();

        $this->jsonGet('/api/queue/queueName/queued-user/0');
        $this->assertNotFound();

        $this->jsonPost('/api/queue/queueName/queued-user', [
            'user' => $this->getUser()->getId(),
            'type' => 'simple',
        ]);
        $this->assertCreated();
        $this->assertArrayHasKey('id', $response = $this->getJsonResponse());
        $this->assertIsInt($id = $response['id']);

        $this->jsonGet('/api/queue/queueName/queued-user/'.$id);
        $this->assertOk();
        $this->assertNotEmpty($this->getJsonResponse());
    }

    #[Test]
    public function itShouldThrowValidationErrorIfUserCannotJoinQueue(): void
    {
        $this->jsonGet('/api/queue/queueName/queued-user/0');
        $this->assertNotFound();

        $this->jsonPost('/api/queue/', [
            'type' => 'simple',
            'name' => 'queueName',
            'maximumEntriesPerUser' => 1,
        ], administrator: true);
        $this->assertCreated();

        $this->jsonPost('/api/queue/queueName/queued-user', [
            'user' => $this->getUser()->getId(),
            'type' => 'simple',
        ]);
        $this->assertCreated();

        $this->jsonPost('/api/queue/queueName/queued-user', [
            'user' => $this->getUser()->getId(),
        ]);
        $this->assertUnprocessable();
        $this->assertValidationErrorExistsAtProperty('user', 'You are already in the queueName queue.');
    }

    #[Test]
    public function itShouldThrowValidationErrorIfUserAddsOtherUser(): void
    {
        $this->jsonGet('/api/queue/queueName/queued-user/0');
        $this->assertNotFound();

        $this->jsonPost('/api/queue/', [
            'type' => 'simple',
            'name' => 'queueName',
            'maximumEntriesPerUser' => 1,
        ], administrator: true);
        $this->assertCreated();

        $this->jsonPost('/api/queue/queueName/queued-user', [
            'user' => $this->getAdministrator()->getId(),
        ]);
        $this->assertUnprocessable();
        $this->assertValidationErrorExistsAtProperty('user', 'The selected choice is invalid.');
    }

    #[Test]
    public function itShouldThrowValidationErrorIfQueueIsDeploymentQueue(): void
    {
        $this->jsonGet('/api/queue/queueName/queued-user/0');
        $this->assertNotFound();

        $repository = $this->createRepository();

        $this->jsonPost('/api/queue/', [
            'type' => 'deployment',
            'name' => 'queueName',
            'repositories' => [$repository],
            'behaviour' => QueueBehaviour::ENFORCE_QUEUE->value,
        ], administrator: true);
        $this->assertCreated();

        $this->jsonPost('/api/queue/queueName/queued-user', [
            'type' => 'simple',
            'user' => $this->getUser()->getId(),
        ]);
        $this->assertUnprocessable();
        $this->assertValidationErrorExistsAtProperty('link', 'This value should not be blank.');
        $this->assertValidationErrorExistsAtProperty('description', 'This value should not be blank.');
        $this->assertValidationErrorExistsAtProperty('repository', 'This value should not be null.');
        $this->assertValidationErrorExistsAtProperty('type', 'The selected choice is invalid.');
    }

    #[Test]
    public function itShouldThrowValidationErrorIfLinkInvalid(): void
    {
        $this->jsonGet('/api/queue/queueName/queued-user/0');
        $this->assertNotFound();

        $repository = $this->createRepository();

        $this->jsonPost('/api/queue/', [
            'type' => 'deployment',
            'name' => 'queueName',
            'repositories' => [$repository],
            'behaviour' => QueueBehaviour::ENFORCE_QUEUE->value,
        ], administrator: true);
        $this->assertCreated();

        $this->jsonPost('/api/queue/queueName/queued-user', [
            'type' => 'deployment',
            'user' => $this->getUser()->getId(),
            'link' => 'invalid link',
            'description' => 'description',
            'repository' => $repository,
        ]);
        $this->assertUnprocessable();
        $this->assertValidationErrorExistsAtProperty('link', 'This value is not a valid URL.');
    }

    #[Test]
    public function itShouldThrowNotFoundExceptionIfQueueNotExistsOnCreate(): void
    {
        $this->jsonPost('/api/queue/queueName/queued-user', [
            'type' => 'deployment',
            'user' => $this->getUser()->getId(),
            'link' => 'https://example.com',
            'description' => 'description',
            'repository' => $this->createRepository(),
        ]);
        $this->assertNotFound();
    }

    #[Test]
    public function itShouldCreateDeployment(): void
    {
        $this->jsonGet('/api/queue/queueName/queued-user/0');
        $this->assertNotFound();

        $repository = $this->createRepository();

        $this->jsonPost('/api/queue/', [
            'type' => 'deployment',
            'name' => 'queueName',
            'repositories' => [$repository],
            'behaviour' => QueueBehaviour::ENFORCE_QUEUE->value,
        ], administrator: true);
        $this->assertCreated();

        $this->jsonPost('/api/queue/queueName/queued-user', [
            'type' => 'deployment',
            'user' => $this->getUser()->getId(),
            'link' => 'https://example.com',
            'description' => 'description',
            'repository' => $repository,
        ]);
        $this->assertCreated();
        $id = $this->extractIdFromResponse();

        $this->jsonGet('/api/queue/queueName/queued-user/'.$id);
        $this->assertOk();
    }

    #[Test]
    public function itShouldPopQueues(): void
    {
        $this->jsonGet('/api/queue/queueName/queued-user/0');
        $this->assertNotFound();

        $repository = $this->createRepository();

        $this->jsonPost('/api/queue/', [
            'type' => 'deployment',
            'name' => 'queueName',
            'repositories' => [$repository],
            'behaviour' => QueueBehaviour::ENFORCE_QUEUE->value,
        ], administrator: true);
        $this->assertCreated();

        $this->jsonPost('/api/queue/queueName/queued-user', [
            'type' => 'deployment',
            'user' => $this->getUser()->getId(),
            'link' => 'https://example.com',
            'description' => 'description',
            'repository' => $repository,
        ]);
        $this->assertCreated();
        $id = $this->extractIdFromResponse();

        $this->jsonDelete('/api/queue/queueName/queued-user/'.$id, administrator: true);
        $this->assertNoContent();

        $this->jsonGet('/api/queue/queueName/queued-user/'.$id);
        $this->assertNotFound();
    }

    #[Test]
    public function itShouldLeaveQueue(): void
    {
        $this->jsonGet('/api/queue/queueName/queued-user/0');
        $this->assertNotFound();

        $repository = $this->createRepository();

        $this->jsonPost('/api/queue/', [
            'type' => 'deployment',
            'name' => 'queueName',
            'repositories' => [$repository],
            'behaviour' => QueueBehaviour::ENFORCE_QUEUE->value,
        ], administrator: true);
        $this->assertCreated();

        $this->jsonPost('/api/queue/queueName/queued-user', [
            'type' => 'deployment',
            'user' => $this->getUser()->getId(),
            'link' => 'https://example.com',
            'description' => 'description',
            'repository' => $repository,
        ]);
        $this->assertCreated();
        $id = $this->extractIdFromResponse();

        $this->jsonDelete('/api/queue/queueName/queued-user/'.$id);
        $this->assertNoContent();

        $this->jsonGet('/api/queue/queueName/queued-user/'.$id);
        $this->assertNotFound();
    }

    #[Test]
    public function itShouldNotAllowUserToPopQueue(): void
    {
        $this->jsonGet('/api/queue/queueName/queued-user/0');
        $this->assertNotFound();

        $repository = $this->createRepository();

        $this->jsonPost('/api/queue/', [
            'type' => 'deployment',
            'name' => 'queueName',
            'repositories' => [$repository],
            'behaviour' => QueueBehaviour::ENFORCE_QUEUE->value,
        ], administrator: true);
        $this->assertCreated();

        $this->jsonPost('/api/queue/queueName/queued-user', [
            'type' => 'deployment',
            'user' => $this->getAdministrator()->getId(),
            'link' => 'https://example.com',
            'description' => 'description',
            'repository' => $repository,
        ], administrator: true);
        $this->assertCreated();
        $response = $this->getJsonResponse();

        $id = $this->extractIdFromResponse();

        $this->jsonDelete('/api/queue/queueName/queued-user/'.$id);
        $this->assertUnauthorized();

        $this->jsonGet('/api/queue/queueName/queued-user/'.$id);
        $this->assertOk();

        $this->assertEquals($response, $this->getJsonResponse());
    }

    #[Test]
    public function itShouldThrowNotFoundExceptionIfQueueNotExistsOnDelete(): void
    {
        $this->jsonDelete('/api/queue/queueName/queued-user/0');
        $this->assertNotFound();
    }
}
