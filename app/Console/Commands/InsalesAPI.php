<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\InsalesAPI\InsalesAPIService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use DB;

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

    /**
     * Получение товаров с Insales
     * 
     * @return void
     */
    public function getInfoProducts(): void
    {
        $service = InsalesAPIService::getInstance();

        $this->printLog('Старт получения информации о товарах');

        $countProductsByInsales = $service->getCountProductsByInsales();
        $countPage = ceil($countProductsByInsales/250);

        $this->printLog('Количество товара на площадке: ' . $countProductsByInsales);
        $this->printLog('Количество страниц: ' . $countPage);

        $countProductsBySeveralVariants = 0;
        $countProductsVariantsWithoutQuantity = 0;

        $progressBar = $this->output->createProgressBar($countProductsByInsales);
        $progressBar->start();

        $numberPageCurrent = 1;

        while ($numberPageCurrent <= $countPage) {
            $collectProducts = $service->getInfoProductsByInsales($numberPageCurrent);

            $this->printLog('Получено товаров: ' . count($collectProducts));

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

        $this->printLog('Количество товаров у которых несколько вариантов: ' . $countProductsBySeveralVariants);
        $this->printLog('Количество вариантов у которых отстутствует количество: ' . $countProductsVariantsWithoutQuantity);
        $this->printLog('Количество товаров которые дублируются: ' . count($this->arrayIdVariantsByRemove));
    }

    /**
     * Сохранение лога и вывод сообщения в консоль
     * 
     * @param string $message
     * 
     * @return void
     */
    public function printLog(string $message): void
    {
        $this->info($message);
        Log::info($message);
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', 1000);

        $service = InsalesAPIService::getInstance();

        $this->info('Старт синхронизации');
        $this->getInfoProducts();
        $countProductUpdate = 0;

        $progressBar = $this->output->createProgressBar(count($this->obj));
        $progressBar->start();

        foreach($this->obj as $productInsales) {
            foreach($productInsales['variants'] as $variant) {
                $productMysql = $service->getInfoProductByMysql($variant['sku']);
                if ($productMysql == null) { // если товара нет в БД, то убираем колличество
                    if ($variant['quantity'] != 0) {
                        $variantUpdateInfo = [
                            "quantity" => 0
                        ];
    
                        $service->updateVariantProduct($productInsales['product_id_insales'], $variant['id'], $variantUpdateInfo);
                        $countProductUpdate += 1;
                    }
                }

                if ($productMysql != null) { //Если товар есть в БД
                    $price = $productMysql->pricePriority1 == null ? $productMysql->pricePriority2 : $productMysql->pricePriority1;
                    $old_price = $productMysql->oldPricePriority1 == null ? $productMysql->oldPricePriority2 : $productMysql->oldPricePriority1;
                    $quantity =  $service->getQuantityProduct($productMysql->id);

                    if ($quantity < 0) {
                        $quantity = 0;
                    }

                    if ($price != $variant['price'] || $old_price != $variant['old_price'] || $quantity != $variant['quantity']) { // Проверяем разницу, если есть, то обновляем кол и цену
                        $variantUpdateInfo = [
                            "price" => $price,
                            "old_price" => $old_price,
                            "quantity" => $quantity
                        ];
    
                        $service->updateVariantProduct($productInsales['product_id_insales'], $variant['id'], $variantUpdateInfo);

                        DB::connection('tech')->table('history_insales_a_p_i_s')->insert([
                            'product_id_insales' => $productInsales['product_id_insales'],
                            'variants_id' => $variant['id'],
                            'sku' => $variant['sku'],

                            'current_price' => $price,
                            'current_old_price' => $old_price,
                            'current_quantity' => $quantity,

                            'past_price' => $variant['price'],
                            'past_old_price' => $variant['old_price'],
                            'past_quantity' => $variant['quantity'],

                            'created_at' => Carbon::now(),
                            'updated_at' => Carbon::now(),
                        ]);

                        $countProductUpdate += 1;
                    }
                }
            }

            $progressBar->advance();
        }

        $progressBar->finish();

        $this->printLog('Количество вариантов которые были обновлены: ' . $countProductUpdate);

        foreach ($this->arrayIdVariantsByRemove as $key => $value) { // удаляем дубликаты
            foreach ($value as $variant) {
                if ($variant['count_variants_product'] == 1) {
                    $service->deleteProduct($key);
                }else {
                    $service->deleteVariantProduct($key, $variant['variants_id']);
                }
            }
        }

        $this->printLog('Количество вариантов которые были удалены: ' . count($this->arrayIdVariantsByRemove));

        $productNeedToAdd = $service->getProductsNeedToAdd($this->arraySku);

        $this->printLog('Количество товаров которые нужно добавить: ' . $productNeedToAdd->count());

        foreach ($productNeedToAdd as $product) {
            /*
                TO DO

                Добавление товара на площадку Insales
            */
        }

        $this->printLog('Конец синхронизации');

        return Command::SUCCESS;
    }
}