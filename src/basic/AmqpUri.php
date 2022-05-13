<?php


namespace yii2\mq_task\basic;


class AmqpUri
{
    public static function make(Task $task)
    {
        return sprintf("amqp://%s:%s@%s:%s", $task->username, $task->password, $task->host, $task->port);
    }

    public static function parse($amqpUri) {
        preg_match("/amqp:\/\/(\w+):(\w+)@(.*):(\d+)/", $amqpUri, $matches);
        return [$matches[1], $matches[2], $matches[3], $matches[4]];
    }
}