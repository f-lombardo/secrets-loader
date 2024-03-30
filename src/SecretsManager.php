<?php declare(strict_types=1);

namespace Bref\Secrets;

use AsyncAws\SecretsManager\SecretsManagerClient;

class SecretsManager
{
    use SecretManagerClientCreation;

    private SecretsManagerClient $client;

    public function __construct()
    {
        $this->client = self::getSecretsManagerClient();
    }

    public function getSecret(string $secretId, bool $isJson): mixed
    {
        $result = $this->client->getSecretValue([
            'SecretId' => $secretId,
        ]);

        $secret = $result->getSecretString() ?? base64_decode($result->getSecretBinary());

        return $isJson ? json_decode($secret, true) : $secret;
    }
}
