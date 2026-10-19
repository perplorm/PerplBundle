PerplBundle
===========

[![CI](https://github.com/perplorm/PerplBundle/actions/workflows/ci.yml/badge.svg)](https://github.com/perplorm/PerplBundle/actions/workflows/ci.yml)

Integration of [Perpl](https://perplorm.github.io), the maintained fork of Propel2, and of [Propel2](https://github.com/propelorm/Propel2) itself in Symfony.

## Requirements

* PHP 8.2+
* Symfony 7.4 LTS or 8.x
* Perpl 2.10+, or Propel2 from its `master` branch (Symfony 7.4 only, until Propel2 supports Symfony 8)

## Installation

With Perpl:

```bash
composer require perplorm/perpl perplorm/perpl-bundle
```

With Propel2, which has no usable release on Packagist, require its `master` branch:

```bash
composer require propel/propel:dev-master perplorm/perpl-bundle
```

Coming from `propel/propel-bundle` or one of its forks? See [UPGRADE.md](UPGRADE.md).

Register the bundle if Symfony Flex did not do it for you:

```php
// config/bundles.php
return [
    // ...
    Propel\Bundle\PropelBundle\PropelBundle::class => ['all' => true],
];
```

## Configuration

```yaml
# config/packages/propel.yaml
propel:
    database:
        connections:
            default:
                adapter: mysql
                dsn: '%env(DATABASE_DSN)%'
                user: '%env(DATABASE_USER)%'
                password: '%env(DATABASE_PASSWORD)%'
```

Schemas are read from `config/*schema.xml`, from `propel/schema.xml` (see `propel.paths.schemaDir`) and from the `Resources/config/*schema.xml` files of your bundles.

## Features

* Generation of model classes based on XML schemas;
* Insertion of SQL statements;
* Runtime initialization through the bundle configuration;
* Migrations;
* Reverse engineering from an existing database;
* Integration with the Symfony Profiler;
* Loading SQL, YAML and XML fixtures;
* Creating and dropping databases;
* Integration with the Form, Security and Validator components;
* Resolving model controller arguments from route parameters;
* Injecting connections into services;
* Storing Symfony Messenger messages in a Propel connection.

### Controller arguments

Arguments marked with the `#[MapModel]` attribute are looked up by primary key, or by the other route parameters matching a column:

```php
use Propel\Bundle\PropelBundle\Attribute\MapModel;

#[Route('/books/{id}')]
public function show(#[MapModel] Book $book): Response

#[Route('/books/{slug}')]
public function showBySlug(#[MapModel] Book $book): Response
```

The attribute also configures the lookup:

```php
#[Route('/authors/{author_id}/books')]
public function books(#[MapModel(mapping: ['author_id' => 'id'], with: 'Book')] Author $author): Response
```

A nullable argument receives `null` when nothing is found, otherwise a 404 is returned.

### Connection services

Each configured connection is a service, `propel.<name>_connection`, holding its write connection, and `propel.connection` is the default one (`runtime.defaultConnection`, or the first connection). They are the objects Propel's service container hands out, created when first used. Autowiring injects the default connection for a `ConnectionInterface` argument, and a named connection for an argument named after it:

```php
use Propel\Runtime\Connection\ConnectionInterface;

public function __construct(
    private ConnectionInterface $connection,
    private ConnectionInterface $reportingConnection,
) {
}
```

### User provider

The `propel` user provider loads users through the Query class of the configured model. Symfony rehashes a password on login when the hashing configuration changed, and the provider hands that to the Query class when it implements `PasswordUpgraderInterface`, so the upgrade lives next to the other queries of the model:

```php
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

class UserQuery extends BaseUserQuery implements PasswordUpgraderInterface
{
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        $user->setPassword($newHashedPassword);
        $user->save();
    }
}
```

### Messenger transport

With [symfony/messenger](https://symfony.com/doc/current/messenger.html) installed, the `propel://` transport stores messages in a table of a Propel connection, the way Symfony's Doctrine transport does. The DSN names the connection:

```yaml
# config/packages/messenger.yaml
framework:
    messenger:
        transports:
            async: 'propel://default'
            failed: 'propel://default?queue_name=failed'
```

The options mirror those of the Doctrine transport, as query parameters of the DSN or under the `options` key:

| Option | Default | Description |
|---|---|---|
| `queue_name` | `default` | The queue the transport sends to and receives from. |
| `redeliver_timeout` | `3600` | Seconds after which a message that was received but not acknowledged is delivered again. |
| `table_name` | `messenger_message` | The table storing the messages. |
| `auto_setup` | `true` | Create the table when a query finds it missing. |

Timestamps are stored in UTC. On MySQL, MariaDB and PostgreSQL, workers lock the rows they receive with `FOR UPDATE SKIP LOCKED`, so several workers can consume the same queue. A worker receives one message per query, or as many as the `--fetch-size` option of `messenger:consume` asks for (Symfony 8.1+).

The table is created when it turns out to be missing, or by `bin/console messenger:setup-transports`, on MySQL, MariaDB, PostgreSQL and SQLite. When Propel migrations manage your schema, declare the table in your `schema.xml` instead and set `auto_setup` to `false`:

```xml
<table name="messenger_message">
    <column name="id" type="BIGINT" primaryKey="true" autoIncrement="true"/>
    <column name="body" type="CLOB" required="true"/>
    <column name="headers" type="LONGVARCHAR" required="true"/>
    <column name="queue_name" type="VARCHAR" size="190" required="true"/>
    <column name="created_at" type="DATETIME" required="true"/>
    <column name="available_at" type="DATETIME" required="true"/>
    <column name="delivered_at" type="DATETIME"/>
    <index>
        <index-column name="queue_name"/>
        <index-column name="available_at"/>
        <index-column name="delivered_at"/>
        <index-column name="id"/>
    </index>
</table>
```

### Long-running processes

Propel keeps every loaded object in an instance pool, which is what you want within a request or a message, but not across them. The bundle empties the pools of all configured connections whenever Symfony resets its services: between the messages of a Messenger worker, and between requests under runtimes such as FrankenPHP or RoadRunner. Pooling stays enabled.

## History and credits

PerplBundle continues [PropelBundle](https://github.com/propelorm/PropelBundle), started by William Durand in 2010 and developed by many contributors since, among them Kévin Gomez, Marc J. Schmidt, Toni Uebernickel, Gregor Harlan and Moritz Schroeder. Its Symfony 7 support comes from the [SkyFoxvn fork](https://github.com/SkyFoxvn/PropelBundle). The form, security and translation integration started out in Symfony's Propel1 bridge, which is why those files carry Symfony's copyright notice.

Thanks to everyone who worked on these projects. The full history is kept in this repository.

## License

Released under the MIT license, see [LICENSE](LICENSE).
