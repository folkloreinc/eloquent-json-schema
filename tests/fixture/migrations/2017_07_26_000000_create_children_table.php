<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateChildrenTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('children', function (Blueprint $table) {
            $table->increments('id');
            $table->integer('test_id')->unsigned()->nullable();
            $table->string('test_handle')->nullable();
            $table->longText('data')->nullable();
            $table->longText('metadata')->nullable();
            $table->timestamps();

            $table->index('test_id');
            $table->index('test_handle');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('children');
    }
}
