<?php
declare(strict_types=1);

namespace Panth\CacheManager\Model;

use Magento\Framework\Model\AbstractModel;

class WarmupLog extends AbstractModel
{
    protected function _construct(): void
    {
        $this->_init(\Panth\CacheManager\Model\ResourceModel\WarmupLog::class);
    }
}
