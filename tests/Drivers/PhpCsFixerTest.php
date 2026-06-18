<?php

declare(strict_types=1);

function copyPhpCsFixerFixture(): string
{
    $source = dirname(__DIR__).'/Fixtures/PhpCsFixer/changes';
    $target = sys_get_temp_dir().'/pao_php_cs_fixer_'.bin2hex(random_bytes(8));

    mkdir($target.'/src', 0777, true);
    copy($source.'/.php-cs-fixer.php', $target.'/.php-cs-fixer.php');
    copy($source.'/src/NeedsFix.php', $target.'/src/NeedsFix.php');

    return $target;
}

function removePhpCsFixerFixture(string $path): void
{
    @unlink($path.'/src/NeedsFix.php');
    @rmdir($path.'/src');
    @unlink($path.'/.php-cs-fixer.php');
    @rmdir($path);
}

it('outputs json for clean code', function (): void {
    $process = runPhpCsFixer('tests/Fixtures/PhpCsFixer/clean/.php-cs-fixer.php');

    $output = decodeOutput($process);

    expect($process->getExitCode())->toBe(0)
        ->and($output['tool'])->toBe('php-cs-fixer')
        ->and($output['result'])->toBe('passed')
        ->and($output)->not->toHaveKey('files')
        ->and($process->getErrorOutput())->toBe('');
});

it('outputs json for code with changes in check mode', function (): void {
    $process = runPhpCsFixer('tests/Fixtures/PhpCsFixer/changes/.php-cs-fixer.php');

    $output = decodeOutput($process);

    expect($process->getExitCode())->not->toBe(0)
        ->and($output['tool'])->toBe('php-cs-fixer')
        ->and($output['result'])->toBe('failed')
        ->and($output['files'])->toHaveCount(1)
        ->and($output['files'][0]['path'])->toEndWith('NeedsFix.php')
        ->and($output['files'][0]['fixers'])->toContain('native_function_invocation')
        ->and($output)->not->toHaveKey('memory')
        ->and($process->getErrorOutput())->toBe('');
});

it('outputs passed json after fixing code', function (): void {
    $fixture = copyPhpCsFixerFixture();

    try {
        $process = runPhpCsFixer($fixture.'/.php-cs-fixer.php', command: 'fix');

        $output = decodeOutput($process);

        expect($process->getExitCode())->toBe(0)
            ->and($output['tool'])->toBe('php-cs-fixer')
            ->and($output['result'])->toBe('passed')
            ->and($output['files'])->toHaveCount(1)
            ->and($output['files'][0]['path'])->toEndWith('NeedsFix.php')
            ->and($output['files'][0]['fixers'])->toContain('native_function_invocation')
            ->and(file_get_contents($fixture.'/src/NeedsFix.php'))->toContain('return \strlen($value);')
            ->and($process->getErrorOutput())->toBe('');
    } finally {
        removePhpCsFixerFixture($fixture);
    }
});

it('passes through normal output without agent', function (): void {
    $process = runPhpCsFixer('tests/Fixtures/PhpCsFixer/changes/.php-cs-fixer.php', withAgent: false);

    expect($process->getOutput())->not->toContain('"tool"')
        ->and($process->getOutput())->toContain('NeedsFix.php');
});
