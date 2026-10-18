<?php

/**
 * This file is part of the PropelBundle package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace Propel\Bundle\PropelBundle\Tests\DependencyInjection\Security\UserProvider;

use PHPUnit\Framework\TestCase;
use Propel\Bundle\PropelBundle\DependencyInjection\Security\UserProvider\PropelFactory;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class PropelFactoryTest extends TestCase
{
    public function testKey(): void
    {
        $factory = new PropelFactory('propel', 'propel.security.user.provider');

        $this->assertSame('propel', $factory->getKey());
    }

    public function testConfiguration(): void
    {
        $this->assertSame(['class' => 'App\\Model\\User', 'property' => null], $this->processConfiguration(['class' => 'App\\Model\\User']));

        $this->expectException(InvalidConfigurationException::class);
        $this->processConfiguration(['property' => 'email']);
    }

    public function testCreate(): void
    {
        $container = new ContainerBuilder();
        $factory = new PropelFactory('propel', 'propel.security.user.provider');

        $factory->create($container, 'app.user_provider', ['class' => 'App\\Model\\User', 'property' => 'email']);

        $definition = $container->getDefinition('app.user_provider');
        $this->assertInstanceOf(ChildDefinition::class, $definition);
        $this->assertSame('propel.security.user.provider', $definition->getParent());
        $this->assertSame(['App\\Model\\User', 'email'], $definition->getArguments());
    }

    private function processConfiguration(array $config): array
    {
        $treeBuilder = new TreeBuilder('propel');
        (new PropelFactory('propel', 'propel.security.user.provider'))->addConfiguration($treeBuilder->getRootNode());

        return (new Processor())->process($treeBuilder->buildTree(), [$config]);
    }
}
