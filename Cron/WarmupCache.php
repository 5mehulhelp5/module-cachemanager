<?php
declare(strict_types=1);

namespace Panth\CacheManager\Cron;

use Panth\CacheManager\Helper\Data as ConfigHelper;
use Panth\CacheManager\Model\WarmupLogFactory;
use Panth\CacheManager\Model\ResourceModel\WarmupLog as WarmupLogResource;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory as CategoryCollectionFactory;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory as ProductCollectionFactory;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory as CmsPageCollectionFactory;
use Psr\Log\LoggerInterface;

class WarmupCache
{
    private ConfigHelper $configHelper;

    private StoreManagerInterface $storeManager;

    private CategoryCollectionFactory $categoryCollectionFactory;

    private ProductCollectionFactory $productCollectionFactory;

    private CmsPageCollectionFactory $cmsPageCollectionFactory;

    private LoggerInterface $logger;
    private WarmupLogFactory $warmupLogFactory;
    private WarmupLogResource $warmupLogResource;

    public function __construct(
        ConfigHelper $configHelper,
        StoreManagerInterface $storeManager,
        CategoryCollectionFactory $categoryCollectionFactory,
        ProductCollectionFactory $productCollectionFactory,
        CmsPageCollectionFactory $cmsPageCollectionFactory,
        LoggerInterface $logger,
        WarmupLogFactory $warmupLogFactory,
        WarmupLogResource $warmupLogResource
    ) {
        $this->configHelper = $configHelper;
        $this->storeManager = $storeManager;
        $this->categoryCollectionFactory = $categoryCollectionFactory;
        $this->productCollectionFactory = $productCollectionFactory;
        $this->cmsPageCollectionFactory = $cmsPageCollectionFactory;
        $this->logger = $logger;
        $this->warmupLogFactory = $warmupLogFactory;
        $this->warmupLogResource = $warmupLogResource;
    }

    public function execute(): void
    {
        if (!$this->configHelper->isWarmupEnabled()) {
            return;
        }

        try {
            $this->runWarmup();
        } catch (\Exception $e) {
            $this->logger->error('CacheManager Cron Error: ' . $e->getMessage());
        }
    }

    public function runWarmup(?callable $onResult = null): array
    {
        $this->pruneLog();

        $results = [];
        $total = 0;
        foreach ($this->getStoresToWarm() as $store) {
            $storeId = (int) $store->getId();
            $urls = $this->collectUrls($store);
            if (empty($urls)) {
                continue;
            }

            $concurrentRequests = max(1, $this->configHelper->getConcurrentRequests($storeId));
            $this->logger->info('CacheManager: Starting cache warmup', [
                'store' => $store->getCode(),
                'url_count' => count($urls),
                'concurrent' => $concurrentRequests
            ]);

            $storeResults = $this->warmUpUrls($urls, $concurrentRequests, $storeId, $onResult);
            $total += count($storeResults);
            array_push($results, ...$storeResults);
        }

        if ($total === 0) {
            $this->logger->info('CacheManager: No URLs to warm up');
            return [];
        }

        $this->logger->info('CacheManager: Cache warmup completed', [
            'total_urls' => $total
        ]);
        return $results;
    }

    private function getStoresToWarm(): array
    {
        $stores = [];
        foreach ($this->storeManager->getStores(false) as $store) {
            if (!$store->getIsActive()) {
                continue;
            }
            if (!$this->configHelper->isWarmupEnabled((int) $store->getId())) {
                continue;
            }
            $stores[] = $store;
        }
        return $stores;
    }

    private function pruneLog(): void
    {
        $days = $this->configHelper->getLogRetentionDays();
        if ($days <= 0) {
            return;
        }
        try {
            $connection = $this->warmupLogResource->getConnection();
            $deleted = $connection->delete(
                $this->warmupLogResource->getMainTable(),
                ['warmed_at < ?' => gmdate('Y-m-d H:i:s', time() - $days * 86400)]
            );
            if ($deleted > 0) {
                $this->logger->info('CacheManager: Pruned warmup log', ['rows' => $deleted, 'days' => $days]);
            }
        } catch (\Exception $e) {
            $this->logger->error('CacheManager: Failed to prune warmup log', ['error' => $e->getMessage()]);
        }
    }

    private function collectUrls(StoreInterface $store): array
    {
        $storeId = (int) $store->getId();
        $pageTypes = $this->configHelper->getWarmupPages($storeId);
        $baseUrl = rtrim((string) $store->getBaseUrl(), '/');
        $urls = [];

        foreach ($pageTypes as $type) {
            $type = trim($type);
            switch ($type) {
                case 'home':
                    $urls[$baseUrl . '/'] = 'home';
                    break;
                case 'catalog_category':
                    $urls += array_fill_keys($this->getCategoryUrls($baseUrl, $storeId), 'category');
                    break;
                case 'catalog_product':
                    $urls += array_fill_keys($this->getProductUrls($baseUrl, $storeId), 'product');
                    break;
                case 'cms':
                    $urls += array_fill_keys($this->getCmsPageUrls($baseUrl, $storeId), 'cms');
                    break;
            }
        }

        return array_filter(
            $urls,
            fn ($url) => $this->isStoreUrl((string) $url, $baseUrl),
            ARRAY_FILTER_USE_KEY
        );
    }

    private function isStoreUrl(string $url, string $baseUrl): bool
    {
        $base = parse_url($baseUrl);
        $target = parse_url($url);
        if (!is_array($base) || !is_array($target) || empty($target['host']) || empty($base['host'])) {
            return false;
        }
        $scheme = strtolower((string) ($target['scheme'] ?? ''));
        if (!in_array($scheme, ['http', 'https'], true)) {
            return false;
        }
        return strtolower((string) $target['host']) === strtolower((string) $base['host'])
            && ($target['port'] ?? null) === ($base['port'] ?? null)
            && !isset($target['user'])
            && !isset($target['pass']);
    }

    private function getCategoryUrls(string $baseUrl, int $storeId): array
    {
        $urls = [];

        try {
            $suffix = $this->configHelper->getCategoryUrlSuffix($storeId);
            $collection = $this->categoryCollectionFactory->create();
            $collection->setStoreId($storeId)
                ->addAttributeToSelect('url_path')
                ->addAttributeToFilter('is_active', 1)
                ->addAttributeToFilter('level', ['gt' => 1]);

            foreach ($collection as $category) {
                $urlPath = $category->getUrlPath();
                if ($urlPath) {
                    $urls[] = $baseUrl . '/' . ltrim($urlPath, '/') . $suffix;
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('CacheManager: Failed to collect category URLs', [
                'error' => $e->getMessage()
            ]);
        }

        return $urls;
    }

    private function getProductUrls(string $baseUrl, int $storeId): array
    {
        $urls = [];

        try {
            $suffix = $this->configHelper->getProductUrlSuffix($storeId);
            $collection = $this->productCollectionFactory->create();
            $collection->setStoreId($storeId)
                ->addStoreFilter($storeId)
                ->addAttributeToSelect('url_key')
                ->addAttributeToFilter('status', \Magento\Catalog\Model\Product\Attribute\Source\Status::STATUS_ENABLED)
                ->addAttributeToFilter(
                    'visibility',
                    ['in' => [
                        \Magento\Catalog\Model\Product\Visibility::VISIBILITY_IN_CATALOG,
                        \Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH
                    ]]
                );

            foreach ($collection as $product) {
                $urlKey = $product->getUrlKey();
                if ($urlKey) {
                    $urls[] = $baseUrl . '/' . ltrim($urlKey, '/') . $suffix;
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('CacheManager: Failed to collect product URLs', [
                'error' => $e->getMessage()
            ]);
        }

        return $urls;
    }

    private function getCmsPageUrls(string $baseUrl, int $storeId): array
    {
        $urls = [];

        try {
            $collection = $this->cmsPageCollectionFactory->create();
            $collection->addFieldToFilter('is_active', 1)
                ->addStoreFilter($storeId);

            foreach ($collection as $page) {
                $identifier = $page->getIdentifier();
                if ($identifier && $identifier !== 'no-route') {
                    $urls[] = $baseUrl . '/' . ltrim($identifier, '/');
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('CacheManager: Failed to collect CMS page URLs', [
                'error' => $e->getMessage()
            ]);
        }

        return $urls;
    }

    private function warmUpUrls(
        array $urls,
        int $concurrentRequests,
        int $storeId,
        ?callable $onResult = null
    ): array {
        $chunks = array_chunk($urls, $concurrentRequests, true);
        $redirectStatus = $this->configHelper->getRedirectStatus($storeId);
        $results = [];

        foreach ($chunks as $chunk) {
            $multiHandle = curl_multi_init();
            $curlHandles = [];

            foreach (array_keys($chunk) as $url) {
                $ch = curl_init();
                curl_setopt_array($ch, [
                    CURLOPT_URL => $url,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_FOLLOWLOCATION => false,
                    CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
                    CURLOPT_TIMEOUT => 30,
                    CURLOPT_CONNECTTIMEOUT => 10,
                    CURLOPT_USERAGENT => 'PanthCacheManager/1.0 (Warmup)',
                    CURLOPT_NOBODY => false,
                    CURLOPT_SSL_VERIFYPEER => true,
                ]);
                curl_multi_add_handle($multiHandle, $ch);
                $curlHandles[$url] = $ch;
            }

            $transferResults = [];
            $running = null;
            do {
                curl_multi_exec($multiHandle, $running);
                while (($info = curl_multi_info_read($multiHandle)) !== false) {
                    $transferResults[spl_object_id($info['handle'])] = (int) $info['result'];
                }
                if ($running > 0) {
                    curl_multi_select($multiHandle, 1.0);
                }
            } while ($running > 0);
            while (($info = curl_multi_info_read($multiHandle)) !== false) {
                $transferResults[spl_object_id($info['handle'])] = (int) $info['result'];
            }

            foreach ($curlHandles as $url => $ch) {
                $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $totalTime = round(curl_getinfo($ch, CURLINFO_TOTAL_TIME) * 1000, 2);
                $curlCode = $transferResults[spl_object_id($ch)] ?? null;
                $error = (string) curl_error($ch);
                if ($error === '' && $curlCode !== null && $curlCode !== CURLE_OK) {
                    $error = (string) curl_strerror($curlCode);
                }
                if ($error === '' && $curlCode === null) {
                    $error = 'Transfer did not complete';
                }

                $status = $this->resolveStatus($httpCode, $error, $redirectStatus);
                $pageType = (string) ($chunk[$url] ?? 'cms');

                $row = [
                    'store_id' => $storeId,
                    'url' => $url,
                    'page_type' => $pageType,
                    'status' => $status,
                    'http_code' => $httpCode,
                    'response_time_ms' => $totalTime,
                    'error' => $error,
                ];
                $results[] = $row;

                try {
                    $log = $this->warmupLogFactory->create();
                    $log->setData([
                        'url' => $url,
                        'page_type' => $pageType,
                        'http_status' => $httpCode,
                        'status' => $status,
                        'response_time' => $totalTime,
                    ]);
                    $this->warmupLogResource->save($log);
                } catch (\Exception) {
                }

                if ($onResult !== null) {
                    $onResult($row);
                }

                curl_multi_remove_handle($multiHandle, $ch);
                curl_close($ch);
            }

            curl_multi_close($multiHandle);
        }
        return $results;
    }

    private function resolveStatus(int $httpCode, string $error, string $redirectStatus): string
    {
        if ($error !== '' || $httpCode === 0 || $httpCode >= 400) {
            return 'failed';
        }
        if ($httpCode >= 300) {
            return $redirectStatus;
        }
        return 'success';
    }
}
