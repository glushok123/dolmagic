<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\InsalesAPI\InsalesAPIService;
use Illuminate\Support\Facades\Log;

class InsalesAPI extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'insales:synchronization';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    public $obj = [];
    public $arraySku = [];
    public $arrayCheckDuplicate = [];
    public $countDuble = [];
    public $arrayIdProductsByRemove = [];
    public $arrayIdVariantsByRemove = [];

    public function getInfoProducts()
    {
        $service = InsalesAPIService::getInstance();

        $this->info('Старт получения информации о товарах');
        Log::info('Старт получения информации о товарах');

        $countProductsByInsales = $service->getCountProductsByInsales();
        $countPage = ceil($countProductsByInsales/250);

        $this->info('Количество товара на площадке: ' . $countProductsByInsales);
        $this->info('Количество страниц: ' . $countPage);

        Log::info('Количество товара на площадке: ' . $countProductsByInsales);
        Log::info('Количество страниц: ' . $countPage);
        
        $countProductsBySeveralVariants = 0;
        $countProductsVariantsWithoutQuantity = 0;

        $progressBar = $this->output->createProgressBar($countProductsByInsales);
        $progressBar->start();

        $numberPageCurrent = 1;

        while ($numberPageCurrent <= $countPage) {
            $collectProducts = $service->getInfoProductsByInsales($numberPageCurrent);

            foreach ($collectProducts as $product) {
                $this->obj[$product['id']]['product_id_insales'] = $product['id'];
                $this->obj[$product['id']]['category_id'] = $product['category_id'];
                $this->obj[$product['id']]['title'] = $product['title'];
                $this->obj[$product['id']]['short_description'] = $product['short_description'];
                $this->obj[$product['id']]['count_variants'] = count($product['variants']);

                if (count($product['variants']) > 1) {
                    $countProductsBySeveralVariants += 1;
                }

                foreach ($product['variants'] as $key => $value) {

                    $this->arrayCheckDuplicate[$product['variants'][$key]['sku']][] = [
                        'variants_id' => $product['variants'][$key]['id'],
                        'product_id_insales' => $product['id']
                    ];

                    if (in_array($product['variants'][$key]['sku'], $this->arraySku) == false) {
                        $this->arraySku[] = $product['variants'][$key]['sku'];
                    }
                    else{
                        $this->arrayIdVariantsByRemove[$product['id']][] = [
                            'sku' => $product['variants'][$key]['sku'],
                            'variants_id' => $product['variants'][$key]['id'],
                            'count_variants_product' => count($product['variants'])
                        ];
                    }

                    if ($product['variants'][$key]['quantity'] < 1) {
                        $countProductsVariantsWithoutQuantity += 1;
                    }

                    $this->obj[$product['id']]['variants'][$key] = [
                        'id' => $product['variants'][$key]['id'],
                        'sku' => $product['variants'][$key]['sku'],
                        'product_id' => $product['variants'][$key]['product_id'],
                        'price' => $product['variants'][$key]['price'],
                        'old_price' => $product['variants'][$key]['old_price'],
                        'quantity' => $product['variants'][$key]['quantity'],
                    ];
                }

                $progressBar->advance();
            }

            $numberPageCurrent += 1;
        }
        $progressBar->finish();

        $this->info('Количество товаров у которых несколько вариантов: ' . $countProductsBySeveralVariants);
        $this->info('Количество вариантов у которых отстутствует количество: ' . $countProductsVariantsWithoutQuantity);
        $this->info('Количество товаров которые дублируются: ' . count($this->arrayIdVariantsByRemove));

        Log::info('Количество товаров у которых несколько вариантов: ' . $countProductsBySeveralVariants);
        Log::info('Количество вариантов у которых отстутствует количество: ' . $countProductsVariantsWithoutQuantity);
        Log::info('Количество товаров которые дублируются: ' . count($this->arrayIdVariantsByRemove));

    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        ini_set('memory_limit', '1024M');
        $service = InsalesAPIService::getInstance();

        $this->info('Старт синхронизации');
        Log::info('Старт синхронизации');
        $this->getInfoProducts();

        $countProductsByInsales = count($this->obj);
        $progressBar = $this->output->createProgressBar($countProductsByInsales);
        $progressBar->start();

        $countProductUpdate = 0;

        foreach($this->obj as $productInsales) {
            foreach($productInsales['variants'] as $variant) {
                $productMysql = $service->getInfoProductByMysql($variant['sku']);
                if ($productMysql == null) {
                    if ($variant['quantity'] != 0) {
                        $variantUpdateInfo = [
                            "quantity" => 0
                        ];
    
                        $service->updateVariantProduct($productInsales['product_id_insales'], $variant['id'], $variantUpdateInfo);
                        $countProductUpdate += 1;
                    }
                }

                if ($productMysql != null) {
                    $price = $productMysql->pricePriority1 == null ? $productMysql->pricePriority2 : $productMysql->pricePriority1;
                    $old_price = $productMysql->oldPricePriority1 == null ? $productMysql->oldPricePriority2 : $productMysql->oldPricePriority1;
                    $quantity =  $service->getQuantityProduct($productMysql->id);

                    if ($quantity < 0) {
                        $quantity = 0;
                    }

                    if ($price != $variant['price'] || $old_price != $variant['old_price'] || $quantity != $variant['quantity']) {
                        $variantUpdateInfo = [
                            "price" => $price,
                            "old_price" => $old_price,
                            "quantity" => $quantity
                        ];
    
                        $service->updateVariantProduct($productInsales['product_id_insales'], $variant['id'], $variantUpdateInfo);
                        $countProductUpdate += 1;
                    }
                }
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->info('Количество вариантов которые были обновлены: ' . $countProductUpdate);
        Log::info('Количество вариантов которые были обновлены: ' . $countProductUpdate);

        foreach ($this->arrayIdVariantsByRemove as $key => $value) {
            $service->deleteVariantProduct($key, $value['variants_id']);
        }

        $this->info('Количество вариантов которые были удалены: ' . count($this->arrayIdVariantsByRemove));
        Log::info('Количество вариантов которые были удалены: ' . count($this->arrayIdVariantsByRemove));


        $productNeedToAdd = $service->getProductsNeedToAdd($this->arraySku);

        $this->info('Количество товаров которые нужно добавить: ' . $productNeedToAdd->count());
        Log::info('Количество товаров которые нужно добавить: ' . $productNeedToAdd->count());

        $this->info('Конец синхронизации');
        Log::info('Конец синхронизации');

        return Command::SUCCESS;
    }
}
