<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BingConversations extends Model
{
    use HasFactory;

    protected $table = 'bing_conversations';

    protected $guarded = [];

    public static function record($data)
    {
        self::updateOrCreate($data,$data);
    }
}
