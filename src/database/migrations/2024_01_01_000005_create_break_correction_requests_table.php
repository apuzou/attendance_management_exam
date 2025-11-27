<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateBreakCorrectionRequestsTable extends Migration
{
    public function up()
    {
        Schema::create('break_correction_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stamp_correction_request_id')->constrained()->cascadeOnUpdate()->cascadeOnDelete();
            $table->foreignId('break_time_id')->nullable()->constrained('break_times')->nullOnDelete()->cascadeOnUpdate();
            $table->time('original_break_start')->nullable();
            $table->time('original_break_end')->nullable();
            $table->time('corrected_break_start')->nullable();
            $table->time('corrected_break_end')->nullable();
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('break_correction_requests');
    }
}

