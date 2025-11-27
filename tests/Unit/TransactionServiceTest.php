<?php

declare(strict_types=1);

namespace Tests\Unit;

use Alexi\MyWeeklyAllowance\Entity\Child;
use Alexi\MyWeeklyAllowance\Entity\Transaction;
use Alexi\MyWeeklyAllowance\Service\ChildService;
use Alexi\MyWeeklyAllowance\Service\DepositService;
use Alexi\MyWeeklyAllowance\Service\ExpenseService;
use Alexi\MyWeeklyAllowance\Service\TransactionService;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for TransactionService
 *
 * Tests retrieving and managing transaction history
 */
class TransactionServiceTest extends TestCase
{
    private TransactionService $transactionService;
    private ChildService $childService;
    private DepositService $depositService;
    private ExpenseService $expenseService;
    private Child $testChild1;
    private Child $testChild2;

    protected function setUp(): void
    {
        $this->childService = new ChildService();
        $this->transactionService = new TransactionService();
        $this->depositService = new DepositService($this->childService);
        $this->expenseService = new ExpenseService($this->childService);

        // Create test children
        $this->testChild1 = $this->childService->createChild(1, 'Tom');
        $this->testChild2 = $this->childService->createChild(1, 'Sarah');
    }

    /**
     * @test
     * Test getting transactions for a child with no transactions
     */
    public function testGetTransactionsForChildWithNoTransactions(): void
    {
        // Arrange
        $childId = $this->testChild1->getId();

        // Act
        $transactions = $this->transactionService->getTransactionsForChild($childId);

        // Assert
        $this->assertIsArray($transactions);
        $this->assertEmpty($transactions, 'Should return empty array for child with no transactions');
    }

    /**
     * @test
     * Test getting transactions for a child with one transaction
     */
    public function testGetTransactionsForChildWithOneTransaction(): void
    {
        // Arrange
        $childId = $this->testChild1->getId();
        $depositTransaction = $this->depositService->deposit($childId, 50.0, 'First deposit');

        // Act
        $transactions = $this->transactionService->getTransactionsForChild($childId);

        // Assert
        $this->assertIsArray($transactions);
        $this->assertCount(1, $transactions);
        $this->assertInstanceOf(Transaction::class, $transactions[0]);
        $this->assertEquals($depositTransaction->getId(), $transactions[0]->getId());
    }

    /**
     * @test
     * Test getting transactions for a child with multiple transactions
     */
    public function testGetTransactionsForChildWithMultipleTransactions(): void
    {
        // Arrange
        $childId = $this->testChild1->getId();

        // Create multiple transactions
        $this->depositService->deposit($childId, 100.0, 'Initial deposit');
        $this->depositService->deposit($childId, 50.0, 'Second deposit');
        $this->expenseService->recordExpense($childId, 25.0, 'Cinema ticket');

        // Act
        $transactions = $this->transactionService->getTransactionsForChild($childId);

        // Assert
        $this->assertIsArray($transactions);
        $this->assertCount(3, $transactions);
        $this->assertContainsOnlyInstancesOf(Transaction::class, $transactions);
    }

    /**
     * @test
     * Test that transactions are ordered by date (most recent first)
     */
    public function testTransactionsAreOrderedByDate(): void
    {
        // Arrange
        $childId = $this->testChild1->getId();

        // Create transactions with slight delays to ensure different timestamps
        $transaction1 = $this->depositService->deposit($childId, 100.0, 'First');
        usleep(10000); // 10ms delay
        $transaction2 = $this->depositService->deposit($childId, 50.0, 'Second');
        usleep(10000);
        $transaction3 = $this->expenseService->recordExpense($childId, 25.0, 'Third');

        // Act
        $transactions = $this->transactionService->getTransactionsForChild($childId);

        // Assert
        $this->assertCount(3, $transactions);

        // Most recent should be first (transaction3, transaction2, transaction1)
        $this->assertEquals($transaction3->getId(), $transactions[0]->getId(), 'Most recent should be first');
        $this->assertEquals($transaction2->getId(), $transactions[1]->getId());
        $this->assertEquals($transaction1->getId(), $transactions[2]->getId(), 'Oldest should be last');
    }

    /**
     * @test
     * Test getting transactions for different children returns correct data
     */
    public function testGetTransactionsForDifferentChildren(): void
    {
        // Arrange
        $child1Id = $this->testChild1->getId();
        $child2Id = $this->testChild2->getId();

        // Create transactions for child 1
        $this->depositService->deposit($child1Id, 100.0, 'Tom deposit');
        $this->depositService->deposit($child1Id, 50.0, 'Tom deposit 2');

        // Create transactions for child 2
        $this->depositService->deposit($child2Id, 75.0, 'Sarah deposit');

        // Act
        $child1Transactions = $this->transactionService->getTransactionsForChild($child1Id);
        $child2Transactions = $this->transactionService->getTransactionsForChild($child2Id);

        // Assert
        $this->assertCount(2, $child1Transactions, 'Child 1 should have 2 transactions');
        $this->assertCount(1, $child2Transactions, 'Child 2 should have 1 transaction');

        // Verify each child's transactions belong to them
        foreach ($child1Transactions as $transaction) {
            $this->assertEquals($child1Id, $transaction->getChildId());
        }

        foreach ($child2Transactions as $transaction) {
            $this->assertEquals($child2Id, $transaction->getChildId());
        }
    }

    /**
     * @test
     * Test that transaction types are correctly stored
     */
    public function testTransactionTypesAreCorrect(): void
    {
        // Arrange
        $childId = $this->testChild1->getId();

        $this->depositService->deposit($childId, 100.0, 'Deposit transaction');
        $this->expenseService->recordExpense($childId, 25.0, 'Expense transaction');

        // Act
        $transactions = $this->transactionService->getTransactionsForChild($childId);

        // Assert
        $this->assertCount(2, $transactions);

        // Find deposit and expense transactions (order might vary)
        $types = array_map(fn($t) => $t->getType(), $transactions);
        $this->assertContains('deposit', $types);
        $this->assertContains('expense', $types);
    }

    /**
     * @test
     * Test that transaction amounts are correctly stored
     */
    public function testTransactionAmountsAreCorrect(): void
    {
        // Arrange
        $childId = $this->testChild1->getId();
        $depositAmount = 123.45;
        $expenseAmount = 67.89;

        $this->depositService->deposit($childId, $depositAmount, 'Test deposit');
        $this->expenseService->recordExpense($childId, $expenseAmount, 'Test expense');

        // Act
        $transactions = $this->transactionService->getTransactionsForChild($childId);

        // Assert
        $this->assertCount(2, $transactions);

        $amounts = array_map(fn($t) => $t->getAmount(), $transactions);
        $this->assertContains($depositAmount, $amounts);
        $this->assertContains($expenseAmount, $amounts);
    }

    /**
     * @test
     * Test that transaction descriptions are correctly stored
     */
    public function testTransactionDescriptionsAreCorrect(): void
    {
        // Arrange
        $childId = $this->testChild1->getId();
        $depositDescription = 'Weekly allowance from parents';
        $expenseDescription = 'Movie ticket and popcorn';

        $this->depositService->deposit($childId, 50.0, $depositDescription);
        $this->expenseService->recordExpense($childId, 25.0, $expenseDescription);

        // Act
        $transactions = $this->transactionService->getTransactionsForChild($childId);

        // Assert
        $this->assertCount(2, $transactions);

        $descriptions = array_map(fn($t) => $t->getDescription(), $transactions);
        $this->assertContains($depositDescription, $descriptions);
        $this->assertContains($expenseDescription, $descriptions);
    }
}
