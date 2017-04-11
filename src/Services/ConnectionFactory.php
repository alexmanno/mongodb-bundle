<?php declare(strict_types = 1);

namespace Facile\MongoDbBundle\Services;

use MongoDB\Database;

/**
 * Class ConnectionFactory.
 * @internal
 */
final class ConnectionFactory
{
    /** @var ClientRegistry */
    private $clientRegistry;

    /**
     * ConnectionFactory constructor.
     *
     * @param ClientRegistry $clientRegistry
     */
    public function __construct(ClientRegistry $clientRegistry)
    {
        $this->clientRegistry = $clientRegistry;
    }

    /**
     * @param string $clientName
     * @param string $databaseName
     *
     * @return Database
     *
     * @deprecated
     */
    public function createConnection(string $clientName, string $databaseName): Database
    {
        return $this->clientRegistry
            ->getClientForDatabase($clientName, $databaseName)
            ->selectDatabase($databaseName);
    }

    /**
     * @param string $clientName
     * @param string $databaseName
     *
     * @return Database
     */
    public function createNamedConnection(string $clientName, string $databaseName, $connectionName): Database
    {
        return $this->clientRegistry
            ->getClientForDatabase($clientName, $databaseName, $connectionName)
            ->selectDatabase($databaseName);
    }
}
