<?php

class Am_Grid_Action_Customize extends Am_Grid_Action_Abstract
{
    protected $privilege = null;
    protected $type = self::HIDDEN;
    protected $fields = [];
    protected $defaultFields = [];
    protected $sort_fields = [];

    public function run()
    {
        $form = new Am_Form_Admin('form-grid-config');
        $form->setAttribute('name', 'customize');

        $form->addSortableMagicSelect('fields', ['class'=>'am-combobox-fixed'])
            ->loadOptions($this->getFieldsOptions())
            ->setLabel(___('Fields to Display in Grid'))
            ->setJsOptions(<<<CUT
{
    allowSelectAll:true,
    sortable: true
}
CUT
            );
        if ($this->sort_fields) {
            $op = [];
            foreach ($this->sort_fields as $fn => $label) {
                if (is_array($label)) {
                    $op[implode(', ', array_map(function($fn){return "{$fn} ASC";}, $label['fields']))] = "{$label['label']} ASC";
                    $op[implode(', ', array_map(function($fn){return "{$fn} DESC";}, $label['fields']))] = "{$label['label']} DESC";
                } else {
                    $op["{$fn} ASC"] = "$label ASC";
                    $op["{$fn} DESC"] = "$label DESC";
                }

            }
            $form->addSelect('sort_by')
                ->setLabel(___("Default Order By"))
                ->loadOptions([''=>''] + $op);
        }

        foreach ($this->grid->getVariablesList() as $k) {
            $form->addHidden($this->grid->getId() . '_' . $k)->setValue($this->grid->getRequest()->get($k, ""));
        }

        $form->addSaveButton();
        $form->setDataSources([$this->grid->getCompleteRequest()]);

        if ($form->isSubmitted()) {
            $values = $form->getValue();
            $this->setConfig($values['fields']);
            $this->setConfig($values['sort_by'], 'sort_by');
            $this->grid->redirectBack();
        } else {
            $form->setDataSources([
                new HTML_QuickForm2_DataSource_Array([
                'fields' => $this->getSelectedFields(),
                'sort_by' => $this->getConfig('sort_by')
                ])
            ]);
            echo $this->renderTitle();
            echo sprintf('<div class="info">%s</div>',
                ___('You can change Number of %sRecords per Page%s in section %sSetup/Configuration%s',
                    '<strong>', '</strong>',
                    '<a class="link" href="' . $this->grid->getDi()->url('admin-setup') . '" target="_top">','</a>'));
            echo $form;
        }
    }

    function setSortByOptions($fields)
    {
        $this->sort_fields = $fields;
    }

    /**
     * @param Am_Grid_Field $field
     * @return Am_Grid_Action_Customize
     */
    public function addField(Am_Grid_Field $field)
    {
        $this->fields[$field->getFieldName()] = $field;
        return $this;
    }

    protected function getFieldsOptions()
    {
        $res = [];
        foreach ($this->fields as $field)
        {
            $res[$field->getFieldName()] = $field->getFieldTitle();
        }
        return $res;
    }

    public function setGrid(Am_Grid_Editable $grid)
    {
        parent::setGrid($grid);
        if ($this->hasPermissions()) {
            $grid->addCallback(Am_Grid_ReadOnly::CB_RENDER_TABLE, [$this, 'renderLink']);
            $grid->addCallback(Am_Grid_Editable::CB_INIT_GRID_FINISHED, [$this, 'setupFields']);
        }
    }

    public function renderLink(& $out)
    {
        $out .= sprintf('<div style="float:right">&nbsp;&nbsp;&nbsp;<a class="link" href="%s">' . ___('Customize') . '</a></div>',
                $this->getUrl());
    }

    public function setupFields(Am_Grid_ReadOnly $grid)
    {
        $fields = [];

        //it is special fields and user should not be able to disable or rearrange it
        $fieldCheckboxes = null;
        $fieldActions = null;

        foreach ($grid->getFields() as $field)
        {
            if ($field->getFieldName() == '_actions')
            {
                $fieldActions = $field;
                $grid->removeField($field->getFieldName());
                continue;
            }
            if ($field->getFieldName() == '_checkboxes')
            {
                $fieldCheckboxes = $field;
                $grid->removeField($field->getFieldName());
                continue;
            }
            $this->addField($field);
            $fields[] = $field->getFieldName();
            $grid->removeField($field->getFieldName());
        }
        $this->defaultFields = $fields;

        $fields = $this->getSelectedFields();

        foreach ($fields as $fieldName)
        {
            if (isset($this->fields[$fieldName]))
            {
                $grid->addField($this->fields[$fieldName]);
            }
        }
        if ($fieldCheckboxes)
        {
            $grid->prependField($fieldCheckboxes);
        }
        if ($fieldActions)
        {
            $grid->addField($fieldActions);
        }
        if ($_ = $this->getConfig('sort_by')) {
            $grid->getDataSource()->getDataSourceQuery()
                ->setOrderRaw($_);
        }
    }

    protected function getSelectedFields()
    {
        return $this->getConfig() ? $this->getConfig() : $this->defaultFields;
    }

    protected function getConfig($name = null)
    {
        return $this->grid->getDi()->authAdmin->getUser()->getPref($this->getPrefId($name));
    }

    protected function setConfig($config, $name = null)
    {
        $this->grid->getDi()->authAdmin->getUser()->setPref($this->getPrefId($name), $config);
    }

    protected function getPrefId($name = null)
    {
        return 'grid_setup' . $this->grid->getId() . $name;
    }
}