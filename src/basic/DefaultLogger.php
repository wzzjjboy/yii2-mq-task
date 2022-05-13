<?php


namespace yii2\mq_task\basic;


use Psr\Log\LoggerInterface;
use Psr\Log\LoggerTrait;
use Psr\Log\LogLevel;
use yii\log\Logger;

class DefaultLogger implements LoggerInterface
{
    use LoggerTrait;

    public function log($level, $message, array $context = array())
    {
        switch ($level){
            case LogLevel::DEBUG:
                $logLevel = Logger::LEVEL_TRACE;
                break;
            case LogLevel::INFO:
            case LogLevel::NOTICE:
                $logLevel = Logger::LEVEL_INFO;
                break;
            case LogLevel::WARNING:
                $logLevel = Logger::LEVEL_WARNING;
                break;
            case LogLevel::ERROR:
            case LogLevel::CRITICAL:
            case LogLevel::ALERT:
            case LogLevel::EMERGENCY:
                $logLevel = Logger::LEVEL_ERROR;
                break;
            default:
                $logLevel = Logger::LEVEL_ERROR;

        }
        \Yii::getLogger()->log($message, $logLevel, 'yii2-mq-task');
    }
}