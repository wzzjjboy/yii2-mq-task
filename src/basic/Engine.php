<?php

namespace yii2\mq_task\basic;

/**
 *
 */
interface Engine
{
    /**
     *启动服务
     */
    public function start();

    /**
     *停止服务
     */
    public function stop();

    /**
     *服务状态
     */
    public function status();

    /**
     *服务热重启
     */
    public function reload();

    /**
     *服务重启
     */
    public function restart();

    /**
     * 获取PID文件
     */
    public function getPid();

}
