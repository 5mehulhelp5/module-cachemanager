<?php
declare(strict_types=1);

namespace Panth\CacheManager\Test\Unit\Observer;

use Panth\CacheManager\Observer\CacheInvalidate;
use Panth\CacheManager\Helper\Data as ConfigHelper;
use Magento\Framework\Event\Observer;
use Magento\Framework\Event;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\DataObject\IdentityInterface;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class CacheInvalidateTest extends TestCase
{
    private CacheInvalidate $observer;

    private $configHelperMock;

    private $cacheMock;

    private $loggerMock;

    private $observerMock;

    private $eventMock;

    protected function setUp(): void
    {
        $this->configHelperMock = $this->createMock(ConfigHelper::class);
        $this->cacheMock = $this->createMock(CacheInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->observerMock = $this->createMock(Observer::class);
        $this->eventMock = $this->createMock(Event::class);

        $this->observer = new CacheInvalidate(
            $this->configHelperMock,
            $this->cacheMock,
            $this->loggerMock
        );
    }

    public function testExecuteReturnEarlyWhenModuleDisabled(): void
    {
        $this->configHelperMock->expects($this->once())
            ->method('isEnabled')
            ->willReturn(false);

        $this->observerMock->expects($this->never())
            ->method('getEvent');

        $this->cacheMock->expects($this->never())
            ->method('clean');

        $this->observer->execute($this->observerMock);
    }

    public function testExecuteReturnEarlyWhenSmartInvalidationDisabled(): void
    {
        $this->configHelperMock->expects($this->once())
            ->method('isEnabled')
            ->willReturn(true);

        $this->configHelperMock->expects($this->once())
            ->method('isSmartInvalidationEnabled')
            ->willReturn(false);

        $this->observerMock->expects($this->never())
            ->method('getEvent');

        $this->cacheMock->expects($this->never())
            ->method('clean');

        $this->observer->execute($this->observerMock);
    }

    public function testExecuteInvalidatesProductCacheOnProductSave(): void
    {
        $eventName = 'catalog_product_save_after';
        $expectedTags = ['catalog_product'];

        $this->configHelperMock->method('isEnabled')->willReturn(true);
        $this->configHelperMock->method('isSmartInvalidationEnabled')->willReturn(true);
        $this->configHelperMock->expects($this->once())
            ->method('shouldInvalidateOnProductSave')
            ->willReturn(true);

        $this->observerMock->method('getEvent')->willReturn($this->eventMock);
        $this->eventMock->method('getName')->willReturn($eventName);

        $this->cacheMock->expects($this->once())
            ->method('clean')
            ->with($expectedTags);

        $this->loggerMock->expects($this->once())
            ->method('info')
            ->with(
                'CacheManager: Smart invalidation triggered',
                ['event' => $eventName, 'tags' => $expectedTags]
            );

        $this->observer->execute($this->observerMock);
    }

    public function testExecuteSkipsWhenEntityToggleIsOff(): void
    {
        $this->configHelperMock->method('isEnabled')->willReturn(true);
        $this->configHelperMock->method('isSmartInvalidationEnabled')->willReturn(true);
        $this->configHelperMock->expects($this->once())
            ->method('shouldInvalidateOnProductSave')
            ->willReturn(false);

        $this->observerMock->method('getEvent')->willReturn($this->eventMock);
        $this->eventMock->method('getName')->willReturn('catalog_product_save_after');

        $this->cacheMock->expects($this->never())
            ->method('clean');

        $this->observer->execute($this->observerMock);
    }

    public function testExecuteInvalidatesCategoryCacheOnCategorySave(): void
    {
        $this->configHelperMock->method('isEnabled')->willReturn(true);
        $this->configHelperMock->method('isSmartInvalidationEnabled')->willReturn(true);
        $this->configHelperMock->expects($this->once())
            ->method('shouldInvalidateOnCategorySave')
            ->willReturn(true);

        $this->observerMock->method('getEvent')->willReturn($this->eventMock);
        $this->eventMock->method('getName')->willReturn('catalog_category_save_after');

        $this->cacheMock->expects($this->once())
            ->method('clean')
            ->with(['catalog_category']);

        $this->observer->execute($this->observerMock);
    }

    public function testExecuteInvalidatesCmsCacheOnCmsPageSave(): void
    {
        $this->configHelperMock->method('isEnabled')->willReturn(true);
        $this->configHelperMock->method('isSmartInvalidationEnabled')->willReturn(true);
        $this->configHelperMock->expects($this->once())
            ->method('shouldInvalidateOnCmsSave')
            ->willReturn(true);

        $this->observerMock->method('getEvent')->willReturn($this->eventMock);
        $this->eventMock->method('getName')->willReturn('cms_page_save_after');

        $this->cacheMock->expects($this->once())
            ->method('clean')
            ->with(['cms_page']);

        $this->observer->execute($this->observerMock);
    }

    public function testExecuteInvalidatesCmsCacheOnCmsBlockSave(): void
    {
        $this->configHelperMock->method('isEnabled')->willReturn(true);
        $this->configHelperMock->method('isSmartInvalidationEnabled')->willReturn(true);
        $this->configHelperMock->expects($this->once())
            ->method('shouldInvalidateOnCmsSave')
            ->willReturn(true);

        $this->observerMock->method('getEvent')->willReturn($this->eventMock);
        $this->eventMock->method('getName')->willReturn('cms_block_save_after');

        $this->cacheMock->expects($this->once())
            ->method('clean')
            ->with(['cms_block']);

        $this->observer->execute($this->observerMock);
    }

    public function testExecuteDoesNotCleanCacheForUnknownEvents(): void
    {
        $this->configHelperMock->method('isEnabled')->willReturn(true);
        $this->configHelperMock->method('isSmartInvalidationEnabled')->willReturn(true);

        $this->observerMock->method('getEvent')->willReturn($this->eventMock);
        $this->eventMock->method('getName')->willReturn('some_unknown_event');

        $this->cacheMock->expects($this->never())
            ->method('clean');

        $this->observer->execute($this->observerMock);
    }

    public function testExecuteHandlesExceptionGracefully(): void
    {
        $this->configHelperMock->method('isEnabled')->willReturn(true);
        $this->configHelperMock->method('isSmartInvalidationEnabled')->willReturn(true);

        $this->observerMock->method('getEvent')
            ->willThrowException(new \Exception('Event error'));

        $this->cacheMock->expects($this->never())
            ->method('clean');

        $this->loggerMock->expects($this->once())
            ->method('error')
            ->with($this->stringContains('CacheManager Observer Error'));

        $this->observer->execute($this->observerMock);
    }

    public function testExecuteCleansOnlySavedEntityTags(): void
    {
        $this->configHelperMock->method('isEnabled')->willReturn(true);
        $this->configHelperMock->method('isSmartInvalidationEnabled')->willReturn(true);
        $this->configHelperMock->method('shouldInvalidateOnProductSave')->willReturn(true);

        $entity = $this->createMock(IdentityInterface::class);
        $entity->method('getIdentities')->willReturn(['cat_p_42', 'cat_p_42', 'cat_c_p_3']);

        $this->observerMock->method('getEvent')->willReturn($this->eventMock);
        $this->eventMock->method('getName')->willReturn('catalog_product_save_after');
        $this->eventMock->method('getData')->with('data_object')->willReturn($entity);

        $this->cacheMock->expects($this->once())
            ->method('clean')
            ->with(['cat_p_42', 'cat_c_p_3']);

        $this->observer->execute($this->observerMock);
    }
}
