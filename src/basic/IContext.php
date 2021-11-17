<?php


namespace yii2\mq_task\basic;


interface IContext
{
    /**
     * 获取logId
     * @return string
     */
    public function getLogId(): string;

    /**
     * 获取请求的路由
     * @return string
     */
    public function getRoute(): string;

    /**
     * 获取请求的参数
     * @return array
     */
    public function getParams(): array;
}