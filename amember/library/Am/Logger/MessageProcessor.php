<?php

/**
 * Based on https://github.com/Seldaek/monolog/blob/master/src/Monolog/Processor/PsrLogMessageProcessor.php
 *
 * Class Am_Logger_MessageProcessor
 */
class Am_Logger_MessageProcessor /**  implements ProcessorInterface  **/
{
    const SIMPLE_DATE = "Y-m-d\TH:i:s.uP";
    /** @var string|null */
    private $dateFormat;
    /** @var bool */
    private $removeUsedContextFields;

    /**
     * @param string|null $dateFormat              The format of the timestamp: one supported by DateTime::format
     * @param bool        $removeUsedContextFields If set to true the fields interpolated into message gets unset
     */
    public function __construct($dateFormat = null, $removeUsedContextFields = false)
    {
        $this->dateFormat = $dateFormat;
        $this->removeUsedContextFields = $removeUsedContextFields;
    }

    /**
     * @param  array $record
     * @return array
     */
    public function __invoke(array $record)
    {
        if (false === strpos($record['message'], '{')) {
            return $record;
        }
        $replacements = [];
        foreach ($record['context'] as $key => $val) {
            $placeholder = '{' . $key . '}';
            if (strpos($record['message'], $placeholder) === false) {
                continue;
            }
            if (is_object($val) && ($_ = $this->amemberObjectToString($val))) {
                $replacements[$placeholder] = '['.$_.']';
            } elseif (is_null($val) || is_scalar($val) || (is_object($val) && method_exists($val, "__toString"))) {
                $replacements[$placeholder] = $val;
            } elseif ($val instanceof \DateTimeInterface) {
                if (!$this->dateFormat && $val instanceof \Monolog\DateTimeImmutable) {
                    // handle monolog dates using __toString if no specific dateFormat was asked for
                    // so that it follows the useMicroseconds flag
                    $replacements[$placeholder] = (string) $val;
                } else {
                    $replacements[$placeholder] = $val->format($this->dateFormat ?: static::SIMPLE_DATE);
                }
            } elseif (is_object($val)) {
                $class = get_class($val);
                $replacements[$placeholder] = '[object '.$class($val).']';
            } elseif (is_array($val)) {
                $replacements[$placeholder] = 'array'.@json_encode($val);
            } else {
                $replacements[$placeholder] = '['.gettype($val).']';
            }
            if ($this->removeUsedContextFields) {
                unset($record['context'][$key]);
            }
        }
        $record['message'] = strtr($record['message'], $replacements);
        return $record;
    }

    function amemberObjectToString($obj)
    {
        switch (true)
        {
            case $obj instanceof Am_Plugin_Base:
                return get_class($obj) . ':' . $obj->getId();
            case $obj instanceof Invoice:
                return get_class($obj) . '#' . $obj->pk() . ':' . $obj->public_id;
            case $obj instanceof User:
                return get_class($obj) . '#' . $obj->pk() . ':' . $obj->login;
            case $obj instanceof Am_Record:
                return get_class($obj) . '#' . $obj->pk();
            case $obj instanceof GuzzleHttp\Psr7\Request:
            case $obj instanceof GuzzleHttp\Psr7\Response:
                return get_class($obj) . ':' . \GuzzleHttp\Psr7\str($obj);
        }
    }
}