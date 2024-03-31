<?php declare(strict_types=1);

namespace Bref\Secrets;

use AsyncAws\SecretsManager\Exception\ResourceNotFoundException;
use AsyncAws\Ssm\SsmClient;
use Closure;
use JsonException;
use RuntimeException;

class Secrets
{
    public const PARAMETER_STORE_VAR_NAME = 'BREF_PARAMETER_STORE';

    /**
     * Decrypt environment variables that are encrypted with AWS SSM.
     *
     * @param SsmClient|null $ssmClient To allow mocking in tests.
     * @throws JsonException
     */
    public static function loadSecretEnvironmentVariables(?SsmClient $ssmClient = null): void
    {
        /** @var array<string,string>|string|false $envVars */
        $envVars = getenv(local_only: true); // @phpstan-ignore-line PHPStan is wrong
        if (! is_array($envVars)) {
            return;
        }

        if (\array_key_exists(self::PARAMETER_STORE_VAR_NAME, $envVars)) {
            $parameterStoreNames = [self::PARAMETER_STORE_VAR_NAME => $envVars[self::PARAMETER_STORE_VAR_NAME]];
            self::loadParametersUsingCache($parameterStoreNames, 'bref-ssm-parameters-store', true, function () use ($ssmClient, $parameterStoreNames) {
                return self::readEnvFromParameterStore($parameterStoreNames[self::PARAMETER_STORE_VAR_NAME], $ssmClient);
            });
        }

        $ssmNames = self::extractNames($envVars, 'bref-ssm:');
        self::loadParametersUsingCache($ssmNames, 'bref-ssm', false, function () use ($ssmClient, $ssmNames) {
            return self::retrieveParametersFromSsm($ssmClient, $ssmNames);
        });

        $secretsNames = self::extractNames($envVars, 'bref-secretsmanager:');
        self::loadParametersUsingCache($secretsNames, 'bref-secretsmanager', false, function () use ($secretsNames) {
            return self::retrieveParametersFromSecrets($secretsNames, false);
        });

        $secretsJsonNames = self::extractNames($envVars, 'bref-secretsmanager-json:');
        self::loadParametersUsingCache($secretsJsonNames, 'bref-secretsmanager-json', true, function () use ($secretsJsonNames) {
            return self::retrieveParametersFromSecrets($secretsJsonNames, true);
        });
    }

    /**
     * @param string[] $ssmNames an array of names of ssn parameters
     * @return array<string, string> Map of parameter name -> value
     */
    private static function retrieveParametersFromSsm(?SsmClient $ssmClient, array $ssmNames): array
    {
        $ssm = $ssmClient ?? new SsmClient([
            'region' => $_ENV['AWS_REGION'] ?? $_ENV['AWS_DEFAULT_REGION'],
        ]);

        /** @var array<string, string> $parameters Map of parameter name -> value */
        $parameters = [];
        $parametersNotFound = [];

        // The API only accepts up to 10 parameters at a time, so we batch the calls
        foreach (array_chunk($ssmNames, 10) as $batchOfSsmNames) {
            try {
                $result = $ssm->getParameters([
                    'Names' => $batchOfSsmNames,
                    'WithDecryption' => true,
                ]);
                foreach ($result->getParameters() as $parameter) {
                    $parameters[$parameter->getName()] = $parameter->getValue();
                }
            } catch (RuntimeException $e) {
                if ($e->getCode() === 400) {
                    // Extra descriptive error message for the most common error
                    throw new RuntimeException(
                        "Bref was not able to resolve secrets contained in environment variables from SSM because of a permissions issue with the SSM API. Did you add IAM permissions in serverless.yml to allow Lambda to access SSM? (docs: https://bref.sh/docs/environment/variables.html#at-deployment-time).\nFull exception message: {$e->getMessage()}",
                        $e->getCode(),
                        $e,
                    );
                }
                throw $e;
            }
            $parametersNotFound = array_merge($parametersNotFound, $result->getInvalidParameters());
        }

        if (count($parametersNotFound) > 0) {
            throw new RuntimeException('The following SSM parameters could not be found: ' . implode(', ', $parametersNotFound));
        }

        return $parameters;
    }

    /**
     * @param string[] $names
     * @return array<string, string> Map of parameter name -> value
     */
    private static function retrieveParametersFromSecrets(array $names, bool $jsonFormat): array
    {
        $secretsManager = new SecretsManager;

        /** @var array<string, string> $parameters Map of aws parameter name -> value */
        $parameters = [];
        $parametersNotFound = [];

        foreach ($names as $name) {
            try {
                if ($jsonFormat) {
                    $parameters += $secretsManager->getSecret($name, $jsonFormat);
                } else {
                    $parameters[$name] = $secretsManager->getSecret($name, $jsonFormat);
                }
            } catch (ResourceNotFoundException $e) {
                $parametersNotFound[] = $name;
            }
        }

        if (count($parametersNotFound) > 0) {
            throw new RuntimeException('The following Secrets Manager parameters could not be found: ' . implode(', ', $parametersNotFound));
        }

        return $parameters;
    }

    /**
     * This method logs to stderr.
     *
     * It must only be used in a lambda environment since all error output will be logged.
     *
     * @param string $message The message to log
     */
    private static function logToStderr(string $message): void
    {
        file_put_contents('php://stderr', date('[c] ') . $message . PHP_EOL, FILE_APPEND);
    }

    private static function setEnvValue(bool|int|string $parameterValue, bool|int|string $envVar): void
    {
        $_SERVER[$envVar] = $_ENV[$envVar] = $parameterValue;
        putenv("$envVar=$parameterValue");
    }

    /**
     * @param string $parameterStoreName Name of the ssn variable that stores env var in ini format
     * @param SsmClient|null $ssmClient Client to use. If null it will be created, otherwise it's probably a mock for tests
     * @return array<string, string> Map of parameter name -> value
     */
    private static function readEnvFromParameterStore(string $parameterStoreName, ?SsmClient $ssmClient): array
    {
        $iniValues = self::retrieveParametersFromSsm($ssmClient, [$parameterStoreName])[$parameterStoreName];

        $values = parse_ini_string($iniValues);
        if ($values === false) {
            throw new \RuntimeException('Error parsing data from parameter store');
        }

        return $values;
    }

    /**
     * This function lists all env vars whose value start with $prefix
     * and returns them in an array varName -> valueWithoutPrefix
     *
     * @param  array<string,string> $envVars
     * @return array<string, string>
     */
    private static function extractNames(array $envVars, string $prefix): array
    {
        // Only consider environment variables that start with $prefix
        $envVarsToDecrypt = array_filter($envVars, function (string $value) use ($prefix): bool {
            return str_starts_with($value, $prefix);
        });
        if (empty($envVarsToDecrypt)) {
            return [];
        }

        // Extract the SSM parameter names by removing the prefix
        return array_map(function (string $value) use ($prefix): string {
            return substr($value, strlen($prefix));
        }, $envVarsToDecrypt);
    }

    /**
     *  Cache the parameters in a temp file.
     *  Why? Because on the function runtime, the PHP process might
     *  restart on every invocation (or on error), so we don't want to
     *  call SSM every time.
     *
     *  @param array<string, string> $awsNames
     *  @param Closure(): array<string, string> $paramResolver
     *  @throws JsonException
     * /
     */
    private static function loadParametersUsingCache(array $awsNames, string $cacheName, bool $multivalue, Closure $paramResolver): void
    {
        if (empty($awsNames)) {
            return;
        }

        $paramCache = new ParameterCache($cacheName);
        $parameters = $paramCache->readParametersFromCacheOr($paramResolver);

        foreach ($parameters as $parameterName => $parameterValue) {
            if ($multivalue) {
                self::setEnvValue($parameterValue, $parameterName);
            } else {
                $envVar = array_search($parameterName, $awsNames, true);
                self::setEnvValue($parameterValue, $envVar);
            }
        }

        // Only log once (when the cache was empty) else it might spam the logs in the function runtime
        // (where the process restarts on every invocation)
        if ($paramCache->functionActuallyCalled()) {
            if ($multivalue) {
                $message = "[Bref] Loaded these environment variables for $cacheName: " . implode(', ', array_keys($parameters));
            } else {
                $message = "[Bref] Loaded these environment variables for $cacheName: " . implode(', ', array_keys($awsNames));
            }
            self::logToStderr($message);
        }
    }
}
