<?php

/**
 * Represents "array" as data source for grid
 * @package Am_Grid
 */
class Am_Grid_DataSource_Array implements Am_Grid_DataSource_Interface_Editable
{
    protected $array = [], $_order_f = null, $_order_d = null;

    public function __construct(array $array)
    {
        foreach ($array as $a)
            $this->array[$this->getHash($a)] = $a;
    }

    public function getFoundRows()
    {
        return count($this->array);
    }

    public function selectPageRecords($page, $itemCountPerPage)
    {
        return array_map(function($a) {return (object)$a;},
            array_slice($this->array, $page*$itemCountPerPage, $itemCountPerPage));
    }

    public function setOrder($fieldNameOrRaw, $desc=null)
    {
        $this->_order_f = $fieldNameOrRaw;
        $this->_order_d = $desc;

        switch (is_null($desc)) {
            case true :
                if ($fieldNameOrRaw) {
                    $this->_setOrderRaw($fieldNameOrRaw);
                }
                break;
            case false :
                $this->_setOrder($fieldNameOrRaw, $desc);
                break;
        }
    }

    protected function _setOrder($fieldName, $desc)
    {
        uasort($this->array, function($a, $b) use ($fieldName, $desc) {
            if (is_string($a->{$fieldName})) {
                return ($desc ? -1 : 1) * strcmp($a->{$fieldName}, $b->{$fieldName});
            } else {
                return ($desc ? -1 : 1) * ($a->{$fieldName} - $b->{$fieldName});
            }
        });
    }

    protected function _setOrderRaw($raw)
    {
        //@todo Parse Raw Order and use _setOrder
    }

    //this method is only for use in Am_Grid_Filter
    public function _friendGetArray()
    {
        return $this->array;
    }

    //this method is only for use in Am_Grid_Filter
    public function _friendSetArray($records)
    {
        return $this->array = $records;
    }

    //this method is only for use in Am_Grid_Filter
    public function _friendGetOrder()
    {
        return [$this->_order_f, $this->_order_d];
    }

    public function getDataSourceQuery()
    {
        throw new Am_Exception_NotImplemented(__METHOD__);
    }

    public function getIdForRecord($record)
    {
        return $this->getHash($record);
    }

    protected function getHash($record)
    {
        return md5(serialize(get_object_vars($record)));
    }

    public function getRecord($id)
    {
        return (object)$this->array[$id];
    }

    public function createRecord()
    {
        return new stdClass;
    }

    public function deleteRecord($id, $record)
    {
        unset($this->array[$id]);
        $this->persist($this->array);
    }

    public function insertRecord($record, $valuesFromForm)
    {
        foreach ($valuesFromForm as $k => $v) {
            $record->{$k} = $v;
        }
        $this->array[$this->getHash($record)] = $record;
        $this->persist($this->array);
    }

    public function updateRecord($record, $valuesFromForm)
    {
        unset($this->array[$this->getHash($record)]);

        foreach ($valuesFromForm as $k => $v) {
            $record->{$k} = $v;
        }

        $this->array[$this->getHash($record)] = $record;
        $this->persist($this->array);
    }

    protected function persist($arr)
    {
        throw new Am_Exception_NotImplemented(__METHOD__);
    }
}