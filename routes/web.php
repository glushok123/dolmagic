<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', 'App\Http\Controllers\StatisticsOrderController@show')->name('statistics');
Route::get('/statistics', 'App\Http\Controllers\StatisticsOrderController@show')->name('statistics');
Route::post('/get-info-statics-order', 'App\Http\Controllers\StatisticsOrderController@getInfoStaticsOrder')->name('getInfoStaticsOrder');
Route::post('/get-info-statics-product', 'App\Http\Controllers\StatisticsOrderController@getInfoStaticsProduct')->name('getInfoStaticsProduct');
Route::post('/get-info-statics-sales-by-date-for-table', 'App\Http\Controllers\StatisticsOrderController@getInfoStaticsSalesByDateForTable')->name('getInfoStaticsSalesByDateForTable');

Route::get('/insales-api', 'App\Http\Controllers\InsalesAPIController@show')->name('insales-api');

Route::get('/group-shop', 'App\Http\Controllers\StatisticsOrderController@show')->name('statistics');

Route::group(['prefix' => 'admin', 'as' => 'admin.'], function () {
    Route::get('statistics', 'App\Http\Controllers\Admin\AdminStatisticsOrderController@showAdmin')->name('statisticsAdmin');
});