<?php
namespace Oxzion\Document\Template\Smarty;

use PHPUnit\Framework\TestCase;
use \Exception;
use Oxzion\Document\Template\Smarty\SmartyTemplateProcessorImpl;

class SmartyTemplateProcessorTest extends TestCase
{
    private $file;
    private $parser;
    public function setUp() : void
    {
        $this->parser = new SmartyTemplateProcessorImpl();
    }

    public function testInit()
    {
        $params['templateDir'] = __DIR__."/Data/";
        $params['cacheDir'] = __DIR__."/Data/";
        $params['configsDir'] = __DIR__."/Data/";
        $params['compileDir'] = __DIR__."/Data/";
        $params['OITemplateDir'] = __DIR__."/Data/";
        $this->parser->init($params);
        $this->assertEquals(true, true);
    }

    public function testGetContent()
    {
        $data = ['username' => 'John','orgid'=>3];
        $params['templateDir'] = __DIR__."/template/";
        $params['cacheDir'] = __DIR__."/template/";
        $params['configsDir'] = __DIR__."/template/";
        $params['compileDir'] = __DIR__."/template/";
        $params['OITemplateDir'] = __DIR__."/template/";
        $this->parser->init($params);
        copy(__DIR__."/template/GenericTemplate.tpl", __DIR__."/template/Template.tpl");
        copy(__DIR__."/template/Template.tpl", __DIR__."/../../../../data/template/Template.tpl");
        $options = array();
        $template['templateNameWithExt'] = 'Template.tpl';
        $template['templatePath'] = __DIR__."/../../../../data/template/";
        $content = $this->parser->getContent($template, $data, $options);
        $temp = "Hello ".$data['username'].", this is a generic template.</p>";
        $this->assertEquals(strpos($content, $temp), 3);
        unlink(__DIR__."/template/Template.tpl");
        unlink(__DIR__."/../../../../data/template/Template.tpl");
    }
}
