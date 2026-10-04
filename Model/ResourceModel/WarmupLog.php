<?php
declare(strict_types=1);

namespace Panth\CacheManager\Model\ResourceModel;

use Magento\Framework\Model\ResourceModel\Db\AbstractDb;

class WarmupLog extends AbstractDb
{
    protected function _construct(): void
    {
        $this->_init('panth_cache_warmup_log', 'log_id');
    }
}
