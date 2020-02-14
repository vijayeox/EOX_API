<?php
namespace Oxzion\Analytics\MySQL;

use Zend\Db\Adapter\Adapter;
use Zend\Db\Sql\Sql;
use Oxzion\Analytics\AnalyticsEngine;
use Oxzion\Auth\AuthContext;
use Oxzion\Auth\AuthConstants;
use Oxzion\Analytics\AnalyticsPostProcessing;
use Zend\Db\ResultSet\ResultSet;

class AnalyticsEngineMySQLImpl implements AnalyticsEngine {
	private $config;
	private $dbAdapter;

    public function __construct($config) {
		$this->config = $config;
		$dbConfig['driver'] = 'Pdo';
		$dbConfig['database'] = $config['database'];
		$dbConfig['host'] = $config['host'];
		$dbConfig['username'] = $config['username'];
		$dbConfig['password'] = $config['password'];
		$dbConfig['dsn'] = 'mysql:dbname=' . $config['database'] . ';host=' . $config['host'] . ';charset=utf8;username=' . $config["username"] . ';password=' . $config["password"] . '';
        $this->dbAdapter = new Adapter($dbConfig);
    }

    public function runQuery($app_name,$entity_name,$parameters)
    {
        try {
			
			$orgId = AuthContext::get(AuthConstants::ORG_ID);
			if (isset($parameters['view'])) {
				$result = $this->getResultsFromView($orgId,$parameters['view']);	
				$finalResult['meta']['type'] = 'view';
				foreach(array_keys($result) as $key) {
					unset($result[$key]['org_id']);
				 }
				 $finalResult['data'] = $result;
			} else {
				$formatedPara = $this->formatQuery($parameters);
				$result = $this->getResultsFromPara($orgId,$app_name,$entity_name,$formatedPara);
			}
			if (isset($parameters['expression']) ||  isset($parameters['round']) ) {
				$finalResult['data'] = AnalyticsPostProcessing::postProcess($finalResult['data'],$parameters);
			}
			return $finalResult;
			
        } catch (Exception $e) {
            throw new Exception("Error running MySQL Analytics", 0, $e);
        }
    }

	private function formatQuery($parameters) {
		$range=null;
		$field = null;
		$filter =array();
		$datetype = (!empty($parameters['date_type']))?$parameters['date_type']:null;
		if (!empty($parameters['date-period'])) {
			$period = explode('/', $parameters['date-period']);
			$startdate = date('Y-m-d', strtotime($period[0]));
			$enddate =  date('Y-m-d', strtotime($period[1]));
		} else {
			$startdate = date('Y').'-01-01';
			$enddate = date('Y').'-12-31';
		}
		if (!empty($parameters['field'])) {
			if (substr(strtolower($parameters['field']), 0, 5) == 'date(') {
				$parameters['field'] = substr($parameters['field'], 5, -1);
			}
			$field = $parameters['field'];
		}

		if (!isset($parameters['operation'])) {
			$parameters['operation'] = 'count';
		}
		$operation = strtolower($parameters['operation']);
		$group = array();

		if (!empty($parameters['group'])) {
			$parameters['frequency'] = null;  //frequency 4 is to override time frequecy by group
			if (is_array($parameters['group'])) {
				$group = $parameters['group'];
			} else {
				$group = explode(',',$parameters['group']);
			}
		} 
		$select = array();
		if ($field) { 
			$select = [$field,"$operation($field)"];
		} 
		else {
				if (!isset($parameters['list'])) {
					if (!empty($group)) {
						$select = ["$operation($field)"];
					} else {
						$select = ["count(*)"];
				}
			}
		}
		if (!empty($parameters['frequency'])) {
			switch ($parameters['frequency']) {
				case 1:
					$group[] = "$datetype";
					break;
				case 2:
					$group[] = "MONTH($datetype)";
					break;
				case 3:
					$group[] = "QUARTER($datetype)";
					break;
				case 4:
					$group[] = "YEAR($datetype)";
					break;
			}
		}
		if ($datetype)
			$range = "$datetype between '$startdate' and '$enddate'";
		if (isset($parameters['filter'])) {
			$filter = $parameters['filter'];  // Need to put logic for this
		}
		$returnarray = array('group' => $group, 'range' => $range, 'select' => $select);
		if (isset($parameters['pagesize'])) {
			$returnarray['limit'] = $parameters['limit'];
		}
		if (isset($parameters['list'])) {
			$listConfig=explode(",", $parameters['list']);
			foreach ($listConfig as $k => $v) {
				if(strpos($v, "=")!==false){
					$listitem = explode("=", $v);
					$returnarray['select'][] = $listitem[0];
					$returnarray['displaylist'][] = $listitem[1];
				} else {
					$returnarray['select'][] = $v;
					$returnarray['displaylist'][] = $v;
				}
			}
		}	
		if (isset($parameters['sort'])) {
			$returnarray['sort'] = $parameters['sort'];
		}
		return $returnarray;
	}

	public function getResultsFromView($orgId,$view) {
		$sql    = new Sql($this->dbAdapter);
		$select = $sql->select();
		$select->from($view);
		$select->where(['org_id' => $orgId]);

		$statement = $sql->prepareStatementForSqlObject($select);
		$result = $statement->execute();
		$resultSet = new ResultSet();
        return $resultSet->initialize($result)->toArray();
		
	}

	public function getResultsFromPara($orgId,$app_name,$entity_name,$para) {
		
		$sql    = new Sql($this->dbAdapter);
		$select = $sql->select();
		$select = $sql->columns($para['select']);
		$select->from($entity_name);
		$select->where(['org_id' => $orgId]);
		if (!empty($para['range'])) {
			$select->where($para['range']);
		}
		if (!empty($para['group'])) {
			$select->group($para['group']);
		}
		if (!empty($para['limit'])) {
			$select->group($para['limit']);
		}
		$statement = $sql->prepareStatementForSqlObject($select);
		$result = $statement->execute();
		$resultSet = new ResultSet();
        return $resultSet->initialize($result)->toArray();

	}

}
?>