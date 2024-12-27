<?php

declare(strict_types=1);

namespace App\Tests\Behavior;

use App\Domain\BankAccount;
use App\Domain\BankAccountId;
use App\Domain\Command\CloseBankAccount;
use App\Domain\Command\OpenBankAccount;
use App\Domain\Command\SetOverdraftLimit;
use App\Domain\Event\MoneyDeposited;
use App\Domain\Event\MoneyWithDrawn;
use App\Domain\Exception\InvalidDepositAmountException;
use App\Domain\Exception\InvalidWithDrawnAmountException;
use App\Domain\Exception\OverDraftLimitException;
use EventSauce\EventSourcing\AggregateRootId;
use EventSauce\EventSourcing\TestUtilities\AggregateRootTestCase;

use function PHPUnit\Framework\assertEquals;

abstract class BankAccountTestCase extends AggregateRootTestCase
{
    protected function newAggregateRootId(): AggregateRootId
    {
        return BankAccountId::create();
    }

    protected function aggregateRootClassName(): string
    {
        return BankAccount::class;
    }

    protected function aggregateRootId(): BankAccountId
    {
        /** @var BankAccountId $bankAccountId */
        $bankAccountId = parent::aggregateRootId();

        return $bankAccountId;
    }

    protected function retrieveAggregateRoot(AggregateRootId $id): BankAccount
    {
        /** @var BankAccount $bankAccount */
        $bankAccount = parent::retrieveAggregateRoot($id);

        return $bankAccount;
    }

    public function handle($command): void
    {
        if ($command instanceof OpenBankAccount) {
            $bankAccount = BankAccount::openBankAccount($command);
        } else {
            $bankAccount = $this->retrieveAggregateRoot($this->aggregateRootId());
        }

        if ($command instanceof SetOverdraftLimit) {
            $bankAccount->setOverdraftLimit($command);
        } elseif ($command instanceof CloseBankAccount) {
            $bankAccount->closeBankAccount($command);
        }

        $this->repository->persist($bankAccount);
    }
    // test for zero balance 
    public function testDepositZeroAmountThrowsException()
    {
        $this->expectException(InvalidDepositAmountException::class);
        $bankAccount = new BankAccount(0.0);
        $bankAccount->depositMoney(0.0);
    }

    // test for negative balance 
    public function testDepositNegativeBalanceThrowsException()
    {
        $this->expectException(InvalidDepositAmountException::class);
        $bankAccount = new BankAccount(-1);
        $bankAccount->depositMoney(0.0);
    }

    public function testDepositMoney()
    {
        $bankAccount = new BankAccount(50.0);
        $event  = $bankAccount->depositMoney(100.0);

        $this->assertInstanceOf(MoneyDeposited::class, $event);
        $this->assertEquals(150.0, $bankAccount->getBalance());
        $this->assertEquals(100.0, $event->getAmount());
        $this->assertEquals(50.0, $event->getNewBalance());
    }

    // test for withdrawn amount
    public function testWithdrawMoney()
    {
        $bankAccount = new BankAccount(100.0);
        $event  = $bankAccount->withdrawMoney(50.0);

        $this->assertInstanceOf(MoneyWithDrawn::class, $event);
        $this->assertEquals(50.0, $bankAccount->getBalance());
        $this->assertEquals(50.0, $event->getAmount());
        $this->assertEquals(50.0, $event->getNewBalance());
    }

    //exceed limit 
    public function testWithdrawMoneyExceedOverDraft()
    {
        $this->expectException(OverDraftLimitException::class);
        $bankAccount = new BankAccount(100.0);
        $bankAccount->withdrawMoney(200.0);
    }

    // test for zero balance 
    public function testWithDrawZeroAmountThrowsException()
    {
        $this->expectException(InvalidWithDrawnAmountException::class);
        $bankAccount = new BankAccount(0.0);
        $bankAccount->withdrawMoney(0.0);
    }

    // test for negative balance 
    public function testWithdrawNegativeBalanceThrowsException()
    {
        $this->expectException(InvalidWithDrawnAmountException::class);
        $bankAccount = new BankAccount(0.0);
        $bankAccount->withdrawMoney(-10.0);
    }
}
