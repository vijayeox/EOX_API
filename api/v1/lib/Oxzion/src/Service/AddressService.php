<?php
namespace Oxzion\Service;

use Oxzion\Auth\AuthContext;
use Oxzion\Auth\AuthConstants;
use Oxzion\ValidationException;
use Oxzion\Service\AbstractService;
use Oxzion\Model\Address;
use Oxzion\Model\AddressTAble;
use Oxzion\Messaging\MessageProducer;
use Oxzion\Security\SecurityManager;
use Oxzion\AccessDeniedException;
use Oxzion\ServiceException;


class AddressService extends AbstractService
{
    protected $table;
    protected $modelClass;
    /**
     * @ignore __construct
     */
    public function __construct($config, $dbAdapter, AddressTable $table)
    {
        parent::__construct($config, $dbAdapter);
        $this->table = $table;
        $this->modelClass = new Address();
    }

    public function addAddress($data){
        $form = new Address($data);
        $form->validate();
        $this->beginTransaction();
        $count = 0;
        try{
            $count = $this->table->save($form);
            if ($count == 0) {
                $this->rollback();
                throw new ServiceException("Failed to add the address","failed.add.address");
            }
        }
        catch(Exception $e){
            throw $e;
        }        
        return $this->table->getLastInsertValue();   
    }

    public function updateAddress($id,$data){
        $obj = $this->table->get($id, array());
        if (is_null($obj)) {
            throw new ServiceException("Address not found","address.not.found");
        }
        $org = $obj->toArray();
        $form = new Address();
        $changedArray = array_merge($obj->toArray(), $data);
        $form->exchangeArray($changedArray);
        $form->validate();
        $this->beginTransaction();
        $count = 0;
        try {
            $count = $this->table->save($form);
            $this->commit();
        }
        catch(Exception $e){
            throw $e;
        }
    }

}