<?php declare(strict_types=1);

namespace Bref\Secrets\Test;

trait TestUtils
{
    public function setEnvValueWithSanityCheck(string $varName, string $value): void
    {
        putenv($varName . '=' . $value);
        $this->assertSame($value, getenv($varName));
    }

    protected function asserVarIsSet(string|int|bool $value, string $varName): void
    {
        $this->assertEquals($value, getenv($varName));
        $this->assertEquals($value, $_SERVER[$varName]);
        $this->assertEquals($value, $_ENV[$varName]);
    }
}
