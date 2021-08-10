<?php

/**
 * Storage plugins manager and common functions
 */
class Am_Plugins_Storage extends Am_Plugins
{
    public function getAvailable()
    {
        return ['disk'=>'disk', 'upload'=>'upload'] + parent::getAvailable();
    }

    public function splitPath($path)
    {
        if (ctype_digit((string)$path))
            return ['upload', $path, []];
        @list($id, $path) = explode('::', $path, 2);
        $id = filterId($id);
        @list($path, $query) = explode('?', $path, 2);
        if (strlen($query))
        {
            parse_str($query, $q);
            $query = $q;
        }
        return [$id, $path, $query];
    }

    /**
     * @param string $path storage file path
     * @param null|Am_Storage[] if specified choose file from specified plugins only
     * @return Am_Storage_File|null */
    function getFile($path, array $selectedPlugins = null)
    {
        list($id, $path) = $this->splitPath($path);
        if ($selectedPlugins === null)
        {
            $pl = $this->loadGet($id);
        } else {
            $pl = null;
            foreach ($selectedPlugins as $p)
                if ($p->getId() == $id)
                {
                    $pl = $p; break;
                }
        }
        if (!$pl) return null;
        return $pl->get($path);
    }
}
