<?php

namespace App\Http\Controllers;

use App\StatisticsOrder;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\Statistics\StatisticsService;
use App\Services\Statistics\StatisticsProductsService;
use App\Eloquent\Order\OrdersTypeShop;
use DB;

class StatisticsOrderController extends Controller
{
    /**
     * Главная страница статистики
     */
    public function show()
    {
        $shops = OrdersTypeShop::select('id', 'name')
            ->where('filter_order', '!=', 0)
            ->orderBy('filter_order')
            ->get();

        $warehouses = DB::table('warehouses')
            ->select('id', 'name')
            ->get();

        return view('statistics.index')->with([
            'shops' => $shops,
            'warehouses' => $warehouses,
        ]);
    }

    /**
     * статистика по продажам + возвраты
     * 
     * @param Request $request
     * 
     * @return JsonResponse
     */
    public function getInfoStaticsOrder(Request $request): JsonResponse
    {
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', 1000);
        $service = StatisticsService::getInstance();

        $responseArray = [];

        if($request->union == 'true') {
            $service->setUnionTrue();
            $service->setProperties(
                $request->step, 
                $request->shopId, 
                $request->unit, 
                $request->dateStartSales, 
                $request->dateEndSales,
                $request->type
            );
            $responseArray['объединенная'] = $service->getInfoStaticsOrder();
        }else{
            foreach ($request->shopId as $item) {
                $service = StatisticsService::getInstance();
                $service->setProperties(
                    $request->step, 
                    $item, 
                    $request->unit, 
                    $request->dateStartSales, 
                    $request->dateEndSales,
                    $request->type
                );

                if ($item != 'all') {
                    $itemName = DB::table('orders_type_shops')->where('id', $item)->first()->name;
                    $responseArray[$itemName] = $service->getInfoStaticsOrder();
                    continue;
                }

                $responseArray[$item] = $service->getInfoStaticsOrder();
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => $responseArray,
        ]);
    }

    /**
     * статистика по товарам
     * 
     * @param Request $request
     * 
     * @return JsonResponse
     */
    public function getInfoStaticsProduct(Request $request): JsonResponse
    {
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', 1000);
        $service = StatisticsProductsService::getInstance();

        $responseArray = [];

        if($request->union == 'true') {
            $service->setUnionTrue();
            $service->setProperties(
                $request->step, 
                $request->warehousesId, 
                $request->unit, 
                $request->dateStart, 
                $request->dateEnd
            );
            $responseArray['объединенная'] = $service->getInfoStaticsProducts();
        }else{
            foreach ($request->warehousesId as $item) {
                $service = StatisticsProductsService::getInstance();

                $service->setProperties(
                    $request->step, 
                    $item, 
                    $request->unit, 
                    $request->dateStart, 
                    $request->dateEnd
                );

                if ($item != 'all') {
                    $itemName = DB::table('warehouses')->where('id', $item)->first()->name;
                    $responseArray[$itemName] = $service->getInfoStaticsProducts();
                    continue;
                }

                $responseArray[$item] = $service->getInfoStaticsProducts();
            }
        }

        return response()->json([
            'status' => 'success',
            'data' => $responseArray,
        ]);
    }
}