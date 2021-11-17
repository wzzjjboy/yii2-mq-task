<?php


namespace yii2\mq_task\components;


use yii\base\Behavior;

class LogIDBehavior  extends Behavior {

    private $logId;

    public $name;
    /**
     * @var integer
     */
    public $length = 32;

    public function init() {
        parent::init();
        $this->setLogId();
    }

    /**
     * @return LogIDBehavior
     */
    public function setLogId(): LogIDBehavior
    {
        $this->logId = substr(hash('md5', uniqid('', true)), 0, $this->length);
        return $this;
    }

    /**
     * @return string
     */
    public function getLogId(): string
    {
        return $this->logId;
    }
}

