<?php
/**
 * TOP API: cainiao.global.handover.parcel.query request
 * 
 * @author auto create
 * @since 1.0, 2019.10.25
 */
class CainiaoGlobalHandoverParcelQueryRequest
{
	/** 
	 * 多语言
	 **/
	private $locale;
	
	/** 
	 * 小包的订单号
	 **/
	private $orderCode;
	
	/** 
	 * 小包的国际运单号
	 **/
	private $trackingNumber;
	
	private $apiParas = array();
	
	public function setLocale($locale)
	{
		$this->locale = $locale;
		$this->apiParas["locale"] = $locale;
	}

	public function getLocale()
	{
		return $this->locale;
	}

	public function setOrderCode($orderCode)
	{
		$this->orderCode = $orderCode;
		$this->apiParas["order_code"] = $orderCode;
	}

	public function getOrderCode()
	{
		return $this->orderCode;
	}

	public function setTrackingNumber($trackingNumber)
	{
		$this->trackingNumber = $trackingNumber;
		$this->apiParas["tracking_number"] = $trackingNumber;
	}

	public function getTrackingNumber()
	{
		return $this->trackingNumber;
	}

	public function getApiMethodName()
	{
		return "cainiao.global.handover.parcel.query";
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
