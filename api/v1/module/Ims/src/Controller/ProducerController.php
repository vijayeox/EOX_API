<?php
namespace Ims\Controller;

use Ims\Controller\AbstractController;

class ProducerController extends AbstractController
{
    public function __construct($insuranceService)
    {
        parent::__construct($insuranceService, 'ProducerFunctions');
    }

}