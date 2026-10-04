<?php
declare(strict_types=1);

namespace Panth\CacheManager\Test\Unit\Model\Config\Source;

use Panth\CacheManager\Model\Config\Source\LogPageType;
use Panth\CacheManager\Model\Config\Source\WarmupStatus;
use PHPUnit\Framework\TestCase;

class WarmupStatusTest extends TestCase
{
    public function testStatusOptionsCoverEveryStoredStatus(): void
    {
        $options = (new WarmupStatus())->toOptionArray();

        $this->assertSame(['success', 'failed', 'skipped', 'pending'], array_column($options, 'value'));
        $this->assertSame(
            ['Success', 'Failed', 'Skipped', 'Pending'],
            array_map(static fn (array $option): string => (string) $option['label'], $options)
        );
    }

    public function testStatusToArrayIsKeyedByValue(): void
    {
        $map = (new WarmupStatus())->toArray();

        $this->assertSame(
            [WarmupStatus::SUCCESS, WarmupStatus::FAILED, WarmupStatus::SKIPPED, WarmupStatus::PENDING],
            array_keys($map)
        );
        $this->assertSame('Failed', (string) $map['failed']);
    }

    public function testLogPageTypesMatchValuesWrittenByWarmup(): void
    {
        $options = (new LogPageType())->toOptionArray();

        $this->assertSame(['home', 'category', 'product', 'cms'], array_column($options, 'value'));
        $this->assertSame(
            ['Home Page', 'Category Page', 'Product Page', 'CMS Page'],
            array_map(static fn (array $option): string => (string) $option['label'], $options)
        );
    }
}
