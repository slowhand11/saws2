<?php
/**
 * Import entity of grouped product type
 *
 * Copyright © 2013-2017 Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace saws\sawsconnector\Model\Import\Product\Type;


use Magento\CatalogImportExport\Model\Import\Product;
use Magento\ImportExport\Model\Import;

class Grouped extends \Magento\GroupedImportExport\Model\Import\Product\Type\Grouped {
  public function saveData() {
    $this->_entityModel->switchBehavior();
    parent::saveData();
  }

}