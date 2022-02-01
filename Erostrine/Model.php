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

namespace Syscodes\Components\Database\Erostrine;

use ArrayAccess;
use LogicException;
use Syscodes\Components\Support\Str;
use Syscodes\Components\Collections\Arr;
use Syscodes\Components\Contracts\Support\Arrayable;
use Syscodes\Components\Support\Traits\ForwardsCalls;
use Syscodes\Components\Database\ConnectionResolverInterface;
use Syscodes\Components\Database\Erostrine\Concerns\HasEvents;
use Syscodes\Components\Database\Query\Builder as QueryBuilder;
use Syscodes\Components\Collections\Collection as BaseCollection;
use Syscodes\Components\Database\Erostrine\Concerns\HasAttributes;
use Syscodes\Components\Database\Erostrine\Concerns\HidesAttributes;
use Syscodes\Components\Database\Erostrine\Concerns\GuardsAttributes;
use Syscodes\Components\Database\Erostrine\Exceptions\MassAssignmentException;

/**
 * Creates a ORM model instance.
 * 
 * @author Alexander Campo <jalexcam@gmail.com>
 */
class Model implements Arrayable, ArrayAccess
{
	use HasAttributes,
	    HasEvents,
		HidesAttributes,
	    GuardsAttributes,
	    ForwardsCalls;

	/**
	 * The database connection name.
	 * 
	 * @var string|null $connection
	 */
	protected $connection;

	/**
	 * Indicates if the model exists.
	 * 
	 * @var bool $exists
	 */
	protected $exists = false;

	/**
	 * Indicates if the IDs are auto-incrementing.
	 * 
	 * @var bool $incrementing
	 */
	protected $incrementing = true;

	/**
	 * The primary key for the model.
	 * 
	 * @var string $primaryKey
	 */
	protected $primaryKey = 'id';

	/**
	 * The table associated with the model.
	 * 
	 * @var string $table
	 */
	protected $table;

	/**
	 * Indicates if the model should be timestamped.
	 * 
	 * @var bool $timestamps
	 */
	protected $timestamps = true;

	/**
	 * The array of booted models.
	 * 
	 * @var array $booted
	 */
	protected static $booted = [];

	/**
	 * The event dispatcher instance.
	 * 
	 * @var \Syscodes\Components\Contracts\Events\Dispatcher $dispatcher
	 */
	protected static $dispatcher;
	
	/**
	 * The connection resolver instance.
	 * 
	 * @var \Syscodes\Components\Database\ConnectionResolverInterface
	 */
	protected static $resolver;

	/**
	 * The name of the "created at" column.
	 * 
	 * @var string|null
	 */
	const CREATED_AT = 'created_at';

	/**
	 * The name of the "updated at" column.
	 * 
	 * @var string|null
	 */
	const UPDATED_AT = 'updated_at';

	/**
	 * Constructor. The create new Model instance.
	 *
	 * @param  array  $attributes
	 *
	 * @return void
	 */
	public function __construct(array $attributes = [])
	{
		$this-> bootIfNotBooted();

		$this->syncOriginal();

		$this->fill($attributes);
	}

	/**
	 * Check if the model needs to be booted and if so, do it.
	 * 
	 * @return void
	 */
	public function bootIfNotBooted(): void
	{
		if ( ! isset(static::$booted[static::class])) {
			static::$booted[static::class] = true;

			$this->fireModelEvent('booting', false);

			static::boot();

			$this->fireModelEvent('booted', false);
		}
	}

	/**
	 * The "booting" method of the model.
	 * 
	 * @return void
	 */
	public static function boot(): void
	{
		$class = static::class;

		static::$mutatorCache[$class] = [];

		foreach (get_class_methods($class) as $method) {
			if (preg_match('/^get(.+)Attribute$/', $method, $matches)) {
				if (static::$snakeAttributes) {
					$matches[1] = Str::snake($matches[1]);
				}

				static::$mutatorCache[$class][] = lcfirst($matches[1]);
			}
		}

		static::bootTraits();
	}

	/**
	 * Boot all of the bootable traits on the model.
	 * 
	 * @return void
	 */
	protected static function bootTraits()
	{
		$class = static::class;

		foreach (class_recursive($class) as $trait) {
			if (method_exists($class, $method = 'boot'.class_basename($trait))) {
				forward_static_call([$class, $method]);
			}
		}
	}
	
	/**
	 * Get the table qualified key name.
	 * 
	 * @return string
	 */
	public function getQualifiedKeyName(): string
	{
		return $this->qualifyColumn($this->getKeyName());
	}
	
	/**
	 * Qualify the given column name by the model's table.
	 * 
	 * @param  string  $column
	 * 
	 * @return string
	 */
	public function qualifyColumn($column): string
	{
		if (Str::contains($column, '.')) {
			return $column;
		}
		
		return $column;
	}

	/**
	 * Get all of the models from the database.
	 * 
	 * @param  array|mixed  $columns
	 * 
	 * @return \Syscodes\Components\Database\Erostrine\Collection|static[]
	 */
	public static function all($columns = ['*'])
	{
		return static::query()->get(
			is_array($columns) ? $columns : func_get_args()
		);
	}

	/**
	 * Begin querying the model on a given connection. 
	 * 
	 * @param  string|null	$connection
	 * 
	 * @return \Syscodes\Components\Database\Erostrine\Builder
	 */
	public static function on($connection = null)
	{
		$instance = new static;
		$instance->setConnection($connection);

		return $instance->newQuery();
	}
	
	/**
	 * Get the primary key for the model.
	 * 
	 * @return string
	 */
	public function getKeyName(): string
	{
		return $this->primaryKey;
	}
	
	/**
	 * Get the value of the model's primary key.
	 * 
	 * @return mixed
	 */
	public function getKey()
	{
		return $this->getAttribute($this->getKeyName());
	}
	
	/**
	 * Set the primary key for the model.
	 * 
	 * @param  string  $key
	 * 
	 * @return void
	 */
	public function setKeyName($key): void
	{
		$this->primaryKey = $key;
	}
	
	/**
	 * Save a new model and return the instance.
	 * 
	 * @param  array  $attributes
	 * 
	 * @return static
	 */
	public static function create(array $attributes)
	{
		$model = new static($attributes);
		$model->save();

		return $model;
	}

	/**
	 * Update the model in the database.
	 * 
	 * @param  array  $attributes
	 * @param  array  $options
	 * 
	 * @return bool
	 */
	public function update(array $attributes = [], array $options = []): bool
	{
		if ( ! $this->exists) {
			return false;
		}

		return $this->fill($attributes)->save($options);
	}

	/**
	 * Save the model to the database.
	 * 
	 * @param  array  $options
	 * 
	 * @return bool
	 */
	public function save(array $options = []): bool
	{
		$query = $this->newQuery();

		if ($this->fireModelEvent('saving') === false) {
            return false;
        }

		if ($this->exists) {
			$saved = $this->isDirty() ? $this->performUpdate($query) : true;
		} else {
			$saved = $this->performInsert($query);
		}

		if ($saved) {
			$this->fireModelEvent('saved', false);
			
			$this->syncOriginal();
		}

		return $saved;
	}

	/**
	 * Perform a model update operation.
	 * 
	 * @param  \Syscodes\Components\Database\Erostrine\Builder $builder
	 * 
	 * @return bool
	 */
	public function performUpdate(Builder $builder): bool
	{
		if ($this->fireModelEvent('updating') === false) {
			return false;
		}

		$dirty = $this->getDirty();
		
		if (count($dirty) > 0) {
			$this->setKeysForSaveQuery($builder)->update($dirty);
			
			$this->fireModelEvent('updated', false);
		}

		return true;
	}
	
	/**
	 * Set the keys for a save update query.
	 * 
	 * @param  \Syscodes\Components\Database\Erostrine\Builder  $query
	 * 
	 * @return \Syscodes\Components\Database\Erostrine\Builder
	 */
	protected function setKeysForSaveQuery($query)
	{
		$query->where($this->getKeyName(), '=', $this->getKeyForSaveQuery());
		
		return $query;
	}
	
	/**
	 * Get the primary key value for a save query.
	 * 
	 * @return mixed
	 */
	protected function getKeyForSaveQuery()
	{
		return $this->original[$this->getKeyName()] ?? $this->getKey();
	}

	/**
	 * Perform a model insert operation.
	 * 
	 * @param  \Syscodes\Components\Database\Erostrine\Builder  $builder
	 * 
	 * @return bool
	 */
	public function performInsert(Builder $builder): bool
	{
		$attributes = $this->getAttributes();

		if ($this->getIncrementing()) {
			$this->insertAndSetId($builder, $attributes);
		} else {
			if (empty($attributes)) {
				return true;
			}
			
			$builder->insert($attributes);
		}

		$this->exists = true;

		return true;
	}
	
	/**
	 * Insert the given attributes and set the ID on the model.
	 * 
	 * @param  \Syscodes\Components\Database\Erostrine\Builder  $builder
	 * @param  array  $attributes
	 * 
	 * @return void
	 */
	protected function insertAndSetId(Builder $builder, $attributes)
    {
		$id = $builder->insertGetId($attributes, $keyName = $this->getKeyName());
		
		$this->setAttribute($keyName, $id);
	}

	/**
	 * Delete the model from the database.
	 * 
	 * @return bool|null
	 */
	public function delete()
	{
		if (is_null($this->getKeyName())) {
			throw new LogicException('No primary key defined on model');
		}

		if ($this->fireModelEvent('deleting') === false) {
			return false;
		}

		if ( ! $this->exists) {
			return;
		}

		$this->performDeleteOnModel();

		$this->fireModelEvent('deleted', false);

		return true;
	}
	
	/**
	 * Perform the actual delete query on this model instance.
	 * 
	 * @return void
	 */
	protected function performDeleteOnModel(): self
	{
		$this->setKeysForSaveQuery($this->newQuery())->delete();
		
		$this->exists = false;

		return $this;
	}

	/**
	 * Delete the model from the database within a transaction.
	 * 
	 * @return bool|null
	 * 
	 * @throws \Throwable
	 */
	public function deleteOrFail()
	{
		if ( ! $this->exists) {
			return;
		}

		return $this->getConnection()->transaction(function () {
			return $this->delete();
		});
	}

	/**
	 * Deleting the models for the given IDs.
	 * 
	 * @param  \Syscodes\Component\Collections\Collection|array|int|string  $ids
	 * 
	 * @return int
	 */
	public static function deleting($ids): int
	{
		$ids = is_array($ids) ? $ids : func_get_args();

		if (count($ids) === 0) {
			return 0;
		}

		$key = ($instance = new static)->getKeyName();

		$count = 0;

		foreach ($instance->whereIn($key, $ids)->get() as $model) {
			if ($model->delete()) {
				$count++;
			}
		}

		return $count;
	}
	
	/** 
	 * Get the value indicating whether the IDs are incrementing.
	 * 
	 * @return bool
	 */
	public function getIncrementing(): bool
	{
		return $this->incrementing;
    }
	
	/**
	 * Set the value indicating whether the IDs are incrementing.
	 * 
	 * @param  bool  $value
	 * 
	 * @return self
	 */
	public function setIncrementing($value): self
	{
		$this->incrementing = $value;
		
		return $this;
	}

	/**
	 * Begin querying the model.
	 * 
	 * @return \Syscodes\Components\Database\Erostrine\Builder
	 */
	public static function query()
	{
		return (new static)->newQuery();
	}

	/**
	 * Get a new query builder for the model's table.
	 * 
	 * @return \Syscodes\Components\Database\Erostrine\Builder
	 */
	public function newQuery()
	{
		return $this->newQueryBuilder(
					$this->newBaseQueryBuilder()
				)->setModel($this);
	}

	/**
	 * Create a new Erostrine query builder for the model.
	 * 
	 * @param  \Syscodes\Components\Database\Query\Builder  $builder
	 * 
	 * @return \Syscodes\Components\Database\Erostrine\Builder
	 */
	public function newQueryBuilder(QueryBuilder $builder)
	{
		return new Builder($builder);
	}
	
	/**
	 * Get a new query builder instance for the connection.
	 * 
	 * @return \Syscodes\Components\Database\Query\Builder
	 */
	protected function newBaseQueryBuilder()
	{
		$connection = $this->getConnection();

		$grammar   = $connection->getQueryGrammar();
		$processor = $connection->getPostProcessor();

		return new QueryBuilder(
			$connection, $grammar, $processor
		);
	}
	
	/**
	 * Fill the model with an array of attributes.
	 * 
	 * @param  array  $attributes
	 * 
	 * @return self
	 * 
	 * @throws \Syscodes\Components\Database\Erostrine\Exceptions\MassAssignmentException
	 */
	public function fill(array $attributes): self
	{
		$totallyGuarded = $this->totallyGuarded();
		
		foreach ($this->fillableFromArray($attributes) as $key => $value) {
			if ($this->isFillable($key)) {
				$this->setAttribute($key, $value);
			} else if ($totallyGuarded) {
				throw new MassAssignmentException(sprintf(
					'Add [%s] to fillable property to allow mass assignment on [%s]',
					$key, get_class($this)
				));
			}
		}
		
		return $this;
	}
	
	/**
	 * Create a new instance of the given model.
	 * 
	 * @param  array  $attributes
	 * @param  bool   $exists
	 * 
	 * @return static
	 */
	public function newInstance($attributes = [], $exists = false)
	{
		$model = new static((array) $attributes);		
		
		$model->exists = $exists;
		
		$model->setConnection(
            $this->getConnectionName()
        );

        $model->setTable($this->getTable());
		
		return $model;
	}
	
	/**
	 * Create a new model instance that is existing.
	 * 
	 * @param  array  $attributes
	 * @param  string|null  $connection
	 * 
	 * @return static
	 */
	public function newFromBuilder($attributes = [], $connection = null)
	{
		$instance = $this->newInstance([], true);		
		$instance->setRawAttributes((array) $attributes, true);
		$instance->setConnection($connection ?: $this->getConnectionName());
		
		return $instance;
	}

	/**
	 * Create a new ORM Collection instance.
	 * 
	 * @param  array  $models
	 * 
	 * @return \Syscodes\Components\Database\Collection
	 */
	public function newCollection(array $models = [])
	{
		return new Collection($models);
	}

	/**
	 * Get the table associated with the model.
	 * 
	 * @return string
	 */
	public function getTable(): string
	{
		$class = class_basename($this);

		return $this->table ?? Str::snake(Str::plural($class));
	}

	/**
	 * Set the table associated with the model.
	 * 
	 * @param  string  $table
	 * 
	 * @return void
	 */
	public function setTable(string $table): void
	{
		$this->table = $table;
	}

	/**
	 * Get the default foreign key name for the model.
	 * 
	 * @return string
	 */
	public function getForeignKey(): string
	{
		$name = class_basename($this);

		return sprintf("%s_{$this->getKeyName()}", Str::snake($name));
	}

	/**
	 * {@inheritdoc}
	 */
	public function toArray()
	{
		
	}
	
	/**
	 * Get the database connection for the model.
	 * 
	 * return \Syscodes\Components\Database\Database\Connection
	 */
	public function getConnection()
	{
		return static::resolveConnection($this->getConnectionName());
	}
	
	/**
	 * Get the current connection name for the model.
	 * 
	 * @return string
	 */
	public function getConnectionName()
	{
		return $this->connection;
	}
	
	/**
	 * Set the connection associated with the model.
	 * 
	 * @param  string  $name
	 * 
	 * @return self
	 */
	public function setConnection($name)
	{
		$this->connection = $name;
		
		return $this;
	}

	/**
	 * The resolver connection a instance.
	 * 
	 * @param  string|null  $connection
	 * 
	 * @return \Syscodes\Components\Database\Connections\Connection
	 */
	public static function resolveConnection(string $connection = null)
	{
		return static::$resolver->connection($connection);
	}

	/**
	 * Get the connectiion resolver instance.
	 * 
	 * @return \Syscodes\Components\Database\ConnectionResolverInstance
	 */
	public static function getConnectionResolver()
	{
		return static::$resolver;
	}

	/**
	 * Set the connection resolver instance.
	 * 
	 * @param  \Syscodes\Components\Database\ConnectionResolverInstance  $resolver
	 * 
	 * @return void
	 */
	public static function setConnectionResolver(ConnectionResolverInterface $resolver): void
	{
		static::$resolver = $resolver;
	}

	/**
	 * Magic method.
	 * 
	 * Dynamically retrieve attributes on the model.
	 * 
	 * @param  string  $key
	 * 
	 * @return mixed
	 */
	public function __get($key)
	{
		return $this->getAttribute($key);
	}

	/**
	 * Magic method.
	 * 
	 * Dynamically set attributes on the model.
	 * 
	 * @param  string  $key
	 * @param  mixed  $value
	 * 
	 * @return void
	 */
	public function __set($key, $value)
	{
		return $this->setAttribute($key, $value);
	}

	/*
	|-----------------------------------------------------------------
	| ArrayAccess Methods
	|-----------------------------------------------------------------
	*/

	/**
	 * Whether or not an offset exists.
	 * 
	 * @param  string  $offset
	 * 
	 * @return bool
	 */
	public function offsetExists($offset)
	{
		return ! is_null($this->getAttribute($offset));
	}

	/**
	 * Returns the value at specified offset.
	 * 
	 * @param  string  $offset
	 * 
	 * @return mixed
	 */
	public function offsetGet($offset)
	{
		return $this->getAttribute($offset);
	}

	/**
	 * Set a value to the specified offset
	 * 
	 * @param  string  $offset
	 * @param  mixed  $value
	 * 
	 * @return void
	 */
	public function offsetSet($offset, $value)
	{
		$this->setAttribute($offset, $value);
	}

	/**
	 * Unset the value for a given offset.
	 * 
	 * @param  string  $offset
	 * 
	 * @return void
	 */
	public function offsetUnset($offset)
	{
		unset($this->attributes[$offset]);
	}

	/**
	 * Determine if an attribute or relation exists on the model.
	 * 
	 * @param  string  $key
	 * 
	 * @return bool
	 */
	public function __isset($key)
	{
		return $this->offsetExists($key);
	}

	 /**
     * Unset an attribute on the model.
     *
     * @param  string  $key
	 * 
     * @return void
     */
    public function __unset($key)
    {
        $this->offsetUnset($key);
    }

	/**
	 * Magic method.
     * 
     * Dynamically handle method calls into the model instance.
     * 
     * @param  string  $method
     * @param  array  $parameters
     * 
     * @return mixed
	 */
	public function __call($method, $parameters)
    {
		return $this->forwardCallTo($this->newQuery(), $method, $parameters);
    }

	/**
	 * Magic method.
     * 
     * Dynamically handle static method calls into the model instance.
     * 
     * @param  string  $method
     * @param  array  $parameters
     * 
     * @return mixed
	 */
	public static function __callStatic($method, $parameters)
    {
		return (new static)->{$method}(...$parameters);
    }
}