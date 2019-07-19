<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace saws\sawsconnector\Model\Import\SawsConnector;
use Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingError;
/**
 * Description of Products
 *
 * @author michael
 */
class Deletecategories extends Categories {

  function processImport($arrData, $options=array()) {
    if (!count($arrData))return;
    $arrData = array_values($arrData);
    $objResponse = $this->objResponse;

    $import = \Magento\Framework\App\ObjectManager::getInstance()->create('saws\sawsconnector\Model\Importer');
    $import->setEntityCode('catalog_category');
    $import->setBehavior(\Magento\ImportExport\Model\Import::BEHAVIOR_DELETE);
    $import->processImport($arrData);
    $import->setValidationStrategy(\Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorAggregator::VALIDATION_STRATEGY_SKIP_ERRORS);

    $objErrAggr = $import->createImportModel()->getErrorAggregator();
    $arrErrors = $objErrAggr->getAllErrors();
    if (count($arrErrors)) {
      foreach ((array)$arrErrors as $objError){
        $objResponse->addLogEntry('Import: '.$objError->getErrorMessage().' [ Row: '. $objError->getRowNumber().' ]','global', 'categories', 0, $objError->getErrorLevel() == ProcessingError::ERROR_LEVEL_CRITICAL);
      }
    }
    
  }

}
