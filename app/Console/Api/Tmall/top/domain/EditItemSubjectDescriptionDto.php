<?php

/**
 * input parameters
 * @author auto create
 */
class EditItemSubjectDescriptionDto
{
	
	/** 
	 * description
	 **/
	public $description;
	
	/** 
	 * Indicates the language, in which the subject or description needs to be edited. Currently supported languages: en, es, ru
	 **/
	public $language;
	
	/** 
	 * aliexpress product id
	 **/
	public $product_id;
	
	/** 
	 * subject
	 **/
	public $subject;	
}
?>