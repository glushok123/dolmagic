<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\AdvertisingNetworkRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class AdvertisingNetworkCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class AdvertisingNetworkCrudController extends CrudController
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
        CRUD::setModel(\App\Models\AdvertisingNetwork::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/advertising-network');
        CRUD::setEntityNameStrings('Рекламную сеть', 'Рекламная сеть');
    }

    /**
     * Define what happens when the List operation is loaded.
     * 
     * @see  https://backpackforlaravel.com/docs/crud-operation-list-entries
     * @return void
     */
    protected function setupListOperation()
    {
       // CRUD::column('name');
        CRUD::addColumn([
            'label'     => 'Название',
            'name'      => 'name',
        ]);
        CRUD::addColumn([
            'name'      => 'logo', // The db column name
            'label'     => 'логотип', // Table column heading
            'type'      => 'image',
             'prefix' => 'public/uploads/',
            // image from a different disk (like s3 bucket)
             //'disk'   => 'uploads', 
            // optional width/height if 25px is not ok with you
             'height' => 'auto',
             'width'  => '130px',
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
        CRUD::setValidation(AdvertisingNetworkRequest::class);

        //CRUD::field('name');
        //CRUD::field('logo');
        CRUD::addField([
            'label'     => 'Название',
            'name'      => 'name',
        ]);

        CRUD::addField([
            'name' => 'logo',
            'type' => 'upload',
            'upload' => true,
            'disk' => 'uploads'
            //'type' => 'file_image',
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
