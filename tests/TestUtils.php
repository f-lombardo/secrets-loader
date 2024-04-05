<?php declare(strict_types=1);

namespace Bref\Secrets\Test;

trait TestUtils
{
    protected function asserVarIsSet(string|int|bool $value, string $varName): void
    {
        $this->assertEquals($value, getenv($varName));
        $this->assertEquals($value, $_SERVER[$varName]);
        $this->assertEquals($value, $_ENV[$varName]);
    }
}
