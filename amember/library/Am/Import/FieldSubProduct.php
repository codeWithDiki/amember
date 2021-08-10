<?php

/** empty */
class Am_Import_FieldSubProduct extends Am_Import_Field
{
    protected $isMustBeAssigned = false;
    protected static $productOptions = null;
    protected static $productIndex = null;

    static function id($id)
    {
        if (is_null(self::$productIndex))
        {
            self::$productIndex = Am_Di::getInstance()->db
                ->selectCol('SELECT product_id, title AS ARRAY_KEY '.
                    'FROM ?_product');
        }

        return is_numeric($id) ?
            $id :
            (isset(self::$productIndex[$id]) ? self::$productIndex[$id] : '');
    }

    protected function _buildForm(HTML_QuickForm2_Container $form)
    {
        $el = $form->addElement('select', 'field_'.$this->getName())
            ->setLabel($this->getTitle())
            ->loadOptions($this->getProductOptions());

        if ($this->isRequired())
        {
            $el->addRule('required', ___('This field is a requried field'));
        }
    }

    public function getValue($lineParsed, $partialRecord = null)
    {
        if ($this->isAssigned())
        {
            return self::id(parent::getValue($lineParsed, $partialRecord));
        } elseif (isset($this->session->fieldsValue['field_'.$this->getName()]))
        {
            return $this->session->fieldsValue['field_'.$this->getName()];
        } else
        {
            return '';
        }
    }

    public function getReadableValue($lineParsed, $partialRecord = null)
    {
        $product_id = $this->getValue($lineParsed, $partialRecord);
        $productOptions = $this->getProductOptions();
        if (isset($productOptions[$product_id]))
        {
            return $productOptions[$product_id];
        } else
        {
            return '';
        }
    }

    private function getProductOptions()
    {
        if (is_null(self::$productOptions))
        {
            self::$productOptions = $this->getDi()->productTable->getOptions();
        }

        return self::$productOptions;
    }
}