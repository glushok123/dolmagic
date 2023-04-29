<?php

namespace App\Models\Others\Goods;


use App\Models\Model;
use App\Models\Others\Excel\SimpleXLSX;
use App\Models\Products;


class Goods extends Model
{
    public static function getDifferentProductsFromFile($File)
    {
        $filePath = $File->getRealPath();

        if($xlsx = SimpleXLSX::parse($filePath))
        {
            $sheetName = $xlsx->sheetNames()[0];
            return self::parseSheet($xlsx, 0, $sheetName);
        }else{
            return false;
        }

    }

    public static function getColumnsNamesKeys($xlsx, $sheetKey, $sheetName)
    {
        $keys = new \stdClass();
        $necessaryFields = new \stdClass();

        switch($sheetName)
        {
            // Report payments
            case 'Report':
                $necessaryFields->sku = 'Код товара продавца (offerId)';
                break;
            default: return false;
        }

        $titleRowKey = false;
        foreach($xlsx->rows($sheetKey) as $rowKey => $row)
        {
            if(($rowKey === 100) and empty($keys)) return false;

            foreach($row as $cKey => $column)
            {
                foreach($necessaryFields as $nKey => $necessaryField)
                {
                    if($column === $necessaryField)
                    {
                        $keys->{$nKey} = $cKey;
                        $titleRowKey = $rowKey;
                    }
                }

                if(count((array) $keys) === count((array) $necessaryFields))
                {
                    break 2;
                }
            }
        }

        $return = new \stdClass();
        $return->titleRowKey = $titleRowKey;
        $return->keys = $keys;
        return $return;
    }

    public static function parseSheet($xlsx, $sheetKey, $sheetName)
    {
        $columnNamesKeys = self::getColumnsNamesKeys($xlsx, $sheetKey, $sheetName);
        $res = new \stdClass();
        $res->products = [];
        $res->checked = 0;

        if($columnNamesKeys)
        {
            foreach($xlsx->rows($sheetKey) as $rowKey => $row)
            {
                if($rowKey <= $columnNamesKeys->titleRowKey) continue;

                $res->checked++;

                if(isset($columnNamesKeys->keys->sku))
                {
                    if($Product = Products::getProductBy('sku', $row[$columnNamesKeys->keys->sku]))
                    {
                        $quantity = $Product->getWarehouseAvailable(1);
                        if($quantity > 0)
                        {
                            $Product->stvQuantity = $quantity;
                            $res->products[] = $Product;
                        }

                    }
                }
            }
        }


        return $res;
    }
}


