<?php

namespace BusyPHP\task;

use BusyPHP\swoole\App;
use BusyPHP\swoole\Manager;
use Swoole\Server;
use Swoole\Server\Task;
use Throwable;


/**
 * 任务执行器基本类
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2019 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2020/7/18 下午2:29 下午 TaskInterface.php $
 * @see https://wiki.swoole.com/#/start/start_task
 * @see https://wiki.swoole.com/#/timer
 */
abstract class TaskBase
{
    /**
     * @var App
     */
    protected $app;
    
    /**
     * @var Manager
     */
    protected $manager;
    
    /**
     * @var Server
     */
    protected $server;
    
    
    public function __construct(Manager $manager, App $app)
    {
        $this->app     = $app;
        $this->manager = $manager;
        $this->server  = $this->manager->getServer();
    }
    
    
    public function handle(Task $task)
    {
        $handle = $task->data['handle'] ?? '';
        if ($handle !== static::class) {
            return;
        }
        
        try {
            $this->onTask($task, $task->data['data']);
        } catch (Throwable $e) {
            TaskService::log("Task {$handle} 处理失败: {$e->getMessage()}");
        }
    }
    
    
    /**
     * 定时任务间隔毫秒
     * @return int
     */
    abstract public static function getTimerIntervalMs() : int;
    
    
    /**
     * 没有空闲进程的时候是否进行任务投递
     * @return bool
     */
    abstract public static function getTaskEmptyIdleStatus() : bool;
    
    
    /**
     * 任务投递允许的排队数，0则不限制
     * 该设置依赖{@see TaskBase::getTaskEmptyIdleStatus()}为true有效
     * @return int
     */
    abstract public static function getTaskMaxNumber() : int;
    
    
    /**
     * 执行定时任务，该方法一般用于不耗时的任务处理。
     * 如果要处理耗时任务，建议在该方法中获取处理数据后投递到 task 中执行，不会阻塞定时器。
     * 注意：如果该方法耗时超过下一次计时，会导致下一次计时被系统丢弃；
     * 警告：投递数据超过 8K 时会启用临时文件来保存。当临时文件内容超过 server->package_max_length 时底层会抛出一个警告。此警告不影响数据的投递，过大的 Task 可能会存在性能问题；
     * @param int     $timerId 计时器ID
     * @param App     $app
     * @param Manager $manager
     * @return TaskTimerResult|null 将数据投递到 {@link TaskBase::onTask()} 中，返回 null 不启用任务
     */
    abstract public static function onTimer(int $timerId, App $app, Manager $manager) : ?TaskTimerResult;
    
    
    /**
     * 执行任务处理。
     * 执行完成后要使用{@see Task::finish()}通知执行完成，如不执行{@see TaskBase::onFinish()}将无法收到对应的数据
     * @param Task  $task 任务对象
     * @param mixed $data 要处理的数据，由 {@link TaskBase::onTimer()} 投递
     */
    abstract protected function onTask(Task $task, $data);
    
    
    /**
     * 任务执行完毕
     * @param App      $app
     * @param Manager  $manager
     * @param mixed    $originalData 原数据，即投递到任务的数据
     * @param mixed    $finishData 新数据，即在{@see TaskBase::onTask()}执行完成以后返回的数据，分三种情况:
     *      # 1. 异步任务：由{@link Task::finish()}返回;
     *      # 2. 同步等待任务：返回 false 则任务执行超时。否则由 {@link Task::finish()} 返回;
     *      # 3. 同步等待并发任务：返回结果数组，结果的顺序与 {@link TaskBase::onTimer()} 投递的数据相同，返回的结果数据中将不包含超时的任务
     * @param int|null $taskId 任务或被指定的workerId，并发同步任务为null
     */
    abstract public static function onFinish(App $app, Manager $manager, $originalData, $finishData, $taskId);
}