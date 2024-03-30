<?php declare(strict_types=1);

namespace Bref\Secrets\Test;

trait TestUtils
{
    protected function asserVarIsSet(string $value, string $varName): void
    {
        $this->assertSame($value, getenv($varName));
        $this->assertSame($value, $_SERVER[$varName]);
        $this->assertSame($value, $_ENV[$varName]);
    }
}
