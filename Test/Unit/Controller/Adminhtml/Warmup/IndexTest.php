<?php
declare(strict_types=1);

namespace Panth\CacheManager\Test\Unit\Controller\Adminhtml\Warmup;

use Magento\Backend\App\Action\Context;
use Magento\Backend\Model\View\Result\Page;
use Magento\Framework\AuthorizationInterface;
use Magento\Framework\View\Page\Config as PageConfig;
use Magento\Framework\View\Page\Title;
use Magento\Framework\View\Result\PageFactory;
use Panth\CacheManager\Controller\Adminhtml\Warmup\Index;
use PHPUnit\Framework\TestCase;

class IndexTest extends TestCase
{
    public function testExecuteBuildsWarmupLogPage(): void
    {
        $title = $this->createMock(Title::class);
        $title->expects($this->once())
            ->method('prepend')
            ->with($this->callback(static fn ($phrase): bool => (string) $phrase === 'Cache Warmup Log'));

        $config = $this->createStub(PageConfig::class);
        $config->method('getTitle')->willReturn($title);

        $page = $this->createMock(Page::class);
        $page->expects($this->once())
            ->method('setActiveMenu')
            ->with('Panth_CacheManager::warmup_log')
            ->willReturnSelf();
        $page->method('getConfig')->willReturn($config);

        $factory = $this->createStub(PageFactory::class);
        $factory->method('create')->willReturn($page);

        $controller = new Index($this->createStub(Context::class), $factory);

        $this->assertSame($page, $controller->execute());
    }

    public function testAclUsesConfigResource(): void
    {
        $this->assertSame('Panth_CacheManager::config', Index::ADMIN_RESOURCE);

        $authorization = $this->createMock(AuthorizationInterface::class);
        $authorization->expects($this->once())
            ->method('isAllowed')
            ->with('Panth_CacheManager::config')
            ->willReturn(false);
        $context = $this->createStub(Context::class);
        $context->method('getAuthorization')->willReturn($authorization);

        $controller = new Index($context, $this->createStub(PageFactory::class));
        $method = new \ReflectionMethod($controller, '_isAllowed');

        $this->assertFalse($method->invoke($controller));
    }
}
