<?php
/**
 * TOP API: aliexpress.solution.product.subject.description.edit request
 * 
 * @author auto create
 * @since 1.0, 2018.09.11
 */
class AliexpressSolutionProductSubjectDescriptionEditRequest
{
	/** 
	 * input parameters
	 **/
	private $editProductSubjectDescriptionRequest;
	
	private $apiParas = array();
	
	public function setEditProductSubjectDescriptionRequest($editProductSubjectDescriptionRequest)
	{
		$this->editProductSubjectDescriptionRequest = $editProductSubjectDescriptionRequest;
		$this->apiParas["edit_product_subject_description_request"] = $editProductSubjectDescriptionRequest;
	}

	public function getEditProductSubjectDescriptionRequest()
	{
		return $this->editProductSubjectDescriptionRequest;
	}

	public function getApiMethodName()
	{
		return "aliexpress.solution.product.subject.description.edit";
	}
	
	public function getApiParas()
	{
		return $this->apiParas;
	}
	
	public function check()
	{
		
	}
	
	public function putOtherTextParam($key, $value) {
		$this->apiParas[$key] = $value;
		$this->$key = $value;
	}
}
