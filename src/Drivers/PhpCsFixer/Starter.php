<?php

declare(strict_types=1);

namespace Laravel\Pao\Drivers\PhpCsFixer;

use Laravel\Pao\Drivers\Starter as BaseStarter;
use Laravel\Pao\UserFilters\CaptureFilter;

/**
 * @internal
 *
 * @codeCoverageIgnore
 */
final class Starter extends BaseStarter
{
    /**
     * @var array<int, string>
     */
    private array $argv = [];

    public function name(): string
    {
        return 'php-cs-fixer';
    }

    public function start(): void
    {
        /** @var array<int, string> $argv */
        $argv = $_SERVER['argv'];
        $this->argv = $argv;

        if (! $this->supportsCommand($argv)) {
            return;
        }

        $this->registerNullFilter();
        $this->silenceStderr();

        $argv = $this->ensureFormatJson($argv);
        $argv = $this->ensureNoProgress($argv);
        $argv = $this->ensureVerbose($argv);

        $_SERVER['argv'] = $argv;
        $GLOBALS['argv'] = $argv;
        $this->argv = $argv;

        $this->silenceStdout();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function parse(): ?array
    {
        $captured = trim(CaptureFilter::output());

        CaptureFilter::reset();

        if ($captured === '') {
            return null;
        }

        $start = strpos($captured, '{');

        if ($start !== false && $start > 0) {
            $captured = substr($captured, $start);
        }

        /** @var array<string, mixed>|null $data */
        $data = json_decode($captured, associative: true);

        if (! is_array($data) || ! is_array($data['files'] ?? null)) {
            return [
                'result' => 'failed',
                'raw' => [$captured],
            ];
        }

        $files = $this->files($data['files']);

        /** @var array<string, mixed> $result */
        $result = [
            'result' => $files !== [] && $this->isCheckMode() ? 'failed' : 'passed',
        ];

        if ($files !== []) {
            $result['files'] = $files;
        }

        return $result;
    }

    /**
     * @param  array<int, string>  $argv
     */
    private function supportsCommand(array $argv): bool
    {
        $command = $this->command($argv);

        return $command === null || in_array($command, ['check', 'fix'], true);
    }

    /**
     * @param  array<int, string>  $argv
     */
    private function command(array $argv): ?string
    {
        $skipNext = false;

        foreach (array_slice($argv, 1) as $arg) {
            if ($skipNext) {
                $skipNext = false;

                continue;
            }

            if ($arg === '--') {
                return null;
            }

            if ($this->optionRequiresValue($arg)) {
                $skipNext = true;

                continue;
            }

            if ($this->isBinaryArgument($arg)) {
                continue;
            }

            if (str_starts_with($arg, '-')) {
                continue;
            }

            return $arg;
        }

        return null;
    }

    private function isBinaryArgument(string $arg): bool
    {
        $binary = basename(str_replace('\\', '/', $arg));

        foreach (['.bat', '.cmd', '.exe'] as $extension) {
            if (str_ends_with($binary, $extension)) {
                $binary = substr($binary, 0, -strlen($extension));

                break;
            }
        }

        return in_array($binary, ['php-cs-fixer', 'php-cs-fixer.phar'], true);
    }

    private function optionRequiresValue(string $arg): bool
    {
        return in_array($arg, [
            '--allow-unsupported-php-version',
            '--allow-risky',
            '--cache-file',
            '--config',
            '--format',
            '--path-mode',
            '--rules',
            '--show-progress',
            '--using-cache',
        ], true);
    }

    /**
     * @param  array<int, string>  $argv
     * @return array<int, string>
     */
    private function ensureFormatJson(array $argv): array
    {
        $filtered = [];
        $skipNext = false;

        foreach ($argv as $arg) {
            if ($skipNext) {
                $skipNext = false;

                continue;
            }

            if (str_starts_with($arg, '--format=')) {
                continue;
            }

            if ($arg === '--format') {
                $skipNext = true;

                continue;
            }

            $filtered[] = $arg;
        }

        $filtered[] = '--format=json';

        return $filtered;
    }

    /**
     * @param  array<int, string>  $argv
     * @return array<int, string>
     */
    private function ensureNoProgress(array $argv): array
    {
        $filtered = [];
        $skipNext = false;

        foreach ($argv as $arg) {
            if ($skipNext) {
                $skipNext = false;

                continue;
            }

            if (str_starts_with($arg, '--show-progress=')) {
                continue;
            }

            if ($arg === '--show-progress') {
                $skipNext = true;

                continue;
            }

            $filtered[] = $arg;
        }

        $filtered[] = '--show-progress=none';

        return $filtered;
    }

    /**
     * @param  array<int, string>  $argv
     * @return array<int, string>
     */
    private function ensureVerbose(array $argv): array
    {
        $filtered = [];
        $verbose = false;

        foreach ($argv as $arg) {
            if (in_array($arg, ['--quiet', '-q'], true)) {
                continue;
            }

            if (in_array($arg, ['--verbose', '-v', '-vv', '-vvv'], true)) {
                $verbose = true;
            }

            $filtered[] = $arg;
        }

        if (! $verbose) {
            $filtered[] = '-v';
        }

        return $filtered;
    }

    private function isCheckMode(): bool
    {
        if ($this->command($this->argv) === 'check') {
            return true;
        }

        if (in_array('--dry-run', $this->argv, true)) {
            return true;
        }

        return in_array('-n', $this->argv, true);
    }

    /**
     * @return list<array{path: string, fixers?: list<string>}>
     */
    private function files(mixed $files): array
    {
        if (! is_array($files)) {
            return [];
        }

        $result = [];

        foreach ($files as $file) {
            if (! is_array($file)) {
                continue;
            }

            if (! is_string($file['name'] ?? null)) {
                continue;
            }

            if ($file['name'] === '') {
                continue;
            }

            $entry = [
                'path' => $file['name'],
            ];

            $fixers = $this->fixers($file['appliedFixers'] ?? null);

            if ($fixers !== []) {
                $entry['fixers'] = $fixers;
            }

            $result[] = $entry;
        }

        return $result;
    }

    /**
     * @return list<string>
     */
    private function fixers(mixed $fixers): array
    {
        if (! is_array($fixers)) {
            return [];
        }

        return array_values(array_filter(
            $fixers,
            fn (mixed $fixer): bool => is_string($fixer) && $fixer !== '',
        ));
    }
}
