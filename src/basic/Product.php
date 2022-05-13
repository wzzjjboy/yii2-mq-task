<?php


namespace yii2\mq_task\basic;


use yii\base\BaseObject;
use Spiral\Goridge\RPC\RPC;
use Spiral\RoadRunner\Jobs\Jobs;
use Spiral\RoadRunner\Jobs\Options;
use Spiral\RoadRunner\Jobs\Exception\JobsException;
use Spiral\RoadRunner\Jobs\Serializer\JsonSerializer;

class Product extends BaseObject
{
    private $rpcUriTmp = "tcp://%s:%s";

    public $rpcPort = '6001';

    /**
     * @var Jobs
     */
    private $jobs;

    public function init()
    {
    }

    public function single($queueName, $data = [])
    {
        $queueName = YamlTool::fixedQueueName($queueName);
        $jobs = $this->getJobs($queueName);
        $queue = $jobs->connect($queueName);
        $task = $queue->create($queueName, $data);
        try {
            return $queue->dispatch($task);
        } catch (JobsException $e) {
            throw $e;
        }
    }

    public function batch($queueName, ...$dataList)
    {
        $queueName = YamlTool::fixedQueueName($queueName);
        $jobs = $this->getJobs($queueName);
        $queue = $jobs->connect($queueName);
        if (empty($dataList)) {
            throw new \InvalidArgumentException("invalid data list argument");
        }
        $tasks = [];
        foreach ($dataList as $data) {
            $tasks[] = $queue->create($queueName, $data);
        }
        try {
            $queue->dispatchMany(...$tasks);
        } catch (JobsException $e) {
            throw $e;
        }
    }

    public function delay($queueName, $data, $second)
    {
        $queueName = YamlTool::fixedQueueName($queueName);
        $jobs = $this->getJobs($queueName);
        $queue = $jobs->connect($queueName);
        try {
            return $queue->push($queueName, $data, new Options(
                intval($second)
            ));
        } catch (JobsException $e) {
            throw $e;
        }
    }

    private function getJobs($queueName)
    {
        $this->jobs = new Jobs(
            RPC::create($this->getRpcUri($queueName)),
            new JsonSerializer()
        );
        if (!$this->jobs->isAvailable()) {
            throw new \LogicException('The server does not support "jobs" functionality...');
        }
        return $this->jobs;
    }

    private function getRpcUri($queueName)
    {
        return sprintf($this->rpcUriTmp, YamlTool::getContainerNameByQueue($queueName), $this->rpcPort);
    }
}