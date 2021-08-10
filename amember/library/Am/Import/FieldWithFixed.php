<?php

/** empty */
class Am_Import_FieldWithFixed extends Am_Import_Field
{
    protected $isMustBeAssigned = false;

    protected function _buildForm(HTML_QuickForm2_Container $form)
    {
        $el = $form->addText('field_'.$this->getName(), ['class' => 'fixed'])
            ->setLabel($this->getTitle());

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
}