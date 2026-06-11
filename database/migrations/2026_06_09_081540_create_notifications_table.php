<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignUuid('batch_id')
                ->constrained('notification_batches')
                ->cascadeOnDelete(); // UUID массовой рассылки
            $table->string('recipient'); // телефон или email
            $table->enum('channel', ['sms', 'email']);
            $table->text('message');
            $table->unsignedTinyInteger('priority'); // убрали ->default(0)
            $table->enum('status', ['queued', 'sent', 'delivered', 'failed'])->default('queued');
            $table->string('provider_message_id')->nullable(); // id сообщения у внешнего провайдера
            $table->unsignedTinyInteger('attempts')->default(0); // количество попыток отправки
            $table->text('last_error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('processing_locked_at')->nullable();
            $table->timestamps();

            $table->index(['recipient', 'created_at']);
            $table->index('status');

            $table->unique([
                'batch_id',
                'recipient',
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
