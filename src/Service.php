<?php

namespace BusyPHP\task;

use BusyPHP\helper\util\Filter;

/**
 * Server注册
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2019 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2020/11/10 下午8:01 下午 Service.php $
 */
class Service extends \think\Service
{
    use TaskConfig;
    
    public function boot()
    {
        $this->app->event->listen('swoole.workerStart', TaskService::class, true);
        
        // 监听onTask
        $taskQueue = $this->getTaskConfig('list', []);
        if (is_callable($taskQueue)) {
            $taskQueue = call_user_func($taskQueue);
        }
        $taskQueue = is_array($taskQueue) ? $taskQueue : [];
        $taskQueue = Filter::trimArray($taskQueue);
        foreach ($taskQueue as $item) {
            $this->app->event->listen('swoole.task', $item);
        }
    }
}