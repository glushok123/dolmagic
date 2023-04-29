<?php

namespace App\Models\Others\Yandex;

use App\Console\Api\OzonApi;
use App\Console\Api\YandexApi;
use App\Console\Api\YandexApi3;
use App\Eloquent\Directories\Cost;
use App\Eloquent\Directories\CostsDefault;
use App\Eloquent\Order\Order;
use App\Eloquent\Order\OrdersInfo;
use App\Eloquent\Other\WB\WbReportsData;
use App\Eloquent\Products\Product;
use App\Eloquent\Sales\Sale;
use App\Eloquent\Sales\SalesCost;
use App\Eloquent\Sales\SalesFinancesFile;
use App\Eloquent\Sales\SalesFinancesFromAPI;
use App\Eloquent\Sales\SalesFinancesFromFile;
use App\Eloquent\Sales\SalesFinancesFromFilesPreload;
use App\Eloquent\Sales\SalesFinancesName;
use App\Eloquent\Sales\SalesFinancesService;
use App\Eloquent\Sales\SalesProduct;
use App\Eloquent\Sales\SalesProductsPack;
use App\Models\Directories\Costs;
use App\Models\Model;
use App\Models\Orders;
use App\Models\Others\Excel\SimpleXLSX;
use App\Models\Others\Ozon;
use App\Models\Others\Wildberries\Wildberries;
use App\Models\Others\Wildberries\WildberriesReports;
use App\Models\Products;
use App\Models\Sales;
use App\Models\Shops\Shops;
use App\Models\Users\Notifications;
use Carbon\Carbon;
use Illuminate\Http\File;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class YandexFBY extends Model
{
    public static function saveReportFile($File, $shopId)
    {
        $path = 'files/sales/finances';
        $name = uniqid().'-'.$File->getClientOriginalName();
        $newFile = Storage::disk('public')->putFileAs($path, $File, $name);
        //return asset('storage/'.$fileName);
        $fileName = basename($newFile);

        if($fileName)
        {
            $userId = auth()->user()->id??0;
            $SalesFinancesFile = new SalesFinancesFile;
            $SalesFinancesFile->shop_id = $shopId;
            $SalesFinancesFile->filename = $fileName;
            $SalesFinancesFile->original_name = $File->getClientOriginalName();
            $SalesFinancesFile->user_id = $userId;
            if($SalesFinancesFile->save())
            {
                return $SalesFinancesFile->id;
            };
        };

        return false;
    }

    public static function saveReportFromFile($File, $shopId)
    {
        $fileId = self::saveReportFile($File, $shopId);
        $filePath = $File->getRealPath();

        if($xlsx = SimpleXLSX::parse($filePath))
        {
            return self::parseSheets($xlsx, $fileId, $shopId);
        }else{
            return false;
        }
    }

    public static function parseSheets($xlsx, $fileId, $shopId)
    {
        $sheetNames = $xlsx->sheetNames();
        foreach($sheetNames as $sheetKey => $sheetName)
        {
            if(!self::parseSheet($xlsx, $sheetKey, $sheetName, $fileId, $shopId))
            {
                return false;
            }
        }

        return true; // ?
    }

    public static function getColumnsNamesKeys($xlsx, $sheetKey, $sheetName, $shopId)
    {
        $keys = new \stdClass();
        $keys->compareFields = [];

        $values = new \stdClass();
        $necessaryFields = new \stdClass();
        $necessaryFields->parse = [];

        $helpers = new \stdClass();

        switch($shopId)
        {
            case 1: // OzonSTV
            case 10001: // OzonSTV2
            case 10002: // OzonMSK2
            case 2: // OzonMSK

                    switch($sheetName)
                    {
                        case 'Начисления': // services
                                $necessaryFields->orderNumber = 'Номер отправления или идентификатор услуги';
                                $necessaryFields->orderNumber2 = false;

                                $necessaryFields->serviceNames = [
                                    //'Комиссия за продажу' => NULL,
                                    'Сборка заказа' => NULL,
                                    'Обработка отправления' => NULL,
                                    'Магистраль' => NULL,
                                    'Последняя миля' => NULL,
                                    'Обратная магистраль' => NULL,
                                    'Обработка возврата' => NULL,
                                    'Обработка отмененного или невостребованного товара' => NULL,
                                    'Обработка невыкупленного товара' => NULL,
                                    'Логистика (до 15.04 – плата за доставку КГТ)' => NULL,
                                    'Логистика (до 15.04 – плата за возврат КГТ)' => NULL,
                                    'Обратная логистика (до 15.04 – плата за возврат КГТ)' => NULL,
                                ];
                                $keys->serviceNames = new \stdClass();
                                $keys->reversePrice = true;
                                $necessaryFields->date = 'Дата начисления';
                                $necessaryFields->skus = 'Список SKU';
                            break;
                        case 'Отчет о реализации комиссионног': // pays
                            $necessaryFields->orderNumber = 'Номер';
                            $necessaryFields->date = 'Дата';
                            $necessaryFields->price = [
                                'columnNames' => [
                                    'Итого к начислению, руб.',
                                    'Итого к начислению, RUR',
                                ],
                            ];
                            $necessaryFields->skus = 'Код товара продавца';
                            $necessaryFields->title = 'Товар';
                            $necessaryFields->product_quantity = 'Кол-во';

                            //$keys->quantityZeroWherePriceZero = true;
                            $keys->quantityZeroWherePriceZero = false; //???

                            // Отчет реализации № 679885
                            $necessaryFields->parse['financeNumber'] = 'Отчет реализации № ';

                            $helpers->returnByClient = (object) [
                                'columnName' => 'Возвращено клиентом',
                                'columnKey' => 0
                            ];

                            $keys->compareFields[] = (object) [
                                'columnName' => 'Комиссия за продажу по категории',
                                'columnNames' => [
                                    'Комиссия за продажу по категории',
                                    'Комиссия за продажу по категорииRUR',
                                ],

                                'dbName' => 'compare_commission',

                                'dbNameReturn' => 'compare_commission',
                                'returnSign' => 1,
                                'round2' => true
                            ];

                            $keys->compareFields[] = (object) [
                                'columnName' => 'Реализовано на сумму, руб.',
                                'columnNames' => [
                                    'Реализовано на сумму, руб.',
                                    'Реализовано на сумму, RUR',
                                ],

                                'dbName' => 'ozon_price_1',
                            ];

                            $keys->compareFields[] = (object) [
                                'columnName' => 'Доплата за счет Ozon, руб.',
                                'columnNames' => [
                                    'Доплата за счет Ozon, руб.',
                                    'Доплата за счет Ozon, RUR',
                                ],

                                'dbName' => 'ozon_price_2',
                            ];

                            $keys->compareFields[] = (object) [
                                'columnName' => 'Итого комиссия с учетом скидок и наценки, руб.',
                                'columnNames' => [
                                    'Итого комиссия с учетом скидок и наценки, руб.',
                                    'Итого комиссия с учетом скидок и наценки, RUR',
                                ],

                                'dbName' => 'compare_commission_value',
                                'sign' => -1
                            ];


                            $keys->compareFields[] = (object) [
                                'columnName' => 'Цена продавца, руб. (с учетом скидки продавца)',
                                'columnNames' => [
                                    'Цена продавца, руб. (с учетом скидки продавца)',
                                    'Цена продавца,  (с учетом скидки продавца) RUR',
                                ],

                                'dbName' => 'ozon_price_3',
                                'dbNameReturn' => 'ozon_price_3',
                                'returnSign' => -1
                            ];

                            // returns
                            $keys->compareFields[] = (object) [
                                'columnName' => 'Кол-во',

                                'helper' => 'returnByClient',
                                'dbNameReturn' => 'product_quantity',
                            ];
                            $keys->compareFields[] = (object) [
                                'columnName' => 'Возвращено на сумму, руб.',
                                'columnNames' => [
                                    'Возвращено на сумму, руб.',
                                    'Возвращено на сумму, RUR',
                                ],

                                'helper' => 'returnByClient',
                                'dbNameReturn' => 'ozon_price_1',
                                'returnSign' => -1
                            ];
                            $keys->compareFields[] = (object) [
                                'columnName' => 'Доплата за счет Ozon, руб.',
                                'columnNames' => [
                                    'Доплата за счет Ozon, руб.',
                                    'Доплата за счет Ozon, RUR',
                                ],

                                'helper' => 'returnByClient',
                                'dbNameReturn' => 'ozon_price_2',
                                'returnSign' => -1
                            ];
                            $keys->compareFields[] = (object) [
                                'columnName' => 'Итого комиссия с учетом скидок и наценки, руб.',
                                'columnNames' => [
                                    'Итого комиссия с учетом скидок и наценки, руб.',
                                    'Итого комиссия с учетом скидок и наценки, RUR',
                                ],

                                'helper' => 'returnByClient',
                                'dbNameReturn' => 'compare_commission_value',
                                'returnSign' => 1
                            ];


                            $keys->compareFields[] = (object) [
                                'columnName' => 'Итого возвращено, руб.',
                                'columnNames' => [
                                    'Итого возвращено, руб.',
                                    'Итого возвращено, RUR',
                                ],

                                'helper' => 'returnByClient',
                                'dbNameReturn' => 'price',
                                'returnSign' => -1
                            ];

                            break;
                    }
                break;

            case 67: // Yandex FBY
            case 71: // Yandex DBS
            case 174: // Yandex DBS2
                switch($sheetName)
                {
                    // Report payments
                    case 'Платежи за период':
                    case 'Отчёт о платежах':
                        $necessaryFields->orderNumber = 'Ваш номер заказа';
                        $necessaryFields->orderNumber2 = 'Номер заказа или акта об оказанных услугах';
                        $necessaryFields->serviceName = 'Источник транзакции';
                        $necessaryFields->date = 'Дата платёжного поручения';
                        $necessaryFields->price = 'Сумма транзакции, руб.';
                        $necessaryFields->sku = 'Ваш SKU';
                        $necessaryFields->product_quantity = 'Количество, шт.';


                        $necessaryFields->financeNumber = 'Номер платёжного поручения';

                        break;

                    // Report services
                    case 'Размещение товаров на витрине':
                    case 'Складская обработка':
                    case 'Участие в программе лояльности':
                    case 'Доставка покупателям':
                    case 'Агентское вознаграждение':
                    case 'Приём и перевод платежей':
                    case 'Приём и перевод платежа':
                        $necessaryFields->orderNumber = 'Номер заказа';
                        $necessaryFields->orderNumber2 = false;
                        $necessaryFields->serviceName = 'Услуга';
                        $necessaryFields->date = 'Дата и время предоставления услуги';
                        $necessaryFields->price = 'Стоимость услуги, руб.';
                        $necessaryFields->sku = 'Ваш SKU';
                        $necessaryFields->product_quantity = 'Количество, шт.';
                        break;
                    default: return false;
                }
                break;
        }

        $titleRowKey = false;
        foreach($xlsx->rows($sheetKey) as $rowKey => $row)
        {
            if(($rowKey === 100) and empty($keys)) return false;

            foreach($row as $cKey => $column)
            {
                if(empty($column)) continue; // skip empty column names

                if(isset($helpers->returnByClient))
                {
                    if($helpers->returnByClient->columnName === trim($column))
                    {
                        $helpers->returnByClient->columnKey = $cKey;
                    }
                }

                if(count($necessaryFields->parse) > 0)
                {
                    foreach($necessaryFields->parse as $parseName => $parseString)
                    {
                        if(isset($values->{$parseName})) continue; // only first value

                        $value = explode($parseString, $column)[1]??false;
                        if(is_string($value)) $value = trim($value);
                        if($value) $values->{$parseName} = $value;
                    }
                }

                // compare fields
                if(count($keys->compareFields) > 0)
                {
                    foreach($keys->compareFields as $compareField)
                    {
                        if(!isset($compareField->columnKey)) // if not set columnKey to prevent rewrite second column
                        {
                            $trimColumn = trim($column);
                            $columnFound = isset($compareField->columnNames)
                                    ?(in_array($trimColumn, $compareField->columnNames))
                                    :($trimColumn === $compareField->columnName);

                            if($columnFound)
                            {
                                if(isset($compareField->helper) and isset($helpers->{$compareField->helper}))
                                {
                                    if($helpers->{$compareField->helper}->columnKey <= $cKey) {
                                        $compareField->columnKey = $cKey;
                                        $titleRowKey = $rowKey; // only to check $rowKey to skip first strings
                                    }
                                } else {
                                    $compareField->columnKey = $cKey;
                                    $titleRowKey = $rowKey; // only to check $rowKey to skip first strings
                                }
                            }
                        }
                    }
                }

                foreach($necessaryFields as $nKey => $necessaryField)
                {
                    if(is_array($necessaryField))
                    {
                        if(isset($necessaryField['columnNames']))
                        {
                            if(in_array($column, $necessaryField['columnNames']))
                            {
                                if(!isset($keys->{$nKey}))
                                {
                                    $keys->{$nKey} = $cKey;
                                    $titleRowKey = $rowKey;
                                }
                            }
                        }else
                        {
                            foreach($necessaryField as $nFNkey => $nFName)
                            {
                                if($column === $nFNkey)
                                {
                                    if(!isset($keys->serviceNames->{$nFNkey}))
                                    {
                                        $keys->serviceNames->{$nFNkey} = $cKey;
                                        $titleRowKey = $rowKey;
                                    }
                                }
                            }
                        }
                    }else
                    {
                        if($column === $necessaryField)
                        {
                            if(!isset($keys->{$nKey}))
                            {
                                $keys->{$nKey} = $cKey;
                                $titleRowKey = $rowKey;
                            }
                        }
                    }

                }

                if(count((array) $keys) === count((array) $necessaryFields))
                {
                    // now go to end
                    //break 2;
                }
            }


        }


        $return = new \stdClass();
        $return->titleRowKey = $titleRowKey;
        $return->keys = $keys;
        $return->values = $values;
        return $return;
    }

    public static function getServiceIdByName($serviceName)
    {
        $SalesFinancesService = SalesFinancesService::firstOrCreate(
            ['name' =>  $serviceName],
        );

        return $SalesFinancesService->id;
    }

    public static function getFinanceIdByName($financeName)
    {
        $SalesFinancesName = SalesFinancesName::firstOrCreate(
            ['name' =>  $financeName],
        );

        return $SalesFinancesName->id;
    }

    public static function deleteFileData($financeId, $Period, $shopId)
    {
        $q = [
            ['date', '>=', $Period->from],
            ['date', '<=', $Period->to],
            //['shop_id', $shopId],
        ];
        if($financeId != -1) $q['finance_id'] = $financeId;

        $SalesFinancesFromFile = SalesFinancesFromFile::where($q);

        if(in_array($shopId, [1, 177])) // OzonSTV = OzonSTV + (OzonFBO = OzonHor + OzonRos + ...)
        {
            $SalesFinancesFromFile->whereIn('shop_id', Shops::getAllShopByMain($shopId));
        }else
        {
            $SalesFinancesFromFile->where('shop_id', $shopId);
        }

        return $SalesFinancesFromFile->delete();
    }

    public static function checkReturn($row)
    {
        if(isset($columnNamesKeys->keys->sku))
            $Product = Products::getProductBy('sku', $row[$columnNamesKeys->keys->sku]);

        if(!isset($Product)) return ['error' => 'Не найден продукт возврата'];

        return false;
    }

    public static function newOrUpdateSalesFinancesFromFilesPreloads(
        $shopId,
        $financeId,
        $Date,
        $Product,
        $sku,
        $price,
        $orderNumber,
        $serviceId,
        $fileId,
        $productQuantity,
        $financeNumber,
        $compareFieldsValues,
        $isReturn = 0
    )
    {
        $q = [
            'shop_id' => $shopId,
            'date' => $Date,
            'price' => $price,
            'order_number' => $orderNumber,
            'service_id' => $serviceId,
            'file_id' => $fileId,
            'is_return' => $isReturn
        ];

        $Order = Order::where([
            ['order_system_number', $orderNumber]
        ]);

        if(in_array($shopId, [1, 177])) // OzonSTV = OzonSTV + (OzonFBO = OzonHor + OzonRos + ...)
        {
            $Order->whereIn('type_shop_id', Shops::getAllShopByMain($shopId));
        }else
        {
            $Order->where('type_shop_id', $shopId);
        }

        $Order = $Order->orderBy('id', 'DESC')->first();
        if($Order) $q['shop_id'] = $Order->type_shop_id;

        if($Product) $q['product_id'] = $Product->id;
        if($Order and isset($Order->sale))
        {
            $q['sale_id'] = $Order->sale->id;
            if(!$Product)
            {
                $found = false;

                // first check if only 1 product
                if(count($Order->sale->products) === 1)
                {
                    $q['product_id'] = $Order->sale->products[0]->product_id;
                    $found = true;
                }

                // second check by id
                if(!$found)
                {
                    if($CheckProduct = Products::getProductByShopId($shopId, $sku))
                    {
                        foreach($Order->sale->products as $SaleProduct)
                        {
                            if($SaleProduct->product_id === $CheckProduct->id)
                            {
                                $q['product_id'] = $SaleProduct->product_id;
                                $found = true;
                            }
                        }
                    }
                }

                if(!$found)
                {
                    $simMax = 0; // check similar skus
                    foreach($Order->sale->products as $SaleProduct)
                    {
                        if($SaleProduct->product)
                        {
                            $sim = similar_text($sku, $SaleProduct->product->sku);
                            if($sim > $simMax)
                            {
                                $simMax = $sim;
                                $q['product_id'] = $SaleProduct->product_id;
                            }
                        }
                    }
                }
            }
        }
        if(!is_null($productQuantity)) $q['product_quantity'] = $productQuantity;
        if($financeNumber) $q['finance_number'] = $financeNumber;

        if($sku) $q['product_sku'] = $sku;
        if($financeId) $q['finance_id'] = $financeId;

        $SalesFinancesFromFilesPreload = SalesFinancesFromFilesPreload::firstOrNew($q);

        if($SalesFinancesFromFilesPreload->id)
        {
            $SalesFinancesFromFilesPreload->row_double = 1;
            $SalesFinancesFromFilesPreload->save();

            $SalesFinancesFromFilesPreload = new SalesFinancesFromFilesPreload;
            $SalesFinancesFromFilesPreload->row_double = 1;
            $SalesFinancesFromFilesPreload->fill($q);
        }

        // for service_id = 24 - set sale product return
        if(($SalesFinancesFromFilesPreload->service_id === 24) and $SalesFinancesFromFilesPreload->sale and $SalesFinancesFromFilesPreload->SaleProduct)
        {
            $SalesFinancesFromFilesPreload->set_sale_product_status_id = 4;
        }

        // compare fields (need to exist in db)
        if(!empty($compareFieldsValues)) $SalesFinancesFromFilesPreload->fill($compareFieldsValues);

        $SalesFinancesFromFilesPreload->save();
    }

    public static function wrongRowsAfterTitle($row): bool
    {
        $countWrong = 0;
        $countToWrong = 8;

        foreach($row as $rowColumnValue)
        {
           switch ($rowColumnValue)
           {
               case '1': case '2': case '3':
               case '4': case '5': case '6':
               case '7': case '8': case '9':
               case '10': case '11': case '12':
               case '13': case '14': case '15':
               case '16': case '17': case '18':
               case '19': case '20': case '21':
                        $countWrong++;
                   break;
           }
        }

        return ($countWrong >= $countToWrong);
    }

    public static function getCompareValuesByKeys($row, $compareFields)
    {
        $compareFieldsValues = [];
        foreach($compareFields as $CompareField)
        {
            if(isset($CompareField->columnKey) and isset($CompareField->dbName))
            {
                if($compareValue = $row[$CompareField->columnKey]??NULL)
                {
                    switch($CompareField->columnName)
                    {
                        case 'Комиссия за продажу по категории':
                                if($compareValue < 0.4) $compareValue = $compareValue * 100;
                            break;
                    }
                }

                if($compareValue)
                {
                    if(isset($CompareField->round2)) $compareValue = round($compareValue, 2);
                    if(isset($CompareField->sign)) $compareValue = $CompareField->sign * $compareValue;

                    $compareFieldsValues[$CompareField->dbName] = $compareValue;
                }
            }
        }

        return $compareFieldsValues;
    }

    public static function addOzonReturn($compareFields, $row)
    {
        $fields = [];
        foreach($compareFields as $CompareField)
        {
            if(
                //isset($CompareField->helper)
                //and ($CompareField->helper === 'returnByClient')
                isset($CompareField->dbNameReturn)
                and !empty($CompareField->dbNameReturn)
                and isset($CompareField->columnKey)
            )
            {
                $value = trim($row[$CompareField->columnKey]);
                if($value)
                {
                    switch($CompareField->columnName)
                    {
                        case 'Комиссия за продажу по категории':
                            if($value < 0.4) $value = $value * 100; // there is can be error, if low value in percent commission
                            break;
                    }

                    $value = Orders::getPriceFromString($value, true);

                    if(isset($CompareField->round2)) $value = round($value, 2);
                    if(isset($CompareField->returnSign)) $value = $CompareField->returnSign * $value;
                    $fields[$CompareField->dbNameReturn] = $value;
                }
            }
        }

        return $fields;

    }
    public static function parseSheet($xlsx, $sheetKey, $sheetName, $fileId, $shopId)
    {
        $columnNamesKeys = self::getColumnsNamesKeys($xlsx, $sheetKey, $sheetName, $shopId);

        if($columnNamesKeys)
        {
            $rowNumber = 0;
            foreach($xlsx->rows($sheetKey) as $rowKey => $row)
            {
                $rowNumber++;

                if($rowKey <= $columnNamesKeys->titleRowKey) continue; // rows before title
                if(self::wrongRowsAfterTitle($row)) continue; // wrong rows after title

                $orderNumber = $row[$columnNamesKeys->keys->orderNumber];
                if(!$orderNumber and isset($columnNamesKeys->keys->orderNumber2))
                    $orderNumber = $row[$columnNamesKeys->keys->orderNumber2];

                // temporary don't use no-date rows
                if(empty($orderNumber))  continue; // Нет номера заказа - нет проблем
                if(!isset($columnNamesKeys->keys->date) or empty($row[$columnNamesKeys->keys->date])) continue; // нет даты начисления

                $financeId = self::getFinanceIdByName($sheetName);
                try
                {
                    $Date = Carbon::parse($row[$columnNamesKeys->keys->date]);
                }catch(\Exception $e)
                {
                    dd('Дата не корректная ', $row[$columnNamesKeys->keys->date], "Строка {$columnNamesKeys->keys->date}");
                    continue;
                }

                $Product = isset($columnNamesKeys->keys->sku)?Products::getProductBy('sku', $row[$columnNamesKeys->keys->sku]):false;



                $productQuantity = false;
                if(isset($columnNamesKeys->keys->product_quantity))
                {
                    $productQuantity = (int) ($row[$columnNamesKeys->keys->product_quantity]??false);
                }


                if(isset($columnNamesKeys->keys->skus))
                {
                    $skus = explode(';', $row[$columnNamesKeys->keys->skus]);

                    if(!$productQuantity) $productQuantity = count($skus);
                    if(count($skus) === 1)
                    {
                        $sku = trim($skus[0]);
                        if($sku)
                        {

                            $found = false;
                            $Order = Order::where([
                                ['order_system_number', $orderNumber]
                            ])->first();

                            if($Order and $Sale = $Order->sale)
                            {
                                $Product = Product::where('sku', $sku)
                                    ->whereIn('id', SalesProduct::where('sale_id', $Sale->id)->pluck('product_id')->toArray())
                                    ->first();
                                if($Product) $found = true;

                                if(!isset($Product)
                                    and isset($columnNamesKeys->keys->title)
                                    and $title = $row[$columnNamesKeys->keys->title]
                                )
                                {
                                    $Product = Product::where('name_ru', $title)
                                        ->whereIn('id', SalesProduct::where('sale_id', $Sale->id)->pluck('product_id')->toArray())
                                        ->first();

                                    if($Product) $found = true;
                                }
                            }

                            if(!$found) $Product = Products::getProductBy('sku', $sku);
                        }
                    }
                }

                if(!isset($sku)) $sku = "Строка $rowNumber";

                $financeNumber = false;
                if(isset($columnNamesKeys->keys->financeNumber)) $financeNumber = $row[$columnNamesKeys->keys->financeNumber]??false;
                if(isset($columnNamesKeys->values->financeNumber)) $financeNumber = $columnNamesKeys->values->financeNumber?:false;

                $servicesList = [];
                if(isset($columnNamesKeys->keys->serviceNames))
                {
                    foreach($columnNamesKeys->keys->serviceNames as $serviceName => $serviceKey)
                    {
                        $price = Orders::getPriceFromString($row[$serviceKey], true);
                        if($price != 0)
                        {
                            if(isset($columnNamesKeys->keys->reversePrice) and $columnNamesKeys->keys->reversePrice)
                                $price = -$price;

                            $servicesList[$serviceName] = $price;
                        }

                        if(
                            isset($columnNamesKeys->keys->quantityZeroWherePriceZero)
                            and $columnNamesKeys->keys->quantityZeroWherePriceZero
                            and ($price == 0)
                        ) $productQuantity = 0;
                    }
                }else
                {
                    $serviceName = isset($columnNamesKeys->keys->serviceName)?$row[$columnNamesKeys->keys->serviceName]:$sheetName;

                    $price = Orders::getPriceFromString($row[$columnNamesKeys->keys->price], true);

                    if(
                        isset($columnNamesKeys->keys->quantityZeroWherePriceZero)
                        and $columnNamesKeys->keys->quantityZeroWherePriceZero
                        and ($price == 0)
                    ) $productQuantity = 0;

                    if(isset($columnNamesKeys->keys->reversePrice) and $columnNamesKeys->keys->reversePrice)
                        $price = -$price;

                    $servicesList[$serviceName] = $price;
                }



                if(!empty($servicesList))
                {
                    $compareFieldsValues = self::getCompareValuesByKeys($row, $columnNamesKeys->keys->compareFields);

                    foreach($servicesList as $serviceName => $price)
                    {
                        $serviceId = self::getServiceIdByName($serviceName);

                        if(($financeId === 19) and ($price == 0)) // only ozon file incomes where price = zero
                            continue;

                        self::newOrUpdateSalesFinancesFromFilesPreloads(
                            $shopId,
                            $financeId,
                            $Date,
                            $Product,
                            $sku,
                            $price,
                            $orderNumber,
                            $serviceId,
                            $fileId,
                            $productQuantity,
                            $financeNumber,
                            $compareFieldsValues
                        );
                    }

                    // ozon returns
                    if(
                        ($financeId === 19)
                        and
                        ($compareFieldsValues = self::addOzonReturn($columnNamesKeys->keys->compareFields, $row))
                        and
                        (count($compareFieldsValues) > 2)
                    )
                    {
                        self::newOrUpdateSalesFinancesFromFilesPreloads(
                            $shopId,
                            $financeId,
                            $Date,
                            $Product,
                            $sku,
                            $price,
                            $orderNumber,
                            $serviceId,
                            $fileId,
                            $productQuantity,
                            $financeNumber,
                            $compareFieldsValues,
                            1
                        );
                    }
                }
            }
        }

        return true;
    }

    public static function createSaleUpdateAnother($PreloadedFinances, &$Sale) // only for WB set sale_id if already created
    {
        $wbReportData = WbReportsData
            ::whereIn('shop_id', Shops::getAllShopByMain($PreloadedFinances->shop_id))
            ->where('sale_create', $PreloadedFinances->sale_create)
            ->get();

        foreach($wbReportData as $WbReportData)
        {
            $WbReportData->sale_id = $Sale->id;
            $WbReportData->sale_create = 0;
            $WbReportData->save();

            if($Sale->date_sale > $WbReportData->rr_date)
            {
                $Sale->date_sale = $WbReportData->rr_date;
                $Sale->save();
            }
        }

        $salesFinancesFromFilesPreloads = SalesFinancesFromFilesPreload
            ::whereIn('shop_id', Shops::getAllShopByMain($PreloadedFinances->shop_id))
            ->where('sale_create', $PreloadedFinances->sale_create)
            ->get();

        foreach($salesFinancesFromFilesPreloads as $SFFFP)
        {
            $SFFFP->sale_id = $Sale->id;
            $SFFFP->sale_create = 0;
            $SFFFP->save();
        }
    }

    public static function createSale($PreloadedFinances) // only for WB
    {
        $wbShopIds = array_merge(Shops::getAllShopByMain(177), Shops::getAllShopByMain(179));
        if(!in_array($PreloadedFinances->shop_id, $wbShopIds)) return false;
        if(!$WbData = WbReportsData::where('id', $PreloadedFinances->wb_data_id)->first()) return false;

        $storeShopId = Wildberries::getStoreShop($PreloadedFinances->shop_id);

        $Sale = new Sale;
        $Sale->system_id = Shops::getShop($storeShopId)->system_id;
        $Sale->type_shop_id = $storeShopId;
        $Sale->date_sale = $WbData->rr_date;
        $Sale->comments = "Создана через Финансы 3.0: загрузка отчётов (Отчёт $WbData->realizationreport_id)";

        if($WbData->shk_id) $Sale->shk_id = $WbData->shk_id;
        if($WbData->sticker_id) $Sale->sticker_id = $WbData->sticker_id;
        if($WbData->rid) $Sale->rid = $WbData->rid;
        if($WbData->srid) $Sale->srid = $WbData->srid;

        if(!$Sale->save()) return false;

        Notifications::notifyNewSaleByFinanceV3($Sale, $WbData, 'reports_finance_v3');

        $SalesProductsPack = new SalesProductsPack;
        $SalesProductsPack->sale_id = $Sale->id;
        $SalesProductsPack->number = 1;

        if(!$SalesProductsPack->save()) return false;

        if($PreloadedFinances->product_id)
        {
            $SalesProduct = new SalesProduct;
            $SalesProduct->sale_id = $Sale->id;
            $SalesProduct->pack_id = $SalesProductsPack->id;

            $SalesProduct->product_id = $PreloadedFinances->product_id;
            $SalesProduct->product_quantity = $PreloadedFinances->product_quantity;
            $SalesProduct->product_price = 0.01;

            $SalesProduct->save();
        }

        Costs::createAutoCostsForSale($Sale);
        $Sale->full_created = 1;
        $Sale->save();

        // there is need set all WB sale_id if it has the same sale_create code
        self::createSaleUpdateAnother($PreloadedFinances, $Sale);

        return $Sale->id;
    }

    public static function saveFinancesPreloads($SalesFinancesFile)
    {
        $wbShopIds = array_merge(Shops::getAllShopByMain(177), Shops::getAllShopByMain(179));
        foreach($SalesFinancesFile->allTypePreloadedFinances as $key => $PreloadedFinances)
        {
            $PreloadedFinances->refresh(); // is using for update model if is changed by WB

            if(!$PreloadedFinances->Sale)
            {
                // if it needs to create sale for WB only now
                if($PreloadedFinances->sale_create and in_array($PreloadedFinances->shop_id, $wbShopIds))
                {
                    if($createdSaleId = self::createSale($PreloadedFinances))
                    {
                        $PreloadedFinances->sale_id = $createdSaleId; // temp variable - is not saving there
                    }
                }else
                {
                    $PreloadedFinances->delete();
                    continue;
                }
            }

            // for WB set price for zero sales
            if(in_array($PreloadedFinances->shop_id, $wbShopIds))
            {
                if($WbData = WbReportsData::where('id', $PreloadedFinances->wb_data_id)->first())
                {
                    if($WbData->OperationName->product_price_set)
                    {
                        if($SalesProduct = SalesProduct::where('sale_id', $PreloadedFinances->sale_id)->first())
                        {
                            if($SalesProduct->product_price <= 1)
                            {
                                $SalesProduct->product_price = $WbData->{$WbData->OperationName->field};
                                $SalesProduct->commission_value = $WbData->calc_commission;
                                $SalesProduct->commission_percent = $WbData->calc_commission_percent;
                                $SalesProduct->save();
                            }
                        }
                    }
                }
            }

            $q = [
                'shop_id' => $PreloadedFinances->shop_id,
                'date' => $PreloadedFinances->date,
                'price' => $PreloadedFinances->price,
                'order_number' => $PreloadedFinances->order_number,
                'service_id' => $PreloadedFinances->service_id,

                'is_return' => $PreloadedFinances->is_return, // ?? only ozon?
            ];

            // WB need all prices
            if(in_array($PreloadedFinances->shop_id, $wbShopIds))
            {
                $q['sale_id'] = $PreloadedFinances->sale_id;
            }

            if($PreloadedFinances->finance_number) $q['finance_number'] = $PreloadedFinances->finance_number;
            if($PreloadedFinances->product_id) $q['product_id'] = $PreloadedFinances->product_id;
            if($PreloadedFinances->finance_id) $q['finance_id'] = $PreloadedFinances->finance_id;

            // newest for next month doubles
            $q['reconciled'] = 0;

            // for WB
            if($PreloadedFinances->wb_data_id)
                $q['wb_data_id'] = $PreloadedFinances->wb_data_id;

            if(in_array(Shops::getMainShop($SalesFinancesFile->shop_id), [1, 2])) // OZON
            {
                // only add, no update
                $SalesFinancesFromFile = new SalesFinancesFromFile;
                $SalesFinancesFromFile->fill($q);
            }else
            {
                $SalesFinancesFromFile = SalesFinancesFromFile::firstOrNew($q);
            }

            if($PreloadedFinances->sale_id)
            {
                $SalesFinancesFromFile->sale_id = $PreloadedFinances->sale_id;
            }
            $SalesFinancesFromFile->file_id = $PreloadedFinances->file_id;

            // feel other fields from preload (only info)

            $SalesFinancesFromFile->product_sku = $PreloadedFinances->product_sku;
            $SalesFinancesFromFile->product_quantity = $PreloadedFinances->product_quantity;
            $SalesFinancesFromFile->compare_commission = $PreloadedFinances->compare_commission;
            $SalesFinancesFromFile->compare_commission_value = $PreloadedFinances->compare_commission_value;
            $SalesFinancesFromFile->ozon_price_1 = $PreloadedFinances->ozon_price_1;
            $SalesFinancesFromFile->ozon_price_2 = $PreloadedFinances->ozon_price_2;
            $SalesFinancesFromFile->ozon_price_3 = $PreloadedFinances->ozon_price_3;

            $SalesFinancesFromFile->wb_data_id = $PreloadedFinances->wb_data_id;
            $SalesFinancesFromFile->wb_income = $PreloadedFinances->wb_income;

            if($SalesFinancesFromFile->save())
            {
                if(
                    $FinanceSale = Sale::where('id', $SalesFinancesFromFile->sale_id)->first()
                        and
                    in_array($FinanceSale->shop_id, [178, 180]) // WB Store Costs
                )
                {
                    WildberriesReports::createCostFromWbData($PreloadedFinances);
                }

                if($PreloadedFinances->set_sale_product_status_id) // if only has set_sale_product_status_id
                {
                    $SaleProduct = $PreloadedFinances->SaleProduct;
                    if($SaleProduct and ($SaleProduct->status_id !== $PreloadedFinances->set_sale_product_status_id))
                    {
                        $SaleProduct->status_id = $PreloadedFinances->set_sale_product_status_id;
                        $SaleProduct->save();
                    }
                }

                $PreloadedFinances->delete();
            }
        }

        return true;
    }




    // API Data

    public static function saveAPIFinancesBySaleList($sales = [])
    {
        if(!$sales)
        {
            $sales = Sale::where([
                ['type_shop_id', '67']
            ])->whereHas('order')->get();
        }

        $systemOrdersList = [];
        $YandexAPI = new YandexApi();
        foreach($sales as $Sale)
        {
            $SystemOrder = $YandexAPI->getOrder($Sale->order->system_order_id);
            if($SystemOrder) $systemOrdersList[] = $SystemOrder;
        }

        self::saveAPIFinances($systemOrdersList);
    }

    public static function saveAPIFinances($systemOrdersList = false, $subDays = 30)
    {
        // $Order, $SystemOrder

        if(!$systemOrdersList)
        {
            $YandexAPI = new YandexApi();
            $systemOrdersList = $YandexAPI->getOrdersList(Carbon::now()->subDays($subDays)->startOfDay());
        }

        if($systemOrdersList)
        {
            foreach($systemOrdersList as $oKey => $SystemOrder)
            {
                $Order = Orders::getOrder($SystemOrder->id, 68);
                if(!$Order) continue;

                if($Sale = $Order->sale and isset($SystemOrder->commissions) and !empty($SystemOrder->commissions))
                {
                    foreach($SystemOrder->commissions as $SystemOrderCommission)
                    {
                        if(isset($SystemOrderCommission->type))
                        {
                            $serviceId = self::getServiceIdByName($SystemOrderCommission->type);
                            if(!$serviceId) continue;

                            $query = [
                                'shop_id' => 67,
                                'sale_id'  => $Sale->id,
                                'service_id' => $serviceId,
                            ];

                            if($systemOrderNumber = Orders::getSystemOrderField($SystemOrder, 68, 'number'))
                            {
                                $query['order_number'] = $systemOrderNumber;
                            }

                            $value = false;
                            if(isset($SystemOrderCommission->actual)) $value = $SystemOrderCommission->actual;
                            if(($value === false) and isset($SystemOrderCommission->predicted)) $value = $SystemOrderCommission->predicted;

                            if($value !== false)
                            {
                                $SalesFinancesFromAPI = SalesFinancesFromAPI::firstOrNew($query);
                                $SalesFinancesFromAPI->price = $value;
                                $SalesFinancesFromAPI->date = Carbon::now('Europe/Moscow');
                                $SalesFinancesFromAPI->save();
                            }
                        }
                    }
                }
            }
        }else
        {
            dd('Nothing found - maybe error in get orders');
        }
    }

    public static function saveDBSAPIFinances($systemOrdersList = false, $subDays = 2, $FromDate = false, $ToDate = false)
    {
        // $Order, $SystemOrder

        if(!$systemOrdersList)
        {
            $YandexAPI3 = new YandexApi3;
            if($FromDate)
            {
                $systemOrdersList = $YandexAPI3->getOrdersList(
                    Carbon::parse($FromDate)->startOfDay(),
                    NULL,
                    NULL,
                    Carbon::parse($ToDate)->endOfDay()
                );
            }else
            {
                $systemOrdersList = $YandexAPI3->getOrdersList(Carbon::now()->subDays($subDays)->startOfDay());
            }
        }

        if($systemOrdersList)
        {
            foreach($systemOrdersList as $oKey => $SystemOrder)
            {
                $Order = Orders::getOrder($SystemOrder->id, 74);
                if(!$Order) continue;

                if($Sale = $Order->sale and isset($SystemOrder->commissions) and !empty($SystemOrder->commissions))
                {
                    foreach($SystemOrder->commissions as $SystemOrderCommission)
                    {
                        if(isset($SystemOrderCommission->type))
                        {
                            $serviceId = self::getServiceIdByName($SystemOrderCommission->type);
                            if(!$serviceId) continue;

                            $query = [
                                'shop_id' => 71,
                                'sale_id'  => $Sale->id,
                                'service_id' => $serviceId,
                            ];

                            if($systemOrderNumber = Orders::getSystemOrderField($SystemOrder, 74, 'number'))
                            {
                                $query['order_number'] = $systemOrderNumber;
                            }

                            $value = false;
                            if(isset($SystemOrderCommission->actual)) $value = $SystemOrderCommission->actual;
                            if(($value === false) and isset($SystemOrderCommission->predicted)) $value = $SystemOrderCommission->predicted;

                            if($value !== false)
                            {
                                $SalesFinancesFromAPI = SalesFinancesFromAPI::firstOrNew($query);
                                $SalesFinancesFromAPI->price = $value;
                                $SalesFinancesFromAPI->date = Carbon::now('Europe/Moscow');
                                $SalesFinancesFromAPI->save();
                            }
                        }
                    }
                }
            }
        }
    }

    public static function saveOzonAPIFinances(
        $shopId,
        $FromDate = false,
        $ToDate = false,
        $postingNumber = false,
        $subDays = 14
    )
    {
        $OzonAPI = Ozon::getOzonApiByShopId(Shops::getMainShop($shopId));

        if(!$postingNumber) $FromDate = $FromDate?(Carbon::parse($FromDate)->startOfDay()):(Carbon::now()->subDays($subDays)->startOfDay());
        $ToDate = $ToDate?(Carbon::parse($ToDate)->endOfDay()):false;

        $transactions = $OzonAPI->getTransactionsV3($FromDate, $ToDate, $postingNumber);

        if($transactions)
        {
            foreach($transactions as $oKey => $Transaction)
            {
                if(isset($Transaction->posting->posting_number) and $Transaction->posting->posting_number)
                {
                    $postingNumber = $Transaction->posting->posting_number;
                }else
                {
                    continue;
                }


                if(isset($Transaction->services) and $Transaction->services)
                {
                    $services = $Transaction->services;
                }else
                {
                    if(!in_array($Transaction->operation_type, [
                        'OperationAgentDeliveredToCustomer',
                        'ClientReturnAgentOperation',
                        'OperationAgentStornoDeliveredToCustomer',
                        ])
                    ) dd($Transaction);
                    continue;
                }

                $Order = Orders::getOrder($postingNumber, $OzonAPI->systemId);
                if(!$Order) continue;

                if($Sale = $Order->sale)
                {
                    foreach($services as $Service)
                    {
                        if($serviceId = Ozon::getOzonTransactionServiceIdByAlias($Service->name))
                        {
                            $query = [
                                'shop_id' => $shopId,
                                'sale_id'  => $Sale->id,
                                'service_id' => $serviceId,
                                'shop_operation_id' => $Transaction->operation_id,
                            ];

                            $SalesFinancesFromAPI = SalesFinancesFromAPI::firstOrNew($query);
                            $SalesFinancesFromAPI->price = -$Service->price;
                            $SalesFinancesFromAPI->date = Carbon::now('Europe/Moscow');
                            $SalesFinancesFromAPI->save();
                        }
                    }
                }
            }
        }
    }


    public static function getCostsWithAPIAndFile(
        $dateFrom,
        $dateTo,
        $shopId,
        $serviceId = false,
        $onlyDifferences = false,
        $sale_reconciled = -1,
        $finances_reconciled = -1,
        $order_number = false
    )
    {
        $res = new \stdClass();
        $res->financesTableCosts = [];
        $res->totalCalcPrice = 0;
        $res->totalApiPrice = 0;
        $res->totalFilePrice = 0;

        $q = [
            ['date', '>=', Carbon::parse($dateFrom)->startOfDay()],
            ['date', '<=', Carbon::parse($dateTo)->endOfDay()],
        ];

        if($serviceId) $q['service_id'] = $serviceId;
        if($order_number) $q['order_number'] = $order_number;
        if($finances_reconciled !== -1)
        {
            $q['reconciled'] = $finances_reconciled;
        }

        $financesFromFiles = SalesFinancesFromFile::
        selectRaw('id, sale_id, shop_id, service_id, file_id, reconciled, sum(price) as price, date, GROUP_CONCAT(id) as sale_finances_ids')
        ->whereHas('service', function($q){
            $q->whereNotNull('cost_id');
        })->where($q)->groupBy('sale_id', 'service_id');

        if(in_array($shopId, [1, 177])) // OzonSTV = OzonSTV + (OzonFBO = OzonHor + OzonRos + ...)
        {
            $financesFromFiles->whereIn('shop_id', Shops::getAllShopByMain($shopId));
        }else
        {
            $financesFromFiles->where('shop_id', $shopId);
        }

        if($sale_reconciled !== -1)
        {
            $financesFromFiles->whereHas('sale', function($sale) use ($sale_reconciled)
            {
                $sale->where('finances_reconciled', $sale_reconciled);
            });
        }

        $financesFromFiles = $financesFromFiles->get();

        foreach($financesFromFiles as $key => $FinancesFromFile)
        {
            $FinancesTableCost = new \stdClass();
            $FinancesTableCost->cost_id = $FinancesFromFile->service->cost_id;
            $FinancesTableCost->name = $FinancesFromFile->service->name;
            $FinancesTableCost->filePrice = $FinancesFromFile->price;
            $FinancesTableCost->FinancesFromFile = $FinancesFromFile;

            $FinancesTableCost->fileDate = Carbon::parse($FinancesFromFile->date, 'Europe/Moscow')->toDateTimeString();

            if($SaleCost = SalesCost::where([
                ['sale_id', $FinancesFromFile->sale_id],
                ['cost_id', $FinancesFromFile->service->cost_id],
            ])->first())
            {
                $FinancesTableCost->calcPrice = round(Sales::getIncomeOrCostValue($SaleCost, $SaleCost->sale), 2);
                $FinancesTableCost->calcDate = Carbon::now('Europe/Moscow')->toDateTimeString();
            }

            // filtering equal sums
            if($onlyDifferences)
            {
                if((($FinancesTableCost->calcPrice??0) === $FinancesTableCost->filePrice))
                {
                    continue;
                }
            }
            if(isset($FinancesTableCost->calcPrice)) $res->totalCalcPrice += $FinancesTableCost->calcPrice;
            $res->totalFilePrice += $FinancesTableCost->filePrice;


            if($FromAPI = SalesFinancesFromAPI::where([
                ['shop_id', $FinancesFromFile->sale->type_shop_id],
                ['sale_id', $FinancesFromFile->sale_id],
            ])->whereHas('service', function($q) use ($FinancesFromFile)
            {
                $q->where('cost_id', $FinancesFromFile->service->cost_id);
            })->first())
            {
                $FinancesTableCost->apiDate = Carbon::parse($FromAPI->date, 'Europe/Moscow')->toDateTimeString();
                $FinancesTableCost->apiPrice = $FromAPI->price;
                $res->totalApiPrice += $FinancesTableCost->apiPrice;
            }

            if(!isset($FinancesTableCost->apiPrice) or (($FinancesTableCost->apiPrice??0) !== $FinancesTableCost->filePrice))
                $FinancesTableCost->apiError = true;

            if(!isset($FinancesTableCost->calcPrice) or (($FinancesTableCost->calcPrice??0) != $FinancesTableCost->filePrice))
                $FinancesTableCost->calcError = true;

            $res->financesTableCosts[] = $FinancesTableCost;
        }

        return $res;
    }


}


