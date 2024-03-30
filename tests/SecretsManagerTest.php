<?php declare(strict_types=1);

namespace Bref\Secrets\Test;

use Bref\Secrets\SecretManagerClientFactory;
use Bref\Secrets\SecretsManager;
use PHPUnit\Framework\TestCase;

class SecretsManagerTest extends TestCase
{
    public function testCanReadSecretsInJsonForm(): void
    {
        $secretId = 'MyTestSecret1';
        $secret = '{"username":"admin", "password":"xyz"}';

        $client = SecretManagerClientFactory::getSecretsManagerClient();

        SecretsManagerTestUtils::createSecret($client, $secretId, $secret);

        $secretValue = (new SecretsManager)->getSecret($secretId, true);

        $this->assertEquals('admin', $secretValue['username']);
        $this->assertEquals('xyz', $secretValue['password']);
    }

    public function testCanReadSecretsAsPlainStrings(): void
    {
        $secretId = 'MyTestSecret1';
        $secret = 'foo';

        $client = SecretManagerClientFactory::getSecretsManagerClient();

        SecretsManagerTestUtils::createSecret($client, $secretId, $secret);

        $secretValue = (new SecretsManager)->getSecret($secretId, false);

        $this->assertEquals($secret, $secretValue);
    }
}
