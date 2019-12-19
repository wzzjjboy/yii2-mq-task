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
    public function consume(array $data): bool;

    public function start();
}