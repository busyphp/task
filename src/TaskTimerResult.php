<?php

namespace BusyPHP\task;

/**
 * OnTimer返回结构
 * @author busy^life <busy.life@qq.com>
 * @copyright (c) 2015--2019 ShanXi Han Tuo Technology Co.,Ltd. All rights reserved.
 * @version $Id: 2020/11/11 下午10:03 上午 TaskTimerResult.php $
 */
class TaskTimerResult
{
    /**
     * 是否异步任务
     * @var bool
     */
    private $taskIsDefault = true;
    
    /**
     * 是否同步等待任务
     * @var bool
     */
    private $taskIsWait = false;
    
    /**
     * 是否同步等待并发任务
     * @var bool
     */
    private $taskIsWaitMulti = false;
    
    /**
     * 任务超时时长
     * @var float
     */
    private $taskTimeout = 0.5;
    
    /**
     * 任务数据
     * @var array
     */
    private $taskData = [];
    
    /**
     * 指定要给投递给哪个 Task 进程的进程ID
     * @var int
     */
    private $taskDstWorkerId = 0;
    
    
    /**
     * 快速实例化
     * @return $this
     */
    public static function init()
    {
        return new static();
    }
    
    
    /**
     * 设置启用异步任务。
     * 注意：该方法不会阻塞进程。但进程过多会导致worker阻塞。所以要适当配置 config/extend/task.php 中的 max_tasking 和 empty_idle
     * 建议：该方法一般用于不重要的数据处理。
     * @param mixed $data 投递的任务数据
     * @param int   $dstWorkerId 指定要给投递给哪个 Task 进程 {@see https://wiki.swoole.com/#/learn?id=taskworker%e8%bf%9b%e7%a8%8b}，传入 ID 即可，范围参考 {@see https://wiki.swoole.com/#/server/properties?id=worker_id} 默认为 -1 表示随机投递，底层会自动选择一个空闲 Task 进程
     * @return $this
     */
    public function setTaskIsDefault($data, int $dstWorkerId = -1) : self
    {
        $this->taskIsDefault   = true;
        $this->taskIsWait      = false;
        $this->taskIsWaitMulti = false;
        $this->taskData        = $data;
        $this->setTaskDstWorkerId($dstWorkerId);
        
        return $this;
    }
    
    
    /**
     * 设置启用同步等待任务。直到执行完毕或者执行超时。
     * 警告：该方法会阻塞进程。
     * @param mixed     $data 投递的任务数据
     * @param float|int $timeout 任务超时时长控制。单位秒
     * @param int       $dstWorkerId 指定要给投递给哪个 Task 进程 {@see https://wiki.swoole.com/#/learn?id=taskworker%e8%bf%9b%e7%a8%8b}，传入 ID 即可，范围参考 {@see https://wiki.swoole.com/#/server/properties?id=worker_id} 默认为 -1 表示随机投递，底层会自动选择一个空闲 Task 进程
     * @return $this
     */
    public function setTaskIsWait($data, float $timeout = 0.5, int $dstWorkerId = -1) : self
    {
        $this->taskIsWait      = true;
        $this->taskIsDefault   = false;
        $this->taskIsWaitMulti = false;
        $this->taskData        = $data;
        $this->setTaskDstWorkerId($dstWorkerId);
        $this->setTaskTimeout($timeout);
        
        return $this;
    }
    
    
    /**
     * 设置启用同步等待并发任务。
     * 系统会将 $data 中的值分配给每一个task中执行，直到执行完毕或者超时。
     * 警告：该方法会阻塞进程。
     * @param array     $data 投递的任务数据，必须是索引数组，数组长度不能超过 1000
     * @param float|int $timeout 任务超时时长控制。单位秒
     * @return $this
     */
    public function setTaskIsWaitMulti(array $data, float $timeout = 0.5) : self
    {
        $this->taskIsWaitMulti = true;
        $this->taskIsDefault   = false;
        $this->taskIsWait      = false;
        $this->taskData        = $data;
        $this->setTaskTimeout($timeout);
        
        return $this;
    }
    
    
    /**
     * 指定worker id
     * @param int $taskDstWorkerId 指定要给投递给哪个 Task 进程 {@see https://wiki.swoole.com/#/learn?id=taskworker%e8%bf%9b%e7%a8%8b}，传入 ID 即可，范围参考 {@see https://wiki.swoole.com/#/server/properties?id=worker_id} 默认为 -1 表示随机投递，底层会自动选择一个空闲 Task 进程
     * @return $this
     */
    public function setTaskDstWorkerId(int $taskDstWorkerId) : self
    {
        $taskDstWorkerId = $taskDstWorkerId < -1 ? -1 : $taskDstWorkerId;
        
        $this->taskDstWorkerId = $taskDstWorkerId;
        
        return $this;
    }
    
    
    /**
     * 设置超时时间
     * @param float $taskTimeout 单位秒
     * @return $this
     */
    public function setTaskTimeout(float $taskTimeout) : self
    {
        $taskTimeout = floatval($taskTimeout);
        $taskTimeout = $taskTimeout <= 0 ? 0 : $taskTimeout;
        
        $this->taskTimeout = $taskTimeout;
        
        return $this;
    }
    
    
    /**
     * 是否异步任务
     * @return bool
     */
    public function isTaskIsDefault() : bool
    {
        return $this->taskIsDefault;
    }
    
    
    /**
     * 是否同步等待任务
     * @return bool
     */
    public function isTaskIsWait() : bool
    {
        return $this->taskIsWait;
    }
    
    
    /**
     * 是否同步等待并发任务
     * @return bool
     */
    public function isTaskIsWaitMulti() : bool
    {
        return $this->taskIsWaitMulti;
    }
    
    
    /**
     * 获取任务投递的数据
     * @return mixed
     */
    public function getTaskData()
    {
        return $this->taskData;
    }
    
    
    /**
     * 获取任务超时时长
     * @return float
     */
    public function getTaskTimeout() : float
    {
        return $this->taskTimeout;
    }
    
    
    /**
     * 获取指定要给投递给哪个 Task 进程ID
     * @return int
     */
    public function getTaskDstWorkerId() : int
    {
        return $this->taskDstWorkerId;
    }
}