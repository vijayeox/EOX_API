<?php
namespace Ims\Controller;

use Ims\Controller\AbstractController;

class DocumentController extends AbstractController
{
    public function __construct($insuranceService)
    {
        parent::__construct($insuranceService, 'DocumentFunctions');
    }

}