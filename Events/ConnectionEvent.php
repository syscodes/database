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
 
namespace Syscodes\Database\Events;

/**
 * Get the database connection event.
 * 
 * @author Alexander Campo <jalexcam@gmail.com>
 */
abstract class ConnectionEvent
{
    /**
     * The database connection instance.
     * 
     * @var \Syscodes\Database\Connection $connection
     */
    public $connection;

    /**
     * The name of the connection.
     * 
     * @var string $ConnectionName
     */
    public $connectionName;

    /**
     * Constructor. Create a new event instance.
     * 
     * @param  \Syscodes\Database\Connection  $connection
     * 
     * @return void
     */
    public function __construct($connection)
    {
        $this->connection     = $connection;
        $this->connectionName = $connection->getName();
    }
}