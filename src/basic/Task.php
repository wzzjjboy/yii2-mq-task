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
use yii\base\InvalidParamException;

abstract Class Task extends BaseObject implements ITask
{
    public $host            = "172.25.0.4";

    public $username        = "guest";

    public $password        = "guest";

    public $port            = "5672";

    public $exchange_name    = null;

    public $exchange_type    = AMQP_EX_TYPE_DIRECT;

    public $exchange_flags   = AMQP_DURABLE;

    public $queue_name       = null;

    public $queue_flags      = AMQP_DURABLE;

    public $consume_flags    = AMQP_NOPARAM;

    public $routing_key      = null;

    public $max_run_count    = 100000;

    /**
     * @var MQEngine
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

    public function init()
    {
        parent::init();
        if (!$this->host){
            throw new TaskException("host is empty");
        }
        if (!$this->username){
            throw new TaskException("username is empty");
        }
        if (!$this->password){
            throw new TaskException("password is empty");
        }
        if (is_string($this->engine)){
            $this->engine = Yii::$app->get($this->engine);
        }
        if (!$this->log){
            $this->log = $this->engine->log;
        }
    }

    public function start(\swoole_server $server, int $worker_id)
    {
        $run_count = 0;
        do{
            $this->getAMQPExchange();
            $queue = $this->getAMQPQueue();
            $this->bind();
            try{
                if ($envelope = $this->getAMQPQueue()->get($this->consume_flags)){
//                    echo sprintf("handler task: %d max task num:%d pid:%s\n", $run_count, $this->max_run_count, $worker_id);
                    $this->handlerTask($envelope, $queue, $worker_id);
                    $run_count++;
                } else {
                    sleep(1);
                }
            }catch (AMQPChannelException $AMQPChannelException){
                $this->engine->log->warning("AMQPChannelException: {$AMQPChannelException->getMessage()}");
                $this->disconnect();
                continue;
            }catch (AMQPConnectionException $AMQPConnectionException){
                $this->engine->log->warning("AMQPChannelException: {$AMQPConnectionException->getMessage()}");
                $this->disconnect();
                continue;
            }catch (\Exception $exception){
                $this->engine->log->error(sprintf("PHP Fatal Exception:%s\nat file:%s\nat line:%s\ntrace:%s", $exception->getMessage(), $exception->getFile(), $exception->getLine(), $exception->getTraceAsString()));
            }catch (\Error $error) {
                $this->engine->log->error(sprintf("PHP Fatal error:%s\nat file:%s\nat line:%s\ntrace:%s", $error->getMessage(), $error->getFile(), $error->getLine(), $error->getTraceAsString()));
            }
        } while ($this->max_run_count > $run_count);
        $server->reload();
    }

    public function handlerTask(AMQPEnvelope $envelope, AMQPQueue $queue, int $worker_id)
    {
        if (empty($envelope->getBody()) || !is_array($message = json_decode($envelope->getBody(),true))){
            $queue->ack($envelope->getDeliveryTag()); //手动发送ACK应答
            $this->log->warning("无效的请求参数：{$envelope->getBody()}");
            return false;
        }
        try{
            $this->log->info(sprintf("worker:%d hand task at:%s input:%s",$worker_id, get_class($this), $envelope->getBody()));
            $result = $this->consume($message);
            if (true === $result){
                $queue->ack($envelope->getDeliveryTag()); //手动发送ACK应答
            } else {
                return false;
            }
        }catch (\Exception $e){
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

    private function getConnect()
    {
        try{
            if ($this->connect && $this->connect->isConnected()){
                return $this->connect;
            }
            $connection = new AMQPConnection();
            $connection->setHost($this->host);
            $connection->setPort($this->port);
            $connection->setLogin($this->username);
            $connection->setPassword($this->password);
            $connection->connect();
            return $this->connect = $connection;
        }catch (AMQPConnectionException $exception){
            throw new AMQPChannelException($exception->getMessage() . "{$this->host}:{$this->port}");
        }
    }

    private function clearConnect()
    {
        if ($this->connect){
            $this->connect = null;
        }
    }

    private function getChannel()
    {
        if($this->channel && $this->channel->isConnected()){
            return $this->channel;
        }
        return $this->channel = new AMQPChannel($this->getConnect());
    }

    private function clearChannel()
    {
        if ($this->channel){
            $this->channel = null;
        }
    }

    protected function getAMQPExchange()
    {
        if ($this->exchange && $this->exchange->getChannel()->isConnected()){
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
        if ($this->exchange){
            $this->exchange = null;
        }
    }

    protected function getAMQPQueue()
    {
        if ($this->queue && $this->queue->getChannel()->isConnected()){
            return $this->queue;
        }
        $queue = new AMQPQueue($this->getChannel());
        $queue->setFlags($this->queue_flags);
        $this->queue_name && $queue->setName($this->queue_name);
        $queue->declareQueue();
        return $this->queue = $queue;
    }

    private function clearQueue() {
        $this->queue = null;
    }

    private $is_bind = false;

    protected function bind($queue = null, $exchange_name = null, $routing_key = null)
    {
        if ($this->is_bind){
            return;
        }

        if(is_null($queue)){
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
     * @throws \yii\base\InvalidConfigException
     */
    public static function publish(string $id, string $message)
    {
        /** @var Task $obj */
        $obj = Yii::$app->get($id);
        $exchange = $obj->getAMQPExchange();
        $exchange->setName($obj->exchange_name);
        $res = $exchange->publish($message, $obj->routing_key);
        if (Yii::$app->getRequest()->isConsoleRequest){
            $obj->log->info(["method" => "publish", "input" =>  $message, "output" => $res]);
        }
        return $res;
    }
}