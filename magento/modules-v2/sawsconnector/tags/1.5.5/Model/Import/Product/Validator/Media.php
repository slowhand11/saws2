<?php

namespace saws\sawsconnector\Model\Import\Product\Validator;

class Media extends \Magento\CatalogImportExport\Model\Import\Product\Validator\Media {
  
  protected function checkPath($string) {
    return preg_match('#^(?!.*[\\/]\.{2}[\\/])(?!\.{2}[\\/])[-\w.\\/\ &äööü.,]+$#', $string);
  }

}