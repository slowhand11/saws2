<?php
namespace saws\sawsconnector\Model\Import\SawsConnector;


class Categories extends AbstractImportType {
  private $arrAffectedStores = array();

  function processImport($arrData, $options=array()) {
    if (!count($arrData))return;
    $objResponse = $this->objResponse;

    $objResponse->addLogEntry('Start category import of '.count($arrData),'global', 'categories', 0);
    $intStartTime = $objResponse->microtimeFloat();

    $this->updateCategories($arrData);

    $objResponse->addLogEntry('Category import done of '.count($arrData) ,'global', 'categories', $objResponse->getDuration($intStartTime));
  }

  private function sort($arrData) {
    $objResponse = $this->objResponse;
    if (!is_numeric(key($arrData))) {
      ksort($arrData, SORT_STRING|SORT_NATURAL);
      $arrCategories = array();
      while(($arrShifted = array_shift($arrData)) !== null) {
        $objResponse->addEntities($arrShifted, 'categories', $this->getResponseFields(), '_category');

        $arrCategories = array_merge(
                $arrCategories,
                array_map(array($this, 'prepareCategoryValuesForImport'), $arrShifted));

      }
      $arrData = $arrCategories;
    }
    return $arrData;
  }

  private function splitForUpdateAndInsert($arrCategories) {
    $arrUpdate = $arrInsert = array();
    $arrLast = null;
    foreach ($arrCategories as $intKey => $arrCategory) {
      if (isset($arrCategory['_store'])) {
        $this->arrAffectedStores[] = $arrCategory['_store'];
        if ($arrCategory['_store'] != 'default') {
          $this->unsetMediaFields($arrCategory);
          $arrLast[] = $arrCategory;
          continue;
        }
      }

      if (isset($arrCategory['_store'])) unset($arrCategory['_store']);
      if (isset($arrCategory['entity_id']) && $arrCategory['entity_id']>0) {

        $this->unsetMediaFields($arrCategory);
        $arrUpdate[] = $arrCategory;
        $arrLast = &$arrUpdate;
      } else {
        $this->unsetCategoryFields($arrCategory);
        $arrInsert[] = $arrCategory;
        $arrLast = &$arrInsert;
      }
    }
    return array('insert' => $arrInsert, 'update' => $arrUpdate);

  }

  private function unsetMediaFields(&$arrData) {
    unset($arrData['_media_image']);
    unset($arrData['_media_is_disabled']);
    unset($arrData['_media_lable']);
    unset($arrData['_media_position']);
    unset($arrData['small_image']);
    unset($arrData['PdmarticleID']);
  }

  protected function getResponseFields() {
    return array('entity_id','path','level','position', 'sku','_root','_category', 'csid');
  }

  protected function updateCategories($arrCategories, $bDelete = false){
    if (!count($arrCategories)) return true;
    $objResponse = $this->objResponse;
    $arrCategories = $this->sort($arrCategories);

    $arrInsertAndUpdate = $this->splitForUpdateAndInsert($arrCategories);
  //file_put_contents('/tmp/mv.log', "\n IANDU: " .print_r($arrInsertAndUpdate,1), FILE_APPEND);
    $objResponse->increaseRecordCount(count($arrCategories));

    $bError = false;
    try {
      if (count($arrInsertAndUpdate['insert'])) {
        $import = \Magento\Framework\App\ObjectManager::getInstance()->create('saws\sawsconnector\Model\Importer');
        $import->setEntityCode('catalog_category');
        $import->processImport($arrInsertAndUpdate['insert']);
        $objErrAggr = $import->createImportModel()->getErrorAggregator();
        $rowMessages = $objErrAggr->getRowsGroupedByErrorCode([], []);
        if (count($rowMessages)) {
          $bError = true;
          foreach ((array)$rowMessages as $strMessage => $arrMessage){
            $objResponse->addLogEntry('Import: '.$strMessage.' [ '. implode(', ',$arrMessage).' ]','global', 'categories', 0, true);
          }
        }
      }

      if (count($arrInsertAndUpdate['update'])) {

        $arrInsertAndUpdate['update'] = \Magento\Framework\App\ObjectManager::getInstance()->create('saws\sawsconnector\Model\Import\Category')->prepareCategoriesForUpdate($arrInsertAndUpdate['update']);
        $arrInsertAndUpdate['update'] = array_map(array($this, 'unsetCategoryFields'), $arrInsertAndUpdate['update']);

        $import = \Magento\Framework\App\ObjectManager::getInstance()->create('saws\sawsconnector\Model\Importer');
        $import->setEntityCode('catalog_category');
        $import->processImport($arrInsertAndUpdate['update']);
        $objErrAggr = $import->createImportModel()->getErrorAggregator();
        $rowMessages = $objErrAggr->getRowsGroupedByErrorCode([], []);
        if (count($rowMessages)) {
          $bError = true;
          foreach ((array)$rowMessages as $strMessage => $arrMessage){
            $objResponse->addLogEntry('Import: '.$strMessage.' [ '. implode(', ',$arrMessage).' ]','global', 'categories', 0, true);
          }
        }
      }

      if (!$bError) {
        $this->fillEntitiesForCategories();
        $this->createUrlRewrites();
      }

    } catch (\Exception $e) {

      $objResponse->addLogEntry('Errors occured during import:'.$e->getMessage(),'global', 'categories', -1,  true);
      $objResponse->addLogEntry($e->getMessage(), 'global', 'categories', 0, true);

      foreach ((array)$import->getErrorMessages() as $strKey => $arrErrVal) {
        if (!is_array($arrErrVal)) continue;
        foreach($arrErrVal as $intCatKey){
          $objResponse->addLogEntry('Import:'.$strKey, $arrCategories[($intCatKey-1)]['_category'], 'categories', 0, true);
        }
      }
    }

    return;
  }


  protected function prepareCategoryValuesForImport($arrData) {
    //$this->unsetCategoryFields($arrData);
    $this->transformCategoryValues($arrData);
    return $arrData;
  }

  protected function transformCategoryValues(&$arrData){
    if(isset($arrData['is_active']))$arrData['is_active'] = ($arrData['is_active'] == 'yes' ? '1' : '0');
    if(isset($arrData['include_in_menu']))$arrData['include_in_menu'] = ($arrData['include_in_menu'] == 'yes' ? '1' : '0');
    if(isset($arrData['is_anchor']))$arrData['is_anchor'] = ($arrData['is_anchor'] == 'no' ? '0' : '1');

    $arrData['available_sort_by'] = (isset($arrData['available_sort_by']))
            ? str_replace(array('use_config','position','name','price'),array('###EMPTY###','position','name','price'),$arrData['available_sort_by'])
            : '###EMPTY###';

    $arrData['default_sort_by'] = (isset($arrData['default_sort_by']))
            ? str_replace(array('use_config','position','name','price'),array('###EMPTY###','position','name','price'),$arrData['default_sort_by'])
            : '###EMPTY###';

    $arrData['display_mode'] = (isset($arrData['display_mode']))
            ? str_replace(array('products only','static block only','static block and products'),array('PRODUCTS','PAGE','PRODUCTS_AND_PAGE'),strtolower($arrData['display_mode']))
            : 'PRODUCTS';

    if(isset($arrData['_media_is_disabled'])) unset($arrData['_media_is_disabled']);
    if(isset($arrData['_media_image'])) unset($arrData['_media_image']);
    if(isset($arrData['_media_position'])) unset($arrData['_media_position']);
    if(isset($arrData['small_image'])) unset($arrData['small_image']);

    //??? evtl muss für Kategorien was anderes verwendet werden
    if (isset($arrData['url_key']) && $arrData['url_key']!='###EMPTY###') {
      $arrData['url_key'] = \Magento\Framework\App\ObjectManager::getInstance()
              ->get('\Magento\Catalog\Model\Product\Url')->formatUrlKey($arrData['url_key']);
    }

  }

  protected function unsetCategoryFields(&$arrData){
    unset($arrData['entity_id']);
    unset($arrData['label']);
    unset($arrData['csid']);
    unset($arrData['csclass']);
    unset($arrData['SortOrder']);
    unset($arrData['ParentID']);
    unset($arrData['PdmarticleID']);
    return $arrData;
  }

  public function createUrlRewrites() {
    $storesList = $this->getAllStoreIds();

    $arrEntityMap =  $this->objResponse->getEntityMap('categories');
    $arrEntityIds = array_values(array_filter(array_map(function ($e) {
      return $e['entity_id'];
    }, $arrEntityMap )));

    if (count($storesList) > 0 && !empty($arrEntityIds)) {
      $this->removeAllUrlRewrites($storesList, $arrEntityIds);
    }

    foreach ($storesList as $storeId => $storeCode) {
      $cf = \Magento\Framework\App\ObjectManager::getInstance()->get(\Magento\Catalog\Model\ResourceModel\Category\CollectionFactory::class);
      $categories = $cf->create()
          ->addAttributeToSelect('*')
          ->setStore($storeId)
          ->addFieldToFilter('level', array('gt' => '1'))
          ->setOrder('level', 'DESC');

      foreach ($categories as $category) {
        try {
          if (!in_array($category->getId(), $arrEntityIds))continue;
          $category->setStoreId($storeId);
          $category->setOrigData('url_key', '');
          $category->save();
        } catch (\Exception $e) {}
      }
    }

  }

  private function removeAllUrlRewrites($storesList, $arrEntityIds) {
    $resource = \Magento\Framework\App\ObjectManager::getInstance()->get(\Magento\Framework\App\ResourceConnection::class);
    $storeIds = implode(',', array_keys($storesList));
    $entityIds = implode(',', $arrEntityIds);

    $sql = "DELETE FROM {$resource->getTableName('url_rewrite')} WHERE `entity_type`='category' "
      . "AND `store_id` IN ({$storeIds}) AND `entity_id` IN ({$entityIds});";
    $resource->getConnection()->query($sql);

    $sql = "DELETE FROM {$resource->getTableName('catalog_url_rewrite_product_category')} WHERE `url_rewrite_id` NOT IN (
        SELECT `url_rewrite_id` FROM {$resource->getTableName('url_rewrite')}
    );";

    $resource->getConnection()->query($sql);
  }

  private function getAllStoreIds() {
    $resource = \Magento\Framework\App\ObjectManager::getInstance()->get(\Magento\Framework\App\ResourceConnection::class);
    $result = [];

    $sql = $resource->getConnection()->select()
        ->from($resource->getTableName('store'), array('store_id', 'code'))
        ->order('store_id', 'ASC');

    $queryResult = $resource->getConnection()->fetchAll($sql);
    foreach ($queryResult as $row) {
      if (!in_array($row['code'], $this->arrAffectedStores)) continue;
      $result[(int)$row['store_id']] = $row['code'];
    }

    return $result;
  }

  public function fillEntitiesForCategories(){
    $objResponse = $this->objResponse;
    $arrEntityMap = $objResponse->getEntityMap('categories');
    //if (!$this->arrEntityMap['categories']) return;
    $arrCats = \Magento\Framework\App\ObjectManager::getInstance()->create('saws\sawsconnector\Model\Import\Category')
            ->getCategoriesWithRoots(true);

    foreach ($arrEntityMap as $strKey => $arrSingleCat){
      if (!isset($arrSingleCat['_root']))continue;
      if (!isset($arrCats[$arrSingleCat['_root']][$strKey]))continue;
      $arrEntityMap[$strKey] = array_merge($arrEntityMap[$strKey], $arrCats[$arrSingleCat['_root']][$strKey]);
    }

    $objResponse->addEntities($arrEntityMap, 'categories', $this->getResponseFields(), false);
  }
}
