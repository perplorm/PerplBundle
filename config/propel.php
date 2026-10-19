<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Propel\Bundle\PropelBundle\DataCollector\PropelDataCollector;
use Propel\Bundle\PropelBundle\DataFixtures\Dumper\DataDumperInterface;
use Propel\Bundle\PropelBundle\DataFixtures\Dumper\YamlDataDumper;
use Propel\Bundle\PropelBundle\DataFixtures\Loader\XmlDataLoader;
use Propel\Bundle\PropelBundle\DataFixtures\Loader\YamlDataLoader;
use Propel\Bundle\PropelBundle\Form\Type\ModelType;
use Propel\Bundle\PropelBundle\Form\TypeGuesser;
use Propel\Bundle\PropelBundle\Logger\PropelLogger;
use Propel\Bundle\PropelBundle\Service\InstancePoolResetter;
use Propel\Bundle\PropelBundle\Service\SchemaLocator;
use Propel\Bundle\PropelBundle\Twig\Extension\SyntaxExtension;
use Propel\Runtime\Connection\ConnectionInterface;
use Propel\Runtime\Propel;
use Propel\Runtime\ServiceContainer\ServiceContainerInterface;

return static function (ContainerConfigurator $container): void {
    $container->parameters()
        ->set('propel.schema_locator.class', SchemaLocator::class)
        ->set('propel.data_collector.class', PropelDataCollector::class)
        ->set('propel.logger.class', PropelLogger::class)
        ->set('propel.twig.extension.syntax.class', SyntaxExtension::class)
        ->set('form.type_guesser.propel.class', TypeGuesser::class)
        ->set('propel.form.type.model.class', ModelType::class)
        ->set('propel.dumper.yaml.class', YamlDataDumper::class)
        ->set('propel.loader.yaml.class', YamlDataLoader::class)
        ->set('propel.loader.xml.class', XmlDataLoader::class);

    $container->services()
        // Propel's own service container, which the bundle configures when it boots. The connection services are
        // created from it, so they are only opened when used.
        ->set('propel.service_container', ServiceContainerInterface::class)
            ->factory([Propel::class, 'getServiceContainer'])

        // The write connection of the default connection, see PropelExtension for the connection services
        ->alias(ConnectionInterface::class, 'propel.connection')

        ->set('propel.schema_locator', '%propel.schema_locator.class%')
            ->public()
            ->args([service('service_container'), service('file_locator'), '%propel.configuration%'])

        ->set('propel.logger', '%propel.logger.class%')
            ->public()
            ->args([service('logger')->nullOnInvalid(), service('debug.stopwatch')->nullOnInvalid()])
            ->tag('monolog.logger', ['channel' => 'propel'])
            ->tag('kernel.reset', ['method' => 'reset'])

        ->set('propel.instance_pool_resetter', InstancePoolResetter::class)
            ->public()
            ->args([abstract_arg('the configured connection names')])
            ->tag('kernel.reset', ['method' => 'reset'])

        ->set('propel.data_collector', '%propel.data_collector.class%')
            ->private()
            ->args([service('propel.logger')])
            ->tag('data_collector', ['template' => '@Propel/Collector/propel', 'id' => 'propel'])

        ->set('propel.twig.extension.syntax', '%propel.twig.extension.syntax.class%')
            ->tag('twig.extension')

        ->set('form.type_guesser.propel', '%form.type_guesser.propel.class%')
            ->tag('form.type_guesser')

        ->set('propel.form.type.model', '%propel.form.type.model.class%')
            ->tag('form.type', ['alias' => 'model'])

        ->set('propel.dumper.yaml', '%propel.dumper.yaml.class%')
            ->public()
            ->args(['%kernel.project_dir%', '%propel.configuration%'])

        ->set('propel.loader.yaml', '%propel.loader.yaml.class%')
            ->public()
            ->args(['%kernel.project_dir%', '%propel.configuration%', service('faker.generator')->nullOnInvalid()])

        ->set('propel.loader.xml', '%propel.loader.xml.class%')
            ->public()
            ->args(['%kernel.project_dir%', '%propel.configuration%'])

        // autowiring by class, as when the bundle autowired all of its classes
        ->alias(SchemaLocator::class, 'propel.schema_locator')
        ->alias(PropelLogger::class, 'propel.logger')
        ->alias(YamlDataDumper::class, 'propel.dumper.yaml')
        ->alias(DataDumperInterface::class, 'propel.dumper.yaml')
        ->alias(YamlDataLoader::class, 'propel.loader.yaml')
        ->alias(XmlDataLoader::class, 'propel.loader.xml');
};
