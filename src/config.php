<?php
/**
 * 异步任务配置
 */
return [
    // 任务映射
    // 建议用闭包返回，否则柔性重启代码无法生效
    'list' => function() {
        /**
         * 返回数组
         * 任务类，必须集成 {@see \BusyPHP\task\TaskBase} 接口
         */
        return [
            // Task1::class,
            // Task2::class,
            // ...
        ];
    }
];