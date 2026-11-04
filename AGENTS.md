# AGENTS.md

This file provides guidance to coding agents working with code in this repository.

## What this is

`perplorm/perpl-bundle`: the Symfony bundle integrating Perpl (the maintained fork of Propel2) and Propel2 itself. It continues `propel/propel-bundle` and keeps its bundle class, the `Propel\Bundle\PropelBundle\` namespace, the `propel` configuration tree and the service IDs. Supports PHP 8.2+, Symfony 7.4 LTS and 8.x.

## Commands

```bash
composer install                       # dev dependencies install Perpl
vendor/bin/phpunit                     # all tests
vendor/bin/phpunit tests/Form/TypeGuesserTest.php
vendor/bin/phpunit --filter testModelBuild
vendor/bin/phpstan analyse             # level 6, src/ only
composer cs-check                      # phpcs (Perpl/Spryker standard), src/ and config/ only
composer cs-fix                        # phpcbf
```

PHPUnit fails on any deprecation, notice or warning (`failOnDeprecation`, etc.).

`tests/Functional/CommandsTest.php` runs the real `propel:*` commands against SQLite in a scratch project under the system temp dir; its tests depend on each other in order. Set `KEEP_SCRATCH=1` to keep the scratch directory for inspection.

To check against Propel2 instead of Perpl, as CI does:

```bash
composer remove --dev --no-update perplorm/perpl
composer update
vendor/bin/phpunit
```

CI matrix: PHP 8.2–8.5 × Symfony 7.4/8 × perpl/propel (Symfony 8 needs PHP 8.4 and Perpl only). PHPStan runs against both ORMs.

## Dual ORM compatibility

Everything in 1.x must work with both `propel/propel` (dev-master) and `perplorm/perpl` ^2.10. The code keeps using the `Propel\` namespaces (Perpl aliases them). Where the two diverge, branch at runtime with `method_exists`/`class_exists` (see `src/Util/ColumnTypeName.php`, `tests/TestCase.php::buildSchema()`), and add a narrowly scoped `ignoreErrors` entry in `phpstan.neon` with a comment when one branch is unreachable. Perpl-only APIs and breaking changes belong to the next major; in 1.x, deprecate first (see `UPGRADE.md`'s Deprecated section) and remove in 2.0. Symfony 7.4 vs 8.x differences are handled the same way.

## Architecture

- **`PropelExtension`** (`src/DependencyInjection/`) processes the `propel` config (`Configuration.php`), stores the whole tree as the `propel.configuration` parameter, resolves `runtime.defaultConnection` (configured, else first connection) and `propel.dbal.default_connection` for commands (configured, else a connection named `default`, else the runtime default), loads the service files in `config/*.php`, and registers one `propel.<name>_connection` service per connection plus the `propel.connection` alias and named autowiring aliases. `config/messenger.php` is loaded only when symfony/messenger is installed.
- **`PropelBundle::boot()`** configures Propel's global `StandardServiceContainer` from `propel.configuration` (connection managers, primary/replica, `loadDatabase.php` from `paths.loaderScriptDir`) and enables query logging via `propel.logger` when `runtime.logging` is on. Connection services are factories on `Propel::getServiceContainer()`, so they open lazily. `build()` registers the `propel` security user provider factory.
- **Commands** (`src/Command/`): registered by `config/services.php` from `*Command.php`, with deprecated `propel_bundle_propel.command.*` aliases. `AbstractCommand` copies all located schemas (`SchemaLocator`: `config/*schema.xml`, `paths.schemaDir`, bundles' `Resources/config/*schema.xml`) into `<cache>/propel/`, rewriting `package` attributes from namespaces, and writes `<cache>/propel/propel.json` from the bundle config. Most commands extend `WrappedCommand`, which then instantiates the corresponding Propel/Perpl generator command and runs it with `--schema-dir`/`--config-dir` pointing at that cache dir. Bundle-only config keys that Propel's own config tree rejects (e.g. `runtime.logging`, `options.ATTR_TIMEOUT`) must be stripped in `createPropelConfigurationFile()`. `AppBundle` is a pseudo-bundle so bundle-oriented code paths work for the bundle-less `App\` layout.
- **Integrations**, each wired by its own `config/*.php`: Form (`ModelType`, `TypeGuesser`, translation types), Security (`PropelUserProvider`, delegating password upgrades to the Query class), Validator (`UniqueObject`), controller argument resolver (`#[MapModel]`, also honours legacy `propel_converter` route options), profiler (`PropelDataCollector`, `PanelController`, templates under `templates/` exposed as `@Propel`), Messenger `propel://` transport (`src/Messenger/Transport/`, modelled on Symfony's Doctrine transport), fixtures loaders/dumpers, and `InstancePoolResetter` (tagged `kernel.reset`, clears instance pools between requests/messages).
- **Tests**: unit tests build containers by hand (`tests/TestCase.php`) and generate model classes in memory with Propel's `QuickBuilder`; functional tests boot real kernels from `tests/Functional/*Kernel.php`. Hand-written model fixtures live in `tests/Fixtures/`. Tests are not covered by phpcs.

## Conventions

- Code style is enforced by phpcs: fully qualified `use function`/`use const` imports for global functions and constants, docblocks with FQCN types. Files inherited from PropelBundle and the Symfony Propel1 bridge keep their original copyright headers and `@author` tags.
- Every user-facing change gets an entry in `CHANGELOG.md`; changes affecting migration from `propel/propel-bundle` go in `UPGRADE.md`. README documents features.
- Keep commits small and self-contained with a one-line message (CONTRIBUTING.md).
