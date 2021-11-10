<?php

use Oxzion\AppDelegate\FileTrait;

class FetchExistingClaimData
{
    use FileTrait;
    protected $requiredFieldSet = [
        "valueDate",
        "lookupCode",
        "effectiveMonth",
        "lossYear",
        "dba",
        "mailingAddress",
        "city",
        "state"
    ];

    public function execute(array $data)
    {
        if (isset($data['bos']['parentFileId'])) {
            $assocId = $data['bos']['parentFileId'];
        } else {
            return $data;
        }
        $params = array(
            "entityName" => "Policy Claims",
            "assocId" => $assocId,
            "orgId" => "34bf01ab-79ca-42df-8284-965d8dbf290e"
        );
        $filterParams = array(
            ["skip" => 0, "take" => 10000]
        );
        $files = $this->getFileList($params, $filterParams);

        if ($files["total"] > 0) {
            $recentFile = $files["data"][($files["total"] - 1)];
            $fileData = array();
            foreach ($this->requiredFieldSet as $field) {
                if (isset($recentFile[$field])) {
                    $fileData[$field] = $recentFile[$field];
                }
            }
            return $fileData;
        } else {
            throw new Exception("No records found");
        }
    }
}
