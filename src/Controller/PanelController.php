<?php

/**
 * This file is part of the PropelBundle package.
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license MIT License
 */

declare(strict_types = 1);

namespace Propel\Bundle\PropelBundle\Controller;

use Exception;
use PDO;
use Propel\Runtime\Propel;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Twig\Environment;

/**
 * PanelController is designed to display information in the Propel Panel.
 *
 * @author William DURAND <william.durand1@gmail.com>
 */
class PanelController
{
    /**
     * @param array<string, mixed> $configuration
     */
    public function __construct(
        private readonly Environment $twig,
        private readonly ?Profiler $profiler,
        private readonly array $configuration,
        private readonly bool $logging,
    ) {
    }

    /**
     * This method renders the global Propel configuration.
     *
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function configurationAction(): Response
    {
        return new Response($this->twig->render(
            '@Propel/Panel/configuration.html.twig',
            [
                'propel_version' => Propel::VERSION,
                'configuration' => $this->configuration,
                'logging' => $this->logging,
            ],
        ));
    }

    /**
     * Renders the profiler panel for the given token.
     *
     * @param string $token The profiler token
     * @param string $connection The connection name
     * @param int $query
     *
     * @return \Symfony\Component\HttpFoundation\Response A Response instance
     */
    public function explainAction(string $token, string $connection, int $query): Response
    {
        if ($this->profiler === null) {
            return new Response('The profiler is not enabled.');
        }

        $this->profiler->disable();

        $profile = $this->profiler->loadProfile($token);

        /** @var \Propel\Bundle\PropelBundle\DataCollector\PropelDataCollector $propelDataCollector */
        $propelDataCollector = $profile->getCollector('propel');
        $queries = $propelDataCollector->getQueries();

        if (!isset($queries[$query])) {
            return new Response('This query does not exist.');
        }

        // Open the connection
        $con = Propel::getConnection($connection);

        try {
            $dataFetcher = $con->query('EXPLAIN ' . $queries[$query]['sql']);
            $results = [];
            while (($results[] = $dataFetcher->fetch(PDO::FETCH_ASSOC)));
        } catch (Exception $e) {
            return new Response('<div class="error">This query cannot be explained.</div>');
        }

        return new Response($this->twig->render(
            '@Propel/Panel/explain.html.twig',
            [
                'data' => $results,
                'query' => $query,
            ],
        ));
    }
}
