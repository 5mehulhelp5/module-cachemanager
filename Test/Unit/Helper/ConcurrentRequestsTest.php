<?php
declare(strict_types=1);

namespace Panth\CacheManager\Test\Unit\Helper;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Helper\Context;
use Panth\CacheManager\Helper\Data;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ConcurrentRequestsTest extends TestCase
{
    public static function valueProvider(): array
    {
        return [
            'configured' => ['8', 8],
            'minimum' => ['1', 1],
            'maximum' => ['50', 50],
            'above maximum is capped' => ['5000', 50],
            'zero falls back' => ['0', 5],
            'negative falls back' => ['-3', 5],
            'empty falls back' => [null, 5],
            'decimal truncates' => ['2.5', 2],
        ];
    }

    #[DataProvider('valueProvider')]
    public function testConcurrentRequestsAreBounded(?string $stored, int $expected): void
    {
        $scopeConfig = $this->createStub(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            static fn (string $path) => $path === 'panth_cachemanager/warmup/concurrent_requests' ? $stored : null
        );
        $context = $this->createStub(Context::class);
        $context->method('getScopeConfig')->willReturn($scopeConfig);

        $this->assertSame($expected, (new Data($context))->getConcurrentRequests(1));
        $this->assertSame(50, Data::MAX_CONCURRENT_REQUESTS);
    }
}
