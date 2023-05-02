<?php
/**
 * TOP API: cainiao.global.handover.cancel request
 * 
 * @author auto create
 * @since 1.0, 2019.10.25
 */
class CainiaoGlobalHandoverCancelRequest
{
	/** 
	 * 要取消的交接单
	 **/
	private $handoverOrderId;
	
	/** 
	 * 要取消的交接物运单号
	 **/
	private $trackingNumber;
	
	private $apiParas = array();
	
	public function setHandoverOrderId($handoverOrderId)
	{
		$this->handoverOrderId = $handoverOrderId;
		$this->apiParas["handover_order_id"] = $handoverOrderId;
	}

	public function getHandoverOrderId()
	{
		return $this->handoverOrderId;
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
		return "cainiao.global.handover.cancel";
	}
	
	public function getApiParas()
	{
		return $this->apiParas;
	}
	
	public function check()
	{
		
		RequestCheckUtil::checkNotNull($this->handoverOrderId,"handoverOrderId");
		RequestCheckUtil::checkNotNull($this->trackingNumber,"trackingNumber");
	}
	
	public function putOtherTextParam($key, $value) {
		$this->apiParas[$key] = $value;
		$this->$key = $value;
	}
}
