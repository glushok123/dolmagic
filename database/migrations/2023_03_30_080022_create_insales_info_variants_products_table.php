<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::connection('tech')->create('insales_info_variants_products', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('variants_id_insales')->comment('id товара');
            $table->string('sku')->comment('Артикул');
            $table->string('price')->nullable()->comment('цена');
            $table->string('old_price')->nullable()->comment('Зачеркнутая цена');
            $table->dateTime('variants_created_at')->comment('дата создания');
            $table->dateTime('variants_updated_at')->comment('дата обновления');

            $table->bigInteger('insales_info_products_id')->unsigned()->nullable()->comment('Сфера');
            $table->foreign('insales_info_products_id')->references('id')->on('insales_info_products');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('insales_info_variants_products');
    }
};
