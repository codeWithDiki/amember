<?php


class Am_Import_Field
{
    protected $title;
    protected $name;
    protected $isRequired = false;
    /** @var Am_Session_Ns */
    protected $session = null;
    //field can be fetched from CSV file
    protected $isForAssign = true;
    protected $isMustBeAssigned = true;
    /** @var Am_Di */
    protected $di;

    public function __construct($name, $title, $isRequired = false)
    {
        $this->name = $name;
        $this->title = $title;
        $this->isRequired = $isRequired;
    }

    public function setDi(Am_Di $di)
    {
        $this->di = $di;
    }

    /**
     * @return Am_Di
     */
    public function getDi()
    {
        return $this->di;
    }

    public function setSession(Am_Session_Ns $session)
    {
        $this->session = $session;
    }

    public function buildForm(HTML_QuickForm2_Container $form)
    {
        if (!$this->isAssigned()) {
            $this->_buildForm($form);
        }
    }

    protected function _buildForm(HTML_QuickForm2_Container $form)
    {
        //nop
    }

    public function isAssigned()
    {
        return isset($this->session->fieldsMap[$this->getName()]);
    }

    //field can be fetched from CSV file
    public function isForAssign()
    {
        return $this->isForAssign;
    }

    public function isRequired()
    {
        return $this->isRequired;
    }

    //field should be used in import process (Required or Defined)
    public function isForImport()
    {
        return $this->isRequired() ||
            ($this->isAssigned() || $this->isDefined());
    }

    public function isDefined()
    {
        //try to guess if this field is defined
        //getValue should return non empty value
        //in this case
        static $dummyArray;
        if (!is_array($dummyArray)) {
            $dummyArray = range(1, 30);
        }
        return!('' === $this->getValue($dummyArray));
    }

    //this field can be fetched only from CSV file
    public function isMustBeAssigned()
    {
        return $this->isMustBeAssigned;
    }

    public function getAssignedIndex()
    {
        if (isset($this->session->fieldsMap[$this->getName()])) {
            return $this->session->fieldsMap[$this->getName()];
        } else {
            return false;
        }
    }

    public function getName()
    {
        return $this->name;
    }

    public function getTitle()
    {
        return $this->title;
    }

    public function setValueForRecord($record, $lineParsed)
    {
        if ($this->isForImport()) {
            $this->_setValueForRecord($record, $this->getValue($lineParsed, $record));
        }
    }

    protected function _setValueForRecord($record, $value)
    {
        $record->{$this->getName()} = $value;
    }

    public function getValue($lineParsed, $partialRecord = null)
    {
        if ($this->isAssigned()) {
            return trim($lineParsed[$this->getAssignedIndex()]);
        } else {
            return '';
        }
    }

    public function getReadableValue($lineParsed, $partialRecord = null)
    {
        return $this->getValue($lineParsed, $partialRecord);
    }
}
