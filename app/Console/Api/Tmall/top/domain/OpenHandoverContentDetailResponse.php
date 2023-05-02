<?php

/**
 * 大包详情
 * @author auto create
 */
class OpenHandoverContentDetailResponse
{
	
	/** 
	 * 大包的LP号
	 **/
	public $content_lg_order_code;
	
	/** 
	 * 大包的运单号
	 **/
	public $content_tracking_number;
	
	/** 
	 * 大包关联的小包列表
	 **/
	public $parcel_order_list;
	
	/** 
	 * 大包状态
	 **/
	public $status;	
}
?>