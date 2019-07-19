<?php
/**
 * Import entity configurable product type model
 *
 * Copyright © 2013-2017 Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */

// @codingStandardsIgnoreFile

namespace saws\sawsconnector\Model\Import\Product\Type;

use Magento\Catalog\Api\Data\ProductInterface;
use Magento\CatalogImportExport\Model\Import\Product as ImportProduct;

/**
 * Importing configurable products
 * @package Magento\ConfigurableImportExport\Model\Import\Product\Type
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */

class Configurable extends \Magento\ConfigurableImportExport\Model\Import\Product\Type\Configurable {
    public function saveData() {
      $this->_entityModel->switchBehavior();
      parent::saveData();
      return $this;
    }

    protected function _deleteData() {
      $this->_entityModel->resetBehavior();
      if ($this->_entityModel->getBehavior() !== \Magento\ImportExport\Model\Import::BEHAVIOR_ADD_UPDATE) {
        return parent::_deleteData();
      }

      $quoted = $this->_connection->quoteInto('IN (?)', [$this->_productSuperData['product_id']]);
      $linkTable = $this->_resource->getTableName('catalog_product_super_link');
      $relationTable = $this->_resource->getTableName('catalog_product_relation');

      $this->_connection->delete($linkTable, "parent_id {$quoted}");
      $this->_connection->delete($relationTable, "parent_id {$quoted}");
      return $this;
    }

}