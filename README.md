Automatically load secrets from SSM into environment variables when running with Bref.

This work is a fork of the [project](https://github.com/brefphp/secrets-loader) created by [Matthieu Napoli](https://github.com/mnapoli).

I introduced here the ability to have an SSM parameter containing many application environment variables in ini format.

Migrating an existing complex Symfony application to Bref leads to having many environment variables defined in `serverless.yml`.
Instead of having a one to one mapping between lambda environment variables and SSM parameters, 
I suggest to have a single lambda environment variable with the special name `BREF_PARAMETER_STORE` that stores a string in ini format. 
That string will be expanded in many application environment variables.
For example a lambda could have the environment variable `BREF_PARAMETER_STORE=ssm:/some/parameter`. Data contained in that parameter could be:
```
VAR1=foo
VAR2=bar
```
The lambda execution runtime should then see `VAR1=foo` and `VAR2=bar` as environment variables. I suggest to use the `ssm:` prefix in front of the value of the parameter to let a future implementation using Secrets Manager.

This project is fully compatible with the behavior of the original library, whose documentation I report below.

---

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
      BREF_PARAMETER_STORE: ssm:/my-app/my-par-store
```
our lambda will see the these environment variables:
```shell
FOO=bar
BAR=baz
```

This feature is shipped as a separate package so that all its code and dependencies are not installed by default for all Bref users. Install this package if you want to use the feature.

## Running tests and quality checks

You can run tests and quality checks on this code by using the [quality.sh](scripts/quality.sh) script:
```shell
scripts/quality.sh
```

## Installation

```
composer require f-lombardo/secrets-loader
```

## Usage

Read the Bref documentation: https://bref.sh/docs/environment/variables.html#secrets

