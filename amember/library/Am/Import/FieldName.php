<?php

/** empty */
class Am_Import_FieldName extends Am_Import_Field
{
    protected function _setValueForRecord($record, $value)
    {
        $names = explode(" ", $value);
        $name_l = array_pop($names);
        $name_f = implode(" ", $names);

        $record->name_f = $name_f;
        $record->name_l = $name_l;

    }
}