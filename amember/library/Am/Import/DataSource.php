<?php

/** empty */
class Am_Import_DataSource
{
    const MAX_LINE_LENGTH = 4096;
    const DELIM_SEMICOLON = 1;
    const DELIM_COMMA = 2;
    const DELIM_SPACE = 3;
    const DELIM_TABULATION = 4;
    const DELIM_VALUE = 1;
    const DELIM_CODE = 2;

    protected $filePointerIterator = null;
    protected $filePointer = null;
    protected $colNum = null;
    protected $delimCode = null;

    public function __construct($path)
    {
        $this->filePointer = fopen($path, 'r');
        $this->filePointerIterator = fopen($path, 'r');
    }

    public function __destruct()
    {
        fclose($this->filePointer);
        fclose($this->filePointerIterator);
    }

    public function getOffset()
    {
        return ftell($this->filePointerIterator);
    }

    public function setOffset($offset = 0)
    {
        fseek($this->filePointerIterator, $offset);
    }

    public function rewind()
    {
        $this->setOffset(0);
    }

    public function getDelim($mode = self::DELIM_VALUE)
    {
        if (is_null($this->delimCode))
        {
            $this->setDelim($this->guessDelim());
        }

        switch ($mode)
        {
            case self::DELIM_VALUE :
                return self::getDelimByCode($this->delimCode);
            case self::DELIM_CODE :
                return $this->delimCode;
            default :
                throw new Am_Exception_InputError(
                    ___('Unknown mode [%s] in %s->%s', $mode, __CLASS__, __METHOD__));
        }
    }

    public function setDelim($delimCode)
    {
        $this->delimCode = $delimCode;

        //remove cached values that depends on delimiter
        $this->colNum = null;
    }

    public function getNextLineParsed($pointer = null, $normalize = true)
    {
        $pointer = $pointer ?: $this->filePointerIterator;

        $res = $this->_getNextLineParsed($pointer);

        if ($res === false || !is_array($res))
        {
            return false;
        }
        if (is_null($res[0]))
        {
            return $this->getNextLineParsed($pointer, $normalize);
        }

        return $normalize ? $this->normalizeLineParsed($res) : $res;
    }

    protected function _getNextLineParsed($pointer)
    {
        if (feof($pointer))
        {
            return false;
        } else
        {
            return fgetcsv($pointer, self::MAX_LINE_LENGTH, $this->getDelim());
        }
    }

    public function getFirstLineParsed($normalize = true)
    {
        fseek($this->filePointer, 0);
        $_ = $this->getNextLineParsed($this->filePointer, $normalize);
        fseek($this->filePointer, 0);

        return $_;
    }

    public function getFirstLinesParsed($num, $normalize = true)
    {
        $result = [];

        fseek($this->filePointer, 0);
        for ($i = 0; $i < $num; $i++)
        {
            $res = $this->getNextLineParsed($this->filePointer, $normalize);
            if (!$res)
            {
                break;
            }
            $result[$i] = $res;
        }

        return $result;
    }

    public function getColNum()
    {
        if (!$this->colNum)
        {
            $this->colNum = count((array)$this->getFirstLineParsed(false));
        }

        return $this->colNum;
    }

    public static function getDelimOptions()
    {
        return [
            self::DELIM_SEMICOLON => ___('Semicolon'),
            self::DELIM_COMMA => ___('Comma'),
            self::DELIM_SPACE => ___('Space'),
            self::DELIM_TABULATION => ___('Tabulation'),
        ];
    }

    public function getEstimateTotalLines($proccessed)
    {
        $perLine = round($this->getOffset() / $proccessed);
        $total = round($this->getFileSize() / $perLine);

        return $total;
    }

    protected function getFirstLineRaw()
    {
        fseek($this->filePointer, 0);
        $_ = trim(fgets($this->filePointer));
        fseek($this->filePointer, 0);

        return $_;
    }

    private function getFileSize()
    {
        $stat = fstat($this->filePointer);

        return $stat['size'];
    }

    protected function normalizeLineParsed($lineParsed)
    {
        $result = (array)$lineParsed;

        if (count($lineParsed) > $this->getColNum())
        {
            $result = array_slice($result, 0, $this->getColNum());
        } elseif (count($lineParsed) < $this->getColNum())
        {
            $result = array_pad($result, $this->getColNum(), '');
        }

        return $result;
    }

    protected static function getDelimMap()
    {
        return [
            self::DELIM_SEMICOLON => ';',
            self::DELIM_COMMA => ',',
            self::DELIM_SPACE => ' ',
            self::DELIM_TABULATION => "\t",
        ];
    }

    protected static function getDelimByCode($delimCode)
    {
        $map = self::getDelimMap();

        if (!isset($map[$delimCode]))
        {
            throw new Am_Exception_InputError('Unknown delim code ['.$delimCode.']');
        }

        return $map[$delimCode];
    }

    protected function guessDelim()
    {
        $line = $this->getFirstLineRaw();
        foreach (self::getDelimMap() as $delimCode => $delim)
        {
            if (count(explode($delim, $line)) >= 3)
            {
                return $delimCode;
            }
        }

        return self::DELIM_SEMICOLON;
    }
}