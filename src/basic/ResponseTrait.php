<?php


namespace yii2\mq_task\basic;


trait ResponseTrait
{
    public function Running($pid)
    {
        $this->show("MqTask正在运行 pid:%s。。。", $pid);
    }

    public function Term()
    {
        $this->Show("MqTask未启动");
    }

    public function SignTerm($pid)
    {

        $this->Show("pid:%s 收到SIGTERM信号，准备退出...", $pid);
    }

    public function  InvalidArgument($tmp, ...$args)
    {
        $this->show($tmp, ...$args);
    }

    private function Show($tmp, ...$args)
    {
        echo sprintf($tmp . PHP_EOL, ...$args);
    }
}