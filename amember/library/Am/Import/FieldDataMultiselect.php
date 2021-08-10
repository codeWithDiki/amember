<?php

/** empty */
class Am_Import_FieldDataMultiselect extends Am_Import_Field
{
    protected function _setValueForRecord($record, $value)
    {
        $record->data()->set($this->getName(), preg_split('/[:,]/', $value));
    }
}