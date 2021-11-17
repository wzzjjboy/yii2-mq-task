<?php
/**
 * 自定义日志格式.
 * Date: 2019/11/14
 * Time: 14:21
 */
namespace yii2\mq_task\components;

use Yii;
use yii\log\Logger;
use yii\helpers\VarDumper;
use yii\web\Request;

class FileTarget extends \yii\log\FileTarget
{
    public $sessionID;

    public function getMessagePrefix($message)
    {
        if ($this->prefix !== null) {
            return call_user_func($this->prefix, $message);
        }

        if (Yii::$app === null) {
            return '';
        }

        $request = Yii::$app->getRequest();
        $ip = $request instanceof Request ? $request->getUserIP() : '-';

        /* @var $user \yii\web\User */
        $user = Yii::$app->has('user', true) ? Yii::$app->get('user') : null;
        if ($user && ($identity = $user->getIdentity(false))) {
            $userID = $identity->getId();
        } else {
            $userID = '-';
        }
        $sessionID = self::getSessionID();
        return "$ip|$userID|$sessionID";
    }

    public function formatMessage($message)
    {
        list($text, $level, $category, $timestamp) = $message;
        $level = Logger::getLevelName($level);
        if (!is_string($text)) {
            // exceptions may not be serializable if in the call stack somewhere is a Closure
            if ($text instanceof \Throwable || $text instanceof \Exception) {
                $text = (string) $text;
            } else {
                $text = VarDumper::export($text);
            }
        }
        $request = Yii::$app->getRequest();
        $url = $request instanceof Request ? $request->getAbsoluteUrl() : '-';

        $server_ip = $_SERVER['SERVER_ADDR'] ?? '';

        // modify: message format
        $traces = '';
        if (isset($message[4])) {
            $trace = end($message[4]);
            $traces = "{$trace['file']}:{$trace['line']}";
        }
        $text = str_replace(PHP_EOL,' ',$text);
        $prefix = $this->getMessagePrefix($message);
        return $this->getTime($timestamp) . "|$server_ip|{$prefix}|$level|$category|$url|$traces|$text";
    }

    public function getSessionID()
    {
        if (Yii::$app->request->isConsoleRequest && ($request = Yii::$app->getRequest()) && method_exists($request, 'setLogId') && method_exists($request, 'getLogId')){
            $request->setLogId();
            return $request->getLogId();
        }
        if ($this->sessionID)
        {
            return $this->sessionID;
        }
        $uid = Yii::$app->request->getIsConsoleRequest() ? mt_rand(10000, 99999) : Yii::$app->user->getId();
        return $this->sessionID = md5(microtime(true) . $uid) . mt_rand(0, 1000);
    }
}