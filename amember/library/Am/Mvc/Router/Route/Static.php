<?php
/**
 * Zend Framework
 *
 * LICENSE
 *
 * This source file is subject to the new BSD license that is bundled
 * with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://framework.zend.com/license/new-bsd
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@zend.com so we can send you a copy immediately.
 *
 * @copyright  Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @copyright  Copyright (c) 2016-2017 Alexey Presnyakov (http://www.amember.com )
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */

/** Am_Mvc_Router_Route_Abstract */
//--//require_once 'Zend/Controller/Router/Route/Abstract.php';

/**
 * StaticRoute is used for managing static URIs.
 *
 * It's a lot faster compared to the standard Route implementation.
 *
 * @package    Zend_Controller
 * @subpackage Router
 * @copyright  Copyright (c) 2005-2014 Zend Technologies USA Inc. (http://www.zend.com)
 * @license    http://framework.zend.com/license/new-bsd     New BSD License
 */
class Am_Mvc_Router_Route_Static extends Am_Mvc_Router_Route_Abstract
{

    protected $_route = null;
    protected $_defaults = [];

    public function getVersion() {
        return 1;
    }

    /**
     * Prepares the route for mapping.
     *
     * @param string $route Map used to match with later submitted URL path
     * @param array $defaults Defaults for map variables with keys as variable names
     */
    public function __construct($route, $defaults = [])
    {
        $this->_route = trim($route, self::URI_DELIMITER);
        $this->_defaults = (array) $defaults;
    }

    /**
     * Matches a user submitted path with a previously defined route.
     * Assigns and returns an array of defaults on a successful match.
     *
     * @param string $path Path used to match against this routing map
     * @return array|false An array of assigned values or a false on a mismatch
     */
    public function match($path, $partial = false)
    {
        if ($partial) {
            if ((empty($path) && empty($this->_route))
                || (substr($path, 0, strlen($this->_route)) === $this->_route)
            ) {
                $this->setMatchedPath($this->_route);
                return $this->_defaults;
            }
        } else {
            if (trim($path, self::URI_DELIMITER) == $this->_route) {
                return $this->_defaults;
            }
        }

        return false;
    }

    /**
     * Assembles a URL path defined by this route
     *
     * @param array $data An array of variable and value pairs used as parameters
     * @return string Route path with user submitted parameters
     */
    public function assemble($data = [], $reset = false, $encode = false, $partial = false)
    {
        return $this->_route;
    }

    /**
     * Return a single parameter of route's defaults
     *
     * @param string $name Array key of the parameter
     * @return string Previously set default
     */
    public function getDefault($name) {
        if (isset($this->_defaults[$name])) {
            return $this->_defaults[$name];
        }
        return null;
    }

    /**
     * Return an array of defaults
     *
     * @return array Route defaults
     */
    public function getDefaults() {
        return $this->_defaults;
    }

}
