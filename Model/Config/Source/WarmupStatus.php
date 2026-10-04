<?php
declare(strict_types=1);

namespace Panth\CacheManager\Model\Config\Source;

use Magento\Framework\Data\OptionSourceInterface;

class WarmupStatus implements OptionSourceInterface
{
    public const SUCCESS = 'success';
    public const FAILED = 'failed';
    public const SKIPPED = 'skipped';
    public const PENDING = 'pending';

    public function toOptionArray(): array
    {
        $options = [];
        foreach ($this->toArray() as $value => $label) {
            $options[] = ['value' => $value, 'label' => $label];
        }
        return $options;
    }

    public function toArray(): array
    {
        return [
            self::SUCCESS => __('Success'),
            self::FAILED => __('Failed'),
            self::SKIPPED => __('Skipped'),
            self::PENDING => __('Pending'),
        ];
    }
}
