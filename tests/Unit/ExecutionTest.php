<?php

declare(strict_types=1);

use Laravel\Pao\Execution;

it('detects windows composer binary proxies after the php executable', function (): void {
    $method = new ReflectionMethod(Execution::class, 'binaryName');

    $binary = $method->invoke(null, [
        'C:\\php\\php.exe',
        'vendor\\bin\\php-cs-fixer.bat',
        'check',
    ]);

    expect($binary)->toBe('php-cs-fixer');
});
