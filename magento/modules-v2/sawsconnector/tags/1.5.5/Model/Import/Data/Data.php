<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace saws\sawsconnector\Model\Import\Data;

/**
 * Description of Data
 *
 * @author michael
 */
class Data extends \Magento\Framework\Api\AbstractSimpleObject{
 
  function getData($key=null) {
    return ($key!==null) ? $this->_get($key) : $this->__toArray();
  }
  
  
}
