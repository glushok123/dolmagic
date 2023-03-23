<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\HistoryUserRequestRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;
use App\Models\Product;

/**
 * Class HistoryUserRequestCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class HistoryUserRequestCrudController extends CrudController
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
        CRUD::setModel(\App\Models\HistoryUserRequest::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/history-user-request');
        CRUD::setEntityNameStrings('запрос', 'История запросов');

        if ($user->type == 0) {
            $this->crud->addClause('where', 'user_id', '=', $user->id);
        }

        if ($user->type == 1) {
            $products = Product::where('user_id', $user->id)->get();

            foreach($products as $product) {
                $this->crud->query->orWhere(function($query) use ($product) { 
                    $query->where('name', 'like', '%' . $product->name . '%');
                    $query->where('type', '=', $product->type);
                    $query->whereBetween('min_price', [$product->min_price, $product->max_price]);
                    $query->orWhereBetween('max_price', [$product->min_price, $product->max_price]);
                });
            }
        }
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
        CRUD::setValidation(HistoryUserRequestRequest::class);
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
