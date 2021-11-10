<?php
//test
use Oxzion\Db\Persistence\Persistence;
use Oxzion\Utils\ArtifactUtils;
use Oxzion\AppDelegate\FileTrait;
use Oxzion\AppDelegate\AbstractDocumentAppDelegate;

class ClaimsExcelExport extends AbstractDocumentAppDelegate
{
    use FileTrait;

    public function __construct()
    {
        parent::__construct();
    }

    protected $excelTemplate = 'ClaimsExport.xlsx';

    public function execute(array $data, Persistence $persistenceService)
    {
        $params = array(
            "entityName" => "Policy Claims",
            "orgId" => "34bf01ab-79ca-42df-8284-965d8dbf290e"
        );
        $filterParams = array(
            ["skip" => 0, "take" => 10000]
        );
        $files = $this->getFileList($params, $filterParams);
        $sumTotal = 0;
        if ($files["total"] > 0) {
            $fileData['exportDate'] = date('Y-m-d');
            $fileData['recordsCount'] = $files["total"];
            $fileData['entity_name'] = "Claims Export";
            $this->saveFile($fileData);

            $generatedDocumentspath = [];
            $dest =  ArtifactUtils::getDocumentFilePath(
                $this->destination,
                $fileData["uuid"],
                array('orgUuid' => $fileData["org_id"])
            );
            $this->documentBuilder->fillExcelTemplate(
                $this->excelTemplate,
                $this->excelDataMassage($files["data"]),
                $dest['absolutePath'] .  $this->excelTemplate,
                ["Sheet1"]
            );
            array_push(
                $generatedDocumentspath,
                array(
                    "fullPath" => $dest['absolutePath'] .  $this->excelTemplate,
                    "file" => $dest['relativePath'] . $this->excelTemplate,
                    "originalName" => $this->excelTemplate,
                    "type" => "excel/xlsx"
                )
            );
            $fileData["claimsExport"] = json_encode($generatedDocumentspath);
            $fileData["claimsExportDocPath"] = $dest['relativePath'] .  $this->excelTemplate;
            $this->saveFile($fileData, $fileData["uuid"]);
        } else {
            throw new Exception("No Records found");
        }
        return $data;
    }

    private function excelDataMassage($data)
    {
        $result = array();
        foreach ($data as $j => $item) {
            foreach ($item as $i => $response) {
                if (
                    $i == "valueDate" ||
                    $i == "lossDate" ||
                    $i == "reportedDate"
                ) {
                    $result[$i][$j] = $this->formatDate($item[$i]);
                } else if (
                    $i == "state"
                ) {
                    $stateValue = $item[$i];
                    $stateValue = isset(($this->checkJSON($stateValue))["abbreviation"]) ?
                        ($this->checkJSON($stateValue))["abbreviation"] : "";
                    $result[$i][$j] = $stateValue;
                } else if (
                    $i == "effectiveMonth"
                ) {
                    $monthName = $item[$i];
                    $monthName = isset(($this->checkJSON($monthName))["name"]) ?
                        ($this->checkJSON($monthName))["name"] : "";
                    $result[$i][$j] = $monthName;
                } else if (
                    $i == "claimlevel1" || $i == "claimlevel2"
                ) {
                    $levelValue = $item[$i];
                    if (!is_array($this->checkJSON($levelValue))) {
                        $result[$i][$j] = $levelValue;
                    }
                } else if (
                    $i == "lossYear"
                ) {
                    $yearValue = $item[$i];
                    $result[$i][$j] = $yearValue ?
                        substr($yearValue, 0, 2) . '-' . substr($yearValue, 2, 4) : "";
                } else if (
                    $i == "namedInsured" ||
                    $i == "lookupCode" ||
                    $i == "dba" ||
                    $i == "mailingAddress" ||
                    $i == "city" ||
                    $i == "naicsCode" ||
                    $i == "carrier" ||
                    $i == "paid" ||
                    $i == "reserve" ||
                    $i == "subro" ||
                    $i == "lae" ||
                    $i == "total" ||
                    $i == "lrPdfReference"
                ) {
                    $result[$i][$j] = $item[$i];
                }
            }
        }
        return $result;
    }

    private function checkJSON($data)
    {
        if (!is_array($data)) {
            $data = json_decode($data, true);
        }
        return $data;
    }


    private function formatDate($data)
    {
        $date = strpos($data, "T") ? explode("T", $data)[0] : $data;
        if (is_null($date) || empty($date)) {
            return "Invalid Date";
        } else {
            return date(
                "m-d-Y",
                strtotime($date)
            );
        }
    }
}
