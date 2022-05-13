<?php
/**
 * Created by PhpStorm.
 * User: alan
 * Date: 2018/8/31
 * Time: 12:32
 */

namespace yii2\mq_task\basic;


use AMQPChannel;
use AMQPChannelException;
use AMQPConnection;
use AMQPConnectionException;
use AMQPQueue;
use common\utils\ToolsHelper;
use Psr\Log\LoggerInterface;
use Yii;
use yii\base\BaseObject;
use AMQPEnvelope;
use AMQPExchange;
use yii\base\InvalidConfigException;
use yii\base\InvalidParamException;

abstract class Task extends BaseObject implements ITask
{
    /**
     * @var string rabbitMq的主机地址
     */
    public $host = "127.0.0.1";

    /**
     * @var string rabbitMq的用户名
     */
    public $username = "guest";

    /**
     * @var string rabbitMq的密码
     */
    public $password = "guest";

    /**
     * @var string rabbitMq的端口 默认5672
     */
    public $port = "5672";

    /**
     * @var string rabbitMq的交换机名称
     */
    public $exchange_name = null;

    /**
     * @var string rabbitMq的交换机类型
     */
    public $exchange_type = "direct";

    /**
     * @var int rabbitMq的交换机的Flags
     */
    public $exchange_flags = 2;

    /**
     * @var string rabbitMq队列名称
     */
    public $queue_name = null;

    /**
     * @var string rabbitMq队列Flags
     */
    public $queue_flags = 2;

    /**
     * @var string rabbitMq消费Flags
     */
    public $consume_flags = 0;

    /**
     * @var string rabbitMq的Routing key
     */
    public $routing_key = null;

    /**
     * @var LoggerInterface
     */
    public $log;

    /**
     * @param array $data
     * @return bool
     */
    abstract public function consume(array $data): bool;

    /**
     * @throws TaskException
     */
    public function init()
    {
        parent::init();
        if (!$this->host) {
            throw new TaskException("host is empty");
        }
        if (!$this->username) {
            throw new TaskException("username is empty");
        }
        if (!$this->password) {
            throw new TaskException("password is empty");
        }
        if (empty($this->log)){
            $this->log = new DefaultLogger();
        }
        if (!$this->log instanceof LoggerInterface) {
            throw new TaskException("invalid logger");
        }
    }
}