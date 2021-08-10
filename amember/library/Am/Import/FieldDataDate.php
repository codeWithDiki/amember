<?php

/** empty */
class Am_Import_FieldDataDate extends Am_Import_FieldDate
{
    protected function _setValueForRecord($record, $value)
    {
        $record->data()->set($this->getName(), $value);
    }
}