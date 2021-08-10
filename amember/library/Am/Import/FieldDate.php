<?php

/** empty */
class Am_Import_FieldDate extends Am_Import_Field
{
    protected $isMustBeAssigned = false;

    protected function _buildForm(HTML_QuickForm2_Container $form)
    {
        $el = $form->addDate('field_'.$this->getName(), ['class' => 'fixed'])
            ->setLabel($this->getTitle());

        if ($this->isRequired())
        {
            $el->addRule('required', ___('This field is a requried field'));
        }
    }

    public function getValue($lineParsed, $partialRecord = null)
    {
        $rawValue = $this->getRawValue($lineParsed, $partialRecord);

        return $rawValue ? date('Y-m-d', amstrtotime($rawValue)) : '';
    }

    protected function getRawValue($lineParsed, $partialRecord = null)
    {
        if ($this->isAssigned())
        {
            return parent::getValue($lineParsed, $partialRecord);
        } else
        {
            return (isset($this->session->fieldsValue['field_'.$this->getName()])) ?
                $this->session->fieldsValue['field_'.$this->getName()] :
                '';
        }
    }

    public function getReadableValue($lineParsed, $partialRecord = null)
    {
        if ($date = $this->getValue($lineParsed, $partialRecord))
        {
            return amDate($date);
        } else
        {
            return '';
        }
    }
}