<?php

abstract class CustomFieldController extends Am_Mvc_Controller_Grid
{
    abstract protected function getTable();

    public function indexAction()
    {
        $this->getTable()->syncSortOrder();
        parent::indexAction();
    }

    public function parseCsvAction()
    {
        $this->_response->ajaxResponse(array_map('str_getcsv', array_map('trim', explode("\n", trim($this->getParam('csv', ''))))));
    }

    public function getCsvAction()
    {
        $data = json_decode($this->getParam('data'), true);
        $out = "";
        foreach ($data['order'] as $k) {
            $out .= sprintf("%s,%s,%s\n",
                amEscapeCsv($k, ','),
                amEscapeCsv($data['options'][$k], ','),
                amEscapeCsv(in_array($k, $data['default']) ? true : false, ',')
            );
        }

        echo trim($out);
    }

    public function createGrid()
    {
        $table = $this->getTable();

        $fields = $table->customFields()->getAll();
        uksort($fields, [$table, 'sortCustomFields']);
        $ds = new Am_Grid_DataSource_CustomField($fields, $table);
        $grid = new Am_Grid_Editable('_f', ___('Additional Fields'), $ds, $this->_request, $this->view);
        $grid->addField('name', ___('Name'));
        $grid->addField('title', ___('Title'));
        $grid->addField('sql', ___('Field Type'))
            ->setRenderFunction([$this, 'renderFieldType']);
        $grid->addField('type', ___('Display Type'), true, '', null, '10%');
        $grid->addField('validateFunc', ___('Validation'), false)
            ->setGetFunction(function($r) {return implode(",", (array)$r->validateFunc);});

        $grid->setForm([$this, 'createForm']);
        $grid->addCallback(Am_Grid_Editable::CB_VALUES_TO_FORM, [$this, 'valuesToForm']);
        $grid->addCallback(Am_Grid_ReadOnly::CB_TR_ATTRIBS, [$this, 'getTrAttribs']);

        $grid->actionGet('edit')
            ->setIsAvailableCallback(function($r) {return isset($r->from_config) && $r->from_config;});
        $grid->actionGet('delete')
            ->setIsAvailableCallback(function($r) {return isset($r->from_config) && $r->from_config;});

        $grid->actionAdd(new Am_Grid_Action_Sort_CustomField())
            ->setTable($table);

        $grid->setRecordTitle(function($r = null) {
            return $r ? sprintf('%s - %s', ___('Field'), $r->title) : ___('Field');
        });
        $grid->setFilter(new Am_Grid_Filter_CustomField);
        return $grid;
    }

    public function renderFieldType($record, $fieldName, Am_Grid_ReadOnly $grid)
    {
        return $grid->renderTd(!empty($record->sql) ? (!empty($record->sql_type) ? "SQL: {$record->sql_type}" : 'SQL') : 'DATA');
    }

    public function createForm()
    {
        $form = new Am_Form_Admin_CustomField($this->grid->getRecord());
        $form->setTable($this->getTable());
        return $form;
    }

    public function getTrAttribs(& $ret, $record)
    {
        if (empty($record->from_config)) {
            $ret['class'] = isset($ret['class']) ? $ret['class'] . ' disabled' : 'disabled';
        }
    }

    public function valuesToForm(& $ret, $record)
    {
        $ret['validate_func'] = @$record->validateFunc;

        $ret['values'] = [
            'options' => $record->options,
            'default' => $record->default,
        ];
    }
}

class Am_Grid_Filter_CustomField extends Am_Grid_Filter_Abstract
{
    protected function applyFilter()
    {
        $_ = $this->grid->getDataSource()->_friendGetArray();

        $_ = $this->filter($_, $this->getParam('filter'));
        $this->grid->getDataSource()->_friendSetArray($_);

        list($fieldname, $desc) = $this->grid->getDataSource()->_friendGetOrder();
        if ($fieldname) {
            $this->grid->getDataSource()->setOrder($fieldname, $desc);
        }
    }

    function renderInputs()
    {
        return $this->renderInputText([
            'name' => 'filter',
            'placeholder' => ___('Name/Title'),
        ]);
    }

    protected function filter($array, $filter)
    {
        if (!$filter) return $array;

        foreach ($array as $k => $field) {
            if (false === stripos($field->title, $filter) &&
                false === stripos($field->name, $filter)) {

                unset($array[$k]);
            }
        }

        return $array;
    }
}