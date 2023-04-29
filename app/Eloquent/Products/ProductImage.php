<?php
namespace App\Eloquent\Products;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    protected $appends = array('url');

    //Attributes

    public function getUrlAttribute()
    {
        $url = asset('storage/images/products/'.$this->product_id.'/'.$this->filename);
        $url = str_replace('s1.crmdollmagic.ru', 'crmdollmagic.ru', $url);
        $url = str_replace('s2.crmdollmagic.ru', 'crmdollmagic.ru', $url);
        $url = str_replace('s3.crmdollmagic.ru', 'crmdollmagic.ru', $url);
        $sUrl = str_replace('http://', 'https://', $url);
        return $sUrl;
    }

    public function getHttpShortLinkAttribute()
    {
        $arr = explode('.', $this->filename);
        $format = $arr[count($arr) - 1];
        if($format === $this->filename) $format = 'jpg'; // jpg as default

        $url = route('short-link.product-image', [
            'id' => $this->id,
            'format' => $format,
        ]);

        $url = str_replace('s1.crmdollmagic.ru', 'crmdollmagic.ru', $url);
        $url = str_replace('s2.crmdollmagic.ru', 'crmdollmagic.ru', $url);
        $url = str_replace('s3.crmdollmagic.ru', 'crmdollmagic.ru', $url);
        $url = str_replace('https://', 'http://', $url);

        return $url;
    }

    public function getShortLinkAttribute()
    {
        $arr = explode('.', $this->filename);
        $format = $arr[count($arr) - 1];
        if($format === $this->filename) $format = 'jpg'; // jpg as default

        $url = route('short-link.product-image', [
            'id' => $this->id,
            'format' => $format,
        ]);

        $url = str_replace('s1.crmdollmagic.ru', 'crmdollmagic.ru', $url);
        $url = str_replace('s2.crmdollmagic.ru', 'crmdollmagic.ru', $url);
        $url = str_replace('s3.crmdollmagic.ru', 'crmdollmagic.ru', $url);
        $sUrl = str_replace('http://', 'https://', $url);

        return $sUrl;
    }

    public function getShortLinkAwayAttribute() // didn't use?
    {
        return str_replace(
            'https://crmdollmagic.ru/',
            'https://shop.dollmagic.ru/loadUrl.php?url=',
            $this->ShortLink
        );
    }

    public function getLocalPathAttribute()
    {
        return 'images/products/'.$this->product_id.'/'.$this->filename;
    }
}
