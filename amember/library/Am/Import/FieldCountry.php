<?php

/** empty */
class Am_Import_FieldCountry extends Am_Import_Field
{
    static $countryOptions;

    public function getReadableValue($lineParsed, $partialRecord = null)
    {
        $country = $this->getValue($lineParsed, $partialRecord);
        $countryOptions = $this->getCountryOptions();
        if (isset($countryOptions[$country]))
        {
            return $countryOptions[$country];
        } else
        {
            return '';
        }
    }

    private function getCountryOptions()
    {
        if (is_null(self::$countryOptions))
        {
            self::$countryOptions = $this->getDi()->countryTable->getOptions();
        }

        return self::$countryOptions;
    }
}