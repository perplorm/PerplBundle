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
* Resolving model controller arguments from route parameters.

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

## History and credits

PerplBundle continues [PropelBundle](https://github.com/propelorm/PropelBundle), started by William Durand in 2010 and developed by many contributors since, among them Kévin Gomez, Marc J. Schmidt, Toni Uebernickel, Gregor Harlan and Moritz Schroeder. Its Symfony 7 support comes from the [SkyFoxvn fork](https://github.com/SkyFoxvn/PropelBundle). The form, security and translation integration started out in Symfony's Propel1 bridge, which is why those files carry Symfony's copyright notice.

Thanks to everyone who worked on these projects. The full history is kept in this repository.

## License

Released under the MIT license, see [LICENSE](LICENSE).
