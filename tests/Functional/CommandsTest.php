<?php

namespace Propel\Bundle\PropelBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\Depends;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Runs the commands against a schema and a SQLite database in a scratch project directory.
 *
 * The tests depend on each other, as each command builds on the files of the previous one.
 */
class CommandsTest extends TestCase
{
    private static string $projectDir;

    public static function setUpBeforeClass(): void
    {
        self::$projectDir = sys_get_temp_dir().'/perpl-bundle-tests/commands-'.uniqid();

        $filesystem = new Filesystem();
        $filesystem->mirror(__DIR__.'/Fixtures/commands', self::$projectDir);
        $filesystem->mkdir(self::$projectDir.'/var');

        // the generated model classes
        spl_autoload_register(static function (string $class): void {
            if (str_starts_with($class, 'App\\')) {
                $file = self::$projectDir.'/src/'.str_replace('\\', '/', substr($class, 4)).'.php';
                if (file_exists($file)) {
                    require $file;
                }
            }
        });
    }

    protected function tearDown(): void
    {
        // the console application registers exception handlers it does not remove
        while (set_exception_handler(null) !== null) {
            restore_exception_handler();
            restore_exception_handler();
        }
        restore_exception_handler();
    }

    public static function tearDownAfterClass(): void
    {
        if (!getenv('KEEP_SCRATCH')) {
            (new Filesystem())->remove(self::$projectDir);
        }
    }

    public function testSqlBuild(): void
    {
        $tester = $this->runCommand('propel:sql:build');

        $sql = self::$projectDir.'/propel/sql/default.sql';
        $this->assertFileExists($sql, $tester->getDisplay());
        $this->assertStringContainsString('CREATE TABLE [book]', file_get_contents($sql));
    }

    public function testModelBuild(): void
    {
        $tester = $this->runCommand('propel:model:build');

        $this->assertFileExists(self::$projectDir.'/src/Model/Book.php', $tester->getDisplay());
        $this->assertFileExists(self::$projectDir.'/src/Model/BookQuery.php');
        $this->assertFileExists(self::$projectDir.'/src/Model/Base/Book.php');
        $this->assertFileExists(self::$projectDir.'/src/Model/Map/BookTableMap.php');
        $this->assertFileExists(self::$projectDir.'/generated-conf/loadDatabase.php');
        $this->assertStringContainsString('namespace App\Model;', file_get_contents(self::$projectDir.'/src/Model/Book.php'));
    }

    public function testMigrationDiff(): void
    {
        $tester = $this->runCommand('propel:migration:diff');

        $migrations = glob(self::$projectDir.'/propel/migrations/PropelMigration_*.php');
        $this->assertCount(1, $migrations, $tester->getDisplay());
        $this->assertStringContainsString('CREATE TABLE [book]', file_get_contents($migrations[0]));
    }

    #[Depends('testMigrationDiff')]
    public function testMigrationStatusAndMigrate(): void
    {
        $tester = $this->runCommand('propel:migration:status');
        $this->assertStringContainsString('1 valid migration classes found', $tester->getDisplay());
        $this->assertStringContainsString('1 migration needs to be executed', $tester->getDisplay());

        $this->runCommand('propel:migration:migrate');
        $this->assertSame(['book', 'propel_migration'], $this->getTables());

        // the status command exits with 1 when there is nothing to migrate
        $tester = $this->runCommand('propel:migration:status', [], null);
        $this->assertStringContainsString('Nothing to migrate', $tester->getDisplay());
        $this->assertStringNotContainsString('needs to be executed', $tester->getDisplay());
    }

    #[Depends('testMigrationStatusAndMigrate')]
    public function testSqlInsertNeedsForce(): void
    {
        $tester = $this->runCommand('propel:sql:insert', [], 1);

        $this->assertStringContainsString('--force', $tester->getDisplay());
    }

    #[Depends('testSqlBuild')]
    #[Depends('testSqlInsertNeedsForce')]
    public function testSqlInsert(): void
    {
        $this->runCommand('propel:sql:insert', ['--force' => true]);

        $this->assertContains('book', $this->getTables());
        $this->assertSame(0, $this->countBooks());
    }

    #[Depends('testModelBuild')]
    #[Depends('testSqlInsert')]
    public function testFixturesLoad(): void
    {
        $tester = $this->runCommand('propel:fixtures:load', ['--yml' => true]);

        $this->assertStringContainsString('1 YML fixtures file loaded', $tester->getDisplay());
        $this->assertSame(2, $this->countBooks());
    }

    #[Depends('testFixturesLoad')]
    public function testFixturesDump(): void
    {
        $this->runCommand('propel:fixtures:dump');

        $dumps = glob(self::$projectDir.'/propel/fixtures/fixtures_*.yml');
        $this->assertCount(1, $dumps);
        $this->assertStringContainsString('Dune', file_get_contents($dumps[0]));
        $this->assertStringContainsString('Emma', file_get_contents($dumps[0]));
    }

    public function testDatabaseCreateNeedsADatabaseName(): void
    {
        $tester = $this->runCommand('propel:database:create', [], 1);

        $this->assertStringContainsString('No database name found', $tester->getDisplay());
    }

    /**
     * @param array<string, mixed> $parameters
     */
    private function runCommand(string $command, array $parameters = [], ?int $expectedStatus = 0): ApplicationTester
    {
        $kernel = new CommandTestKernel(self::$projectDir);
        $application = new Application($kernel);
        $application->setAutoExit(false);
        $application->setCatchExceptions(false);

        $tester = new ApplicationTester($application);
        $status = $tester->run(['command' => $command] + $parameters);

        if ($expectedStatus === null) {
            return $tester;
        }

        $this->assertSame($expectedStatus, $status, sprintf("%s exited with %d:\n%s", $command, $status, $tester->getDisplay()));

        return $tester;
    }

    /**
     * @return list<string>
     */
    private function getTables(): array
    {
        $pdo = new \PDO('sqlite:'.CommandTestKernel::getDatabaseFile(self::$projectDir));

        return $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%' ORDER BY name")->fetchAll(\PDO::FETCH_COLUMN);
    }

    private function countBooks(): int
    {
        $pdo = new \PDO('sqlite:'.CommandTestKernel::getDatabaseFile(self::$projectDir));

        return (int) $pdo->query('SELECT COUNT(*) FROM book')->fetchColumn();
    }
}
