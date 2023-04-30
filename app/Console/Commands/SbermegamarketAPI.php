<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\SbermegamarketAPI\SbermegamarketAPIService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use DB;

class SbermegamarketAPI extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sbermegamarket:synchronization';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Синхронизация цен и количества с сбермегамаркет';

    public $objPrice = [];
    public $objQuantity = [];
    public $countProductForRequest = 200;
    public $service;

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
     * Обновление цен
     * 
     * @return void
     */
    public function updatePriceProducts(): void
    {
        foreach ($this->objPrice as $key => $value) {
            if (array_key_exists('offerId', $value) == false) {
                unset($this->objPrice[$key]);
            }

            if (array_key_exists('price', $value) == false) {
                unset($this->objPrice[$key]);
            }
        }

        $response = $this->service->updatePricesProducts($this->objPrice);

        if (array_key_exists('data', $response) && array_key_exists('savedPrices', $response['data'])) {
            $this->printLog("sbermegamarket Обновлено цен -> " . $response['data']['savedPrices']);
        }

        if (array_key_exists('data', $response) && array_key_exists('warnings', $response['data']) && $response['data']['warnings'] != []) {
            Log::warning(print_r($response, true));
            Log::warning("sbermegamarket ПРЕДУПРЕЖДЕНИЕ ОБНОВЛЕНИЯ !!! цен !!!");
        }
    }

    /**
     * Обновление остатков
     * 
     * @return void
     */
    public function updateStocksProducts(): void
    {
        foreach ($this->objQuantity as $key => $value) {
            if (array_key_exists('offerId', $value) == false) {
                unset($this->objQuantity[$key]);
            }

            if (array_key_exists('quantity', $value) == false) {
                unset($this->objQuantity[$key]);
            }
        }

        $response = $this->service->updateStocksProducts($this->objQuantity);

        if ($response['error'] == []) {
            $this->printLog("sbermegamarket Обновлено остатков");
        }else{
            Log::error("sbermegamarket ОШИБКА ОБНОВЛЕНИЯ !!! остатков !!!");
            Log::error(print_r($response, true));
        }
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

        $this->printLog('Старт синхронизации sbermegamarket');

        $this->service = SbermegamarketAPIService::getInstance();
        $products = $this->service->getInfoProductsByMysql();

        $progressBar = $this->output->createProgressBar($products->count());
        $progressBar->start();

        foreach ($products as $product) {
            $price = $this->service->getPriceProduct($product->id);
            $quantity = $this->service->getQuantityProduct($product->id);

            $this->objPrice[] = [
                "offerId" => $product->sku,
                "price" => $price,
                "isDeleted" => false
            ];

            $this->objQuantity[] = [
                "offerId" => $product->sku,
                "quantity" => (int) $quantity
            ];

            if (count($this->objPrice) == $this->countProductForRequest) {
                $this->updatePriceProducts();
                $this->objPrice = [];
            }

            if (count($this->objQuantity) == $this->countProductForRequest) {
                //$this->updateStocksProducts();
                $this->objQuantity = [];
            }

            $progressBar->advance();
        }

        $this->updatePriceProducts();
        //$this->updateStocksProducts();

        $progressBar->finish();

        $this->printLog('Конец синхронизации sbermegamarket');

        return Command::SUCCESS;
    }
}