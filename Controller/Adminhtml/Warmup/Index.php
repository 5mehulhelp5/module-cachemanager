<?php
declare(strict_types=1);

namespace Panth\CacheManager\Controller\Adminhtml\Warmup;

use Magento\Backend\App\Action;
use Magento\Framework\View\Result\PageFactory;

class Index extends Action
{
    public const ADMIN_RESOURCE = 'Panth_CacheManager::config';

    private PageFactory $pageFactory;

    public function __construct(Action\Context $context, PageFactory $pageFactory)
    {
        parent::__construct($context);
        $this->pageFactory = $pageFactory;
    }

    public function execute()
    {
        $page = $this->pageFactory->create();
        $page->setActiveMenu('Panth_CacheManager::warmup_log');
        $page->getConfig()->getTitle()->prepend(__('Cache Warmup Log'));
        return $page;
    }
}
