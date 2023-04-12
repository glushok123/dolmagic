<?php

namespace App\Models;

use Backpack\CRUD\app\Models\Traits\CrudTrait;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
        return $this->BelongsToMany('App\Models\ShopByGroup');
    }
}