<?php
/**
 * Copyright © 2013-2017 Magento, Inc. All rights reserved.
 * See COPYING.txt for license details.
 */
namespace saws\sawsconnector\Model\Import\Product\Type;
use saws\sawsconnector\Model\Product;
/*
use Magento\Framework\App\ResourceConnection;
use Magento\CatalogImportExport\Model\Import\Product\RowValidatorInterface;
use Magento\CatalogImportExport\Model\Import\Product;
*/
/**
 * Import entity abstract product type model
 *
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
abstract class AbstractType extends \Magento\CatalogImportExport\Model\Import\Product\Type\AbstractType
{
        /**
     * Clear empty columns in the Row Data
     *
     * @param array $rowData
     * @return array
     */
    public function clearEmptyData(array $rowData) {
      foreach ($this->_getProductAttributes($rowData) as $attrCode => $attrParams) {
        if (!isset($rowData[$attrCode]))continue;
          if ($rowData[$attrCode] == '###EMPTY###'){
            $rowData[$attrCode] = NULL;
            continue;
          }

/*
          if (!$attrParams['is_static'] && $rowData[$attrCode] !== null && empty($rowData[$attrCode])) {
          //    unset($rowData[$attrCode]);
          }
*/          
      }
      return $rowData;
    }
    
}
