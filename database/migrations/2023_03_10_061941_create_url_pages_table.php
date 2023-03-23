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
        Schema::create('url_pages', function (Blueprint $table) {
            $table->id();
            $table->string('url')->index()->comment('Адресс ЛК');
            $table->string('parent_url')->nullable()->index()->comment('Адресс ЛК родителя');
            $table->string('phone')->index()->nullable()->comment('Телефон');
            $table->string('city')->index()->nullable()->comment('Город');
            $table->string('age')->index()->nullable()->comment('Возраст');
            $table->string('work')->index()->nullable()->comment('Работа');
            $table->string('floor')->index()->nullable()->comment('Пол');
            $table->text('payment_method')->nullable()->comment('Способы оплаты');
            $table->text('comment')->nullable()->comment('Комментарий');
            $table->dateTime('time_first_open_url')->nullable()->comment('Дата и время первого открытия ссылки');
            $table->dateTime('time_active')->nullable()->comment('Дата и время нажатия кнопки "Активации"');
            $table->dateTime('time_payment')->nullable()->comment('Дата и время нажатия кнопки "Оплатил"');
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
        Schema::dropIfExists('url_pages');
    }
};