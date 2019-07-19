<?php

/**
 * Copyright 2015 Magento. All rights reserved.
 * See COPYING.txt for license details.
 */

namespace saws\sawsconnector\Api;

//use saws\sawsconnector\Api\Data\PointInterface;

/**
 * Defines the service contract for some simple maths functions. The purpose is
 * to demonstrate the definition of a simple web service, not that these
 * functions are really useful in practice. The function prototypes were therefore
 * selected to demonstrate different parameter and return values, not as a good
 * calculator design.
 */
interface SawsConnectorInterface {
  
/**
 * Return the sum of the two numbers.
 *
 * @api
 * @return string The sum of the numbers.
 */
public function executeData();

/**
 * Return the sum of the two numbers.
 *
 * @api
 * @return array The sum of the numbers.
 */
public function executeOpenprocess();

/**
 * @api
 * @return mixed
 */
public function executeGetversion();
  

}