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
        Schema::connection('tech')->table('history_insales_a_p_i_s', function (Blueprint $table) {
            $table->bigInteger('product_id_insales')->comment('id товара в insales');
            $table->bigInteger('variants_id')->comment('id variant товара в insales');

            $table->string('sku')->comment('sku');

            $table->string('current_price')->nullable()->comment('Цена (текущие)');
            $table->string('current_old_price')->nullable()->comment('Зачеркнутая цена (текущие)');
            $table->string('current_quantity')->nullable()->comment('Количесвто (текущие)');

            $table->string('past_price')->nullable()->comment('Цена (прошлые)');
            $table->string('past_old_price')->nullable()->comment('Зачеркнутая цена (прошлые)');
            $table->string('past_quantity')->nullable()->comment('Количесвто (прошлые)');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('history_insales_a_p_i_s', function (Blueprint $table) {
            //
        });
    }
};
