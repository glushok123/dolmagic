<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\ShopByGroup;
use App\Eloquent\Order\OrdersTypeShop;

class GroupShop extends Model
{
    use CrudTrait;
    use HasFactory;

    /**
     * @var string
     */
    protected $table = 'group_shops';

    /**
     * @var array
     */
    protected $fillable = [
        'name',
    ];

    public function shops()
    {
        return $this->hasMany(ShopByGroup::class, 'group_id', 'id');
    }

    public function getShops() {
        $arrayShopsId = ShopByGroup::where('group_id', $this->id)->pluck('shop_id')->toArray();
        $shops = OrdersTypeShop::select('id', 'name')
            ->whereIn('id', $arrayShopsId)
            ->get();
        $text = '';
        foreach ($shops as $shop) {
            $text = $text . $shop->name . ', ';
        }
        return $text;
    }
}