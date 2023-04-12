<?php

namespace App\Jobs;

use App\Http\Services\BaseService;
use App\Http\Services\Other\WxService;
use App\Http\Services\SiriService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class NormalChatGpt implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    private $user_id;
    private $prompt;
    private $bot_name;
    private WxService $service;
    private $channel;

    public $timeout = 120;

    /**
     * Create a new job instance.
     *
     * @param mixed $user_id
     * @param mixed $prompt
     * @param mixed $bot_name
     * @param mixed $channel
     */
    public function __construct($user_id, $prompt, $bot_name, $channel, WxService $service)
    {
        $this->user_id  = $user_id;
        $this->prompt   = $prompt;
        $this->bot_name = $bot_name;
        $this->channel  = $channel;

        $this->service = $service;
    }

    /**
     * Execute the job.
     *
     * @return true
     */
    public function handle()
    {
        // 一次只能执行一个任务
        if (!Redis::connection()->client()->set('lock:chatGpt', 1, ['nx', 'ex' => 120])) {
            // 如果有任务正在执行，延迟10秒再执行
            dispatch(new static($this->user_id,$this->prompt,$this->bot_name,$this->channel,$this->service))->delay(now()->addSeconds(5));

            return true;
        }

        $answer =  SiriService::getInstance()->chatGpt($this->user_id, $this->prompt, $this->bot_name, false, $this->user_id);

        Log::info('NormalChatGpt', [
            'user_id'  => $this->user_id,
            'prompt'   => $this->prompt,
            'bot_name' => $this->bot_name,
            'answer'   => $answer,
        ]);

        // 释放锁
        Redis::connection()->client()->del('lock:chatGpt');

        $this->service->send($answer, $this->user_id, $this->channel);

        BaseService::getInstance()->destroyAll();

        return true;
    }
}
