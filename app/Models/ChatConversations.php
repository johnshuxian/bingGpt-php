<?php

namespace App\Models;

use App\Http\Services\ChatGptService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ChatConversations extends Model
{
    use HasFactory;

    protected $table = 'chat_conversations';

    protected $guarded = [];

    public static function record($conversation_id, $parent_id, $title = '')
    {
        // 检查是否存在
        $conversation = self::where('conversation_id', $conversation_id)->count();

        if (!$conversation && $title) {
            ChatGptService::getInstance()->updateConversationTitle($conversation_id, $title);
        }

        return self::updateOrCreate(['conversation_id' => $conversation_id], [
            'conversation_id' => $conversation_id,
            'parent_id'       => $parent_id,
        ]);
    }
}
