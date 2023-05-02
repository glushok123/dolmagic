<?php

namespace App\Console\Api;

use App\Models\Products;
use Carbon\Carbon;

class InsalesApi extends Api
{
    public $systemId = 1; // Insales = 1
    public $shopId = 3;

    public $token = 'c99f12e7ce039a72b966b9255dcf8119'; // Your authorization token
    public $pass = '377254a1c36eab4178af2d97ccbf9d32'; // Password
    public $host = 'dollmagic.myinsales.ru'; // Host
    public $timezone = 'Europe/Moscow';

    public function __construct()
    {
        parent::__construct();

        $this->headers = array(
            'Content-Type: application/json; charset=utf-8'
        );
        $this->host = "http://$this->token:$this->pass@$this->host";
    }

    public function getOrdersStack($date_from, $orders = array(), $page = 1, $cycle = 0)
    {
        $newOrders = $this->makeRequest(
            'GET',
            '/admin/orders.json?page='.$page.'&updated_since='.urlencode($date_from.' +03:00').'&per_page=100'
        );

        if(empty($newOrders)){ // if not taken
            sleep(1);
            return $this->getOrdersStack($date_from, $orders, $page, $cycle);
        };

        $orders = array_merge($orders, $newOrders);

        if(count($newOrders) == 100){
            print_r('Цикл: '.$cycle. PHP_EOL);
            if($cycle > 15){
                $this->log('error', 'getOrdersStack', "cycle > 15");
                die();
            }
            return $this->getOrdersStack($date_from, $orders, $page+1, $cycle+1);
        }else{
            return $orders;
        }
    }

    public function cmp($a, $b)
    {
        $as = $a->created_at;
        $bs = $b->created_at;
        if(strtotime($as) > strtotime($bs)){
            return 1;
        }else{
            return -1;
        }
    }

    private function ordersSort($orders)
    {
        usort($orders, array($this, "cmp"));
        return $orders;
    }

    public function getOrdersList($date_from = false)
    {
        $orders = $this->getOrdersStack($date_from);
        if(count($orders) > 1) $orders = $this->ordersSort($orders);
        return $orders;
    }

    public function getOrder($systemOrderId, $cycle = 0, $showResponse = false)
    {
        $res = $this->makeRequest(
            'GET',
            "/admin/orders/$systemOrderId.json",
            NULL,
            $showResponse
        );
        if(empty($res)){ // if not taken
            if($cycle > 2){
                $this->log('error', 'getOrder', "cycle = $cycle ($systemOrderId)");
                die();
            };
            sleep(1);
            $this->log('warning', 'getOrder', "cycle = $cycle ($systemOrderId)");
            return $this->getOrder($systemOrderId, $cycle + 1, true);
        };
        return $res;
    }

    public function updateOrder($systemOrderId, $req): bool
    {
        $res = $this->makeRequest(
            'PUT',
            "/admin/orders/$systemOrderId.json",
            $req
        );
        return ($res and isset($res->id));
    }

    // Take all products from the Insales system
    public function getNewProducts($insalesProducts = false)
    {
        if(!$insalesProducts) $insalesProducts = $this->getProducts();

        if(count($insalesProducts) > 0){
            $countProcessed = Products::createProducts($insalesProducts, true, false);
            $this->log('info', 'getNewProducts', "Processed $countProcessed products");
        }else{
            $this->log('info', 'getNewProducts', 'Nothing to found');
        }
    }

    public function getOnlyNewProducts() // new function for updating only updated products: not all
    {
        $updateSince = Carbon::now()->setTimezone('Europe/Moscow')
            ->subHour()
            ->subMinutes(30)
            ->toDateTimeString();
        $updateSince .= ' +03:00';

        $updateSince = urlencode($updateSince);
        $insalesProducts = $this->getProducts(NULL, $updateSince);

        if(count($insalesProducts) > 0)
        {
            $countProcessed = Products::createProducts($insalesProducts, true, true);
            $this->log('info', 'getOnlyNewProducts', "Processed $countProcessed products");
        }else{
            $this->log('info', 'getOnlyNewProducts', 'Nothing found');
        }
    }

    //Get all products from the system
    public function getProducts($perPage = 250, $updatedSince = false)
    {
        $insalesProducts = array();

        $page = 1;
        $stop = false;
        $stopper = 0;

        while(!$stop)
        {
            $event = "/admin/products.json?page=$page&per_page=$perPage";
            if($updatedSince)  $event .= "&updated_since=$updatedSince";

            $products = $this->makeRequest(
                'GET',
                $event
            );

            $page++;

            if($products){
                if(count($products) > 0)
                {
                    $insalesProducts = array_merge($insalesProducts, $products);
                    dump("got ".count($insalesProducts));
                }
                if(count($products) < $perPage) $stop = true;
            }else{
                $this->log('error', 'getProducts', "GET $event", NULL, $products);
                $stop = true;
            };

            $stopper++;
            if($stopper == 50) $stop = true;
        };

        return $insalesProducts;
    }

    public function getProductById($id)
    {
        $event = "/admin/products/$id.json";
        $InsalesProduct = $this->makeRequest(
            'GET',
            $event
        );

        if(!$InsalesProduct){
            $this->log('error', 'getProductById', "GET $event", NULL, $InsalesProduct);
        };

        return $InsalesProduct;
    }

    public function updateVariant($shopProductId, $variantId, $VariantTemplate)
    {
        $event = "/admin/products/$shopProductId/variants/$variantId.json";
        $res = $this->makeRequest(
            'PUT',
            $event,
            $VariantTemplate
        );

        if(isset($res->id))
        {
            return $res;
        }else
        {
            return false;
        }
    }

    public function getFirstVariant($shopProductId)
    {
        return $this->getVariants($shopProductId)[0]??false;
    }

    public function getVariants($shopProductId)
    {
        $event = "/admin/products/$shopProductId/variants.json";
        $insalesVariants = $this->makeRequest(
            'GET',
            $event
        );

        return $insalesVariants;
    }

    public function getVariantById($id)
    {
        $event = "/admin/products/$id/variants/1.json";
        $InsalesVariant = $this->makeRequest(
            'GET',
            $event
        );

        if(!$InsalesVariant){
            $this->log('error', 'getVariantById', "GET $event", NULL, $InsalesVariant);
        };

        return $InsalesVariant;
    }

    //Get one category from Insales
    public function getCategory($categoryId)
    {
        $event = "/admin/categories/$categoryId.json";
        $category = $this->makeRequest(
            'GET',
            $event
        );

        if(!$category){
            $this->log('error', 'getCategory', "GET $event", NULL, $category);
        };

        return $category;
    }

    //Get one collection from Insales by Id
    public function getCollection($collectionId)
    {
        $event = "/admin/collections/$collectionId.json";
        $Collection = $this->makeRequest(
            'GET',
            $event
        );

        if(isset($Collection->id)){
            return $Collection;
        }else{
            $this->log('error', 'getCollection', "GET $event", NULL, $Collection);
            return $this->getCollection($collectionId);
        }
    }

    // // POST /admin/collects.json
    public function addProductCollection($shopProductId, $collectionId)
    {
        $event = "/admin/collects.json";
        $res = $this->makeRequest(
            'POST',
            $event,
            ['collect' =>
                [
                    'product_id' => $shopProductId,
                    'collection_id' => $collectionId,
                ]
            ]
        );

        if(isset($res->id))
        {
            return $res;
        }else
        {
            return false;
        }
    }
    public function updateProduct($shopProductId, $reqProduct)
    {
        $event = "/admin/products/$shopProductId.json";
        $res = $this->makeRequest(
            'PUT',
            $event,
            ['product' => $reqProduct]
        );

        if(isset($res->id))
        {
            return $res;
        }else
        {
            return false;
        }
    }

    public function createProduct($reqProduct)
    {
        $event = "/admin/products.json";
        $res = $this->makeRequest(
            'POST',
            $event,
            ['product' => $reqProduct]
        );

        dump($res);

        if(isset($res->id))
        {
            return $res;
        }else
        {
            return false;
        }
    }

    public function getCategories()
    {
        $event = "/admin/categories.json";
        $res = $this->makeRequest(
            'GET',
            $event
        );

        dd($res);

        return $res;
    }

    public function getProductImages($shopProductId)
    {
        $event = "/admin/products/$shopProductId/images.json";
        $res = $this->makeRequest(
            'GET',
            $event
        );
        return $res;
    }

    public function deleteProductImages($shopProductId, $shopImageId)
    {
        $event = "/admin/products/$shopProductId/images/$shopImageId.json";
        $res = $this->makeRequest(
            'DELETE',
            $event
        );
        return $res;
    }

    public function createImageFromSrc($shopProductId, $Image)
    {
        $event = "/admin/products/$shopProductId/images.json";
        $res = $this->makeRequest(
            'POST',
            $event,
            [
                'src' => $Image->Url,
                'external_id' => $Image->id
            ]
        );
    }

    public function deleteProduct($shopProductId)
    {
        $event = "/admin/products/$shopProductId.json";
        $res = $this->makeRequest(
            'DELETE',
            $event
        );
        dump($res);
        return $res;
    }

}

