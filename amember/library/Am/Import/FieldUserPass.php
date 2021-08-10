<?php

/** empty */
class Am_Import_FieldUserPass extends Am_Import_Field
{
    const KEY_FIXED = 'FIXED';
    const KEY_GENERATE = 'GENERATE';
    protected $isMustBeAssigned = false;

    protected function _buildForm(HTML_QuickForm2_Container $form)
    {
        $fieldGroup = $form->addElement('group', 'field_'.$this->getName())
            ->setLabel($this->getTitle());

        $fieldGroup->addElement('select', 'type')
            ->loadOptions(
                [
                    self::KEY_GENERATE => 'Generate',
                    self::KEY_FIXED => 'Fixed',
                ]
            );
        $fieldGroup->addElement('text', 'fixed', ['class' => 'fixed']);

        if ($this->isRequired())
        {
            $fieldGroup->addRule('required', ___('This field is a requried field'));
        }
    }

    protected function _setValueForRecord($record, $value)
    {
        $record->setPass($value);
    }

    public function getValue($lineParsed, $partialRecord = null)
    {
        if ($this->isAssigned())
        {
            return parent::getValue($lineParsed, $partialRecord);
        } elseif (self::KEY_FIXED == $this->session->fieldsValue['field_'.$this->getName()]['type'])
        {
            return $this->session->fieldsValue['field_'.$this->getName()]['fixed'];
        } else
        {
            return $this->getDi()->security->randomString(8);
        }
    }

    public function setValueForRecord($record, $lineParsed)
    {
        //user already exists in database
        //so we do not generate new password for him
        //but admin still can assign new password while import
        if (!$this->isAssigned() && @$record->pass)
        {
            return;
        }
        parent::setValueForRecord($record, $lineParsed);
    }
}