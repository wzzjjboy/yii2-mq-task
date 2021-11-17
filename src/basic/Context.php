<?php


namespace yii2\mq_task\basic;

class Context implements IContext
{
    private $logId;

    private $route;

    private $params;

    public function __construct($route, $params, $logId = null)
    {
        $this->route = $route;
        $this->params = $params;
        $this->logId = $logId;
        if (empty($this->logId)){
            $this->logId = $this->makeLogId(16);
        }
    }

    public function getLogId(): string
    {
        return $this->logId ?: '';
    }

    public function getRoute(): string
    {
        return $this->route;
    }

    public function getParams(): array
    {
        return  $this->params;
    }

    private function makeLogId($size = 32) {
        $chars = md5(uniqid(mt_rand(), true));
        return substr($chars, 0, $size);
    }
}