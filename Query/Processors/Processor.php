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
 * @copyright   Copyright (c) 2019 - 2025 Alexander Campo <jalexcam@gmail.com>
 * @license     https://opensource.org/licenses/BSD-3-Clause New BSD license or see https://lenevor.com/license or see /license.md
 */
 
namespace Syscodes\Components\Database\Query\Processors;

use Syscodes\Components\Database\Query\Builder;

/**
 * Allows identify the ID field and results of SELECT query in a table.
 */
class Processor
{
    /**
     * Process the results of a "select" query.
     * 
     * @param  \Syscodes\Components\Database\Query\Builder  $builder
     * @param  array  $results
     * 
     * @return array
     */
    public function processSelect(Builder $builder, $results)
    {
        return $results;
    }

    /**
     * Process an  "insert get ID" query.
     * 
     * @param  \Syscodes\Components\Database\Query\Builder  $builder
     * @param  string  $sql
     * @param  array  $values
     * @param  string  $sequence  
     * 
     * @return int
     */
    public function processInsertGetId(Builder $builder, $sql, $values, $sequence = null): int
    {
        $builder->getConnection()->insert($sql, $values);

        $id = $builder->getConnection()->getPdo()->lastInsertId($sequence);

        return is_numeric($id) ? (int) $id : $id;
    }

    /**
     * Process the results of a column listing query.
     * 
     * @param  array  $results
     * 
     * @return array
     */
    public function processColumnListing($results): array
    {
        return $results;
    }
}