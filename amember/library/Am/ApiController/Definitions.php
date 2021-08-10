<?php

/**
 * Defines controllers for API calls in some scope : rest-api, oauth api, admin api, etc.
 * Implemented to have single point of knowledge for nested API definitions
 * Simple routes are not enough here
 */

class Am_ApiController_Definitions
{
    protected $defs = [];
    protected $comments = [];
    protected $methods = [];

    /**
     * @param $alias like 'user' or 'invoice-item'
     * @param Am_ApiController_Base|string|callable $classOrObject or classname or callable to creation
     */
    function add($alias, $classOrObject, $comment = null, $methods = [])
    {
        $this->defs[$alias] = $classOrObject;
        $this->comments[$alias] = isset($comment) ? $comment : ucfirst($alias);
        $this->methods[$alias] = (array)$methods;
        return $this;
    }

    function get($alias)
    {
        if (!isset($this->defs[$alias]))
            throw new Am_Exception_InternalError("No definition [$alias] found in " . __METHOD__);
        return $this->defs[$alias];
    }

    function comment($alias, $set = null)
    {
        if (null !== $set)
        {
            $this->comments[$alias] = $set;
            return $this;
        }
        return $this->comments[$alias];
    }

    /**
     * @param $alias
     * @param array|null $httpMethods
     * @return $this|mixed
     */
    function methods($alias, array $httpMethods = null)
    {
        if (null !== $httpMethods)
        {
            $this->methods[$alias] = $httpMethods;
            return $this;
        }
        return $this->methods[$alias];
    }

    /**
     * @param string $alias
     * @param array $args must have 'di' passed in array
     * @return Am_ApiController_Base
     * @throws Am_Exception_InternalError
     */
    function create($alias, array $args)
    {
        $d = $this->get($alias);
        if (is_callable($d))
            return $d($args['di']);
        elseif (is_string($d)) {
            return new $d($args['di']);
        } elseif (is_object($d)) {
            return $d;
        } else {
            throw new Am_Exception_InternalError("Wrong definition set for [$alias] in " . __METHOD__);
        }
    }


    function getAll()
    {
        $ret = [];
        foreach ($this->defs as $k => $v)
            $ret[$k] = [
                'def' => $v,
                'comment' => $this->comments[$k],
                'methods' => $this->methods[$k],
            ];
        return $ret;
    }

    function count()
    {
        return count($this->defs);
    }

}
