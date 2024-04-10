[![OpenSSF Scorecard](https://api.securityscorecards.dev/projects/github.com/f-lombardo/secrets-loader/badge)](https://securityscorecards.dev/viewer/?uri=github.com/f-lombardo/secrets-loader)
[![Quality Gate Status](https://sonarcloud.io/api/project_badges/measure?project=f-lombardo_secrets-loader&metric=alert_status)](https://sonarcloud.io/summary/new_code?id=f-lombardo_secrets-loader)

# Load secrets 
Automatically load secrets from SSM into environment variables when running with Bref.

This work is a fork of the [project](https://github.com/brefphp/secrets-loader) created by [Matthieu Napoli](https://github.com/mnapoli), who is also the creator of the amazing [Bref](https://bref.sh/) project.

I introduced here the ability to load parameters from Secrets Manager and to have an SSM parameter containing many application environment variables in ini format.

## Load secrets from Secrets Manager

This library replaces at runtime secrets read from AWS Secrets Manager. Those secrets can be both in JSON format or in plain text.

```yaml
provider:
    # ...
    environment:
        MY_PARAMETER: bref-secretsmanager:/my-app/my-parameter-in-plain-text
        MY_PARAMETER_JSON: bref-secretsmanager-json:/my-app/my-parameter-in-json
```

In this example the Bref Lambda function will see an environment variable `MY_PARAMETER` which value will be the content of secret `/my-app/my-parameter-in-plain-text`.
Secret pointed by `/my-app/my-parameter-in-json` should be a JSON string of the form:
```json
{
  "VAR1": "value1", 
  "VAR2": "value2"
}
```
The Lambda function will have access to two environment variables `VAR1=value1` and `VAR2=value2`.

## SSM parameter in .ini format

Migrating an existing complex Symfony application to Bref leads to having many environment variables defined in `serverless.yml`.
Instead of having a one to one mapping between lambda environment variables and SSM parameters, 
I suggest to have a single lambda environment variable with the special name `BREF_PARAMETER_STORE` that stores a string in ini format. 
That string will be expanded in many application environment variables.
For example a lambda could have the environment variable `BREF_PARAMETER_STORE=ssm:/some/parameter`. Data contained in that parameter could be:
```
VAR1=foo
VAR2=bar
```
The lambda execution runtime should then see `VAR1=foo` and `VAR2=bar` as environment variables.

This project is fully compatible with the behavior of the original library, whose documentation I report below.

## Usage following the original library

This library is fully compatible with the orginal one developed by Bref's author.
Read the Bref documentation: https://bref.sh/docs/environment/variables.html#secrets

It replaces (at runtime) the variables whose value starts with `bref-ssm:`. For example, you could set such a variable in `serverless.yml` like this:

```yaml
provider:
    # ...
    environment:
        MY_PARAMETER: bref-ssm:/my-app/my-parameter
```

In AWS Lambda, the `MY_PARAMETER` would be automatically replaced and would contain the value stored at `/my-app/my-parameter` in AWS SSM Parameters.

It could be also used to read a set of parameters from a SSM variable that contains a string in an INI format. 
For example, if there is an SSM parameter `/my-app/my-par-store` that contains this sting:
```ini
FOO=bar
BAR=baz
```
and we have this `severless.yml` configuration with the special variable `BREF_PARAMETER_STORE` set this way:
```yaml
provider:
    # ...
    environment:
      BREF_PARAMETER_STORE: /my-app/my-par-store
```
our lambda will see the these environment variables:
```shell
FOO=bar
BAR=baz
```

This feature is shipped as a separate package so that all its code and dependencies are not installed by default for all Bref users. Install this package if you want to use the feature.

## Notes for developers

In the [docker](/docker) directory you can find a `docker compose` project that allows the developing and testing of the application.
You can run it with
```bash
cd docker 
docker compose up -d
docker compose exec php bash
```
Last command leads you inside the PHP container where you can run tests and quality checks using the [quality.sh](scripts/quality.sh) script:
```shell
scripts/quality.sh
```

## Installation in your PHP project

```
composer require f-lombardo/secrets-loader
```

