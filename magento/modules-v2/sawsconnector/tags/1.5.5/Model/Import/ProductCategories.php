<?php
namespace saws\sawsconnector\Model\Import;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Model\ResourceModel\Db\TransactionManagerInterface;
use Magento\Framework\Model\ResourceModel\Db\ObjectRelationProcessor;
use Magento\Framework\Stdlib\DateTime;
use Magento\ImportExport\Model\Import;
use Magento\ImportExport\Model\Import\ErrorProcessing\ProcessingErrorAggregatorInterface;



class ProductCategories extends Product {
  private $entityTypeCode = 'catalog_product_categories';


  public function __construct(
       \Magento\Framework\Json\Helper\Data $jsonHelper,
       \Magento\ImportExport\Helper\Data $importExportData,
       \Magento\ImportExport\Model\ResourceModel\Import\Data $importData,
       \Magento\Eav\Model\Config $config,
       \Magento\Framework\App\ResourceConnection $resource,
       \Magento\ImportExport\Model\ResourceModel\Helper $resourceHelper,
       \Magento\Framework\Stdlib\StringUtils $string,
       ProcessingErrorAggregatorInterface $errorAggregator,
       \Magento\Framework\Event\ManagerInterface $eventManager,
       \Magento\CatalogInventory\Api\StockRegistryInterface $stockRegistry,
       \Magento\CatalogInventory\Api\StockConfigurationInterface $stockConfiguration,
       \Magento\CatalogInventory\Model\Spi\StockStateProviderInterface $stockStateProvider,
       \Magento\Catalog\Helper\Data $catalogData,
       \Magento\ImportExport\Model\Import\Config $importConfig,
       \Magento\CatalogImportExport\Model\Import\Proxy\Product\ResourceModelFactory $resourceFactory,
       \Magento\CatalogImportExport\Model\Import\Product\OptionFactory $optionFactory,
       \Magento\Eav\Model\ResourceModel\Entity\Attribute\Set\CollectionFactory $setColFactory,
       \Magento\CatalogImportExport\Model\Import\Product\Type\Factory $productTypeFactory,
       \Magento\Catalog\Model\ResourceModel\Product\LinkFactory $linkFactory,
       \Magento\CatalogImportExport\Model\Import\Proxy\ProductFactory $proxyProdFactory,
       \Magento\CatalogImportExport\Model\Import\UploaderFactory $uploaderFactory,
       \Magento\Framework\Filesystem $filesystem,
       \Magento\CatalogInventory\Model\ResourceModel\Stock\ItemFactory $stockResItemFac,
       \Magento\Framework\Stdlib\DateTime\TimezoneInterface $localeDate,
       DateTime $dateTime,
       \Psr\Log\LoggerInterface $logger,
       \Magento\Framework\Indexer\IndexerRegistry $indexerRegistry,
       \Magento\CatalogImportExport\Model\Import\Product\StoreResolver $storeResolver,
       \Magento\CatalogImportExport\Model\Import\Product\SkuProcessor $skuProcessor,
       \Magento\CatalogImportExport\Model\Import\Product\CategoryProcessor $categoryProcessor,
       \Magento\CatalogImportExport\Model\Import\Product\Validator $validator,
       ObjectRelationProcessor $objectRelationProcessor,
       TransactionManagerInterface $transactionManager,
       \Magento\CatalogImportExport\Model\Import\Product\TaxClassProcessor $taxClassProcessor,
       \Magento\Framework\App\Config\ScopeConfigInterface $scopeConfig,
       \Magento\Catalog\Model\Product\Url $productUrl,
       array $data = [],
       array $dateAttrCodes = []
  ) {
    $this->entityTypeCode = 'catalog_product';
    call_user_func_array(['parent', '__construct'], func_get_args());
    $this->entityTypeCode = 'catalog_product_categories';
  }

  public function getEntityTypeCode() {
    return $this->entityTypeCode;
  }

  protected function _importData() {
    $this->entityTypeCode = 'catalog_product';
    return parent::_importData();
  }

  protected function _saveProductsData() {
    $this->_saveProducts();
    return $this;
  }

  function saveProductEntity(array $entityRowsIn, array $entityRowsUp) {
    return $this;
  }

  protected function _saveProductWebsites(array $websiteData) {
    return $this;
  }

  protected function _saveProductTierPrices(array $tierPriceData) {
    return $this;
  }

  protected function _saveProductAttributes(array $attributesData) {
    return $this;
  }

  protected function _saveMediaGallery(array $mediaGalleryData) {
    return $this;
  }

  protected function _saveProductCategories(array $categoriesData) {
      static $tableName = null;
      static $arrCleanCatgeoryIds = array();

      if (!$tableName) {
          $tableName = $this->_resourceFactory->create()->getProductCategoryTable();
      }
      if ($categoriesData) {
          $categoriesIn = [];
          $delProductId = [];
          $arrPos = [];
          $arrCurrentCatIds = [];

          foreach ($categoriesData as $delSku => $categories) {
            foreach (array_keys($categories) as $categoryId) {
              $arrPos[$categoryId] = 0;
            }
          }
          foreach ($categoriesData as $delSku => $categories) {
            $productId = $this->skuProcessor->getNewSku($delSku)['entity_id'];
            $delProductId[] = $productId;
            foreach (array_keys($categories) as $categoryId) {
              $arrPos[$categoryId]++;
              $categoriesIn[] = ['product_id' => $productId, 'category_id' => $categoryId, 'position' => $arrPos[$categoryId]];
              $arrCurrentCatIds[] = $categoryId;
            }
          }

          $arrCurrentCatIds = array_unique($arrCurrentCatIds);
          $arrCatIdsToClean = array_diff($arrCurrentCatIds, $arrCleanCatgeoryIds);


          if (\Magento\ImportExport\Model\Import::BEHAVIOR_ADD_UPDATE === $this->getBehavior() && !empty($arrCatIdsToClean)) {
            $this->_connection->delete(
                $tableName,
                $this->_connection->quoteInto('category_id IN ('. rtrim(str_repeat('?,', count($arrCatIdsToClean)),',').')', $arrCatIdsToClean)
            );
            $arrCleanCatgeoryIds = array_merge($arrCleanCatgeoryIds, $arrCatIdsToClean);
          }


          if (Import::BEHAVIOR_APPEND != $this->getBehavior() &&
              \Magento\ImportExport\Model\Import::BEHAVIOR_ADD_UPDATE != $this->getBehavior()) {
            $this->_connection->delete(
                $tableName,
                $this->_connection->quoteInto('product_id IN (?)', $delProductId)
            );
          }

          if ($categoriesIn) {
            $this->_connection->insertOnDuplicate($tableName, $categoriesIn, ['product_id', 'category_id']);
          }
      }
      return $this;
    }

}