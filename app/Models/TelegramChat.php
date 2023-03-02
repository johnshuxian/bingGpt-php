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

    public static function getLastChatId($telegram_chat_id,$bot_name,$last_hour = true,$column = 'chat_id')
    {
        return DB::table('telegram_records')->where(['recycle'=>0])->where('telegram_chat_id',$telegram_chat_id)->where(function($q)use($last_hour){
            if($last_hour){
                $q->where('created_at','>=',Carbon::now()->addHours(-1)->toDateTimeString());
            }
        })->where(['is_bot'=>1,'username'=>$bot_name])->orderBy('id','desc')->limit(1)->value($column)??0;
    }

    public static function getRecords(mixed $chat_id, string $column = 'chat_id', bool $last_hour = true)
    {
        return DB::table('telegram_records')->where(['recycle'=>0])->where($column,$chat_id)->where(function($q)use($last_hour){
            if($last_hour){
                $q->where('created_at','>=',Carbon::now()->addHours(-1)->toDateTimeString());
            }
        })->orderBy('id')->select('is_bot','content','id')->get()->toArray();
    }

    //$username,$content,$telegram_chat_id,$chat_id = 0,$chat_type = 'private',$is_bot = false
    public function record($arr)
    {
        //['telegram_chat_id'=>$telegram_chat_id,'chat_id'=>$chat_id,'chat_type'=>$chat_type,'content'=>$content,'username'=>$username,'is_bot'=>$is_bot?1:0]
        return self::create($arr);
    }
}
