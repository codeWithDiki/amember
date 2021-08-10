<?php

/**
 * Controller to serve remote API requests, based
 * on a Am_Table/Am_Query pair
 */
class Am_ApiController_Table extends Am_ApiController_Base
{
    /** describes nested records and relation
     * @example
     *     array('invoice' => array('controller' => 'invoices', 'key' => 'user_id',));
     */

    /** @var callable|null */
    private $_nestedCallback;

    protected $_nested = [];
    /** array default value of incoming _nested param */
    protected $_defaultNested = [];
    /** @access private */
    protected $_nestedControllers = [];
    /** whatever is passed as 'nested' parameter in update/insert request */
    protected $_nestedInput = [];
    /** @var Am_Record current record in POST/PUT/DELETE action */
    protected $record;

    /** @var Am_Table */
    protected $_table;

    public function __construct(Am_Di $di, Am_Table $table = null)
    {
        parent::__construct($di);
        $this->_table = $table;
    }

    /** @return Am_Table */
    function createTable()
    {
        if (!$this->_table)
            throw new Am_Exception_InternalError("override " . __METHOD__);
        return $this->_table;
    }

    /** @return Am_Query */
    function createQuery()
    {
        return new Am_Query($this->createTable());
    }

    /**
     * Pass callback to create a nested ApiController
     * Callback  get string $nestedId parameter
     *    and will return ready to use controller
     * @param $callback
     * @return $this
     */
    public function setNestedCallback($callback)
    {
        $this->_nestedCallback = $callback;
        return $this;
    }

    protected function getNestedController($nest)
    {
        if (!empty($this->_nestedControllers[$nest]))
            return $this->_nestedControllers[$nest];
        if (empty($this->_nested[$nest])) throw new Am_Exception_InputError("Nested relation [$nest] is not defined");

        $this->_nestedControllers[$nest] = call_user_func($this->_nestedCallback, $nest);

        return $this->_nestedControllers[$nest];
    }
    protected function getNestedKeyField($nest)
    {
        if (empty($this->_nested[$nest])) throw new Am_Exception_InputError("Nested relation [$nest] is not defined");
        $relation = $this->_nested[$nest];
        if (!empty($relation['key']))
            return $relation['key'];
        else
            return $this->createTable()->getKeyField();
    }

    /**
     * Prepare record for displaying
     * @return Am_Record
     */
    protected function prepareRecordForDisplay(Am_Record $rec, $request)
    {
        // include nested records
        $nested = (array)$request->getParam('_nested');
        if (empty($nested)) $nested = $this->_defaultNested;

        $_nested = [];
        if (empty($rec->_nested_)) $rec->_nested_ = [];
        foreach ($nested as $nest)
        {
            if (empty($rec->_nested_[$nest]))
                $rec->_nested_[$nest] = [];
            $controller = $this->getNestedController($nest);
            $keyField = $this->getNestedKeyField($nest);

            $nrequest = clone $request;
            $nrequest->setParam('_filter', [$keyField => $rec->pk()]);
            $nestedRecords = $controller->selectRecords($t_, true, $nrequest);
            foreach ($nestedRecords as $nestedRecord)
            {
                $_nested[$nest][] = $nestedRecord;
            }
        }
        $rec->_nested_ = $_nested;
        return $rec;
    }
    protected function apiOutRecords(array $records, array $addInfo = [], $request, $response)
    {
        $ret = $addInfo;
        foreach ($records as $r)
        {
            $ret[] = $this->prepareRecordForDisplay($r, $request);
        }
        return $this->dumpResponse($ret, $request, $response);
    }
    protected function recordToXml(Am_Record $rec, XmlWriter $x)
    {
        $rec->exportXml($x, []);
    }
    protected function recordToArray(Am_Record $rec)
    {
        $ret = $rec->toArray();
        if (!empty($rec->_nested_))
        {
            foreach ($rec->_nested_ as $table => $nestedRecords)
            {
                foreach ($nestedRecords as $nestedRecord)
                {
                    $ret['nested'][$table][] = $nestedRecord->toArray();
                }
            }
        }
        return $ret;
    }
    protected function dumpResponse(array $ret, $request, $response)
    {
        $format = $request->getParam('_format');
        switch ($format)
        {
            case 'xml':
                $x = new XMLWriter();
                $x->openMemory();
                $x->setIndent(2);
                $x->startDocument('1.0', 'utf-8');
                $x->startElement('rows');
                foreach ($ret as $k => $rec)
                {
                    if (!$rec instanceof Am_Record)
                        $x->writeElement($k, (string)$rec);
                    else {
                        $x->startElement('row');
                        $rec->exportXml($x, ['element' => null]);
                        if (!empty($rec->_nested_))
                        {
                            $x->startElement('nested');
                            foreach ($rec->_nested_ as $table => $nestedRecords)
                            {
                                $x->startElement($table);
                                foreach ($nestedRecords as $nestedRecord)
                                    $nestedRecord->exportXml($x);
                                $x->endElement(); // $table
                            }
                            $x->endElement(); //nested
                        }
                        $x->endElement(); //row
                    }
                }
                $x->endElement();
                $x->endDocument();
                $out = $x->flush();
                $response->setHeader('Content-type', 'application/xml; charset=UTF-8', true);
                break;
            case 'serialize':
            case 'json':
            default:
                foreach ($ret as $k => $rec)
                {
                    if ($rec instanceof Am_Record)
                        $ret[$k] = $this->recordToArray($rec);
                }
                if ($format == 'serialize')
                {
                    $response->setHeader('Content-type', 'text/plain; charset=UTF-8', true);
                    $out = serialize($ret);
                } else {
                    $response->setHeader('Content-type', 'application/json; charset=UTF-8', true);
                    if ($request->getParam('_pretty'))
                        $out = json_encode($ret, JSON_PRETTY_PRINT);
                    else
                        $out = json_encode($ret);
                }
        }
        $response->setBody($out);
        return $response;
    }

    public function selectRecords(& $total = 0, $skipCountLimit = false, $request)
    {
        $page = $request->getParam('_page', 0);
        $count = min(1000, $request->get('_count', 20));

        $ds = $this->createQuery();

        $filter = (array)$request->getParam('_filter', []);
        foreach ($filter as $k => $v)
        {
            if (strpos($v, '%')!==false)
                $ds->addWhere('?# LIKE ?', $k, $v);
            else
                $ds->addWhere('?#=?', $k, $v);
        }
        if ($skipCountLimit) {
            $ret = $ds->selectAllRecords();
        } else {
            $ret = $ds->selectPageRecords($page, $count);
        }
        $total = $ds->getFoundRows();
        return $ret;
    }

    /** api to return list of records */
    public function index($request, $response, $args)
    {
        $total = 0;
        $records = $this->selectRecords($total, false, $request);

        return $this->apiOutRecords($records, ['_total' => $total], $request, $response);
    }
    /** api to return a single record */
    public function get($request, $response, $args)
    {
        $t = $this->createTable();
        $records = [$t->load((int)$request->getParam('_id'))];
        return $this->apiOutRecords($records, [], $request, $response);
    }

    function createRecord($vars)
    {
        $t = $this->createTable();

        $record = $t->createRecord();

        $this->setForInsert($record, $vars);

        $record->insert();

        return $record;

    }

    /** api to create new record */
    public function post($request, $response, $args)
    {
        $vars = $request->getParams();
        if (!empty($vars['nested']))
        {
            $this->_nestedInput = $vars['nested'];
            unset($vars['nested']);
        }
        unset($vars['controller'], $vars['module'], $vars['action']);
        $this->record = $this->createRecord($vars);

        $this->insertNested($this->record, $vars, $request, $response, $args);
        return $this->apiOutRecords([$this->record], [], $request, $response);
    }
    /** api to update existing record */
    public function put($request, $response, $args)
    {
        $t = $this->createTable();
        $this->record = $t->load((int)$request->getParam('_id'));
        $vars = $request->getParams();
        if (!empty($vars['nested']))
        {
            $this->_nestedInput = $vars['nested'];
            unset($vars['nested']);
        }
        unset($vars['controller'], $vars['module'], $vars['action']);
        $this->setForUpdate($this->record, $vars);
        $this->record->update();
        $this->updateNested($this->record, $vars);
        return $this->apiOutRecords([$this->record], [], $request, $response);
    }
    /** api to delete existing record */
    public function delete($request, $response, $args)
    {
        $t = $this->createTable();
        $this->record = $t->load((int)$request->getParam('_id'));
        $this->beforeDelete($this->record);
        $this->record->delete();
        return $this->apiOutRecords([$this->record], ['_success' => true], $request, $response);
    }

    public function setForInsert(Am_Record $record, array $vars)
    {
        $record->setForInsert($vars);
        $this->setInsertNested($record, $vars);
    }
    public function setForUpdate(Am_Record $record, array $vars)
    {
        $record->setForUpdate($vars);
        $this->setUpdateNested($record, $vars);
    }
    /**
     * insert records from $this->_nestedInput
     * after $record->insert() call
     */
    public function insertNested(Am_Record $record, array $vars, $request, $response, $args)
    {
        foreach ($this->_nestedInput as $nest => $records)
        {
            $controller = $this->getNestedController($nest);
            foreach ($records as $rec)
            {
                $rec[$this->getNestedKeyField($nest)] = $record->pk();
                $request = new Am_Mvc_Request($rec, 'POST');
                $response = clone $response;
                $controller->post($request, $response, $args);
            }
        }
    }
    /**
     * update records from $this->_nestedInput
     * after $record->update() call
     */
    public function updateNested(Am_Record $record, array $vars)
    {
        foreach ($this->_nestedInput as $nest => $records)
        {
            $controller = $this->getNestedController($nest);
            foreach ($records as $rec)
            {
                throw new Am_Exception_InputError("PUT for nested records is not implemented");
            }
        }
    }
    /**
     * set variables in $record from $this->_nestedInput
     * before $record->insert() call
     */
    public function setInsertNested(Am_Record $record, array $vars)
    {

    }
    /**
     * set variables in $record from $this->_nestedInput
     * before $record->update() call
     */
    public function setUpdateNested(Am_Record $record, array $vars)
    {

    }
    public function beforeDelete(Am_Record $record) {}

    public function addNested($nestedId, $default = false)
    {
        $this->_nested[$nestedId] = true;
        if (!$default)
            $this->_defaultNested[] = $nestedId;
        return $this;
    }
}
