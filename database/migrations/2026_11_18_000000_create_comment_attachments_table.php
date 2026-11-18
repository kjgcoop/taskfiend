<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Comments used to carry a single attachment in four columns. Move those
     * into their own table so a comment can have any number of files.
     */
    public function up(): void
    {
        Schema::create('comment_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('comment_id')->constrained()->cascadeOnDelete();
            $table->string('file_path');
            $table->string('original_filename');
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestamps();

            $table->index('comment_id');
        });

        DB::table('comments')->whereNotNull('file_path')->orderBy('id')->each(function ($comment) {
            DB::table('comment_attachments')->insert([
                'comment_id' => $comment->id,
                'file_path' => $comment->file_path,
                'original_filename' => $comment->original_filename ?? basename($comment->file_path),
                'mime_type' => $comment->mime_type,
                'file_size' => $comment->file_size,
                'created_at' => $comment->created_at,
                'updated_at' => $comment->updated_at,
            ]);
        });

        Schema::table('comments', function (Blueprint $table) {
            $table->dropColumn(['file_path', 'original_filename', 'mime_type', 'file_size']);
        });
    }

    public function down(): void
    {
        Schema::table('comments', function (Blueprint $table) {
            $table->string('file_path')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('mime_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
        });

        // Only one file fits per comment on the way back; keep the first.
        DB::table('comment_attachments')->orderBy('id')->each(function ($attachment) {
            DB::table('comments')
                ->where('id', $attachment->comment_id)
                ->whereNull('file_path')
                ->update([
                    'file_path' => $attachment->file_path,
                    'original_filename' => $attachment->original_filename,
                    'mime_type' => $attachment->mime_type,
                    'file_size' => $attachment->file_size,
                ]);
        });

        Schema::dropIfExists('comment_attachments');
    }
};
