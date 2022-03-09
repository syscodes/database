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

namespace Syscodes\Components\Database\Erostrine\Relations;

use Syscodes\Components\Database\Erostrine\Model;
use Syscodes\Components\Database\Erostrine\Collection;
use Syscodes\Components\Database\Erostrine\Relations\Concerns\SupportModelRelations;

/**
 * Relation HasOne given on the parent model.
 * 
 * @author Alexander Campo <jalexcam@gmail.com>
 */
class HasOne extends HasOneOrMany
{
    use SupportModelRelations;
    
    /**
     * {@inheritdoc}
     */
    public function getResults()
    {
        return $this->getRelationQuery()->first() ?: $this->getDefaultFor($this->parent);
    }
    
    /**
     * {@inheritdoc}
     */
    public function initRelation(array $models, $relation): array
    {
        foreach ($models as $model) {
            $model->setRelation($relation, $this->getDefaultFor($model));
        }
        
        return $models;
    }
    
    /**
     * Match the eagerly loaded results to their parents.
     * 
     * @param  array   $models
     * @param  \Syscodes\Components\Database\Erostrine\Collection  $results
     * @param  string  $relation
     * 
     * @return array
     */
    public function match(array $models, Collection $results, $relation): array
    {
        return $this->matchOne($models, $results, $relation);
    }

    /**
     * Make a new related instance for the given model.
     * 
     * @param  \Syscodes\Components\Database\Erostrine\Model  $parent
     * 
     * @return \Syscodes\Components\Database\Erostrine\Model
     */
    protected function newRelatedInstanceFor(Model $parent)
    {
        return $this->related->newInstance()->setAttribute(
            $this->getForeignKeyName(), $parent->{$this->localKey}
        );
    }
}