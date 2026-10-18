Changelog
=========

All notable changes are documented here. The project follows [Semantic Versioning](https://semver.org): minor releases add features and deprecations, major releases remove them.

## 1.0.0 (unreleased)

First release as PerplBundle, continuing `propel/propel-bundle` and its Symfony 7 forks. See [UPGRADE.md](UPGRADE.md).

* Support Perpl 2.10+ alongside Propel2.
* Require PHP 8.2+ and Symfony 7.4 LTS or 8.x.
* Replace the SensioFrameworkExtraBundle ParamConverter with the `#[MapModel]` controller argument value resolver.
* Allow `UniqueObject` as an attribute, with named arguments.
* Configure services in PHP and register commands with `#[AsCommand]`.
* Follow the current Symfony bundle directory layout.
* Deprecate `PropelUserProvider::loadUserByUsername()` and passing an options array to `UniqueObject`.
* Follow the Perpl coding standard.
