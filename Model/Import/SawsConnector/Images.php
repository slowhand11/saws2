<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace saws\sawsconnector\Model\Import\SawsConnector;

use Magento\Framework\App\Filesystem\DirectoryList;
use \Magento\Framework\Filesystem;

/**
 * Description of Products
 *
 * @author michael
 */
class Images extends AbstractImportType {
  const MEDIA_IMAGE_IMPORT_FOLDER = 'pub/media/import/';
  private $configHelper;
  private $filesystem;

  function __construct(
          \saws\sawsconnector\Helper\Config $configHelper,
          \Magento\Framework\Filesystem $filesystem) {

    parent::__construct();
    $this->configHelper = $configHelper;
    $this->filesystem = $filesystem;
  }

  protected function getImageDir() {
    $directory = $this->filesystem->getDirectoryWrite(DirectoryList::ROOT);
    
    $strPath = $this->configHelper->getImportFileDir();
    if (!$strPath) {
      return self::MEDIA_IMAGE_IMPORT_FOLDER;
    }
    $strReturn = $directory->getAbsolutePath($strPath);
    return $strReturn;
    
  }

  public function processImport($arrData, $options=array()) {
    if (!count($arrData))return;
    $objResponse = $this->objResponse;

    $intStartTime = $objResponse->microtimeFloat();
    foreach($arrData as $arrTypeImages){
      $objResponse->addLogEntry('Start download of '.count($arrTypeImages),'global', 'images', 0);
      $this->downloadImages($arrTypeImages);
    }
    $objResponse->addLogEntry('Finish images import of '.count($arrTypeImages),'global', 'images', $objResponse->getDuration($intStartTime));

//    $objResponse->addLogEntry('Images not supported.', 'global', 'categories', 0);
  }

  protected static function generateSplitPathForID($iID, $iCharNumber = 4) {
    $sIDPath = '';
    $sSuffix = '';
    $sTmpID = (string)$iID;
    while (strlen($sTmpID) >= $iCharNumber) {
      $sSuffix .= substr($sTmpID, 0, $iCharNumber);
      $sIDPath .= '/'.$sSuffix;
      $sTmpID = substr($sTmpID, $iCharNumber);
    }
    return substr(($sIDPath . "/" . $iID),1);
  }

  protected function downloadImages($arrImages){
    if (!count($arrImages))return;

    $objResponse = $this->objResponse;
    $strDir = $this->getImageDir();
    if (!is_dir($strDir)) {
      mkdir($strDir, 0755, true);
    }

    if (!is_dir($strDir)) throw new \Exception ('Import Folder not found');

    $objResponse->increaseRecordCount(count($arrImages));
    foreach($arrImages as $arrProdImages){
      $objResponse->addEntities($arrProdImages, 'images', array('csid', 'csclass', 'sku'), 'csid');

      foreach($arrProdImages as $arrImage){
        if (!$this->isFileModified($strDir.'/'.  self::generateSplitPathForID($arrImage['fileid']).'/'.$arrImage['filename'], $arrImage['mdate'])){
//          $objResponse->addLogEntry('Skip unchanged image '.$arrImage['filename'],$arrImage['csid'], 'images');
          continue;
        }

        if (!$this->downloadFileByURL($arrImage['url'], $strDir .'/'.  self::generateSplitPathForID($arrImage['fileid']).'/', $arrImage['filename'])){
          $objResponse->addLogEntry('Could not download '.$arrImage['filename'].' with URL:'.$arrImage['url'].' to folder '.self::MEDIA_IMAGE_IMPORT_FOLDER.'/'.  self::generateSplitPathForID($arrImage['fileid']).'/',$arrImage['csid'], 'images', -1);
          continue;
        }

        $objResponse->addLogEntry('Download '.$arrImage['filename'].' with URL:'.$arrImage['url'],$arrImage['csid'], 'images');
      }
    }

  }

  protected function downloadFileByURL($strUrl, $strTargetPath, $strFileName){
    if (!is_dir($strTargetPath)) mkdir($strTargetPath, 0755, true);
    if (!is_dir($strTargetPath)) return false;

    $ch = curl_init($strUrl);
    $fp = fopen($strTargetPath.$strFileName, 'wb');
    curl_setopt($ch, CURLOPT_FILE, $fp);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 240);

    //if ($this->strUsername) curl_setopt ($ch, CURLOPT_USERPWD, $this->strUsername.":".$this->strPassword); // Speichern Login-Daten in Optionen

    $result = curl_exec($ch);

    curl_close($ch);
    fclose($fp);
    clearstatcache();

    return file_exists($strTargetPath.$strFileName);
  }

  protected function isFileModified($strPath, $strLastModificationDate){
    if (!is_file($strPath)) return true;
    return (filemtime($strPath) < strtotime ($strLastModificationDate));
  }

}
