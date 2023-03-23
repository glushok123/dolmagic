<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\BundleRequest;
use Backpack\CRUD\app\Http\Controllers\CrudController;
use Backpack\CRUD\app\Library\CrudPanel\CrudPanelFacade as CRUD;

/**
 * Class BundleCrudController
 * @package App\Http\Controllers\Admin
 * @property-read \Backpack\CRUD\app\Library\CrudPanel\CrudPanel $crud
 */
class BundleCrudController extends CrudController
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
        CRUD::setModel(\App\Models\Bundle::class);
        CRUD::setRoute(config('backpack.base.route_prefix') . '/bundle');
        CRUD::setEntityNameStrings('Связку', 'Связка');
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
            'label'     => '№',
            'name'      => 'id',
        ]);

        CRUD::addColumn([
            'label'     => 'Пользователь',
            'type'      => 'select',
            'name'      => 'user_id',
            'entity'    => 'user',
            'attribute' => 'name',
            'model'     => "App\Models\User",
        ]);

        CRUD::addColumn([
            'label'     => 'Гарантия',
            'name'      => 'warranty',
        ]);

        CRUD::addColumn([
            'label'     => 'Рекламная сеть',
            'type'      => 'select',
            'name'      => 'advertising_networks_id',
            'entity'    => 'advertising_networks',
            'attribute' => 'name',
            'model'     => "App\Models\AdvertisingNetwork",
        ]);

        CRUD::addColumn([
            'label'     => 'Сфера',
            'type'      => 'select',
            'name'      => 'spheres_id',
            'entity'    => 'spheres',
            'attribute' => 'name',
            'model'     => "App\Models\Sphere",
        ]);

        CRUD::addColumn([
            'label'     => 'Цена',
            'name'      => 'price',
        ]);

        CRUD::addColumn([
            'label'     => 'Описание',
            'name'      => 'description',
        ]);

        CRUD::addColumn([
            'label'     => 'Дата создания',
            'name'      => 'created_at',
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
        CRUD::setValidation(BundleRequest::class);

        CRUD::addField([
            'label'     => 'Гарантия',
            'name'      => 'warranty',
        ]);

        CRUD::addField([
            'label'     => 'Пользователь',
            'type'      => 'select',
            'name'      => 'user_id',
            'entity'    => 'user',
            'attribute' => 'name',
            'model'     => "App\Models\User",
        ]);

        CRUD::addField([
            'label'     => 'Рекламная сеть',
            'type'      => 'select',
            'name'      => 'advertising_networks_id',
            'entity'    => 'advertising_networks',
            'attribute' => 'name',
            'model'     => "App\Models\AdvertisingNetwork",
        ]);

        CRUD::addField([
            'label'     => 'Сфера',
            'type'      => 'select',
            'name'      => 'spheres_id',
            'entity'    => 'spheres',
            'attribute' => 'name',
            'model'     => "App\Models\Sphere",
        ]);

        CRUD::addField([
            'label'     => 'Цена',
            'name'      => 'price',
        ]);

        CRUD::addField([
            'label'     => 'Описание',
            //'type'      => 'ckeditor',
            'name'      => 'description',
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
