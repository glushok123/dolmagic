<?php

namespace App\Models;

use App\Eloquent\Products\Product;
use App\Eloquent\Products\ProductCategory;
use App\Eloquent\Products\ProductCharacter;
use App\Eloquent\Products\ProductFeature;
use App\Eloquent\Products\ProductImage;
use App\Eloquent\Products\ProductImagesShopRule;
use App\Eloquent\Products\ProductImagesShopRulesPosition;
use App\Eloquent\Products\ProductShopCategory;
use App\Eloquent\Products\ProductsName;
use App\Eloquent\Products\ProductsRemoveHistory;
use App\Eloquent\Products\ProductsShopPrice;
use App\Eloquent\Products\ProductType;
use App\Eloquent\Products\ProductMaterial;
use App\Eloquent\Products\ProducingCountry;
use App\Eloquent\Products\Manufacturer;
use App\Eloquent\Products\ProductGroup;
use App\Eloquent\Products\TypeShopProduct;
use App\Eloquent\Sales\SalesProduct;
use App\Eloquent\Shops\Products\ShopProductsCategory;
use App\Eloquent\Shops\Products\ShopProductsReload;
use App\Eloquent\Shops\ShopProductsPostfix;
use App\Eloquent\Shops\ShopProductsSize;
use App\Eloquent\System\SystemsCommission;
use App\Eloquent\System\SystemsCommissionType;
use App\Eloquent\System\SystemsProductsStop;
use App\Console\Api\InsalesApi;
use App\Models\Others\Ozon;
use App\Models\Shops\Shops;
use App\Models\Users\Notifications;
use App\Models\Users\Users;
use Carbon\Carbon;
use Illuminate\Http\File;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use SebastianBergmann\CodeCoverage\Report\PHP;
use App\Models\Model;
use function foo\func;

class Products extends Model{

    public static function getProductWithPostfix($skuWithPostfix, $shopId = false)
    {
        return
            $skuWithPostfix
            ?Product::where('sku', self::getProductSkuWithPostfix($skuWithPostfix, $shopId))->first()
            :false;
    }


    // orderByRaw('CHAR_LENGTH(name)')->get();

    public static function getProductSkuWithPostfix($skuWithPostfix, $shopId = false)
    {
        $ShopProductsPostfix = ShopProductsPostfix
            ::where('state', '>', -1);
        if($shopId) $ShopProductsPostfix->where('shop_id', $shopId);
        $ShopProductsPostfix->orderByRaw('CHAR_LENGTH(postfix) DESC');
        $productsPostfixes = $ShopProductsPostfix->get();

        foreach($productsPostfixes as $ProductsPostfix)
        {
            $pos = strpos($skuWithPostfix, '-'.$ProductsPostfix->postfix);
            if($pos !== FALSE)
            {
                return mb_substr($skuWithPostfix, 0, $pos);
            }
        }

        return $skuWithPostfix;
    }

    public static function getProductSkuWithPostfixOld2($skuWithPostfix, $shopId = false): string
    {
        $postfix2 = false;
        $postfix3 = false;

        $sku = $skuWithPostfix;
        $skuParts = explode('-', $skuWithPostfix);
        $countParts = count($skuParts);
        $postfix = $skuParts[$countParts-1];
        if(isset($skuParts[$countParts-2])) $postfix2 = $skuParts[$countParts-2] .'-'. $skuParts[$countParts-1];
        if(isset($skuParts[$countParts-3])) $postfix3 = ($skuParts[$countParts-3] .'-'. $skuParts[$countParts-2] .'-'. $skuParts[$countParts-1])??false;
        if($postfix)
        {
            $ShopProductsPostfix = ShopProductsPostfix
                ::where(function($q) use ($postfix, $postfix2, $postfix3)
                {
                    $q->where('postfix', $postfix);
                    if($postfix2) $q->orWhere('postfix', $postfix2);
                    if($postfix3) $q->orWhere('postfix', $postfix3);
                })
                ->where('state', '>', -1);
            if($shopId) $ShopProductsPostfix->where('shop_id', $shopId);

            if($ShopProductsPostfix = $ShopProductsPostfix->first())
                $sku = str_replace('-'.$ShopProductsPostfix->postfix, '', $sku);
        }

        return $sku;
    }

    public static function getProductSkuWithPostfixOLD($skuWithPostfix, $shopId = false): string
    {
        $sku = $skuWithPostfix;
        $skuParts = explode('-', $skuWithPostfix);
        $countParts = count($skuParts);
        $postfix = $skuParts[$countParts-1];
        if($postfix)
        {
            $ShopProductsPostfix = ShopProductsPostfix::where('postfix', $postfix)->where('state', 1);
            if($shopId) $ShopProductsPostfix->where('shop_id', $shopId);

            if($ShopProductsPostfix->first())
            {
                unset($skuParts[$countParts-1]);
                $sku = implode('-', $skuParts);
            }
        }

        return $sku;
    }

    public static function getProductByBarcodes(array $barcodes)
    {
        $Product = Product::whereIn('dummy_barcode', $barcodes)
            ->orWhereIn('barcode1', $barcodes)
            ->orWhereIn('barcode2', $barcodes)
            ->orWhereIn('barcode3', $barcodes)
            ->orWhereIn('barcode4', $barcodes)
            ->first();

        return $Product;
    }

    public static function getProductByBarcode($barcode)
    {
        $Product = Product::where('dummy_barcode', $barcode)
            ->orWhere('barcode1', $barcode)
            ->orWhere('barcode2', $barcode)
            ->orWhere('barcode3', $barcode)
            ->orWhere('barcode4', $barcode)
        ->first();

        return $Product;
    }

    public static function getProductBy($attr, $val, $createIfSku = false, $name = false)
    {
        $Product = Product::with('images')->where($attr, '=', $val)->first();
        if(empty($Product))
        {
            if($createIfSku and ($attr === 'sku'))
            {
                $Product = new Product;
                $Product->sku = $val;
                if($name) $Product->name_ru = $name;
                $Product->save();
            };
        };
        return $Product??false;
    }

    /*
    public static function getProductFromInsalesById($id){
        $InsalesApi = new InsalesApi();
        $InsalesProduct = $InsalesApi->getProductById($id);
        $Product = Product::where('sku', '=', self::getInsalesProductParameter($InsalesProduct, 'sku'))->limit(1)->first();
        return $Product??false;
    }
    */

    public static function getProductImageFromUrl($url, $productId = 'undefended'){
        $path = 'images/products/'.$productId;
        $info = pathinfo($url);
        $contents = file_get_contents($url);
        if($contents !== false) {
            $file = '/tmp/' . $info['basename'];
            file_put_contents($file, $contents);
            $fileName = Storage::disk('public')->putFile($path, new File($file));
            unlink($file);
            //return asset('storage/'.$fileName);
            return basename($fileName);
        };
        return false;
    }

    public static function productImageAdd($url, $productId, $position = 0){
        if($url and $productId){
            $fileName = self::getProductImageFromUrl($url, $productId);
            if($fileName){
                $ProductImage = new ProductImage;
                $ProductImage->filename = $fileName;
                $ProductImage->product_id = $productId;
                $ProductImage->position = $position;
                $ProductImage->save();
            };
        };
        return false;
    }

    public static function getImagesInsalesProduct($InsalesImages){
        $images = array();
        foreach($InsalesImages as $InsalesImage){
            $Image = new \stdClass();
            $Image->url = $InsalesImage->original_url;
            $Image->position = $InsalesImage->position;
            $images[] = $Image;
        };
        return $images;
    }

    public static function getManufacturerIdFromString($strManufacturer, $createIfExist = false){
        if($strManufacturer){
            $Manufacturer = Manufacturer::whereRaw('LOWER(name) = ?', [mb_strtolower($strManufacturer)])->first();
            if($Manufacturer){
                return $Manufacturer->id;
            }else if($createIfExist){
                $Manufacturer = new Manufacturer;
                $Manufacturer->name = $strManufacturer;
                $Manufacturer->save();
                return $Manufacturer->id;
            };
        };
        return 0; // default
    }

    public static function getCharacterIdFromString($strCharacter, $createIfExist = false)
    {
        if($strCharacter)
        {
            if($ProductCharacter = ProductCharacter::whereRaw('LOWER(name) = ?', [mb_strtolower($strCharacter)])->first())
            {
                return $ProductCharacter->id;
            }else if($createIfExist)
            {
                $ProductCharacter = new ProductCharacter;
                $ProductCharacter->name = $strCharacter;
                if($ProductCharacter->save())
                {
                    return $ProductCharacter->id;
                }
            };
        };
        return 0; // default
    }

    public static function getFeatureIdFromString($strFeature, $createIfExist = false)
    {
        if($strFeature)
        {
            if($ProductFeature = ProductFeature::whereRaw('LOWER(name) = ?', [mb_strtolower($strFeature)])->first())
            {
                return $ProductFeature->id;
            }else if($createIfExist)
            {
                $ProductFeature = new ProductFeature;
                $ProductFeature->name = $strFeature;
                if($ProductFeature->save())
                {
                    return $ProductFeature->id;
                }
            };
        };
        return 0; // default
    }

    public static function getProducingCountryIdFromString($strProducingCountry, $createIfExist = false){
        if($strProducingCountry){
            $ProducingCountry = ProducingCountry::whereRaw('LOWER(name) = ?', [mb_strtolower($strProducingCountry)])->limit(1)->first();
            if($ProducingCountry){
                return $ProducingCountry->id;
            }else if($createIfExist){
                $ProducingCountry = new ProducingCountry;
                $ProducingCountry->name = $strProducingCountry;
                $ProducingCountry->save();
                return $ProducingCountry->id;
            };
        };
        return 0; // default
    }

    public static function getMaterialIdFromString($strProductMaterial, $createIfExist = false){
        if($strProductMaterial){
            $ProductMaterial = ProductMaterial::whereRaw('LOWER(name) = ?', [mb_strtolower($strProductMaterial)])->limit(1)->first();
            if($ProductMaterial){
                return $ProductMaterial->id;
            }else if($createIfExist){
                $ProductMaterial = new ProductMaterial;
                $ProductMaterial->name = $strProductMaterial;
                $ProductMaterial->save();
                return $ProductMaterial->id;
            };
        };
        return 0; // default
    }

    public static function getTypeIdFromString($strProductType, $createIfExist = false){
        if($strProductType)
        {
            if($ProductType = ProductType::whereRaw('LOWER(name) = ?', [mb_strtolower($strProductType)])->first())
            {
                return $ProductType->id;
            }else if($createIfExist){
                $ProductType = new ProductType;
                $ProductType->name = $strProductType;
                $ProductType->save();
                return $ProductType->id;
            };
        };
        return 0; // default
    }

    public static function getCategoryIdFromString($strCategory, $createIfExist = false){
        if($strCategory)
        {
            if($ProductCategory = ProductCategory::whereRaw('LOWER(name) = ?', [mb_strtolower($strCategory)])->first())
            {
                return $ProductCategory->id;
            }else if($createIfExist)
            {
                $ProductCategory = new ProductCategory;
                $ProductCategory->name = $strCategory;
                $ProductCategory->save();
                return $ProductCategory->id;
            };
        };
        return 0; // default
    }

    public static function getCharacteristicsInsalesProduct($insalesProduct, $ids = false){
        $characteristics = [];
        if($ids){
            foreach($insalesProduct->characteristics as $characteristic){
                if(isset($characteristic->property_id)){
                    if(in_array($characteristic->property_id, $ids)){
                        $characteristics[] = $characteristic->title;
                    };
                };
            };
        };

        if(count($characteristics) > 0) return $characteristics[0];
        return false; // 0 is default
    }

    public static function getCategoryInsalesProduct($categoryId){
        if($categoryId) {
            $InsalesApi = new InsalesApi();
            $category = $InsalesApi->getCategory($categoryId);

            if($category){
                if(mb_strtolower($category->title) == 'склад') return 0;// For Insales main Group

                $ProductGroup = ProductGroup::whereRaw('LOWER(name) = ?', [mb_strtolower($category->title)])->limit(1)->first();
                if($ProductGroup){
                    return $ProductGroup->id;
                }else{
                    if($category->parent_id) $parentId = self::getCategoryInsalesProduct($category->parent_id);
                    $ProductGroup = new ProductGroup;
                    $ProductGroup->name = $category->title;
                    if(isset($parentId)) $ProductGroup->parent_id = $parentId;
                    $ProductGroup->save();
                    return $ProductGroup->id;
                };
            };
        };
        return 0; //Default
    }

    public static function getProductGroupByInsalesCharacter($characterName){
        $ProductGroup = ProductGroup::whereRaw('LOWER(insales_character_name) LIKE "%?%"', [mb_strtolower($characterName)])->first();
        return $ProductGroup??0;
    }

    public static function getProductGroupByInsalesCollectionId($insalesCollectionId){
        $ProductGroup = ProductGroup::where('insales_collection_id', 'LIKE', "%$insalesCollectionId%")->limit(1)->first();
        return $ProductGroup??0;
    }

    public static $test = 0;
    public static function stop(){
        echo "Type 'y' to continue: ";
        $handle = fopen ("php://stdin","r");
        $line = fgets($handle);
        if(trim($line) != 'y'){
            echo "ABORTING!\n";
            exit;
        };
    }
    public static function getGroupInsalesProduct($collectionsIds, $characterName = false){
        $productGroupId = 0; // Default
        if($collectionsIds){
            $InsalesApi = new InsalesApi();
            foreach($collectionsIds as $collectionId)
            {
                $Collection = $InsalesApi->getCollection($collectionId);
                if($Collection)
                {
                    $ProductGroup = self::getProductGroupByInsalesCollectionId($Collection->id);
                    if($ProductGroup){
                        $productGroupId = $ProductGroup->id;
                        break;
                    }else{
                        if(!empty($Collection->parent_id)){
                            $productGroupId = self::getGroupInsalesProduct([$Collection->parent_id]);
                            if($productGroupId !== 0) break;
                        };
                    };
                }else{
                    echo 'Collection not found'.PHP_EOL;
                };
            };
        };

        if($productGroupId === 0){
            if($characterName){
                print_r($collectionsIds);
                echo PHP_EOL;
                //self::stop();
                self::$test++;
                $ProductGroup = self::getProductGroupByInsalesCharacter($characterName);
                if($ProductGroup) {
                    $productGroupId = $ProductGroup->id;
                };
            };
        };

        return $productGroupId;
    }

    public static function getInsalesProductParameter($insalesProduct, $parameter){
        switch($parameter){
            case 'sku':
                    return $insalesProduct->variants[0]->sku;
                break;
            case 'barcode1':
                    return $insalesProduct->variants[0]->barcode;
                break;
            case 'name_ru':
                    return $insalesProduct->title;
                break;
            case 'weight':
                    return $insalesProduct->variants[0]->weight*1000;
                break;
            case 'markdown':
                    return (bool) self::getCharacteristicsInsalesProduct(
                        $insalesProduct,
                        [
                            27326135 //utsenka
                        ]
                    );
                break;
            case 'category_id':
                return self::getCategoryIdFromString(
                    self::getCharacteristicsInsalesProduct(
                        $insalesProduct,
                        [
                            1520250,  // Категория
                        ]
                    ),
                    true
                );
                break;
            case 'type_id':
                    return self::getTypeIdFromString(
                        self::getCharacteristicsInsalesProduct(
                            $insalesProduct,
                            [
                                35666974,
                            ]
                        ),
                        true
                    );
                break;
            case 'material_id':
                    return self::getMaterialIdFromString(
                        self::getCharacteristicsInsalesProduct(
                            $insalesProduct,
                            [
                                1520251, //material
                                1520306, //material-izgotovleniya
                                1520312, //material-tsiferblata
                                1520322, //material-korpusa
                                1520350  //material-oblozhki
                            ]
                        ),
                        true
                    );
                break;
            case 'producing_country_id':
                return self::getProducingCountryIdFromString(
                    self::getCharacteristicsInsalesProduct(
                        $insalesProduct,
                        [
                            1547330 //strana-proizvoditel
                        ]
                    ),
                    true
                );
                break;
            case 'manufacturer_id':
                return self::getManufacturerIdFromString(
                    self::getCharacteristicsInsalesProduct(
                        $insalesProduct,
                        [
                            1547354 //proizvoditel
                        ]
                    ),
                    true
                );
                break;
            case 'character_id':
                return self::getCharacterIdFromString(
                    self::getCharacteristicsInsalesProduct(
                        $insalesProduct,
                        [
                            1520270 //персонажи
                        ]
                    ),
                    true
                );
                break;
            case 'feature_id':
                return self::getFeatureIdFromString(
                    self::getCharacteristicsInsalesProduct(
                        $insalesProduct,
                        [
                            1520255 //особенности
                        ]
                    ),
                    true
                );
                break;
            case 'height':
                return floatval(self::getCharacteristicsInsalesProduct(
                    $insalesProduct,
                    [
                        1520248 //vysota
                    ]
                ));
                break;
            case 'group_id':
                if(empty($insalesProduct->collections_ids)){
                    print_r($insalesProduct->variants[0]->sku);
                };
                $res = self::getGroupInsalesProduct(
                    $insalesProduct->collections_ids,
                    self::getCharacteristicsInsalesProduct($insalesProduct, [1520270]) //character
                );
                return $res;
                break;
            case 'images':
                return self::getImagesInsalesProduct($insalesProduct->images);
                break;

            // Temp variables
            case 'short_description':
                return $insalesProduct->short_description;
                break;
            case 'full_description':
                return $insalesProduct->description;
                break;
            case 'price':
                return $insalesProduct->variants[0]->price;
                break;
            case 'old_price':
                return $insalesProduct->variants[0]->old_price;
                break;
            default:
                return false;
        }
    }

    // Get from Insales product Template Product for Eloquent
    public static function getFromInsalesProduct($insalesProduct)
    {
        $TemplateProduct = new \stdClass();
        $TemplateProduct->sku = self::getInsalesProductParameter($insalesProduct, 'sku');
        $TemplateProduct->barcode1 = self::getInsalesProductParameter($insalesProduct, 'barcode1');
        $TemplateProduct->name_ru = self::getInsalesProductParameter($insalesProduct, 'name_ru');
        $TemplateProduct->weight = self::getInsalesProductParameter($insalesProduct, 'weight');

        $TemplateProduct->markdown = self::getInsalesProductParameter($insalesProduct, 'markdown');

        $TemplateProduct->type_id = self::getInsalesProductParameter($insalesProduct, 'type_id');
        $TemplateProduct->material_id = self::getInsalesProductParameter($insalesProduct, 'material_id');
        $TemplateProduct->producing_country_id = self::getInsalesProductParameter($insalesProduct, 'producing_country_id');
        $TemplateProduct->manufacturer_id = self::getInsalesProductParameter($insalesProduct, 'manufacturer_id');

        $TemplateProduct->height = self::getInsalesProductParameter($insalesProduct, 'height');

        $TemplateProduct->group_id = self::getInsalesProductParameter($insalesProduct, 'group_id');
        $TemplateProduct->category_id = self::getInsalesProductParameter($insalesProduct, 'category_id');

        //array of images
        $TemplateProduct->images = self::getInsalesProductParameter($insalesProduct, 'images');

        //Temp variables
        $TemplateProduct->temp_short_description = self::getInsalesProductParameter($insalesProduct, 'short_description');
        $TemplateProduct->temp_full_description = self::getInsalesProductParameter($insalesProduct, 'full_description');
        $TemplateProduct->temp_price = self::getInsalesProductParameter($insalesProduct, 'price');
        $TemplateProduct->temp_old_price = self::getInsalesProductParameter($insalesProduct, 'old_price');

        $TemplateProduct->character_id = self::getInsalesProductParameter($insalesProduct, 'character_id');
        $TemplateProduct->feature_id = self::getInsalesProductParameter($insalesProduct, 'feature_id');


        return $TemplateProduct;
    }

    public static function createProduct($TemplateProduct, $insales = false, $update = false){
        try{
            if($insales){
                $TemplateProduct = self::getFromInsalesProduct($TemplateProduct);
            };

            //Check if exist the product
            $newProduct = false;
            $Product = Product::where('sku', '=', $TemplateProduct->sku)->first();
            if($Product){
                if(!$update) return false;
            }else{
                $Product = new Product;
                $newProduct = true;
            };

            if(!isset($TemplateProduct->sku)){
                return false;
            };
            $Product->sku = $TemplateProduct->sku;

            $Product->markdown = $TemplateProduct->markdown??false;

            if(isset($TemplateProduct->barcode1)){
                $Product->barcode1 = $TemplateProduct->barcode1;
            };
            if(isset($TemplateProduct->barcode2)){
                $Product->barcode2 = $TemplateProduct->barcode2;
            };
            if(isset($TemplateProduct->barcode3)){
                $Product->barcode3 = $TemplateProduct->barcode3;
            };
            if(isset($TemplateProduct->barcode4)){
                $Product->barcode4 = $TemplateProduct->barcode4;
            };

            if(isset($TemplateProduct->gtin)){
                $Product->gtin = $TemplateProduct->gtin;
            };
            if(isset($TemplateProduct->name_ru)){
                $Product->name_ru = $TemplateProduct->name_ru;
            };
            if(isset($TemplateProduct->name_eng)){
                $Product->name_eng = $TemplateProduct->name_eng;
            };

            //For the parameters need to create something date base reference
            if(isset($TemplateProduct->type_id)){
                $Product->type_id = $TemplateProduct->type_id;
            };
            if(isset($TemplateProduct->group_id)){
                $Product->group_id = $TemplateProduct->group_id;
            };
            if(isset($TemplateProduct->category_id)){
                $Product->category_id = $TemplateProduct->category_id;
            };

            // updating only where no value
            if(isset($TemplateProduct->weight) and !$Product->weight)
            {
                $Product->weight = $TemplateProduct->weight;
            };
            if(isset($TemplateProduct->box_length) and !$Product->box_length)
            {
                $Product->box_length = $TemplateProduct->box_length;
            };
            if(isset($TemplateProduct->box_width) and !$Product->box_width)
            {
                $Product->box_width = $TemplateProduct->box_width;
            };
            if(isset($TemplateProduct->box_height) and !$Product->box_height)
            {
                $Product->box_height = $TemplateProduct->box_height;
            };
            // /updating only where no value

            if(isset($TemplateProduct->height)){
                $Product->height = $TemplateProduct->height;
            };
            if(isset($TemplateProduct->material_id)){
                $Product->material_id = $TemplateProduct->material_id;
            };

            if(isset($TemplateProduct->producing_country_id)){
                $Product->producing_country_id = $TemplateProduct->producing_country_id;
            };
            if(isset($TemplateProduct->manufacturer_id)){
                $Product->manufacturer_id = $TemplateProduct->manufacturer_id;
            };

            // Temp variables
            if(isset($TemplateProduct->temp_short_description)){
                $Product->temp_short_description = $TemplateProduct->temp_short_description;
            };
            if(isset($TemplateProduct->temp_full_description)){
                $Product->temp_full_description = $TemplateProduct->temp_full_description;
            };

            if($newProduct) // price only for new products
            {
                if(isset($TemplateProduct->temp_price))
                    $Product->temp_price = $TemplateProduct->temp_price;

                if(isset($TemplateProduct->temp_old_price))
                    $Product->temp_old_price = $TemplateProduct->temp_old_price;
            }

            if(isset($TemplateProduct->character_id)){
                $Product->character_id = $TemplateProduct->character_id;
            }

            if(isset($TemplateProduct->feature_id)){
                $Product->feature_id = $TemplateProduct->feature_id;
            }


            $Product->save();

            // If no one images, then take its
            if(count($Product->images) === 0){
                if(isset($TemplateProduct->images)){
                    foreach($TemplateProduct->images as $key => $Image){
                        self::productImageAdd($Image->url, $Product->id, $Image->position ?? $key);
                    };
                };
            };

            return $Product->id;
        }catch(\Exception $e){
            self::log('error', 'createProduct', 'catch', $e);
            // do task when error
            echo $e->getMessage();   // insert query
            return false;
        }
    }

    public static function createProducts($arrayTemplateProducts, $insales = false, $update = false)
    {
        dd('Now closed 2022-04-21');

        $countToCreate = count($arrayTemplateProducts);
        print_r("Total to create $countToCreate".PHP_EOL);
        foreach($arrayTemplateProducts as $key => $templateProduct){
            if(!self::createProduct($templateProduct, $insales, $update))
            {
                self::log('error', 'createProducts', 'catch', $templateProduct);
            }
            print_r($key.' of '.$countToCreate."\r");
        };
        print_r(self::$test.' reached'.PHP_EOL);
        return count($arrayTemplateProducts);
    }

    public static function getSorting($request){
        $Sorting = new \stdClass();
        $Sorting->sku = $request->input('sorting-sku', NULL);
        $Sorting->name_ru = $request->input('sorting-name_ru', NULL);
        $Sorting->name_eng = $request->input('sorting-name_eng', NULL);
        $Sorting->type_id = $request->input('sorting-type_id', NULL);
        $Sorting->group_id = $request->input('sorting-group_id', NULL);
        $Sorting->group_id = $request->input('sorting-group_id', NULL);
        $Sorting->weight = $request->input('sorting-weight', NULL);
        $Sorting->box_length = $request->input('sorting-box_length', NULL);
        $Sorting->box_width = $request->input('sorting-box_width', NULL);
        $Sorting->box_height = $request->input('sorting-box_height', NULL);
        $Sorting->box_height = $request->input('sorting-box_height', NULL);
        $Sorting->height = $request->input('sorting-height', NULL);
        $Sorting->material_id = $request->input('sorting-material_id', NULL);
        $Sorting->producing_country_id = $request->input('sorting-producing_country_id', NULL);
        $Sorting->manufacturer_id = $request->input('sorting-manufacturer_id', NULL);
        return $Sorting;
    }

    public static function remove($productId)
    {
        $Product = Product::where('id', '=', $productId)->firstOrFail();
        $ProductsRemoveHistory = new ProductsRemoveHistory;
        $ProductsRemoveHistory->sku = $Product->sku;
        $ProductsRemoveHistory->user_id = auth()->user()->id??0;
        $ProductsRemoveHistory->product_id = $productId;
        if($ProductsRemoveHistory->save())
            return $Product->delete();
        return false;
    }

    public static function restore($productId){
        if(empty($productId)){
            die('Do not found ID 2');
        };
        $Product = Product::where('id', '=', $productId)->firstOrFail();
        $Product->state = 1;
        return $Product->save();
    }

    public static function updateShopProductsReloads($shopId, $productId, $reload)
    {
        $ShopProductsReload = ShopProductsReload::firstOrNew([
            'shop_id' => $shopId,
            'product_id' => $productId,
        ]);
        if($reload) $ShopProductsReload->user_id = Users::getCurrent()->id;
        $ShopProductsReload->reload = (bool) $reload;
        $ShopProductsReload->save();
    }

    public static function updateSystemProductsStops($Product, $systemProductsStops)
    {
        foreach($systemProductsStops as $sPSField)
        {
            $proto = true;
            foreach($sPSField as $field)
            {
                if(!is_null($field)) $proto = false;
            }
            if($proto) continue;

            $sPSField = (object) $sPSField;

            if(isset($sPSField->id)){
                $SystemsProductsStop = SystemsProductsStop::where('id', $sPSField->id)->firstOrFail();
            }else{
                if(!empty($systemProductsStops)) $SystemsProductsStop = new SystemsProductsStop;
            }

            $SystemsProductsStop->user_id = Auth::user()->id??0;
            $SystemsProductsStop->product_id = $Product->id;

            if(isset($sPSField->orders_type_shop_id)){
                $SystemsProductsStop->orders_type_shop_id = $sPSField->orders_type_shop_id;
            }else{
                $SystemsProductsStop->orders_type_shop_id = NULL;
            }

            $SystemsProductsStop->stop_stock = isset($sPSField->stop_stock)?1:0;
            if(isset($sPSField->stop_stock_until_date))
            {
                $stop_stock_until_time = $sPSField->stop_stock_until_time??'00:00';
                $SystemsProductsStop->stop_stock_until =
                    $sPSField->stop_stock_until_date . ' ' . $stop_stock_until_time;
            }else{
                $SystemsProductsStop->stop_stock_until = NULL;
            }

            $SystemsProductsStop->stop_price = isset($sPSField->stop_price)?1:0;
            if(isset($sPSField->stop_price_until_date))
            {
                $stop_price_until_time = $sPSField->stop_price_until_time??'00:00';

                $SystemsProductsStop->stop_price_until =
                    $sPSField->stop_price_until_date . ' ' . $stop_price_until_time;
            }else{
                $SystemsProductsStop->stop_price_until = NULL;
            }

            $SystemsProductsStop->stop_image = isset($sPSField->stop_image)?1:0;
            if(isset($sPSField->stop_image_until_date))
            {
                $stop_image_until_time = $sPSField->stop_image_until_time??'00:00';

                $SystemsProductsStop->stop_image_until =
                    $sPSField->stop_image_until_date . ' ' . $stop_image_until_time;
            }else{
                $SystemsProductsStop->stop_image_until = NULL;
            }

            $SystemsProductsStop->force_stock = isset($sPSField->force_stock);

            $SystemsProductsStop->ozon_auto_nth_for_free = isset($sPSField->ozon_auto_nth_for_free);
            $SystemsProductsStop->ozon_auto_sum_condition = isset($sPSField->ozon_auto_sum_condition);
            $SystemsProductsStop->ozon_auto_action_deactivate = isset($sPSField->ozon_auto_action_deactivate);
            $SystemsProductsStop->ozon_auto_discount = isset($sPSField->ozon_auto_discount);

            $SystemsProductsStop->ozon_auto_action_max_discount = $sPSField->ozon_auto_action_max_discount;
            $SystemsProductsStop->ozon_auto_action_max_discount_value_type_id = $sPSField->ozon_auto_action_max_discount_value_type_id;

            $SystemsProductsStop->save();
        }
    }

    public static function getSystemsProductsStopResult(Product $Product, $ordersTypeShopId)
    {
        $stop = new \stdClass();
        $stop->price = false;
        $stop->stock = false;
        $stop->force_stock = false;
        $stop->image = false;

        if($Product and $ordersTypeShopId)
        {
            $systemsProductsStops = self::getSystemsProductsStops($Product, $ordersTypeShopId);

            if($systemsProductsStops)
            {
                foreach($systemsProductsStops as $SystemsProductsStop)
                {
                    if($SystemsProductsStop->stop_price) $stop->price = true;
                    if($SystemsProductsStop->stop_stock) $stop->stock = true;
                    if($SystemsProductsStop->stop_image) $stop->image = true;
                    $stop->force_stock = $SystemsProductsStop->force_stock;
                };
            }
        };

        return $stop;
    }

    public static function checkSystemsProductsStopToUnset()
    {
        $systemsProductsStops = SystemsProductsStop::with('product', 'product.amounts')->where(function($q){
            $q->where('stop_price', 1)
                ->orWhere('stop_stock', 1)
                ->orWhere('stop_image', 1)
                ->orWhere('ozon_auto_nth_for_free', 1)
                ->orWhere('ozon_auto_sum_condition', 1)
                ->orWhere('ozon_auto_action_deactivate', 1)
                ->orWhere('ozon_auto_discount', 1);
        })->get();

        $CarbonNow = Carbon::now();

        foreach($systemsProductsStops as $SystemsProductsStop)
        {
            if($SystemsProductsStop->stop_price and $SystemsProductsStop->stop_price_until)
            {
                if(Carbon::parse($SystemsProductsStop->stop_price_until) <= $CarbonNow)
                {
                    $SystemsProductsStop->stop_price = 0;
                    $SystemsProductsStop->user_id = 0;
                    $SystemsProductsStop->save();
                }
            }

            if($SystemsProductsStop->stop_stock and $SystemsProductsStop->stop_stock_until)
            {
                if(Carbon::parse($SystemsProductsStop->stop_stock_until) <= $CarbonNow)
                {
                    $SystemsProductsStop->stop_stock = 0;
                    $SystemsProductsStop->user_id = 0;
                    $SystemsProductsStop->save();
                }
            }

            if($SystemsProductsStop->stop_image and $SystemsProductsStop->stop_image_until)
            {
                if(Carbon::parse($SystemsProductsStop->stop_image_until) <= $CarbonNow)
                {
                    $SystemsProductsStop->stop_image = 0;
                    $SystemsProductsStop->user_id = 0;
                    $SystemsProductsStop->save();
                }
            }

            // check if Product = zero quantity
            $needRemoveStop = false;
            if($SystemsProductsStop->product)
            {
                if($SystemsProductsStop->orders_type_shop_id) // if set shopId
                {
                    $amounts = $SystemsProductsStop->product->shopAmounts($SystemsProductsStop->orders_type_shop_id)->amounts->available;
                }else{
                    $amounts = $SystemsProductsStop->product->amounts->sum('available');
                }
                if($amounts === 0) $needRemoveStop = true;
            }else{
                $needRemoveStop = true; // unknown product
            }

            if($needRemoveStop)
            {
                //$SystemsProductsStop->delete(); // ??? since 2022-10-26

                $SystemsProductsStop->stop_stock = 0;
                $SystemsProductsStop->ozon_auto_nth_for_free = 0;
                $SystemsProductsStop->ozon_auto_sum_condition = 0;
                $SystemsProductsStop->ozon_auto_action_deactivate = 0;
                $SystemsProductsStop->ozon_auto_discount = 0;
                $SystemsProductsStop->save();
            }
        }
    }

    public static function setSystemsProductStop(Product $Product, $ordersTypeShopId, $comment = false)
    {
        $SystemsProductsStop = SystemsProductsStop::firstOrNew([
            'product_id' => $Product->id,
            'orders_type_shop_id' => $ordersTypeShopId
        ]);
        $SystemsProductsStop->user_id = 0;
        $SystemsProductsStop->stop_stock = 1;
        $SystemsProductsStop->stop_stock_until = '2024-01-01 00:00:00'; // there need change to new year
        $SystemsProductsStop->stop_stock_auto_settled = 1;
        if($comment) $SystemsProductsStop->comment = $comment;
        $SystemsProductsStop->save();
    }

    public static function getSystemsProductsStops(Product $Product, $ordersTypeShopId)
    {
        $systemsProductsStops = SystemsProductsStop::where('product_id', $Product->id);

        $systemsProductsStops->where(function($q) use ($ordersTypeShopId)
        {
            $q->where('orders_type_shop_id', $ordersTypeShopId)
                ->orWhere('orders_type_shop_id', NULL);
        });

        return $systemsProductsStops->get();
    }

    public static function getCommission(Product $Product, $systemId, $orderDate = false)
    {
        $defaultCommissionTypeId = SystemsCommissionType::where('default', 1)->firstOrFail();
        $systemsCommissionTypeIds = [$defaultCommissionTypeId->id];
        //if($ProductType = $Product->type) $systemsCommissionTypeIds[] = $ProductType->systems_commission_type_id;

        if($orderDate){
            $orderDate = Carbon::parse($orderDate);
        }else{
            $orderDate = Carbon::now();
        }
        $orderDate = $orderDate->setTimezone('Europe/Moscow')->format('Y-m-d');

        $SystemsCommission = SystemsCommission::where('system_id', $systemId);

        $SystemsCommission->where(function($query) use ($systemsCommissionTypeIds)
        {
            $query->whereIn(
                'commission_type_id', $systemsCommissionTypeIds
            )->orWhereNull('commission_type_id');
        });

        $SystemsCommission->where(function($query) use ($orderDate)
        {
            $query->where(function($query2) use ($orderDate) {
                $query2->where([['used_since', '<=', $orderDate]])->orWhereNull('used_since');
            })
            ->where(function($query2) use ($orderDate)
            {
                $query2->where([['used_to', '>=', $orderDate]])->orWhereNull('used_to');
            });
        });

        $SystemsCommission->orderBy('commission_type_id', 'DESC');

        return $SystemsCommission->first();
    }

    public static function getProductNameForOzon($Product)
    {
        $badWords = array("!", "?", '№', "#", "«", '»', "'", '"', '`');
        return str_ireplace($badWords, "", $Product->name_ru);
    }

    public static function getProductModelForOzon($Product)
    {
        $badWords = array("Кукла ", "Набор кукол ", ' набор кукол', "(Mattel) для кукол", "(Mattel)", "кукол-", "Набор одежды для ", ' - Игровой набор', ", Игровой набор");
        return str_ireplace($badWords, "", $Product->name_ru);
    }

    public static function getProductDescriptionForOzon($Product)
    {
        $description = strip_tags(trim($Product->temp_short_description), '<br>');
        if(mb_strlen($description) === 0) $description = 'Описание на данный момент отсутствует.';
        return $description;
    }

    public static function reloadImagesFromInsales($products)
    {
        $insalesProducts = (new InsalesApi())->getProducts();

        foreach($products as $Product)
        {
            $productImages = ProductImage::where('product_id', $Product->id)->get();
            if(count($productImages) > 0)
            {
                foreach($productImages as $ProductImage)
                {
                    $ProductImage->delete();
                }
            }


            foreach ($insalesProducts as $InsalesProduct)
            {
                if($InsalesProduct->variants[0]->sku === $Product->sku)
                {
                    Products::createProducts([$InsalesProduct], true, true);
                }
            }
        }
    }

    public static function removeUnusedProductsImages()
    {
        $products = Product::all();
        $productsCount = count($products);
        foreach($products as $productKey => $Product)
        {
            $filesInDir = Storage::disk('public')->files('images/products/'.$Product->id);

            $productImages = $Product->images;
            if(count($productImages) > 0)
            {
                foreach($productImages as $ProductImage)
                {
                    if(($key = array_search($ProductImage->LocalPath, $filesInDir)) !== false)
                    {
                        // image ok
                        unset($filesInDir[$key]);
                    }else{
                        // need to add
                        echo $Product->id.' need to add'.PHP_EOL;
                    }
                }
            }

            if(count($filesInDir) > 0) // this array to remove files
            {
                Storage::disk('public')->delete($filesInDir);
            }

            print_r($productKey.' of '.$productsCount."\r");

            //dd('exit code');
        }

    }
    /*
     public static function getProductImageFromUrl($url, $productId = 'undefended'){
        $path = 'images/products/'.$productId;
        $info = pathinfo($url);
        $contents = file_get_contents($url);
        if($contents !== false) {
            $file = '/tmp/' . $info['basename'];
            file_put_contents($file, $contents);
            $fileName = Storage::disk('public')->putFile($path, new File($file));
            unlink($file);
            //return asset('storage/'.$fileName);
            return basename($fileName);
        };
        return false;
    }
     */

    public static function setProductShopCategory($shopId, $productId, $userId, $shopCategoryId)
    {
        if($shopCategoryId)
        {
            $ShopProductsCategory = ShopProductsCategory::where([
                'shop_id' => $shopId,
                'shop_category_id' => $shopCategoryId,
            ])->first();

            if($ShopProductsCategory)
            {
                $ProductShopCategory = ProductShopCategory::firstOrNew([
                    'shop_id' => $shopId,
                    'product_id' => $productId,
                ]);

                if($ProductShopCategory->shop_products_category_id !== $ShopProductsCategory->id)
                    $ProductShopCategory->user_id = $userId;

                $ProductShopCategory->shop_products_category_id = $ShopProductsCategory->id;
                $ProductShopCategory->save();
            }
        }
    }

    public static function shopProductsReload($shopId = 10001, $products = [])
    {
        if(!$products)
            $products = Product::whereHas('reloads', function($qReload) use ($shopId)
            {
                $qReload->where([
                    ['shop_id', $shopId],
                    ['reload', 1],
                ]);
            })->get();

        if(count($products) === 0) return 'Nothing found';

        $OzonApi = Ozon::getOzonApiByShopId($shopId);

        // remove products
        //Ozon::pseudoRemoveProducts($shopId, $products);

        //sleep(30); // sleep to wait Ozon operations

        // upload products if it doesn't have
        foreach($products as $Product)
        {
            $reloaded = false;
            if(!$OzonApi->getProductInfoV2($Product->sku))
            {
                if($TypeShopProduct = TypeShopProduct::where([
                    'type_shop_id' => $shopId,
                    'product_id' => $Product->id,
                ])->first())
                {
                    $TypeShopProduct->delete();
                }

                try{
                    $OzonApi->uploadProductsV2([$Product]);
                    $reloaded = true;
                }catch(\Exception $e){}
            }else
            {
                $reloaded = false;
                dump("Product $Product->sku isset in shop");
            }

            if($Reload = $Product->reload($shopId))
            {
                $Reload->reload = false;
                $Reload->reloaded_datetime = Carbon::now();
                $Reload->reloaded_user_id = $Reload->user_id;
                $Reload->comment = $reloaded?null:'Ошибка загрузки, товар уже есть на Озон.';
                $Reload->save();

                if(!$reloaded)
                {
                    $Shop = Shops::getShop($shopId);
                    Notifications::new(
                        "Перезагрузка товара $Shop->name",
                        "Ошибка перезагрузки, товар <a href = '$Product->editPath#collapse-products-synchro'>$Product->sku</a> уже существует на <a target = '_blank' href = 'https://seller.ozon.ru/app/products?filter=all&search=$Product->sku'>площадке</a>.",
                        [$Reload->user_id]
                    );
                }
            }
        }

        return true;
    }

    public static function getProductByShopId($shopId, $shopProductId)
    {
        $TypeShopProduct = TypeShopProduct::where([
            ['type_shop_id', $shopId],
            ['shop_product_id', $shopProductId],
        ])->first();

        return $TypeShopProduct->product??false;
    }


    public static function generateBarcode()
    {
        $key = uniqid();
        $md5code=hexdec(md5($key));
        $code = substr(number_format($md5code,13,'',''),0,12);

        if(Product::where(function($q) use ($code)
        {
            $q
                ->where('dummy_barcode', $code)
                ->orWhere('barcode1', $code)
                ->orWhere('barcode2', $code)
                ->orWhere('barcode3', $code)
                ->orWhere('barcode4', $code);
        })->count() > 0)
        {
            $code = self::generateDummyBarcode();
        }

        return $code;
    }

    public static function generateDummyBarcode()
    {
        $key = uniqid();
        $md5code=hexdec(md5($key));
        $code = substr(number_format($md5code,13,'',''),0,12);

        if(Product::where('dummy_barcode', $code)->count() > 0)
        {
            $code = self::generateDummyBarcode();
        }

        return $code;

    }


    public static function productImagesAddFromForm($files, $productId): bool
    {
        $allAdded = true;
        $fromKey = 1;

        if($files)
        {
            if($LastProductImage = ProductImage::where('product_id', $productId)->orderBy('position', 'DESC')->first())
                $fromKey = $LastProductImage->position;

            foreach($files as $key => $File)
            {
                $path = 'images/products/'.$productId;
                $FileImage = Storage::disk('public')->putFile($path, new File($File));
                $baseName = basename($FileImage);

                $ProductImage = new ProductImage;
                $ProductImage->filename = $baseName;
                $ProductImage->product_id = $productId;
                $ProductImage->position = $fromKey + $key;
                if(!$ProductImage->save()) $allAdded = false;
            }
        }

        return $allAdded;
    }

    public static function saveImagesPositions($imagesPositions)
    {
        foreach($imagesPositions as $imageId => $position)
        {
            if($ProductImage = ProductImage::where('id', $imageId)->first())
            {
                $ProductImage->position = (int) $position;
                $ProductImage->save();
            }
        }
    }

    public static function saveProductShopsImageRules($productShopsImageRules, $productId)
    {
        foreach($productShopsImageRules as $productShopsImageRuleId => $ProductShopsImageRuleForm)
        {
            $ProductImagesShopRule = ProductImagesShopRule::where('id', $productShopsImageRuleId)->first();
            $ProductImagesShopRule->shop_id = $ProductShopsImageRuleForm['shop_id']?:NULL;
            $ProductImagesShopRule->save();

            $position = 0;
            foreach($ProductShopsImageRuleForm['images'] as $imageId => $info)
            {
                $position++;
                $hide = isset($info['hide']);
                $ProductImagesShopRulesPosition = ProductImagesShopRulesPosition::firstOrNew([
                    'product_images_shop_rule_id' => $productShopsImageRuleId,
                    'image_id' => $imageId,
                ]);
                $ProductImagesShopRulesPosition->position = $position;
                $ProductImagesShopRulesPosition->hide = $hide;
                $ProductImagesShopRulesPosition->save();
            }
        }
    }

    public static function getShopImages($Product, $shopId)
    {
        $ProductImagesShopRule = ProductImagesShopRule::where(function($q) use ($shopId)
        {
            $q->where('shop_id', $shopId)->orWhereNull('shop_id');
        })->where('product_id', $Product->id)->first();

        if($ProductImagesShopRule)
        {
            $images = [];
            foreach($ProductImagesShopRule->positions as $ProductImagesShopRulePosition)
            {
                if($ProductImage = ProductImage::where('id', $ProductImagesShopRulePosition->image_id)->first())
                {
                    if(!$ProductImagesShopRulePosition->hide)
                    {
                        $images[] = $ProductImage;
                    }
                }
            }
        }else
        {
            $images = $Product->images;
        }

        return $images;
    }


    public static function getRecentlyUpdatedProduct($shopId, $days = 2)
    {
        $products = Product::whereHas('typeShopProducts', function ($q) use ($shopId)
        {
            $q->where('type_shop_id', $shopId);
        })
            ->where(function($q) use ($days)
            {
                $q
                    ->where('updated_at', '>=', Carbon::now()->subDays($days))
                    ->orWhereHas('images', function($q) use ($days)
                    {
                        $q
                            ->where('updated_at', '>=', Carbon::now()->subDays($days))
                            ->orWhere('created_at', '>=', Carbon::now()->subDays($days));
                    })
                    ->orWhereHas('imagesShopRules', function($q) use ($days)
                    {
                        $q->whereHas('positions', function($q) use ($days)
                        {
                            $q->where('updated_at', '>=', Carbon::now()->subDays($days))
                                ->orWhere('created_at', '>=', Carbon::now()->subDays($days));
                        });
                    });
            })
            ->inRandomOrder()->get();

        return $products;
    }

    public static function saveProductsNames($productNames, $Product)
    {
        if($productNames and is_array($productNames))
        {
            foreach($productNames as $shopId => $productName)
            {
                $ProductsName = ProductsName::where([
                    ['shop_id', $shopId],
                    ['product_id', $Product->id],
                ])->first();

                if($productName)
                {
                    if(!$ProductsName)
                    {
                        $ProductsName = new ProductsName();
                        $ProductsName->shop_id = $shopId;
                        $ProductsName->product_id = $Product->id;
                    }
                    $ProductsName->title = $productName;
                    $ProductsName->save();
                }else
                {
                    if($ProductsName) $ProductsName->delete();
                }
            }
        }
    }

    public static function getName($Product, $shopId)
    {
        $name = $Product->name_ru;
        if(
            ($ProductsName = ProductsName::where([
                ['shop_id', $shopId],
                ['product_id', $Product->id],
            ])->first())
                and
            $ProductsName->title
        ) $name = $ProductsName->title;

        return $name;
    }

    public static function saveProductsShopBoxSizes($shopBoxSizes, $productId)
    {
        if($shopBoxSizes and is_array($shopBoxSizes))
        {
            foreach($shopBoxSizes as $shopId => $sizes)
            {
                $ShopProductsSize = ShopProductsSize::firstOrNew([
                    'shop_id' => $shopId,
                    'product_id' => $productId,
                ]);
                $ShopProductsSize->box_weight = (int) $sizes['box_weight'];
                $ShopProductsSize->box_length = (int) $sizes['box_length'];
                $ShopProductsSize->box_width = (int) $sizes['box_width'];
                $ShopProductsSize->box_height = (int) $sizes['box_height'];
                $ShopProductsSize->save();
            }
        }
    }

    public static function saveProductsShopPrices($shopPrices, $productId)
    {
        if($shopPrices and is_array($shopPrices))
        {
            foreach($shopPrices as $shopId => $prices)
            {
                if($prices['price'] or $prices['old_price'])
                {
                    $ProductsShopPrice = ProductsShopPrice::firstOrNew([
                        'shop_id' => $shopId,
                        'product_id' => $productId,
                    ]);

                    $price = $prices['price']?((int) $prices['price']):null;
                    $old_price = $prices['old_price']?((int) $prices['old_price']):null;

                    $ProductsShopPrice->price = $price;
                    $ProductsShopPrice->old_price = $old_price;
                    $ProductsShopPrice->user_id = auth()->user()->id??0;
                    $ProductsShopPrice->save();
                }else
                {
                    // delete if it has
                    ProductsShopPrice::where([
                        ['shop_id', $shopId],
                        ['product_id', $productId],
                    ])->delete();
                }
            }
        }
    }

}
