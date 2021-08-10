<?php

/** empty */
class Am_Import_FieldMultiselect extends Am_Import_Field
{
    protected function _setValueForRecord($record, $value)
    {
        $record->{$this->getName()} = preg_split('/[:,]/', $value);
    }
}