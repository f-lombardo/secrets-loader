<?php declare(strict_types=1);

namespace Bref\Secrets;

use AsyncAws\SecretsManager\SecretsManagerClient;

class SecretsManager
{
    use SecretManagerClientCreation;

    public static function getSecret(string $secretId, bool $isJson): mixed
    {
        $client = new SecretsManagerClient([
            'region' => $_ENV['AWS_REGION'] ?? $_ENV['AWS_DEFAULT_REGION'],
        ]);

        $result = $client->getSecretValue([
            'SecretId' => $secretId,
        ]);

        $secret = $result->getSecretString() ?? base64_decode($result->getSecretBinary());

        return $isJson ? json_decode($secret, true) : $secret;
    }
}
