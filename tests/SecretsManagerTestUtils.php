<?php declare(strict_types=1);

namespace Bref\Secrets\Test;

use AsyncAws\SecretsManager\SecretsManagerClient;

class SecretsManagerTestUtils
{
    public static function createSecret(SecretsManagerClient $client, string $secretId, string $secret): void
    {
        self::deleteSecret($client, $secretId);

        $client->createSecret([
            'Name' => $secretId,
            'SecretString' => $secret,
        ]);
    }

    public static function deleteSecret(SecretsManagerClient $client, string $secretId): void
    {
        $client->deleteSecret(['SecretId' => $secretId, 'ForceDeleteWithoutRecovery' => true]);
    }
}
