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
        Schema::create('bing_conversations', function (Blueprint $table) {
            $table->id();
            $table->string('client_id',50)->nullable(false)->default('');
            $table->string('conversation_id',100)->nullable(false)->default('')->unique();
            $table->string('conversation_signature',100)->nullable(false)->default('')->unique();
            $table->integer('invocation_id')->nullable(false)->default(0);
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
        Schema::dropIfExists('bing_conversations');
    }
};
