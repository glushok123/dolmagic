<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\InsalesAPI\InsalesAPIService;
use App\Models\InsalesInfoVariantsProduct;
use App\Models\InsalesInfoProduct;

class InsalesGetInfoProduct extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'insales:gitInfoProduct';

    public $obj = [];

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

        $this->info('Старт получения информации о товарах');

        $countProductsByInsales = $service->getCountProductsByInsales() - 3000;
        $countPage = round($countProductsByInsales/250);

        $this->info('Количество товара на площадке: ' . $countProductsByInsales);
        $this->info('Количество страниц: ' . $countPage);
        
        $countProductsBySeveralVariants = 0;
        $countProductsVariantsWithoutQuantity = 0;

        $progressBar = $this->output->createProgressBar($countProductsByInsales);
        $progressBar->start();
        
        $numberPageCurrent = 1;

        $double = [];
        $countDuble = [];
        

        while ($numberPageCurrent != $countPage) {
            $this->obj = [];
            $collectProducts = $service->getInfoProductsByInsales($numberPageCurrent);

            foreach ($collectProducts as $product) {
                $this->obj[$product['id']]['product_id_insales'] = $product['id'];
                $this->obj[$product['id']]['category_id'] = $product['category_id'];
                $this->obj[$product['id']]['title'] = $product['title'];
                $this->obj[$product['id']]['short_description'] = $product['short_description'];
                $this->obj[$product['id']]['count_variants'] = count($product['variants']);

                if( count($product['variants']) > 1) {
                    $countProductsBySeveralVariants += 1;
                }

                foreach ($product['variants'] as $key => $value) {
                    $double[$product['variants'][$key]['sku']][] = $product['variants'][$key]['id'];

                    if($product['variants'][$key]['quantity'] < 1) {
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

            /*foreach ($double as $key => $value) {
                if (count($value) > 1 && in_array($key, $countDuble) == false) {
                    $this->error('Дубль: ' . $key);
                    $countDuble[] = $key;
                }
            }*/
            //dd($double);
            //dd($obj);
            $numberPageCurrent += 1;
            //dd($collectProducts);
        }

        $this->info('Количество товаров у которых несколько вариантов: ' . $countProductsBySeveralVariants);
        $this->info('Количество вариантов у которых отстутствует количество: ' . $countProductsVariantsWithoutQuantity);
       // dd($countDuble);
        //dd($double);
        return Command::SUCCESS;
    }
}
