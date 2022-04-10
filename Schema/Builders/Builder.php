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

namespace Syscodes\Components\Database\Schema\Builders;

/**
 * Creates a Erostrine schema builder.
 * 
 * @author Alexander Campo <jalexcam@gmail.com>
 */
class Builder
{
    /**
     * The database connection instance.
     *
     * @var \Syscodes\Components\Database\Connection
     */
    protected $connection;

    /**
     * The schema grammar instance.
     *
     * @var \Syscodes\Components\Database\Schema\Grammars\Grammar
     */
    protected $grammar;

    /**
     * The Dataprint resolver callback.
     *
     * @var \Closure
     */
    protected $resolver;

    /**
     * The default string length for migrations.
     * 
     * @var int|null $defaultStringLength
     */
    public static $defaultStringLength = 255;
}