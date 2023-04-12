<?php

namespace App\Http\Services;

use App\Helpers\ApiResponse;
use Illuminate\Support\Facades\Log;

class BaseService
{
    // 引入api统一返回消息
    use ApiResponse;

    protected static $instance;

    /**
     * @var array 保存已经实例化的单例对象
     */
    public static $instance_pools = [];

    public static function getInstance(...$params): static
    {
        Log::info('getInstance ' . static::class, ['is_null' => is_null(static::$instance)]);

        if (static::$instance instanceof static) {
            Log::info('returning ' . static::class);

            return static::$instance;
        }

        static::$instance = new static(...$params);

        self::$instance_pools[static::class] = static::$instance;

        return static::$instance;
    }

    public function __construct()
    {
    }

    protected function __clone()
    {
    }

    public function destroy()
    {
        Log::info('destructing ' . static::class);

        unset(self::$instance_pools[static::class]);

        static::$instance = null;
    }

    public function destroyAll()
    {
        foreach (self::$instance_pools as $instance) {
            $instance->destroy();
        }
    }
}
