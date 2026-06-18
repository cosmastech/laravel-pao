<?php

declare(strict_types=1);

namespace Tests\Fixtures\PhpCsFixer\Changes;

function length(string $value): int
{
    return strlen($value);
}
