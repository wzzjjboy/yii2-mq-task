#!/usr/bin/env php
<?php

defined('YII_DEBUG') or define('YII_DEBUG', true);
defined('YII_ENV') or define('YII_ENV', 'dev');

require(__DIR__ . '/../../../../autoload.php');
require(__DIR__ . '/../../../../yiisoft/yii2/Yii.php');
require(__DIR__ . '/../../../../../common/config/bootstrap.php');
require(__DIR__ . '/../../../../../console/config/bootstrap.php');

$config = \yii\helpers\ArrayHelper::merge(
    require(__DIR__ . '/../../../../../common/config/main.php'),
    require(__DIR__ . '/../../../../../common/config/main-local.php'),
    require(__DIR__ . '/../../../../../console/config/main.php'),
    require(__DIR__ . '/../../../../../console/config/main-local.php')
);

new \yii\console\Application($config);


use Spiral\RoadRunner\Jobs\Task\ReceivedTaskInterface;
use Symfony\Component\Console\Logger\ConsoleLogger;
use yii\base\BaseObject;
use Spiral\RoadRunner\Jobs\Exception\JobsException;
use Spiral\RoadRunner\Jobs\Serializer\JsonSerializer;
use Spiral\RoadRunner\Jobs\Task\TaskInterface;
use Spiral\RoadRunner\Jobs\Consumer as BConsumer;
use yii2\mq_task\basic\ITask;
use yii2\mq_task\basic\Task;
use Psr\Log\LoggerInterface;
use yii2\mq_task\basic\YamlTool;
use yii2\mq_task\basic\TaskException;


class Consumer extends BaseObject
{
    public $shouldBeRestarted = false;

    /**
     * task 名称和数量的配置关系
     * @var array
     */
    public $tasks = [];

    /**
     * @var LoggerInterface
     */
    public $log;

    /**
     * @var Closure
     */
    public $beforeProcess;

    /**
     * @var BConsumer
     */
    private $consumer;

    /**
     * @var []
     */
    private $handlerMap = [];

    /**
     * 队列名映射
     * @var array
     */
    private $queueMapN2O = [];


    public function init()
    {
        parent::init(); // TODO: Change the autogenerated stub
        $this->consumer = new BConsumer(null, new JsonSerializer());
        if (empty($this->tasks)) {
            $this->tasks = Yii::$app->params['yii2_mq_task_config']['tasks'] ?? [];
        }
        if (empty($this->tasks)) {
            throw new TaskException("invalid task setting.");
        }
        if (Yii::$app->params['yii2_mq_task_config']['logger'] ?? null) {
            $this->log = Yii::$app->params['yii2_mq_task_config']['logger']();
        }elseif (empty($this->log)){
            $this->log = new ConsoleLogger();
        }
        if (!$this->log instanceof LoggerInterface) {
            throw new TaskException("invalid logger.");
        }
        foreach ($this->tasks as $taskName => $taskNumber) {
            /** @var Task $p */
            $p = Yii::$app->get($taskName);
            $p->log = $this->log;
            $newQueueName = YamlTool::fixedQueueName($p->queue_name);
            $this->handlerMap[$p->queue_name] = $p;
            $this->queueMapN2O[$newQueueName] = $p->queue_name;
        }
    }

    /**
     * @var ReceivedTaskInterface $task
     * @return string
     */
    private function getOldQueueName($task) {
        return $this->queueMapN2O[$task->getQueue()] ?? sprintf('(not found old queue name:%s)', $task->getQueue());
    }

    public function run()
    {
        while ($task = $this->consumer->waitTask()) {
            try {
                if (is_callable($this->beforeProcess)){
                    call_user_func($this->beforeProcess, $task);
                }
                $queueName = $task->getQueue();
                $payload = $task->getPayload();
                $handler = $this->getHandler($queueName);
                $jsonPayload =$this->getJsonPayload($task);
                if (empty($handler)) {
                    $this->taskComplete($task);
                    $this->log->warning(sprintf("task not found handler. queue:%s. task payload:%s", $queueName, $jsonPayload));
                    continue;
                }
                if (true === $handler->consume($payload)) {
                    $this->log->info(sprintf("task is complete.  queue:%s payload:%s", $queueName, $jsonPayload));
                    $this->taskComplete($task);
                } else {
                    $this->taskComplete($task);
                    $this->log->warning(sprintf("task task will be abandoned. queue:%s, payload:%s ", $queueName, $jsonPayload));
                }
            } catch (\Throwable $e) {
                $this->log->error(sprintf("task has exception. msg:%s file:%s line:%s. queue:%s, payload:%s", $e->getMessage(), $e->getFile(), $e->getLine(),  $queueName, $jsonPayload));
                $this->taskFail($e, $task);
            }
        }
    }

    /**
     * @param string|\Stringable|\Throwable $error
     * @param $task TaskInterface
     */
    private function taskFail($error, $task)
    {
        try {
            $task->fail($error, $this->shouldBeRestarted);
        } catch (JobsException $jobsException) {
            $this->log->error(sprintf("task fail (%s)  get exception:%s", $this->getJsonPayload($task), $jobsException->getMessage()));
        }
    }

    /**
     * @param $task TaskInterface
     */
    private function taskComplete($task)
    {
        try {
            $task->complete();
        } catch (JobsException $jobsException) {
            $this->log->error(sprintf("task complete (%s)  get exception:%s", $this->getJsonPayload($task), $jobsException->getMessage()));
        }
    }

    /**
     * @param $task TaskInterface
     * @return string
     */
    private function getJsonPayload($task)
    {
        return json_encode($task->getPayload(), JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param string $queueName
     * @return false | ITask
     */
    private function getHandler(string $queueName)
    {
        if (!isset($this->handlerMap[$queueName])) {
            return false;
        }
        return $this->handlerMap[$queueName];
    }
}

(new Consumer())->run();