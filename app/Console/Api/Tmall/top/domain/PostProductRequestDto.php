<?php
namespace App\Console\Api\Tmall\top\domain;
/**
 * input param
 * @author auto create
 */
class PostProductRequestDto
{

	/**
	 * If the merchant/ISVs has done the category mapping by himself, this field should be filled with the aliexpress category id.
	 **/
	public $aliexpress_category_id;

	/**
	 * merchant's attribute list
	 **/
	public $attribute_list;

	/**
	 * merchant's brand name
	 **/
	public $brand_name;

	/**
	 * If the category mapping has been done with help of local operator, this field could be filled with the merchant's category ID. The fields:aliexpress_category_id and category_id can not be both empty.
	 **/
	public $category_id;

	/**
	 * merchant's category name
	 **/
	public $category_name;

	/**
	 * freight template ID. After the merchant has created an freight template in the backend: freighttemplate.aliexpress.com, the id could be obtained in the backend directly or thourgh the API: aliexpress.freight.redefining.listfreighttemplate
	 **/
	public $freight_template_id;

	/**
	 * group id, you can get group list from aliexpress.product.productgroups.get
	 **/
	public $group_id;

	/**
	 * indicate when the inventory of a specific product will be deducted. place_order_withhold: the inventory is deducted just after the order is placed by customer. payment_success_deduct: the stock is deducted after the payment is done successfully by the customer.
	 **/
	public $inventory_deduction_strategy;

	/**
	 * Main images to be displayed for the product. The urls needs to be accessible. The url could be in the merchant's server or obtained by uploading the pictures to merchant's Aliexpress photobank, by using the API: aliexpress.photobank.redefining.uploadimageforsdk
	 **/
	public $main_image_urls_list;

	/**
	 * multi country price configuration
	 **/
	public $multi_country_price_configuration;

	/**
	 * List for multi language description. To learn how to set this field, please refer to the document:https://developers.aliexpress.com/en/doc.htm?docId=108976&docType=1
	 **/
	public $multi_language_description_list;

	/**
	 * List for multi language subject. To learn how to set this field, please refer to the document:https://developers.aliexpress.com/en/doc.htm?docId=108976&docType=1
	 **/
	public $multi_language_subject_list;

	/**
	 * product height in unit of "cm"
	 **/
	public $package_height;

	/**
	 * product length in unit of "cm"
	 **/
	public $package_length;

	/**
	 * product width in unit of "cm"
	 **/
	public $package_width;

	/**
	 * aliexpress product Id
	 **/
	public $product_id;

	/**
	 * service policy id, which could be set and obtained in the seller's background.
	 **/
	public $service_policy_id;

	/**
	 * refer to the preparation period of merchant before the package could be dispatched to the customer.
	 **/
	public $shipping_lead_time;

	/**
	 * merchant's size chart id, more used in the category of shoes and clothes.
	 **/
	public $size_chart_id;

	/**
	 * All the skus included in one product.
	 **/
	public $sku_info_list;

	/**
	 * weight for the product, including the package.
	 **/
	public $weight;
}
?>
