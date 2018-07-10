<?php

use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Migrations\Migration;

class CreateTestsChildrenPivotTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('tests_children_pivot', function (Blueprint $table) {
            $table->increments('id');
            $table->string('handle')->nullable();
            $table->integer('test_id')->unsigned()->nullable();
            $table->string('child_id')->nullable();
            $table->timestamps();

            $table->index('test_id');
            $table->index('child_id');
            $table->index('handle');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('tests_children_pivot');
    }
}
