<?php

namespace Propel\Bundle\PropelBundle\Tests\Functional;

use App\Model\Book;
use App\Model\BookQuery;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Tester\ApplicationTester;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Builds the model of the commands fixture against a SQLite database and checks that resetting the container empties
 * the instance pools.
 */
class InstancePoolResetTest extends TestCase
{
    private static string $projectDir;

    public static function setUpBeforeClass(): void
    {
        self::$projectDir = sys_get_temp_dir().'/perpl-bundle-tests/instance-pool-'.uniqid();

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

        foreach (['propel:model:build' => [], 'propel:sql:build' => [], 'propel:sql:insert' => ['--force' => true]] as $command => $parameters) {
            $application = new Application(new CommandTestKernel(self::$projectDir));
            $application->setAutoExit(false);
            $application->setCatchExceptions(false);

            $tester = new ApplicationTester($application);
            if ($tester->run(['command' => $command] + $parameters) !== 0) {
                throw new \RuntimeException(sprintf("%s failed:\n%s", $command, $tester->getDisplay()));
            }
        }

        // the console application registers error and exception handlers it does not remove
        while (set_error_handler(null) !== null) {
            restore_error_handler();
            restore_error_handler();
        }
        restore_error_handler();
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

    public function testResetEmptiesTheInstancePools(): void
    {
        $kernel = new CommandTestKernel(self::$projectDir);
        $kernel->boot();

        $book = new Book();
        $book->setTitle('Dune');
        $book->save();

        $this->assertSame($book, BookQuery::create()->findPk($book->getId()), 'A loaded object comes from the pool.');

        $kernel->getContainer()->get('services_resetter')->reset();

        $fresh = BookQuery::create()->findPk($book->getId());
        $this->assertNotSame($book, $fresh, 'After the reset, a query hydrates a new object.');
        $this->assertSame('Dune', $fresh->getTitle());
        $this->assertSame($fresh, BookQuery::create()->findPk($book->getId()), 'Pooling stays enabled.');
    }

    public function testResetWithNothingLoaded(): void
    {
        $kernel = new CommandTestKernel(self::$projectDir);
        $kernel->boot();

        $kernel->getContainer()->get('services_resetter')->reset();

        $this->assertSame(1, BookQuery::create()->count());
    }
}
