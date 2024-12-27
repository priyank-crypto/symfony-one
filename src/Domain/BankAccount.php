<?php

declare(strict_types=1);

namespace App\Domain;

use App\Domain\Command\CloseBankAccount;
use App\Domain\Command\OpenBankAccount;
use App\Domain\Command\SetOverdraftLimit;
use App\Domain\Event\BankAccountClosed;
use App\Domain\Event\BankAccountOpened;
use App\Domain\Event\OverdraftLimitSet;
use App\Domain\Exception\CannotCloseBankAccountBecauseAccountIsNotActive;
use App\Domain\ValueObject\AccountStatus;
use App\Domain\ValueObject\AccountType;
use App\Domain\ValueObject\Currency;
use EventSauce\EventSourcing\AggregateRoot;
use EventSauce\EventSourcing\AggregateRootBehaviour;
use App\Domain\Event\MoneyDeposited;
use App\Domain\Event\MoneyWithDrawn;
use App\Domain\Exception\InvalidDepositAmountException;
use App\Domain\Exception\InvalidWithDrawnAmountException;
use App\Domain\Exception\OverDraftLimitException;

/**
 * @implements AggregateRoot<BankAccountId>
 */
class BankAccount implements AggregateRoot
{
    /**
     * @use AggregateRootBehaviour<BankAccountId>
     */
    use AggregateRootBehaviour;

    private string $accountHolderName;
    private float $balance = 0;
    private AccountType $type;
    private Currency $currency;
    private float $overdraftLimit = 0;
    private AccountStatus $status;

    public static function openBankAccount(OpenBankAccount $command): self
    {
        $bankAccount = new self($command->bankAccountId);
        $bankAccount->recordThat(
            event: new BankAccountOpened(
                bankAccountId: $command->bankAccountId,
                accountHolderName: $command->accountHolderName,
                accountType: $command->accountType,
                currency: $command->currency,
            )
        );

        return $bankAccount;
    }

    public function getAccountHolderName(): string
    {
        return $this->accountHolderName;
    }

    public function getBalance(): float
    {
        return $this->balance;
    }

    public function getType(): AccountType
    {
        return $this->type;
    }

    public function getCurrency(): Currency
    {
        return $this->currency;
    }

    public function getOverdraftLimit(): float
    {
        return $this->overdraftLimit;
    }

    public function getStatus(): AccountStatus
    {
        return $this->status;
    }

    public function setOverdraftLimit(SetOverdraftLimit $command): void
    {
        $this->recordThat(
            event: new OverdraftLimitSet(
                bankAccountId: $this->aggregateRootId(),
                newOverdraftLimit: $command->overdraftLimit,
                oldOverdraftLimit: $this->overdraftLimit
            )
        );
    }

    public function closeBankAccount(CloseBankAccount $command): void
    {
        if (AccountStatus::ACTIVE !== $this->status) {
            throw new CannotCloseBankAccountBecauseAccountIsNotActive();
        }
        $this->recordThat(
            event: new BankAccountClosed(
                bankAccountId: $this->aggregateRootId(),
            )
        );
    }

    /**
     * @phpstan-ignore method.unused (Used by \EventSauce\EventSourcing\AggregateAlwaysAppliesEvents)
     */
    private function applyBankAccountOpened(BankAccountOpened $event): void
    {
        $this->accountHolderName = $event->accountHolderName;
        $this->type = $event->accountType;
        $this->currency = $event->currency;
        $this->status = AccountStatus::ACTIVE;
    }

    /**
     * @phpstan-ignore method.unused (Used by \EventSauce\EventSourcing\AggregateAlwaysAppliesEvents)
     */
    private function applyOverdraftLimitSet(OverdraftLimitSet $event): void
    {
        $this->overdraftLimit = $event->newOverdraftLimit;
    }

    /**
     * @phpstan-ignore method.unused (Used by \EventSauce\EventSourcing\AggregateAlwaysAppliesEvents)
     */
    private function applyBankAccountClosed(BankAccountClosed $event): void
    {
        $this->status = AccountStatus::CLOSED;
    }

    public function __construct(float $initialBalance)
    {
        $this->balance = $initialBalance;
    }

    // deposit money 
    public function depositMoney(float $amount): MoneyDeposited
    {
        if ($amount <= 0) {
            throw new InvalidDepositAmountException("Deposite amount should be greater than zero");
        }
        $this->balance  += $amount;
        return new MoneyDeposited($amount, $this->balance);
    }

    // withdrawl money
    public function withdrawMoney(float $amount): MoneyWithDrawn
    {
        if ($amount <= 0) {
            throw new InvalidWithDrawnAmountException("Withdrawn amount should be greater than zero");
        }
        if ($this->balance - $amount < $this->overdraftLimit) {
            throw new OverDraftLimitException("Withdrawn exceeds overdraft limit");
        }
        $this->balance -= $amount;
        return new MoneyWithDrawn($amount, $this->balance);
    }
}
