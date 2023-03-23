<?php
return array(
    'host'    => 'technostor-sphinx',
    'port'    => 9312,
    'timeout' => 30,
    'indexes' => array(
        'crm_products' => ['table' => 'products', 'column' => 'id', 'modelname' => 'App\Product'],
        'crm_categories' => ['table' => 'product_categories', 'column' => 'id', 'modelname' => 'App\Category'],
        'crm_brands' => ['table' => 'product_brands', 'column' => 'id', 'modelname' => 'App\ProductBrand'],
    ),
    'mysql_server' => array(
        'host' => 'technostor-mariadb',
        'port' => 9306
    )
);
