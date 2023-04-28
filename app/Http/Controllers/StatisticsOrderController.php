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
        if (backpack_auth()->check() == false) {
            return redirect(url('admin/login'));
        }else {
            return redirect(url('admin/statistics'));
        }

        /*
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
        */
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

        if ($request->has('percentages') && $request->percentages == "true") {
            $service->setRefundsPercentagesTrue();
        }

        foreach ($request->shopId as $item) {
            $service = StatisticsService::getInstance();

            if ($request->has('percentages') && $request->percentages == "true") {
                $service->setRefundsPercentagesTrue();
            }

            $service->setProperties(
                $request->step, 
                $item, 
                $request->unit, 
                $request->dateStartSales, 
                $request->dateEndSales,
                $request->type,
                $request->checkedSp,
                $request->checkedSelfPurchase,
                $request->checkedStatusCancel,
                $request->article,
            );

            if ($item != 'all') {
                $itemName = DB::table('orders_type_shops')->where('id', $item)->first()->name;
                $responseArray[$itemName] = $service->getInfoStaticsOrder();
                continue;
            }

            $responseArray[$item] = $service->getInfoStaticsOrder();
        }

        if($request->union == 'true') {
            $service = StatisticsService::getInstance();
            $service->setUnionTrue();

            if ($request->has('percentages') && $request->percentages == "true") {
                $service->setRefundsPercentagesTrue();
            }

            $service->setProperties(
                $request->step, 
                $request->shopId, 
                $request->unit, 
                $request->dateStartSales, 
                $request->dateEndSales,
                $request->type,
                $request->checkedSp,
                $request->checkedSelfPurchase,
                $request->checkedStatusCancel,
                $request->article,
            );
            $responseArray['объединенная'] = $service->getInfoStaticsOrder();
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

        foreach ($request->warehousesId as $item) {
            $service = StatisticsProductsService::getInstance();

            $service->setProperties(
                $request->step, 
                $item, 
                $request->unit, 
                $request->dateStart, 
                $request->dateEnd,
                $request->article,
            );

            if ($item != 'all') {
                $itemName = DB::table('warehouses')->where('id', $item)->first()->name;
                $responseArray[$itemName] = $service->getInfoStaticsProducts();
                continue;
            }

            $responseArray[$item] = $service->getInfoStaticsProducts();
        }

        if($request->union == 'true') {
            $service = StatisticsProductsService::getInstance();
            $service->setUnionTrue();
            $service->setProperties(
                $request->step, 
                $request->warehousesId, 
                $request->unit, 
                $request->dateStart, 
                $request->dateEnd,
                $request->article,
            );
            $responseArray['объединенная'] = $service->getInfoStaticsProducts();
        }

        return response()->json([
            'status' => 'success',
            'data' => $responseArray,
        ]);
    }

    /**
     * Информация о продажах для таблицы, при клике на график
     * 
     * @param Request $request
     * 
     * @return JsonResponse
     */
    public function getInfoStaticsSalesByDateForTable(Request $request): JsonResponse
    {
        ini_set('memory_limit', '1024M');
        ini_set('max_execution_time', 1000);

        $responseArray = [];

        if (in_array('all' ,$request->shopId)) {
            $service = StatisticsService::getInstance();

            $service->setProperties(
                $request->step, 
                'all', 
                $request->unit, 
                $request->dateStartSales, 
                $request->dateEndSales,
                $request->type,
                $request->checkedSp,
                $request->checkedSelfPurchase,
                $request->checkedStatusCancel,
                $request->article,
            );

            $responseArray = $service->getInfoStaticsSalesByDateForTable();

            return response()->json([
                'status' => 'success',
                'data' => $responseArray,
            ]);
        }

        $service = StatisticsService::getInstance();
        $service->setUnionTrue();

        $service->setProperties(
            $request->step, 
            $request->shopId, 
            $request->unit, 
            $request->dateStartSales, 
            $request->dateEndSales,
            $request->type,
            $request->checkedSp,
            $request->checkedSelfPurchase,
            $request->checkedStatusCancel,
            $request->article,
        );

        $responseArray = $service->getInfoStaticsSalesByDateForTable();

        return response()->json([
            'status' => 'success',
            'data' => $responseArray,
        ]);
    }
}