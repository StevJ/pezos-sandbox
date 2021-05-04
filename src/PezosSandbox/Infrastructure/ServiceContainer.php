<?php

declare(strict_types=1);

namespace PezosSandbox\Infrastructure;

use Assert\Assert;
use PezosSandbox\Application\AccessPolicy;
use PezosSandbox\Application\Application;
use PezosSandbox\Application\ApplicationInterface;
use PezosSandbox\Application\Clock;
use PezosSandbox\Application\EventDispatcher;
use PezosSandbox\Application\EventDispatcherWithSubscribers;
use PezosSandbox\Application\Members\Members;
use PezosSandbox\Domain\Model\Member\MemberRepository;
use Symfony\Component\Security\Core\Encoder\PasswordEncoderInterface;
use Symfony\Component\Security\Core\Encoder\UserPasswordEncoder;
use Test\Acceptance\FakeClock;

abstract class ServiceContainer
{
    protected ?EventDispatcher $eventDispatcher = null;
    protected ?ApplicationInterface $application  = null;
    protected ?MemberRepository $memberRepository = null;
    private ?Clock $clock                         = null;

    public function eventDispatcher(): EventDispatcher
    {
        if (null === $this->eventDispatcher) {
            $this->eventDispatcher = new EventDispatcherWithSubscribers();

            $this->registerEventSubscribers($this->eventDispatcher);
        }

        Assert::that($this->eventDispatcher)->isInstanceOf(
            EventDispatcher::class,
        );

        return $this->eventDispatcher;
    }

    public function application(): ApplicationInterface
    {
        if (null === $this->application) {
            $this->application = new Application(
                $this->memberRepository(),
                $this->eventDispatcher(),
                $this->members(),
                $this->clock(),
            );
        }

        return $this->application;
    }

    protected function clock(): Clock
    {
        if (null === $this->clock) {
            $this->clock = new FakeClock();
        }

        return $this->clock;
    }

    protected function registerEventSubscribers(
        EventDispatcherWithSubscribers $eventDispatcher
    ): void {
        /* $eventDispatcher->subscribeToSpecificEvent(AccessWasGranted::class, [ */
        /*     $this->accessPolicy(), */
        /*     'whenAccessWasGranted', */
        /* ]); */
    }

    abstract protected function memberRepository(): MemberRepository;

    abstract protected function members(): Members;

    private function accessPolicy(): AccessPolicy
    {
        return new AccessPolicy($this->application());
    }
}
