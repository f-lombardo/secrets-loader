<?php declare(strict_types=1);

namespace Bref\Secrets;

use AsyncAws\SecretsManager\SecretsManagerClient;

class SecretManagerClientCreation
{
    public static function getSecretsManagerClient(): SecretsManagerClient
    {
        return new SecretsManagerClient([
            'region' => $_ENV['AWS_REGION'] ?? $_ENV['AWS_DEFAULT_REGION'],
        ]);
    }
}
