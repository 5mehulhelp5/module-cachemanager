<?php
declare(strict_types=1);

namespace Panth\CacheManager\Test\Unit\Helper;

use Panth\CacheManager\Helper\Data;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

class DataTest extends TestCase
{
    private Data $helper;

    private $scopeConfigMock;

    private $contextMock;

    protected function setUp(): void
    {
        $this->scopeConfigMock = $this->createMock(ScopeConfigInterface::class);
        $this->contextMock = $this->createMock(Context::class);

        $this->contextMock->method('getScopeConfig')
            ->willReturn($this->scopeConfigMock);

        $this->helper = new Data($this->contextMock);
    }

    public function testIsEnabledReturnsTrueWhenConfigured(): void
    {
        $this->scopeConfigMock->method('getValue')
            ->willReturnMap([
                ['panth_cachemanager/general/enabled', ScopeInterface::SCOPE_STORE, null, '1'],
            ]);

        $this->assertTrue($this->helper->isEnabled());
    }

    public function testIsEnabledReturnsFalseWhenNotConfigured(): void
    {
        $this->scopeConfigMock->method('getValue')
            ->willReturnMap([
                ['panth_cachemanager/general/enabled', ScopeInterface::SCOPE_STORE, null, '0'],
            ]);

        $this->assertFalse($this->helper->isEnabled());
    }

    public function testIsEnabledWithStoreId(): void
    {
        $storeId = 2;
        $this->scopeConfigMock->method('getValue')
            ->willReturnMap([
                ['panth_cachemanager/general/enabled', ScopeInterface::SCOPE_STORE, $storeId, '1'],
            ]);

        $this->assertTrue($this->helper->isEnabled($storeId));
    }

    public function testGetCacheTtlReturnsConfiguredValue(): void
    {
        $this->scopeConfigMock->method('getValue')
            ->willReturnMap([
                ['panth_cachemanager/full_page/ttl', ScopeInterface::SCOPE_STORE, null, '3600'],
            ]);

        $this->assertEquals(3600, $this->helper->getCacheTtl());
    }

    public function testGetCacheTtlReturnsDefaultValue(): void
    {
        $this->scopeConfigMock->method('getValue')
            ->willReturnMap([
                ['panth_cachemanager/full_page/ttl', ScopeInterface::SCOPE_STORE, null, null],
            ]);

        $this->assertEquals(86400, $this->helper->getCacheTtl());
    }

    public function testIsWarmupEnabledReturnsTrueWhenBothEnabled(): void
    {
        $this->scopeConfigMock->method('getValue')
            ->willReturnMap([
                ['panth_cachemanager/general/enabled', ScopeInterface::SCOPE_STORE, null, '1'],
                ['panth_cachemanager/warmup/enabled', ScopeInterface::SCOPE_STORE, null, '1'],
            ]);

        $this->assertTrue($this->helper->isWarmupEnabled());
    }

    public function testIsWarmupEnabledReturnsFalseWhenGeneralDisabled(): void
    {
        $this->scopeConfigMock->method('getValue')
            ->willReturnMap([
                ['panth_cachemanager/general/enabled', ScopeInterface::SCOPE_STORE, null, '0'],
            ]);

        $this->assertFalse($this->helper->isWarmupEnabled());
    }

    public function testGetWarmupPagesReturnsArrayOfPages(): void
    {
        $this->scopeConfigMock->method('getValue')
            ->willReturnMap([
                ['panth_cachemanager/warmup/warmup_pages', ScopeInterface::SCOPE_STORE, null, 'home,catalog_category,catalog_product,cms'],
            ]);

        $result = $this->helper->getWarmupPages();
        $this->assertCount(4, $result);
        $this->assertContains('home', $result);
    }

    public function testGetWarmupPagesReturnsEmptyArrayWhenNotConfigured(): void
    {
        $this->scopeConfigMock->method('getValue')
            ->willReturnMap([
                ['panth_cachemanager/warmup/warmup_pages', ScopeInterface::SCOPE_STORE, null, null],
            ]);

        $this->assertEmpty($this->helper->getWarmupPages());
    }

    public function testGetConcurrentRequestsReturnsConfiguredValue(): void
    {
        $this->scopeConfigMock->method('getValue')
            ->willReturnMap([
                ['panth_cachemanager/warmup/concurrent_requests', ScopeInterface::SCOPE_STORE, null, '10'],
            ]);

        $this->assertEquals(10, $this->helper->getConcurrentRequests());
    }

    public function testGetConcurrentRequestsReturnsDefaultValue(): void
    {
        $this->scopeConfigMock->method('getValue')
            ->willReturnMap([
                ['panth_cachemanager/warmup/concurrent_requests', ScopeInterface::SCOPE_STORE, null, null],
            ]);

        $this->assertEquals(5, $this->helper->getConcurrentRequests());
    }

    public function testGetWarmupScheduleReturnsConfiguredValue(): void
    {
        $this->scopeConfigMock->method('getValue')
            ->willReturnMap([
                ['panth_cachemanager/warmup/warmup_schedule', ScopeInterface::SCOPE_STORE, null, '0 2 * * *'],
            ]);

        $this->assertEquals('0 2 * * *', $this->helper->getWarmupSchedule());
    }

    public function testGetWarmupScheduleReturnsDefaultValue(): void
    {
        $this->scopeConfigMock->method('getValue')
            ->willReturnMap([
                ['panth_cachemanager/warmup/warmup_schedule', ScopeInterface::SCOPE_STORE, null, null],
            ]);

        $this->assertEquals('0 */6 * * *', $this->helper->getWarmupSchedule());
    }

    public function testIsSmartInvalidationEnabledRequiresModuleEnabled(): void
    {
        $this->scopeConfigMock->method('getValue')
            ->willReturnMap([
                ['panth_cachemanager/general/enabled', ScopeInterface::SCOPE_STORE, null, '1'],
                ['panth_cachemanager/invalidation/smart_invalidation', ScopeInterface::SCOPE_STORE, null, '1'],
            ]);

        $this->assertTrue($this->helper->isSmartInvalidationEnabled());
    }

    public function testIsSmartInvalidationDisabledWhenModuleDisabled(): void
    {
        $this->scopeConfigMock->method('getValue')
            ->willReturnMap([
                ['panth_cachemanager/general/enabled', ScopeInterface::SCOPE_STORE, null, '0'],
            ]);

        $this->assertFalse($this->helper->isSmartInvalidationEnabled());
    }

    public function testShouldInvalidateOnProductSave(): void
    {
        $this->scopeConfigMock->method('getValue')
            ->willReturnMap([
                ['panth_cachemanager/invalidation/invalidate_on_product_save', ScopeInterface::SCOPE_STORE, null, '1'],
            ]);

        $this->assertTrue($this->helper->shouldInvalidateOnProductSave());
    }

    public function testShouldInvalidateOnCategorySave(): void
    {
        $this->scopeConfigMock->method('getValue')
            ->willReturnMap([
                ['panth_cachemanager/invalidation/invalidate_on_category_save', ScopeInterface::SCOPE_STORE, null, '1'],
            ]);

        $this->assertTrue($this->helper->shouldInvalidateOnCategorySave());
    }

    public function testShouldInvalidateOnCmsSave(): void
    {
        $this->scopeConfigMock->method('getValue')
            ->willReturnMap([
                ['panth_cachemanager/invalidation/invalidate_on_cms_save', ScopeInterface::SCOPE_STORE, null, '1'],
            ]);

        $this->assertTrue($this->helper->shouldInvalidateOnCmsSave());
    }
}
