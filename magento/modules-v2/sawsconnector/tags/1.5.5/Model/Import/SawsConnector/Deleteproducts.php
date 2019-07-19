<?php

namespace saws\sawsconnector\Model\Import\SawsConnector;

/**
 * Description of Deleteproducts
 *
 * @author michael
 */
class Deleteproducts extends Products {
  
  function processImport($arrData, $options=array()) {
    $objResponse = $this->objResponse ;
    
    $intStartTime = $objResponse->microtimeFloat();
    $objResponse->addLogEntry('Start product deletion of '.count($arrData).' items','global', 'products', 0);
    
    $this->updateProducts($arrData, array('behavior' => \Magento\ImportExport\Model\Import::BEHAVIOR_DELETE));
    
    $objResponse->addLogEntry('Finish product deletion of '.count($arrData).' items','global', 'products', $objResponse->getDuration($intStartTime));

  }
  
}
