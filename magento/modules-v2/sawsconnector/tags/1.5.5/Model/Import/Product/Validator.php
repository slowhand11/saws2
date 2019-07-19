<?php
/**
 * Copyright © 2013-2017 Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace saws\sawsconnector\Model\Import\Product;


class Validator extends \Magento\CatalogImportExport\Model\Import\Product\Validator {
  /**
   * @param string $attrCode
   * @param array $attrParams
   * @param array $rowData
   * @return bool
   * @SuppressWarnings(PHPMD.CyclomaticComplexity)
   * @SuppressWarnings(PHPMD.NPathComplexity)
   */
  public function isAttributeValid($attrCode, array $attrParams, array $rowData) {
      $this->_rowData = $rowData;
      if (trim($rowData[$attrCode])=='###EMPTY###')return true;
      return parent::isAttributeValid($attrCode, $attrParams, $rowData);
  }


}
