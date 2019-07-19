<?php

/**
 * Copyright © 2016 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */
// @codingStandardsIgnoreFile

namespace saws\sawsconnector\Model\Import;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\CatalogImportExport\Model\Import\Product\RowValidatorInterface as ValidatorInterface;
use Magento\Framework\Model\ResourceModel\Db\TransactionManagerInterface;
use Magento\Framework\Model\ResourceModel\Db\ObjectRelationProcessor;
use Magento\Framework\Stdlib\DateTime;
use Magento\ImportExport\Model\Import;
use Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingError;
use Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorAggregatorInterface;
use Magento\ImportExport\Model\Import\Entity\AbstractEntity;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\Config as CatalogConfig;

#use Magento\CatalogImportExport\Model\Import\Product\StoreResolver;
/**
 * Import entity product model
 * @SuppressWarnings(PHPMD.TooManyFields)
 * @SuppressWarnings(PHPMD.ExcessiveClassComplexity)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */

class Product extends \Magento\CatalogImportExport\Model\Import\Product {

  private $strRealBehavior = '';
  private $productEntityLinkField;

  private function getProductEntityLinkField() {
    if (!$this->productEntityLinkField) {
      $this->productEntityLinkField = $this->getMetadataPool()
              ->getMetadata(\Magento\Catalog\Api\Data\ProductInterface::class)
              ->getLinkField();
    }
    return $this->productEntityLinkField;
  }

  protected function getUrlKey($rowData) {
    if (empty($rowData[self::URL_KEY]) && array_key_exists(self::COL_NAME, $rowData) && $rowData[self::COL_NAME] === null) {
      return null;
    }

    return parent::getUrlKey($rowData);
  }

  private function _prepareProductLinks() {
    $resource = $this->_linkFactory->create();

    while ($bunch = $this->_dataSourceModel->getNextBunch()) {
      $arrEnitityIds = [];
      $arrLinkNames = $this->_linkNameToId;

      foreach ($bunch as $rowNum => $rowData) {
        if (!$this->isRowAllowedToImport($rowData, $rowNum)) {
          continue;
        }

        $sku = $rowData[self::COL_SKU];
        $productId = $this->skuProcessor->getNewSku($sku)[$this->getProductEntityLinkField()];
        if (!$productId) continue;
        $arrEnitityIds[] = $productId;

        foreach ($arrLinkNames as $linkName => $linkId) {
          if (!array_key_exists($linkName . 'sku', $rowData)) continue;
          unset($arrLinkNames[$linkName]);
        }

        $arrDiff = array_diff_key($this->_linkNameToId, $arrLinkNames);

        if (array_key_exists('associated_skus', $rowData) && !$rowData['associated_skus']) {
          $arrDiff['associated_skus'] = \Magento\GroupedProduct\Model\ResourceModel\Product\Link::LINK_TYPE_GROUPED;
        }

        if (!empty($arrDiff)) {
          $this->_connection->delete(
                  $resource->getMainTable(), $this->_connection->quoteInto('product_id IN (?) AND ', array_unique($arrEnitityIds)) .
                  $this->_connection->quoteInto('link_type_id IN (?) ', array_values($arrDiff))
          );
        }
      }
    }
    return $this;
  }

  protected function _saveProductAttributes(array $attributesData) {

    foreach ($attributesData as $tableName => $skuData) {
      $tableData = [];
      foreach ($skuData as $sku => $attributes) {
        $linkId = $this->_connection->fetchOne(
                $this->_connection->select()
                        ->from($this->getResource()->getTable('catalog_product_entity'))
                        ->where('sku = ?', $sku)
                        ->columns($this->getProductEntityLinkField())
        );

        foreach ($attributes as $attributeId => $storeValues) {
          foreach ($storeValues as $storeId => $storeValue) {

            if ($storeValue !== null || $storeId == 0) {
              if ($storeValue == ' ') $storeValue = '';
              $tableData[] = [
                  $this->getProductEntityLinkField() => $linkId,
                  'attribute_id' => $attributeId,
                  'store_id' => $storeId,
                  'value' => $storeValue,
              ];
            }
            else {
              $connection = $this->_connection;
              $affectedRows = $connection->delete($tableName, array(
                  $this->getProductEntityLinkField() . '=?' => $linkId,
                  'attribute_id=?' => $attributeId,
                  'store_id=?' => $storeId,
              ));
            }
          }
        }
      }
      $this->_connection->insertOnDuplicate($tableName, $tableData, ['value']);
    }

    return $this;
  }

  protected function getExistingImages1($bunch) {
    return $this->mediaProcessor->getExistingImages($bunch);
  }

  protected function _saveMediaGallery(array $mediaGalleryData) {
    if (empty($mediaGalleryData)) {
      return $this;
    }


    $cb = function ($data) use (&$cb) {
      if (!is_array($data)) return false;
      if (array_key_exists('value', $data)) {
        return (trim($data['value']) && $data['value'] != '###EMPTY###') ? $data : false;
      }

      $data = array_map($cb, $data);
      return array_filter($data);
    };

    if (\Magento\ImportExport\Model\Import::BEHAVIOR_APPEND != $this->getBehavior()) {
      $this->initMediaGalleryResources();
      $arrSKUS = [];
      foreach ($mediaGalleryData as $arrStoreData) {
        $arrSKUS = array_merge($arrSKUS, array_keys((array)$arrStoreData));
      }
      $arrSKUS = array_unique($arrSKUS);
      $productIds = [];
      foreach ($arrSKUS as $productSku) {
        $productId = $this->skuProcessor->getNewSku($productSku)[$this->getProductEntityLinkField()];
        $productIds[] = $productId;
        $this->_connection->delete(
                $this->mediaGalleryEntityToValueTableName,
                $this->_connection->quoteInto($this->getProductEntityLinkField().' IN (?)', $productId)
        );
        $this->_connection->delete(
                $this->mediaGalleryValueTableName,
                $this->_connection->quoteInto($this->getProductEntityLinkField().' IN (?)', $productId)
        );

      }
    }

    $mediaGalleryData = $cb($mediaGalleryData);
    $this->switchBehavior(\Magento\ImportExport\Model\Import::BEHAVIOR_APPEND);
    parent::_saveMediaGallery($mediaGalleryData);

    $this->resetBehavior();

    $objectManager = \Magento\Framework\App\ObjectManager::getInstance();
    $objProductRepository = $objectManager->get('\Magento\Catalog\Api\ProductRepositoryInterface');
    $objImageFacory = $objectManager->get('\Magento\Catalog\Model\Product\Image\CacheFactory');

    foreach ($productIds as $intProduct) {
      try {
        /** @var Product $product */
        $product = $objProductRepository->getById($intProduct);
      }
      catch (\Magento\Framework\Exception\NoSuchEntityException $e) {
        continue;
      }
      $imageCache = $objImageFacory->create();
      $imageCache->generate($product);
    }

    return $this;
  }

  public function getBehavior() {
    if (isset($this->_parameters['behavior']) && $this->_parameters['behavior'] == \Magento\ImportExport\Model\Import::BEHAVIOR_ADD_UPDATE) {
      return $this->_parameters['behavior'];
    }

    return parent::getBehavior();
  }

  protected function _saveProductCategories(array $categoriesData) {
    if ($this->getBehavior() == \Magento\ImportExport\Model\Import::BEHAVIOR_ADD_UPDATE) {
      $funcRemoveEmpty = function ($varData) use (&$funcRemoveEmpty) {
        if (is_array($varData)) {
          return array_filter($varData, $funcRemoveEmpty);
        }
        return empty($varData);

      };

      $arrData = $funcRemoveEmpty($categoriesData);
      if (empty($arrData)) {
        return $this;
      }
      $categoriesData = $arrData;
    }

    return parent::_saveProductCategories($categoriesData);
  }

   protected function uploadMediaFiles($fileName, $renameFileOff = false) {
     if ($fileName === '###EMPTY###') return $fileName;
     return parent::uploadMediaFiles($fileName);
  }

  protected function _saveProducts() {
    parent::_saveProducts();
    $this->_prepareProductLinks();
    return $this;

    $this->switchBehavior();
  }

  protected function _saveLinks() {
    $this->switchBehavior();
    return parent::_saveLinks();
  }

  function switchBehavior($strBehavior = \Magento\ImportExport\Model\Import::BEHAVIOR_APPEND) {
    if (!$this->strRealBehavior) {
      $this->strRealBehavior = $this->getBehavior();
    }

    if ($this->strRealBehavior === \Magento\ImportExport\Model\Import::BEHAVIOR_ADD_UPDATE) {
      $this->_parameters['behavior'] = $strBehavior;
    }
  }

  function resetBehavior() {
    if ($this->strRealBehavior) {
      $this->_parameters['behavior'] = $this->strRealBehavior;
    }
  }

}
