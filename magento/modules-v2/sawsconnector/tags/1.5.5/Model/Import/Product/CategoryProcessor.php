<?php

namespace saws\sawsconnector\Model\Import\Product;

class CategoryProcessor extends \Magento\CatalogImportExport\Model\Import\Product\CategoryProcessor {

  protected function initCategories() {
    if (empty($this->categories)) {
      $collection = $this->categoryColFactory->create();
      $collection->addAttributeToSelect('name')
              ->addAttributeToSelect('url_key')
              ->addAttributeToSelect('url_path')
              ->setStoreId(0);

      /* @var $collection \Magento\Catalog\Model\ResourceModel\Category\Collection */
      foreach ($collection as $category) {
        $category->setStoreId(0);

        $structure = explode(self::DELIMITER_CATEGORY, $category->getPath());
        $pathSize = count($structure);

        $this->categoriesCache[$category->getId()] = $category;
        if ($pathSize > 1) {
          $path = [];
          for ($i = 1; $i < $pathSize; $i++) {
            $item = $collection->getItemById((int) $structure[$i]);
            $item->setStoreId(0);
            $path[] = $item->getName();
          }
          /** @var string $index */
          $index = $this->standardizeString(
                  implode(self::DELIMITER_CATEGORY, $path)
          );
          $this->categories[$index] = $category->getId();
        }
      }
    }
    return $this;
  }

  protected function upsertCategory($categoryPath) {
    /** @var string $index */
    $arrCats = $this->standardizeArrayKeys($this->categories);

    $index = $this->standardizeString(str_replace('\/', '/', $categoryPath));
    if (!isset($arrCats[$index])) {
      $pathParts = preg_split('/(?<!\\\)' . preg_quote(self::DELIMITER_CATEGORY, '/') . '/', $categoryPath);
      $parentId = \Magento\Catalog\Model\Category::TREE_ROOT_ID;
      $path = '';

      foreach ($pathParts as $pathPart) {
        $pathPart = str_replace('\/', '/', $pathPart);
        $path .= $this->standardizeString($pathPart);
        if (!isset($arrCats[$path])) {
          $id = $this->createCategory($pathPart, $parentId);
          $arrCats[$path] = $id;
          $this->categories[$path] = $id;
        }

        $parentId = $arrCats[$path];
        $path .= self::DELIMITER_CATEGORY;
      }
    }

    return $arrCats[$index];
  }

  function standardizeArrayKeys($arr) {
    foreach ($arr as $k => $v) {
      $ret[$this->standardizeString($k)] = $v;
    }
    return $ret;
  }


  private function standardizeString($string) {
    return mb_strtolower($string);
  }

}