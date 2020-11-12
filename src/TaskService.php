<?php

namespace BusyPHP\task;

use BusyPHP\helper\util\Arr;
use BusyPHP\helper\util\Filter;
use BusyPHP\swoole\App;
use BusyPHP\swoole\Manager;
use Swoole\Server;
use Swoole\Timer;
use think\facade\Log;
use Throwable;

/**
 * 定时任务服务类
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2019 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2020/7/18 下午2:29 下午 Task.php $
 */
class TaskService
{
    use TaskConfig;
    
    /**
     * @var Manager
     */
    protected $manager;
    
    /**
     * @var Server
     */
    protected $server;
    
    /**
     * @var App
     */
    protected $app;
    
    
    public function __construct(Manager $manager, App $app)
    {
        $this->manager = $manager;
        $this->server  = $this->manager->getServer();
        $this->app     = $app;
    }
    
    
    /**
     * 执行处理
     */
    public function handle()
    {
        if ($this->server->taskworker) {
            return;
        }
        
        $taskQueue = $this->getTaskConfig('list', []);
        if (is_callable($taskQueue)) {
            $taskQueue = call_user_func($taskQueue);
        }
        $taskQueue = is_array($taskQueue) ? $taskQueue : [];
        $taskQueue = Filter::trimArray($taskQueue);
        
        foreach ($taskQueue as $class) {
            if (!class_exists($class)) {
                self::log("Task类 {$class} 不存在");
                continue;
            }
            
            if (!is_subclass_of($class, TaskBase::class)) {
                $face = TaskBase::class;
                self::log("Task类 {$class} 必须继承 {$face}");
                continue;
            }
            
            $emptyIdle  = call_user_func([$class, 'getTaskEmptyIdleStatus']);
            $maxTasking = call_user_func([$class, 'getTaskMaxNumber']);
            $time       = call_user_func([$class, 'getTimerIntervalMs']);
            if ($time < 0) {
                self::log("Task类 {$class} 的定时器至少需要设置0毫秒");
                continue;
            }
            
            // 创建定时器
            Timer::tick($time, function($timerId) use ($class, $emptyIdle, $maxTasking) {
                $stats = $this->server->stats();
                
                // 没有空闲进程不允许投递
                if (!$emptyIdle && $stats['task_idle_worker_num'] == 0) {
                    return;
                }
                
                // 排队进程超出设置则不允许投递
                if ($maxTasking > 0 && $stats['tasking_num'] > $maxTasking) {
                    return;
                }
                
                // Worker 进程忙碌中
                if ($this->server->getWorkerStatus() === 1) {
                    return;
                }
                
                try {
                    $result = call_user_func_array([$class, 'onTimer'], [$timerId, $this->app, $this->manager]);
                    if (!$result instanceof TaskTimerResult) {
                        return;
                    }
                    
                    if ($result->isTaskIsDefault()) {
                        $this->task($class, $result);
                    } elseif ($result->isTaskIsWait()) {
                        $this->taskWait($class, $result);
                    } elseif ($result->isTaskIsWaitMulti()) {
                        $this->taskWaitMulti($class, $result);
                    }
                } catch (Throwable $e) {
                    self::log("{$class}执行失败, {$e->getMessage()}");
                }
            });
        }
    }
    
    
    /**
     * 投递异步任务
     * @param string          $handle 执行程序
     * @param TaskTimerResult $taskTimerResult
     */
    protected function task($handle, TaskTimerResult $taskTimerResult)
    {
        $this->server->task([
            'handle' => $handle,
            'data'   => $taskTimerResult->getTaskData()
        ], $taskTimerResult->getTaskDstWorkerId(), function(Server $server, $taskId, $finishData) use ($handle, $taskTimerResult) {
            if (is_subclass_of($handle, TaskBase::class)) {
                call_user_func_array([$handle, 'onFinish'], [
                    $this->app,
                    $this->manager,
                    $taskTimerResult->getTaskData(),
                    $finishData,
                    $taskId
                ]);
            }
        });
    }
    
    
    /**
     * 投递同步等待任务
     * @param string          $handle 执行程序
     * @param TaskTimerResult $taskTimerResult
     */
    protected function taskWait($handle, TaskTimerResult $taskTimerResult)
    {
        $result = $this->server->taskwait([
            'handle' => $handle,
            'data'   => $taskTimerResult->getTaskData()
        ], $taskTimerResult->getTaskTimeout(), $taskTimerResult->getTaskDstWorkerId());
        
        if (is_subclass_of($handle, TaskBase::class)) {
            call_user_func_array([$handle, 'onFinish'], [
                $this->app,
                $this->manager,
                $taskTimerResult->getTaskData(),
                $result,
                $taskTimerResult->getTaskDstWorkerId()
            ]);
        }
    }
    
    
    /**
     * 投递同步等待并发任务
     * @param string          $handle 执行程序
     * @param TaskTimerResult $taskTimerResult
     * @throws TaskException
     */
    protected function taskWaitMulti($handle, TaskTimerResult $taskTimerResult)
    {
        $data     = $taskTimerResult->getTaskData();
        $dataSize = count($data);
        if ($dataSize < 1) {
            throw new TaskException('投递的数据为0');
        }
        
        if ($dataSize > 1000) {
            throw new TaskException('投递的数据长度不能超过1000');
        }
        
        if (Arr::isAssoc($data)) {
            throw new TaskException('投递的数据必须为数字索引数组');
        }
        
        $tasks = [];
        foreach ($data as $item) {
            $tasks[] = [
                'handle' => $handle,
                'data'   => $item
            ];
        }
        
        $result = $this->server->taskCo($tasks, 0.5);
        $result = is_array($result) ? $result : [];
        
        // 执行回调
        if (is_subclass_of($handle, TaskBase::class)) {
            call_user_func_array([$handle, 'onFinish'], [
                $this->app,
                $this->manager,
                $data,
                $result,
                null
            ]);
        }
    }
    
    
    /**
     * 记录日志
     * @param string $message
     * @param string $type
     */
    public static function log($message, $type = 'error')
    {
        Log::write("busyphp/task {$message}", $type);
    }
}