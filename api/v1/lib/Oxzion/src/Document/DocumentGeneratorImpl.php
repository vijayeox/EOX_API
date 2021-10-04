<?php
namespace Oxzion\Document;

use Oxzion\Service\TemplateService;
use TCPDF;
use Knp\Snappy\Pdf;
use setasign\Fpdi\Tcpdf\Fpdi;
use Oxzion\Utils\FileUtils;
use mikehaertl\pdftk\Pdf as PDFTK;
use Logger;
use Exception;

class DocumentGeneratorImpl implements DocumentGenerator
{
    private $logger;

    public function __construct()
    {
        $this->logger = Logger::getLogger(__CLASS__);
    }

    public function generateDocument($htmlContent, $destination, array $options, $signatureCerticate=null)
    {
        // if (!$options) {
        //     $file = '/var/www/lib/Oxzion/test/DocumentTest/headerInfo.json';
        //     $json = file_get_contents($file);
        //     $options = json_decode($json, true);
        // }

        // create new PDF document
        $pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

        // set default header data
        if (!empty($options)) {
            $pdf->SetHeaderData($options['pdf_header_logo'], $options['pdf_header_logo_width'], $options['initial_title'], $options['second_title'], $options['header_text_color'], $options['header_line_color']);

            $pdf->setFooterData($options['footer_text_color'], $options['footer_line_color']);
        } else {
            $pdf->setPrintHeader(false);
            $pdf->setPrintFooter(false);
        }

        // set margins
        $pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
        $pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
        $pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

        // set auto page breaks
        $pdf->SetAutoPageBreak(true, PDF_MARGIN_BOTTOM);

        // set image scale factor
        $pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

        $pdf->setFontSubsetting(true);

        // Add a page
        // This method has several options, check the source code documentation for more information.
        $pdf->AddPage();

        $pdf->writeHTMLCell(0, 0, '', '', $htmlContent, 0, 1, 0, true, '', true);
        // $pdf->writeHTML($htmlContent);
        // TO DO DIGITAL SIGNATURE CERTIFICATE
        // Refer https://tcpdf.org/examples/example_052/
        /* if($signatureCerticate){
        // set additional information
        $info = array(
            'Name' => 'TCPDF',
            'Location' => 'Office',
            'Reason' => 'Testing TCPDF',
            'ContactInfo' => 'http://www.tcpdf.org',
        );

        // set document signature
        $pdf->setSignature($signatureCerticate, $signatureCerticate, 'tcpdfdemo', '', 2, $info);

        $pdf->AddPage();

        // print a line of text
        $pdf->writeHTML($htmlContent, true, 0, true, 0);

        // create content for signature (image and/or text)
        $pdf->Image('images/tcpdf_signature.png', 180, 60, 15, 15, 'PNG');

        // define active area for signature appearance
        $pdf->setSignatureAppearance(180, 60, 15, 15);

        // *** set an empty signature appearance ***
        $pdf->addEmptySignatureAppearance(180, 80, 15, 15);

        } */

        $path = $pdf->output($destination, 'F');
        return $destination;
    }

    public function generatePdfDocumentFromHtml($htmlContent, $destination, $header = null, $footer = null, $data = null, $append = null, $prepend = null, $generateOptions = null)
    {
        $appendOptions = "";
        $prependOptions = "";
        $finalpdf = array();
        $dest = $destination;
        $myProjectDirectory = __DIR__."/../../../..";
        if (isset($data)) {
            foreach ($data as $key => $value) {
                $docData = json_decode($value, true);
                if (is_array($docData)) {
                    unset($data[$key]);
                }
            }
        }
        $snappy = new Pdf($myProjectDirectory . '/vendor/h4cc/wkhtmltopdf-amd64/bin/wkhtmltopdf-amd64');
        $snappy->setOption("load-error-handling", 'ignore');
        $snappy->setOption("load-media-error-handling", 'ignore');
        if (isset($generateOptions['disable_smart_shrinking'])) {
            $snappy->setOption("disable-smart-shrinking", true);
        }
        $snappy->setOption("header-html", $header);
        $snappy->setOption("footer-html", $footer);
        $snappy->setOption('replace', $data);

        if (isset($append) && !empty($append) || isset($prepend) && !empty($prepend)) {
            $dest = sys_get_temp_dir().'/COI.pdf';
            if (file_exists($dest)) {
                FileUtils::deleteFile('/COI.pdf', sys_get_temp_dir());
            }
        }
        $snappy->generateFromHtml($htmlContent, $dest);

        if (isset($prepend) && count($prepend) > 0) {
            foreach ($prepend as $key => $value) {
                $prependOptions = $value;
            }
            array_push($finalpdf, $prependOptions);
        }


        array_push($finalpdf, $dest);


        if (isset($append) && count($append) > 0) {
            foreach ($append as $key => $value) {
                $appendOptions = $value;
            }
            array_push($finalpdf, $appendOptions);
        }

        $destination = $this->mergeDocuments($finalpdf, $destination);
        return $destination;
    }
    // public function generateDocumentFromFile($filePath,$destination){

    // }

    public function mergeDocuments($sourceArray, $destination)
    {
        $pdf = new Fpdi();

        foreach ($sourceArray as $file) {
            // get the page count
            $pageCount = $pdf->setSourceFile($file);
            // iterate through all pages
            for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
                $pdf->setPrintHeader(false);
                $pdf->setPrintFooter(false);
                // import a page
                $templateId = $pdf->importPage($pageNo);
                // get the size of the imported page
                $size = $pdf->getTemplateSize($templateId);

                // create a page (landscape or portrait depending on the imported page size)
                if ($size['width'] > $size['height']) {
                    $pdf->AddPage('L', array($size['width'], $size['height']));
                } else {
                    $pdf->AddPage('P', array($size['width'], $size['height']));
                }

                // use the imported page
                $pdf->useTemplate($templateId);
            }
        }
        $path = $pdf->output($destination, 'F');

        return $destination;
    }

    public function fillPDFForm($form, $data, $destination, bool $flattenFields = false)
    {
        // create new PDF document
        $pdf = new PDFTK($form);
        $result = $pdf->fillForm($data)
                    ->{($flattenFields) ? 'flatten' : 'needAppearances'}()
                    ->saveAs($destination);
        if (!$result) {
            throw new Exception($pdf->getError());
        }
        return $result;
    }

    public function mergePdf(array $pdfs, string $destination)
    {
        $pdfObj = new PDFTK();
        foreach ($pdfs as $pdf) {
            if (!is_file($pdf)) throw new Exception("File does not exist at ".$pdf, 404);
            $pdfObj->addFile($pdf);
        }
        $result = $pdfObj->needAppearances()
                        ->saveAs($destination);
        if (!$result) throw new Exception($pdfObj->getError());
        return $result;
    }

}