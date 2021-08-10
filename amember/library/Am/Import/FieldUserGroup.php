<?php

class Am_Import_FieldUserGroup extends Am_Import_Field
{
    static $groupOptions;

    public function getReadableValue($lineParsed, $partialRecord = null)
    {
        $groupIds = explode(',', $this->getValue($lineParsed, $partialRecord));
        $groupOptions = $this->getGroupOptions();
        return implode(', ', array_filter(array_map(function($_) use ($groupOptions) {
            return isset($groupOptions[$_]) ? $groupOptions[$_] : null;
        }, $groupIds)));
    }

    protected function _setValueForRecord($record, $value)
    {
        $groups = $this->getGroupOptions();
        $groupIds = array_filter(explode(',', $value), function($_) use ($groups) { return isset($groups[$_]); });
        $record->setGroups($groupIds);
    }

    private function getGroupOptions()
    {
        if (is_null(self::$groupOptions))
        {
            self::$groupOptions = $this->getDi()->userGroupTable->getOptions();
        }

        return self::$groupOptions;
    }
}