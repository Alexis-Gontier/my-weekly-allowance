<?php

declare(strict_types=1);

namespace Tests\Unit;

use Alexi\MyWeeklyAllowance\Entity\Child;
use Alexi\MyWeeklyAllowance\Exception\EmptyNameException;
use Alexi\MyWeeklyAllowance\Service\ChildService;
use PHPUnit\Framework\TestCase;

/**
 * Test suite for ChildService
 *
 * Tests the creation and management of child accounts (virtual wallets)
 */
class ChildServiceTest extends TestCase
{
    private ChildService $childService;

    protected function setUp(): void
    {
        $this->childService = new ChildService();
    }

    /**
     * @test
     * Test that a child can be created with valid data
     */
    public function testCreateChildWithValidData(): void
    {
        // Arrange
        $userId = 1;
        $name = 'Tom';

        // Act
        $child = $this->childService->createChild($userId, $name);

        // Assert
        $this->assertInstanceOf(Child::class, $child);
        $this->assertEquals($userId, $child->getUserId());
        $this->assertEquals($name, $child->getName());
        $this->assertNotNull($child->getId(), 'Child should have an ID after creation');
    }

    /**
     * @test
     * Test that creating a child with empty name throws exception
     */
    public function testCreateChildWithEmptyName(): void
    {
        // Arrange
        $userId = 1;
        $emptyName = '';

        // Assert
        $this->expectException(EmptyNameException::class);
        $this->expectExceptionMessage('Child name cannot be empty');

        // Act
        $this->childService->createChild($userId, $emptyName);
    }

    /**
     * @test
     * Test that a child's balance defaults to zero
     */
    public function testChildBalanceDefaultsToZero(): void
    {
        // Arrange
        $userId = 1;
        $name = 'Sarah';

        // Act
        $child = $this->childService->createChild($userId, $name);

        // Assert
        $this->assertEquals(0.0, $child->getBalance(), 'Initial balance should be 0.0');
    }

    /**
     * @test
     * Test that a child can be retrieved by ID
     */
    public function testGetChildById(): void
    {
        // Arrange
        $userId = 1;
        $name = 'Tom';
        $createdChild = $this->childService->createChild($userId, $name);
        $childId = $createdChild->getId();

        // Act
        $retrievedChild = $this->childService->getChildById($childId);

        // Assert
        $this->assertNotNull($retrievedChild);
        $this->assertEquals($childId, $retrievedChild->getId());
        $this->assertEquals($name, $retrievedChild->getName());
        $this->assertEquals($userId, $retrievedChild->getUserId());
    }

    /**
     * @test
     * Test that getting a non-existent child returns null
     */
    public function testGetNonExistentChildReturnsNull(): void
    {
        // Arrange
        $nonExistentChildId = 999;

        // Act
        $child = $this->childService->getChildById($nonExistentChildId);

        // Assert
        $this->assertNull($child, 'Should return null for non-existent child');
    }

    /**
     * @test
     * Test that we can get a list of children for a specific user
     */
    public function testGetChildrenForUser(): void
    {
        // Arrange
        $userId = 1;
        $this->childService->createChild($userId, 'Tom');
        $this->childService->createChild($userId, 'Sarah');

        // Act
        $children = $this->childService->getChildrenForUser($userId);

        // Assert
        $this->assertIsArray($children);
        $this->assertCount(2, $children);
        $this->assertContainsOnlyInstancesOf(Child::class, $children);
    }

    /**
     * @test
     * Test that a parent can have multiple children
     */
    public function testParentCanHaveMultipleChildren(): void
    {
        // Arrange
        $userId = 1;
        $child1Name = 'Tom';
        $child2Name = 'Sarah';
        $child3Name = 'Emma';

        // Act
        $child1 = $this->childService->createChild($userId, $child1Name);
        $child2 = $this->childService->createChild($userId, $child2Name);
        $child3 = $this->childService->createChild($userId, $child3Name);

        $children = $this->childService->getChildrenForUser($userId);

        // Assert
        $this->assertCount(3, $children);
        $this->assertEquals($child1Name, $child1->getName());
        $this->assertEquals($child2Name, $child2->getName());
        $this->assertEquals($child3Name, $child3->getName());

        // All children should belong to the same parent
        foreach ($children as $child) {
            $this->assertEquals($userId, $child->getUserId());
        }
    }
}
