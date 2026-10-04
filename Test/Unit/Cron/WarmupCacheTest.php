<?php
declare(strict_types=1);

namespace Panth\CacheManager\Test\Unit\Cron;

use Panth\CacheManager\Cron\WarmupCache;
use Panth\CacheManager\Helper\Data as ConfigHelper;
use Panth\CacheManager\Model\WarmupLog;
use Panth\CacheManager\Model\WarmupLogFactory;
use Panth\CacheManager\Model\ResourceModel\WarmupLog as WarmupLogResource;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Store\Model\Store;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as CmsPageCollectionFactory;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class WarmupCacheTest extends TestCase
{
    private WarmupCache $cron;

    private $configHelperMock;

    private $storeManagerMock;

    private $categoryCollectionFactoryMock;

    private $productCollectionFactoryMock;

    private $cmsPageCollectionFactoryMock;

    private $loggerMock;

    private $storeMock;

    private $logFactoryMock;

    protected function setUp(): void
    {
        $this->configHelperMock = $this->createMock(ConfigHelper::class);
        $this->storeManagerMock = $this->createMock(StoreManagerInterface::class);
        $this->categoryCollectionFactoryMock = $this->createMock(CategoryCollectionFactory::class);
        $this->productCollectionFactoryMock = $this->createMock(ProductCollectionFactory::class);
        $this->cmsPageCollectionFactoryMock = $this->createMock(CmsPageCollectionFactory::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->storeMock = $this->createMock(Store::class);
        $this->logFactoryMock = $this->createMock(WarmupLogFactory::class);
        $this->logFactoryMock->method('create')->willReturn($this->createMock(WarmupLog::class));

        $this->cron = new WarmupCache(
            $this->configHelperMock,
            $this->storeManagerMock,
            $this->categoryCollectionFactoryMock,
            $this->productCollectionFactoryMock,
            $this->cmsPageCollectionFactoryMock,
            $this->loggerMock,
            $this->logFactoryMock,
            $this->createMock(WarmupLogResource::class)
        );
    }

    public function testExecuteReturnEarlyWhenWarmupDisabled(): void
    {
        $this->configHelperMock->expects($this->once())
            ->method('isWarmupEnabled')
            ->willReturn(false);

        $this->configHelperMock->expects($this->never())
            ->method('getWarmupPages');

        $this->cron->execute();
    }

    public function testExecuteLogsInfoWhenNoUrls(): void
    {
        $this->configHelperMock->method('isWarmupEnabled')->willReturn(true);
        $this->configHelperMock->method('getWarmupPages')->willReturn([]);

        $this->storeManagerMock->method('getStores')->willReturn([$this->storeMock]);
        $this->storeMock->method('getId')->willReturn(1);
        $this->storeMock->method('getIsActive')->willReturn(true);
        $this->storeMock->method('getBaseUrl')->willReturn('https://example.com/');

        $this->loggerMock->expects($this->once())
            ->method('info')
            ->with('CacheManager: No URLs to warm up');

        $this->cron->execute();
    }

    public function testExecuteHandlesGeneralExceptionDuringWarmup(): void
    {
        $this->configHelperMock->method('isWarmupEnabled')->willReturn(true);
        $this->storeMock->method('getIsActive')->willReturn(true);
        $this->storeMock->method('getId')->willReturn(1);
        $this->storeManagerMock->method('getStores')->willReturn([$this->storeMock]);

        $this->configHelperMock->expects($this->once())
            ->method('getWarmupPages')
            ->willThrowException(new \Exception('Config error'));

        $this->loggerMock->expects($this->once())
            ->method('error')
            ->with($this->stringContains('CacheManager Cron Error'));

        $this->cron->execute();
    }

    public function testEveryEnabledStoreIsWarmedWithItsOwnBaseUrl(): void
    {
        $first = $this->createMock(Store::class);
        $first->method('getId')->willReturn(1);
        $first->method('getIsActive')->willReturn(true);
        $first->method('getBaseUrl')->willReturn('http://127.0.0.1:9/');
        $second = $this->createMock(Store::class);
        $second->method('getId')->willReturn(2);
        $second->method('getIsActive')->willReturn(true);
        $second->method('getBaseUrl')->willReturn('http://localhost:9/');
        $disabled = $this->createMock(Store::class);
        $disabled->method('getId')->willReturn(3);
        $disabled->method('getIsActive')->willReturn(true);

        $this->storeManagerMock->method('getStores')->willReturn([$first, $second, $disabled]);
        $this->configHelperMock->method('isWarmupEnabled')
            ->willReturnCallback(static fn ($storeId = null) => $storeId !== 3);
        $this->configHelperMock->method('getWarmupPages')->willReturn(['home']);
        $this->configHelperMock->method('getConcurrentRequests')->willReturn(2);
        $this->configHelperMock->method('getRedirectStatus')->willReturn('success');

        $results = $this->cron->runWarmup();

        $this->assertSame(
            ['http://127.0.0.1:9/', 'http://localhost:9/'],
            array_column($results, 'url')
        );
        $this->assertSame([1, 2], array_column($results, 'store_id'));
    }

    public function testFailedTransferIsRecordedAsFailed(): void
    {
        $this->storeMock->method('getId')->willReturn(1);
        $this->storeMock->method('getIsActive')->willReturn(true);
        $this->storeMock->method('getBaseUrl')->willReturn('http://127.0.0.1:9/');
        $this->storeManagerMock->method('getStores')->willReturn([$this->storeMock]);
        $this->configHelperMock->method('isWarmupEnabled')->willReturn(true);
        $this->configHelperMock->method('getWarmupPages')->willReturn(['home']);
        $this->configHelperMock->method('getConcurrentRequests')->willReturn(1);
        $this->configHelperMock->method('getRedirectStatus')->willReturn('success');

        $results = $this->cron->runWarmup();

        $this->assertCount(1, $results);
        $this->assertSame('failed', $results[0]['status']);
        $this->assertNotSame('', $results[0]['error']);
    }

    public static function statuses(): array
    {
        return [
            'ok' => [200, '', 'skipped', 'success'],
            'redirect as success' => [301, '', 'success', 'success'],
            'redirect as skipped' => [302, '', 'skipped', 'skipped'],
            'redirect as failed' => [308, '', 'failed', 'failed'],
            'not found' => [404, '', 'success', 'failed'],
            'server error' => [503, '', 'success', 'failed'],
            'no response' => [0, '', 'success', 'failed'],
            'transfer error' => [200, 'Operation timed out', 'success', 'failed'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('statuses')]
    public function testResolveStatus(int $httpCode, string $error, string $redirectStatus, string $expected): void
    {
        $method = new \ReflectionMethod(WarmupCache::class, 'resolveStatus');
        $this->assertSame($expected, $method->invoke($this->cron, $httpCode, $error, $redirectStatus));
    }
}
