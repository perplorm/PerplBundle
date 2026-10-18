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
use Propel\Bundle\PropelBundle\DependencyInjection\PropelExtension;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

class PropelExtensionTest extends TestCase
{
    public function testServices(): void
    {
        $container = new ContainerBuilder(new ParameterBag([
            'kernel.debug' => false,
            'kernel.project_dir' => dirname(__DIR__, 2),
            'kernel.bundles' => [],
        ]));

        (new PropelExtension())->load([[
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
        ]], $container);

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
}
