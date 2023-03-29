<?php

namespace App\Services\InsalesAPI;

use App\Repository\InsalesAPI\InsalesAPIRepository;
use App\Services\Base\BaseModelService;
use Illuminate\Support\Facades\Http;
use DB;

class InsalesAPIService extends BaseModelService
{
    public $login = 'c99f12e7ce039a72b966b9255dcf8119';
    public $password = '377254a1c36eab4178af2d97ccbf9d32';
    public $host = 'https://dollmagic.myinsales.ru/admin/';

    /**
     * @see BaseModelService
     */
    protected static $repositoryClass = InsalesAPIRepository::class;


    public function test(): void
    {
        $this->repository->test();
    }

    /**
     * Колличество товара на площадке
     * 
     * @return int
     */
    public function getCountProductsByInsales(): int
    {
        $countProducts = Http::acceptJson()->withBasicAuth($this->login, $this->password)->get($this->host . 'products/count.json');
        return (int) $countProducts->json()['count'];
    }

    /**
     * Колличество товара в БД
     * 
     * @return int
     */
    public function getCountProductsByMysql(): int
    {
        $countProducts = DB::table('products')->where('archive', 0)->count();

        return (int) $countProducts;
    }

    public function getInfoProductsByInsales(): array
    {
        $infoProducts = Http::acceptJson()->withBasicAuth($this->login, $this->password)->get($this->host . 'products.json');
        return (array) $infoProducts->json();
    }

    public function getInfoProductByInsales($id): array
    {
        $infoProduct = Http::acceptJson()->withBasicAuth($this->login, $this->password)->get($this->host . 'products/' . $id . '.json');
        return (array) $infoProduct->json();
    }

    public function getInfoProductsByMysql()
    {
       $arrayIdWarehouses = $this->getIdWarehousesByInsales();

        $infoProducts = DB::table('products')
            ->select(
                'products.id',
                'products.sku',
                'shop_prices.price as pricePriority1',
                'shop_prices.old_price as oldPricePriority1',
                'products.temp_price as pricePriority3',
                'products.temp_old_price as oldPricePriority3',
                'type_shop_products.shop_product_id as idInsales',
                'warehouses_stocks.available',
                'warehouses_stocks.reserved',
            )
            ->where('products.archive', 0)
            ->where('type_shop_products.type_shop_id', 3)
            //->where('products.id', 2)
            ->whereIn('warehouses_stocks.warehouse_id', $arrayIdWarehouses)
            ->leftJoin('type_shop_products', 'products.id', '=', 'type_shop_products.product_id')
            ->leftJoin('shop_prices', 'products.id', '=', 'shop_prices.product_id')
            ->leftJoin('warehouses_stocks', 'products.id', '=', 'warehouses_stocks.product_id')
            ->limit(1)
            ->get();

        return  $infoProducts;
    }

    public function getIdWarehousesByInsales(): array
    {
        return (array) DB::table('type_shop_warehouses')->where('type_shop_id', 3)->pluck('warehouse_id')->toArray();
    }

    public function updateVariantProduct($idProduct, $idVariant, $infoProduct)
    {
        $response = Http::acceptJson()
            ->withBasicAuth($this->login, $this->password)
            ->put($this->host . 'products/' . $idProduct . '/variants/' . $idVariant .'.json', [
                'variant' => [
                    'quantity' => 0
                ],
    
            ]);
    }

    public function createVariantProduct($idProduct, $idVariant, $infoProduct, $productInfo)
    {
        $response = Http::acceptJson()
            ->withBasicAuth($this->login, $this->password)
            ->put($this->host . 'products/' . $idProduct . '/variants.json', [
                'variant' => [
                    "price" => 100,
                    "old_price" => 100,
                    "quantity" => 1,
                    "sku" => 1,
                    "barcode" => 1,
                ]
            ]);
    }

    public function updateVariantsProducts()
    {
        $response = Http::acceptJson()
            ->withBasicAuth($this->login, $this->password)
            ->put($this->host . 'products/variants_group_update.json', [
                'variants' => [
                    [
                        'id' => 90264467,
                        'quantity' => 0
                    ]
                ],
    
            ]);
        
        dd($response->json());
    }
}