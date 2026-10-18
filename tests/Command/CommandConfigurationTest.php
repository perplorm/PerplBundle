<?php

/**
 * This file is part of the PropelBundle package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license    MIT License
 */

namespace Propel\Bundle\PropelBundle\Tests\Command;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Propel\Bundle\PropelBundle\Command\BuildCommand;
use Propel\Bundle\PropelBundle\Command\DatabaseCreateCommand;
use Propel\Bundle\PropelBundle\Command\DatabaseDropCommand;
use Propel\Bundle\PropelBundle\Command\DatabaseReverseCommand;
use Propel\Bundle\PropelBundle\Command\FixturesDumpCommand;
use Propel\Bundle\PropelBundle\Command\FixturesLoadCommand;
use Propel\Bundle\PropelBundle\Command\FormGenerateCommand;
use Propel\Bundle\PropelBundle\Command\GraphvizGenerateCommand;
use Propel\Bundle\PropelBundle\Command\MigrationDiffCommand;
use Propel\Bundle\PropelBundle\Command\MigrationDownCommand;
use Propel\Bundle\PropelBundle\Command\MigrationMigrateCommand;
use Propel\Bundle\PropelBundle\Command\MigrationStatusCommand;
use Propel\Bundle\PropelBundle\Command\MigrationUpCommand;
use Propel\Bundle\PropelBundle\Command\ModelBuildCommand;
use Propel\Bundle\PropelBundle\Command\SqlBuildCommand;
use Propel\Bundle\PropelBundle\Command\SqlInsertCommand;
use Propel\Bundle\PropelBundle\Command\TableDropCommand;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class CommandConfigurationTest extends TestCase
{
    public static function commandProvider(): array
    {
        return [
            [DatabaseCreateCommand::class, 'propel:database:create', false],
            [DatabaseDropCommand::class, 'propel:database:drop', false],
            [TableDropCommand::class, 'propel:table:drop', false],
            [FormGenerateCommand::class, 'propel:form:generate', true],
            [FixturesLoadCommand::class, 'propel:fixtures:load', false],
            [FixturesDumpCommand::class, 'propel:fixtures:dump', false],
            [SqlBuildCommand::class, 'propel:sql:build', true],
            [SqlInsertCommand::class, 'propel:sql:insert', false],
            [ModelBuildCommand::class, 'propel:model:build', true],
            [GraphvizGenerateCommand::class, 'propel:graphviz:generate', true],
            [DatabaseReverseCommand::class, 'propel:database:reverse', true],
            [MigrationDiffCommand::class, 'propel:migration:diff', true],
            [MigrationDownCommand::class, 'propel:migration:down', true],
            [MigrationMigrateCommand::class, 'propel:migration:migrate', true],
            [MigrationUpCommand::class, 'propel:migration:up', true],
            [MigrationStatusCommand::class, 'propel:migration:status', true],
        ];
    }

    #[DataProvider('commandProvider')]
    public function testNameAndPlatformOption(string $commandClass, string $name, bool $hasPlatformOption): void
    {
        $command = new $commandClass(new ContainerBuilder());

        $this->assertSame($name, $command->getName());
        $this->assertSame($hasPlatformOption, $command->getDefinition()->hasOption('platform'), sprintf('"%s" has a platform option.', $name));
    }

    public function testBuildHub(): void
    {
        $this->assertSame('propel:build', (new BuildCommand())->getName());
    }
}
