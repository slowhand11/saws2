<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace saws\sawsconnector\Model\Import\SawsConnector;

/**
 * Description of Abstract
 *
 * @author michael
 */
abstract class AbstractImportType {
  protected $objResponse;

  function __construct() {
    $this->objResponse = \Magento\Framework\App\ObjectManager::getInstance()->get('saws\sawsconnector\Helper\Response');
    //$this->objResponse = $objResponse;
  }

  protected function getMultipleValueSeparator() {
    return ',';
  }

  abstract function processImport($arrData, $options=array());

  private function logMessage($strMessage, $bError) {
    /*
      Method          Iterations    Average Time
      --------------  ------------  --------------
      explode       : 10,000        0.0000020221710
      substr        : 10,000        0.0000017177343
      reflection    : 10,000        0.0000015984058
    */
    $strName = strtolower((new \ReflectionClass($this))->getShortName());
    $this->objResponse->addLogEntry($strMessage, 'global', $strName, 0, $bError);
  }

  function logError($strMessage){
    $this->logMessage($strMessage, true);
  }

  function logInfo($strMessage){
    $this->logMessage($strMessage, false);
  }
}
