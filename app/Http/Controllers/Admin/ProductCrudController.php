<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\ProductRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class ProductCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class ProductCrudController extends CrudController
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
        $user = backpack_auth()->user();

        if ($user->type == 0) {
            abort(404);
        }

        CRUD::setModel(\App\Models\Product::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/product');
        CRUD::setEntityNameStrings('товар', 'Товары');

        $this->crud->addClause('where', 'user_id', '=', $user->id);
        //$this->crud->addClause('orWhere', 'min_price', '>', 0);
    }

    /**
     * Define what happens when the List operation is loaded.
     * 
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     * @return void
     */
    protected function setupListOperation()
    {
        CRUD::addColumn([
            'name' => 'name',
            'label' => 'Название'
        ]);

        CRUD::addColumn([
            'name' => 'min_price',
            'label' => 'цена (min)',
        ]);

        CRUD::addColumn([
            'name' => 'max_price',
            'label' => 'цена (max)',
        ]);

        CRUD::addColumn([
            'name' => 'type_text',
            'label' => 'Состояние',
            'type' => 'text'
        ]);
    }

    /**
     * Define what happens when the Create operation is loaded.
     * 
     * @see https://backpackforlaravel.com/docs/crud-operation-create
     * @return void
     */
    protected function setupCreateOperation()
    {
        CRUD::setValidation(ProductRequest::class);
        $user = backpack_auth()->user();

        CRUD::addField([
            'name' => 'name',
            'label' => 'Название'
        ]);

        CRUD::addField([
            'name' => 'min_price',
            'label' => 'цена (min)',
        ]);

        CRUD::addField([
            'name' => 'max_price',
            'label' => 'цена (max)',
        ]);

        CRUD::addField([
            'name' => 'type',
            'label' => 'Б/У',
            'type' => 'checkbox',
        ]);

        CRUD::addField([
            'name' => 'user_id',
            'value' => $user->id,
            'type' => 'hidden',
        ]);
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