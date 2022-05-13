<?php
/**
 * Created by PhpStorm.
 * User: alan
 * Date: 2018/8/31
 * Time: 13:01
 */

namespace yii2\mq_task\basic;

interface ITask
{
    /**
     * 消费任务
     * @param array $data 任务的参数
     * @return bool
     */
    public function consume(array $data): bool;
}