Changelog
=========

All notable changes are documented here. The project follows [Semantic Versioning](https://semver.org): minor releases add features and deprecations, major releases remove them.

## Unreleased

* Register each connection as a service, `propel.<name>_connection`, with `propel.connection` for the default one, and autowire `ConnectionInterface` arguments to them.
* The `propel.dbal.default_connection` parameter now holds the configured default connection instead of always `default`.
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
