<?php
namespace Ims\Controller;

use Ims\Controller\AbstractController;

class DocumentController extends AbstractController
{
    public function __construct($imsService)
    {
        parent::__construct($imsService, 'DocumentFunctions');
    }

}