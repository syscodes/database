<?php 

/**
 * Lenevor PHP Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file license.md.
 * It is also available through the world-wide-web at this URL:
 * https://lenevor.com/license
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@Lenevor.com so we can send you a copy immediately.
 *
 * @package     Lenevor
 * @subpackage  Base
 * @link        https://lenevor.com
 * @copyright   Copyright (c) 2019 - 2021 Alexander Campo <jalexcam@gmail.com>
 * @license     https://opensource.org/licenses/BSD-3-Clause New BSD license or see https://lenevor.com/license or see /license.md
 */

namespace Syscodes\Database;

use PDO;
use Syscodes\Support\Str;
use Syscodes\Collections\Arr;
use InvalidArgumentException;

/**
 * It is used to instantiate the connection and its respective settings.
 * 
 * @author Alexander Campo <jalexcam@gmail.com>
 */
class DatabaseManager implements ConnectionResolverInterface
{
    /**
     * The appilcation instance.
     * 
     * @var \Syscodes\Contracts\Core\Application $app
     */
    protected $app;

    /**
     * The active connection instances.
     * 
     * @var array $connections
     */
    protected $connections = [];

    /**
     * The database connection factory instance.
     * 
     * @var \Syscodes\Database\ConnectionFactory $factory
     */
    protected $factory;

    /**
     * The custom connection resolvers.
     * 
     * @var array $extensions
     */
    protected $extensions = [];

    /**
     * Constructor. Create a new DatabaseManager instance.
     * 
     * @param  \Syscodes\Contracts\Core\Application  $app
     * @param  \Syscodes\Database\ConnectionFactory  $factory
     * 
     * @return void
     */
    public function __construct($app, ConnectionFactory $factory)
    {
        $this->app     = $app;
        $this->factory = $factory;
    }
    
    /**
     * Get a database connection instance.
     * 
     * @param  string|null  $name  (null by default)
     * 
     * @return \Syscodes\Database\Connection
     */
    public function connection($name = null)
    {
        [$database, $type] = $this->parseConnectionName($name);

        $name = $name ?: $database;

        if ( ! isset($this->connections[$name])) {
            $connection = $this->makeConnection($name);

            $this->connections[$name] = $this->configure($connection, $type);
        }

        return $this->connections[$name];
    }

    /**
     * Parse the connection into an array of the name and read / write type.
     * 
     * @param  string  $name
     * 
     * @return array 
     */
    protected function parseConnectionName($name)
    {
        $name = $name ?: $this->getDefaultConnection();

        return Str::endsWith($name, ['::read', '::write'])
                ? explode('::', $name, 2)
                : [$name, null];
    }

    /**
     * Make the database connection instance.
     * 
     * @param  string  $name
     * 
     * @return \Syscodes\Database\Connection 
     */
    protected function makeConnection($name)
    {
        $config = $this->getConfiguration($name);

        if (isset($this->extensions[$name])) {
            return call_user_func($this->extensions[$name], $config, $name);
        }

        $driver = $config['driver'];

        if (isset($this->extensions[$driver])) {
            return call_user_func($this->extensions[$driver], $config, $name);
        }

        return $this->factory->make($config, $name);
    }

    /**
     * Get the configuration for a connection.
     * 
     * @param  string  $name
     * 
     * @return array
     * 
     * @throws \InvalidArgumentException
     */
    protected function getConfiguration($name)
    {
        $name = $name ?: $this->getDefaultConnection();

        $connections = $this->app['config']['database.connections'];

        if (is_null($config = Arr::get($connections, $name))) {
            throw new InvalidArgumentException("Database connection [{$name}] not configured");
        }

        return $config;
    }

    /**
     * Prepare the database connection instance.
     * 
     * @param  \Syscodes\Database\Connection  $connection
     * @param  string  $type
     * 
     * @return \Syscodes\Database\Connection
     */
    protected function configure(Connection $connection, $type)
    {
        $connection = $this->setPdoForType($connection, $type);

        if ($this->app->bound('events')) {
            $connection->setEventDispatcher($this->app['events']);
        }

        $connection->setReconnector(function ($connection) {
            $this->reconnect($connection->getName());
        });

        return $connection;
    }

    /**
     * Prepare the read / write mode for database connection instance.
     * 
     * @param  \Syscodes\Database\Connection  $connection
     * @param  string|null  $type
     * 
     * @return \Syscodes\Database\Connection
     */
    protected function setPdoForType(Connection $connection, $type)
    {
        if ($type === 'read') {
            $connection->setPdo($connection->getReadPdo());
        } elseif ($type === 'write') {
            $connection->setReadPdo($connection->getPdo());
        }

        return $connection;
    }

    /**
     * Reconnect to the given database.
     * 
     * @param  string|null  $name  (null by default)
     * 
     * @return \Syscodes\Database\Connection
     */
    public function reconnect($name = null)
    {
        $this->disconnect($name = $name ?: $this->getDefaultConnection());

        if ( ! isset($this->connections[$name])) {
            return $this->connection($name);
        }

        return $this->refreshPdoConnections($name);
    }

    /**
     * Disconnect from the given database.
     * 
     * @param  string|null  $name  (null by default)
     * 
     * @return void
     */
    public function disconnect($name = null)
    {
        if (isset($this->connections[$name = $name ?: $this->getDefaultConnection()])) {
            $this->connections[$name]->disconnect();
        }
    }

    /**
     * Refresh the PDO connections on a given connection.
     * 
     * @param  string  $name
     * 
     * @return \Syscodes\Database\Connection
     */
    protected function refreshPdoConnections($name)
    {
        $fresh = $this->makeConnection($name);

        return $this->connections[$name]
                ->setPdo($fresh->getRawPdo())
                ->setReadPdo($fresh->getRawReadPdo());
    }

    /**
     * Disconnect from the given database and remove from local cache.
     * 
     * @param  string|null  $name  (null by default)
     * 
     * @return void
     */
    public function purge($name = null)
    {
        $name = $name ?: $this->getDefaultConnection();

        $this->disconnect($name);

        unset($this->connections[$name]);
    }

    /**
     * Get the default connection name.
     * 
     * @return string
     */
    public function getDefaultConnection()
    {
        return $this->app['config']['database.default'];
    }

    /**
     * Set the default connection name.
     * 
     * @return string
     */
    public function setDefaultConnection($name)
    {
        $this->app['config']['database.default'] = $name;
    }

    /**
     * Register an extension connection resolver.
     * 
     * @param  string  $name
     * @param  \Callable  $resolver
     * 
     * @return void
     */
    public function extend($name, Callable $resolver)
    {
        $this->extensions[$name] = $resolver;
    }

    /**
     * Return all of the created connections.
     * 
     * @return array
     */
    public function getConnections()
    {
        return $this->connections;
    }

    /**
     * Dynamically pass methods to the default connection.
     * 
     * @param  string  $method
     * @param  array  $parameters
     * 
     * @return mixed
     */
    public function __call($method, $parameters)
    {
        return $this->connection()->$method(...$parameters);
    }
}