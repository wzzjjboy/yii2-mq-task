<?php

namespace yii2\mq_task\basic;

use Yii;
use yii\base\BaseObject;

/**
 *
 */
class Log extends BaseObject implements ILog
{
    public $category = 'application';

    private function logFormat($msg, $level)
    {
        $msg = is_array($msg) ? json_encode($msg, JSON_UNESCAPED_UNICODE) : $msg;
        $logId = '';
        echo "[" . date('Y-m-d H:i:s') . "] [$logId] [$level]: {$msg}" . PHP_EOL;
    }

    /**
     * @inheritDoc
     * @param mixed $msg
     * @return void
     */
    public function error($msg)
    {
        $this->logFormat($msg, 'error');
        Yii::error($msg, $this->category);
    }

    /**
     * @inheritDoc
     * @param mixed $msg
     * @return void
     */
    public function info($msg)
    {
        $this->logFormat($msg, 'info');
        Yii::info($msg, $this->category);
    }

    /**
     * @inheritDoc
     * @param mixed $msg
     * @return void
     */
    public function warning($msg)
    {
        $this->logFormat($msg, 'warning');
        Yii::warning($msg, $this->category);
    }

}
