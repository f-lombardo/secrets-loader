<?php declare(strict_types=1);

namespace Bref\Secrets;

use Closure;
use JsonException;

class ParameterCache
{
    private bool $functionActuallyCalled = false;

    public function __construct(private string $cacheName)
    {
    }

    /**
     * Cache the parameters in a temp file.
     * Why? Because on the function runtime, the PHP process might
     * restart on every invocation (or on error), so we don't want to
     * call SSM every time.
     *
     * @param Closure(): array<string, bool|int|string> $paramResolver
     * @return array<string, string> Map of parameter name -> value
     * @throws JsonException
     */
    public function readParametersFromCacheOr(Closure $paramResolver): array
    {
        // Check in cache first
        $cacheFile = sys_get_temp_dir() . '/' . $this->cacheName . '.php';
        if (is_file($cacheFile)) {
            $parameters = json_decode(file_get_contents($cacheFile), true, 512, JSON_THROW_ON_ERROR);
            if (is_array($parameters)) {
                $this->functionActuallyCalled = false;
                return $parameters;
            }
        }

        // Not in cache yet: we resolve it
        $this->functionActuallyCalled = true;
        $parameters = $paramResolver();

        // Using json_encode instead of var_export due to possible security issues
        file_put_contents($cacheFile, json_encode($parameters, JSON_THROW_ON_ERROR));

        return $parameters;
    }

    public function functionActuallyCalled(): bool
    {
        return $this->functionActuallyCalled;
    }
}
