<?php
declare(strict_types=1);

namespace Panth\CacheManager\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class RedirectStatus implements OptionSourceInterface
{
    public function toOptionArray(): array
    {
        return [
            ['value' => 'success', 'label' => __('Success')],
            ['value' => 'skipped', 'label' => __('Skipped')],
            ['value' => 'failed', 'label' => __('Failed')],
        ];
    }
}
