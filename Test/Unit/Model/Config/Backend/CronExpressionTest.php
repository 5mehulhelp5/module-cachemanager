<?php
declare(strict_types=1);

namespace Panth\CacheManager\Test\Unit\Model\Config\Backend;

use Magento\Framework\App\Cache\TypeListInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Event\ManagerInterface;
use Magento\Framework\Exception\LocalizedException;
use Magento\Framework\Model\Context;
use Magento\Framework\Registry;
use Panth\CacheManager\Model\Config\Backend\CronExpression;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CronExpressionTest extends TestCase
{
    private CronExpression $model;

    protected function setUp(): void
    {
        $context = $this->createStub(Context::class);
        $context->method('getEventDispatcher')->willReturn($this->createStub(ManagerInterface::class));
        $this->model = new CronExpression(
            $context,
            $this->createStub(Registry::class),
            $this->createStub(ScopeConfigInterface::class),
            $this->createStub(TypeListInterface::class)
        );
    }

    public static function validProvider(): array
    {
        return [
            'default' => ['0 */6 * * *'],
            'daily' => ['0 2 * * *'],
            'every minute' => ['* * * * *'],
            'list and range' => ['0,30 8-18 * * 1-5'],
            'step on range' => ['0-30/10 * * * *'],
            'month and day names' => ['15 3 1 jan,jul mon-fri'],
            'upper case names' => ['0 0 * DEC SUN'],
            'max bounds' => ['59 23 31 12 6'],
        ];
    }

    #[DataProvider('validProvider')]
    public function testValidExpressions(string $expression): void
    {
        $this->assertTrue($this->model->isValidExpression($expression));
    }

    public static function invalidProvider(): array
    {
        return [
            'text' => ['abc * * * *'],
            'four fields' => ['0 */6 * *'],
            'six fields' => ['0 0 */6 * * *'],
            'minute out of range' => ['60 * * * *'],
            'hour out of range' => ['0 24 * * *'],
            'day zero' => ['0 0 0 * *'],
            'month thirteen' => ['0 0 * 13 *'],
            'weekday seven' => ['0 0 * * 7'],
            'zero step' => ['*/0 * * * *'],
            'reversed range' => ['30-10 * * * *'],
            'day name in month field' => ['0 0 * mon *'],
            'empty list item' => ['0, * * * *'],
            'negative' => ['-1 * * * *'],
        ];
    }

    #[DataProvider('invalidProvider')]
    public function testInvalidExpressions(string $expression): void
    {
        $this->assertFalse($this->model->isValidExpression($expression));
    }

    public function testBeforeSaveNormalisesWhitespace(): void
    {
        $this->model->setValue("  0   2 *\t* *  ");
        $this->model->beforeSave();
        $this->assertSame('0 2 * * *', $this->model->getValue());
    }

    public function testBeforeSaveAllowsEmptyValueSoCronDefaultApplies(): void
    {
        $this->model->setValue('   ');
        $this->model->beforeSave();
        $this->assertSame('', $this->model->getValue());
    }

    public function testBeforeSaveRejectsInvalidExpression(): void
    {
        $this->model->setValue('every six hours');
        $this->expectException(LocalizedException::class);
        $this->expectExceptionMessage('is not a valid cron expression');
        $this->model->beforeSave();
    }
}
