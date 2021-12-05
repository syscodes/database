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
 * @copyright   Copyright (c) 2019 - 2021 Alexander Campo <jalexcam@gmail.com>
 * @license     https://opensource.org/licenses/BSD-3-Clause New BSD license or see https://lenevor.com/license or see /license.md
 */

namespace Syscodes\Components\Database\Elusen;

use ArrayAccess;
use LogicException;
use Syscodes\Components\Collections\Arr;
use Syscodes\Components\Collections\Str;
use Syscodes\Components\Contracts\Support\Arrayable;
use Syscodes\Components\Database\Query\Builder as QueryBuilder;
use Syscodes\Components\Collections\Collection as BaseCollection;

/**
 * Creates a ORM model instance.
 * 
 * @author Alexander Campo <jalexcam@gmail.com>
 */
class Model implements Arrayable, ArrayAccess
{
	/**
	 * The database connection name.
	 * 
	 * @var string|null $connection
	 */
	protected $connection;

	/**
	 * The primary key for the model.
	 * 
	 * @var string $primaryKey
	 */
	protected $primaryKey =  'id';

	/**
	 * The table associated with the model.
	 * 
	 * @var string $table
	 */
	protected $table;

	/**
	 * Constructor. The create new Model instance.
	 *
	 * @param  array  $attributes
	 *
	 * @return void
	 */
	public function __construct(array $attributes = [])
	{
		
	}

	/**
	 * Create a new Elusen query builder for the model.
	 * 
	 * @param  \Syscodes\Components\Database\Query\Builder  $builder
	 * 
	 * @return \Syscodes\Components\Database\Elusen\Builder
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
		return new QueryBuilder(

		);
	}
}