<?php

class Am_Form_Admin_CustomField extends Am_Form_Admin
{
    protected $record, $table;

    function __construct($record)
    {
        $this->record = $record;
        parent::__construct('fields');
    }

    function setTable(Am_Table_WithData $table)
    {
        $this->table = $table;
    }

    function init()
    {
        $name = $this->addText('name')
            ->setLabel(___('Field Name'));

        if (isset($this->record->name)) {
            $name->setAttribute('disabled', 'disabled');
            $name->setValue($this->record->name);
        } else {
            $name->addRule('required');
            $name->addRule('callback', ___('Please choose another field name. This name is already used'), [$this, 'checkName']);
            $name->addRule('regex', ___('Name must be entered and it may contain lowercase letters, underscores and digits'), '/^[a-z][a-z0-9_]+$/');
            $name->addRule('length', ___('Length of field name must be from %d to %d', 1, 64), [1,64]);
        }

        $title = $this->addText('title', ['class' => 'translate'])
            ->setLabel(___('Field Title'));
        $title->addRule('required');

        $this->addTextarea('description', ['class' => 'translate'])
            ->setLabel(___("Field Description\n" .
                    'for dispaying on signup and profile editing screen (for user)'));

        $sql = $this->addAdvRadio('sql')
            ->setLabel(___("Field Type\n" .
                    'sql field will be added to table structure, common field ' .
                    'will not, we recommend you to choose second option'))
            ->loadOptions([
                1 => ___('SQL (could not be used for multi-select and checkbox fields)'),
                0 => ___('Not-SQL field (default)'),
            ])
            ->setValue(0);

        $sql->addRule('required');

        $sql_type = $this->addSelect('sql_type')
            ->setLabel(___("SQL field type\n" .
                    'if you are unsure, choose first type (string)'))
            ->loadOptions([
                '' => '-- ' . ___('Please choose') . '--',
                'VARCHAR(255)' => ___('String') . ' (VARCHAR(255))',
                'TEXT' => ___('Text (string data)'),
                'MEDIUMTEXT' => ___('Text (unlimited length string data)'),
                'BLOB' => ___('Blob (binary data)'),
                'MEDIUMBLOB' => ___('Blob (unlimited length binary data)'),
                'INT' => ___('Integer field (only numbers)'),
                'DECIMAL(12,2)' => ___('Numeric field') . ' (DECIMAL(12,2))',
            ]);

        $sql_type->addRule('callback', ___('This field is requred'), [
            'callback' => [$this, 'checkSqlType'],
            'arguments' => ['fieldSql' => $sql],
        ]);

        $this->addAdvRadio('type')
            ->setLabel(___('Display Type'))
            ->loadOptions($this->getTypes())
            ->setValue('text');

        $this->addElement('options_editor', 'values', ['class' => 'props'])
            ->setLabel(___('Field Values'))
            ->setValue([
                'options' => [],
                'default' => [],
            ]);

        $textarea = $this->addGroup()
            ->setLabel(___("Size of textarea field\n" .
                'Columns × Rows'));
        $textarea->setSeparator(' ');
        $textarea->addText('cols', ['size' => 6, 'class' => 'props'])
            ->setValue(20);
        $textarea->addText('rows', ['size' => 6, 'class' => 'props'])
            ->setValue(5);

        $this->addText('size', ['class' => 'props'])
            ->setLabel(___('Size of input field'))
            ->setValue(20);

        $this->addText('placeholder', ['class' => 'translate'])
            ->setLabel(___("Field Placeholder\nspecifies a short hint that describes the expected value of an input field"));

        $this->addText('default', ['class' => 'props'])
            ->setLabel(___("Default value for field\n(that is default value for inputs, not SQL DEFAULT)"));

        $this->addTextarea('default', ['class' => 'props'])
            ->setLabel(___("Default value for field\n(that is default value for inputs, not SQL DEFAULT)"));

        $this->addMagicSelect('validate_func')
            ->setLabel(___('Validation'))
            ->loadOptions([
                'required' => ___('Required Value'),
                'integer' => ___('Integer Value'),
                'numeric' => ___('Numeric Value'),
                'email' => ___('E-Mail Address'),
                'emails' => ___('List of E-Mail Address'),
                'url' => ___('URL'),
                'ip' => ___('IP Address'),
            ]);

        $jsCode = <<<CUT
(function($){
	prev_opt = null;

    jQuery("input[name=default]").change(function(){
       jQuery("textarea[name=default]").val(jQuery(this).val());
    });

    jQuery("input[textarea=default]").change(function(){
        jQuery("input[name=default]").val(jQuery(this).val());
    });

    jQuery("[name=type]").click(function(){
        toggleAdditionalFields(this);
    });

    jQuery("[name=type]:checked").each(function(){
        toggleAdditionalFields(this);
    });

    jQuery("[name=sql]").click(function(){
        toggleSQLType(this);
    })

    jQuery("[name=sql]:checked").each(function(){
        toggleSQLType(this);
    });

    function toggleSQLType(radio)
    {
        if (radio.checked && radio.value == 1) {
            jQuery("select[name=sql_type]").closest(".am-row").show();
        } else {
            jQuery("select[name=sql_type]").closest(".am-row").hide();
        }
    }

    function clear_sql_types()
    {
        var elem = jQuery("select[name='sql_type']");
        if (elem.val()!="TEXT") {
            prev_opt = elem.val();
            elem.val("TEXT");
        }
    }

    function back_sql_types(){
        var elem = jQuery("select[name='sql_type']");
        if ((elem.val()=="TEXT") && prev_opt)
            elem.val(prev_opt);
    }

    function switchDefaults(isMultiline)
    {
        if (isMultiline) {
            jQuery("input[name=default]").attr('disabled', 'disabled');
            jQuery("textarea[name=default]").removeAttr('disabled');
        } else {
            jQuery("textarea[name=default]").attr('disabled', 'disabled');
            jQuery("input[name=default]").removeAttr('disabled');
        }
    }

    function toggleAdditionalFields(radio) {
        jQuery(".props").closest(".am-row").hide();
        if ( radio.checked ) {
            switch (jQuery(radio).val()) {
                case 'upload':
                    switchDefaults(false);
                    jQuery("input[name=size],input[name=default],input[name=placeholder]").closest(".am-row").hide();
                    clear_sql_types();
                    break;
                case 'upload_multiple':
                    switchDefaults(false);
                    jQuery("input[name=size],input[name=default],input[name=placeholder]").closest(".am-row").hide();
                    clear_sql_types();
                    break;
                case 'text':
                    switchDefaults(false);
                    jQuery("input[name=size],input[name=default],input[name=placeholder]").closest(".am-row").show();
                    back_sql_types();
                    break;
                case 'textarea':
                    switchDefaults(true);
                    jQuery("input[name=cols],input[name=rows],textarea[name=default],input[name=placeholder]").closest(".am-row").show();
                    clear_sql_types();
                    break;
                case 'single_checkbox':
                    switchDefaults(false);
                    jQuery("input[name=placeholder]").closest(".am-row").hide();
                    back_sql_types();
                    break;
                case 'date':
                    switchDefaults(false);
                    jQuery("input[name=placeholder]").closest(".am-row").hide();
                    jQuery("input[name=default]").closest(".am-row").show();
                    clear_sql_types();
                    break;
                case 'multi_select':
                    switchDefaults(false);
                    jQuery("input[name=placeholder]").closest(".am-row").hide();
                    jQuery("input[name=values],input[name=size]").closest(".am-row").show();
                    clear_sql_types();
                    break;
                case 'select':
                    switchDefaults(false);
                    jQuery("input[name=placeholder]").closest(".am-row").hide();
                    jQuery("input[name=values]").closest(".am-row").show();
                    clear_sql_types();
                    break;
                case 'checkbox':
                case 'radio':
                    switchDefaults(false);
                    jQuery("input[name=placeholder]").closest(".am-row").hide();
                    jQuery("input[name=values]").closest(".am-row").show();
                    clear_sql_types();
                break;
            }
        }
    }
})(jQuery)
CUT;

        $this->addScript()
            ->setScript($jsCode);
    }

    public function getTypes()
    {
        return [
            'text' => ___('Text'),
            'select' => ___('Select (Single Value)'),
            'multi_select' => ___('Select (Multiple Values)'),
            'textarea' => ___('TextArea'),
            'radio' => ___('RadioButtons'),
            'single_checkbox' => ___('Single Checkbox'),
            'checkbox' => ___('Multiple Checkboxes'),
            'date' => ___('Date'),
            'upload' => ___('Upload'),
            'multi_upload' => ___('Multi Upload')
        ];
    }

    public function checkName($name)
    {
        $dbFields = $this->table->getFields(true);
        if (in_array($name, $dbFields)) {
            return false;
        } else {
            return is_null($this->table->customFields()->get($name));
        }
    }

    public function checkSqlType($sql_type, $fieldSql)
    {
        return (!$sql_type && $fieldSql->getValue()) ? false : true;
    }
}