<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatConversations extends Model
{
    use HasFactory;

    protected $table = 'chat_conversations';

    protected $guarded = [];

    public static function record($conversation_id, $parent_id)
    {
        return self::updateOrCreate(['conversation_id'=>$conversation_id], [
            'conversation_id'=> $conversation_id,
            'parent_id'      => $parent_id,
        ]);
    }
}
