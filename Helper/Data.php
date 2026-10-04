<?php
declare(strict_types=1);

namespace Panth\CacheManager\Helper;

use Panth\Core\Helper\AbstractConfig;
use Magento\Store\Model\ScopeInterface;

class Data extends AbstractConfig
{
    public const XML_PATH_CACHE_MANAGER = 'panth_cachemanager/';
    public const XML_PATH_PRODUCT_URL_SUFFIX = 'catalog/seo/product_url_suffix';
    public const XML_PATH_CATEGORY_URL_SUFFIX = 'catalog/seo/category_url_suffix';
    public const MAX_CONCURRENT_REQUESTS = 50;

    protected function getConfigValue(string $group, string $field, $storeId = null)
    {
        return $this->scopeConfig->getValue(
            self::XML_PATH_CACHE_MANAGER . $group . '/' . $field,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }

    public function isEnabled($storeId = null): bool
    {
        return (bool)$this->getConfigValue('general', 'enabled', $storeId);
    }

    public function getCacheTtl($storeId = null): int
    {
        return (int)$this->getConfigValue('full_page', 'ttl', $storeId) ?: 86400;
    }

    public function isWarmupEnabled($storeId = null): bool
    {
        return $this->isEnabled($storeId) && (bool)$this->getConfigValue('warmup', 'enabled', $storeId);
    }

    public function getWarmupPages($storeId = null): array
    {
        $pages = $this->getConfigValue('warmup', 'warmup_pages', $storeId);
        return $pages ? explode(',', (string)$pages) : [];
    }

    public function getWarmupSchedule($storeId = null): string
    {
        return (string)$this->getConfigValue('warmup', 'warmup_schedule', $storeId) ?: '0 */6 * * *';
    }

    public function getConcurrentRequests($storeId = null): int
    {
        $value = (int)$this->getConfigValue('warmup', 'concurrent_requests', $storeId);
        return $value > 0 ? min(self::MAX_CONCURRENT_REQUESTS, $value) : 5;
    }

    public function getRedirectStatus($storeId = null): string
    {
        $value = (string)$this->getConfigValue('warmup', 'redirect_status', $storeId);
        return in_array($value, ['success', 'skipped', 'failed'], true) ? $value : 'success';
    }

    public function getLogRetentionDays(): int
    {
        return max(0, (int)$this->getConfigValue('warmup', 'log_retention_days'));
    }

    public function isSmartInvalidationEnabled($storeId = null): bool
    {
        return $this->isEnabled($storeId)
            && (bool)$this->getConfigValue('invalidation', 'smart_invalidation', $storeId);
    }

    public function shouldInvalidateOnProductSave($storeId = null): bool
    {
        return (bool)$this->getConfigValue('invalidation', 'invalidate_on_product_save', $storeId);
    }

    public function shouldInvalidateOnCategorySave($storeId = null): bool
    {
        return (bool)$this->getConfigValue('invalidation', 'invalidate_on_category_save', $storeId);
    }

    public function shouldInvalidateOnCmsSave($storeId = null): bool
    {
        return (bool)$this->getConfigValue('invalidation', 'invalidate_on_cms_save', $storeId);
    }

    public function getProductUrlSuffix($storeId = null): string
    {
        return (string)$this->getConfig(self::XML_PATH_PRODUCT_URL_SUFFIX, $storeId);
    }

    public function getCategoryUrlSuffix($storeId = null): string
    {
        return (string)$this->getConfig(self::XML_PATH_CATEGORY_URL_SUFFIX, $storeId);
    }
}
