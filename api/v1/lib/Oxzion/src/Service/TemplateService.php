<?php
namespace Oxzion\Service;

use Exception;
use Oxzion\Document\Template\Excel\ExcelTemplateProcessorImpl;
use Oxzion\Document\Template\Smarty\SmartyTemplateProcessorImpl;
use Oxzion\Service\AbstractService;
use Oxzion\Utils\ArtifactUtils;

class TemplateService extends AbstractService
{
    private $client;
    private $excelClient;
    private $templateName;
    private $templateExt = ".tpl";
    private $templateDir;

    const HTML_TEMPLATE = 1;
    const EXCEL_TEMPLATE = 2;

    public function __construct($config, $dbAdapter)
    {
        parent::__construct($config, $dbAdapter);
        $this->init();
    }

    public function init()
    {
        $templateDir = $this->config['TEMPLATE_FOLDER'];
        $this->templateDir = $templateDir;
        $this->client = new SmartyTemplateProcessorImpl();
        $params = $this->getTemplateFolderList($templateDir);
        $this->client->init($params);
        $this->excelClient = new ExcelTemplateProcessorImpl();
        $this->excelClient->init();
    }

    public function getTemplateFolderList($templateDir)
    {
        if (!file_exists($templateDir)) {
            mkdir($templateDir, 0777, true);
        }
        if (!file_exists($cacheDir = $templateDir . '/cache/')) {
            mkdir($cacheDir, 0777);
        }
        if (!file_exists($configsDir = $templateDir . '/configs/')) {
            mkdir($configsDir, 0777);
        }
        if (!file_exists($templatescDir = $templateDir . '/templates_c/')) {
            mkdir($templatescDir, 0777);
        }
        if (!file_exists($OITemplateDir = $templateDir . '/OITemplate/')) {
            mkdir($OITemplateDir, 0777);
        }
        return array('cacheDir' => $cacheDir, 'configsDir' => $configsDir, 'templateDir' => $templateDir, 'compileDir' => $templatescDir, 'OITemplateDir' => $OITemplateDir);
    }

    /**
     * Gets the template content.
     *
     * @param      String     $templateName  The template name
     * @param      array      $data          The data element pathed in the template
     *@param      array      $options - templateType along with templator processor supported option. For example: ExcelTemplateProcessorImpl
     *
     * @throws     Exception  (If the template is not found)
     *
     * @return     String     The template content.
     */
    public function getContent($templateName, $data = array(), $options = array())
    {
        $this->logger->info("Template Name:" . $templateName);
        $this->logger->info("Data context" . print_r($data, true));

        $template = $this->getTemplateDir($templateName, $data, $options);
        $this->logger->info("Template Directory:" . print_r($template['templatePath'], true));
        $this->logger->info("Template Name with Extension:" . print_r($template['templateNameWithExt'], true));
        if (!$template) {
            throw new Exception("Template not found!");
        }

        if (isset($options['templateType']) && $options['templateType'] == static::EXCEL_TEMPLATE) {
            $client = $this->excelClient;
            unset($options['templateType']);
        } else {
            $client = $this->client;
        }
        try {
            $content = $client->getContent($template, $data, $options);
            $this->logger->info("TEMPLATE CONTENT" . print_r($content, true));
        } catch (Exception $e) {
            print("Error - " . $e->getMessage() . "\n");
            throw $e;
        }
        return $content;
    }

    private function getTemplateDir($templateName, $params = array(), $options = null)
    {
        $this->logger->info("in getTemplateDir");
        if (isset($options['templateType']) && $options['templateType'] == static::EXCEL_TEMPLATE) {
            $template['templateNameWithExt'] = $templateName;
        } else {
            $template['templateNameWithExt'] = $templateName . $this->templateExt;
        }
        $template['templatePath'] = $this->getTemplatePath($template['templateNameWithExt'], $params);
        return $template;
    }

    public function getTemplatePath($template, $params = array())
    {
        $this->logger->info("Params - " . print_r($params, true));
        $this->logger->debug("In getTemplatePath");
        return ArtifactUtils::getTemplatePath($this->config, $template, $params);
    }
}
