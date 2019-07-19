<?php

/**
 * Import entity of bundle product type
 *
 * Copyright © 2013-2017 Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace saws\sawsconnector\Model\Import\Product\Type;

use \Magento\Bundle\Model\Product\Price as BundlePrice;
use \Magento\BundleImportExport\Model\Export\RowCustomizer;
//use \Magento\Catalog\Model\Product\Type\AbstractType;

/**
 * Class Bundle
 * @package Magento\BundleImportExport\Model\Import\Product\Type
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 */
class Bundle extends \Magento\BundleImportExport\Model\Import\Product\Type\Bundle {

  protected function deleteOptionsAndSelections($productIds) {
    $productIds = array_unique($productIds);
    $optionTable = $this->_resource->getTableName('catalog_product_bundle_option');
    $productIdsInWhere = $this->connection->quoteInto('parent_id IN (?)', $productIds);
    $this->connection->delete(
        $optionTable,
        $productIdsInWhere
    );
    return $this;
  }


  public function saveData() {
    $this->_entityModel->resetBehavior();
    if ($this->_entityModel->getBehavior() === \Magento\ImportExport\Model\Import::BEHAVIOR_ADD_UPDATE) {
      $this->_entityModel->switchBehavior(\Magento\ImportExport\Model\Import::BEHAVIOR_DELETE);
      parent::saveData();
      $this->_entityModel->resetBehavior();
    }
    return parent::saveData();
  }
  
}