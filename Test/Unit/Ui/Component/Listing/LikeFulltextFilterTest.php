<?php
declare(strict_types=1);

namespace Panth\CacheManager\Test\Unit\Ui\Component\Listing;

use Magento\Framework\Api\Filter;
use Magento\Framework\Data\Collection;
use Magento\Framework\Data\Collection\AbstractDb;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Panth\CacheManager\Ui\Component\Listing\LikeFulltextFilter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class LikeFulltextFilterTest extends TestCase
{
    /**
     * @var array
     */
    private array $quoted = [];

    private function filterWithValue($value): Filter
    {
        $filter = new Filter();
        $filter->setValue($value);
        return $filter;
    }

    private function connection(): AdapterInterface
    {
        $connection = $this->createStub(AdapterInterface::class);
        $connection->method('quoteIdentifier')
            ->willReturnCallback(static fn ($ident): string => '`' . $ident . '`');
        $connection->method('quoteInto')
            ->willReturnCallback(function ($text, $value): string {
                $this->quoted[] = $value;
                return str_replace('?', "'" . $value . "'", $text);
            });
        return $connection;
    }

    public function testNonDbCollectionIsIgnored(): void
    {
        $collection = $this->createMock(Collection::class);
        $collection->expects($this->never())->method($this->anything());

        (new LikeFulltextFilter(['url']))->apply($collection, $this->filterWithValue('abc'));
    }

    public function testNoColumnsMeansNoFilter(): void
    {
        $collection = $this->createMock(AbstractDb::class);
        $collection->expects($this->never())->method('getSelect');

        (new LikeFulltextFilter([123, null]))->apply($collection, $this->filterWithValue('abc'));
    }

    public static function blankValues(): array
    {
        return [
            'empty' => [''],
            'whitespace' => ['   '],
            'array' => [['x']],
            'null' => [null],
        ];
    }

    #[DataProvider('blankValues')]
    public function testBlankOrNonScalarValueSkipsFilter($value): void
    {
        $collection = $this->createMock(AbstractDb::class);
        $collection->expects($this->never())->method('getSelect');
        $collection->expects($this->never())->method('getConnection');

        (new LikeFulltextFilter(['url']))->apply($collection, $this->filterWithValue($value));
    }

    public function testBuildsOrConditionAcrossColumns(): void
    {
        $select = $this->createMock(Select::class);
        $select->expects($this->once())
            ->method('where')
            ->with("`url` LIKE '%home%' OR `page_type` LIKE '%home%'");

        $collection = $this->createStub(AbstractDb::class);
        $collection->method('getConnection')->willReturn($this->connection());
        $collection->method('getSelect')->willReturn($select);

        (new LikeFulltextFilter(['url', 'skip' => 5, 'page_type']))
            ->apply($collection, $this->filterWithValue('  home  '));

        $this->assertSame(['%home%', '%home%'], $this->quoted);
    }

    public function testEscapesWildcardsAndTruncatesLongInput(): void
    {
        $collection = $this->createStub(AbstractDb::class);
        $collection->method('getConnection')->willReturn($this->connection());
        $collection->method('getSelect')->willReturn($this->createStub(Select::class));

        $filter = new LikeFulltextFilter(['url']);
        $filter->apply($collection, $this->filterWithValue('50%_off\\'));
        $filter->apply($collection, $this->filterWithValue(str_repeat('a', 250)));

        $this->assertSame('%50\\%\\_off\\\\%', $this->quoted[0]);
        $this->assertSame('%' . str_repeat('a', 200) . '%', $this->quoted[1]);
    }

    public function testNumericValueIsAccepted(): void
    {
        $collection = $this->createStub(AbstractDb::class);
        $collection->method('getConnection')->willReturn($this->connection());
        $collection->method('getSelect')->willReturn($this->createStub(Select::class));

        (new LikeFulltextFilter(['http_status']))->apply($collection, $this->filterWithValue(404));

        $this->assertSame(['%404%'], $this->quoted);
    }
}
