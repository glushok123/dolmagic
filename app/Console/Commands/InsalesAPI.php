<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\InsalesAPI\InsalesAPIService;

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

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $service = InsalesAPIService::getInstance();

        $this->info('Старт синхронизации');
        $infoProductsByMysql = $service->getInfoProductsByMysql(); //информация о товарах в БД
        //$countProductsByInsales = $service->getCountProductsByInsales(); //кол товара на площадке
        //$countProductsByMysql = $service->getCountProductsByMysql(); //кол товара в БД

        //$this->info('Количество товара на площадке: ' . $countProductsByInsales);
        $countProductsByMysql = $infoProductsByMysql->count();
        $this->info('Количество товара в БД: ' . $countProductsByMysql);

        

        $progressBar = $this->output->createProgressBar($countProductsByMysql);
        $progressBar->start();

        $updateArrayVariants = []; // Максимум 100 вариантов для обновления

        foreach($infoProductsByMysql as $product) {
            $this->info('Артикул: ' . $product->sku);

            //$this->info('Количество товара в БД: ' . $countProductsByMysql);
            //$this->info('Количество товара в БД: ' . $countProductsByMysql);
            $productInsales = $service->getInfoProductByInsales($product->idInsales);

            dd($productInsales);

            if (array_key_exists('variants', $productInsales) == true) {
                dd($productInsales['variants']);
            }
            else{
                $service->createVariantProduct();
            }

            $progressBar->advance();
            //$infoCurentBySales[] = $service->getInfoProductByInsales($product->idInsales);
        }

        $progressBar->finish();
        return Command::SUCCESS;
    }
}
