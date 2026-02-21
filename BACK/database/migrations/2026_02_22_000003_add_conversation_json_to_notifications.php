<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // add column conversation safely (no content assumption)
        Schema::table('notifications', function (Blueprint $table) {
            if (! Schema::hasColumn('notifications', 'conversation')) {
                if (Schema::hasColumn('notifications', 'content')) {
                    $table->json('conversation')->nullable()->after('content');
                } else {
                    $table->json('conversation')->nullable()->after('receiver_id');
                }
            }
        });

        // convert existing rows that only have single message in content
        $rows = \DB::table('notifications')->get();
        foreach ($rows as $row) {
            if ($row->conversation === null) {
                // if a legacy content field exists include its value
                $message = [
                    'sender_id' => $row->sender_id,
                    'content' => isset($row->content) ? $row->content : '',
                    'created_at' => $row->created_at,
                ];
                \DB::table('notifications')
                    ->where('id', $row->id)
                    ->update(['conversation' => json_encode([$message])]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('notifications', function (Blueprint $table) {
            if (Schema::hasColumn('notifications', 'conversation')) {
                $table->dropColumn('conversation');
            }
        });
    }
};