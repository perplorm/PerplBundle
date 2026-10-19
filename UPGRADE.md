Upgrade to 1.0
==============

PerplBundle 1.0 continues `propel/propel-bundle` and its Symfony 7 forks, such as `skyfox/propel-bundle`. It keeps the bundle class, the PHP namespace, the `propel` configuration and the service IDs, so most applications only need to change `composer.json`.

## Composer

Replace the old bundle package:

```diff
-"skyfox/propel-bundle": "7.0.x-dev",
+"perplorm/perpl-bundle": "^1.0",
```

Symfony Flex removes `Propel\Bundle\PropelBundle\PropelBundle` from `config/bundles.php` when it uninstalls the old package, because both packages use the same bundle class. Check `config/bundles.php` after `composer update` and restore the line if it is gone.

Keep requiring Propel2 from its `master` branch, or switch to Perpl (see below). PerplBundle requires PHP 8.2 or later and Symfony 7.4 LTS or 8.x. Symfony 8 needs Perpl, because Propel2 does not support it yet.

## Unchanged

* The bundle class `Propel\Bundle\PropelBundle\PropelBundle` and the `Propel\Bundle\PropelBundle\` namespace.
* The `propel` configuration tree.
* The connection setup when the bundle boots, the `propel.logger` service and its `propel` Monolog channel.
* The services `propel.schema_locator`, `propel.logger`, `propel.data_collector`, `propel.twig.extension.syntax`, `form.type_guesser.propel`, `propel.form.type.model`, `propel.dumper.yaml`, `propel.loader.yaml`, `propel.loader.xml` and `propel.security.user.provider`, and their `*.class` parameters.
* The `propel:*` console commands, and the `@Propel` Twig namespace.
* Autowiring `SchemaLocator`, `PropelLogger`, `YamlDataDumper`, `YamlDataLoader` and `XmlDataLoader` by type. They now resolve to the shared services listed above; the bundle no longer registers every one of its classes as a service.
* The default connection: the one `runtime.defaultConnection` names, else the only one, else the one named `default`. With several connections and none named `default`, the first one is now the default where the bundle used to fail.

## Changed

* The SensioFrameworkExtraBundle ParamConverter (`propel.converter.propel.orm`) is replaced by a controller argument value resolver, as SensioFrameworkExtraBundle does not support Symfony 7. Mark the arguments to resolve with `#[MapModel]`; arguments with `propel_converter` route options are still resolved too. The resolver ignores all other arguments, so value resolvers of your own keep working.
* `PropelUserProvider::supportsClass()` also accepts subclasses of the user class.

## Deprecated

* The command service IDs `propel_bundle_propel.command.*`, such as `propel_bundle_propel.command.build_command`: the commands are registered under their class names, and the old IDs are aliases of those.
* `PropelUserProvider::loadUserByUsername()`: use `loadUserByIdentifier()`.
* Passing an array of options to the `UniqueObject` constraint: use named arguments, e.g. `new UniqueObject(fields: ['email'])`. It can now also be used as an attribute.

These keep working in 1.x and will be removed in 2.0.

## Moving to Perpl

Perpl replaces `propel/propel`, so switching is a separate step you can take whenever it suits you:

```diff
-"propel/propel": "dev-master",
+"perplorm/perpl": "^2.10",
```

Then rebuild your models and check the [Perpl documentation](https://perplorm.github.io) for changes that affect your own code. The bundle itself works the same with both.
