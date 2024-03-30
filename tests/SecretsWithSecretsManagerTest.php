<?php declare(strict_types=1);

namespace Bref\Secrets\Test;

use AsyncAws\Ssm\SsmClient;
use Bref\Secrets\SecretManagerClientFactory;
use Bref\Secrets\Secrets;
use PHPUnit\Framework\TestCase;

class SecretsWithSecretsManagerTest extends TestCase
{
    use TestUtils;

    public function setUp(): void
    {
        if (file_exists(sys_get_temp_dir() . '/bref-secretsmanager.php')) {
            unlink(sys_get_temp_dir() . '/bref-secretsmanager.php');
        }
        if (file_exists(sys_get_temp_dir() . '/bref-ssm-parameters-store.json')) {
            unlink(sys_get_temp_dir() . '/bref-ssm-parameters-store.json');
        }
        putenv('SOME_VARIABLE');
        putenv('SOME_OTHER_VARIABLE');
        putenv('FOO');
        putenv('BAR');
    }

    public function testDecryptsEnvVariables(): void
    {
        $client = SecretManagerClientFactory::getClient();
        putenv('SOME_VARIABLE=bref-secretsmanager:/some/parameter');
        SecretsManagerTestUtils::createSecret($client, '/some/parameter', 'foobar');
        putenv('SOME_OTHER_VARIABLE=helloworld');

        // Sanity checks
        $this->assertSame('bref-secretsmanager:/some/parameter', getenv('SOME_VARIABLE'));
        $this->assertSame('helloworld', getenv('SOME_OTHER_VARIABLE'));

        Secrets::loadSecretEnvironmentVariables($this->mockSsmClient());

        $this->asserVarIsSet('foobar', 'SOME_VARIABLE');
        // Check that the other variable was not modified
        $this->assertSame('helloworld', getenv('SOME_OTHER_VARIABLE'));
    }

    public function testThrowsAClearErrorMessageOnMissingParameter(): void
    {
        $client = SecretManagerClientFactory::getClient();
        putenv('SOME_VARIABLE=bref-secretsmanager:/some/parameter');
        SecretsManagerTestUtils::deleteSecret($client, '/some/parameter');

        $this->expectExceptionMessage('The following Secrets Manager parameters could not be found: /some/parameter');

        Secrets::loadSecretEnvironmentVariables($this->mockSsmClient());
    }

    protected function mockSsmClient(): SsmClient
    {
        return $this->getMockBuilder(SsmClient::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getParameters'])
            ->getMock();
    }
}
