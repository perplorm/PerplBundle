<?php

/**
 * This file is part of the PropelBundle package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace Propel\Bundle\PropelBundle\Tests\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Propel\Bundle\PropelBundle\Command\BuildCommand;
use Propel\Bundle\PropelBundle\Command\ModelBuildCommand;
use Propel\Bundle\PropelBundle\DataFixtures\Dumper\DataDumperInterface;
use Propel\Bundle\PropelBundle\DataFixtures\Dumper\YamlDataDumper;
use Propel\Bundle\PropelBundle\DataFixtures\Loader\XmlDataLoader;
use Propel\Bundle\PropelBundle\DataFixtures\Loader\YamlDataLoader;
use Propel\Bundle\PropelBundle\DependencyInjection\PropelExtension;
use Propel\Bundle\PropelBundle\Logger\PropelLogger;
use Propel\Bundle\PropelBundle\Service\SchemaLocator;
use Propel\Runtime\Connection\ConnectionInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

class PropelExtensionTest extends TestCase
{
    public function testServices(): void
    {
        $container = $this->load([
            'database' => [
                'connections' => [
                    'default' => [
                        'adapter' => 'sqlite',
                        'dsn' => 'sqlite::memory:',
                        'user' => '',
                        'password' => '',
                    ],
                ],
            ],
        ]);

        // The service IDs documented in UPGRADE.md
        $services = [
            'propel.schema_locator',
            'propel.logger',
            'propel.data_collector',
            'propel.twig.extension.syntax',
            'form.type_guesser.propel',
            'propel.form.type.model',
            'propel.dumper.yaml',
            'propel.loader.yaml',
            'propel.loader.xml',
            'propel.security.user.provider',
            'propel.value_resolver',
            BuildCommand::class,
            ModelBuildCommand::class,
        ];
        foreach ($services as $id) {
            $this->assertTrue($container->hasDefinition($id), sprintf('Service "%s" is defined.', $id));
        }

        $this->assertSame('default', $container->getParameter('propel.dbal.default_connection'));
        $this->assertSame('sqlite::memory:', $container->getParameter('propel.configuration')['database']['connections']['default']['dsn']);
    }

    public function testConnectionServices(): void
    {
        $container = $this->load([
            'database' => [
                'connections' => [
                    'default' => ['adapter' => 'sqlite', 'dsn' => 'sqlite::memory:', 'user' => '', 'password' => ''],
                    'other' => ['adapter' => 'sqlite', 'dsn' => 'sqlite::memory:', 'user' => '', 'password' => ''],
                ],
            ],
        ]);

        foreach (['default', 'other'] as $name) {
            $definition = $container->getDefinition(sprintf('propel.%s_connection', $name));
            $this->assertTrue($definition->isPublic());
            $this->assertSame(ConnectionInterface::class, $definition->getClass());
            $this->assertSame([$name], $definition->getArguments());
            $this->assertSame('getWriteConnection', $definition->getFactory()[1]);
        }

        $this->assertSame('default', $container->getParameter('propel.dbal.default_connection'));
        $this->assertSame('propel.default_connection', (string) $container->getAlias('propel.connection'));
        $this->assertTrue($container->getAlias('propel.connection')->isPublic());
        $this->assertSame('propel.connection', (string) $container->getAlias(ConnectionInterface::class));
        $this->assertSame('propel.default_connection', (string) $container->getAlias(ConnectionInterface::class . ' $defaultConnection'));
        $this->assertSame('propel.other_connection', (string) $container->getAlias(ConnectionInterface::class . ' $otherConnection'));
    }

    public function testAutowiringAliases(): void
    {
        $container = $this->load([
            'database' => [
                'connections' => [
                    'default' => ['adapter' => 'sqlite', 'dsn' => 'sqlite::memory:', 'user' => '', 'password' => ''],
                ],
            ],
        ]);

        $aliases = [
            SchemaLocator::class => 'propel.schema_locator',
            PropelLogger::class => 'propel.logger',
            YamlDataDumper::class => 'propel.dumper.yaml',
            DataDumperInterface::class => 'propel.dumper.yaml',
            YamlDataLoader::class => 'propel.loader.yaml',
            XmlDataLoader::class => 'propel.loader.xml',
        ];
        foreach ($aliases as $alias => $id) {
            $this->assertSame($id, (string) $container->getAlias($alias));
            $this->assertFalse($container->getAlias($alias)->isDeprecated());
        }
    }

    public function testDeprecatedCommandIds(): void
    {
        $container = $this->load([
            'database' => [
                'connections' => [
                    'default' => ['adapter' => 'sqlite', 'dsn' => 'sqlite::memory:', 'user' => '', 'password' => ''],
                ],
            ],
        ]);

        $aliases = [
            'propel_bundle_propel.command.build_command' => BuildCommand::class,
            'propel_bundle_propel.command.model_build_command' => ModelBuildCommand::class,
        ];
        foreach ($aliases as $alias => $id) {
            $this->assertSame($id, (string) $container->getAlias($alias));
            $this->assertTrue($container->getAlias($alias)->isDeprecated());
            $this->assertSame(sprintf('The "%s" service alias is deprecated, use "%s" instead.', $alias, $id), $container->getAlias($alias)->getDeprecation($alias)['message']);
        }

        $this->assertCount(17, array_filter(array_keys($container->getAliases()), static fn (string $alias): bool => str_starts_with($alias, 'propel_bundle_propel.command.')));
    }

    public function testTheCommandsFallBackToTheConnectionNamedDefault(): void
    {
        $container = $this->load([
            'database' => [
                'connections' => [
                    'other' => ['adapter' => 'sqlite', 'dsn' => 'sqlite::memory:', 'user' => '', 'password' => ''],
                    'default' => ['adapter' => 'sqlite', 'dsn' => 'sqlite::memory:', 'user' => '', 'password' => ''],
                ],
            ],
        ]);

        // the runtime default is still the first connection
        $this->assertSame('default', $container->getParameter('propel.dbal.default_connection'));
        $this->assertSame('other', $container->getParameter('propel.configuration')['runtime']['defaultConnection']);
        $this->assertSame('propel.other_connection', (string) $container->getAlias('propel.connection'));
    }

    public function testDefaultConnectionIsTheFirstOne(): void
    {
        $container = $this->load([
            'database' => [
                'connections' => [
                    'first' => ['adapter' => 'sqlite', 'dsn' => 'sqlite::memory:', 'user' => '', 'password' => ''],
                    'second' => ['adapter' => 'sqlite', 'dsn' => 'sqlite::memory:', 'user' => '', 'password' => ''],
                ],
            ],
        ]);

        $this->assertSame('first', $container->getParameter('propel.dbal.default_connection'));
        $this->assertSame('first', $container->getParameter('propel.configuration')['runtime']['defaultConnection']);
        $this->assertSame('propel.first_connection', (string) $container->getAlias('propel.connection'));
    }

    public function testDefaultConnectionFollowsTheRuntimeConfiguration(): void
    {
        $container = $this->load([
            'runtime' => ['defaultConnection' => 'other'],
            'database' => [
                'connections' => [
                    'default' => ['adapter' => 'sqlite', 'dsn' => 'sqlite::memory:', 'user' => '', 'password' => ''],
                    'other' => ['adapter' => 'sqlite', 'dsn' => 'sqlite::memory:', 'user' => '', 'password' => ''],
                ],
            ],
        ]);

        $this->assertSame('other', $container->getParameter('propel.dbal.default_connection'));
        $this->assertSame('propel.other_connection', (string) $container->getAlias('propel.connection'));
    }

    /**
     * @param array<string, mixed> $config
     */
    private function load(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder(new ParameterBag([
            'kernel.debug' => false,
            'kernel.project_dir' => dirname(__DIR__, 2),
            'kernel.bundles' => [],
        ]));

        (new PropelExtension())->load([$config], $container);

        return $container;
    }
}
