<?php

declare(strict_types=1);

namespace PezosSandbox\Application;

use PezosSandbox\Application\Exchanges\Exchange as ExchangeReadModel;
use PezosSandbox\Application\Exchanges\Exchanges;
use PezosSandbox\Application\Members\Member as MemberReadModel;
use PezosSandbox\Application\Members\Members;
use PezosSandbox\Application\RequestAccess\RequestAccess;
use PezosSandbox\Application\Tokens\Token as TokenReadModel;
use PezosSandbox\Application\Tokens\Tokens;
use PezosSandbox\Domain\Model\Exchange\Exchange;
use PezosSandbox\Domain\Model\Exchange\ExchangeRepository;
use PezosSandbox\Domain\Model\Member\Member;
use PezosSandbox\Domain\Model\Member\MemberRepository;
use PezosSandbox\Domain\Model\Member\PubKey;
use PezosSandbox\Domain\Model\Token\Address as TokenAddress;
use PezosSandbox\Domain\Model\Token\Token;
use PezosSandbox\Domain\Model\Token\TokenRepository;

class Application implements ApplicationInterface
{
    private ExchangeRepository $exchangeRepository;
    private MemberRepository $memberRepository;
    private TokenRepository $tokenRepository;
    private EventDispatcher $eventDispatcher;
    private Exchanges $exchanges;
    private Members $members;
    private Tokens $tokens;
    private Clock $clock;

    public function __construct(
        ExchangeRepository $exchangeRepository,
        MemberRepository $memberRepository,
        TokenRepository $tokenRepository,
        EventDispatcher $eventDispatcher,
        Exchanges $exchanges,
        Members $members,
        Tokens $tokens,
        Clock $clock
    ) {
        $this->exchangeRepository = $exchangeRepository;
        $this->memberRepository   = $memberRepository;
        $this->tokenRepository    = $tokenRepository;
        $this->eventDispatcher    = $eventDispatcher;
        $this->exchanges          = $exchanges;
        $this->members            = $members;
        $this->tokens             = $tokens;
        $this->clock              = $clock;
    }

    public function requestAccess(RequestAccess $command): void
    {
        $member = Member::requestAccess(
            $command->pubKey(),
            $this->clock->currentTime()
        );

        $this->memberRepository->save($member);
    }

    public function grantAccess(PubKey $pubKey): void
    {
        $member = $this->memberRepository->getByPubKey($pubKey);
        $member->grantAccess();
        $this->memberRepository->save($member);
    }

    public function addExchange(AddExchange $addExchange): void
    {
        $exchangeId = $this->exchangeRepository->nextIdentity();
        $exchange   = Exchange::createExchange(
            $exchangeId->asString(),
            $addExchange->name(),
            $addExchange->homepage()
        );

        $this->exchangeRepository->save($exchange);
    }

    public function updateExchange(UpdateExchange $updateExchange): void
    {
        $exchange = $this->exchangeRepository->getById(
            $updateExchange->exchangeId()
        );
        $this->exchangeRepository->save($exchange);
    }

    public function addToken(AddToken $command): void
    {
        $tokenId = $this->tokenRepository->nextIdentity();
        $token   = Token::createToken($tokenId, $command->address(), []);

        $this->tokenRepository->save($token);
        $this->eventDispatcher->dispatchAll($token->releaseEvents());
    }

    public function updateToken(UpdateToken $command): void
    {
        $token = $this->tokenRepository->getById($command->tokenId());
        $token->update(
            $command->address(),
            $command->metadata(),
            $command->active()
        );
        $this->tokenRepository->save($token);
    }

    public function getOneExchangeByName(string $name): ExchangeReadModel
    {
        return $this->exchanges->getOneByName($name);
    }

    public function getOneMemberByPubKey(string $pubKey): MemberReadModel
    {
        return $this->members->getOneByPubKey(PubKey::fromString($pubKey));
    }

    public function getOneTokenByAddress(string $tokenAddress): TokenReadModel
    {
        $address = TokenAddress::fromString($tokenAddress);

        return $this->tokens->getOneByAddress($address);
    }

    public function addTokenExchange(AddTokenExchange $command): void
    {
        $token = $this->tokenRepository->getById($command->tokenId());
        $token->addExchange($command->exchangeId(), $command->contract());
        $this->tokenRepository->save($token);
    }

    public function listMembersForAdministrator(): array
    {
        return $this->members->listMembers();
    }

    /**
     * @return array<TokenReadModel>
     */
    public function listTokens(): array
    {
        return $this->tokens->listTokens();
    }

    /**
     * @return array<TokenReadModel>
     */
    public function listTokensForAdmin(): array
    {
        return $this->tokens->listTokensForAdmin();
    }
}
