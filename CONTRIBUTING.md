Contributing
============

Bug reports and pull requests are welcome.

## Compatibility

1.x supports both Propel2 and Perpl, and keeps backward compatibility within the major version: deprecate first, remove in the next major. Perpl-only APIs and breaking changes go to the next major version.

## Running the checks

The development dependencies install Perpl:

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse
composer cs-check
```

The code follows the [Perpl coding standard](https://github.com/perplorm/perpl/blob/main/phpcs.xml); `composer cs-fix` fixes most violations.

To check against Propel2 instead, as CI does:

```bash
composer remove --dev --no-update perplorm/perpl
composer update
vendor/bin/phpunit
```

## Commits

Keep each pull request focused, and each commit small and self-contained, with a one-line message.
