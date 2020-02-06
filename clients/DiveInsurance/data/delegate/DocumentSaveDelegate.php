<?php
use Oxzion\AppDelegate\AbstractDocumentAppDelegate;
use Oxzion\Db\Persistence\Persistence;
use Oxzion\Utils\UuidUtil;
class DocumentSaveDelegate extends AbstractDocumentAppDelegate {
    public function __construct() {
        parent::__construct();
    }
    public function execute(array $data, Persistence $persistenceService) {
        if (isset($data['attachmentsFieldnames'])) {
            if (!isset($data['fileId'])) {
                $data['uuid'] = isset($data['uuid']) ? $data['uuid'] : UuidUtil::uuid();
            } else {
                $data['uuid'] = $data['fileId'];
            }
            $attachmentsFieldnames = $data['attachmentsFieldnames'];
            for ($i = 0;$i < sizeof($attachmentsFieldnames);$i++) {
                $fieldNamesArray =is_string($attachmentsFieldnames[$i]) ? array($attachmentsFieldnames[$i]) : $attachmentsFieldnames[$i];
                if (sizeof($fieldNamesArray) == 1) {
                    $fieldName = $fieldNamesArray[0];
                    $data[$fieldName] = $this->saveFile($data, $data[$fieldName]);
                } else if (sizeof($fieldNamesArray) == 2) {
                    $gridFieldName = $fieldNamesArray[0];
                    $fieldName = $fieldNamesArray[1];
                    for ($i = 0;$i < sizeof($data[$gridFieldName]);$i++) {
                        if (isset($data[$gridFieldName][$i][$fieldName])) {
                            $data[$gridFieldName][$i][$fieldName] = $this->saveFile($data, $data[$gridFieldName][$i][$fieldName]);
                        }
                    }
                }
            }
        }
        return $data;
    }

    public function saveFile(array $data, $documentsArray) {
        if (!isset($data['orgId'])) {
            $data['orgId'] = $this->getOrgId();
        }
        $filepath = $data['orgId'] . '/' . $data['uuid'] . '/';
        if (!is_dir($this->destination . $filepath)) {
            mkdir($this->destination . $filepath, 0777, true);
        }
        for ($i = 0;$i < sizeof($documentsArray);$i++) {
            $base64Data = explode(',', $documentsArray[$i]['url']);
            $content = base64_decode($base64Data[1]);
            $file = fopen($this->destination . $filepath . $documentsArray[$i]['name'], 'wb');
            fwrite($file, $content);
            fclose($file);
            unset($documentsArray[$i]['url']);
            $documentsArray[$i]['file'] = $filepath . $documentsArray[$i]['name'];
        }
        return $documentsArray;
    }
}