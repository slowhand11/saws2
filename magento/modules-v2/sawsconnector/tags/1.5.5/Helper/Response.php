<?php


namespace saws\sawsconnector\Helper;

class Response extends \Magento\Framework\App\Helper\AbstractHelper {
  protected $arrLogEntries = array();
  protected $arrEntityMap = array();
  private $thisLastRunTime = 0;
  private $intRecordCount = 0;
  private $bolHasErrors = false;
  
  public function addEntities($arrValues, $strType, $arrFields, $strKeyField){
    
    foreach ($arrValues as $strKey => $arrEnityEntry){
      if ($strKeyField !== false) {
        if (!isset($arrEnityEntry[$strKeyField])) continue;
        $strKey = $arrEnityEntry[$strKeyField];
      }
      $this->arrEntityMap[$strType][$strKey] = array_intersect_key($arrEnityEntry, array_flip($arrFields));
    }
    
  }
  
  public function resetEntity($strType = ''){
    if (!$strType) {
      unset ($this->arrEntityMap);
    }else{
      unset($this->arrEntityMap[$strType]);
    }
  }
  
  public function addLogEntry($strMessage, $strKey, $strEntityType = 'product', $intTime = 0, $bolIsError = false, $strDebug = ''){
    if ($bolIsError) $this->bolHasErrors = true;
    //$this->arrEntityMap[$strEntityType][$strKey]['log'][] = $strMessage;
    if ($intTime == -1){
      $intTime = number_format(($this->microtimeFloat() - $this->thisLastRunTime),4);
    }
    $this->arrEntityMap[$strEntityType]['log'][] = $arrMessage = array('key' => $strKey, 'message' =>$strMessage, 'time' => time(), 'duration' => $intTime, 'isError' => $bolIsError, 'debug' => $strDebug);
    if (isset($this->arrEntityMap[$strEntityType][$strKey]) && is_array($this->arrEntityMap[$strEntityType][$strKey]))  {
      if ($bolIsError) $this->arrEntityMap[$strEntityType][$strKey]['haserrors'] = true;
    }
    $this->addToFileLog($arrMessage);
    $this->thisLastRunTime = $this->microtimeFloat();
  }
  
  private function addToFileLog($arrMessage){
    $strLogDir = 'var/log/sawsconnector/'.date("Y").'/'.date("Y-m");
    if(!is_dir($strLogDir)) mkdir($strLogDir, 0777, true);
    $arrMessage['isError'] = ($arrMessage['isError']) ? 'ERROR' : 'INFO';
    file_put_contents('var/log/sawsconnector/'.date("Y").'/'.date("Y-m").'/'.date("Y-m-d").'.log', "\n".implode(chr(9), (array(date("Y-m-d H:i:s")) + $arrMessage)), FILE_APPEND);
  }
  
  public function getEntityAndReset($strType){
    $arrMap = $this->arrEntityMap[$strType];
    $arrMap['status']['recordcount'] = $this->getRecordCount();
    $this->resetRecordCount();
    unset($this->arrEntityMap[$strType]);
    return array($strType => $arrMap);
  }
  
  function getEntityMap($strKey = '') {
    return !$strKey 
            ? $this->arrEntityMap 
            : (isset($this->arrEntityMap[$strKey]) ? $this->arrEntityMap[$strKey]: array());
  }
  
  public function microtimeFloat(){
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
  }
  
  public static function staticmicrotimeFloat(){
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
  }
  
  public function getDuration($intStartTime){
    return number_format(($this->microtimeFloat() - $intStartTime),4);
  }
  
  public function increaseRecordCount($intCount){
    $this->intRecordCount = $this->intRecordCount + $intCount;
  }
  
  public function resetRecordCount(){
    $this->intRecordCount = 0;
  }
  
  public function getRecordCount(){
    return $this->intRecordCount;
  }
  
  public function hasErrors(){
    return $this->bolHasErrors;
  }
    
}