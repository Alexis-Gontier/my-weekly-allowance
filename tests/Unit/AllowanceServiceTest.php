<?php

declare(strict_types=1);

namespace Tests\Unit;

use Alexi\MyWeeklyAllowance\Entity\Child;
use Alexi\MyWeeklyAllowance\Entity\WeeklyAllowance;
use Alexi\MyWeeklyAllowance\Exception\InvalidAmountException;
use Alexi\MyWeeklyAllowance\Exception\InvalidDayOfWeekException;
use Alexi\MyWeeklyAllowance\Service\AllowanceService;
use Alexi\MyWeeklyAllowance\Service\ChildService;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for AllowanceService
 *
 * Tests the automatic weekly allowance system
 */
class AllowanceServiceTest extends TestCase
{
    private AllowanceService $allowanceService;
    private ChildService $childService;
    private Child $testChild;

    protected function setUp(): void
    {
        $this->childService = new ChildService();
        $this->allowanceService = new AllowanceService($this->childService);

        // Create a test child
        $this->testChild = $this->childService->createChild(1, 'Tom');
    }

    /**
     * @test
     * Test that an allowance can be set for a child
     */
    public function testSetAllowanceForChild(): void
    {
        // Arrange
        $childId = $this->testChild->getId();
        $amount = 20.0;
        $dayOfWeek = 1; // Monday

        // Act
        $allowance = $this->allowanceService->setAllowance($childId, $amount, $dayOfWeek);

        // Assert
        $this->assertInstanceOf(WeeklyAllowance::class, $allowance);
        $this->assertEquals($childId, $allowance->getChildId());
        $this->assertEquals($amount, $allowance->getAmount());
        $this->assertEquals($dayOfWeek, $allowance->getDayOfWeek());
        $this->assertTrue($allowance->isActive(), 'Allowance should be active by default');
        $this->assertNull($allowance->getLastPaymentDate(), 'Last payment date should be null initially');
    }

    /**
     * @test
     * Test that setting an allowance with zero amount throws an exception
     */
    public function testSetAllowanceWithZeroAmountThrowsException(): void
    {
        // Arrange
        $childId = $this->testChild->getId();
        $zeroAmount = 0.0;
        $dayOfWeek = 1;

        // Assert
        $this->expectException(InvalidAmountException::class);
        $this->expectExceptionMessage('Amount must be greater than zero');

        // Act
        $this->allowanceService->setAllowance($childId, $zeroAmount, $dayOfWeek);
    }

    /**
     * @test
     * Test that setting an allowance with negative amount throws an exception
     */
    public function testSetAllowanceWithNegativeAmountThrowsException(): void
    {
        // Arrange
        $childId = $this->testChild->getId();
        $negativeAmount = -10.0;
        $dayOfWeek = 1;

        // Assert
        $this->expectException(InvalidAmountException::class);
        $this->expectExceptionMessage('Amount cannot be negative');

        // Act
        $this->allowanceService->setAllowance($childId, $negativeAmount, $dayOfWeek);
    }

    /**
     * @test
     * Test that setting an allowance with invalid day throws an exception
     */
    public function testSetAllowanceWithInvalidDayThrowsException(): void
    {
        // Arrange
        $childId = $this->testChild->getId();
        $amount = 20.0;
        $invalidDays = [0, 8, -1, 10];

        foreach ($invalidDays as $invalidDay) {
            try {
                // Act
                $this->allowanceService->setAllowance($childId, $amount, $invalidDay);

                // If we reach here, the test should fail
                $this->fail("Expected InvalidDayOfWeekException for day $invalidDay");
            } catch (InvalidDayOfWeekException $e) {
                // Assert
                $this->assertStringContainsString(
                    'Invalid day of week',
                    $e->getMessage(),
                    "Exception message should mention invalid day for day $invalidDay"
                );
            }
        }
    }

    /**
     * @test
     * Test that we can retrieve an allowance for a child
     */
    public function testGetAllowanceForChild(): void
    {
        // Arrange
        $childId = $this->testChild->getId();
        $amount = 25.0;
        $dayOfWeek = 3; // Wednesday
        $this->allowanceService->setAllowance($childId, $amount, $dayOfWeek);

        // Act
        $allowance = $this->allowanceService->getAllowance($childId);

        // Assert
        $this->assertInstanceOf(WeeklyAllowance::class, $allowance);
        $this->assertEquals($childId, $allowance->getChildId());
        $this->assertEquals($amount, $allowance->getAmount());
        $this->assertEquals($dayOfWeek, $allowance->getDayOfWeek());
    }

    /**
     * @test
     * Test that we can update an existing allowance
     */
    public function testUpdateExistingAllowance(): void
    {
        // Arrange
        $childId = $this->testChild->getId();
        $initialAmount = 20.0;
        $updatedAmount = 30.0;
        $initialDay = 1;
        $updatedDay = 5;

        // Act - Create initial allowance
        $this->allowanceService->setAllowance($childId, $initialAmount, $initialDay);

        // Act - Update allowance
        $updatedAllowance = $this->allowanceService->setAllowance($childId, $updatedAmount, $updatedDay);

        // Assert
        $this->assertEquals($updatedAmount, $updatedAllowance->getAmount());
        $this->assertEquals($updatedDay, $updatedAllowance->getDayOfWeek());

        // Verify only one allowance exists for this child
        $retrievedAllowance = $this->allowanceService->getAllowance($childId);
        $this->assertEquals($updatedAmount, $retrievedAllowance->getAmount());
    }

    /**
     * @test
     * Test that processing allowances creates transactions
     */
    public function testProcessAllowancesCreatesTransaction(): void
    {
        // Arrange
        $childId = $this->testChild->getId();
        $amount = 20.0;
        $dayOfWeek = (int) date('N'); // Today's day of week (1=Monday, 7=Sunday)

        $this->allowanceService->setAllowance($childId, $amount, $dayOfWeek);

        // Act
        $transactions = $this->allowanceService->processAllowances();

        // Assert
        $this->assertIsArray($transactions);
        $this->assertCount(1, $transactions, 'Should create one transaction for the allowance');
        $this->assertEquals('allowance', $transactions[0]->getType());
        $this->assertEquals($amount, $transactions[0]->getAmount());
    }

    /**
     * @test
     * Test that processing allowances increases child balance
     */
    public function testProcessAllowancesIncreasesBalance(): void
    {
        // Arrange
        $childId = $this->testChild->getId();
        $initialBalance = $this->testChild->getBalance();
        $allowanceAmount = 20.0;
        $dayOfWeek = (int) date('N'); // Today's day of week

        $this->allowanceService->setAllowance($childId, $allowanceAmount, $dayOfWeek);

        // Act
        $this->allowanceService->processAllowances();

        // Refresh child data
        $updatedChild = $this->childService->getChildById($childId);

        // Assert
        $expectedBalance = $initialBalance + $allowanceAmount;
        $this->assertEquals(
            $expectedBalance,
            $updatedChild->getBalance(),
            'Balance should increase by allowance amount'
        );
    }

    /**
     * @test
     * Test that processing allowances with no active allowances returns empty array
     */
    public function testProcessAllowancesWithNoActiveAllowances(): void
    {
        // Arrange
        $childId = $this->testChild->getId();
        $amount = 20.0;
        $dayOfWeek = ((int) date('N') % 7) + 1; // Different day than today

        $this->allowanceService->setAllowance($childId, $amount, $dayOfWeek);

        // Act
        $transactions = $this->allowanceService->processAllowances();

        // Assert
        $this->assertIsArray($transactions);
        $this->assertEmpty($transactions, 'Should not process allowances for different days');
    }
}
