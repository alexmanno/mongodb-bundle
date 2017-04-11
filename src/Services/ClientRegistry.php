<?php declare(strict_types = 1);

namespace Facile\MongoDbBundle\Services;

use Facile\MongoDbBundle\Capsule\Client as BundleClient;
use Facile\MongoDbBundle\Event\ConnectionEvent;
use Facile\MongoDbBundle\Models\ClientConfiguration;
use MongoDB\Client;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Class ClientRegistry.
 * @internal
 */
final class ClientRegistry
{
    /** @var Client[] */
    private $clients;
    /** @var string[] */
    private $connections;
    /** @var ClientConfiguration[] */
    private $configurations;
    /** @var string */
    private $environment;
    /** @var EventDispatcherInterface */
    private $eventDispatcher;

    /**
     * ClientRegistry constructor.
     *
     * @param EventDispatcherInterface $eventDispatcher
     * @param string                   $environment
     *
     * @internal param DataCollectorLoggerInterface $logger
     */
    public function __construct(EventDispatcherInterface $eventDispatcher, string $environment)
    {
        $this->clients = [];
        $this->connections = [];
        $this->configurations = [];
        $this->environment = $environment;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * @param array $configurations
     */
    public function addClientsConfigurations(array $configurations)
    {
        foreach ($configurations as $name => $conf) {
            $this->addClientConfiguration($name, $conf);
        }
    }

    /**
     * @param string $name
     * @param array  $conf
     */
    private function addClientConfiguration(string $name, array $conf)
    {
        $this->configurations[$name] = $this->buildClientConfiguration($conf);
    }

    /**
     * @param array $conf
     *
     * @return ClientConfiguration
     */
    private function buildClientConfiguration(array $conf): ClientConfiguration
    {
        return new ClientConfiguration(
            $this->buildConnectionUri($conf['hosts']),
            $conf['username'],
            $conf['password'],
            [
                'replicaSet' => $conf['replicaSet'],
                'ssl' => $conf['ssl'],
                'connectTimeoutMS' => $conf['connectTimeoutMS'],
                'readPreference' => $conf['readPreference'],
            ]
        );
    }

    /**
     * @param array $hosts
     *
     * @return string
     */
    private function buildConnectionUri(array $hosts): string
    {
        return implode(
            ',',
            array_map(
                function (array $host) {
                    return sprintf("%s:%d", $host['host'], $host['port']);
                },
                $hosts
            )
        );
    }

    /**
     * @param string      $name
     * @param string      $databaseName
     * @param string|null $connectionName
     *
     * @return Client
     */
    public function getClientForDatabase(string $name, string $databaseName, string $connectionName = null): Client
    {
        if (!empty($connectionName)) {
            $this->connections[] = $connectionName;
        }
        return $this->getClient($name, $databaseName);
    }

    /**
     * @return array
     */
    public function getClientNames(): array
    {
        return array_keys($this->clients);
    }

    /**
     * @return array
     */
    public function getConnectionNames(): array
    {
        return $this->connections;
    }

    /**
     * @param string $name
     * @param string $databaseName
     *
     * @return Client
     */
    public function getClient(string $name, string $databaseName = null): Client
    {
        $clientKey = !is_null($databaseName) ? $name.'.'.$databaseName : $name;

        if (!isset($this->clients[$clientKey])) {
            $conf = $this->configurations[$name];
            $uri = sprintf('mongodb://%s', $conf->getHosts());
            $options = array_merge(['database' => $databaseName], $conf->getOptions());
            $this->clients[$clientKey] = $this->buildClient($uri, $options, []);

            $this->eventDispatcher->dispatch(
                ConnectionEvent::CLIENT_CREATED,
                new ConnectionEvent($clientKey)
            );
        }

        return $this->clients[$clientKey];
    }

    /**
     * @param                              $uri
     * @param array                        $options
     * @param array                        $driverOptions
     *
     * @return Client
     */
    private function buildClient($uri, array $options, array $driverOptions): Client
    {
        if ('dev' === $this->environment) {
            return new BundleClient($uri, $options, $driverOptions, $this->eventDispatcher);
        }

        return new Client($uri, $options, $driverOptions);
    }
}
