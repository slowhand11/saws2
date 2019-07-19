<?php

namespace saws\sawsconnector\Model\Import\SawsConnector;

/**
 * Description of Products
 *
 * @author michael
 */
class Attributes extends AbstractImportType {
  private $objStoreMgr;
  private $entityType;
  
  function __construct() {
    parent::__construct();
    $this->objStoreMgr = \Magento\Framework\App\ObjectManager::getInstance()->get('\Magento\Store\Model\StoreManagerInterface');
    $this->entityType = \Magento\Framework\App\ObjectManager::getInstance()->get('Magento\Eav\Model\Entity\Type')->loadByCode('catalog_product');    
  }
  
/*  
  
    $arrDeleteCheck = array();
    foreach((array)$arrAttributes['attributes'] as $i => $arrAttr){
      //$objTest->createAttribute('Attribute2', 'Attribute2', array('options' => $arrOption, 'frontend_input' => 'select'));
      //$arrAttr['input'] = $arrAttr['frontend_input'];
      $objAttributHandler->createAttribute($arrAttr);
      foreach($arrAttr['attribute_set'] as $strSetName){
        $arrDeleteCheck[$strSetName][] = $arrAttr['attribute_code'];
        $objAttributHandler->addAttributeToSet($arrAttr['attribute_code'], $strSetName, reset($arrAttr['attribute_group_name']), $arrAttr['sort_order'][$strSetName]);
      }
      $this->objLog->increaseRecordCount(1);
      $idx++;
      if ($idx >= 10){
        $this->sendImportResultToCS($this->objLog->getEntityAndReset('attributes'));
        $idx =0;
      }
    }
    if ($arrDeleteCheck){
      foreach($arrDeleteCheck as $strAttributeSet => $arrExistingAttrs){
        $objAttributHandler->removeAttributesFromAttributeSet($strAttributeSet, $arrExistingAttrs); 
      }
    }* 
 * 
 * 
 */
  
  public function processImport($arrData, $options=array()) {
    if (!count($arrData))return;    
    
    $objResponse = $this->objResponse;
    
    $intStartTime = $objResponse->microtimeFloat();
    
    $this->updateAttributes($arrData);
    $objResponse->addLogEntry('Finish attributes import','global', 'attributes', $objResponse->getDuration($intStartTime));
  }
  
  
  function updateAttributes($arrData) { 
    
    if(isset($arrData['attributeset'])) {
      array_walk($arrData['attributeset'], array($this, 'createAttributeSet'));
    }
   
    if(isset($arrData['attributes'])) {
      array_walk($arrData['attributes'], array($this, 'createAttribute'));
    }      
    
    if ($arrData['attributeset_attributes']){
      array_walk($arrData['attributeset_attributes'], array($this, 'removeAttributesFromAttributeSet'));
    }
    
  }
  
  protected function getDefaultAttributsetId() {
    static $id = null;
    if ($id === null) {
      $id = \Magento\Framework\App\ObjectManager::getInstance()->get('Magento\Catalog\Model\Product')->getDefaultAttributeSetid();
    }
    return $id;
  }

  protected function createAttributeSet($arrAttibuteSet, $copyGroupsFromID = -1) {    
    $setName = isset($arrAttibuteSet['attribute_set_name']) 
                    ? trim($arrAttibuteSet['attribute_set_name']) : '';  
    
    if($setName == '') {
      $this->logError("Could not create attribute set with an empty name.");
      return false;
    }

    $objAttributeset = \Magento\Framework\App\ObjectManager::getInstance()->create('Magento\Eav\Model\Entity\Attribute\Set');
    $this->logInfo("create/update attribute-set with name [".$setName."] and entity-type [".$this->entityType->getId()."]" , 'global', 'attributes', -1);
    
    $attribute_set_id = $this->getAttributesetByName($setName)->getId();
    $arrData = [
        'attribute_set_id' => $attribute_set_id,
        'attribute_set_name' => $setName,
        'entity_type_id' => $this->entityType->getId(),
        //'sort_order' => 1,
    ];

    $objAttributeset->setData($arrData);
    $objAttributeset->validate();
    $objAttributeset->save();
    $objAttributeset->initFromSkeleton($this->getDefaultAttributsetId());
    $objAttributeset->save();
    if($objAttributeset->getId())return;
    
    $this->logError("Could not save AttributeSet: ".$setName);
  }

  
  protected function getAttributesetByName($strAttributesetName) {
    static $cache=array();
    //$a = new \Magento\Eav\Model\Entity\Attribute\Set();
    if (!isset($cache[$strAttributesetName])) {
      $objAttributeset = \Magento\Framework\App\ObjectManager::getInstance()->create('Magento\Eav\Model\Entity\Attribute\Set');
      $objAttributeset->load($strAttributesetName, 'attribute_set_name');
      $cache[$strAttributesetName] = $objAttributeset;
    }
    
    return $cache[$strAttributesetName];
  }
  
  
  function removeAttributesFromAttributeSet($arrAttributesSend = array(), $strSetName){
    $arrCurrentSet = $this->getAttributesForAttributset($strSetName);
    
    $intCurrentSetId = key($arrCurrentSet);
    $arrCurrentSet = reset($arrCurrentSet);

    //$arrCheck = array_diff_key($arrCurrentSet, $arrDefaultSet);
    $arrDelete = array_diff_key($arrCurrentSet, array_flip($arrAttributesSend)); 
    /* All attributes which are not in Default or given attribute set will deleted */
    if ($arrDelete){
      foreach((array)$arrDelete as $strAttrCode => $attribute){
        if (!$attribute->getId()) continue;
        $attributeSet = \Magento\Framework\App\ObjectManager::getInstance()->create('Magento\Eav\Model\Entity\Attribute\Set')->load($intCurrentSetId);
        if (!$attributeSet->getId()) continue;
        
        $attribute->setAttributeSetId($attributeSet->getId())->loadEntityAttributeIdBySet();
        $attribute->deleteEntity();  
        $this->logInfo("Remove Attribute: ".$strAttrCode." [".$attribute->getID()."] from set ".$strSetName);
      }
    }
  }  
  
  private function  getAttributesForAttributset($strSetName = 'Default', $bOnlyUserDefined=true){
    $arrAttributes = array();
    
    $attributeSetId = $this->getAttributesetByName($strSetName)->getId();
    
    $attributeManagementInterface = \Magento\Framework\App\ObjectManager::getInstance()->create('Magento\Eav\Api\AttributeManagementInterface'); 
    if ($attributeSetId==1) $attributeSetId=4;
    $arrTmp = $attributeManagementInterface->getAttributes(\Magento\Catalog\Api\Data\ProductAttributeInterface::ENTITY_TYPE_CODE, $attributeSetId);
    
    foreach((array)$arrTmp as $arrAttribute){
      if ($bOnlyUserDefined && !$arrAttribute->getData('is_user_defined'))continue;
      $arrAttributes[$arrAttribute->getData('attribute_code')] = $arrAttribute;
    }
    
    return array($attributeSetId => $arrAttributes);
  }
  
  protected function createAttribute($values = array(), $productTypes = array(), $setInfo = -1)  {
    $arrStoreLabels = $values['frontend_label'];

    $values['frontend_label'] = trim(reset($values['frontend_label']));
    if (isset($values['backend_model'])) $values['backend_model'] = $this->getBackendModel($values['backend_model']);
    if (isset($values['source_model'])) $values['source_model'] = $this->getSourceModel($values['source_model']);

    $labelText = $values['frontend_label'];
    $attributeCode = trim($values['attribute_code']);
    
    if($labelText == '' || $attributeCode == '') {
      $this->logError("Can't import the attribute with an empty label or code.  LABEL= [$labelText]  CODE= [$attributeCode]");
      return false;
    }    
    
    $strAttrGroup = 'General';
    if(isset($values['attribute_group_name'])) {
      $strAttrGroup = reset($values['attribute_group_name']);
    }
    $arrAttrSets = array();
    if(isset($values['attribute_set'])) {
      $arrAttrSets = $values['attribute_set'];
    }
    unset($values['attribute_set']);
    if(isset($values['attribute_group_name'])) {
      $strAttrGroup = reset($values['attribute_group_name']);  
    }    
    unset($values['attribute_group_name']);

    $values['entity_type_id'] = $this->entityType->getId();            
    $values['is_user_defined'] = 1;
    if (isset($values['apply_to']) &&  $values['apply_to'] == -1) $values['apply_to'] = '';
    //unset($values['source_model']);


    if($setInfo !== -1 && (isset($setInfo['SetID']) == false || isset($setInfo['GroupID']) == false)) {
      $this->logError("Please provide both the set-ID and the group-ID of the attribute-set if you'd like to subscribe to one.");
      return false;
    }

    $attribute = \Magento\Framework\App\ObjectManager::getInstance()->create('Magento\Eav\Model\Entity\Attribute'); 
    $values['modulePrefix'] = 'Magento_Catalog';
//    $attribute = \Magento\Framework\App\ObjectManager::getInstance()->create('Magento\Catalog\Model\Entity\Attribute'); 
    
    $attribute->loadByCode('catalog_product', $attributeCode);
    $values['attribute_id'] = $attribute->getId();
  
    foreach ((array)$arrStoreLabels as $strStoreView => $arrStoreLabel){
      $intStoreID = $strStoreView=='default' ? 
              0 : $this->objStoreMgr->getStore($strStoreView)->getId();
      $values['store_labels'][$intStoreID] = $arrStoreLabels[$strStoreView];
      $values['frontend_labels'][$intStoreID] = $arrStoreLabels[$strStoreView];
    } 
    
    $arrOptions = isset($values['options']) ? $values['options'] : array();
    unset($values['options']);
    $attribute->setData($values);
    $attribute->save();
    
    if($attribute->getId()<1){
      $this->logError("Attribute [$labelText] could not be saved!"); 
      return;
    } 
    
    if (count($arrOptions)) {
      $values['option'] = $this->prepareAttributeOptions($attribute, $arrOptions);
      unset($values['options']);
      $values['attribute_id'] = $attribute->getId();
      $attribute->setData($values);
      $attribute->save();
    }
    
    
    foreach((array)$arrAttrSets as $strAttrSet) {
      $objAttributeset = $this->getAttributesetByName($strAttrSet);
      
      $objMg = \Magento\Framework\App\ObjectManager::getInstance()->create('Magento\Eav\Model\Entity\Attribute\Group');
      $arrGroups = $objMg->getCollection(); 
      $arrGroupsFiltered = $arrGroups->addFieldToFilter("attribute_set_id",$objAttributeset->getId())->getItems(); 
      
      $intGroupId = 0;//$objAttributeset->getDefaultGroupId();
      foreach ($arrGroupsFiltered as $id=>$attributeGroup) {
        if ($attributeGroup->getAttributeGroupName()===$strAttrGroup) {
          $intGroupId = $attributeGroup->getAttributeGroupId();
        }
      }
                
      if (!$intGroupId){
        //create a new group 
        $objMg->setAttributeGroupName($strAttrGroup)->setAttributeSetId($objAttributeset->getId())->setSortOrder(100);
        $objMg->save();
        $intGroupId = $objMg->getAttributeGroupId();
        $this->logInfo("Create new  group for $strAttrGroup with ID (".$objMg->getAttributeGroupId().")");
      }
   
      
      $intSort = isset($values['sort_order'][$strAttrSet]) ? $values['sort_order'][$strAttrSet] : 0; 
      
      \Magento\Framework\App\ObjectManager::getInstance()->create('Magento\Eav\Model\AttributeManagement')
              ->assign('catalog_product', $objAttributeset->getId(), $intGroupId, $attributeCode, $intSort);
    }
    
    return true;
  }
  
  protected function prepareAttributeOptions($attribute, $options) {
    if (!is_array($options) || !count($options)) return true;

    $arrOptionMap = array();
    $arrMagOptions = $attribute->getSource()->getAllOptions(false,true);
    foreach((array)$arrMagOptions as $arrMagOpt){
      if (!is_array($arrMagOpt)) continue;
      if ($arrMagOpt['label'] && !is_array($arrMagOpt['label']) && !is_object($arrMagOpt['label'])) $arrOptionMap[$arrMagOpt['label']] = $arrMagOpt['value'];
    }

    $arrMagOptions = array();
    foreach((array)$options as $strAdminKey => $arrStoreValues) {
      if (array_key_exists($strAdminKey, $arrOptionMap)) {
        $strAdminKey1 = $arrOptionMap[$strAdminKey];
        unset($arrOptionMap[$strAdminKey]);
        $strAdminKey = $strAdminKey1;
      }
      else {
        $strAdminKey = 'new_'.strval($strAdminKey);
      }

      $arrMagOptions['value'][$strAdminKey] = array();
      foreach ((array)$arrStoreValues as $strStoreViewKey => $strOptionValue) {  
        $intStoreID = ($strStoreViewKey === "default") 
                ? 0 : $this->objStoreMgr->getStore($strStoreViewKey)->getId();
        $arrMagOptions['value'][$strAdminKey][$intStoreID] = $strOptionValue;
      }
    }

    if(is_array($arrOptionMap) && (count($arrOptionMap)>0)){
      /* delete unused options  */
      foreach((array)$arrOptionMap as $iOptID){          
        if($iOptID >0){
          $arrMagOptions['value'][$iOptID] = true;
          $arrMagOptions['delete'][$iOptID] = true;
        }
      }
      
    }

    return $arrMagOptions;
  }
  
  function replaceStoreViewCodes($arrValues, $bolAddDefault = true){
    $bolFoundDefault = false;
    $arrRetValue = array();
    foreach($arrValues as $strKey => $strVal){
      $intStoreID = $this->objStoreMgr->getStore($strKey)->getId();
      if (!$intStoreID && !$bolFoundDefault) $intStoreID = 0;
      $arrRetValue[$intStoreID] = $strVal;
      if ($intStoreID == 0) $bolFoundDefault = true;
    }

    if (!$bolFoundDefault && $bolAddDefault) $arrRetValue[0] = reset ($arrValues);
    return $arrRetValue;
  }
  
  protected function getBackendModel($strModel=''){  
    if(!$strModel) return '';
    
    return str_replace(
            array('eav/entity_attribute_backend_array'),
            array('Magento\Eav\Model\Entity\Attribute\Backend\ArrayBackend'),
            $strModel);   
  }
  
  protected function getSourceModel($strModel=''){  
    if(!$strModel) return '';
    
    return str_replace(
            array('eav/entity_attribute_source_boolean','eav/entity_attribute_source_table'),
            array('Magento\Catalog\Model\Product\Attribute\Source\Boolean',''),
            $strModel);   
  }

 
}  