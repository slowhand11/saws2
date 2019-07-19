<?php

namespace saws\sawsconnector\Model\Import\SawsConnector;

/**
 * Description of Products
 *
 * @author michael
 */
class Products extends AbstractImportType {
  private $arrEntityMapping = array();

  function processImport($arrData, $options=array()) {
    $objResponse = $this->objResponse ;
    $objResponse->addLogEntry('Start product import of '.count($arrData),'global', 'products', 0);

    $intStartTime = $objResponse->microtimeFloat();
    $arrData = $this->prepareProductsData($arrData);
    // $arrData['products'][0][0] is the first single product array
    for($i=0;$i<count($arrData);$i++) {
      foreach($arrData[$i] as $key => $value) {
        $arrData[$i][$key] = $this->implodeStandardValueArrays( $this->remapProductKeys($value) );
      }
    }

    $intProductCount = array_sum(array_map(function($a){return count($a);}, $arrData));
    $arrImportProducts = $arrData;

    $objResponse->addLogEntry('Start product import of ' .$intProductCount. ' and split up into '.count($arrImportProducts).' packages','global', 'products', 0);
    foreach($arrImportProducts as $intKey => $arrProduct){
      $objResponse->addLogEntry('Add product package '.($intKey+1).' of '.count($arrImportProducts),'global', 'products', -1);
      $this->updateProducts($arrProduct, $options);
    }

    $objResponse->addLogEntry('Finish product import of '.$intProductCount ,'global', 'products', $objResponse->getDuration($intStartTime));
  }

  function _addMappingPart($strType = 'products', $strKey = '', $strRoot = '', $intCSId = '', $strClass = ''){
    if (!isset($this->arrEntityMapping[$strType][$strKey])) {
      $this->arrEntityMapping[$strType][$strKey] = array('root' => $strRoot, 'csid' => $intCSId, 'csclass' => $strClass);
    }
  }

  function _addLogInfo($strType = 'products', $strKey = '', $strInfo = '', $bolIsError = false){
    $this->arrEntityMapping[$strType][$strKey]['log'][] = $strInfo;
    if ($bolIsError) $this->arrEntityMapping[$strType][$strKey]['haserrors'] = true;
  }

  function unsetProductFields(&$arrData){
    unset($arrData['entity_id']);
    unset($arrData['csid']);
    unset($arrData['csclass']);
    unset($arrData['SortOrder']);
    unset($arrData['ParentID']);
    unset($arrData['PdmarticleID']);
    unset($arrData['parentid']);
    unset($arrData['stateid']);
    unset($arrData['sortorder']);
  }

  protected function getEntityCode() {
   return 'catalog_product';
  }

  protected function updateProducts($arrProducts, $options=array()){
    $objResponse = $this->objResponse;

    if (!is_array($arrProducts) || count($arrProducts) == 0) {
      $objResponse->addLogEntry('No products given - nothing to do','global', 'products');
      return;
    }

    $bolSecondRun   = isset($options['secondrun']) ? $options['secondrun']: false;

    $behavior = \Magento\ImportExport\Model\Import::BEHAVIOR_ADD_UPDATE;
    if (isset($options['behavior'])) {
      $behavior = $options['behavior'];
    }
    unset($options['secondrun']);

    $objResponse->addEntities($arrProducts, 'products', array('csid', 'csclass', 'name', 'sku'), 'sku');

    if (!$bolSecondRun) {
      $objResponse->addLogEntry('running for the 1st time with '.count($arrProducts).' products','global', 'products', -1);
      $objResponse->increaseRecordCount(count($arrProducts));

      foreach ($arrProducts as $intKey => $arrSingle) {
        if (isset($arrSingle['sku'])) {
          $this->_addMappingPart('products', $arrSingle['sku'], '');
          if(isset($arrSingle['csid']) && isset($arrSingle['csclass'])) $this->_addMappingPart($arrSingle['csid'], $arrSingle['csclass']);
        }
        $this->unsetProductFields($arrProducts[$intKey]);
      }
    }
    else {
      $objResponse->addLogEntry('Run product '.(($behavior == \Magento\ImportExport\Model\Import::BEHAVIOR_DELETE) ? 'deletion' : 'import').' '.(($bolSecondRun) ? 'second' : 'first').' time','global', 'products', -1);
    }

    $import = \Magento\Framework\App\ObjectManager::getInstance()->create('saws\sawsconnector\Model\Importer');
    if(!$import) {
      $objResponse->addLogEntry('Failed to instanciate magento importer!','global', 'products', -1, true);
      return;
    }

    $import->setEntityCode($this->getEntityCode());
    $import->setBehavior($behavior);
    $import->setMultipleValueSeparator($this->getMultipleValueSeparator());

    $import->setValidationStrategy(\Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorAggregator::VALIDATION_STRATEGY_SKIP_ERRORS);
    $import->processImport($arrProducts);

    $intProdCount = count($arrProducts);
    $intErrCount = 0;
    $objErrAggr = $import->createImportModel()->getErrorAggregator();

    $rowMessages = $objErrAggr->getRowsGroupedByErrorCode([], []);
    $arrProductsSecond = array();
    if ($rowMessages) $arrProductsSecond = $arrProducts;
    $arrFailedProdBySku = [];

    foreach ($rowMessages as $errorCode => $rows) {
      foreach($rows as $row) {
        $error = $objErrAggr->getErrorByRowNumber($row-1);
        if (empty($error) && $row==1) $error = $objErrAggr->getErrorByRowNumber(null);

        if(isset($arrProductsSecond[$row -1])) {
          if(!in_array($arrProducts[$row - 1]['sku'],array_keys($arrFailedProdBySku))) {
            $arrFailedProdBySku[$arrProducts[$row - 1]['sku']] = [];
            ++$intErrCount;
          }
          if(!in_array($errorCode, $arrFailedProdBySku[$arrProducts[$row - 1]['sku']])) {
            $arrFailedProdBySku[$arrProducts[$row - 1]['sku']][] = $errorCode .
                    (!empty($error) ? ' ('.$error[0]->getErrorDescription().')':'');
          }
          unset($arrProductsSecond[$row -1]);
          $svi = 1; // svi store view index
          while(isset($arrProductsSecond[$row - 1 + $svi]['sku']) && $arrProductsSecond[$row - 1 + $svi]['sku'] === $arrProducts[$row - 1]['sku']) {
            unset($arrProductsSecond[$row - 1 + $svi]);
            ++$svi;
            ++$intErrCount;
          }
        }
      }
    }

    foreach($arrFailedProdBySku as $fsku => $arrErrCodes) {
      if($behavior == "delete")continue;
      $objResponse->addLogEntry('Product with sku ' . $fsku . ' could not be imported: '.
                    implode(",", $arrErrCodes),
                    $fsku,
                    'products', -1, true, '');
    }

    $intSuccessCount = $intProdCount - $intErrCount;

    if (!$bolSecondRun && count($arrProductsSecond)) {
      $options['secondrun'] = true;
      return $this->updateProducts(array_values($arrProductsSecond), $options);
    }

    $objResponse->addLogEntry($intSuccessCount . ' products successfully  '.(($behavior == \Magento\ImportExport\Model\Import::BEHAVIOR_DELETE) ? ' deleted' : 'inserted or updated').' ','global'/*$arrProd['sku']*/, 'products',0);
  }



// Map some attribute names from CS to the corresponding Magento2 keys
  // in : single product array
  // out: single product array (with new keys)
  public function remapProductKeys(array $arrSingleProduct) {
    $arrCS2Mag = array(
        '_attribute_set' => 'attribute_set_code',
        '_type' => 'product_type',
        '_product_websites' => 'product_websites',
        '_links_related_sku' => 'related_skus',
        '_links_related_position' => 'related_position',
        '_links_crosssell_sku' => 'crosssell_skus',
        '_links_crosssell_position' => 'crosssell_position',
        '_links_upsell_sku' => 'upsell_skus',
        '_links_upsell_position' => 'upsell_position',
        'hersteller' => 'manufacturer',
        '_store' => 'store_view_code',
        'image' => 'base_image',
        'thumbnail' => 'thumbnail_image',

        '_associated_sku' => 'associated_skus'
//        '_associated_default_qty' => 'associated_default_qty',
//        '_associated_position' => 'associated_position',
    );

    $arrCSKeys = array_keys($arrCS2Mag);

    foreach($arrSingleProduct as $key => $value) {
      if(in_array($key, $arrCSKeys, false)) {
        $strNewKey = $arrCS2Mag[$key];
        $arrSingleProduct[$strNewKey] = $value;

        unset($arrSingleProduct[$key]);
      }
    }
    return $arrSingleProduct;
  }

  public function implodeStandardValueArrays($arrSingleProduct = array()) {
    //return $arrSingleProduct;

    $arrStdKeys = array(
        'sku', 'store_view_code',
        'attribute_set_code', 'product_type',
        'categories', 'product_websites',
        'name', 'description',
        'short_description', 'weight',
        'product_online', 'tax_class_name',
        'visibility', 'price',
        'special_price', 'special_price_from_date',
        'special_price_to_date', 'url_key',
        'meta_title', 'meta_keywords',
        'meta_description', 'base_image',
        'base_image_label', 'small_image',
        'small_image_label', 'thumbnail_image',
        'thumbnail_image_label', 'swatch_image',
        'swatch_image_label', 'created_at',
        'updated_at', 'new_from_date',
        'new_to_date', 'display_product_options_in',
        'map_price', 'msrp_price',
        'map_enabled', 'gift_message_available',
        'custom_design', 'custom_design_from',
        'custom_design_to', 'custom_layout_update',
        'page_layout', 'product_options_container',
        'msrp_display_actual_price_type', 'country_of_manufacture',
        'additional_attributes', 'qty',
        'out_of_stock_qty', 'use_config_min_qty',
        'is_qty_decimal', 'allow_backorders',
        'use_config_backorders', 'min_cart_qty',
        'use_config_min_sale_qty', 'max_cart_qty',
        'use_config_max_sale_qty', 'is_in_stock',
        'notify_on_stock_below', 'use_config_notify_stock_qty',
        'manage_stock', 'use_config_manage_stock',
        'use_config_qty_increments', 'qty_increments',
        'use_config_enable_qty_inc', 'enable_qty_increments',
        'is_decimal_divided', 'website_id',
        'related_skus', 'crosssell_skus','related_position', 'upsell_position',
        'crosssell_position', 'crosssell_skus',
        'upsell_skus', 'additional_images',
        'additional_image_labels', 'hide_from_product_page',
        'bundle_price_type', 'bundle_sku_type',
        'bundle_price_view', 'bundle_weight_type',
        'bundle_values','_media_image','_media_is_disabled'
/*
        'associated_skus',
        '_associated_sku',
        'associated_default_qty', '_associated_default_qty',
        '_associated_position', 'associated_position',
*/
    );

    $arrPositionKeys = ['related_position', 'upsell_position', 'crosssell_position'];

    foreach($arrSingleProduct as $key => $value) {
      $delimiter = in_array($key, $arrStdKeys) ? ',' : '|';
      if (is_array($value)) {
        if ($key == '_media_image') {
          $value = array_filter($value);
          if (empty($value)) {
            $value = ['###EMPTY###'];
          }
        }



/*
        if (in_array($key, $arrPositionKeys)) {
          $value = array_map(function ($v){return (int)$v+1;}, $value);
        }
*/
        $arrSingleProduct[$key] = (false == empty($value))
                  ? implode($delimiter, $value)
                  : '';
      }

    }
    return $arrSingleProduct;
  }

  private function prepareVisibility(&$arrProd) {
    $arrVisibilityStates = \Magento\Catalog\Model\Product\Visibility::getOptionArray();
      //remap visibility codes
    if (!isset($arrProd['visibility'])) {
      return;
      $arrProd['visibility'] = null;// '###EMPTY###';
    }

    if ($arrProd['visibility'] === '###EMPTY###') {
      $arrProd['visibility'] = null;// '###EMPTY###';
    }

    if ((!isset($arrProd['_store']) || $arrProd['_store'] === 'default') && $arrProd['visibility'] === null) {
      $arrProd['visibility'] = \Magento\Catalog\Model\Product\Visibility::VISIBILITY_BOTH;
    }

    if (is_numeric($arrProd['visibility']) && array_key_exists($arrProd['visibility'], $arrVisibilityStates)) {
        $arrProd['visibility'] = $arrVisibilityStates[$arrProd['visibility']];
    }
  }

  private function prepareTypeConfigurable(&$arrProd) {
    if($arrProd['_type'] !== 'configurable') return;
    if (isset($arrProd['configurable_variations']) && is_string($arrProd['configurable_variations'])) return;

    $arrProd['configurable_variations'] = '';
    for($si=0; $si<count($arrProd['_super_products_sku']); $si++) {
      //$arrProd['configurable_variations'] .= ($arrProd[$i]['configurable_variations'] !== '') ? '|' : '';
      $arrProd['configurable_variations'] .= '|sku='.$arrProd['_super_products_sku'][$si].','.$arrProd['_super_attribute_code'][$si].'='.$arrProd['_super_attribute_option'][$si];
    }
    $arrProd['configurable_variations'] = ltrim($arrProd['configurable_variations'], '|');

    foreach([ '_super_products_sku', '_super_attribute_code', '_super_attribute_option', '_super_attribute_price_corr'] as $key) {
      unset($arrProd[$key]);
    }

  }

  private function prepareTypeBundle(&$arrProd) {
    if($arrProd['_type'] !== 'bundle') return;

    $strBundle = '';
    $arrBundleOption = [];
    if(isset($arrProd['bundle_type'])) $arrBundleOption = array('type'=> $arrProd['bundle_type']);
    if(isset($arrProd['bundle_required'])) $arrBundleOption = array_merge($arrBundleOption,array('required'=> $arrProd['bundle_required']));
    if(isset($arrProd['bundle_content'])){
      if (is_array($arrProd['bundle_content'])) {
        foreach((array)$arrProd['bundle_content'] as $arrSingleBundle){
          $arrMerge = array_merge($arrBundleOption,$arrSingleBundle);
          $strBundle .= $this->arrayToAttributeString($this->remapBundleKeys($arrMerge)).'|';
        }
      }
      elseif (is_string($arrProd['bundle_content'])) {
        $strBundle = $arrProd['bundle_content'];
      }

      $strBundle = preg_replace('/required=(yes|ja)/i', 'required=1', $strBundle);
      $strBundle = preg_replace('/required=(no|nein)/i', 'required=0', $strBundle);

      $arrProd['bundle_values'] = $strBundle;
      unset($arrProd['bundle_content']);
    }

  }

  private function prepareTypeGrouped(&$arrProd) {
    if($arrProd['_type'] !== 'grouped') return;
    if(false === isset($arrProd['_associated_sku'])) {
      $arrProd['_associated_sku'] = array();
    }

    if (!is_array($arrProd['_associated_sku'])) {
      $arrProd['_associated_sku'] = mb_strtolower($arrProd['_associated_sku']);
      if ($arrProd['_associated_sku'] === '' || $arrProd['_associated_sku'] === '###EMPTY###') {
        $arrProd['_associated_sku'] = null;
      }
      return;
    }

    $strAssProd = '';
    for($ai=0;$ai<count($arrProd['_associated_sku']);$ai++) {
      $strAssProd .= ($strAssProd !== '') ? ', ' : '';
      $strAssProd .= $arrProd['_associated_sku'][$ai].'=1';
    }
    $arrProd['_associated_sku'] = mb_strtolower($strAssProd);

  }

  private function prepareUrlKey($key, $default=null) {
    if ($key === '###EMPTY###') return $default;

    return \Magento\Framework\App\ObjectManager::getInstance()
                ->get('\Magento\Catalog\Model\Product\Url')->formatUrlKey($key);
  }

  protected function getStoreCodeToWebsiteId($store=''){
    static $arrStoreCodeToWebsiteId=[];
    if(!$arrStoreCodeToWebsiteId){
      $objStoreMgr = \Magento\Framework\App\ObjectManager::getInstance()->get('\Magento\Store\Model\StoreManagerInterface');
      $arrStores=$objStoreMgr->getStores();
      foreach ((array)$arrStores as $objStore){
        $arrStoreCodeToWebsiteId[$objStore->getCode()] = $objStore->getWebsiteId();
      }
    }
    return !$store ? $arrStoreCodeToWebsiteId : $arrStoreCodeToWebsiteId[$store];
  }

  protected function getStoreCodeToWebsiteCode($store=''){
    if($store == 'default')return 'base';
    static $arrStoreCodeToWebsiteCode = [];
    if(!$arrStoreCodeToWebsiteCode){
      $objStoreMgr = \Magento\Framework\App\ObjectManager::getInstance()->get('\Magento\Store\Model\StoreManagerInterface');
      $arrStores=$objStoreMgr->getStores();
      foreach ((array)$arrStores as $objStore){
        $arrStoreCodeToWebsiteCode[$objStore->getCode()] = $objStoreMgr->getWebsite($objStore->getWebsiteId())->getCode();
      }
    }
    return !$store ? $arrStoreCodeToWebsiteCode : (isset($arrStoreCodeToWebsiteCode[$store])?$arrStoreCodeToWebsiteCode[$store]:false);
  }


  private function prepareTierPrice(&$arrStoreData, $arrDefault) {
    $arrTier = array();
    $arrTierImport = array();
    $arrProductWebsites = $arrDefault['_product_websites'];

    if (!isset($arrStoreData['tier_price']) && !isset($arrStoreData['group_price'])) return []; 

      if (isset($arrStoreData['tier_price'])){
        $arrTierImport = json_decode($arrStoreData['tier_price'], true);
        unset($arrStoreData['tier_price']);
      }
            
      if (isset($arrStoreData['group_price'])) {
          $arrGroupPrice = json_decode($arrStoreData['group_price'], true);
          unset($arrStoreData['group_price']);
              
        if(is_array($arrGroupPrice)){
          foreach((array)$arrGroupPrice as $arrSingleGroup){
            $arrTierImport[]=[
                '_tier_price_website' => $arrSingleGroup['_group_price_website'],
                '_tier_price_customer_group' => $arrSingleGroup['_group_price_customer_group'],
                '_tier_price_qty'            => '1',
                '_tier_price_price'          => $arrSingleGroup['_group_price_price'],
            ];
          }
        }
      }
          
      foreach((array)$arrTierImport as $arrSubRowData) {
      $arrTierPrice = $arrDefault;
      if ($arrSubRowData['_tier_price_website'] == '###REMOVE###' || $arrSubRowData['_tier_price_website'] == '###EMPTY###') {
          $arrTierPrice['_tier_price_website'] = null;
          $arrTier[] = json_encode($arrTierPrice);
          continue;
        }
      
        if (empty($arrSubRowData['_tier_price_price'])) continue;
        $arrTierPrice['_tier_price_customer_group'] = $arrSubRowData['_tier_price_customer_group'];
        $arrTierPrice['_tier_price_qty'] = $arrSubRowData['_tier_price_qty'];
        $arrTierPrice['_tier_price_price'] = $arrSubRowData['_tier_price_price'];
        $arrTierPrice['_tier_price_website'] = 'all';
        if($arrSubRowData['_tier_price_website'] != 'all'){
          $arrTierPrice['_tier_price_website'] = $this->getStoreCodeToWebsiteCode($arrSubRowData['_tier_price_website']);
          if($arrTierPrice['_tier_price_website']===false) continue;
          if(!in_array($arrTierPrice['_tier_price_website'],$arrProductWebsites))continue;
        }
        $arrTier[] = json_encode($arrTierPrice);
      }

    $arrTier = array_unique($arrTier);
    return array_map(function ($json) { return json_decode($json, true); }, $arrTier);
      }

 
  private function prepareProductsData($arrProductsData) {
    $arrReturn = array();

    foreach($arrProductsData as $arrProd) {
      $intArrProdLength = count($arrProd);
      $arrTier = [];
      for($i=0; $i < $intArrProdLength; $i++) {
        $default = null;
        if (isset($arrProd[$i]['url_key'])) {
          $arrProd[$i]['url_key'] = $this->prepareUrlKey($arrProd[$i]['url_key']);
          //$default = $arrProd[$i]['url_key'];
        }
        $this->prepareTypeConfigurable($arrProd[$i]);
        $this->prepareTypeBundle($arrProd[$i]);
        $this->prepareTypeGrouped($arrProd[$i]);
        $this->prepareVisibility($arrProd[$i]);

        $arrProd[$i] = array_replace($arrProd[$i],
          array_fill_keys(
            array_keys($arrProd[$i], '', true),
            ' '
          )
        );

        $arrProd[$i] = array_replace($arrProd[$i],
          array_fill_keys(
            array_keys($arrProd[$i], '###EMPTY###'),
            null
          )
        );

        $j = $i;
        while(isset($arrProd[$i+1]) && !isset($arrProd[$i+1]['sku'])) {

          $arrProd[$i+1] = array_replace($arrProd[$i+1],
            array_fill_keys(
              array_keys($arrProd[$i+1], '', true),
              ' '
            )
          );

          $arrProd[$i+1] = array_replace($arrProd[$i+1],
            array_fill_keys(
              array_keys($arrProd[$i+1], '###EMPTY###'),
              null
            )
          );


          $this->prepareVisibility($arrProd[$i+1]);
          $arrDefaultTier = [];
          $arrDefaultTier['name'] = isset($arrProd[$j]['name']) ? $arrProd[$j]['name'] : '';
          $arrDefaultTier['price'] = isset($arrProd[$j]['price']) ? $arrProd[$j]['price'] : '';
          if (isset($arrProd[$j]['url_key'])) {
            $arrDefaultTier['url_key'] = $arrProd[$j]['url_key'];
          }

          foreach(['csid', 'csclass', 'sku', '_type', '_attribute_set', '_product_websites'] as $key) {
            $arrDefaultTier[$key] = $arrProd[$j][$key];
            if(!isset($arrProd[$i+1][$key])) {
              $arrProd[$i+1][$key] = $arrProd[$j][$key];
            }
          }
          if (isset($arrProd[$i+1]['url_key'])) {
            $arrProd[$i+1]['url_key'] = $this->prepareUrlKey($arrProd[$i+1]['url_key'], $default);
          }

          if (isset($arrProd[$i+1]['name']) && $arrProd[$i+1]['name'] === '###EMPTY###') {
            $arrProd[$i+1]['name'] = null;
          }

          $arrTier = array_merge($arrTier, $this->prepareTierPrice($arrProd[$i+1], $arrDefaultTier));

          $i++;
        }
      }

      		
      $arrReturn[] = array_merge($arrProd, $arrTier);
    }
    return $arrReturn;
  }

  public function remapBundleKeys(array $arrSingleBundle) {

    $arrCS2Mag =  array(
            //'bundle_type' => 'type',
            '_bundle_option_title' => 'name',
            '_bundle_product_sku' => 'sku',
            '_bundle_product_is_default' => 'default',
            //'bundle_required' => 'required',
            '_bundle_product_price_value' => 'price',
            'bundle_price_type' => 'price-type',
            '_bundle_product_qty' => 'default-qty',
            '_bundle_product_position' => false,
            '_bundle_product_can_change_qty' => false
    );
    $arrCSKeys = array_keys($arrCS2Mag);
    foreach($arrSingleBundle as $key => $value) {
      if(in_array($key, $arrCSKeys, false)) {
        $strNewKey = $arrCS2Mag[$key];
        if($strNewKey){
          $arrSingleBundle[$strNewKey] = $value;
        }
        unset($arrSingleBundle[$key]);
      }
    }
    return $arrSingleBundle;
  }

  public function arrayToAttributeString($array){
    $attributes_str = NULL;
    foreach ((array)$array as $attribute => $value) {
        $attributes_str .= "$attribute=$value,";
    }
    return rtrim($attributes_str,',');
  }

}