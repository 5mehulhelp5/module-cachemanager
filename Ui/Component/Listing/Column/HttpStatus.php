<?php
declare(strict_types=1);

namespace Panth\CacheManager\Ui\Component\Listing\Column;

use Magento\Ui\Component\Listing\Columns\Column;

class HttpStatus extends Column
{
    public function prepareDataSource(array $dataSource)
    {
        if (!isset($dataSource['data']['items']) || !is_array($dataSource['data']['items'])) {
            return $dataSource;
        }
        $name = (string) $this->getData('name');
        foreach ($dataSource['data']['items'] as &$item) {
            $value = $item[$name] ?? null;
            if ($value === null || $value === '' || (int) $value === 0) {
                $item[$name] = (string) __('No response');
            }
        }
        unset($item);
        return $dataSource;
    }
}
