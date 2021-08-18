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
        $ext = FileUtils::getFileExtension($file);
        $type = "";
        switch (strtolower($ext)) {
            case 'xlsx':
            case 'xlsm':
                $type = 'Xlsx';
                break;
            case 'xls':
                $type = 'Xls';
                break;
            case 'xml':
                $type = 'Xml';
                break;
            case 'ods':
                $type = 'Ods';
                break;
            case 'csv':
                $type = 'Csv';
                break;
        }

        if ($type == "") {
            throw new Exception("Unrecognized file format");
        }
        if (!FileUtils::fileExists($file)) {
            throw new Exception("File $file not found");
        }
        
        $this->file = $file;
        $this->reader = IOFactory::createReader($type);
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
    public function parseDocument(array $parserOptions = array())
    {
        if (isset($parserOptions['worksheet'])) {
            $worksheet = $parserOptions['worksheet'];
            if (is_string($worksheet)) {
                $worksheet = array($worksheet);
            }
        } else {
            $worksheet = array($this->getSheetNames()[0]);
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
            
            if ($rowMapper) {
                $list = $worksheetData->toArray();
                foreach ($list as $index => $rowData) {
                    $rowMapper->mapRow($rowData);
                }
                $data[$sheetName] = $rowMapper->getData();
                $rowMapper->resetData();
            } else {
                $data[$sheetName] = $worksheetData->toArray();
                for ($i = 0; $i < sizeof($data[$sheetName]); $i++) {
                    $row = $data[$sheetName][$i];
                    for ($j = 0; $j < sizeof($row); $j++) {
                        $cell = $worksheetData->getCellByColumnAndRow($j, $i, false);
                        if (!is_null($cell)) {
                            if ($cell->isFormula()) {
                                $data[$sheetName][$i][$j] =
                                    $cell->getOldCalculatedValue();
                            }
                        }
                    }
                }
            }
        }

        return $data;
    }
}
