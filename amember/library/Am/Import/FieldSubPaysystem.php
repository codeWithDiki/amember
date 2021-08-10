<?php

/** empty */
class Am_Import_FieldSubPaysystem extends Am_Import_Field
{
    protected $isMustBeAssigned = false;
    protected $isForAssign = true;
    private static $paysystemOptions = null;

    protected function _buildForm(HTML_QuickForm2_Container $form)
    {
        $el = $form->addElement('select', 'field_'.$this->getName())
            ->setLabel($this->getTitle())
            ->loadOptions($this->getPaysystemOptions());

        if ($this->isRequired())
        {
            $el->addRule('required', ___('This field is a requried field'));
        }
    }

    public function getValue($lineParsed, $partialRecord = null)
    {
        if ($this->isAssigned())
        {
            return parent::getValue($lineParsed, $partialRecord);
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
        $paysys_id = $this->getValue($lineParsed, $partialRecord);
        $paysystemOptions = $this->getPaysystemOptions();
        if (isset($paysystemOptions[$paysys_id]))
        {
            return $paysystemOptions[$paysys_id];
        } else
        {
            return '';
        }
    }

    private function getPaysystemOptions()
    {
        if (is_null(self::$paysystemOptions))
        {
            self::$paysystemOptions = $this->getDi()->paysystemList->getOptions();
        }

        return self::$paysystemOptions;
    }
}