<?php

namespace yii2\mq_task\basic;
/**
 *
 */
interface ILog
{
    /**
     * @param mixed $msg
     * @return void
     */
    public function error($msg);

    /**
     * @param mixed $msg
     * @return void
     */
    public function info($msg);

    /**
     * @param mixed $msg
     * @return void
     */
    public function warning($msg);
}
