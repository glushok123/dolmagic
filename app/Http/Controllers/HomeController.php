<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreHomeRequest;
use App\Http\Requests\UpdateHomeRequest;
use App\Models\Home;
use App\Models\Bundle;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Pagination\Paginator;
use DB;

class HomeController extends Controller
{
    public $countProductOnPage = 15;


    /**
     * Главная страница
     */
    public function index()
    {
        dd(123);
        Paginator::useBootstrap();

        $modelsBundle = DB::table('bundles')
            ->select(
                'bundles.id',
                'bundles.warranty', 
                'bundles.advertising_networks_id', 
                'bundles.spheres_id', 
                'bundles.price', 
                'bundles.description',
                'spheres.name as spheres_name',
                'advertising_networks.name as advertising_networks_name',
                'bundles.created_at as date',
                'users.logo as user_logo',
                'users.rating as user_rating',
                'users.count_order as user_count_order',
                'users.name as user_name',
            )
            ->leftJoin('spheres', 'bundles.spheres_id', '=', 'spheres.id')
            ->leftJoin('advertising_networks', 'bundles.advertising_networks_id', '=', 'advertising_networks.id')
            ->leftJoin('users', 'bundles.user_id', '=', 'users.id')
            ->orderBy('date', 'DESC')
            ->paginate($this->countProductOnPage);

        $filtersAdvertising = DB::table('advertising_networks')->get();
        $filtersSpheres= DB::table('spheres')->get();

        return view('welcome', [
            'modelsBundle' => $modelsBundle,
            'filtersAdvertising' => $filtersAdvertising,
            'filtersSpheres' => $filtersSpheres,
        ]);
    }

    /**
     * Фильтрация и сортировка
     * 
     * @param Request $request
     * 
     * @return JsonResponse
     */
    public function filtersPost(Request $request): JsonResponse
    {
        Paginator::useBootstrap();
        $builder = $this->filters($request);
        $modelsBundle = $builder->paginate($this->countProductOnPage);
        $modelsBundle->appends($request->all());

        return response()->json([
            'status' => 'success',
            'modelsBundle' => $modelsBundle,
            'data' => view('cart.index', [
                'modelsBundle' => $modelsBundle,
            ])->render()
        ]);
    }

    public function filtersGet(Request $request)
    {
        Paginator::useBootstrap();
        $builder = $this->filters($request);
        $modelsBundle = $builder->paginate($this->countProductOnPage);
        $modelsBundle->appends($request->all());

        $filtersAdvertising = DB::table('advertising_networks')->get();
        $filtersSpheres= DB::table('spheres')->get();

        return view('welcome', [
            'modelsBundle' => $modelsBundle,
            'filtersAdvertising' => $filtersAdvertising,
            'filtersSpheres' => $filtersSpheres,
        ]);
    }

    /**
     * Фильтрация и сортировка (создание запроса)
     * 
     * @param Request $request
     * 
     */
    public function filters(Request $request)
    {
        $builders = DB::table('bundles')
            ->select(
                'bundles.id',
                'bundles.warranty', 
                'bundles.advertising_networks_id', 
                'bundles.spheres_id', 
                'bundles.price', 
                'bundles.description',
                'spheres.name as spheres_name',
                'advertising_networks.name as advertising_networks_name',
                'bundles.created_at as date',
                'users.logo as user_logo',
                'users.rating as user_rating',
                'users.count_order as user_count_order',
                'users.name as user_name',
            )
            ->leftJoin('spheres', 'bundles.spheres_id', '=', 'spheres.id')
            ->leftJoin('advertising_networks', 'bundles.advertising_networks_id', '=', 'advertising_networks.id')
            ->leftJoin('users', 'bundles.user_id', '=', 'users.id');

        if ($request->filtersSort != null) {
            switch ($request->filtersSort) {
                case 'asc-date':
                    $builders = $builders->orderBy('date', 'ASC');
                    break;
                case 'asc-price':
                    $builders = $builders->orderBy('price', 'ASC');
                    break;
                case 'desc-price':
                    $builders = $builders->orderBy('price', 'DESC');
                    break;
            }
        }

        if ($request->filtersAdvertisingValue != null) {
            $builders = $builders->where('advertising_networks_id', $request->filtersAdvertisingValue);
        }

        if ($request->filtersSpheresValue != null) {
            $builders = $builders->where('spheres_id', $request->filtersSpheresValue);
        }

        if ($request->filtersPriceValueMin != null) {
            $builders = $builders->where('price', '>=',  $request->filtersPriceValueMin);
        }

        if ($request->filtersPriceValueMax != null) {
            $builders = $builders->where('price', '<=', $request->filtersPriceValueMax);
        }

        return $builders;
    }

    /**
     * Пользовательское соглашение
     */
    public function showUserAgreement()
    {
        return view('modules.user_agreement');
    }

    /**
     * Политика конфиденциальности
     */
    public function showPrivacyPolicy()
    {
        return view('modules.privacy_policy');
    }

    /**
     * Оферта
     */
    public function showOffer()
    {
        return view('modules.offer');
    }
}