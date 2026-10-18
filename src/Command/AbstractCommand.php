<?php

/**
 * This file is part of the PropelBundle package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license MIT License
 */

namespace Propel\Bundle\PropelBundle\Command;

use InvalidArgumentException;
use Propel\Bundle\PropelBundle\AppBundle;
use Propel\Bundle\PropelBundle\Service\SchemaLocator;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use function array_merge;
use function array_unshift;
use function explode;
use function file_put_contents;
use function implode;
use function in_array;
use function json_encode;
use function key;
use function preg_match;
use function realpath;
use function simplexml_load_file;
use function sprintf;
use function str_replace;
use function stripos;
use function strlen;
use function strpos;
use function strrpos;
use function substr;
use function trim;
use function urlencode;
use const DIRECTORY_SEPARATOR;
use const JSON_PRETTY_PRINT;

/**
 * @author Kévin Gomez <contact@kevingomez.fr>
 */
abstract class AbstractCommand extends Command
{
    use FormattingHelpers;

    protected ?string $cacheDir = null;

    protected ?BundleInterface $bundle = null;

    protected InputInterface $input;

    protected OutputInterface $output;

    private ContainerInterface $container;

    /**
     * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
     * @param string|null $name
     */
    public function __construct(ContainerInterface $container, $name = null)
    {
        $this->container = $container;

        parent::__construct($name);
    }

    /**
     * {@inheritdoc}
     */
    protected function initialize(InputInterface $input, OutputInterface $output): void
    {
        $this->input = $input;
        $this->output = $output;
        $this->cacheDir = $this->getKernel()->getCacheDir() . '/propel';

        if ($input->hasArgument('bundle') && !empty($input->getArgument('bundle')) && substr($input->getArgument('bundle'), 0, 1) === '@') {
            $this->bundle = $this
                ->getContainer()
                ->get('kernel')
                ->getBundle(substr($input->getArgument('bundle'), 1));
        }
    }

    /**
     * @return \Symfony\Component\DependencyInjection\ContainerInterface
     */
    public function getContainer(): ContainerInterface
    {
        return $this->container;
    }

    /**
     * Create all the files needed by Propel's commands.
     *
     * @return void
     */
    protected function setupBuildTimeFiles(): void
    {
        $fs = new Filesystem();
        $fs->mkdir($this->cacheDir);

        // collect all schemas
        $this->copySchemas($this->getKernel(), $this->cacheDir);

        // propel.json
        $this->createPropelConfigurationFile($this->cacheDir . '/propel.json');
    }

    /**
     * @param \Symfony\Component\HttpKernel\KernelInterface $kernel The application kernel.
     * @param string $cacheDir The directory in which the schemas will
     *                                  be copied.
     *
     * @throws \RuntimeException
     *
     * @return void
     */
    protected function copySchemas(KernelInterface $kernel, string $cacheDir): void
    {
        $filesystem = new Filesystem();

        /** @var array<string, array{?\Symfony\Component\HttpKernel\Bundle\BundleInterface, \SplFileInfo}> $finalSchemas */
        $finalSchemas = $this->getFinalSchemas($kernel, $this->bundle);
        foreach ($finalSchemas as $schema) {
            [$bundle, $finalSchema] = $schema;

            if ($bundle) {
                $file = $cacheDir . DIRECTORY_SEPARATOR . 'bundle-' . $bundle->getName() . '-' . $finalSchema->getBaseName();
            } else {
                $file = $cacheDir . DIRECTORY_SEPARATOR . 'app-' . $finalSchema->getBaseName();
            }

            $filesystem->copy((string)$finalSchema, $file, true);

            // the package needs to be set absolute
            // besides, the automated namespace to package conversion has
            // not taken place yet so it needs to be done manually
            $database = simplexml_load_file($file);

            if (isset($database['package'])) {
                // Do not use the prefix!
                // This is used to override the package resulting from namespace conversion.
                $database['package'] = $database['package'];
            } elseif (isset($database['namespace'])) {
                if ($bundle !== null && !$bundle instanceof AppBundle) {
                    $database['package'] = $this->getPackageFromBundle($bundle, (string)$database['namespace']);
                } else {
                    $database['package'] = $this->getPackageFromApp((string)$database['namespace']);
                }
            } else {
                throw new RuntimeException(
                    sprintf(
                        '%s : Please define a `package` attribute or a `namespace` attribute for schema `%s`',
                        $bundle ? $bundle->getName() : 'App',
                        $finalSchema->getBaseName(),
                    ),
                );
            }

            if ($this->input->hasOption('connection')) {
                $connections = $this->input->getOption('connection') ?: [$this->getDefaultConnection()];

                if (!in_array((string)$database['name'], $connections)) {
                    // we skip this schema because the connection name doesn't
                    // match the input values
                    $filesystem->remove($file);
                    $this->output->writeln(sprintf(
                        '<info>Skipped schema %s due to database name missmatch (%s not in [%s]).</info>',
                        $finalSchema->getPathname(),
                        $database['name'],
                        implode(',', $connections),
                    ));

                    continue;
                }
            }

            foreach ($database->table as $table) {
                if (isset($table['package'])) {
                    $table['package'] = $table['package'];
                } elseif (isset($table['namespace'])) {
                    if ($bundle !== null && !$bundle instanceof AppBundle) {
                        $table['package'] = $this->getPackageFromBundle($bundle, (string)$table['namespace']);
                    } else {
                        $table['package'] = $this->getPackageFromApp((string)$table['namespace']);
                    }
                } elseif (isset($database['package'])) {
                    $table['package'] = $database['package'];
                }
            }

            file_put_contents($file, $database->asXML());
        }
    }

    /**
     * Return a list of final schema files that will be processed.
     *
     * @param \Symfony\Component\HttpKernel\KernelInterface $kernel The application kernel.
     * @param \Symfony\Component\HttpKernel\Bundle\BundleInterface|null $bundle If given, only the bundle's schemas will
     *                                     be returned.
     *
     * @return array<string, array{?\Symfony\Component\HttpKernel\Bundle\BundleInterface, \SplFileInfo}> A list of schemas.
     */
    protected function getFinalSchemas(KernelInterface $kernel, ?BundleInterface $bundle = null): array
    {
        if ($bundle !== null) {
            return $this->getSchemaLocator()->locateFromBundle($bundle);
        }

        return $this->getSchemaLocator()->locateFromBundlesAndConfiguration($kernel->getBundles());
    }

    /**
     * Run a Symfony command.
     *
     * @param \Symfony\Component\Console\Command\Command $command The command to run.
     * @param array<string, mixed> $parameters An array of parameters to give to the command.
     * @param \Symfony\Component\Console\Input\InputInterface $input An InputInterface instance
     * @param \Symfony\Component\Console\Output\OutputInterface $output An OutputInterface instance
     *
     * @return int The command return code.
     */
    protected function runCommand(Command $command, array $parameters, InputInterface $input, OutputInterface $output): int
    {
        // add the command's name to the parameters
        array_unshift($parameters, $this->getName());

        // merge the default parameters
        $extraParameters = [
            '--verbose' => $input->getOption('verbose'),
        ];

        if ($command->getDefinition()->hasOption('schema-dir')) {
            $extraParameters['--schema-dir'] = $this->cacheDir;
        }

        if ($command->getDefinition()->hasOption('config-dir')) {
            $extraParameters['--config-dir'] = $this->cacheDir;
        }

        if ($input->hasOption('loader-script-dir') && !empty($input->getOption('loader-script-dir'))) {
            $parameters['--loader-script-dir'] = $input->getOption('loader-script-dir');
        }

        $parameters = array_merge($extraParameters, $parameters);

        if ($input->hasOption('platform')) {
            $platform = $input->getOption('platform') ?: $this->getPlatform();
            if ($platform) {
                $parameters['--platform'] = $platform;
            }
        }

        $command->setApplication($this->getApplication());

        // and run the sub-command
        return $command->run(new ArrayInput($parameters), $output);
    }

    /**
     * Create an XML file which represents propel.configuration
     *
     * @param string $file Should be 'propel.json'.
     *
     * @return void
     */
    protected function createPropelConfigurationFile(string $file): void
    {
        $propelConfig = $this->getConfig();

        //needed because because Propel2's configuration tree is a bit different
        //propel.runtime.logging is PropelBundle feature only.
        unset($propelConfig['runtime']['logging']);

        $config = [
            'propel' => $propelConfig,
        ];

        file_put_contents($file, json_encode($config, JSON_PRETTY_PRINT));
    }

    /**
     * Translates a list of connection names to their DSN equivalents.
     *
     * @param array<string> $connections The names.
     *
     * @return array<string>
     */
    protected function getConnections(array $connections): array
    {
        $dsnList = [];
        foreach ($connections as $connection) {
            $dsnList[] = sprintf('%s=%s', $connection, $this->getDsn($connection));
        }

        return $dsnList;
    }

    /**
     * Get the data (host, user, ...) for a given connection.
     *
     * @param string $name The connection name.
     *
     * @throws \InvalidArgumentException
     *
     * @return array<string, mixed> The connection data.
     */
    protected function getConnectionData(string $name): array
    {
        $knownConnections = $this->getConfig();
        if (!isset($knownConnections['database']['connections'][$name])) {
            throw new InvalidArgumentException(sprintf('Unknown connection "%s"', $name));
        }

        return $knownConnections['database']['connections'][$name];
    }

    /**
     * Get the DSN for a given connection.
     *
     * @param string $connectionName The connection name.
     *
     * @return string The DSN.
     */
    protected function getDsn(string $connectionName): string
    {
        $connection = $this->getConnectionData($connectionName);
        // Add user and password to dsn string
        $dsn = explode(';', $connection['dsn']);
        if (isset($connection['user'])) {
            $dsn[] = 'user=' . urlencode($connection['user']);
        }
        if (isset($connection['password'])) {
            $dsn[] = 'password=' . urlencode($connection['password']);
        }

        return implode(';', $dsn);
    }

    /**
     * @return \Propel\Bundle\PropelBundle\Service\SchemaLocator
     */
    protected function getSchemaLocator(): SchemaLocator
    {
        /** @var \Propel\Bundle\PropelBundle\Service\SchemaLocator $obj */
        $obj = $this->getContainer()->get('propel.schema_locator');

        return $obj;
    }

    /**
     * @param string $namespace
     *
     * @return string
     */
    protected function getPackageFromApp(string $namespace): string
    {
        if ($namespace[0] === '\\') {
            $namespace = substr($namespace, 1);
        }

        if (stripos($namespace, 'App\\') === 0) {
            $namespace = substr($namespace, 4);
        }

        return 'src.' . str_replace('\\', '.', $namespace);
    }

    /**
     * @param \Symfony\Component\HttpKernel\Bundle\BundleInterface $bundle
     * @param string $namespace
     *
     * @return string
     */
    protected function getPackageFromBundle(BundleInterface $bundle, string $namespace): string
    {
        //find relative path from namespace to bundle->getNamespace()
        $baseNamespace = (new ReflectionClass($bundle))->getNamespaceName();
        if (strpos($namespace, $baseNamespace) === 0) {
            //base namespace fits
            //eg.
            //  Base: Jarves/JarvesBundle => Jarves
            //  Model namespace: Jarves\Model
            //  strpos(Jarves\Model, Jarves) === 0
            // $namespaceDiff = Model

            $namespaceDiff = substr($namespace, strlen($baseNamespace) + 1);

            $bundlePath = realpath($bundle->getPath()) . '/' . str_replace('\\', '/', $namespaceDiff);
            $appPath = realpath($this->getKernel()->getProjectDir());

            $path = static::getRelativePath($bundlePath, $appPath);

            return str_replace('/', '.', $path);
        }

        //does not match or its a absolute path, so return it without suffix
        if ($namespace[0] === '\\') {
            $namespace = substr($namespace, 1);
        }

        return str_replace('\\', '.', $namespace);
    }

    /**
     * Return the current Propel cache directory.
     *
     * @return string The current Propel cache directory.
     */
    protected function getCacheDir(): string
    {
        return $this->cacheDir;
    }

    /**
     * Returns a relative path from $path to $current.
     *
     * @param string $from
     * @param string $to relative to this
     *
     * @return string relative path without trailing slash
     */
    public static function getRelativePath(string $from, string $to): string
    {
        $from = '/' . trim($from, '/');
        $to = '/' . trim($to, '/');

        if (strpos($from, $to) === 0) {
            return substr($from, strlen($to) + ($to === '/' ? 0 : 1));
        }

        $result = '';
        while ($to && strpos($from, $to) === false) {
            $result .= '../';
            $to = substr($to, 0, strrpos($to, '/'));
        }

        return !$to /*we reached root*/ ? $result . substr($from, 1) : $result . substr($from, strlen($to) + 1);
    }

    /**
     * Extract the database name from a given DSN
     *
     * @param string $dsn A DSN
     *
     * @return string|null The database name extracted from the given DSN
     */
    protected function parseDbName(string $dsn): ?string
    {
        preg_match('#(dbname|Database)=([a-zA-Z0-9\_]+)#', $dsn, $matches);

        if (isset($matches[2])) {
            return $matches[2];
        }

        // e.g. SQLite
        return null;
    }

    /**
     * Returns the name of the migrations table.
     *
     * @return string
     */
    protected function getMigrationsTable(): string
    {
        $config = $this->getConfig();

        return $config['migrations']['tableName'];
    }

    /**
     * Returns the name of the default connection.
     *
     * @return string
     */
    protected function getDefaultConnection(): string
    {
        $config = $this->getConfig();

        return !empty($config['generator']['defaultConnection']) ? $config['generator']['defaultConnection'] : key($config['database']['connections']);
    }

    /**
     * Reads the platform class from the configuration
     *
     * @return string|null The platform class name.
     */
    protected function getPlatform(): ?string
    {
        $config = $this->getConfig();

        return $config['generator']['platformClass'];
    }

    /**
     * @return \Symfony\Component\HttpKernel\KernelInterface
     */
    protected function getKernel(): KernelInterface
    {
        /** @var \Symfony\Bundle\FrameworkBundle\Console\Application $application */
        $application = $this->getApplication();

        return $application->getKernel();
    }

    /**
     * @return array{paths: array{schemaDir: string, sqlDir: string, migrationDir: string, composerDir: ?string, loaderScriptDir: string}, database: array{connections: array<string, array<string, mixed>>}, runtime: array{defaultConnection: string, logging: bool}, migrations: array{tableName: string, parserClass: string}, generator: array<string, mixed>}
     */
    protected function getConfig(): array
    {
        return $this->getContainer()->getParameter('propel.configuration');
    }
}
