<?php

namespace Oxzion\AppDelegate;

use Oxzion\Document\Parser\Spreadsheet\SpreadsheetParserImpl;
use Oxzion\Document\Parser\Spreadsheet\SpreadsheetFilter;

use Logger;

trait SpreadsheetParserTrait
{
    protected $logger;

    public function __construct()
    {
        $this->logger = Logger::getLogger(__CLASS__);
    }
    public function parseDocument($filePath, $sheet, $rowMapper = null, $startRow = 1)
    {
        $parser = new SpreadsheetParserImpl();
        $parser->init($filePath);
        $filter = new SpreadsheetFilter();
        $filter->setRows($startRow);
        $parsedDocument = $parser->parseDocument(array(
            'worksheet' => $sheet,
            'rowMapper' => $rowMapper,
            'filter' => $filter
        ));
        return $parsedDocument;
    }
}
