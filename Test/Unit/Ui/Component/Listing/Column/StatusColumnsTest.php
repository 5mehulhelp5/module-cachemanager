<?php
declare(strict_types=1);

namespace Panth\CacheManager\Test\Unit\Ui\Component\Listing\Column;

use Magento\Framework\Escaper;
use Magento\Framework\View\Element\UiComponent\ContextInterface;
use Magento\Framework\View\Element\UiComponentFactory;
use Panth\CacheManager\Model\Config\Source\WarmupStatus;
use Panth\CacheManager\Ui\Component\Listing\Column\HttpStatus;
use Panth\CacheManager\Ui\Component\Listing\Column\Status;
use PHPUnit\Framework\TestCase;

class StatusColumnsTest extends TestCase
{
    private function createStatusColumn(): Status
    {
        $escaper = $this->createStub(Escaper::class);
        $escaper->method('escapeHtml')->willReturnCallback(
            static fn ($value): string => htmlspecialchars((string) $value, ENT_QUOTES)
        );
        return new Status(
            $this->createStub(ContextInterface::class),
            $this->createStub(UiComponentFactory::class),
            new WarmupStatus(),
            $escaper,
            [],
            ['name' => 'status']
        );
    }

    private function createHttpStatusColumn(): HttpStatus
    {
        return new HttpStatus(
            $this->createStub(ContextInterface::class),
            $this->createStub(UiComponentFactory::class),
            [],
            ['name' => 'http_status']
        );
    }

    public function testStatusRendersLabelledSeverityBadges(): void
    {
        $result = $this->createStatusColumn()->prepareDataSource(['data' => ['items' => [
            ['status' => 'success'],
            ['status' => 'failed'],
            ['status' => 'skipped'],
            ['status' => 'pending'],
        ]]]);
        $items = $result['data']['items'];

        $this->assertSame('<span class="grid-severity-notice"><span>Success</span></span>', $items[0]['status']);
        $this->assertSame('<span class="grid-severity-critical"><span>Failed</span></span>', $items[1]['status']);
        $this->assertSame('<span class="grid-severity-minor"><span>Skipped</span></span>', $items[2]['status']);
        $this->assertSame('<span class="grid-severity-minor"><span>Pending</span></span>', $items[3]['status']);
    }

    public function testStatusNormalisesCaseAndEscapesUnknownValues(): void
    {
        $result = $this->createStatusColumn()->prepareDataSource(['data' => ['items' => [
            ['status' => ' FAILED '],
            ['status' => '<b>x</b>'],
            [],
        ]]]);
        $items = $result['data']['items'];

        $this->assertSame('<span class="grid-severity-critical"><span>Failed</span></span>', $items[0]['status']);
        $this->assertStringNotContainsString('<b>', $items[1]['status']);
        $this->assertStringContainsString('&lt;b&gt;x&lt;/b&gt;', $items[1]['status']);
        $this->assertSame('<span class="grid-severity-minor"><span></span></span>', $items[2]['status']);
    }

    public function testColumnsLeaveDataSourceWithoutItemsUntouched(): void
    {
        $source = ['data' => ['totalRecords' => 0]];
        $this->assertSame($source, $this->createStatusColumn()->prepareDataSource($source));
        $this->assertSame($source, $this->createHttpStatusColumn()->prepareDataSource($source));
    }

    public function testHttpStatusShowsNoResponseForZeroOrMissingCode(): void
    {
        $result = $this->createHttpStatusColumn()->prepareDataSource(['data' => ['items' => [
            ['http_status' => '0'],
            ['http_status' => null],
            ['http_status' => ''],
            [],
            ['http_status' => '200'],
            ['http_status' => '404'],
        ]]]);
        $values = array_map(static fn (array $item) => (string) ($item['http_status'] ?? ''), $result['data']['items']);

        $this->assertSame(['No response', 'No response', 'No response', 'No response', '200', '404'], $values);
    }
}
