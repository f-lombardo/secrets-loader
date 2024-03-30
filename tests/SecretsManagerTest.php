<?php declare(strict_types=1);

namespace Bref\Secrets\Test;

use AsyncAws\SecretsManager\SecretsManagerClient;
use Bref\Secrets\SecretManagerClientCreation;
use Bref\Secrets\SecretsManager;
use PHPUnit\Framework\TestCase;

class SecretsManagerTest extends TestCase
{
    use SecretManagerClientCreation;

    public function testCanReadSecretsInJsonForm(): void
    {
        $secretId = 'MyTestSecret1';
        $secret = '{"username":"admin", "password":"xyz"}';

        $client = $this->getSecretsManagerClient();

        $this->createSecret($client, $secretId, $secret);

        $secretValue = SecretsManager::getSecret($secretId, true);

        $this->assertEquals('admin', $secretValue['username']);
        $this->assertEquals('xyz', $secretValue['password']);
    }

    public function testCanReadSecretsAsPlainStrings(): void
    {
        $secretId = 'MyTestSecret1';
        $secret = 'foo';

        $client = $this->getSecretsManagerClient();

        $this->createSecret($client, $secretId, $secret);

        $secretValue = SecretsManager::getSecret($secretId, false);

        $this->assertEquals($secret, $secretValue);
    }

    private function createSecret(SecretsManagerClient $client, string $secretId, string $secret): void
    {
        $this->deleteSecret($client, $secretId);

        $client->createSecret([
            'Name' => $secretId,
            'SecretString' => $secret,
        ]);
    }

    private function deleteSecret(SecretsManagerClient $client, string $secretId): void
    {
        $client->deleteSecret(['SecretId' => $secretId, 'ForceDeleteWithoutRecovery' => true]);
    }
}
