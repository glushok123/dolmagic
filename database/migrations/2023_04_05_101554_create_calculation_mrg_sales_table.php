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
        Schema::connection('tech')->create('calculation_mrg_sales', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('sale_id')->nullable()->index()->comment('id продажи');
            $table->decimal('mrg_sale', 5, 2)->nullable()->index()->comment('mrg');
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
        Schema::dropIfExists('calculation_mrg_sales');
    }
};
