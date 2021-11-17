<?php

namespace yii2\mq_task\basic;

use common\base\BaseLog;
use Yii;
use yii\base\BaseObject;

/**
 *
 */
class Log extends BaseObject implements ILog
{
    public $category = 'application';

    /**
     * @var IContext
     */
    private $context;

    private function logFormat($msg, $level): string
    {
        $msg = is_array($msg) ? json_encode($msg, JSON_UNESCAPED_UNICODE) : $msg;
        $logId = $this->context ? $this->context->getLogId() : "";
//        echo "[" . date('Y-m-d H:i:s') . "] [$logId] [$level]: {$msg}" . PHP_EOL;
        return sprintf("[%s] %s", $logId, $msg);
    }

    /**
     * @inheritDoc
     * @param mixed $msg
     * @return void
     */
    public function error($msg)
    {
        $msg = $this->logFormat($msg, 'error');
        BaseLog::error($msg);
    }

    /**
     * @inheritDoc
     * @param mixed $msg
     * @return void
     */
    public function info($msg)
    {
        $msg = $this->logFormat($msg, 'info');
        BaseLog::info($msg);
    }

    /**
     * @inheritDoc
     * @param mixed $msg
     * @return void
     */
    public function warning($msg)
    {
        $msg = $this->logFormat($msg, 'warning');
        BaseLog::warning($msg);
    }

//    public function withContext(IContext $context)
//    {
//        $this->context = $context;
//    }
}
