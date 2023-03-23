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
        Schema::create('bundles', function (Blueprint $table) {
            $table->id();
            $table->string('warranty')->nullable()->comment('Гарантия');
            $table->bigInteger('advertising_networks_id')->unsigned()->nullable()->comment('Рекламная сеть');
            $table->bigInteger('spheres_id')->unsigned()->nullable()->comment('Сфера');
            $table->bigInteger('price')->unsigned()->nullable()->comment('Цена');
            $table->text('description')->nullable()->comment('описание');
            $table->bigInteger('user_id')->unsigned()->nullable()->comment('Пользователь');

            $table->foreign('advertising_networks_id')->references('id')->on('advertising_networks');
            $table->foreign('spheres_id')->references('id')->on('spheres');
            $table->foreign('user_id')->references('id')->on('users');

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
        Schema::dropIfExists('bundles');
    }
};
