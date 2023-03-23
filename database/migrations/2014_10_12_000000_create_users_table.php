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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('logo')->index()->nullable()->comment('Путь до логотипа');
            $table->string('rating')->index()->nullable()->comment('Рейтинг');
            $table->string('count_order')->index()->nullable()->comment('количество заказов');
            $table->string('email')->unique();
            $table->unsignedTinyInteger('type')->default(0)->comment('Тип пользователя покупатель/продавец');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
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
        Schema::dropIfExists('users');
    }
};
