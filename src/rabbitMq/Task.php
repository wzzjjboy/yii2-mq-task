<?php


namespace yii2\metrics\rabbitMq;


use Yii;
use yii\base\BaseObject;
use yii2\crontab\interfaces\IHandler;
use yii2\crontab\interfaces\ITarget;

class Task extends BaseObject implements IHandler, ITarget {

    public $task;
    public $mqHost;
    public $mqPort;
    public $queryName;
    public $mqUsername;
    public $mqPassword;
    public $notifyTaskNum;
    public $serverName;
    public $taskNum;
    public $date;

    /**
     * @return ITarget
     * @throws \yii\base\InvalidConfigException
     */
    public function createTarget() {

    }

    public function handler(...$args)
    {
        pr($args,1);
    }

    public function getTargetData()
    {
        return Yii::createObject([
            'class'      => Target::class,
            'mqHost'     => $this->mqHost,
            'mqPort'     => $this->mqPort,
            'queryName'  => $this->queryName,
            'mqUsername' => $this->mqUsername,
            'mqPassword' => $this->mqPassword,
        ]);
    }
}