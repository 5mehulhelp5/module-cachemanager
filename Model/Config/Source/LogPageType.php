<?php
declare(strict_types=1);

namespace Panth\CacheManager\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class LogPageType implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'home', 'label' => __('Home Page')],
            ['value' => 'category', 'label' => __('Category Page')],
            ['value' => 'product', 'label' => __('Product Page')],
            ['value' => 'cms', 'label' => __('CMS Page')],
        ];
    }
}
