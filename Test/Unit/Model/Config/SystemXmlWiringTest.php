<?php
declare(strict_types=1);

namespace Panth\CacheManager\Test\Unit\Model\Config;

use Panth\CacheManager\Model\Config\Backend\CronExpression;
use Panth\CacheManager\Model\Config\Source\LogPageType;
use Panth\CacheManager\Model\Config\Source\WarmupStatus;
use Panth\CacheManager\Ui\Component\Listing\Column\HttpStatus;
use Panth\CacheManager\Ui\Component\Listing\Column\Status;
use PHPUnit\Framework\TestCase;

class SystemXmlWiringTest extends TestCase
{
    private function load(string $relative): \SimpleXMLElement
    {
        $path = dirname(__DIR__, 4) . '/' . $relative;
        $this->assertTrue(is_file($path), $relative . ' missing');
        $xml = simplexml_load_string((string) file_get_contents($path));
        $this->assertInstanceOf(\SimpleXMLElement::class, $xml);
        return $xml;
    }

    private function field(\SimpleXMLElement $xml, string $group, string $field): \SimpleXMLElement
    {
        $nodes = $xml->xpath(sprintf(
            '//section[@id="panth_cachemanager"]/group[@id="%s"]/field[@id="%s"]',
            $group,
            $field
        ));
        $this->assertCount(1, $nodes, $group . '/' . $field);
        return $nodes[0];
    }

    public function testScheduleFieldIsValidatedOnSave(): void
    {
        $field = $this->field($this->load('etc/adminhtml/system.xml'), 'warmup', 'warmup_schedule');
        $this->assertSame(CronExpression::class, trim((string) $field->backend_model));
    }

    public function testPagesMultiselectCanBeCleared(): void
    {
        $field = $this->field($this->load('etc/adminhtml/system.xml'), 'warmup', 'warmup_pages');
        $this->assertSame('1', trim((string) $field->can_be_empty));
    }

    public function testConcurrentRequestsValidationMatchesHelperCap(): void
    {
        $field = $this->field($this->load('etc/adminhtml/system.xml'), 'warmup', 'concurrent_requests');
        $classes = preg_split('/\s+/', trim((string) $field->validate));
        $this->assertContains('validate-digits', $classes);
        $this->assertContains('digits-range-1-50', $classes);
    }

    public function testChildFieldsAlsoDependOnModuleEnabled(): void
    {
        $xml = $this->load('etc/adminhtml/system.xml');
        $children = [
            'warmup' => ['warmup_schedule', 'warmup_pages', 'concurrent_requests', 'redirect_status'],
            'invalidation' => ['invalidate_on_product_save', 'invalidate_on_category_save', 'invalidate_on_cms_save'],
        ];
        foreach ($children as $group => $fields) {
            foreach ($fields as $id) {
                $depends = [];
                foreach ($this->field($xml, $group, $id)->depends->field as $dependency) {
                    $depends[(string) $dependency['id']] = trim((string) $dependency);
                }
                $this->assertSame('1', $depends['panth_cachemanager/general/enabled'] ?? null, $group . '/' . $id);
                $this->assertCount(2, $depends, $group . '/' . $id);
            }
        }
    }

    public function testGridColumnsUseLabelledRenderers(): void
    {
        $xml = $this->load('view/adminhtml/ui_component/panth_cachemanager_warmup_listing.xml');

        $status = $xml->xpath('//column[@name="status"]')[0];
        $this->assertSame(Status::class, (string) $status['class']);
        $this->assertSame(WarmupStatus::class, (string) $status->settings->options['class']);
        $this->assertSame('select', trim((string) $status->settings->filter));
        $this->assertSame('ui/grid/cells/html', trim((string) $status->settings->bodyTmpl));

        $pageType = $xml->xpath('//column[@name="page_type"]')[0];
        $this->assertSame(LogPageType::class, (string) $pageType->settings->options['class']);
        $this->assertSame('select', trim((string) $pageType->settings->filter));

        $http = $xml->xpath('//column[@name="http_status"]')[0];
        $this->assertSame(HttpStatus::class, (string) $http['class']);
    }
}
