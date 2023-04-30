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

        $this->printLog('Старт синхронизации sbermegamarket');

        $service = SbermegamarketAPIService::getInstance();
        $products = $service->getInfoProductsByMysql();

        $progressBar = $this->output->createProgressBar($products->count());
        $progressBar->start();

        foreach ($products as $product) {
            $price = $service->getPriceProduct($product->id);
            $quantity = $service->getQuantityProduct($product->id);

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
                foreach ($this->objPrice as $key => $value) {
                    if (array_key_exists('offerId', $value) == false) {
                        unset($this->objPrice[$key]);
                    }

                    if (array_key_exists('price', $value) == false) {
                        unset($this->objPrice[$key]);
                    }
                }

                $response = $service->updatePricesProducts($this->objPrice);

                if ($response['data']['warnings'] == []) {
                    $this->printLog("sbermegamarket Обновлено цен" . $response['data']['savedPrices']);
                }else{
                    Log::error("sbermegamarket ОШИБКА ОБНОВЛЕНИЯ !!! цен !!!");
                    Log::error(print_r($response, true));
                }

                $this->objPrice = [];
            }

            /*if (count($this->objQuantity) == $this->countProductForRequest) {
                foreach ($this->objQuantity as $key => $value) {
                    if (array_key_exists('offerId', $value) == false) {
                        unset($this->objQuantity[$key]);
                    }

                    if (array_key_exists('quantity', $value) == false) {
                        unset($this->objQuantity[$key]);
                    }
                }

                $response = $service->updateStocksProducts($this->objQuantity);

                if ($response['error'] == []) {
                    $this->printLog("sbermegamarket Обновлено остатков");
                }else{
                    Log::error("sbermegamarket ОШИБКА ОБНОВЛЕНИЯ !!! остатков !!!");
                    Log::error(print_r($response, true));
                }

                $this->objQuantity = [];
            }*/

            $progressBar->advance();
        }

        $progressBar->finish();

        $this->printLog('Конец синхронизации sbermegamarket');

        return Command::SUCCESS;
    }
}