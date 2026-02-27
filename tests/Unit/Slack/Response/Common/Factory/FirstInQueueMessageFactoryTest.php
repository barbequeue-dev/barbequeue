<?php

declare(strict_types=1);

namespace App\Tests\Unit\Slack\Response\Common\Factory;

use App\Entity\Queue;
use App\Entity\QueuedUser;
use App\Entity\User;
use App\Entity\Workspace;
use App\Slack\Response\PrivateMessage\Factory\FirstInQueueMessageFactory;
use App\Tests\Unit\Slack\WithBlockAssertions;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(FirstInQueueMessageFactory::class)]
class FirstInQueueMessageFactoryTest extends KernelTestCase
{
    use WithBlockAssertions;

    #[Test]
    public function itShouldCreatePrivateMessageWithExpiry(): void
    {
        $expiresAt = CarbonImmutable::now()->addMinutes(5);

        $user = $this->createStub(User::class);
        $queuedUser = $this->createMock(QueuedUser::class);
        $queuedUser->expects($this->once())
            ->method('getExpiresAt')
            ->willReturn($expiresAt);

        $queue = $this->createMock(Queue::class);
        $queue->expects($this->once())
            ->method('getWorkspace')
            ->willReturn($workspace = $this->createStub(Workspace::class));

        $queue->expects($this->once())
            ->method('getName')
            ->willReturn('queueName');

        $queuedUser->expects($this->exactly(2))
            ->method('getQueue')
            ->willReturn($queue);

        $queuedUser->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $factory = new FirstInQueueMessageFactory();

        $response = $factory->create($queuedUser);

        $this->assertEquals($user, $response->getUser());
        $this->assertSame($workspace, $response->getWorkspace());

        $expectedMessage = 'You are now first in the `queueName` queue! You will be removed automatically after 5 minutes.';

        $response = $response->toArray();

        $this->assertArrayHasKey('text', $response);
        $this->assertEquals($expectedMessage, $response['text']);

        $this->assertArrayHasKey('blocks', $response);
        $this->assertIsString($blocks = $response['blocks']);

        $blocks = json_decode($blocks, true);

        $this->assertIsArray($blocks);

        $this->assertCount(1, $blocks);
        $this->assertSectionBlockCorrectlyFormatted($expectedMessage, $blocks[0]);
    }

    #[Test]
    public function itShouldCreatePrivateMessageWithoutExpiry(): void
    {
        $expiresAt = null;

        $user = $this->createStub(User::class);

        $queuedUser = $this->createMock(QueuedUser::class);
        $queuedUser->expects($this->once())
            ->method('getExpiresAt')
            ->willReturn($expiresAt);

        $queue = $this->createMock(Queue::class);
        $queue->expects($this->once())
            ->method('getWorkspace')
            ->willReturn($workspace = $this->createStub(Workspace::class));

        $queue->expects($this->once())
            ->method('getName')
            ->willReturn('queueName');

        $queuedUser->expects($this->exactly(2))
            ->method('getQueue')
            ->willReturn($queue);

        $queuedUser->expects($this->once())
            ->method('getUser')
            ->willReturn($user);

        $factory = new FirstInQueueMessageFactory();

        $response = $factory->create($queuedUser);

        $this->assertEquals($user, $response->getUser());
        $this->assertSame($workspace, $response->getWorkspace());

        $expectedMessage = 'You are now first in the `queueName` queue!';

        $response = $response->toArray();

        $this->assertArrayHasKey('text', $response);
        $this->assertEquals($expectedMessage, $response['text']);

        $this->assertArrayHasKey('blocks', $response);
        $this->assertIsString($blocks = $response['blocks']);

        $blocks = json_decode($blocks, true);

        $this->assertIsArray($blocks);

        $this->assertCount(1, $blocks);
        $this->assertSectionBlockCorrectlyFormatted($expectedMessage, $blocks[0]);
    }
}
