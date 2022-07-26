<?php
namespace Oxzion\Document\Parser\Spreadsheet;

use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\DefaultReadFilter;
use Oxzion\Utils\FileUtils;
use \Exception;

class SpreadsheetParserImpl implements SpreadsheetParser
{
    private $reader;
    private $file;
    private $sheetNames;
    private $sheetInfos;
    private $defaultFilter;
    public function init($file)
    {
        if (!FileUtils::fileExists($file)) {
            throw new Exception("File $file not found");
        }
        // supported files:
        // 'xlsx' // Excel (OfficeOpenXML) Spreadsheet
        // 'xlsm' // Excel (OfficeOpenXML) Macro Spreadsheet (macros will be discarded)
        // 'xltx' // Excel (OfficeOpenXML) Template
        // 'xltm' // Excel (OfficeOpenXML) Macro Template (macros will be discarded)
        // 'xls' // Excel (BIFF) Spreadsheet
        // 'xlt' // Excel (BIFF) Template
        // 'ods' // Open/Libre Offic Calc
        // 'ots' // Open/Libre Offic Calc Template
        // 'slk'
        // 'xml' // Excel 2003 SpreadSheetML
        // 'gnumeric'
        // 'htm'
        // 'html'
        // 'csv'
        $this->file = $file;
        $this->reader = IOFactory::createReaderForFile($file);
        $this->reader->setReadDataOnly(true);
        $this->reader->setReadEmptyCells(false);
        $this->sheetInfos = array();
        $this->sheetNames = null;
        $this->defaultFilter = new DefaultReadFilter();
    }

    public function getSheetNames()
    {
        if (!$this->sheetNames) {
            $this->sheetNames = $this->reader->listWorksheetNames($this->file);
        }
        return $this->sheetNames;
    }
    public function getSheetCount()
    {
        $sheets = $this->getSheetNames();
        return count($sheets);
    }
    public function getWorksheetInfo($sheetName = "")
    {
        if (!isset($this->sheetInfos[$sheetName])) {
            $worksheetData = $this->reader->listWorksheetInfo($this->file);
            foreach ($worksheetData as $worksheet) {
                $this->sheetInfos[$worksheet['worksheetName']] = $worksheet;
            }
        }

        return isset($this->sheetInfos[$sheetName]) ? $this->sheetInfos[$sheetName] : $this->sheetInfos;
    }

    /**
    *
    *   worksheet(string/array) - worksheet name or array of names to parse. Default is first work sheet
    *   rowMapper (RowMapper) - Optional to map row data to a custom data structure
    *   filter (SpreadsheetFilter) - Optional to filter out columns or rows.
    *
    */
    public function parseDocument(array $parserOptions = [])
    {
        if (!empty($parserOptions['worksheet'])) {
            $worksheet = ($parserOptions['worksheet'] === 'ALL_SHEETS') ? $this->getSheetNames() : $parserOptions['worksheet'];
            if (!is_array($worksheet)) $worksheet = [$worksheet];
        } else {
            $worksheet = [current($this->getSheetNames())];
        }
        $rowMapper = null;
        if (isset($parserOptions['rowMapper'])) {
            $obj = $parserOptions['rowMapper'];
            if (is_a($obj, RowMapper::class)) {
                $rowMapper = $obj;
            } else {
                throw new Exception("RowMapper should be of type \Oxzion\Document\Parser\Spreadsheet\RowMapper");
            }
        }
        if (isset($parserOptions['filter'])) {
            $obj = $parserOptions['filter'];
            if (is_a($obj, SpreadsheetFilter::class)) {
                $this->reader->setReadFilter($obj);
            } else {
                throw new Exception("Filter should be of type \Oxzion\Document\Parser\Spreadsheet\SpreadsheetFilter");
            }
        } else {
            $this->reader->setReadFilter($this->defaultFilter);
        }
        $data = array();
        foreach ($worksheet as $index => $sheetName) {
            $this->reader->setLoadSheetsOnly($sheetName);
            $spreadsheet = $this->reader->load($this->file);
            $worksheetData = $spreadsheet->getActiveSheet();

            if (!empty($parserOptions['range']) && !empty($parserOptions['range'][$sheetName])) {
                $data[$sheetName] = $worksheetData->rangeToArray($parserOptions['range'][$sheetName]);
            } elseif ($rowMapper) {
                $list = $worksheetData->toArray();
                foreach ($list as $index => $rowData) {
                    $rowMapper->mapRow($rowData);
                }
                $data[$sheetName] = $rowMapper->getData();
                $rowMapper->resetData();
            } else {
                $data[$sheetName] = $worksheetData->toArray();
                foreach ($data[$sheetName] as $rowkey => &$rowvalue) {
                    foreach ($rowvalue as $colkey => &$colvalue) {
                        $cell = $worksheetData->getCellByColumnAndRow($colkey + 1, $rowkey + 1, false);
                        if (!is_null($cell)) {
                            if ($cell->isFormula()) {
                                $colvalue = $cell->getOldCalculatedValue();
                            }
                        }
                    }
                }
            }
        }

        return $data;
    }
}
