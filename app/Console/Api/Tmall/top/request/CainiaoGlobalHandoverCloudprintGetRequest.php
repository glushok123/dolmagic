<?php
/**
 * TOP API: cainiao.global.handover.cloudprint.get request
 * 
 * @author auto create
 * @since 1.0, 2019.10.25
 */
class CainiaoGlobalHandoverCloudprintGetRequest
{
	/** 
	 * 物流单号
	 **/
	private $logisticsOrderCode;
	
	/** 
	 * 运单号
	 **/
	private $trackingNumber;
	
	private $apiParas = array();
	
	public function setLogisticsOrderCode($logisticsOrderCode)
	{
		$this->logisticsOrderCode = $logisticsOrderCode;
		$this->apiParas["logistics_order_code"] = $logisticsOrderCode;
	}

	public function getLogisticsOrderCode()
	{
		return $this->logisticsOrderCode;
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
		return "cainiao.global.handover.cloudprint.get";
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
