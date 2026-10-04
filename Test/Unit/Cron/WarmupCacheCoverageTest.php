<?php
declare(strict_types=1);

namespace Panth\CacheManager\Test\Unit\Cron;

use Magento\Catalog\Model\ResourceModel\Category\Collection as CategoryCollection;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\Collection as ProductCollection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Cms\Model\ResourceModel\Page\Collection as CmsPageCollection;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as CmsPageCollectionFactory;
use Magento\Framework\DataObject;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Panth\CacheManager\Cron\WarmupCache;
use Panth\CacheManager\Helper\Data as ConfigHelper;
use Panth\CacheManager\Model\ResourceModel\WarmupLog as WarmupLogResource;
use Panth\CacheManager\Model\WarmupLog;
use Panth\CacheManager\Model\WarmupLogFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class WarmupCacheCoverageTest extends TestCase
{
    private $configHelper;
    private $storeManager;
    private $categoryFactory;
    private $productFactory;
    private $cmsFactory;
    private $logger;
    private $logFactory;
    private $logResource;

    protected function setUp(): void
    {
        $this->configHelper = $this->createStub(ConfigHelper::class);
        $this->storeManager = $this->createStub(StoreManagerInterface::class);
        $this->categoryFactory = $this->createStub(CategoryCollectionFactory::class);
        $this->productFactory = $this->createStub(ProductCollectionFactory::class);
        $this->cmsFactory = $this->createStub(CmsPageCollectionFactory::class);
        $this->logger = $this->createStub(LoggerInterface::class);
        $this->logFactory = $this->createStub(WarmupLogFactory::class);
        $this->logResource = $this->createStub(WarmupLogResource::class);
    }

    private function cron(): WarmupCache
    {
        return new WarmupCache(
            $this->configHelper,
            $this->storeManager,
            $this->categoryFactory,
            $this->productFactory,
            $this->cmsFactory,
            $this->logger,
            $this->logFactory,
            $this->logResource
        );
    }

    private function store(int $id, string $baseUrl, bool $active = true): Store
    {
        $store = $this->createStub(Store::class);
        $store->method('getId')->willReturn($id);
        $store->method('getIsActive')->willReturn($active);
        $store->method('getBaseUrl')->willReturn($baseUrl);
        $store->method('getCode')->willReturn('store' . $id);
        return $store;
    }

    private function invokePrivate(string $name, ...$args)
    {
        $method = new \ReflectionMethod(WarmupCache::class, $name);
        return $method->invoke($this->cron(), ...$args);
    }

    public function testPruneSkippedWhenRetentionDisabled(): void
    {
        $this->configHelper->method('getLogRetentionDays')->willReturn(0);
        $this->logResource = $this->createMock(WarmupLogResource::class);
        $this->logResource->expects($this->never())->method('getConnection');
        $this->storeManager->method('getStores')->willReturn([]);

        $this->assertSame([], $this->cron()->runWarmup());
    }

    public function testPruneDeletesOldRowsAndLogs(): void
    {
        $this->configHelper->method('getLogRetentionDays')->willReturn(7);
        $this->storeManager->method('getStores')->willReturn([]);

        $captured = [];
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('delete')->willReturnCallback(
            static function ($table, $where) use (&$captured): int {
                $captured = [$table, $where];
                return 3;
            }
        );
        $this->logResource->method('getConnection')->willReturn($connection);
        $this->logResource->method('getMainTable')->willReturn('panth_cache_warmup_log');

        $infos = [];
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->logger->expects($this->exactly(2))->method('info')->willReturnCallback(
            static function ($message, array $context = []) use (&$infos): void {
                $infos[] = [$message, $context];
            }
        );

        $before = time() - 7 * 86400;
        $this->cron()->runWarmup();
        $after = time() - 7 * 86400;

        $this->assertSame('panth_cache_warmup_log', $captured[0]);
        $this->assertSame(['warmed_at < ?'], array_keys($captured[1]));
        $cutoff = strtotime($captured[1]['warmed_at < ?'] . ' UTC');
        $this->assertGreaterThanOrEqual($before, $cutoff);
        $this->assertLessThanOrEqual($after, $cutoff);
        $this->assertSame(['CacheManager: Pruned warmup log', ['rows' => 3, 'days' => 7]], $infos[0]);
        $this->assertSame('CacheManager: No URLs to warm up', $infos[1][0]);
    }

    public function testPruneWithNothingDeletedDoesNotLogPrune(): void
    {
        $this->configHelper->method('getLogRetentionDays')->willReturn(1);
        $this->storeManager->method('getStores')->willReturn([]);
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('delete')->willReturn(0);
        $this->logResource->method('getConnection')->willReturn($connection);

        $this->logger = $this->createMock(LoggerInterface::class);
        $this->logger->expects($this->once())->method('info')->with('CacheManager: No URLs to warm up');

        $this->cron()->runWarmup();
    }

    public function testPruneFailureIsLoggedAndWarmupContinues(): void
    {
        $this->configHelper->method('getLogRetentionDays')->willReturn(30);
        $this->storeManager->method('getStores')->willReturn([]);
        $this->logResource->method('getConnection')->willThrowException(new \RuntimeException('db gone'));

        $this->logger = $this->createMock(LoggerInterface::class);
        $this->logger->expects($this->once())
            ->method('error')
            ->with('CacheManager: Failed to prune warmup log', ['error' => 'db gone']);
        $this->logger->expects($this->once())->method('info')->with('CacheManager: No URLs to warm up');

        $this->assertSame([], $this->cron()->runWarmup());
    }

    public function testInactiveStoreIsNeverCrawled(): void
    {
        $this->storeManager->method('getStores')->willReturn([$this->store(1, 'https://shop.test/', false)]);
        $this->configHelper->method('isWarmupEnabled')->willReturn(true);
        $this->configHelper->method('getWarmupPages')->willReturn(['catalog_category']);
        $this->categoryFactory = $this->createMock(CategoryCollectionFactory::class);
        $this->categoryFactory->expects($this->never())->method('create');

        $this->assertSame([], $this->cron()->runWarmup());
    }

    public function testCollectUrlsBuildsAllPageTypes(): void
    {
        $this->configHelper->method('getWarmupPages')
            ->willReturn(['home', ' catalog_category ', 'catalog_product', 'cms', 'unknown']);
        $this->configHelper->method('getCategoryUrlSuffix')->willReturn('.html');
        $this->configHelper->method('getProductUrlSuffix')->willReturn('.htm');

        $categoryFilters = [];
        $categories = $this->createStub(CategoryCollection::class);
        $categories->method('setStoreId')->willReturnSelf();
        $categories->method('addAttributeToSelect')->willReturnSelf();
        $categories->method('addAttributeToFilter')->willReturnCallback(
            function ($attribute, $condition) use (&$categoryFilters, $categories) {
                $categoryFilters[$attribute] = $condition;
                return $categories;
            }
        );
        $categories->method('getIterator')->willReturn(new \ArrayIterator([
            new DataObject(['url_path' => 'men/shirts']),
            new DataObject(['url_path' => '']),
            new DataObject(['url_path' => '/women']),
        ]));
        $this->categoryFactory->method('create')->willReturn($categories);

        $products = $this->createStub(ProductCollection::class);
        $products->method('setStoreId')->willReturnSelf();
        $products->method('addStoreFilter')->willReturnSelf();
        $products->method('addAttributeToSelect')->willReturnSelf();
        $products->method('addAttributeToFilter')->willReturnSelf();
        $products->method('getIterator')->willReturn(new \ArrayIterator([
            new DataObject(['url_key' => 'tee']),
            new DataObject(['url_key' => null]),
        ]));
        $this->productFactory->method('create')->willReturn($products);

        $pages = $this->createStub(CmsPageCollection::class);
        $pages->method('addFieldToFilter')->willReturnSelf();
        $pages->method('addStoreFilter')->willReturnSelf();
        $pages->method('getIterator')->willReturn(new \ArrayIterator([
            new DataObject(['identifier' => 'about-us']),
            new DataObject(['identifier' => 'no-route']),
            new DataObject(['identifier' => '']),
        ]));
        $this->cmsFactory->method('create')->willReturn($pages);

        $urls = $this->invokePrivate('collectUrls', $this->store(2, 'https://shop.test/'));

        $this->assertSame([
            'https://shop.test/' => 'home',
            'https://shop.test/men/shirts.html' => 'category',
            'https://shop.test/women.html' => 'category',
            'https://shop.test/tee.htm' => 'product',
            'https://shop.test/about-us' => 'cms',
        ], $urls);
        $this->assertSame(['is_active' => 1, 'level' => ['gt' => 1]], $categoryFilters);
    }

    public function testCollectorFailuresAreLoggedIndividually(): void
    {
        $this->configHelper->method('getWarmupPages')
            ->willReturn(['catalog_category', 'catalog_product', 'cms', 'home']);
        $this->categoryFactory->method('create')->willThrowException(new \RuntimeException('cat'));
        $this->productFactory->method('create')->willThrowException(new \RuntimeException('prod'));
        $this->cmsFactory->method('create')->willThrowException(new \RuntimeException('cms'));

        $errors = [];
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->logger->expects($this->exactly(3))->method('error')->willReturnCallback(
            static function ($message, array $context = []) use (&$errors): void {
                $errors[] = [$message, $context];
            }
        );

        $urls = $this->invokePrivate('collectUrls', $this->store(1, 'https://shop.test'));

        $this->assertSame(['https://shop.test/' => 'home'], $urls);
        $this->assertSame([
            ['CacheManager: Failed to collect category URLs', ['error' => 'cat']],
            ['CacheManager: Failed to collect product URLs', ['error' => 'prod']],
            ['CacheManager: Failed to collect CMS page URLs', ['error' => 'cms']],
        ], $errors);
    }

    public static function storeUrlCases(): array
    {
        return [
            'same host' => ['https://shop.test/a', 'https://shop.test', true],
            'host case insensitive' => ['https://SHOP.test/a', 'https://shop.test', true],
            'http scheme allowed' => ['http://shop.test/a', 'https://shop.test', true],
            'other host' => ['https://evil.test/a', 'https://shop.test', false],
            'ftp scheme' => ['ftp://shop.test/a', 'https://shop.test', false],
            'port mismatch' => ['https://shop.test:8443/a', 'https://shop.test', false],
            'same port' => ['http://shop.test:8080/a', 'http://shop.test:8080', true],
            'userinfo' => ['https://user@shop.test/a', 'https://shop.test', false],
            'password' => ['https://user:pw@shop.test/a', 'https://shop.test', false],
            'no host' => ['/relative/path', 'https://shop.test', false],
            'base without host' => ['https://shop.test/a', '', false],
            'malformed' => ['http:///x', 'https://shop.test', false],
        ];
    }

    #[DataProvider('storeUrlCases')]
    public function testIsStoreUrl(string $url, string $baseUrl, bool $expected): void
    {
        $this->assertSame($expected, $this->invokePrivate('isStoreUrl', $url, $baseUrl));
    }

    public function testUnsafeBaseUrlYieldsNoUrls(): void
    {
        $this->configHelper->method('getWarmupPages')->willReturn(['home']);

        $this->assertSame([], $this->invokePrivate('collectUrls', $this->store(1, 'https://user:pw@shop.test/')));
        $this->assertSame([], $this->invokePrivate('collectUrls', $this->store(1, 'ftp://shop.test/')));
    }

    public function testWarmupReportsRowsToCallbackAndToleratesLogSaveFailure(): void
    {
        $this->storeManager->method('getStores')->willReturn([$this->store(4, 'http://127.0.0.1:9/')]);
        $this->configHelper->method('isWarmupEnabled')->willReturn(true);
        $this->configHelper->method('getWarmupPages')->willReturn(['home']);
        $this->configHelper->method('getConcurrentRequests')->willReturn(0);
        $this->configHelper->method('getRedirectStatus')->willReturn('success');

        $log = $this->createMock(WarmupLog::class);
        $log->expects($this->once())
            ->method('setData')
            ->with($this->callback(static function ($data): bool {
                return $data['url'] === 'http://127.0.0.1:9/'
                    && $data['page_type'] === 'home'
                    && $data['status'] === 'failed'
                    && array_key_exists('http_status', $data)
                    && array_key_exists('response_time', $data);
            }))
            ->willReturnSelf();
        $this->logFactory->method('create')->willReturn($log);
        $this->logResource->method('save')->willThrowException(new \RuntimeException('cannot save'));

        $infos = [];
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->logger->expects($this->never())->method('error');
        $this->logger->method('info')->willReturnCallback(
            static function ($message, array $context = []) use (&$infos): void {
                $infos[] = [$message, $context];
            }
        );

        $seen = [];
        $results = $this->cron()->runWarmup(static function (array $row) use (&$seen): void {
            $seen[] = $row;
        });

        $this->assertCount(1, $results);
        $this->assertSame($results, $seen);
        $this->assertSame(4, $results[0]['store_id']);
        $this->assertSame('home', $results[0]['page_type']);
        $this->assertSame(
            ['CacheManager: Starting cache warmup', ['store' => 'store4', 'url_count' => 1, 'concurrent' => 1]],
            $infos[0]
        );
        $this->assertSame(['CacheManager: Cache warmup completed', ['total_urls' => 1]], $infos[1]);
    }
}
