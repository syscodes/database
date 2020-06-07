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
 * @copyright   Copyright (c) 2019-2020 Lenevor PHP Framework 
 * @license     https://lenevor.com/license or see /license.md or see https://opensource.org/licenses/BSD-3-Clause New BSD license
 * @since       0.7.0
 */
 
namespace Syscodes\Database\Query;

use Closure;
use RuntimeException;
use DateTimeInterface;
use Syscodes\Support\Arr;
use Syscodes\Support\Str;
use BadMethodCallException;
use InvalidArgumentException;
use Syscodes\Database\DatabaseCache;
use Syscodes\Database\Query\Grammar;
use Syscodes\Database\Query\Processor;
use Syscodes\Database\Query\Expression;
use Syscodes\Database\Query\JoinClause;
use Syscodes\Database\ConnectionInterface;

/**
 * Lenevor database query builder provides a convenient, fluent interface 
 * to creating and running database queries. and works on all supported 
 * database systems.
 * 
 * @author Javier Alexander Campo M. <jalexcam@gmail.com>
 */
class Builder
{
    /**
     * An aggregate function and column to be run.
     * 
     * @var array $aggregate
     */
    public $aggregate;

    /**
     * The current query value bindings.
     * 
     * @var array $bindings
     */
    public $bindings = [
        'select' => [],
        'from' => [],
        'join' => [],
        'where' => [],
        'groupBy' => [],
        'having' => [],
        'order' => [],
        'union' => [],
    ];

    /**
     * Get the columns of a table.
     * 
     * @var array $columns
     */
    public $columns;

    /**
     * The database connection instance.
     * 
     * @var \Syscodes\Database\ConnectionInterface $connection
     */
    protected $connection;

    /**
     * Indicates if the query returns distinct results.
     * 
     * @var bool $distinct
     */
    public $distinct = false;

    /**
     * Get the table name for the query.
     * 
     * @var string $from
     */
    public $from;

    /**
     * The database query grammar instance.
     * 
     * @var \Syscodes\Database\Query\Grammar $grammar
     */
    protected $grammar;

    /**
     * Get the grouping for the query.
     * 
     * @var array $groups
     */
    public $groups;

    /**
     * Get the having constraints for the query.
     * 
     * @var array $havings
     */
    public $havings;

    /**
     * Get the table joins for the query.
     * 
     * @var array $joins
     */
    public $joins;

    /**
     * Get the maximum number of records to return.
     * 
     * @var int $limit
     */
    public $limit;

    /**
     * Indicates whether row locking is being used.
     * 
     * @var string|bool $lock
     */
    public $lock;

    /**
     * Get the number of records to skip.
     * 
     * @var int $offset
     */
    public $offset;

    /**
     * Get the orderings for the query.
     * 
     * @var array $orders
     */
    public $orders;

    /**
     * The database query post processor instance.
     * 
     * @var \Syscodes\Database\Query\Processor $processor
     */
    protected $processor;

    /**
     * Get the query union statements.
     * 
     * @var array $unions
     */
    public $unions;

    /**
     * Get the maximum number of union records to return.
     * 
     * @var int $unionLimit
     */
    public $unionLimit;

    /**
     * Get the number of union records to skip.
     * 
     * @var int $unionOffset
     */
    public $unionOffset;

    /**
     * Get the orderings for the union query.
     * 
     * @var array $unionOrders
     */
    public $unionOrders;

    /**
     * Get the where constraints for the query.
     * 
     * @var array $wheres
     */
    public $wheres;

    /**
     * Constructor. Create a new query builder instance.
     * 
     * @param  \Syscodes\Database\ConnectionInterface  $connection
     * @param  \Syscodes\Database\Query\Grammar  $grammar  (null by default)
     * @param  \Syscodes\Database\Query\Processor  $processor  (null by default)
     * 
     * return void
     */
    public function __construct(ConnectionInterface $connection, Grammar $grammar = null, Processor $processor = null)
    {
        $this->processor = $processor ?: $this->getQueryProcessor();
        $this->grammar = $grammar ?: $this->getQueryGrammar();
        $this->connection = $connection;
    }

    /**
     * Set the columns to be selected.
     * 
     * @param  array  $columns
     * 
     * @return $this
     */
    public function select($columns = ['*'])
    {
        $this->columns = is_array($columns) ? $columns : func_get_args();

        return $this;
    }

    /**
     * Add a subselect expression to the query.
     * 
     * @param  \Syscodes\Database\Query\Builder|string  $builder
     * @param  string  $as
     * 
     * @return $this
     * 
     * @throws \InvalidArgumentException
     */
    public function selectSub($builder, $as)
    {
        [$builder, $bindings] = $this->makeSub($builder);

        return $this->selectRaw(
            '('.$builder.') as '.$this->grammar->wrap($as), $bindings
        );
    }

    /**
     * Makes a subquery and parse it.
     * 
     * @param  \Closure|\Syscodes\Database\Query\Builder|string  $builder
     * 
     * @return array
     */
    protected function makeSub($builder)
    {
        if ($builder instanceof Closure)
        {
            $callback = $builder;

            $callback($builder = $this->newBuilder());
        }

        return $this->parseSub($builder);
    }

    /**
     * Parse the subquery into SQL and bindings.
     * 
     * @param  mixed  $builder
     * 
     * @return array
     * 
     * @throws \InvalidArgumentException
     */
    protected function parseSub($builder)
    {
        if ($builder instanceof self)
        {
            return [$builder->getSql(), $builder->getBindings()];
        }
        elseif (is_string($builder))
        {
            return [$builder->getSql(), []];
        }
        else
        {
            throw new InvalidArgument('A subquery must be a query builder instance, a Closure, or a string.');
        }
    }

    /**
     *  Add a new "raw" select expression to the query.
     * 
     * @param  string  $expression
     * @param  array  $bindings
     * 
     * @return $this
     */
    public function selectRaw($expression, array $bindings = [])
    {
        $this->addSelect(new Expression($expression));

        if ($bindings)
        {
            $this->addBinding($bindings, 'select');
        }

        return $this;
    }

    /**
     * Add a new select column to the query.
     * 
     * @param  mixed  $column
     * 
     * @return $this
     */
    public function addSelect($column)
    {
        $column = is_array($column) ? $column : func_get_args();

        $this->columns = array_merge((array) $this->columns, $column);

        return $this;
    }

    /**
     * Allows force the query for return distinct results.
     * 
     * @return $this
     */
    public function distinct()
    {
        $this->distinct = true;

        return $this;
    }
    
    /**
     * Set the table which the query.
     * 
     * @param  string  $table
     * 
     * @return $this
     */
    public function from($table)
    {
        $this->from = $table;

        return $this;
    }

    /**
     * Get the SQL representation of the query.
     * 
     * @return string
     */
    public function getSql()
    {
        return $this->grammar->compileSelect($this);
    }

    /**
     * Get a new join clause.
     * 
     * @param  string  $type
     * @param  string  $table
     * 
     * @return \Syscodes\Database\Query\JoinClause
     */
    protected function newJoinClause($type, $table)
    {
        return new JoinClause($type, $table);
    }

    /**
     * Set the "offset" value of the query.
     * 
     * @param  int  $value
     * 
     * @return $this
     */
    public function offset($value)
    {
        $property = $this->unions ? 'unionOffset' : 'offset';

        $this->$property = max(0, $value);
        
        return $this;
    }

    /**
     * Set the "limit" value of the query.
     * 
     * @param  int  $value
     * 
     * @return $this
     */
    public function limit($value)
    {
        $property = $this->unions ? 'unionLimit' : 'limit';

        if ($value >= 0)
        {
            $this->$property = $value;
        }
        return $this;
    }

    /**
     * Add a union statement to the query.
     * 
     * @param  \Syscodes\Database\Query\Builder|\Closure  $builder
     * @param  bool  $all  (false by default)
     * 
     * @return $this
     */
    public function union($builder, $all = false)
    {
        if ($builder instanceof Closure)
        {
            call_user_func($builder, $builder = $this->newBuilder());
        }

        $this->unions[] = compact('builder', 'all');

        $this->addBinding($builder->getBindings(), 'union');

        return $this;
    }

    /**
     * Add a union all statement to the query.
     * 
     * @param  \Syscodes\Database\Query\Builder|\Closure  $builder
     * 
     * @return $this
     */
    public function unionAll($builder)
    {
        return $this->union($builder, true);
    }

    /**
     * Lock the selected rows in the table.
     * 
     * @param  bool  $value  (true by default)
     * 
     * @return $this
     */
    public function lock($value = true)
    {
        $this->lock = $value;

        return $this;
    }

    /**
     * Lock the selected rows in the table for updating.
     * 
     * @return \Syscodes\Database\Query\Builder
     */
    public function lockRowsUpdate()
    {
        return $this->lock(true);
    }

    /**
     * Share lock the selected rows in the table.
     * 
     * @return \Syscodes\Database\Query\Builder
     */
    public function shareRowsLock()
    {
        return $this->lock(false);
    }

    /**
     * Pluck a single column's value from the first result of a query.
     * 
     * @param  string  $column
     * 
     * @return mixed
     */
    public function pluck($column)
    {
        $sql = (array) $this->first([$column]);

        return count($sql) > 0 ? reset($sql) : null;
    }

    /**
     * Execute the query and get the first result.
     * 
     * @param  array
     * 
     * @return mixed
     */
    public function first($columns = ['*'])
    {
        $sql = $this->limit(1)->get($columns);

        return count($sql) > 0 ? head($sql) : null;
    }
    
    /**
     * Execute the query as a "select" statement.
     * 
     * @param  array  $columns
     * 
     * @return array|static[]
     */
    public function get($columns = ['*'])
    {
        return $this->getFresh(Arr::wrap($columns), function () {
            return $this->getWithStatement();
        });
    }
    
    /**
     * Execute the given callback while selecting the given columns.
     * 
     * @param  string  $columns
     * @param  \callable  $callback
     * 
     * @return mixed 
     */
    protected function getFresh($columns, $callback)
    {
        $original = $this->columns;

        if (is_null($original))
        {
            $this->columns = $columns;
        }

        $result = $callback();

        $this->columns = $original;

        return $result;
    }

    /**
     * Execute the query with a "select" statement.
     * 
     * @return array|static[]
     */
    protected function getWithStatement()
    {
        return $this->processor->processSelect($this, $this->runOnSelectStatement());
    }

    /**
     * Run the query as a "select" statement against the connection.
     * 
     * @return array
     */
    public function runOnSelectStatement()
    {
        return $this->connection->select($this->getSql(), $this->getBindings());
    }

    /**
     * Execute an aggregate function on the database.
     * 
     * 
     */

    /**
     * Insert a new record into the database.
     * 
     * @param  array  $values
     * 
     * @return bool
     */
    public function insert(array $values)
    {
        if (empty($values))
        {
            return true;
        }

        if ( ! is_array(reset($values)))
        {
            $values = [$values];
        }
        else
        {
            foreach ($values as $key => $value)
            {
                ksort($value);

                $values[$key] = $value;
            }
        }

        $sql      = $this->grammar->compileInsert($this, $values);
        $bindings = $this->cleanBindings($this->buildInsertBinding($values));

        return $this->connection->insert($sql, $bindings);
    }

    /**
     * It insert like a batch data so we can easily insert each 
     * records into the database consistenly.
     * 
     * @param  array  $values
     */
    private function buildInsertBinding(array $values)
    {
        $bindings = [];

        foreach ($values as $record)
        {
            foreach ($record as $value)
            {
                $bindings[] = $value;
            }
        }

        return $bindings;
    }

    /**
     * Insert a new record and get the value of the primary key.
     * 
     * @param  array  $values
     * @param  string  $sequence  (null by default)
     * 
     * @return int
     */
    public function insertGetId(array $values, $sequence = null)
    {
        $sql = $this->grammar->compileInsertGetId($this, $values, $sequence);

        $values = $this->cleanBindings($values);

        return $this->processor->processInsertGetId($this, $sql, $values, $sequence);
    }

    /**
     * Update a record in the database.
     * 
     * @param  array  $values
     * 
     * @return \PDOStatement
     */
    public function update(array $values)
    {
        $bindings = array_values(array_merge($values, $this->bindings));

        $sql = $this->grammar->compileUpdate($this, $values);

        return $this->connection->query($sql, $this->cleanBindings($bindings));
    }

    /**
     * Increment a column's value by a given amount.
     * 
     * @param  string  $column
     * @param  int  $amount  (1 by default)
     * @param  array  $extra
     * 
     * @return \PDOStatement
     */
    public function increment($column, $amount = 1, array $extra = [])
    {
        if ( ! is_numeric($amount))
        {
            throw new InvalidArgumentException("Non-numeric value passed to increment method");
        }

        $wrapped = $this->grammar->wrap($column);

        $columns = array_merge([$column => $this->raw("$wrapped + $amount"), $extra]);

        return $this->update($columns);
    }

    /**
     * Decrement a column's value by a given amount.
     * 
     * @param  string  $column
     * @param  int  $amount  (1 by default)
     * @param  array  $extra
     * 
     * @return \PDOStatement
     */
    public function decrement($column, $amount = 1, array $extra = [])
    {
        if ( ! is_numeric($amount))
        {
            throw new InvalidArgumentException("Non-numeric value passed to decrement method");
        }

        $wrapped = $this->grammar->wrap($column);

        $columns = array_merge([$column => $this->raw("$wrapped - $amount"), $extra]);

        return $this->update($columns);
    }

    /**
     * Get run a truncate statment on the table.
     * 
     * @return void
     */
    public function truncate()
    {
        foreach ($this->grammar->compileTruncate($this) as $sql => $bindings)
        {
            $this->connection->query($sql, $bindings);
        }
    }

    /**
     * Create a raw database expression.
     *
     * @param  mixed  $value
     * 
     * @return \Syscodes\Database\Query\Expression
     */
    public function raw($value)
    {
        return $this->connection->raw($value);
    }

    /**
     * Get a new instance of the query builder.
     * 
     * @return \Syscodes\Database\Query\Builder
     */
    public function newBuilder()
    {
        return new static($this->connection, $this->grammar, $this->processor);
    }

    /**
     * Remove all of the expressions from a lists of bindings.
     * 
     * @param  array  $bindings
     * 
     * @return array
     */
    public function cleanBindings(array $bindings)
    {
        return array_values(array_filter($bindings, function () {
            return ! bindings instanceof Expression;
        }));
    }

    /**
     * Get the current query value bindings in a flattened array.
     * 
     * @return array
     */
    public function getBindings()
    {
        return Arr::Flatten($this->bindings);
    }

    /**
     * Get the raw array of bindings.
     * 
     * @return array
     */
    public function getRawBindings()
    {
        return $this->bindings;
    }

    /**
     * /**
     * Set the bindings on the query sql.
     * 
     * @param  mixed  $value
     * @param  string  $type  ('where' by default)
     * 
     * @return $this
     * 
     * @throws \InvalidArgumentException
     */
    public function setBindings($value, $type = 'where')
    {
        if ( ! array_key_exists($type, $this->bindings))
        {
            throw new InvalidArgumentException("Invalid binding type: {$type}");
        }

        $this->bindings[$type] = $value;

        return $this;
    }

    /**
     * Add a binding to the query sql.
     * 
     * @param  mixed  $value
     * @param  string  $type  ('where' by default)
     * 
     * @return $this
     * 
     * @throws \InvalidArgumentException
     */
    public function addBinding($value, $type = 'where')
    {
        if ( ! array_key_exists($type, $this->bindings))
        {
            throw new InvalidArgumentException("Invalid binding type: {$type}");
        }

        if (is_array($value))
        {
            $this->bindings[$type] = array_values(array_merge($this->bindings[$type], $value));
        }
        else
        {
            $this->bindings[$type][] = $value;
        }

        return $this;
    }

    /**
     * Merge an array of bindings into our bindings.
     * 
     * @param  \Syscodes\Database\Query\Builder  $builder
     * 
     * @return $this
     */
    public function mergeBindings(Builder $builder)
    {
        $this->bindings = array_merge_recursive($this->bindings, $builder->bindings);

        return $this;
    }

    /**
     * Get the database connection instance.
     * 
     * @return \Syscodes\Database\ConnectionInterface
     */
    public function getConnection()
    {
        return $this->connection;
    }

    /**
     * Get the database query processor instance.
     * 
     * @return \Syscodes\Database\Query\Processor
     */
    public function getProcessor()
    {
        return $this->processor;
    }

    /**
     * Get the database query grammar instance.
     * 
     * @return \Syscodes\Database\Query\Grammar
     */
    public function getGrammar()
    {
        return $this->grammar;
    }

    /**
     * Die and dump the current SQL and bindings.
     * 
     * @return void
     */
    public function dd()
    {
        dd($this->getSql(), $this->getBindings());
    }

    /**
     * Dynamically handle calls to methods on the class.
     * 
     * @param  string  $method
     * @param  array  $parameters
     * 
     * @return mixed
     * 
     * @throws \BadMethodCallException
     */
    public function __call($method, $parameters)
    {
        $classname = get_class($this);

        throw new BadMethodCallException("Call to undefined method {$classname}::{$method}()");
    }
}