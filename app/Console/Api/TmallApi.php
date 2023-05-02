<?php

namespace App\Console\Api;

use App\Console\Api\Tmall\top\domain\AttributeDto;
use App\Console\Api\Tmall\top\domain\MultiCountryPriceConfigurationDto;
use App\Console\Api\Tmall\top\domain\PostProductRequestDto;
use App\Console\Api\Tmall\top\domain\SingleLanguageDescriptionDto;
use App\Console\Api\Tmall\top\domain\SingleLanguageTitleDto;
use App\Console\Api\Tmall\top\domain\SkuAttributeDto;
use App\Console\Api\Tmall\top\domain\SkuAttributeInfoQueryRequest;
use App\Console\Api\Tmall\top\domain\SkuInfoDto;
use App\Console\Api\Tmall\top\request\AliexpressFreightRedefiningListfreighttemplateRequest;
use App\Console\Api\Tmall\top\request\AliexpressPhotobankRedefiningUploadimageforsdkRequest;
use App\Console\Api\Tmall\top\request\AliexpressPostproductRedefiningOfflineaeproductRequest;
use App\Console\Api\Tmall\top\request\AliexpressPostproductRedefiningOnlineaeproductRequest;
use App\Console\Api\Tmall\top\request\AliexpressProductProductgroupsGetRequest;
use App\Console\Api\Tmall\top\request\AliexpressSolutionBatchProductDeleteRequest;
use App\Console\Api\Tmall\top\request\AliexpressSolutionBatchProductPriceUpdateRequest;
use App\Console\Api\Tmall\top\request\AliexpressSolutionProductEditRequest;
use App\Console\Api\Tmall\top\request\AliexpressSolutionProductPostRequest;
use App\Console\Api\Tmall\top\request\AliexpressSolutionProductSchemaGetRequest;
use App\Console\Api\Tmall\top\request\AliexpressSolutionSellerCategoryTreeQueryRequest;
use App\Console\Api\Tmall\top\request\AliexpressSolutionSkuAttributeQueryRequest;
use App\Eloquent\Products\Product;
use App\Eloquent\Products\TypeShopProduct;
use App\Eloquent\Shops\Products\ShopProductsGroup;
use App\Eloquent\Shops\ShopBrandName;
use App\Eloquent\System\SystemsBrandName;
use App\Eloquent\Warehouse\TypeShopWarehouse;
use App\Models\Prices\Price;
use App\Models\Products;
use App\Console\Api\Tmall\top\TopClient;
use App\Console\Api\Tmall\top\request\AliexpressSolutionOrderGetRequest;
use App\Console\Api\Tmall\top\request\AliexpressSolutionOrderInfoGetRequest;
use App\Console\Api\Tmall\top\request\AliexpressSolutionProductListGetRequest;
use App\Console\Api\Tmall\top\request\AliexpressSolutionProductInfoGetRequest;
use App\Console\Api\Tmall\top\request\AliexpressSolutionBatchProductInventoryUpdateRequest;
use App\Console\Api\Tmall\top\domain\ItemListQuery;
use App\Console\Api\Tmall\top\domain\OrderQuery;
use App\Console\Api\Tmall\top\domain\OrderDetailQuery;
use App\Console\Api\Tmall\top\domain\SynchronizeProductRequestDto;
use App\Console\Api\Tmall\top\domain\SynchronizeSkuRequestDto;
use Illuminate\Support\Facades\Storage;

class TmallApi extends Api
{
    public $systemId = 6; // Tmall
    public $shopId = 6;
    public $appKey = 28144460;
    public $appSecret = 'ed88b6a3b9c41537e27a38612b55bfd7';
    public $sessionKey = '50000500b28govkAo3qP9gckYjIsfRRheDaserUV1f176c98HKjup2hqwxBFHOFZcpy';
    public $importUpPrice = 200;
    public $importUpPricePercent = 10;
    public $importUpPriceLimit = 1000; // this max price for up price
    public $tmallClient;

    // Session key = https://oauth.aliexpress.com/authorize?response_type=token&client_id=28144460&state=1212&view=web&sp=ae

    // other
    //public $freight_template_id = 716480743; // "703476858" - dostavka1
    public $freight_template_id = 24036132097; // "24036132097" - Шаблон FBS. Пункт приёма Почта России 1
    public $header_module_id = 34616114;
    public $footer_module_id = 24657170;



    public function __construct()
    {
        parent::__construct();

        $this->tmallClient = new TopClient;
        $this->tmallClient->appkey = $this->appKey;
        $this->tmallClient->secretKey = $this->appSecret;
    }

    public function getOrdersList($date_from, $onlyPayed = true)
    {
        $orders = $this->getOrdersStack($date_from);
        $returnOrders = [];

        foreach($orders as $Order)
        {
            if($onlyPayed and ($Order->fund_status !== 'PAY_SUCCESS')) continue;
            $returnOrders[] = $Order;
        }
        return $returnOrders;
    }

    public function getOrdersStack($date_from, $orders = array(), $page = 1, $cycle = 0){
        $limit = 50;
        $req = new AliexpressSolutionOrderGetRequest;
        $param0 = new OrderQuery;
        $param0->create_date_start = $date_from;
        $param0->page_size = $limit;
        $param0->current_page = $page;
        $req->setParam0(json_encode($param0));
        $res = $this->tmallClient->execute($req, $this->sessionKey);

        if(isset($res->result) and ($res->result->error_code == 0)){
            if($res->result->total_count > 0){
                $newOrders = $res->result->target_list->order_dto;
                $orders = array_merge($orders, $newOrders);
            };
        }else{
            $this->log('error', 'getOrdersStack', 'Unknown error', $param0, $res);
        };

        if($res->result->total_count == $limit){
            print_r('Цикл: '.$cycle. PHP_EOL);
            if($cycle > 50){
                $this->log('error', 'getOrdersStack code 2', 'code 2', $param0, $res);
                die('exit code getOrdersStack 2');
            };
            return $this->getOrdersStack($date_from, $orders, $page+1, $cycle+1);
        }else{
            return $orders;
        }
    }

    public function getOrder($orderId)
    {

        die('Tmall have another fields on getOrder');

        $req = new AliexpressSolutionOrderInfoGetRequest;
        $param1 = new OrderDetailQuery;
        $param1->order_id=$orderId;
        $req->setParam1(json_encode($param1));
        $res = $this->tmallClient->execute($req, $this->sessionKey);

        print_r($res);
        die();
    }


    public function getAllProducts()
    {
        $allProducts = [];

        if($products = $this->getProducts('onSelling')) // в продаже
            $allProducts = array_merge($allProducts, $products);

        if($products = $this->getProducts('offline')) // снято с продажи
            $allProducts = array_merge($allProducts, $products);

        if($products = $this->getProducts('auditing')) // на рассмотрении
            $allProducts = array_merge($allProducts, $products);

        if($products = $this->getProducts('editingRequired')) // заблокированы?
            $allProducts = array_merge($allProducts, $products);

        return $allProducts;
    }

    public function getProducts($productStatusType = 'onSelling', $perPage = 100)
    {
        $tmallProducts = array();

        $page = 1;
        $stop = false;
        $stopper = 0;

        while(!$stop)
        {
            $req = new AliexpressSolutionProductListGetRequest;
            $aeop_a_e_product_list_query = new ItemListQuery;
            $aeop_a_e_product_list_query->current_page=$page;
            $aeop_a_e_product_list_query->page_size=$perPage;
            $aeop_a_e_product_list_query->product_status_type = $productStatusType;
            $req->setAeopAEProductListQuery(json_encode($aeop_a_e_product_list_query));
            $res = $this->tmallClient->execute($req, $this->sessionKey);

            $products = $res->result->aeop_a_e_product_display_d_t_o_list->item_display_dto??[];

            $page++;

            if($products)
            {
                if(count($products) > 0) $tmallProducts = array_merge($tmallProducts, $products);
                if(count($products) < $perPage) $stop = true;
            }else{
                $this->log('error', 'getProducts', "GET tmall get products", NULL, $products);
                $stop = true;
            };

            $stopper++;
            if($stopper == 50) $stop = true;
        };

        return $tmallProducts;
    }

    public function getProductInfo($productId)
    {
        $req = new AliexpressSolutionProductInfoGetRequest;
        $req->setProductId($productId);
        $resp = $this->tmallClient->execute($req, $this->sessionKey);

        return $resp->result;
    }

    public function updateQuantity($products = [])
    {
        $start = microtime(true);

        if(!$products)
        {
            $products = Product::whereHas('typeShopProducts', function ($q)
            {
                $q->where('type_shop_id', $this->shopId);
            })->get();
        }

        if(count($products) === 0) dd('Nothing to update');

        $productsCount = count($products);
        $productsError = 0;
        $productsSuccess = 0;

        $updateList = [];
        $stuck = 0;
        foreach($products as $key => $Product)
        {
            $mutiple_product_update_list = new SynchronizeProductRequestDto;
            $mutiple_product_update_list->product_id = $Product->typeShopProducts->where('type_shop_id', $this->shopId)->first()->shop_product_id;
            $multiple_sku_update_list = new SynchronizeSkuRequestDto;
            $multiple_sku_update_list->sku_code = $Product->sku;

            $Stop = Products::getSystemsProductsStopResult($Product, $this->shopId);
            $multiple_sku_update_list->inventory = $Stop->stock?0:($Product->shopAmounts($this->shopId)->amounts->balance??0);
            $mutiple_product_update_list->multiple_sku_update_list = $multiple_sku_update_list;

            $updateList[] = $mutiple_product_update_list;


            if((count($updateList) === 20) or ($key === (count($products) - 1))) // max 20 products
            {
                $req = new AliexpressSolutionBatchProductInventoryUpdateRequest;
                $req->setMutipleProductUpdateList(json_encode($updateList));
                $resp = $this->tmallClient->execute($req, $this->sessionKey);
                $stuck++;

                if(isset($resp->update_success) and $resp->update_success)
                {
                    $productsSuccess++;
                    var_dump('success '.count($updateList));
                }else{
                    var_dump('error '.count($updateList));
                    dump($resp);
                    $productsError++;
                    $this->log('error', 'updateQuantity', count($updateList), 'too big', $resp);
                }

                $updateList = [];
            }
            print_r($key.' of '.$productsCount."\r");
        }

        $ExecutionTime = 'Script execution time '.round(microtime(true) - $start, 4).'sec';
        var_dump($ExecutionTime);
        $this->log('info', 'updateQuantity', "Total: $productsCount/$stuck, error: $productsError, success: $productsSuccess. $ExecutionTime");
    }

    public function updatePrice($products = [])
    {
        $start = microtime(true);

        if(!$products)
        {
            $products = Product::whereHas('typeShopProducts', function ($q)
            {
                $q->where('type_shop_id', $this->shopId);
            })->inRandomOrder()->get();
        }

        if(count($products) === 0) dd('Nothing to update');

        $productsCount = count($products);
        $productsError = 0;
        $productsSuccess = 0;

        $updateList = [];
        $stuck = 0;
        foreach($products as $key => $Product)
        {
            $Stop = Products::getSystemsProductsStopResult($Product, $this->shopId);
            if(!$Stop->price)
            {
                $mutiple_product_update_list = new SynchronizeProductRequestDto;
                $mutiple_product_update_list->product_id = $Product->typeShopProducts->where('type_shop_id', $this->shopId)->first()->shop_product_id;
                $multiple_sku_update_list = new SynchronizeSkuRequestDto;

                $multiple_sku_update_list->price = Price::recalculatePriceByUnloadingOption($Product->temp_price, $this->shopId);
                /*
                if($Product->temp_old_price and ($Product->temp_old_price > $Product->temp_price))
                {
                    $multiple_sku_update_list->price = Price::recalculatePriceByUnloadingOption($Product->temp_old_price, $this->shopId);
                    $multiple_sku_update_list->discount_price = Price::recalculatePriceByUnloadingOption($Product->temp_price, $this->shopId);
                }else{
                    $multiple_sku_update_list->price = Price::recalculatePriceByUnloadingOption($Product->temp_price, $this->shopId);
                }
                */

                $multiple_sku_update_list->sku_code = $Product->sku;
                $mutiple_product_update_list->multiple_sku_update_list = $multiple_sku_update_list;

                $updateList[] = $mutiple_product_update_list;
            }

            if((count($updateList) === 20) or ($key === (count($products) - 1))) // max 20 products
            {
                $req = new AliexpressSolutionBatchProductPriceUpdateRequest;
                $req->setMutipleProductUpdateList(json_encode($updateList));
                $resp = $this->tmallClient->execute($req, $this->sessionKey);
                $stuck++;

                if(isset($resp->update_success) and $resp->update_success)
                {
                    $productsSuccess++;
                    var_dump('success '.count($updateList));
                    //$this->log('success', 'updatePrice', count($updateList), $updateList, $resp);
                }else{
                    var_dump('error '.count($updateList));
                    $productsError++;
                    $this->log('error', 'updatePrice', count($updateList), $updateList, $resp);
                }

                $updateList = [];
            }
            print_r($key.' of '.$productsCount."\r");
        }

        $ExecutionTime = 'Script execution time '.round(microtime(true) - $start, 4).'sec';
        var_dump($ExecutionTime);
        $this->log('info', 'updatePrice', "Total: $productsCount/$stuck, error: $productsError, success: $productsSuccess. $ExecutionTime");
    }

    public function getGroupList()
    {
        $req = new AliexpressProductProductgroupsGetRequest;
        $res = $this->tmallClient->execute($req, $this->sessionKey);
        return $res->result->target_list->aeop_ae_product_tree_group??[];
    }


    public function addGroup($Group, $parentId = false, $root = 0)
    {
        $ShopProductsGroup = new ShopProductsGroup;
        $ShopProductsGroup->shop_id = $this->shopId;
        $ShopProductsGroup->shop_title = $Group->group_name;
        $ShopProductsGroup->shop_group_id = $Group->group_id;
        if($parentId) $ShopProductsGroup->shop_parent_group_id = $parentId;
        if($root) $ShopProductsGroup->root = $root;
        if($ShopProductsGroup->save())
        {
            if(isset($Group->child_group_list->aeop_ae_product_child_group))
            {
                foreach($Group->child_group_list->aeop_ae_product_child_group as $ChildGroup)
                {
                    $this->addGroup($ChildGroup, $Group->group_id);
                }
            }
        };
    }

    public function saveGroupList()
    {
        $groups = $this->getGroupList();

        if($groups and !empty($groups))
        {
            ShopProductsGroup::where('shop_id', $this->shopId)->delete(); // clear before add new
            foreach($groups as $Group)
            {
                $this->addGroup($Group, false, 1);
            }
        }
    }

    public function getCategoryList()
    {
        // 26 - Игрушки и хобби
        // 200001389 - Куклы и аксессуары
        // 2605 - Куклы
        $req = new AliexpressSolutionSellerCategoryTreeQueryRequest;
        //$req->setCategoryId(200001389);
        $req->setCategoryId(2605);
        //$req->setFilterNoPermission('true');
        $req->setFilterNoPermission('false');
        $res = $this->tmallClient->execute($req, $this->sessionKey);
        dd($res);
    }

    public function getFreightList()
    {
        $req = new AliexpressFreightRedefiningListfreighttemplateRequest;
        $res = $this->tmallClient->execute($req, $this->sessionKey);
        dd($res);
    }

    public function uploadProducts($products = [])
    {
        if(!$products)
        {
            $products = Product::whereDoesntHave('typeShopProducts', function ($q)
            {
                $q->where('type_shop_id', $this->shopId);
            })
                ->whereHas('amounts', function ($amounts){
                    $amounts->where('available', '>', 0);
                    $amounts->whereIn('warehouse_id', TypeShopWarehouse::where('type_shop_id', $this->shopId)->pluck('warehouse_id')->toArray());
                })
                ->get();
        }

        $countProducts = count($products);

        if($countProducts === 0)
        {
            dd('Nothing to upload');
        }else
        {
            dump("$countProducts to upload");
        }

        $productsUploaded = '';
        foreach($products as $key => $Product)
        {
            dump("$key / $countProducts");

            $Stop = Products::getSystemsProductsStopResult($Product, $this->shopId);

            if(empty($Product->temp_short_description))
            {
                $this->log('error', 'uploadProducts', "Product $Product->sku hasn't temp_short_description");
                continue;
            }

            $req = new AliexpressSolutionProductPostRequest;
            $post_product_request = new PostProductRequestDto;
            $post_product_request->subject = $Product->name_ru;
            $post_product_request->description = $Product->temp_short_description;
            $post_product_request->language = 'ru';
            $post_product_request->product_unit = '100000015'; // шт.

            if($Product->name_eng)
            {
                $multi_language_subject_list = new SingleLanguageTitleDto;
                $multi_language_subject_list->subject = $Product->name_eng;
                $multi_language_subject_list->language='en';
                $post_product_request->multi_language_subject_list = $multi_language_subject_list;
            }

            if($this->shopId === 6)
            {
                $post_product_request->group_id = $Product->group->tmall_group_id;
            }

            if($this->shopId === 8)
            {
                $post_product_request->group_id = $Product->group->aliexpress_group_id;
            }

            $post_product_request->aliexpress_category_id = 2605; // 2605 - куклы

            $post_product_request->brand_name = $this->getBrandNameByManufacturer($Product);;



            if(!$Stop or (!$Stop->image))
                if($Product->images)
                {
                    if($imagesList = $this->getProductImages($Product))
                    {
                        $post_product_request->main_image_urls_list = $imagesList;
                    }
                }

            $sku_info_list = new SkuInfoDto;


            $sku_info_list->inventory = $Stop->stock?0:($Product->shopAmounts($this->shopId)->amounts->balance??0);

            $sku_info_list->price = Price::recalculatePriceByUnloadingOption($Product->temp_price, $this->shopId);

            $sku_info_list->sku_code = $Product->sku;
            $post_product_request->sku_info_list = $sku_info_list;

            $post_product_request->inventory_deduction_strategy = 'payment_success_deduct';

            $post_product_request->weight = (string) ($Product->weight/1000);

            // TEMP SIZES
            $post_product_request->package_length = $Product->category->temp_depth;
            $post_product_request->package_height = $Product->category->temp_height;
            $post_product_request->package_width = $Product->category->temp_width;

            $post_product_request->freight_template_id = $this->freight_template_id;
            $post_product_request->shipping_lead_time = 3;
            $post_product_request->service_policy_id = 0;

            $req->setPostProductRequest(json_encode($post_product_request));

            $res = $this->tmallClient->execute($req, $this->sessionKey);

            if(isset($res->result->product_id))
            {
                $productsUploaded .= "sku: $Product->sku id: {$res->result->product_id}, ";
                $TypeShopProduct = new TypeShopProduct;
                $TypeShopProduct->type_shop_id = $this->shopId;
                $TypeShopProduct->shop_product_id = $res->result->product_id;
                $Product->typeShopProducts()->save($TypeShopProduct);
            }else{
                dump("$Product->sku {$Product->group->aliexpress_group_id}");
                dump($res);
            }
        }

        if(count($products) > 0 )
            $this->log('info', 'uploadProducts', "Products uploaded: $productsUploaded");
    }

    public function getProductImages($Product, $tmallProductId = false): array
    {

        $addAdditionalImage = true; // on / off
        $additionalImage = false;

        if($addAdditionalImage and $tmallProductId)
        {
            switch($this->shopId)
            {
                case 6: // Tmall
                    $additionalImage = "/images/temp/products-tmall/{$tmallProductId}_1.jpg";
                break;
                case 8: // Aliexpress
                    $additionalImage = "/images/temp/products-aliexpress/{$tmallProductId}_1.jpg";
                    break;
            }

            if($additionalImage)
            {
                $path = '/var/www/crmdollmagic.ru/public'.$additionalImage;
                if(!file_exists($path)) $additionalImage = false;
            }
        }

        $images = [];

        if($additionalImage) $images[] = 'http://crmdollmagic.ru'.$additionalImage;

        $shopImages = Products::getShopImages($Product, $this->shopId);
        foreach($shopImages as $key => $ProductImage)
        {
            if($additionalImage)
            {
                if($key === 5) break; // There is no more than 5 images
            }else
            {
                if($key === 6) break; // There is no more than 6 images
            }

            $images[] = $ProductImage->url;
        }
        return $images;
    }

    public function getAttributeQuery()
    {
        $req = new AliexpressSolutionSkuAttributeQueryRequest;
        $query_sku_attribute_info_request = new SkuAttributeInfoQueryRequest;
        $query_sku_attribute_info_request->aliexpress_category_id = 2605;
        $req->setQuerySkuAttributeInfoRequest(json_encode($query_sku_attribute_info_request));
        $resp = $this->tmallClient->execute($req, $this->sessionKey);

        print_r(json_encode($resp));
    }

    public function updateProducts($products = [], $test = false)
    {
        $start = microtime(true);

        if(!$products)
        {
            $products = Product::whereHas('typeShopProducts', function ($q)
            {
                $q->where('type_shop_id', $this->shopId);
            })
                ->whereHas('amounts', function ($amounts){
                    $amounts->where('available', '>', 0);
                    $amounts->whereIn('warehouse_id', TypeShopWarehouse::where('type_shop_id', $this->shopId)->pluck('warehouse_id')->toArray());
                })
                ->get();
        }

        $productsCount = count($products);
        $productsError = 0;
        $productsErrorShopIds = [];
        $productsSuccess = 0;
        $productsSkipped = 0;

        if($productsCount === 0) dd('Nothing to update');

        $productsUpdated = '';
        foreach($products as $key => $Product)
        {
            $Stop = Products::getSystemsProductsStopResult($Product, $this->shopId);

            if(empty($Product->temp_short_description))
            {
                $this->log('error', 'updateProducts', "Product $Product->sku hasn't temp_short_description");
                continue;
            }

            $req = new AliexpressSolutionProductEditRequest;
            $edit_product_request = new PostProductRequestDto;
            $edit_product_request->freight_template_id = $this->freight_template_id;

            $tmallProductId = $Product->typeShopProducts->where('type_shop_id', $this->shopId)->first()->shop_product_id;
            $edit_product_request->product_id = $tmallProductId;

            //$edit_product_request->subject = $Product->name_ru.' 1'; //not changed

            $subjectList = [];

            $multi_language_subject_list = new SingleLanguageTitleDto;
            $multi_language_subject_list->subject = $Product->name_ru;
            $multi_language_subject_list->language='ru';
            $subjectList[] = $multi_language_subject_list;

            if($Product->name_eng)
            {
                $multi_language_subject_list = new SingleLanguageTitleDto;
                $multi_language_subject_list->subject = $Product->name_eng;
                $multi_language_subject_list->language='en';
                $subjectList[] = $multi_language_subject_list;
            }

            $edit_product_request->multi_language_subject_list = $subjectList;


            /* TEMP ACTION TO SAY - NOT WORK */
            if(!$Stop or (!$Stop->image))
                if($Product->images)
                    $edit_product_request->main_image_urls_list = $this->getProductImages($Product, $tmallProductId);


            $attributeList = [];

            if($Product->manufacturer)
            {
                $brandName = $this->getBrandNameByManufacturer($Product);
                $edit_product_request->brand_name = $brandName;

                $attribute_list = new AttributeDto;
                //$attribute_list->aliexpress_attribute_name_id = 2;
                $attribute_list->attribute_name = 'Brand Name';
                $attribute_list->attribute_value = $brandName;
                $attributeList[] = $attribute_list;
            }

            if($Product->producingCountry)
            {
                $attribute_list = new AttributeDto;
                $attribute_list->aliexpress_attribute_name_id = 219;
                $attribute_list->attribute_name = 'Origin';

                $attribute_list->attribute_value = $Product->producingCountry->alliexpress_name;
                $attributeList[] = $attribute_list;
            }

            $attribute_list = new AttributeDto;
            $attribute_list->aliexpress_attribute_name_id = 3;
            $attribute_list->attribute_name = 'Model Number';

            $attribute_list->attribute_value = $Product->sku;
            $attributeList[] = $attribute_list;

            $edit_product_request->attribute_list = $attributeList;
            $description = $this->formationModulesDescription($Product);

            $multi_language_description_list = new SingleLanguageDescriptionDto;
            $multi_language_description_list->language = 'ru';
            //$multi_language_description_list->mobile_detail = $description; // deprecated??
            $multi_language_description_list->web_detail = $description;

            $edit_product_request->multi_language_description_list = $multi_language_description_list;

            $req->setEditProductRequest(json_encode($edit_product_request));

            try{
                $res = $this->tmallClient->execute($req, $this->sessionKey);
            }catch(\Exception $e){
                $this->log('error', 'updateProducts', 'Exception 1');
            }

            if($test) dd($res);


            if(isset($res->result->product_id))
            {
                $productsUpdated .= "sku: $Product->sku id: {$res->result->product_id} - success, ";
                $productsSuccess++;
            }else{
                $productsUpdated .= "sku: $Product->sku id: {$tmallProductId} - error, ";
                //var_dump("sku $Product->sku - error 2");
                //dump($res);
                $productsError++;
            }

            print_r($key.' of '.$productsCount."\r");
        }

        var_dump("Error: $productsError, success: $productsSuccess");

        $ExecutionTime = 'Script execution time '.round(microtime(true) - $start, 4).'sec';
        $comment = "Total: $productsCount, Product skipped: $productsSkipped, Products updated: $productsUpdated. $ExecutionTime";
        $this->log('info', 'updateProducts', $comment);
        print_r($comment);
    }

    public function formationModulesDescription($Product)
    {
        $description = '{
                "version":"2.0.0",
                "moduleList":
                    [
                        {
                            "type":"dynamic",
                            "reference":{"type":"custom","moduleId":'.$this->header_module_id.'}
                        },
        ';
        $description .= '
                        {
                            "html":{"content":"
                                <h1 style = \"text-align: center;\">Характеристики</h1>
                                <div>
                                '.$Product->temp_short_description.'
                                </div>

                                <table border=\"1\" style=\"box-sizing: border-box; overflow-wrap: break-word; word-break: normal; font-family: Arial, Helvetica, sans-serif; font-size: 18px; color: rgb(102, 102, 102); border: 1px solid rgb(231, 231, 231);\" width=\"100%\">
                                    <tbody style=\"box-sizing: border-box;\">
        ';

        if($Product->manufacturer)
        {
            $description .= '
                <tr style=\"box-sizing: border-box; height: 50px; border: 1px solid rgb(231, 231, 231);\">
                    <td data-spm-anchor-id=\"0.0.0.i3.61643e5ftWf9xa\" style=\"box-sizing: border-box; overflow-wrap: break-word; word-break: normal; overflow: visible; padding: 10px; border: 1px solid rgb(231, 231, 231); background: rgb(244, 244, 244); width: 324px; font-weight: bold;\">
                        Производитель
                    </td>
                    <td style=\"box-sizing: border-box; overflow-wrap: break-word; word-break: normal; overflow: visible; padding: 10px; border: 1px solid rgb(231, 231, 231);\">
                        '.$Product->manufacturer->name.'
                    </td>
                </tr>
            ';
        }

        if($Product->type)
        {
            $description .= '
                <tr style=\"box-sizing: border-box; height: 50px; border: 1px solid rgb(231, 231, 231);\">
                    <td data-spm-anchor-id=\"0.0.0.i3.61643e5ftWf9xa\" style=\"box-sizing: border-box; overflow-wrap: break-word; word-break: normal; overflow: visible; padding: 10px; border: 1px solid rgb(231, 231, 231); background: rgb(244, 244, 244); width: 324px; font-weight: bold;\">
                        Тип
                    </td>
                    <td style=\"box-sizing: border-box; overflow-wrap: break-word; word-break: normal; overflow: visible; padding: 10px; border: 1px solid rgb(231, 231, 231);\">
                        '.$Product->type->name.'
                    </td>
                </tr>
            ';
        }

        $description .= '
            <tr style=\"box-sizing: border-box; height: 50px; border: 1px solid rgb(231, 231, 231);\">
                <td data-spm-anchor-id=\"0.0.0.i3.61643e5ftWf9xa\" style=\"box-sizing: border-box; overflow-wrap: break-word; word-break: normal; overflow: visible; padding: 10px; border: 1px solid rgb(231, 231, 231); background: rgb(244, 244, 244); width: 324px; font-weight: bold;\">
                    Возрастная группа
                </td>
                <td style=\"box-sizing: border-box; overflow-wrap: break-word; word-break: normal; overflow: visible; padding: 10px; border: 1px solid rgb(231, 231, 231);\">
                    От 3 лет
                </td>
            </tr>
        ';

        if($Product->character)
        {
            $description .= '
                <tr style=\"box-sizing: border-box; height: 50px; border: 1px solid rgb(231, 231, 231);\">
                    <td data-spm-anchor-id=\"0.0.0.i3.61643e5ftWf9xa\" style=\"box-sizing: border-box; overflow-wrap: break-word; word-break: normal; overflow: visible; padding: 10px; border: 1px solid rgb(231, 231, 231); background: rgb(244, 244, 244); width: 324px; font-weight: bold;\">
                        Персонажи
                    </td>
                    <td style=\"box-sizing: border-box; overflow-wrap: break-word; word-break: normal; overflow: visible; padding: 10px; border: 1px solid rgb(231, 231, 231);\">
                        '.$Product->character->name.'
                    </td>
                </tr>
            ';
        }

        if($Product->height > 0)
        {
            $description .= '
                <tr style=\"box-sizing: border-box; height: 50px; border: 1px solid rgb(231, 231, 231);\">
                    <td data-spm-anchor-id=\"0.0.0.i3.61643e5ftWf9xa\" style=\"box-sizing: border-box; overflow-wrap: break-word; word-break: normal; overflow: visible; padding: 10px; border: 1px solid rgb(231, 231, 231); background: rgb(244, 244, 244); width: 324px; font-weight: bold;\">
                        Высота, см
                    </td>
                    <td style=\"box-sizing: border-box; overflow-wrap: break-word; word-break: normal; overflow: visible; padding: 10px; border: 1px solid rgb(231, 231, 231);\">
                        '.$Product->height.'
                    </td>
                </tr>
            ';
        }

        if($Product->producingCountry)
        {
            $description .= '
                <tr style=\"box-sizing: border-box; height: 50px; border: 1px solid rgb(231, 231, 231);\">
                    <td data-spm-anchor-id=\"0.0.0.i3.61643e5ftWf9xa\" style=\"box-sizing: border-box; overflow-wrap: break-word; word-break: normal; overflow: visible; padding: 10px; border: 1px solid rgb(231, 231, 231); background: rgb(244, 244, 244); width: 324px; font-weight: bold;\">
                        Страна производитель
                    </td>
                    <td style=\"box-sizing: border-box; overflow-wrap: break-word; word-break: normal; overflow: visible; padding: 10px; border: 1px solid rgb(231, 231, 231);\">
                        '.$Product->producingCountry->name.'
                    </td>
                </tr>
            ';
        }

        $description .= '
            <tr style=\"box-sizing: border-box; height: 50px; border: 1px solid rgb(231, 231, 231);\">
                <td data-spm-anchor-id=\"0.0.0.i3.61643e5ftWf9xa\" style=\"box-sizing: border-box; overflow-wrap: break-word; word-break: normal; overflow: visible; padding: 10px; border: 1px solid rgb(231, 231, 231); background: rgb(244, 244, 244); width: 324px; font-weight: bold;\">
                    Гарантия качества
                </td>
                <td style=\"box-sizing: border-box; overflow-wrap: break-word; word-break: normal; overflow: visible; padding: 10px; border: 1px solid rgb(231, 231, 231);\">
                    Оригинальный товар
                </td>
            </tr>
        ';

        if($Product->feature and ($Product->feature->id !== 0))
        {
            $description .= '
                <tr style=\"box-sizing: border-box; height: 50px; border: 1px solid rgb(231, 231, 231);\">
                    <td data-spm-anchor-id=\"0.0.0.i3.61643e5ftWf9xa\" style=\"box-sizing: border-box; overflow-wrap: break-word; word-break: normal; overflow: visible; padding: 10px; border: 1px solid rgb(231, 231, 231); background: rgb(244, 244, 244); width: 324px; font-weight: bold;\">
                        Особенности
                    </td>
                    <td style=\"box-sizing: border-box; overflow-wrap: break-word; word-break: normal; overflow: visible; padding: 10px; border: 1px solid rgb(231, 231, 231);\">
                        '.$Product->feature->name.'
                    </td>
                </tr>
            ';
        }

        $description .= '
                            </tbody>
                                </table>
        ';

        if($Product->alliexpress_images)
        {
            $images = explode(';', $Product->alliexpress_images);
            if(count($images) > 0)
            {
                $description .= '
                    <h1 style = \"text-align: center;\">Внешний вид товара</h1>
                    <table width=\"100%\"><tbody>';

                if(isset($images[0])) $description .= '<tr><td style = \"text-align: center;\"><img src=\"'.$images[0].'\"></td></tr>';
                if(isset($images[1])) $description .= '<tr><td style = \"text-align: center;\"><img src=\"'.$images[1].'\"></td></tr>';
                if(isset($images[2])) $description .= '<tr><td style = \"text-align: center;\"><img src=\"'.$images[2].'\"></td></tr>';

                $description .= '</tbody></table>';
            }
        }

        $description .= '
                            "},
                            "type":"html"
                        },
                        {
                            "type":"dynamic",
                            "reference":{"type":"custom","moduleId":'.$this->footer_module_id.'}
                        }
                    ]
            }
        ';

        $description = str_replace(PHP_EOL, "", $description);

        /*
        $description = '{"version":"2.0.0", "moduleList":[{"type":"dynamic","reference":{"type":"custom","moduleId":34616114}}';
        //$description .= ',{"html":{"content":"<p><span data-spm-anchor-id="0.0.0.i0.61643e5ftWf9xa" style="box-sizing: border-box; max-width: 100%; word-break: break-word; line-height: 42px; font-size: 28px; font-weight: 700;">ХАРАКТЕРИСТИКИ</span></p>"},"type":"html"}}';
        $description .= ',{"type":"dynamic","reference":{"type":"custom","moduleId":24657170}}]';
           */
        return $description;

    }

    public function getImages()
    {
        $start = microtime(true);
        $products = Product::whereHas('typeShopProducts', function ($q)
        {
            $q->where('type_shop_id', $this->shopId);
        })->get();

        $productsCount = count($products);
        $productsError = 0;
        $productsSuccess = 0;

        foreach($products as $key => $Product)
        {
            $tmallProductId = $Product->typeShopProducts->where('type_shop_id', $this->shopId)->first()->shop_product_id;

            $TmallProductInfo = $this->getProductInfo($tmallProductId);

            if(isset($TmallProductInfo->image_u_r_ls))
            {
                $Product->alliexpress_images = $TmallProductInfo->image_u_r_ls;
                if($Product->save())
                {
                    $productsSuccess++;
                }else{
                    $productsError++;
                };

            }

            print_r($key.' of '.$productsCount."\r");
        }

        $ExecutionTime = 'Script execution time '.round(microtime(true) - $start, 4).'sec';
        $this->log('info', 'getImages', "Total: $productsCount, error: $productsError, success: $productsSuccess. $ExecutionTime");
    }

    public function updatePriceAndQuantity($products = false) // not used now?
    {
        $start = microtime(true);
        $productsError = 0;
        $productsSuccess = 0;

        if(!$products)
        {
            $products = Product::whereHas('typeShopProducts', function ($q)
            {
                $q->where('type_shop_id', $this->shopId);
            })->get();
        }

        $productsCount = count($products);

        foreach($products as $key => $Product)
        {
            dump("$key of $productsCount");
            $req = new AliexpressSolutionProductEditRequest;
            $edit_product_request = new PostProductRequestDto;
            $edit_product_request->product_id = $Product->typeShopProducts->where('type_shop_id', $this->shopId)->first()->shop_product_id;

            $sku_info_list = new SkuInfoDto;

            $Stop = Products::getSystemsProductsStopResult($Product, $this->shopId);
            $sku_info_list->inventory = $Stop->stock?0:($Product->shopAmounts($this->shopId)->amounts->balance??0);

            if(!$Stop->price)
            {
                if($Product->temp_old_price and ($Product->temp_old_price > $Product->temp_price))
                {
                    $sku_info_list->price = Price::recalculatePriceByUnloadingOption($Product->temp_old_price, $this->shopId);
                    $sku_info_list->discount_price = Price::recalculatePriceByUnloadingOption($Product->temp_price, $this->shopId);
                }else{
                    $sku_info_list->price = Price::recalculatePriceByUnloadingOption($Product->temp_price, $this->shopId);
                }
            }else{
                $TmallProduct = $this->getProductInfo($Product->typeShopProducts->where('type_shop_id', $this->shopId)->first()->shop_product_id);
                if($TmallProduct)
                {
                    $sku_info_list->price = $TmallProduct->aeop_ae_product_s_k_us->global_aeop_ae_product_sku[0]->sku_price;
                    if($sku_discount_price = $TmallProduct->aeop_ae_product_s_k_us->global_aeop_ae_product_sku[0]->sku_discount_price??false)
                        $sku_info_list->discount_price = $sku_discount_price;
                }else{
                    continue;
                }
            }

            $sku_info_list->sku_code = $Product->sku;
            $edit_product_request->sku_info_list = $sku_info_list;

            $req->setEditProductRequest(json_encode($edit_product_request));
            $res = $this->tmallClient->execute($req, $this->sessionKey);
            if(isset($res->result->product_id))
            {
                $productsSuccess++;
            }else{
                $productsError++;
                $this->log('error', 'updatePriceAndQuantity', "Update error $Product->sku", $req, $res);
            }

            dump($res);
        }

        $ExecutionTime = 'Script execution time '.round(microtime(true) - $start, 4).'sec';
        $this->log('info', 'updatePriceAndQuantity', "Total: $productsCount, error: $productsError, success: $productsSuccess. $ExecutionTime");
    }

    public function productsSetOnline()
    {
        $start = microtime(true);
        $products = Product::whereHas('typeShopProducts', function ($q)
        {
            $q->where('type_shop_id', $this->shopId);
        })->get();

        $productsCount = count($products);
        $productsError = 0;
        $productsSuccess = 0;

        foreach($products as $key => $Product)
        {
            $tmallProductId = $Product->typeShopProducts->where('type_shop_id', $this->shopId)->first()->shop_product_id;

            $req = new AliexpressPostproductRedefiningOnlineaeproductRequest;
            $req->setProductIds($tmallProductId);
            $res = $this->tmallClient->execute($req, $this->sessionKey);

            if(isset($res->result->success) and ($res->result->success === true))
            {
                $productsSuccess++;
            }else{
                $productsError++;
            }

            print_r($key.' of '.$productsCount."\r");
        }

        $ExecutionTime = 'Script execution time '.round(microtime(true) - $start, 4).'sec';
        $this->log('info', 'productsSetOnline', "Total: $productsCount, error: $productsError, success: $productsSuccess. $ExecutionTime");
    }

    public function removeUnwantedProducts()
    {
        $products = $this->getAllProducts();
        $productsCount = count($products);
        $productsToRemove = [];
        foreach($products as $key => $TmallProduct)
        {
            if(!TypeShopProduct::where([['shop_product_id', $TmallProduct->product_id], ['type_shop_id',  $this->shopId]])->first())
            {
                $productsToRemove[] = $TmallProduct;
            }

            print_r($key.' of '.$productsCount."\r");
        }

        if(count($productsToRemove) > 0)
            $this->removeProducts($productsToRemove);
    }

    public function removeProducts($products, $perPage = 100): bool
    {
        $sending = 0;
        $ids = '';
        foreach($products as $key => $TmallProduct)
        {
            $ids .= $TmallProduct->product_id;
            $sending++;
            if($sending === $perPage or (count($products) - 1) === $key)
            {
                $req = new AliexpressSolutionBatchProductDeleteRequest;
                $req->setProductIds($ids);
                $res = $this->tmallClient->execute($req, $this->sessionKey);

                $this->log('info', 'removeProducts', 'Removed '.$sending, $ids, $res);

                $ids = '';
                $sending = 0;
            }else{
                $ids .= ',';
            }
        }

        return true;
    }

    public function getBrandNameByManufacturer($Product)
    {
        $brandName = 'Other';
        if(isset($Product->manufacturer))
        {
            $ShopBrandName = ShopBrandName::where([
                ['shop_id', $this->shopId],
                ['name', $Product->manufacturer->name],
            ])->first();
            if($ShopBrandName) $brandName = $ShopBrandName->name;
        }

        return $brandName;
    }

    public function saveBrandsNames()
    {

        $req = new AliexpressSolutionProductSchemaGetRequest;
        $req->setAliexpressCategoryId('2605');
        $res = $this->tmallClient->execute($req, $this->sessionKey);

        $brandList = json_decode($res->result->schema)->properties->category_attributes->properties->{'Brand Name'}->properties->value->oneOf;

        if(!empty($brandList))
        {
            ShopBrandName::where('shop_id', $this->shopId)
                ->update(['state' => 0]);

            foreach($brandList as $Brand)
            {
                if(isset($Brand->title) and isset($Brand->const))
                {
                    $ShopBrandName = ShopBrandName::firstOrNew([
                        'shop_id' => $this->shopId,
                        'name' => $Brand->title,
                        'system_brand_id' => $Brand->const,
                    ]);
                    $ShopBrandName->state = 1;
                    $ShopBrandName->save();
                }
            }
        }
    }

    public function testImagesUpdate()
    {
        $arrayIds = [];
        $imagesNames = scandir('/var/www/crmdollmagic.ru/public/images/temp/products-tmall');
        foreach($imagesNames as $imagesName)
        {
            $pos = mb_strpos($imagesName, '_1.jpg');
            if($pos === false) continue;

            $imagesName = str_replace('_1.jpg', '', $imagesName);
            $arrayIds[] = $imagesName;
        }

        $products = Product::whereHas('typeShopProducts', function ($q) use ($arrayIds)
        {
            $q->where('type_shop_id', 6);
            $q->whereIn('shop_product_id', $arrayIds);
        })->get();

        (new TmallApi())->updateProducts($products);
    }

    public function productsMatches()
    {
        $start = microtime(true);
        $tmallProducts = $this->getAllProducts();

        $updated = 0;
        $keyCode = uniqid();

        if(count($tmallProducts) > 0)
        {
            foreach($tmallProducts as $key => $TmallProduct)
            {
                if($ProductInfo = $this->getProductInfo($TmallProduct->product_id))
                {
                    $sku = $ProductInfo->aeop_ae_product_s_k_us->global_aeop_ae_product_sku[0]->sku_code??false;

                    if($sku and $Product = Products::getProductBy('sku', $sku))
                    {
                        $TypeShopProduct = TypeShopProduct::firstOrNew([
                            'type_shop_id' => $this->shopId,
                            'product_id' => $Product->id,
                        ]);
                        $TypeShopProduct->shop_product_id = $TmallProduct->product_id;
                        $TypeShopProduct->key_code = $keyCode;
                        $TypeShopProduct->save();
                        $updated++;
                    }else{
                        $this->log('error', 'productsMatches', "Unknown product sku $sku");
                    }
                }

                print_r($key.' of '.count($tmallProducts)."\r");
            }

            TypeShopProduct::where([
                ['type_shop_id', $this->shopId],
            ])->where(function($q) use ($keyCode)
            {
                $q
                    ->where('key_code', '!=', $keyCode)
                    ->orWhereNull('key_code');

            })->delete();
        }

        $ExecutionTime = 'Script execution time '.round(microtime(true) - $start, 4).'sec';
        $this->log('info', 'productsMatches', "Updated: $updated. $ExecutionTime");
    }

    public function imageUpload($ProductImage)
    {
        $data = base64_decode(Storage::disk('public')->get($ProductImage->LocalPath));
        $req = new AliexpressPhotobankRedefiningUploadimageforsdkRequest;
        $req->setGroupId("0");
        $req->setImageBytes($data);
        $req->setFileName($ProductImage->filename);
        $res = $this->tmallClient->execute($req, $this->sessionKey);
        //dd($res);

        dd('ok');
        //return $url;
    }

}
