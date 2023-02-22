<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class TelegramChat extends Model
{
    use HasFactory;

    protected $guarded  = ['id'];

    protected $table = 'telegram_records';

    public static function getLastChatId($telegram_chat_id)
    {
        return DB::table('telegram_records')->where(['recycle'=>0])->where('telegram_chat_id',$telegram_chat_id)->where('created_at','>=',Carbon::now()->addHours(-1)->toDateTimeString())->orderBy('id','desc')->limit(1)->value('chat_id')??0;
    }

    public function record($username,$content,$telegram_chat_id,$chat_id = 0,$chat_type = 'private',$is_bot = false)
    {
        return self::create(['telegram_chat_id'=>$telegram_chat_id,'chat_id'=>$chat_id,'chat_type'=>$chat_type,'content'=>$content,'username'=>$username,'is_bot'=>$is_bot?1:0]);
    }
}
