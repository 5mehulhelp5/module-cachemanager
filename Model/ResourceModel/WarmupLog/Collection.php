<?php
declare(strict_types=1);

namespace Panth\CacheManager\Model\ResourceModel\WarmupLog;

use Magento\Framework\Model\ResourceModel\Db\Collection\AbstractCollection;
use Panth\CacheManager\Model\WarmupLog;
use Panth\CacheManager\Model\ResourceModel\WarmupLog as WarmupLogResource;

class Collection extends AbstractCollection
{
    protected $_idFieldName = 'log_id';

    protected function _construct(): void
    {
        $this->_init(WarmupLog::class, WarmupLogResource::class);
    }
}
