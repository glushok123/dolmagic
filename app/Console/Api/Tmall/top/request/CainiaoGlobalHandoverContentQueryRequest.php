<?php
/**
 * TOP API: cainiao.global.handover.content.query request
 * 
 * @author auto create
 * @since 1.0, 2019.10.26
 */
class CainiaoGlobalHandoverContentQueryRequest
{
	/** 
	 * 大包的LP号
	 **/
	private $contentLgOrderCode;
	
	/** 
	 * 大包的运单号
	 **/
	private $contentTrackingNumber;
	
	private $apiParas = array();
	
	public function setContentLgOrderCode($contentLgOrderCode)
	{
		$this->contentLgOrderCode = $contentLgOrderCode;
		$this->apiParas["content_lg_order_code"] = $contentLgOrderCode;
	}

	public function getContentLgOrderCode()
	{
		return $this->contentLgOrderCode;
	}

	public function setContentTrackingNumber($contentTrackingNumber)
	{
		$this->contentTrackingNumber = $contentTrackingNumber;
		$this->apiParas["content_tracking_number"] = $contentTrackingNumber;
	}

	public function getContentTrackingNumber()
	{
		return $this->contentTrackingNumber;
	}

	public function getApiMethodName()
	{
		return "cainiao.global.handover.content.query";
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
