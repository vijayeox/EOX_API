<?php
namespace Ims\Controller;

use Ims\Controller\AbstractController;

class InsuredController extends AbstractController
{
    public function __construct($insuranceService)
    {
        parent::__construct($insuranceService, 'InsuredFunctions');
    }

}