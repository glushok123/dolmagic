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
        Schema::connection('tech')->create('insales_info_products', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('product_id_insales')->comment('id товара в insales');
            $table->dateTime('product_created_at')->comment('дата создания');
            $table->dateTime('product_updated_at')->comment('дата обновления');
            $table->string('title')->nullable()->comment('Гарантия');
            $table->text('short_description')->nullable()->comment('описание');
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
        Schema::dropIfExists('insales_info_products');
    }
};
