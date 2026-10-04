<?php
declare(strict_types=1);

namespace Panth\CacheManager\Ui\Component\Listing\Column;

use Magento\Framework\Escaper;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Magento\Ui\Component\Listing\Columns\Column;
use Panth\CacheManager\Model\Config\Source\WarmupStatus;

class Status extends Column
{
    private const SEVERITY = [
        WarmupStatus::SUCCESS => 'grid-severity-notice',
        WarmupStatus::FAILED => 'grid-severity-critical',
        WarmupStatus::SKIPPED => 'grid-severity-minor',
        WarmupStatus::PENDING => 'grid-severity-minor',
    ];

    public function __construct(
        ContextInterface $context,
        UiComponentFactory $uiComponentFactory,
        private readonly WarmupStatus $statusSource,
        private readonly Escaper $escaper,
        array $components = [],
        array $data = []
    ) {
        parent::__construct($context, $uiComponentFactory, $components, $data);
    }

    public function prepareDataSource(array $dataSource)
    {
        if (!isset($dataSource['data']['items']) || !is_array($dataSource['data']['items'])) {
            return $dataSource;
        }
        $name = (string) $this->getData('name');
        $labels = $this->statusSource->toArray();
        foreach ($dataSource['data']['items'] as &$item) {
            $value = strtolower(trim((string) ($item[$name] ?? '')));
            $label = isset($labels[$value]) ? (string) $labels[$value] : ucfirst($value);
            $class = self::SEVERITY[$value] ?? 'grid-severity-minor';
            $item[$name] = sprintf(
                '<span class="%s"><span>%s</span></span>',
                $class,
                $this->escaper->escapeHtml($label)
            );
        }
        unset($item);
        return $dataSource;
    }
}
