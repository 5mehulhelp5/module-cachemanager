<?php
declare(strict_types=1);

namespace Panth\CacheManager\Model\ResourceModel\WarmupLog\Grid;

use Magento\Framework\View\Element\UiComponent\DataProvider\SearchResult;
use Magento\Framework\Data\Collection\EntityFactoryInterface;
use Magento\Framework\Data\Collection\Db\FetchStrategyInterface;
use Magento\Framework\Event\ManagerInterface;
use Psr\Log\LoggerInterface;
use Panth\CacheManager\Model\ResourceModel\WarmupLog as WarmupLogResource;

class Collection extends SearchResult
{
    protected $_idFieldName = 'log_id';

    public function __construct(
        EntityFactoryInterface $entityFactory,
        LoggerInterface $logger,
        FetchStrategyInterface $fetchStrategy,
        ManagerInterface $eventManager,
        $mainTable = 'panth_cache_warmup_log',
        $resourceModel = WarmupLogResource::class
    ) {
        parent::__construct($entityFactory, $logger, $fetchStrategy, $eventManager, $mainTable, $resourceModel);
    }

    protected function _initSelect()
    {
        parent::_initSelect();
        $this->addFilterToMap('log_id', 'main_table.log_id');
        return $this;
    }

    protected function _afterLoad()
    {
        parent::_afterLoad();
        foreach ($this->_items as $item) {
            if ($item->getData('log_id')) {
                $item->setId($item->getData('log_id'));
            }
        }
        return $this;
    }
}
