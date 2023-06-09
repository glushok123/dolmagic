<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\GroupShopRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use DB;

/**
 * Class GroupShopCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class GroupShopCrudController extends CrudController
{
    use \Backpack\CRUD\app\Http\Controllers\Operations\ListOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\CreateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\UpdateOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\DeleteOperation;
    use \Backpack\CRUD\app\Http\Controllers\Operations\ShowOperation;

    /**
     * Configure the CrudPanel object. Apply settings to all operations.
     * 
     * @return void
     */
    public function setup()
    {
        CRUD::setModel(\App\Models\GroupShop::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/group-shop');
        CRUD::setEntityNameStrings('group shop', 'group shops');
    }

    /**
     * Define what happens when the List operation is loaded.
     * 
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     * @return void
     */
    protected function setupListOperation()
    {
        CRUD::column('name');

        CRUD::addColumn([
            'name'  => 'Магазины',
            'label' => 'Магазины', // Table column heading
            'type'  => 'model_function',
            'function_name' => 'getShops', // the method in your Model
            'visibleInTable'  => true, // no point, since it's a large text
            'visibleInShow'   => true,
            'limit'=> 1000,
            'escaped' => false,
        ]);

        /**
         * Columns can be defined using the fluent syntax or array syntax:
         * - CRUD::column('price')->type('number');
         * - CRUD::addColumn(['name' => 'price', 'type' => 'number']); 
         */
    }

    /**
     * Define what happens when the Create operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     * @return void
     */
    protected function setupCreateOperation()
    {

        $shopName =  DB::table('orders_type_shops')
            ->select('id', 'name')
            ->where('archive', '!=', 1)
            ->orWhereNull('archive')
            ->pluck('name', 'id')
            ->toArray();

        CRUD::setValidation(GroupShopRequest::class);

        CRUD::field('name');

        CRUD::addField([
            'label'     => 'Магазины',
            'type'      => 'checklist_hidden',
            'name'      => 'shops',
            'entity'    => 'shops',
            'attribute' => 'name',
            'model'     => "App\Models\Shop",
            'pivot'     => true,
            'shopName'     => $shopName,
        ]);

        /**
         * Fields can be defined using the fluent syntax or array syntax:
         * - CRUD::field('price')->type('number');
         * - CRUD::addField(['name' => 'price', 'type' => 'number'])); 
         */
    }

    /**
     * Define what happens when the Update operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-update
     * @return void
     */
    protected function setupUpdateOperation()
    {
        $this->setupCreateOperation();
    }
}
