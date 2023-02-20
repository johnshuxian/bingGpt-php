<?php

namespace App\Listeners;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Log;

class QueryListener
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
    }

    /**
     * Handle the event.
     *
     * @param object $event
     */
    public function handle(QueryExecuted $event)
    {
        // 只在测试环境下输出 log 日志
        if (!app()->environment(['testing', 'local'])) {
            return;
        }
        $sql      = $event->sql;
        $bindings = $event->bindings;
        $time     = $event->time; // 毫秒
        $bindings = array_map(function ($binding) {
            if (is_string($binding)) {
                return (string) $binding;
            }
            if ($binding instanceof \DateTime) {
                return $binding->format("'Y-m-d H:i:s'");
            }

            return $binding;
        }, $bindings);
        $sql = str_replace('?', '%s', $sql);
        $sql = sprintf($sql, ...$bindings);
        Log::channel('sql')->info('sql_log', ['sql' => $sql, 'time' => $time . 'ms']);
    }
}
