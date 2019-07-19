<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace saws\sawsconnector\Model;

use saws\sawsconnector\Api\SawsConnectorInterface;
/**
 * Description of SawsConnector
 *
 * @author michael
 */
class SawsConnector implements SawsConnectorInterface {
  const DEBUG_TOKEN = '__DEBUG_SAWS__';
  /*
   * @var saws\sawsconnector\Model\Import\Data\Data
   */
  private $objData;
  private $objResponse;
  private $objLog; // deprecated alias for response object

  private $arrImportOrder = array(
      'attributes',
      'images',
      'categories', 'deletecategories',
      'products', 'deleteproducts',
      'productandcategories'
  );

  function __construct(
    \saws\sawsconnector\Helper\Response $response
  ) {
    $this->objResponse = $response;
    $this->objLog =& $this->objResponse;
  }

  private function parseInput() {
    $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
    $config = $objectManager->get('saws\sawsconnector\Helper\Config');

    $input = trim(file_get_contents('php://input'));
    if (!$input) throw new \Exception('invalid data');;

    $arrData = json_decode($input, true);
    if (is_array($arrData)) $this->arrData = array();

    $strMethod  = isset($arrData['method']) ? $arrData['method'] : '';
    $strToken   = $arrData['token'];
    if (!isset($arrData['data'])) $arrData['data'] = array();

    if (is_array($arrData['data'])) {
      $strData = json_encode($arrData['data']);
      $arrData = $arrData['data'];
    }
    else {
      $strData = $arrData['data'];
      $arrData = json_decode($strData,true);
    }

    $strHash = md5(sha1($strMethod).strlen($strData).'--'.$config->getImportToken());
    $strMethod = 'execute'.ucfirst(strtolower($strMethod));
    if (($strToken != self::DEBUG_TOKEN && $strHash != $strToken) || !is_callable(array($this, $strMethod))) throw new \Exception('invalid method');
    if (!is_array($arrData)) $arrData=array();

    $this->objData = $objectManager->create('saws\sawsconnector\Model\Import\Data\Data', array('data'=>$arrData));
    return $strMethod;
  }

  private function getData($strKey=null) {
    if (!$this->objData) return false;
    return $this->objData->getData($strKey);
  }

  private function createProcessID() {
    $strTime = time();
    return substr(md5(microtime().rand(0, 999999)), 0, strlen($strTime)*(-1)) . $strTime;
  }

  private function getProcessPath($strID, $bCreate=true) {
    $strPath = 'var/sawsconnector/'.$strID.'/';
    if (!is_dir($strPath) && $bCreate) {
      mkdir('var/sawsconnector/'.$strID.'/', 0750, true);
    }
    return (is_dir($strPath)) ? $strPath : false;
  }

  private function deleteProcessPath($intID) {
    $strPath = preg_replace('/\/$/', '', $this->getProcessPath($intID, false));
    if (!$strPath) return true;

    $func = function ($dir) {
      if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
          if ($object == "." || $object == "..") continue;
          if (is_dir($dir."/".$object)) $func($dir."/".$object); else unlink($dir."/".$object);
        }
        rmdir($dir);
      }
    };

    $func($strPath);
    return !is_dir($strPath);
  }

  public function executeOpenprocess() {
    $strProcessID = $this->createProcessID();
    $strPath = $this->getProcessPath($strProcessID);
    if ($strPath == false) {
      throw new \Exception('open process failed');
    }
    return array('process' => array('id'=>$strProcessID, 'state'=>'listening'));
  }

  public function executeFinalizeprocess() {
    $strProcessID = $this->getData('processid');
    if (!$this->deleteProcessPath($strProcessID)) {
      throw new \Exception('finalize failed');
    }
    return array('process' => array('id' => $strProcessID, 'state' => 'finished'));
  }

  public function executeAbortprocess() {
    try {
      $arrReturn = $this->executeFinalizeprocess();
      $arrReturn['state'] = 'aborted';
      return $arrReturn;
    }catch(\Exception $e) {
      throw new \Exception('abort failed');
    }
  }

  public function executeGetmagentoversion() {
    $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
    $productMetadata = $objectManager->get('Magento\Framework\App\ProductMetadataInterface');
    return $productMetadata->getVersion(); //will return the magento version
  }

  public function executeGetconnectorversion() {
    $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
    $moduleInfo = $objectManager->get('Magento\Framework\Module\ModuleList')->getOne('saws_sawsconnector'); // SR_Learning is module name
    return $moduleInfo['setup_version'];
  }

  public function executeData() {

    ignore_user_abort(true);
    error_reporting(E_ERROR);
    ini_set('display_errors', '0');
    set_time_limit(7200);
    ini_set('memory_limit', "1024M");

    try {
      error_reporting(E_ERROR);
      $strMethod = $this->parseInput();
      return json_encode(call_user_func(array($this, $strMethod)));
    }
    catch (\Exception $e) {
      //$this->objResponse->addLogEntry($e->getMessage(), 'global', 'execData', 0, true);
      return $e->getMessage();
    }
  }

  public function executeGetversion(){
    $response = array(
        'version' => $this->executeGetconnectorversion(),
        'magentoversion' => $this->executeGetmagentoversion(),
        'module'  => true,
    );
    return $response;
  }

  public function executeImport() {
    $objResponse = $this->objResponse;

    $arrImportData = array_filter($this->getData('importdata'));
    $objectManager = \Magento\Framework\App\ObjectManager::getInstance();

    $objSCImportFactory = $objectManager->get('saws\sawsconnector\Model\Import\SawsConnector\Factory');

    $arrImportOptions = isset($arrImportData['options'])?$arrImportData['options']:array();
    unset($arrImportData['options']);

    $arrImportOrder = array_intersect($this->arrImportOrder, array_keys($arrImportData));

    foreach ($arrImportOrder as $type) {
      try {
        $options = isset($arrImportOptions[$type]) ?  $arrImportOptions[$type] : array();
        $objSCImport = $objSCImportFactory->create($type);
        $objSCImport->processImport($arrImportData[$type], $options);
      }
      catch (\InvalidArgumentException $e) {
        $this->objResponse->addLogEntry($e->getMessage(), 'global', $type, 0, true);
      }
      catch (\Exception $e) {
        $this->objResponse->addLogEntry($e->getMessage(), 'global', $type, 0, true);
      }
    }

    return $objResponse->getEntityMap();
  }

  function executeSyncronizemagento(){
    $arrResponse['syncronizemagento'] = array();


    $connection = \Magento\Framework\App\ObjectManager::getInstance()->get('Magento\Framework\App\ResourceConnection');
    $arrTables = $connection->getConnection()->fetchCol('show tables');

    if (in_array($connection->getTableName('cms_block'), $arrTables)){
      $objQuery = 'SELECT DISTINCT identifier as code, title as name FROM '. $connection->getTableName('cms_block');
      $arrRows = $connection->getConnection()->fetchAll($objQuery);
      $arrResponse['syncronizemagento']['Cmsblocks'] = $arrRows;
    }

    $objQuery = 'SELECT code, name FROM ' . $connection->getTableName('store');
    $arrRows = $connection->getConnection()->fetchAll($objQuery);
    $arrResponse['syncronizemagento']['Storeviews'] = $arrRows;

    $objQuery = 'SELECT customer_group_id as code, customer_group_code as name FROM ' .$connection->getTableName('customer_group');
    $arrRows = $connection->getConnection()->fetchAll($objQuery);
    $arrResponse['syncronizemagento']['Customergroups'] = $arrRows;

    $objQuery = 'SELECT code, name FROM ' . $connection->getTableName('store_website');
    $arrRows = $connection->getConnection()->fetchAll($objQuery);
    $arrResponse['syncronizemagento']['Websiteshidden'] = $arrRows;

    if (in_array($connection->getTableName('seo_block'), $arrTables)){
      $objQuery = 'SELECT DISTINCT identifier as code, title as name FROM ' .$connection->getTableName('seo_block');
      $arrRows = $connection->getConnection()->fetchAll($objQuery);
      $arrResponse['syncronizemagento']['Seoblocks'] = $arrRows;
    }
    $arrResponse['responsecode'] = 200;

    return $arrResponse;
  }

  public function executeReindex(){
    $objResponse = $this->objResponse;
    $intStartTime = $objResponse->microtimeFloat();
    $objResponse->addLogEntry('Start reindex process.' ,'global', 'reindex', 0);
    $registry = \Magento\Framework\App\ObjectManager::getInstance()->get('\Magento\Indexer\Model\Processor');
    $registry->reindexAllInvalid();
    $objResponse->addLogEntry('Finish reindex process.' ,'global', 'reindex', $objResponse->getDuration($intStartTime));
    return $objResponse->getEntityMap();
  }

  public function executeUpdateversion(){
    $objResponse = $this->objResponse;
    $objResponse->addLogEntry('not supported anymore','global', 'updateversion', true);
    return $objResponse->getEntityMap();
  }

}