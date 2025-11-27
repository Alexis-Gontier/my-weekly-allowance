<?php

declare(strict_types=1);

namespace Tests\Integration;

use Alexi\MyWeeklyAllowance\Entity\Child;
use Alexi\MyWeeklyAllowance\Service\AllowanceService;
use Alexi\MyWeeklyAllowance\Service\ChildService;
use Alexi\MyWeeklyAllowance\Service\DepositService;
use Alexi\MyWeeklyAllowance\Service\ExpenseService;
use Alexi\MyWeeklyAllowance\Service\TransactionService;
use PHPUnit\Framework\TestCase;

/**
 * Integration test suite for complete workflows
 *
 * Tests end-to-end scenarios combining multiple services
 */
class WorkflowTest extends TestCase
{
    private ChildService $childService;
    private DepositService $depositService;
    private ExpenseService $expenseService;
    private AllowanceService $allowanceService;
    private TransactionService $transactionService;

    protected function setUp(): void
    {
        $this->childService = new ChildService();
        $this->depositService = new DepositService($this->childService);
        $this->expenseService = new ExpenseService($this->childService);
        $this->allowanceService = new AllowanceService($this->childService);
        $this->transactionService = new TransactionService();
    }

    /**
     * @test
     * Test complete deposit workflow: create child, deposit, verify balance and transaction
     */
    public function testCompleteDepositWorkflow(): void
    {
        // Step 1: Create a child
        $child = $this->childService->createChild(1, 'Tom');
        $this->assertEquals(0.0, $child->getBalance(), 'Initial balance should be 0');

        // Step 2: Make a deposit
        $depositAmount = 100.0;
        $transaction = $this->depositService->deposit(
            $child->getId(),
            $depositAmount,
            'Birthday money'
        );

        // Step 3: Verify balance was updated
        $updatedChild = $this->childService->getChildById($child->getId());
        $this->assertEquals($depositAmount, $updatedChild->getBalance());

        // Step 4: Verify transaction was recorded
        $this->assertEquals('deposit', $transaction->getType());
        $this->assertEquals($depositAmount, $transaction->getAmount());

        // Step 5: Verify transaction appears in history
        $transactions = $this->transactionService->getTransactionsForChild($child->getId());
        $this->assertCount(1, $transactions);
        $this->assertEquals($transaction->getId(), $transactions[0]->getId());
    }

    /**
     * @test
     * Test complete expense workflow: create child, deposit, expense, verify balance and transactions
     */
    public function testCompleteExpenseWorkflow(): void
    {
        // Step 1: Create a child and give initial balance
        $child = $this->childService->createChild(1, 'Sarah');
        $initialDeposit = 100.0;
        $this->depositService->deposit($child->getId(), $initialDeposit, 'Initial deposit');

        // Step 2: Record an expense
        $expenseAmount = 35.0;
        $expenseTransaction = $this->expenseService->recordExpense(
            $child->getId(),
            $expenseAmount,
            'Cinema ticket'
        );

        // Step 3: Verify balance was decreased
        $updatedChild = $this->childService->getChildById($child->getId());
        $expectedBalance = $initialDeposit - $expenseAmount;
        $this->assertEquals($expectedBalance, $updatedChild->getBalance());

        // Step 4: Verify expense transaction was recorded
        $this->assertEquals('expense', $expenseTransaction->getType());
        $this->assertEquals($expenseAmount, $expenseTransaction->getAmount());

        // Step 5: Verify both transactions appear in history
        $transactions = $this->transactionService->getTransactionsForChild($child->getId());
        $this->assertCount(2, $transactions);

        // Verify transaction types
        $types = array_map(fn($t) => $t->getType(), $transactions);
        $this->assertContains('deposit', $types);
        $this->assertContains('expense', $types);
    }

    /**
     * @test
     * Test complete allowance workflow: create child, set allowance, process, verify balance
     */
    public function testCompleteAllowanceWorkflow(): void
    {
        // Step 1: Create a child
        $child = $this->childService->createChild(1, 'Emma');
        $initialBalance = $child->getBalance();

        // Step 2: Set up weekly allowance
        $allowanceAmount = 20.0;
        $todayDayOfWeek = (int) date('N'); // 1=Monday, 7=Sunday
        $allowance = $this->allowanceService->setAllowance(
            $child->getId(),
            $allowanceAmount,
            $todayDayOfWeek
        );

        $this->assertEquals($allowanceAmount, $allowance->getAmount());
        $this->assertEquals($todayDayOfWeek, $allowance->getDayOfWeek());
        $this->assertTrue($allowance->isActive());

        // Step 3: Process allowances (should process today's allowance)
        $transactions = $this->allowanceService->processAllowances();

        // Step 4: Verify allowance was processed
        $this->assertCount(1, $transactions);
        $this->assertEquals('allowance', $transactions[0]->getType());
        $this->assertEquals($allowanceAmount, $transactions[0]->getAmount());

        // Step 5: Verify balance was increased
        $updatedChild = $this->childService->getChildById($child->getId());
        $expectedBalance = $initialBalance + $allowanceAmount;
        $this->assertEquals($expectedBalance, $updatedChild->getBalance());

        // Step 6: Verify transaction appears in history
        $history = $this->transactionService->getTransactionsForChild($child->getId());
        $this->assertCount(1, $history);
        $this->assertEquals('allowance', $history[0]->getType());
    }

    /**
     * @test
     * Test balance consistency after multiple operations
     */
    public function testBalanceConsistencyAfterMultipleOperations(): void
    {
        // Step 1: Create a child
        $child = $this->childService->createChild(1, 'Lucas');
        $expectedBalance = 0.0;

        // Step 2: Perform multiple deposits
        $this->depositService->deposit($child->getId(), 100.0, 'Deposit 1');
        $expectedBalance += 100.0;

        $this->depositService->deposit($child->getId(), 50.0, 'Deposit 2');
        $expectedBalance += 50.0;

        $this->depositService->deposit($child->getId(), 25.0, 'Deposit 3');
        $expectedBalance += 25.0;

        // Step 3: Perform multiple expenses
        $this->expenseService->recordExpense($child->getId(), 30.0, 'Expense 1');
        $expectedBalance -= 30.0;

        $this->expenseService->recordExpense($child->getId(), 20.0, 'Expense 2');
        $expectedBalance -= 20.0;

        // Step 4: Set and process allowance
        $allowanceAmount = 15.0;
        $todayDayOfWeek = (int) date('N');
        $this->allowanceService->setAllowance($child->getId(), $allowanceAmount, $todayDayOfWeek);
        $this->allowanceService->processAllowances();
        $expectedBalance += $allowanceAmount;

        // Step 5: Verify final balance matches expected
        $finalChild = $this->childService->getChildById($child->getId());
        $this->assertEquals(
            $expectedBalance,
            $finalChild->getBalance(),
            'Final balance should match expected after all operations'
        );

        // Step 6: Verify correct number of transactions
        $transactions = $this->transactionService->getTransactionsForChild($child->getId());
        $this->assertCount(6, $transactions, 'Should have 3 deposits + 2 expenses + 1 allowance');
    }

    /**
     * @test
     * Test that transaction history is accurate and complete
     */
    public function testTransactionHistoryIsAccurate(): void
    {
        // Step 1: Create two children for the same parent
        $child1 = $this->childService->createChild(1, 'Tom');
        $child2 = $this->childService->createChild(1, 'Sarah');

        // Step 2: Create various transactions for child 1
        $this->depositService->deposit($child1->getId(), 100.0, 'Tom deposit 1');
        $this->depositService->deposit($child1->getId(), 50.0, 'Tom deposit 2');
        $this->expenseService->recordExpense($child1->getId(), 25.0, 'Tom expense 1');

        // Step 3: Create various transactions for child 2
        $this->depositService->deposit($child2->getId(), 75.0, 'Sarah deposit 1');
        $this->expenseService->recordExpense($child2->getId(), 15.0, 'Sarah expense 1');

        // Step 4: Verify each child's transaction history is isolated
        $child1Transactions = $this->transactionService->getTransactionsForChild($child1->getId());
        $child2Transactions = $this->transactionService->getTransactionsForChild($child2->getId());

        $this->assertCount(3, $child1Transactions, 'Child 1 should have 3 transactions');
        $this->assertCount(2, $child2Transactions, 'Child 2 should have 2 transactions');

        // Step 5: Verify transaction isolation (no cross-contamination)
        foreach ($child1Transactions as $transaction) {
            $this->assertEquals(
                $child1->getId(),
                $transaction->getChildId(),
                'All child 1 transactions should belong to child 1'
            );
        }

        foreach ($child2Transactions as $transaction) {
            $this->assertEquals(
                $child2->getId(),
                $transaction->getChildId(),
                'All child 2 transactions should belong to child 2'
            );
        }

        // Step 6: Verify balances match transaction totals
        $child1Balance = $this->childService->getChildById($child1->getId())->getBalance();
        $child2Balance = $this->childService->getChildById($child2->getId())->getBalance();

        $this->assertEquals(125.0, $child1Balance, 'Child 1: 100 + 50 - 25 = 125');
        $this->assertEquals(60.0, $child2Balance, 'Child 2: 75 - 15 = 60');
    }
}
