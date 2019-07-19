<?php


namespace saws\sawsconnector\Model\Import\Product\Type\Grouped;

use \Magento\Framework\App\ResourceConnection;

/**
 * Processing db operations for import entity of grouped product type
 */
class Links extends \Magento\GroupedImportExport\Model\Import\Product\Type\Grouped\Links {

  protected function deleteOldLinks($productIds) {
    if ($this->getBehavior() != \Magento\ImportExport\Model\Import::BEHAVIOR_APPEND && $this->getBehavior() != \Magento\ImportExport\Model\Import::BEHAVIOR_ADD_UPDATE) {
      parent::deleteOldLinks($productIds);
    }
  }

}