<?php declare(strict_types=1);

namespace Bref\Secrets;

use AsyncAws\SecretsManager\SecretsManagerClient;

class SecretManagerClientFactory
{
    public static function getClient(): SecretsManagerClient
    {
        return new SecretsManagerClient([
            'region' => $_ENV['AWS_REGION'] ?? $_ENV['AWS_DEFAULT_REGION'],
        ]);
    }
}
