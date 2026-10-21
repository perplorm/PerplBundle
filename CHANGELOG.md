Changelog
=========

All notable changes are documented here. The project follows [Semantic Versioning](https://semver.org): minor releases add features and deprecations, major releases remove them.

## 1.0.0-RC5 (2026-10-21)

* `cache:clear` no longer fails when the cached container was compiled by an older version of the bundle, as happens when releases share a cache directory.

## 1.0.0-RC4 (2026-10-19)

* `propel:form:generate` writes form types that load on Symfony 7 and 8: their `buildForm()` and `configureOptions()` methods declare the `: void` return type, and they no longer set the obsolete `name` option.
* Require the `ext-pdo` and `ext-simplexml` extensions at runtime, and suggest `symfony/form`, `symfony/validator`, `symfony/twig-bundle` and `symfony/web-profiler-bundle` for the optional integrations.
* Log a warning on the application logger when the query logging cannot be enabled at boot, as when `runtime.logging` is on and a connection cannot be opened, instead of silently ignoring it. Errors while configuring the connections are no longer swallowed.

## 1.0.0-RC3 (2026-10-19)

* Register each connection as a service, `propel.<name>_connection`, with `propel.connection` for the default one, and autowire `ConnectionInterface` arguments to them.
* `runtime.defaultConnection` is always filled in: the configured one, else the first connection. The `propel.dbal.default_connection` parameter the commands use follows the configured one too, and still falls back to the connection named `default` when there is one.
* Autowire the bundle's main classes by type again: `SchemaLocator`, `PropelLogger`, `YamlDataDumper`, `YamlDataLoader` and `XmlDataLoader` resolve to their `propel.*` services.
* Keep the former command service IDs, `propel_bundle_propel.command.*`, as deprecated aliases of the commands.
* The user provider implements `PasswordUpgraderInterface` and delegates password upgrades to the model's Query class when that implements the interface.
* Let the Messenger transport receive several messages per query, as asked by `messenger:consume --fetch-size` in Symfony 8.1+.
* Empty the instance pools of all connections when Symfony resets its services, so Messenger workers and worker runtimes do not keep the objects of a previous message or request.

## 1.0.0-RC2 (2026-10-19)

* Add a Symfony Messenger transport, `propel://<connection>`, storing messages in a table of a Propel connection.

## 1.0.0-RC1 (2026-10-18)

First release as PerplBundle, continuing `propel/propel-bundle` and its Symfony 7 forks. See [UPGRADE.md](UPGRADE.md).

* Support Perpl 2.10+ alongside Propel2.
* Require PHP 8.2+ and Symfony 7.4 LTS or 8.x.
* Replace the SensioFrameworkExtraBundle ParamConverter with the `#[MapModel]` controller argument value resolver.
* Allow `UniqueObject` as an attribute, with named arguments.
* Configure services in PHP and register commands with `#[AsCommand]`.
* Follow the current Symfony bundle directory layout.
* Deprecate `PropelUserProvider::loadUserByUsername()` and passing an options array to `UniqueObject`.
* Generate the model classes of `config/*schema.xml` schemas under `src/`, like those of `propel/schema.xml`, instead of under a directory named after the namespace.
* Forget the collected queries between the requests of a long-running runtime.
* Follow the Perpl coding standard, without strict types until 2.0.
