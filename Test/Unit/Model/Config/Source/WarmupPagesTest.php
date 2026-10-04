<?php
declare(strict_types=1);

namespace Panth\CacheManager\Test\Unit\Model\Config\Source;

use Panth\CacheManager\Model\Config\Source\WarmupPages;
use PHPUnit\Framework\TestCase;

class WarmupPagesTest extends TestCase
{
    public function testToOptionArrayListsSupportedPageTypes(): void
    {
        $options = (new WarmupPages())->toOptionArray();

        $this->assertSame(
            ['home', 'catalog_category', 'catalog_product', 'cms'],
            array_column($options, 'value')
        );
        $this->assertSame('Home Page', (string) $options[0]['label']);
        $this->assertSame('CMS Pages', (string) $options[3]['label']);
    }

    public function testToArrayIsConsistentWithOptionArray(): void
    {
        $source = new WarmupPages();
        $map = array_map('strval', $source->toArray());
        $fromOptions = [];
        foreach ($source->toOptionArray() as $option) {
            $fromOptions[$option['value']] = (string) $option['label'];
        }

        $this->assertSame($fromOptions, $map);
    }
}
