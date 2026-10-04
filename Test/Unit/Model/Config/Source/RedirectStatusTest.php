<?php
declare(strict_types=1);

namespace Panth\CacheManager\Test\Unit\Model\Config\Source;

use Panth\CacheManager\Model\Config\Source\RedirectStatus;
use PHPUnit\Framework\TestCase;

class RedirectStatusTest extends TestCase
{
    public function testOptionsMatchHelperWhitelist(): void
    {
        $options = (new RedirectStatus())->toOptionArray();

        $this->assertSame(['success', 'skipped', 'failed'], array_column($options, 'value'));
        $this->assertSame(
            ['Success', 'Skipped', 'Failed'],
            array_map(static fn (array $option): string => (string) $option['label'], $options)
        );
    }
}
