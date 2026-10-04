<?php
declare(strict_types=1);

namespace Panth\CacheManager\Test\Unit\Observer;

use Magento\Framework\App\CacheInterface;
use Magento\Framework\DataObject;
use Magento\Framework\DataObject\IdentityInterface;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Panth\CacheManager\Helper\Data as ConfigHelper;
use Panth\CacheManager\Observer\CacheInvalidate;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;

class CacheInvalidateEdgeCasesTest extends TestCase
{
    private function observerFor(string $eventName, $entity): Observer
    {
        $event = new Event(['name' => $eventName, 'data_object' => $entity]);
        return new Observer(['event' => $event]);
    }

    private function enabledHelper(): ConfigHelper
    {
        $helper = $this->createStub(ConfigHelper::class);
        $helper->method('isEnabled')->willReturn(true);
        $helper->method('isSmartInvalidationEnabled')->willReturn(true);
        $helper->method('shouldInvalidateOnProductSave')->willReturn(true);
        $helper->method('shouldInvalidateOnCategorySave')->willReturn(true);
        $helper->method('shouldInvalidateOnCmsSave')->willReturn(true);
        return $helper;
    }

    public function testEmptyIdentitiesFallBackToTypeTag(): void
    {
        $entity = $this->createStub(IdentityInterface::class);
        $entity->method('getIdentities')->willReturn(['', '0']);

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())->method('clean')->with(['catalog_category']);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('info')
            ->with(
                'CacheManager: Smart invalidation triggered',
                ['event' => 'catalog_category_save_after', 'tags' => ['catalog_category']]
            );

        (new CacheInvalidate($this->enabledHelper(), $cache, $logger))
            ->execute($this->observerFor('catalog_category_save_after', $entity));
    }

    public function testNonIdentityEntityUsesTypeTag(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())->method('clean')->with(['cms_block']);

        (new CacheInvalidate($this->enabledHelper(), $cache, $this->createStub(LoggerInterface::class)))
            ->execute($this->observerFor('cms_block_save_after', new DataObject(['id' => 9])));
    }

    public function testIdentitiesAreReindexedAfterFiltering(): void
    {
        $entity = $this->createStub(IdentityInterface::class);
        $entity->method('getIdentities')->willReturn(['', 'cms_p_7', 'cms_p_7', 'cms_p']);

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->once())->method('clean')->with(['cms_p_7', 'cms_p']);

        (new CacheInvalidate($this->enabledHelper(), $cache, $this->createStub(LoggerInterface::class)))
            ->execute($this->observerFor('cms_page_save_after', $entity));
    }

    public function testSmartInvalidationOffSkipsCacheClean(): void
    {
        $helper = $this->createStub(ConfigHelper::class);
        $helper->method('isEnabled')->willReturn(true);
        $helper->method('isSmartInvalidationEnabled')->willReturn(false);

        $cache = $this->createMock(CacheInterface::class);
        $cache->expects($this->never())->method('clean');

        (new CacheInvalidate($helper, $cache, $this->createStub(LoggerInterface::class)))
            ->execute($this->observerFor('catalog_product_save_after', null));
    }

    public function testCacheCleanFailureIsLogged(): void
    {
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('clean')->willThrowException(new \RuntimeException('backend down'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('info');
        $logger->expects($this->once())
            ->method('error')
            ->with('CacheManager Observer Error: backend down');

        (new CacheInvalidate($this->enabledHelper(), $cache, $logger))
            ->execute($this->observerFor('catalog_product_save_after', null));
    }
}
