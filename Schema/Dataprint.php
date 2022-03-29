<?php 

/**
 * Lenevor Framework
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
 * @copyright   Copyright (c) 2019 - 2022 Alexander Campo <jalexcam@gmail.com>
 * @license     https://opensource.org/licenses/BSD-3-Clause New BSD license or see https://lenevor.com/license or see /license.md
 */

namespace Syscodes\Components\Database\Schema;

use Closure;
use Syscodes\Components\Support\Flowing;
use Syscodes\Components\Database\Connections\Connection;
use Syscodes\Components\Database\Schema\Grammars\Grammar;

/**
 * Extracts the column names to using in creating migrations.
 * 
 * @author Alexander Campo <jalexcam@gmail.com>
 */
class Dataprint
{
    /**
     * The columns that should be added to the table.
     * 
     * @var array $columns
     */
    protected $columns = [];

    /**
     * The commands that should be run for the table.
     * 
     * @var array $commands
     */
    protected $commands = [];

    /**
     * The storage engine that should be used for the table.
     * 
     * @var string $engine
     */
    protected $engine;

    /**
     * The prefix of the table.
     * 
     * @var string $prefix
     */
    protected $prefix;

    /**
     * The table the blueprint describes.
     * 
     * @var string $table
     */
    protected $table;

    /**
     * Constructor. Create a new schema Dataprint instance.
     * 
     * @param  string  $table
     * @param  \Closure|null  $callback
     * @param  string  $prefix
     * 
     * @return void
     */
    public function __construct($table, Closure $callback = null, $prefix = '')
    {
        $this->table = $table;
        $this->prefix = $prefix;

        if ( ! is_null($callback)) $callback($this);
    }

    /**
     * Execute the blueprint against the database.
     * 
     * @param  \Syscodes\Components\Database\Connections\Connection  $connection
     * @param  \Syscodes\Components\Database\Schema\Grammars\Grammar  $grammar
     * 
     * @return void
     */
    public function build(Connection $connection, Grammar $grammar): void
    {
        foreach($this->toSql($connection, $grammar) as $statement) {
            $connection->statement($statement);
        }
    }

    /**
     * Get the raw SQL statements for the blueprint.
     * 
     * @param  \Syscodes\Components\Database\Connections\Connection  $connection
     * @param  \Syscodes\Components\Database\Schema\Grammars\Grammar  $grammar
     * 
     * @return array
     */
    public function toSql(Connection $connection, Grammar $grammar): array
    {
        $this->addImpliedCommands();

        $statements = [];

        foreach ($this->commands as $command) {
            $method = 'compile'.ucfirst($command->name);

            if (method_exists($grammar, $method)) {
                if ( ! is_null($sql = $grammar->$method($this, $command, $connection))) {
                    $statements[] = array_merge($statements, (array) $sql);
                }
            }
        }

        return $statements;
    }

    /**
     * Add the commands that are implied by the data print.
     * 
     * @return void
     */
    protected function addImpliedCommands(): void
    {
        if (count($this->getAddedColumns()) > 0 && ! $this->creating()) {
            array_unshift($this->commands, $this->createCommand('add'));
        }

        if (count($this->getChangedColumns()) > 0 && ! $this->creating()) {
            array_unshift($this->commands, $this->createCommand('change'));
        }

        $this->addFlowingIndexes();
    }

    /**
     * Add the index commands fluently specified on columns.
     * 
     * @return void
     */
    protected function addFlowingIndexes(): void
    {
        foreach ($this->columns as $column) {
            foreach (['primary', 'unique', 'index'] as $index) {
                if ($column->{$index} === true) {
                    $this->{$index}($column->name);

                    $column->{$index} = false;
                    
                    continue 2;
                } elseif (isset($column->{$index})) {
                    $this->{$index}($column->name, $column->{$index});

                    $column->{$index} = false;
                    
                    continue 2;
                }
            }
        }
    }
    
    /**
     * Determine if the data print has a create command.
     * 
     * @return bool
     */
    public function creating(): bool
    {
        return collect($this->commands)->contains(function ($command) {
            return $command->name === 'create';
        });
    }
    
    /**
     * Remove a column from the schema data print.
     * 
     * @param  string  $name
     * 
     * @return self
     */
    public function removeColumn($name): self
    {
        $this->columns = array_values(array_filter($this->columns, function ($column) use ($name) {
            return $column['name'] != $name;
        }));
        
        return $this;
    }
    
    /**
     * Add a new command to the data print.
     * 
     * @param  string  $name
     * @param  array  $parameters
     * 
     * @return \Syscodes\Components\Support\Flowing
     */
    protected function addCommand($name, array $parameters = array())
    {
        $this->commands[] = $command = $this->createCommand($name, $parameters);
        
        return $command;
    }
    
    /**
     * Create a new Flowing command.
     * 
     * @param  string  $name
     * @param  array   $parameters
     * 
     * @return \Syscodes\Components\Support\Flowing
     */
    protected function createCommand($name, array $parameters = array())
    {
        return new Flowing(array_merge(compact('name'), $parameters));
    }
    
    /**
     * Get the table the data print describes.
     * 
     * @return string
     */
    public function getTable(): string
    {
        return $this->table;
    }
    
    /**
     * Get the columns that should be added.
     * 
     * @return array
     */
    public function getColumns(): array
    {
        return $this->columns;
    }
    
    /**
     * Get the commands on the data print.
     * 
     * @return array
     */
    public function getCommands(): array
    {
        return $this->commands;
    }
    
    /**
     * Get the columns on the data print that should be added.
     * 
     * @return \Syscodes\Components\Database\Schema\ColumnDefinition[]
     */
    public function getAddedColumns()
    {
        return array_filter($this->columns, function ($column) {
            return ! $column->change;
        });
    }
    
    /**
     * Get the columns on the data print that should be changed.
     * 
     * @return \Syscodes\Components\Database\Schema\ColumnDefinition[]
     */
    public function getChangedColumns()
    {
        return array_filter($this->columns, function ($column) {
            return (bool) $column->change;
        });
    }
}