<?php
declare(strict_types=1);

namespace Panth\CacheManager\Model\Config\Backend;

use Magento\Framework\App\Config\Value;
use Magento\Framework\Exception\LocalizedException;

class CronExpression extends Value
{
    private const FIELD_RANGES = [
        [0, 59],
        [0, 23],
        [1, 31],
        [1, 12],
        [0, 6],
    ];

    private const NAMES = [
        3 => ['jan', 'feb', 'mar', 'apr', 'may', 'jun', 'jul', 'aug', 'sep', 'oct', 'nov', 'dec'],
        4 => ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'],
    ];

    public function beforeSave()
    {
        $value = trim((string) $this->getValue());
        $value = (string) preg_replace('/\s+/', ' ', $value);
        if ($value !== '' && !$this->isValidExpression($value)) {
            throw new LocalizedException(
                __(
                    'Warmup Schedule (Cron) "%1" is not a valid cron expression. Use five fields: minute hour day-of-month month day-of-week, for example 0 */6 * * *.',
                    $value
                )
            );
        }
        $this->setValue($value);
        return parent::beforeSave();
    }

    public function isValidExpression(string $expression): bool
    {
        $parts = explode(' ', trim($expression));
        if (count($parts) !== 5) {
            return false;
        }
        foreach ($parts as $index => $part) {
            if (!$this->isValidField(strtolower($part), $index)) {
                return false;
            }
        }
        return true;
    }

    private function isValidField(string $field, int $index): bool
    {
        foreach (explode(',', $field) as $item) {
            if (!$this->isValidItem($item, $index)) {
                return false;
            }
        }
        return true;
    }

    private function isValidItem(string $item, int $index): bool
    {
        if (!preg_match('/^(\*|[a-z0-9]+(?:-[a-z0-9]+)?)(?:\/(\d+))?$/', $item, $matches)) {
            return false;
        }
        if (isset($matches[2]) && (int) $matches[2] < 1) {
            return false;
        }
        if ($matches[1] === '*') {
            return true;
        }
        $bounds = explode('-', $matches[1]);
        $numbers = [];
        foreach ($bounds as $bound) {
            $number = $this->toNumber($bound, $index);
            if ($number === null) {
                return false;
            }
            $numbers[] = $number;
        }
        return count($numbers) === 1 || $numbers[0] <= $numbers[1];
    }

    private function toNumber(string $value, int $index): ?int
    {
        [$min, $max] = self::FIELD_RANGES[$index];
        if (ctype_digit($value)) {
            $number = (int) $value;
            return $number >= $min && $number <= $max ? $number : null;
        }
        if (isset(self::NAMES[$index])) {
            $position = array_search($value, self::NAMES[$index], true);
            if ($position !== false) {
                return $index === 3 ? $position + 1 : $position;
            }
        }
        return null;
    }
}
