<?php declare(strict_types=1);

namespace Bref\Secrets\Test;

use AsyncAws\Core\Test\ResultMockFactory;
use AsyncAws\Ssm\Result\GetParametersResult;
use AsyncAws\Ssm\SsmClient;
use AsyncAws\Ssm\ValueObject\Parameter;
use Bref\Secrets\Secrets;
use PHPUnit\Framework\TestCase;

class SecretsTest extends TestCase
{
    use TestUtils;

    public function setUp(): void
    {
        if (file_exists(sys_get_temp_dir() . '/bref-ssm.php')) {
            unlink(sys_get_temp_dir() . '/bref-ssm.php');
        }
        if (file_exists(sys_get_temp_dir() . '/bref-ssm-parameters-store.php')) {
            unlink(sys_get_temp_dir() . '/bref-ssm-parameters-store.php');
        }
        putenv('SOME_VARIABLE');
        putenv('SOME_OTHER_VARIABLE');
        putenv(Secrets::PARAMETER_STORE_VAR_NAME);
        putenv('FOO');
        putenv('BAR');
    }

    public function testDecryptsEnvVariables(): void
    {
        $this->setEnvValueWithSanityCheck('SOME_VARIABLE', 'bref-ssm:/some/parameter');
        $this->setEnvValueWithSanityCheck('SOME_OTHER_VARIABLE', 'helloworld');

        Secrets::loadSecretEnvironmentVariables($this->mockSsmClient());

        $this->asserVarIsSet('foobar', 'SOME_VARIABLE');
        // Check that the other variable was not modified
        $this->assertSame('helloworld', getenv('SOME_OTHER_VARIABLE'));
    }

    public function testCachesParametersToCallSSMOnlyOnce(): void
    {
        $this->setEnvValueWithSanityCheck('SOME_VARIABLE', 'bref-ssm:/some/parameter');

        // Call twice, the mock will assert that SSM was only called once
        $ssmClient = $this->mockSsmClient();
        Secrets::loadSecretEnvironmentVariables($ssmClient);
        Secrets::loadSecretEnvironmentVariables($ssmClient);

        $this->assertSame('foobar', getenv('SOME_VARIABLE'));
    }

    public function testDecryptsEnvVariablesFromParameterStore(): void
    {
        $this->setEnvValueWithSanityCheck(Secrets::PARAMETER_STORE_VAR_NAME, '/some/parameter');
        $this->setEnvValueWithSanityCheck('SOME_OTHER_VARIABLE', 'helloworld');

        $storeContents = <<<'END'
        FOO=bar
        BAR=baz
        END;

        Secrets::loadSecretEnvironmentVariables($this->mockSsmClient($storeContents));

        $this->asserVarIsSet('bar', 'FOO');
        $this->asserVarIsSet('baz', 'BAR');

        // Check that the other variable was not modified
        $this->assertSame('helloworld', getenv('SOME_OTHER_VARIABLE'));
    }

    public function testCachesParametersFromParametersStoreToCallSSMOnlyOnce(): void
    {
        $this->setEnvValueWithSanityCheck(Secrets::PARAMETER_STORE_VAR_NAME, '/some/parameter');
        $this->setEnvValueWithSanityCheck('SOME_OTHER_VARIABLE', 'helloworld');

        $storeContents = <<<'END'
        FOO=bar
        BAR=baz
        END;

        // Call twice, the mock will assert that SSM was only called once
        $ssmClient = $this->mockSsmClient($storeContents);
        Secrets::loadSecretEnvironmentVariables($ssmClient);
        putenv('FOO');
        putenv('BAR');
        Secrets::loadSecretEnvironmentVariables($ssmClient);

        $this->asserVarIsSet('bar', 'FOO');
        $this->asserVarIsSet('baz', 'BAR');
    }

    public function testThrowsAClearErrorMssageOnMissingPermissions(): void
    {
        $this->setEnvValueWithSanityCheck('SOME_VARIABLE', 'bref-ssm:/some/parameter');

        $ssmClient = $this->getMockBuilder(SsmClient::class)
            ->disableOriginalConstructor()
            ->getMock();
        $result = ResultMockFactory::createFailing(GetParametersResult::class, 400, 'User: arn:aws:sts::123456:assumed-role/app-dev-us-east-1-lambdaRole/app-dev-hello is not authorized to perform: ssm:GetParameters on resource: arn:aws:ssm:us-east-1:123456:parameter/app/test because no identity-based policy allows the ssm:GetParameters action');
        $ssmClient->method('getParameters')
            ->willReturn($result);

        $expected = preg_quote("Bref was not able to resolve secrets contained in environment variables from SSM because of a permissions issue with the SSM API. Did you add IAM permissions in serverless.yml to allow Lambda to access SSM? (docs: https://bref.sh/docs/environment/variables.html#at-deployment-time).\nFull exception message:", '/');
        $this->expectExceptionMessageMatches("/$expected .+/");
        Secrets::loadSecretEnvironmentVariables($ssmClient);
    }

    protected function mockSsmClient(string $parameterValue = 'foobar'): SsmClient
    {
        $ssmClient = $this->getMockBuilder(SsmClient::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getParameters'])
            ->getMock();

        $result = ResultMockFactory::create(GetParametersResult::class, [
            'Parameters' => [
                new Parameter([
                    'Name' => '/some/parameter',
                    'Value' => $parameterValue,
                ]),
            ],
        ]);

        $ssmClient->expects($this->once())
            ->method('getParameters')
            ->with([
                'Names' => ['/some/parameter'],
                'WithDecryption' => true,
            ])
            ->willReturn($result);

        return $ssmClient;
    }
}
