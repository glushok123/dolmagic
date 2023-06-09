<?php

/**
 * input parameters
 * @author auto create
 */

namespace App\Console\Api\Tmall\top\domain;

class SkuAttributeInfoQueryRequest
{

	/**
	 * aliexpress category ID. aliexpress_category_id and category_id could not be both empty.
	 **/
	public $aliexpress_category_id;

	/**
	 * merchant's category ID
	 **/
	public $category_id;
}
?>
