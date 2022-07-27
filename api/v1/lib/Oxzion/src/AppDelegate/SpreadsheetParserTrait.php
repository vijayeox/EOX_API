<?php

namespace Oxzion\AppDelegate;

use Oxzion\Document\Parser\Spreadsheet\SpreadsheetParserImpl;
use Oxzion\Document\Parser\Spreadsheet\SpreadsheetFilter;

use Logger;

trait SpreadsheetParserTrait
{
    protected $logger;
    private $filePath;
    private $parser;

    public function __construct()
    {
        $this->logger = Logger::getLogger(__CLASS__);
    }

    public function setFile($filePath)
    {
        $this->filePath = $filePath;
        $this->parser = new SpreadsheetParserImpl();
        $this->parser->init($filePath);
    }

    public function getParser()
    {
        return $this->parser;
    }

    public function parseDocument($filePath, $sheet, $rowMapper = null, $startRow = 1, $options = [])
    {
        if ($this->filePath != $filePath) {
            $this->setFile($filePath);
        }
        $filter = new SpreadsheetFilter();
        $filter->setRows($startRow);

        return $this->parser->parseDocument($options + array(
            'worksheet' => $sheet,
            'rowMapper' => $rowMapper,
            'filter' => $filter
        ));
    }
}
