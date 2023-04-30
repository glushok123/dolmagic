<?php

namespace App\Services\SbermegamarketAPI;

use App\Repository\SbermegamarketAPI\SbermegamarketAPIRepository;
use App\Services\Base\BaseModelService;
use Illuminate\Support\Facades\Http;
use App\Eloquent\Order\OrdersTypeShop;
use App\Eloquent\Products\Product;
use DB;

class SbermegamarketAPIService extends BaseModelService
{
    public $token = '9670A80B-1F8E-4857-8589-D355E0A98FF7';
    public $hostPrices = 'https://partner.sbermegamarket.ru/api/merchantIntegration/v1/offerService/manualPrice/save';
    public $hostStocks = 'https://partner.sbermegamarket.ru/api/merchantIntegration/v1/offerService/stock/update';

    /**
     * @see BaseModelService
     */
    protected static $repositoryClass = SbermegamarketAPIRepository::class;


    public function test(): void
    {
        $this->repository->test();
    }

    /**
     * Информация о всех товарах
     */
    public function getInfoProductsByMysql()
    {
        $infoProducts = DB::table('products')
            ->select(
                'products.id',
                'products.sku',
                'shop_prices.price as pricePriority1',
                'shop_prices.old_price as oldPricePriority1',
                'products.temp_price as pricePriority2',
                'products.temp_old_price as oldPricePriority2',
            )
            ->where('products.archive', 0)
           // ->where('products.id', 5543)
            ->leftJoin('shop_prices', 'products.id', '=', 'shop_prices.product_id')
            ->limit(100)
            ->get();

        return  $infoProducts;
    }

    /**
     * Количество товара
     * 
     * @param int $productId
     * 
     * @return int
     */
    public function getQuantityProduct(int $productId): int
    {
        if (DB::table('warehouse_products_amounts')->where('warehouse_id', 2)->where('product_id', $productId)->exists() == true) {
            $obj = DB::table('warehouse_products_amounts')->where('warehouse_id', 2)->where('product_id', $productId)->first();

            $available = $obj->available;
            $reserved = $obj->reserved;
    
            return (int) $available - $reserved;
        }

        return 0;
    }

    /**
     * Цена товара
     * 
     * @param int $productId
     * 
     * @return int
     */
    public function getPriceProduct(int $productId): int
    {
        $product = Product::where('id', $productId)->first();
        $actualPrice = $product->actualPrice(5);

        $price =  $actualPrice->price;

        return $price;
    }

    /**
     * Информация о товаре по SKU
     * 
     * @param string $sku
     */
    public function getInfoProductByMysql(string $sku)
    {
        $infoProducts = DB::table('products')
            ->select(
                'products.id',
                'products.sku',
                'shop_prices.price as pricePriority1',
                'shop_prices.old_price as oldPricePriority1',
                'products.temp_price as pricePriority2',
                'products.temp_old_price as oldPricePriority2',
                'type_shop_products.shop_product_id as idInsales',
            )
            ->where('products.archive', 0)
            ->where('type_shop_products.type_shop_id', 3)
            ->where('products.sku', $sku)
            ->leftJoin('type_shop_products', 'products.id', '=', 'type_shop_products.product_id')
            ->leftJoin('shop_prices', function ($join) {
                $join->on('products.id', '=', 'shop_prices.product_id')
                    ->where('shop_prices.shop_id','=', 3);
            })
            ->first();

        return  $infoProducts;
    }

    /**
     * Обновление цен
     * 
     * @param array $productsPrice
     * 
     * @return array
     */
    public function updatePricesProducts(array $productsPrice): array
    {
        $response = Http::acceptJson()
            ->post($this->hostPrices, [
                "meta" => [],
                "data" => [
                    "token" => $this->token,
                    "prices" => $productsPrice
                ]
            ]);

        return $response->json();
    }

    /**
     * Обновление колличества
     * 
     * @param array $productsQuantity
     * 
     * @return array
     */
    public function updateStocksProducts(array $productsQuantity): array
    {
        $response = Http::acceptJson()
            ->post($this->hostStocks, [
                "meta" => [],
                "data" => [
                    "token" => $this->token,
                    "stocks" => $productsQuantity
                ]
            ]);

        return $response->json();
    }
}