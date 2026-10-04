<?php
declare(strict_types=1);

namespace Panth\CacheManager\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;
use Magento\Framework\Phrase;

class WarmupPages implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'home', 'label' => __('Home Page')],
            ['value' => 'catalog_category', 'label' => __('Category Pages')],
            ['value' => 'catalog_product', 'label' => __('Product Pages')],
            ['value' => 'cms', 'label' => __('CMS Pages')]
        ];
    }

    public function toArray(): array
    {
        return [
            'home' => __('Home Page'),
            'catalog_category' => __('Category Pages'),
            'catalog_product' => __('Product Pages'),
            'cms' => __('CMS Pages')
        ];
    }
}
