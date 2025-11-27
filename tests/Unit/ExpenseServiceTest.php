<?php

declare(strict_types=1);

namespace Tests\Unit;

use Alexi\MyWeeklyAllowance\Entity\Child;
use Alexi\MyWeeklyAllowance\Entity\Transaction;
use Alexi\MyWeeklyAllowance\Exception\ChildNotFoundException;
use Alexi\MyWeeklyAllowance\Exception\InsufficientBalanceException;
use Alexi\MyWeeklyAllowance\Exception\InvalidAmountException;
use Alexi\MyWeeklyAllowance\Service\ChildService;
use Alexi\MyWeeklyAllowance\Service\DepositService;
use Alexi\MyWeeklyAllowance\Service\ExpenseService;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for ExpenseService
 *
 * Tests recording expenses and deducting from a child's balance
 */
class ExpenseServiceTest extends TestCase
{
    private ExpenseService $expenseService;
    private ChildService $childService;
    private DepositService $depositService;
    private Child $testChild;

    protected function setUp(): void
    {
        $this->childService = new ChildService();
        $this->depositService = new DepositService($this->childService);
        $this->expenseService = new ExpenseService($this->childService);

        // Create a test child and give them some initial balance
        $this->testChild = $this->childService->createChild(1, 'Tom');
        $this->depositService->deposit($this->testChild->getId(), 100.0, 'Initial deposit');
    }

    /**
     * @test
     * Test that an expense decreases the child's balance
     */
    public function testExpenseDecreasesBalance(): void
    {
        // Arrange
        $childId = $this->testChild->getId();
        $child = $this->childService->getChildById($childId);
        $initialBalance = $child->getBalance();
        $expenseAmount = 25.0;
        $description = 'Cinema ticket';

        // Act
        $this->expenseService->recordExpense($childId, $expenseAmount, $description);

        // Refresh child data
        $updatedChild = $this->childService->getChildById($childId);

        // Assert
        $expectedBalance = $initialBalance - $expenseAmount;
        $this->assertEquals(
            $expectedBalance,
            $updatedChild->getBalance(),
            'Balance should decrease by expense amount'
        );
    }

    /**
     * @test
     * Test that an expense creates a transaction record
     */
    public function testExpenseCreatesTransaction(): void
    {
        // Arrange
        $childId = $this->testChild->getId();
        $expenseAmount = 15.0;
        $description = 'Ice cream';

        // Act
        $transaction = $this->expenseService->recordExpense($childId, $expenseAmount, $description);

        // Assert
        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertEquals($childId, $transaction->getChildId());
        $this->assertEquals($expenseAmount, $transaction->getAmount());
        $this->assertEquals('expense', $transaction->getType());
        $this->assertEquals($description, $transaction->getDescription());
        $this->assertNotNull($transaction->getCreatedAt());
    }

    /**
     * @test
     * Test that recording an expense with zero amount throws an exception
     */
    public function testExpenseWithZeroAmountThrowsException(): void
    {
        // Arrange
        $childId = $this->testChild->getId();
        $zeroAmount = 0.0;
        $description = 'Test expense';

        // Assert
        $this->expectException(InvalidAmountException::class);
        $this->expectExceptionMessage('Amount must be greater than zero');

        // Act
        $this->expenseService->recordExpense($childId, $zeroAmount, $description);
    }

    /**
     * @test
     * Test that recording an expense with negative amount throws an exception
     */
    public function testExpenseWithNegativeAmountThrowsException(): void
    {
        // Arrange
        $childId = $this->testChild->getId();
        $negativeAmount = -10.0;
        $description = 'Test expense';

        // Assert
        $this->expectException(InvalidAmountException::class);
        $this->expectExceptionMessage('Amount cannot be negative');

        // Act
        $this->expenseService->recordExpense($childId, $negativeAmount, $description);
    }

    /**
     * @test
     * Test that recording an expense with insufficient balance throws an exception
     */
    public function testExpenseWithInsufficientBalanceThrowsException(): void
    {
        // Arrange
        $childId = $this->testChild->getId();
        $child = $this->childService->getChildById($childId);
        $currentBalance = $child->getBalance();
        $excessiveAmount = $currentBalance + 50.0;
        $description = 'Expensive item';

        // Assert
        $this->expectException(InsufficientBalanceException::class);
        $this->expectExceptionMessageMatches('/Insufficient balance/');

        // Act
        $this->expenseService->recordExpense($childId, $excessiveAmount, $description);
    }

    /**
     * @test
     * Test that an expense with exact balance works correctly
     */
    public function testExpenseWithExactBalanceWorks(): void
    {
        // Arrange
        $childId = $this->testChild->getId();
        $child = $this->childService->getChildById($childId);
        $exactBalance = $child->getBalance();
        $description = 'Spending all money';

        // Act
        $transaction = $this->expenseService->recordExpense($childId, $exactBalance, $description);

        // Refresh child data
        $updatedChild = $this->childService->getChildById($childId);

        // Assert
        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertEquals(0.0, $updatedChild->getBalance(), 'Balance should be zero after spending all');
    }

    /**
     * @test
     * Test that the expense stores the correct description
     */
    public function testExpenseStoresCorrectDescription(): void
    {
        // Arrange
        $childId = $this->testChild->getId();
        $amount = 20.0;
        $description = 'New video game';

        // Act
        $transaction = $this->expenseService->recordExpense($childId, $amount, $description);

        // Assert
        $this->assertEquals(
            $description,
            $transaction->getDescription(),
            'Transaction should store the correct description'
        );
    }

    /**
     * @test
     * Test that recording an expense on a non-existent child throws an exception
     */
    public function testExpenseOnNonExistentChildThrowsException(): void
    {
        // Arrange
        $nonExistentChildId = 999;
        $amount = 25.0;
        $description = 'Test expense';

        // Assert
        $this->expectException(ChildNotFoundException::class);
        $this->expectExceptionMessage('Child with ID 999 not found');

        // Act
        $this->expenseService->recordExpense($nonExistentChildId, $amount, $description);
    }
}
