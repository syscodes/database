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
 * @author      Javier Alexander Campo M. <jalexcam@gmail.com>
 * @link        https://lenevor.com 
 * @copyright   Copyright (c) 2019-2020 Lenevor Framework 
 * @license     https://lenevor.com/license or see /license.md or see https://opensource.org/licenses/BSD-3-Clause New BSD license
 * @since       0.7.3
 */
 
namespace Syscodes\Database\Events;

/**
 * Get the database connection event.
 * 
 * @author Javier Alexander Campo M. <jalexcam@gmail.com>
 */
class StatementPrepared
{
    /**
     * The database connection instance.
     * 
     * @var \Syscodes\Database\Connection $connection
     */
    public $connection;

    /**
     * The PDO statement.
     * 
     * @var string $statement
     */
    public $statement;

    /**
     * Constructor. Create a new event instance.
     * 
     * @param  \Syscodes\Database\Connection  $connection
     * @param  \PDOStatement  $statement
     * 
     * @return void
     */
    public function __construct($connection, $statement)
    {
        $this->connection = $connection;
        $this->statement  = $statement;
    }
}