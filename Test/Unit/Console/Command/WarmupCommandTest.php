<?php
declare(strict_types=1);

namespace Panth\CacheManager\Test\Unit\Console\Command;

use Magento\Framework\App\State as AppState;
use Magento\Framework\Exception\LocalizedException;
use Panth\CacheManager\Console\Command\WarmupCommand;
use Panth\CacheManager\Cron\WarmupCache;
use Panth\CacheManager\Helper\Data as ConfigHelper;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class WarmupCommandTest extends TestCase
{
    private function row(string $url, string $status, int $code, float $ms, string $error = ''): array
    {
        return [
            'store_id' => 1,
            'url' => $url,
            'page_type' => 'home',
            'status' => $status,
            'http_code' => $code,
            'response_time_ms' => $ms,
            'error' => $error,
        ];
    }

    private function warmerReturning(array $rows): WarmupCache
    {
        $warmer = $this->createStub(WarmupCache::class);
        $warmer->method('runWarmup')->willReturnCallback(
            static function (?callable $onResult = null) use ($rows): array {
                if ($onResult !== null) {
                    foreach ($rows as $row) {
                        $onResult($row);
                    }
                }
                return $rows;
            }
        );
        return $warmer;
    }

    private function enabledHelper(bool $enabled = true): ConfigHelper
    {
        $helper = $this->createStub(ConfigHelper::class);
        $helper->method('isWarmupEnabled')->willReturn($enabled);
        return $helper;
    }

    public function testCommandNameAndOption(): void
    {
        $command = new WarmupCommand(
            $this->createStub(WarmupCache::class),
            $this->createStub(AppState::class),
            $this->enabledHelper()
        );

        $this->assertSame('panth:cachemanager:warmup', $command->getName());
        $this->assertTrue($command->getDefinition()->hasOption('quiet-rows'));
        $this->assertFalse($command->getDefinition()->getOption('quiet-rows')->acceptValue());
    }

    public function testDisabledWarmupPrintsHintAndSkipsRun(): void
    {
        $warmer = $this->createMock(WarmupCache::class);
        $warmer->expects($this->never())->method('runWarmup');

        $appState = $this->createMock(AppState::class);
        $appState->expects($this->once())->method('setAreaCode')->with('frontend');

        $tester = new CommandTester(new WarmupCommand($warmer, $appState, $this->enabledHelper(false)));
        $exit = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exit);
        $this->assertStringContainsString('Cache warmup is disabled', $tester->getDisplay());
    }

    public function testAreaCodeAlreadySetIsIgnored(): void
    {
        $appState = $this->createStub(AppState::class);
        $appState->method('setAreaCode')->willThrowException(new LocalizedException(__('Area code is already set')));

        $tester = new CommandTester(new WarmupCommand($this->warmerReturning([]), $appState, $this->enabledHelper()));
        $exit = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $exit);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Starting cache warmup...', $display);
        $this->assertStringContainsString('No URLs were warmed', $display);
    }

    public function testAllSuccessfulRowsPrintedAndSucceed(): void
    {
        $rows = [
            $this->row('https://shop.test/', 'success', 200, 120.0),
            $this->row('https://shop.test/old', 'skipped', 301, 30.0),
        ];
        $tester = new CommandTester(
            new WarmupCommand($this->warmerReturning($rows), $this->createStub(AppState::class), $this->enabledHelper())
        );
        $exit = $tester->execute([]);
        $display = $tester->getDisplay();

        $this->assertSame(Command::SUCCESS, $exit);
        $this->assertStringContainsString('OK [200 120.0ms] https://shop.test/', $display);
        $this->assertStringContainsString('SKIP [301 30.0ms] https://shop.test/old', $display);
        $this->assertStringContainsString('Warmup complete: 1 OK, 0 failed, 1 skipped, avg 75.0 ms/req', $display);
    }

    public function testFailureRowShowsErrorAndReturnsFailure(): void
    {
        $rows = [
            $this->row('https://shop.test/', 'success', 200, 10.0),
            $this->row('https://shop.test/broken', 'failed', 0, 20.0, 'Connection refused'),
        ];
        $tester = new CommandTester(
            new WarmupCommand($this->warmerReturning($rows), $this->createStub(AppState::class), $this->enabledHelper())
        );
        $exit = $tester->execute([]);
        $display = $tester->getDisplay();

        $this->assertSame(Command::FAILURE, $exit);
        $this->assertStringContainsString('FAIL [0 20.0ms] https://shop.test/broken (Connection refused)', $display);
        $this->assertStringContainsString('Warmup complete: 1 OK, 1 failed, avg 15.0 ms/req', $display);
        $this->assertStringNotContainsString('skipped', $display);
    }

    public function testQuietRowsSuppressesPerUrlLines(): void
    {
        $warmer = $this->createMock(WarmupCache::class);
        $warmer->expects($this->once())
            ->method('runWarmup')
            ->with(null)
            ->willReturn([$this->row('https://shop.test/', 'success', 200, 5.0)]);

        $tester = new CommandTester(
            new WarmupCommand($warmer, $this->createStub(AppState::class), $this->enabledHelper())
        );
        $exit = $tester->execute(['--quiet-rows' => true]);
        $display = $tester->getDisplay();

        $this->assertSame(Command::SUCCESS, $exit);
        $this->assertStringNotContainsString('https://shop.test/', $display);
        $this->assertStringContainsString('Warmup complete: 1 OK, 0 failed, avg 5.0 ms/req', $display);
    }
}
