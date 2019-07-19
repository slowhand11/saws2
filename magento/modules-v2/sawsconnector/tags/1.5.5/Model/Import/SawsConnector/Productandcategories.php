<?php

namespace saws\sawsconnector\Model\Import\SawsConnector;

/**
 * Description of Products
 *
 * @author michael
 */
class Productandcategories extends Products {

  protected function getMultipleValueSeparator() {
    return '###|###';
  }

  function processImport($arrData, $options=array()) {
    if (!count($arrData))return;

    $objResponse = $this->objResponse ;

    $objResponse->addLogEntry('Start product and category assignment import of '.count($arrData),'global', 'productandcategories', 0);
    $intStartTime = $objResponse->microtimeFloat();

    $arrData = $this->prepareCategoryProducts($arrData);
    $this->updateProducts($arrData, array('behavior'=>\Magento\ImportExport\Model\Import::BEHAVIOR_ADD_UPDATE));

    $objResponse->addLogEntry('Finish product and catagory assignment import of '.count($arrData),'global', 'productandcategories', $objResponse->getDuration($intStartTime));
  }

  protected function getEntityCode() {
   return 'catalog_product_categories';
  }

  protected function prepareCategoryProducts($arrCategoryProducts) {
    $objProductFactory = \Magento\Framework\App\ObjectManager::getInstance()->get('\Magento\Catalog\Model\ProductFactory');
    $objProdInterceptor = $objProductFactory->create();

    $arrTmpProducts = array();
    foreach($arrCategoryProducts as $arrProdCat) {
      for($i=0;$i<count($arrProdCat);$i++) {

        if(!$objProd = $objProdInterceptor->loadByAttribute('sku', $arrProdCat[$i]['_sku'])) {
          continue;
        }

        if(!isset($arrProdCat[$i]['_root']) || (trim($arrProdCat[$i]['_root']) =='') )continue;

        $strCat = $arrProdCat[$i]['_root'] . (!empty($arrProdCat[$i]['_category'])
                ?  '/' . $arrProdCat[$i]['_category'] : '');

        if(isset($arrTmpProducts[$arrProdCat[$i]['_sku']])) {
          // Only add category if it has not already been added
          if(in_array($strCat, explode(',', $arrTmpProducts[$arrProdCat[$i]['_sku']]['categories']))) continue;
          $arrTmpProducts[$arrProdCat[$i]['_sku']]['categories'] .= $this->getMultipleValueSeparator() . $strCat;// $arrProdCat[$i]['_category'];
        } else {
          $arrTmpProducts[$arrProdCat[$i]['_sku']] = array(
              'sku'         => $arrProdCat[$i]['_sku'],
              'categories'  => $strCat,
              'csid'        => $arrProdCat[$i]['csid'],
              'csclass'     => $arrProdCat[$i]['csclass']);

          if($objProd->getTypeId() == "bundle") {
            $arrTmpProducts[$arrProdCat[$i]['_sku']]['bundle_values'] = '';
          }
        }

      }
    }
    
    return array_values($arrTmpProducts);
  }

}