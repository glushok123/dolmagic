<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreInsalesAPIRequest;
use App\Http\Requests\UpdateInsalesAPIRequest;
use App\Models\InsalesAPI;
use App\Services\InsalesAPI\InsalesAPIService;

class InsalesAPIController extends Controller
{

    public function show()
    {
        $service = InsalesAPIService::getInstance();
       // $service->updateVariantProduct();
       //$service->updateVariantsProducts();
        $countProductsByInsales = $service->getCountProductsByInsales(); //кол товара на площадке
        $countProductsByMysql = $service->getCountProductsByMysql(); //кол товара в БД

        $infoProductsByMysql = $service->getInfoProductsByMysql(); //информация о товарах в БД

        echo($countProductsByInsales . '<br>');
        echo($countProductsByMysql . '<br>');
        //dd($infoProductsByMysql);
        $infoCurentBySales = [];

        foreach($infoProductsByMysql as $product) {
            $productInsales = $service->getInfoProductByInsales($product->idInsales);
            dd($productInsales);
            //$infoCurentBySales[] = $service->getInfoProductByInsales($product->idInsales);
        }
        //dd($infoCurentBySales);
    }
}
