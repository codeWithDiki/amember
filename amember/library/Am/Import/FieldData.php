<?php

/** empty */
class Am_Import_FieldData extends Am_Import_Field
{
    protected function _setValueForRecord($record, $value)
    {
        $record->data()->set($this->getName(), $value);
    }
}