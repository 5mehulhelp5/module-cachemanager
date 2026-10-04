<?php
declare(strict_types=1);

namespace Panth\CacheManager\Test\Unit\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\Context;
use Magento\Store\Model\ScopeInterface;
use Panth\CacheManager\Helper\Data;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class DataCoverageTest extends TestCase
{
    /**
     * @param array $values
     * @return Data
     */
    private function helperWith(array $values): Data
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            static function (string $path, string $scope, $storeId = null) use ($values) {
                if ($scope !== ScopeInterface::SCOPE_STORE) {
                    return null;
                }
                $key = $path . '@' . ($storeId === null ? 'default' : (string) $storeId);
                if (array_key_exists($key, $values)) {
                    return $values[$key];
                }
                return $values[$path] ?? null;
            }
        );
        $context = $this->createStub(Context::class);
        $context->method('getScopeConfig')->willReturn($scopeConfig);
        return new Data($context);
    }

    public static function redirectStatusValues(): array
    {
        return [
            'success' => ['success', 'success'],
            'skipped' => ['skipped', 'skipped'],
            'failed' => ['failed', 'failed'],
            'unknown falls back' => ['bogus', 'success'],
            'empty falls back' => [null, 'success'],
            'case sensitive' => ['SKIPPED', 'success'],
        ];
    }

    #[DataProvider('redirectStatusValues')]
    public function testGetRedirectStatusWhitelistsValues(?string $configured, string $expected): void
    {
        $helper = $this->helperWith(['panth_cachemanager/warmup/redirect_status' => $configured]);
        $this->assertSame($expected, $helper->getRedirectStatus());
    }

    public function testGetRedirectStatusUsesStoreScope(): void
    {
        $helper = $this->helperWith([
            'panth_cachemanager/warmup/redirect_status@default' => 'failed',
            'panth_cachemanager/warmup/redirect_status@4' => 'skipped',
        ]);
        $this->assertSame('skipped', $helper->getRedirectStatus(4));
        $this->assertSame('failed', $helper->getRedirectStatus());
    }

    public static function retentionValues(): array
    {
        return [
            'positive' => ['30', 30],
            'zero' => ['0', 0],
            'negative clamps to zero' => ['-5', 0],
            'missing' => [null, 0],
            'non numeric' => ['abc', 0],
        ];
    }

    #[DataProvider('retentionValues')]
    public function testGetLogRetentionDaysIsNeverNegative(?string $configured, int $expected): void
    {
        $helper = $this->helperWith(['panth_cachemanager/warmup/log_retention_days' => $configured]);
        $this->assertSame($expected, $helper->getLogRetentionDays());
    }

    public function testUrlSuffixesReadCatalogSeoPaths(): void
    {
        $helper = $this->helperWith([
            'catalog/seo/product_url_suffix@2' => '.html',
            'catalog/seo/category_url_suffix@2' => '/',
        ]);
        $this->assertSame('.html', $helper->getProductUrlSuffix(2));
        $this->assertSame('/', $helper->getCategoryUrlSuffix(2));
        $this->assertSame('', $helper->getProductUrlSuffix());
        $this->assertSame('', $helper->getCategoryUrlSuffix());
    }

    public function testIsWarmupEnabledFalseWhenOnlyGeneralEnabled(): void
    {
        $helper = $this->helperWith(['panth_cachemanager/general/enabled' => '1']);
        $this->assertFalse($helper->isWarmupEnabled());
    }

    public function testIsWarmupEnabledRespectsStoreScope(): void
    {
        $helper = $this->helperWith([
            'panth_cachemanager/general/enabled@3' => '1',
            'panth_cachemanager/warmup/enabled@3' => '1',
        ]);
        $this->assertTrue($helper->isWarmupEnabled(3));
        $this->assertFalse($helper->isWarmupEnabled(1));
    }

    public function testIsSmartInvalidationFalseWhenFeatureFlagOff(): void
    {
        $helper = $this->helperWith(['panth_cachemanager/general/enabled' => '1']);
        $this->assertFalse($helper->isSmartInvalidationEnabled());
    }

    public function testInvalidationTogglesDefaultToFalse(): void
    {
        $helper = $this->helperWith([]);
        $this->assertFalse($helper->shouldInvalidateOnProductSave());
        $this->assertFalse($helper->shouldInvalidateOnCategorySave());
        $this->assertFalse($helper->shouldInvalidateOnCmsSave());
    }

    public function testGetWarmupPagesSplitsWithoutTrimming(): void
    {
        $helper = $this->helperWith(['panth_cachemanager/warmup/warmup_pages@5' => 'home, cms']);
        $this->assertSame(['home', ' cms'], $helper->getWarmupPages(5));
    }

    public function testZeroTtlAndConcurrencyFallBackToDefaults(): void
    {
        $helper = $this->helperWith([
            'panth_cachemanager/full_page/ttl' => '0',
            'panth_cachemanager/warmup/concurrent_requests' => '0',
            'panth_cachemanager/warmup/warmup_schedule' => '',
        ]);
        $this->assertSame(86400, $helper->getCacheTtl());
        $this->assertSame(5, $helper->getConcurrentRequests());
        $this->assertSame('0 */6 * * *', $helper->getWarmupSchedule());
    }
}
