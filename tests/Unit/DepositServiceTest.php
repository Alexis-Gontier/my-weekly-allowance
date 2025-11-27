<?php

declare(strict_types=1);

namespace Tests\Unit;

use Alexi\MyWeeklyAllowance\Entity\Child;
use Alexi\MyWeeklyAllowance\Entity\Transaction;
use Alexi\MyWeeklyAllowance\Exception\ChildNotFoundException;
use Alexi\MyWeeklyAllowance\Exception\InvalidAmountException;
use Alexi\MyWeeklyAllowance\Service\ChildService;
use Alexi\MyWeeklyAllowance\Service\DepositService;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for DepositService
 *
 * Tests depositing money into a child's virtual wallet
 */
class DepositServiceTest extends TestCase
{
    private DepositService $depositService;
    private ChildService $childService;
    private Child $testChild;

    protected function setUp(): void
    {
        $this->childService = new ChildService();
        $this->depositService = new DepositService($this->childService);

        // Create a test child for deposit operations
        $this->testChild = $this->childService->createChild(1, 'Tom');
    }

    /**
     * @test
     * Test that a deposit increases the child's balance
     */
    public function testDepositIncreasesBalance(): void
    {
        // Arrange
        $childId = $this->testChild->getId();
        $initialBalance = $this->testChild->getBalance();
        $depositAmount = 50.0;
        $description = 'Weekly allowance';

        // Act
        $this->depositService->deposit($childId, $depositAmount, $description);

        // Refresh child data
        $updatedChild = $this->childService->getChildById($childId);

        // Assert
        $expectedBalance = $initialBalance + $depositAmount;
        $this->assertEquals(
            $expectedBalance,
            $updatedChild->getBalance(),
            'Balance should increase by deposit amount'
        );
    }

    /**
     * @test
     * Test that a deposit creates a transaction record
     */
    public function testDepositCreatesTransaction(): void
    {
        // Arrange
        $childId = $this->testChild->getId();
        $depositAmount = 50.0;
        $description = 'Birthday money';

        // Act
        $transaction = $this->depositService->deposit($childId, $depositAmount, $description);

        // Assert
        $this->assertInstanceOf(Transaction::class, $transaction);
        $this->assertEquals($childId, $transaction->getChildId());
        $this->assertEquals($depositAmount, $transaction->getAmount());
        $this->assertEquals('deposit', $transaction->getType());
        $this->assertEquals($description, $transaction->getDescription());
        $this->assertNotNull($transaction->getCreatedAt());
    }

    /**
     * @test
     * Test that depositing zero amount throws an exception
     */
    public function testDepositWithZeroAmountThrowsException(): void
    {
        // Arrange
        $childId = $this->testChild->getId();
        $zeroAmount = 0.0;
        $description = 'Test deposit';

        // Assert
        $this->expectException(InvalidAmountException::class);
        $this->expectExceptionMessage('Amount must be greater than zero');

        // Act
        $this->depositService->deposit($childId, $zeroAmount, $description);
    }

    /**
     * @test
     * Test that depositing a negative amount throws an exception
     */
    public function testDepositWithNegativeAmountThrowsException(): void
    {
        // Arrange
        $childId = $this->testChild->getId();
        $negativeAmount = -10.0;
        $description = 'Test deposit';

        // Assert
        $this->expectException(InvalidAmountException::class);
        $this->expectExceptionMessage('Amount cannot be negative');

        // Act
        $this->depositService->deposit($childId, $negativeAmount, $description);
    }

    /**
     * @test
     * Test that the deposit stores the correct description
     */
    public function testDepositStoresCorrectDescription(): void
    {
        // Arrange
        $childId = $this->testChild->getId();
        $amount = 25.0;
        $description = 'Christmas gift from grandma';

        // Act
        $transaction = $this->depositService->deposit($childId, $amount, $description);

        // Assert
        $this->assertEquals(
            $description,
            $transaction->getDescription(),
            'Transaction should store the correct description'
        );
    }

    /**
     * @test
     * Test that multiple deposits accumulate correctly
     */
    public function testMultipleDepositsAccumulate(): void
    {
        // Arrange
        $childId = $this->testChild->getId();
        $deposit1 = 50.0;
        $deposit2 = 30.0;
        $deposit3 = 20.0;

        // Act
        $this->depositService->deposit($childId, $deposit1, 'First deposit');
        $this->depositService->deposit($childId, $deposit2, 'Second deposit');
        $this->depositService->deposit($childId, $deposit3, 'Third deposit');

        // Refresh child data
        $updatedChild = $this->childService->getChildById($childId);

        // Assert
        $expectedBalance = $deposit1 + $deposit2 + $deposit3;
        $this->assertEquals(
            $expectedBalance,
            $updatedChild->getBalance(),
            'Multiple deposits should accumulate correctly'
        );
    }

    /**
     * @test
     * Test that depositing to a non-existent child throws an exception
     */
    public function testDepositOnNonExistentChildThrowsException(): void
    {
        // Arrange
        $nonExistentChildId = 999;
        $amount = 50.0;
        $description = 'Test deposit';

        // Assert
        $this->expectException(ChildNotFoundException::class);
        $this->expectExceptionMessage('Child with ID 999 not found');

        // Act
        $this->depositService->deposit($nonExistentChildId, $amount, $description);
    }
}
