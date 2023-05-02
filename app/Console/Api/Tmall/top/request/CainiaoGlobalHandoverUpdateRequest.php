<?php
/**
 * TOP API: cainiao.global.handover.update request
 * 
 * @author auto create
 * @since 1.0, 2019.10.25
 */
class CainiaoGlobalHandoverUpdateRequest
{
	/** 
	 * 交接单id
	 **/
	private $handoverOrderId;
	
	/** 
	 * 关联小包列表
	 **/
	private $parcelList;
	
	/** 
	 * 揽收信息
	 **/
	private $pickupInfo;
	
	/** 
	 * 大包备注
	 **/
	private $remark;
	
	/** 
	 * 退件信息
	 **/
	private $returnInfo;
	
	/** 
	 * 交接单类型，菜鸟揽收或自寄
	 **/
	private $type;
	
	/** 
	 * 大包重量
	 **/
	private $weight;
	
	/** 
	 * 大包重量单位，默认g
	 **/
	private $weightUnit;
	
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

	public function setParcelList($parcelList)
	{
		$this->parcelList = $parcelList;
		$this->apiParas["parcel_list"] = $parcelList;
	}

	public function getParcelList()
	{
		return $this->parcelList;
	}

	public function setPickupInfo($pickupInfo)
	{
		$this->pickupInfo = $pickupInfo;
		$this->apiParas["pickup_info"] = $pickupInfo;
	}

	public function getPickupInfo()
	{
		return $this->pickupInfo;
	}

	public function setRemark($remark)
	{
		$this->remark = $remark;
		$this->apiParas["remark"] = $remark;
	}

	public function getRemark()
	{
		return $this->remark;
	}

	public function setReturnInfo($returnInfo)
	{
		$this->returnInfo = $returnInfo;
		$this->apiParas["return_info"] = $returnInfo;
	}

	public function getReturnInfo()
	{
		return $this->returnInfo;
	}

	public function setType($type)
	{
		$this->type = $type;
		$this->apiParas["type"] = $type;
	}

	public function getType()
	{
		return $this->type;
	}

	public function setWeight($weight)
	{
		$this->weight = $weight;
		$this->apiParas["weight"] = $weight;
	}

	public function getWeight()
	{
		return $this->weight;
	}

	public function setWeightUnit($weightUnit)
	{
		$this->weightUnit = $weightUnit;
		$this->apiParas["weight_unit"] = $weightUnit;
	}

	public function getWeightUnit()
	{
		return $this->weightUnit;
	}

	public function getApiMethodName()
	{
		return "cainiao.global.handover.update";
	}
	
	public function getApiParas()
	{
		return $this->apiParas;
	}
	
	public function check()
	{
		
		RequestCheckUtil::checkNotNull($this->handoverOrderId,"handoverOrderId");
		RequestCheckUtil::checkNotNull($this->parcelList,"parcelList");
		RequestCheckUtil::checkMaxListSize($this->parcelList,200,"parcelList");
		RequestCheckUtil::checkNotNull($this->weight,"weight");
	}
	
	public function putOtherTextParam($key, $value) {
		$this->apiParas[$key] = $value;
		$this->$key = $value;
	}
}
