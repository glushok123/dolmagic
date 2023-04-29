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
    protected $description = 'Command description';


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

        $this->info('Старт синхронизации sbermegamarket');

        $service = SbermegamarketAPIService::getInstance();
        $products = $service->getInfoProductsByMysql();

        $objPrice = [];
        $objQuantity = [];

        foreach ($products as $product) {
            $price = $service->getPriceProduct($product->id);
            $quantity = $service->getQuantityProduct($product->id);

            $objPrice[] = [
                "offerId" => $product->sku,
                "price" => $price,
                "isDeleted" => false
            ];

            $objQuantity[] = [
                "offerId" => $product->sku,
                "quantity" => $quantity
            ];

           /* if (count($objPrice) == 200) {
                //
                $objPrice = [];
            }

            if (count($objQuantity) == 200) {
                //
                $objQuantity = [];
            }
            */
        }

        dd(count($objPrice), count($objQuantity));
        //$service->updatePricesProducts();

        

        return Command::SUCCESS;
    }
}
