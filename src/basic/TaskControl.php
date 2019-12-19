<?php
/**
 * Created by PhpStorm.
 * User: alan
 * Date: 2018/9/4
 * Time: 20:17
 */

namespace yii2\mq_task\basic;

use swoole_process;

trait TaskControl
{
    private $pid;

    /**
     *启动服务
     */
    abstract public function start();

    /**
     *停止服务
     */
    public function stop(){
        if (!($pid = $this->getPid())){
            echo sprintf("MQ服务未启动\n");
        } else {
            swoole_process::kill($this->getPid(), SIGTERM);
        }
    }

    /**
     *服务状态
     */
    public function status(){
        if($pid = $this->getPid()){
            echo sprintf("服务正运行中... pid:%d\n", $pid);
        } else {
            echo sprintf("MQ服务未启动\n");
        }
    }

    /**
     *服务热重启
     */
    public function reload(){
        if (!($pid = $this->getPid())){
            echo sprintf("MQ服务未启动\n");
        }

        swoole_process::kill($pid, SIGUSR1);
    }

    /**
     *服务重启
     */
    public function restart(){
        $this->stop();
        sleep(1);
        $this->start();
    }

    abstract public function getPid();

}