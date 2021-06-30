<?php
namespace Ims\Controller;

use Ims\Controller\AbstractController;

class QuoteController extends AbstractController
{
    public function __construct($insuranceService)
    {
        parent::__construct($insuranceService, 'QuoteFunctions');
    }

}