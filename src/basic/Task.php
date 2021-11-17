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
    public $exchange_type = AMQP_EX_TYPE_DIRECT;

    /**
     * @var int rabbitMq的交换机的Flags
     */
    public $exchange_flags = AMQP_DURABLE;

    /**
     * @var string rabbitMq队列名称
     */
    public $queue_name = null;

    /**
     * @var string rabbitMq队列Flags
     */
    public $queue_flags = AMQP_DURABLE;

    /**
     * @var string rabbitMq消费Flags
     */
    public $consume_flags = AMQP_NOPARAM;

    /**
     * @var string rabbitMq的Routing key
     */
    public $routing_key = null;

    /**
     * @var int 任务最大运行次数 超过次数会退出并重启进程，以达到释放资源的目的
     */
    public $max_run_count = 10000;

    /**
     * @var int 任务当前运行的次数
     */
    private $run_count = 0;

    /**
     * @var MQEngine 驱动引擎 使用swoole的Process
     */
    public $engine = 'messageQueue';

    /**
     * @var AMQPChannel
     */
    private $channel;
    /**
     * @var AMQPConnection
     */
    private $connect;

    /**
     * @var AMQPQueue
     */
    private $queue;

    /**
     * @var AMQPExchange
     */
    private $exchange;

    /**
     * @var ILog
     */
    protected $log;


    /**
     * @param array $data
     * @return bool
     */
    abstract public function consume(array $data): bool;

    /**
     * @throws TaskException
     * @throws InvalidConfigException
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
        if (is_string($this->engine)) {
            $this->engine = Yii::$app->get($this->engine);
        }
        if (!$this->log) {
            $this->log = $this->engine->log;
        }
    }

    /**
     * @param int $worker_id
     * @param bool $free
     * @throws AMQPChannelException
     * @throws AMQPConnectionException
     * @throws \AMQPExchangeException
     * @throws \AMQPQueueException
     */
    public function start(int $worker_id, bool &$free)
    {
        $this->getAMQPExchange();
        $queue = $this->getAMQPQueue();
        $this->bind();
        try {
            if ($envelope = $this->getAMQPQueue()->get($this->consume_flags)) {
                $this->handlerTask($envelope, $queue, $worker_id);
                $this->run_count++;
                $this->log->info(sprintf("worker:%s consumer task count:%d", $worker_id, $this->run_count));
                if ($this->run_count > $this->max_run_count) {
                    $free = true;
                }
            } else {
                sleep(1);
            }
        } catch (AMQPChannelException $AMQPChannelException) {
            $this->engine->log->warning("AMQPChannelException: {$AMQPChannelException->getMessage()}");
            $this->disconnect();
        } catch (AMQPConnectionException $AMQPConnectionException) {
            $this->engine->log->warning("AMQPChannelException: {$AMQPConnectionException->getMessage()}");
            $this->disconnect();
        } catch (\Exception $exception) {
            $this->engine->log->error(sprintf("PHP Fatal Exception:%s\nat file:%s\nat line:%s\ntrace:%s", $exception->getMessage(), $exception->getFile(), $exception->getLine(), $exception->getTraceAsString()));
        } catch (\Error $error) {
            $this->engine->log->error(sprintf("PHP Fatal error:%s\nat file:%s\nat line:%s\ntrace:%s", $error->getMessage(), $error->getFile(), $error->getLine(), $error->getTraceAsString()));
        }
    }

    /**
     * @param AMQPEnvelope $envelope
     * @param AMQPQueue $queue
     * @param int $worker_id
     * @return false
     * @throws AMQPChannelException
     * @throws AMQPConnectionException
     */
    public function handlerTask(AMQPEnvelope $envelope, AMQPQueue $queue, int $worker_id): bool
    {
        $request = YII::$app->request;
        if (method_exists($request, 'setLogId')) {
            $request->setLogId();
        }
        if (empty($message = $envelope->getBody()) || !is_array($message = json_decode($message, true))) {
            $queue->ack($envelope->getDeliveryTag()); //手动发送ACK应答
            $this->log->warning("无效的请求参数：{$envelope->getBody()}");
            return false;
        }
        try {
            $this->log->info(sprintf("worker:%d hand task at:%s input:%s", $worker_id, get_class($this), $envelope->getBody()));
            $result = $this->consume($message);
            if (true === $result) {
                $queue->ack($envelope->getDeliveryTag()); //手动发送ACK应答
                return true;
            } else {
                $queue->nack($envelope->getDeliveryTag());
                return false;
            }
        } catch (\Exception $e) {
            $this->log->error([
                'method' => 'had exception1',
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'input' => $envelope->getBody(),
                'output' => $e->getMessage(),
                'traceAsString' => $e->getTraceAsString(),
            ]);
            return false;
        }
    }

    /**
     * @return AMQPConnection
     * @throws AMQPChannelException
     */
    private function getConnect(): AMQPConnection
    {
        try {
            if ($this->connect && $this->connect->isConnected()) {
                return $this->connect;
            }
            $connection = new AMQPConnection();
            $connection->setHost($this->host);
            $connection->setPort($this->port);
            $connection->setLogin($this->username);
            $connection->setPassword($this->password);
            $connection->connect();
            return $this->connect = $connection;
        } catch (AMQPConnectionException $exception) {
            throw new AMQPChannelException($exception->getMessage() . "{$this->host}:{$this->port}");
        }
    }

    private function clearConnect()
    {
        if ($this->connect) {
            $this->connect = null;
        }
    }

    /**
     * @return AMQPChannel
     * @throws AMQPChannelException
     * @throws AMQPConnectionException
     */
    private function getChannel()
    {
        if ($this->channel && $this->channel->isConnected()) {
            return $this->channel;
        }
        return $this->channel = new AMQPChannel($this->getConnect());
    }

    private function clearChannel()
    {
        if ($this->channel) {
            $this->channel = null;
        }
    }

    /**
     * @return AMQPExchange
     * @throws AMQPChannelException
     * @throws AMQPConnectionException
     * @throws \AMQPExchangeException
     */
    protected function getAMQPExchange(): AMQPExchange
    {
        if ($this->exchange && $this->exchange->getChannel()->isConnected()) {
            return $this->exchange;
        }
        $exchange = new AMQPExchange($this->getChannel());
        $exchange->setType($this->exchange_type);
        $exchange->setName($this->exchange_name);
        $exchange->setFlags($this->exchange_flags);
        $exchange->declareExchange();
        return $this->exchange = $exchange;
    }

    private function clearAMQPExchange()
    {
        if ($this->exchange) {
            $this->exchange = null;
        }
    }

    /**
     * @return AMQPQueue
     * @throws AMQPChannelException
     * @throws AMQPConnectionException
     * @throws \AMQPQueueException
     */
    protected function getAMQPQueue(): AMQPQueue
    {
        if ($this->queue && $this->queue->getChannel()->isConnected()) {
            return $this->queue;
        }
        $queue = new AMQPQueue($this->getChannel());
        $queue->setFlags($this->queue_flags);
        $this->queue_name && $queue->setName($this->queue_name);
        $queue->declareQueue();
        return $this->queue = $queue;
    }

    private function clearQueue()
    {
        $this->queue = null;
    }

    private $is_bind = false;

    /**
     * @param null $queue
     * @param null $exchange_name
     * @param null $routing_key
     * @throws AMQPChannelException
     * @throws AMQPConnectionException
     * @throws \AMQPQueueException
     */
    protected function bind($queue = null, $exchange_name = null, $routing_key = null)
    {
        if ($this->is_bind) {
            return;
        }

        if (is_null($queue)) {
            $queue = $this->getAMQPQueue();
        }
        $queue->bind($exchange_name ?: $this->exchange_name, $routing_key ?: $this->routing_key);
        $this->is_bind = true;
    }

    private function disconnect()
    {
        $this->clearQueue();
        $this->clearAMQPExchange();
        $this->clearChannel();
        $this->clearConnect();
    }

    public function __destruct()
    {
        $this->disconnect();
    }

    /**
     * 投递任务
     * @param string $id
     * @param string $message
     * @return bool
     * @throws AMQPChannelException
     * @throws AMQPConnectionException
     * @throws \AMQPExchangeException
     * @throws InvalidConfigException
     */
    public static function publish(string $id, string $message): bool
    {
        /** @var Task $obj */
        $obj = Yii::$app->get($id);
        $exchange = $obj->getAMQPExchange();
        $exchange->setName($obj->exchange_name);
        $res = $exchange->publish($message, $obj->routing_key);
        if (Yii::$app->getRequest()->isConsoleRequest) {
            $obj->log->info(["method" => "publish", "input" => $message, "output" => $res]);
        }
        return $res;
    }
}