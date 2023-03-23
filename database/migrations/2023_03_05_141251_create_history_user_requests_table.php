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
        Schema::create('history_user_requests', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable()->index();
            $table->bigInteger('min_price')->nullable()->comment('Цена для продажи минимальная');
            $table->bigInteger('max_price')->nullable()->comment('Цена для продажи максимальная');
            $table->string('type')->nullable()->index()->comment('Тип товара новый/бу');
            $table->bigInteger('user_id')->unsigned();
            $table->foreign('user_id')->references('id')->on('users')->comment('Пользователь совершивший запрос');
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
        Schema::dropIfExists('history_user_requests');
    }
};