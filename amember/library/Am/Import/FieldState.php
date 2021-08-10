<?php

/** empty */
class Am_Import_FieldState extends Am_Import_Field
{
    static $stateOptions;

    public function getReadableValue($lineParsed, $partialRecord = null)
    {
        $state = $this->getValue($lineParsed, $partialRecord);
        $stateOptions = $this->getStateOptions();
        if (isset($stateOptions[$state]))
        {
            return $stateOptions[$state];
        } else
        {
            return $state;
        }
    }

    private function getStateOptions()
    {
        if (is_null(self::$stateOptions))
        {
            $res = $this->getDi()->db->selectCol("SELECT state as ARRAY_KEY,
                    CASE WHEN tag<0 THEN CONCAT(title, ' (disabled)') ELSE title END
                    FROM ?_state");
            self::$stateOptions = $res;
        }

        return self::$stateOptions;
    }
}