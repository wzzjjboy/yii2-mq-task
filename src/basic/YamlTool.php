<?php


namespace yii2\mq_task\basic;


use Yii;
use yii\base\BaseObject;

class YamlTool extends BaseObject
{
    const QueueToContainerMapKey = "yii2_mq_task_queue_to_container";

    /**
     * @var string 镜像名称
     */
    public $dockerImageName = 'ccr.ccs.tencentyun.com/golden-cloud/xx-yii2_mq_roadrunner-prod:1.0';

    /**
     * @var string[] docker 环境变量
     */
    public $dockerEnvironment = ["test_dev=123"];

    /**
     * @var docker-composer.yaml的文件生成位置
     */
    public $dockerDComposeYamlPath = '/app';

    /**
     * @var .rr.yaml的文件生成位置
     */
    public $rrConfigPath = '/app/console/runtime/yii2-mq-roadrunner';

    /**
     * @var string 默认rpc地址，主要用户消息投递
     */
    public $rpcUri = "tcp://0.0.0.0:6001";

    /**
     * @var string php task的入口脚本
     */
    public $phpCommand = "php /app/vendor/alan/yii2-mq-task/src/basic/Consumer.php";

    /**
     * @var string server relay地址
     */
    public $serverRelay = "tcp://127.0.0.1:6002";

    /**
     * @var string task的默认日志级别
     */
    public $logsLevel = 'debug';

    /**
     * 处理线程数量
     * @var int
     */
    public $numPollers = 10;

    /**
     * @var array
     */
    private $box;

    public function init()
    {
        parent::init(); // TODO: Change the autogenerated stub
        $tasks = Yii::$app->params['yii2_mq_task_config']['tasks'] ?? [];
        foreach ($tasks as $taskName => $taskNumber) {
            /** @var Task $p */
            $p = Yii::$app->get($taskName);
            $amqp = AmqpUri::make($p);
            $this->box[$amqp][] = $p;
        }
        if ($this->rrConfigPath && !is_dir($this->rrConfigPath)) {
            mkdir($this->rrConfigPath);
        }
    }

    private function getDockerServerName($host, $port)
    {
        $host = self::fixedQueueName($host);
        return sprintf('roadrunner_%s_%s', $host, $port);
    }

    public function outputDockerCompose()
    {
        $tmp = [];
        $tmp['version'] = '3';
        $tmp['networks']['backend']['driver'] = 'bridge';
        foreach ($this->box as $amqp => $taskList) {
            list(, , $host, $port) = AmqpUri::parse($amqp);
            $dockerName = $this->getDockerServerName($host, $port);
            foreach ($taskList as $task) { //挂载配置文件
                $queue_name = self::fixedQueueName($task->queue_name);
                //映射(新)任务名和窗口，方便在投递时清楚去哪个容器
                array_push($this->dockerEnvironment, $this->getQueueToContainerMap($queue_name, $dockerName));
            }
        }

        foreach ($this->box as $amqp => $taskList) {
            list(, , $host, $port) = AmqpUri::parse($amqp);
            $dockerName = $this->getDockerServerName($host, $port);
            $tmp['services'][$dockerName]['image'] = $this->dockerImageName;
            $tmp['services'][$dockerName]['volumes'][] = ".:/app";
            $tmp['services'][$dockerName]['volumes'][] = "./vendor/alan/yii2-mq-task/src/bin/init_task.sh:/usr/local/bin/init_task.sh";
            $tmp['services'][$dockerName]['volumes'][] = "./vendor/alan/yii2-mq-task/src/bin/start_task.sh:/usr/local/bin/start_task.sh";
            $tmp['services'][$dockerName]['volumes'][] = "./vendor/alan/yii2-mq-task/src/bin/rr:/tmp/rr";
            $hasRrYaml = false;
            $tmp['services'][$dockerName]['tty'] = true;
            $tmp['services'][$dockerName]['environment'] = $this->dockerEnvironment;
            $tmp['services'][$dockerName]['networks'] = ["backend"];
            foreach ($taskList as $task) { //挂载配置文件
                if (!$hasRrYaml && $task->host == $host && $task->port == $port) {
                    $tmp['services'][$dockerName]['volumes'][] = sprintf("./%s:/tmp/%s", ltrim($this->rrConfigPath, '/app/') . '/' . $this->getRrYamlName($task->host, $task->port), '.rr.yaml');
                    $hasRrYaml = true;
                }
            }
        }
        yaml_emit_file($this->dockerDComposeYamlPath . '/yii2-mq-roadrunner-docker-compose.yaml', $tmp, YAML_UTF8_ENCODING);
    }

    public static function getQueueToContainerMap($queueName, $dockerName)
    {
        return self::getQueueToContainerMapKey($queueName) . "=" . $dockerName;
    }

    public static function getContainerNameByQueue($queueName)
    {
        $key = self::getQueueToContainerMapKey($queueName);
        return getenv($key);
    }

    private static function getQueueToContainerMapKey($queue_name)
    {
        return sprintf(sprintf("%s_%s", self::QueueToContainerMapKey, $queue_name));
    }

    public function outputConfigYaml()
    {
        foreach ($this->box as $amqp => $taskList) {
            $tmp = [];
            $tmp["version"] = "2.7";
            $tmp["rpc"]["listen"] = $this->rpcUri;
            $tmp["server"]["command"] = $this->phpCommand;
            $tmp["server"]["relay"] = $this->serverRelay;
            $tmp["logs"]["level"] = $this->logsLevel;
            $tmp["amqp"]["addr"] = $amqp;
            $tmp["jobs"]["num_pollers"] = $this->numPollers;
            $tmp["jobs"]["timeout"] = 60;
            $tmp["jobs"]["pipeline_size"] = 100000;
            $tmp["jobs"]["pool"]["num_workers"] = 10;
            $tmp["jobs"]["pool"]["allocate_timeout"] = 0;
            $tmp["jobs"]["pool"]["destroy_timeout"] = 0;
            /** @var Task $task */
            foreach ($taskList as $task) {
                $queue_name = self::fixedQueueName($task->queue_name);
                $tmp["jobs"]["consume"][] = $queue_name;
                $tmp["jobs"]["pipelines"][$queue_name]['driver'] = 'amqp';
                $tmp["jobs"]["pipelines"][$queue_name]['config'] = [
                    'prefetch' => 10,
                    'priority' => 10,
                    'durable' => true,
                    'delete_queue_on_stop' => false,
                    'queue' => $task->queue_name,
                    'exchange' => $task->exchange_name,
                    'exchange_type' => $task->exchange_type,
                    'routing_key' => $task->routing_key,
                    'exclusive' => false,
                    'multiple_ack' => false,
                    'requeue_on_fail' => false,
                ];
            }
            yaml_emit_file($this->rrConfigPath . '/' . $this->getRrYamlName($task->host, $task->port), $tmp, YAML_UTF8_ENCODING);
        }
    }

    private function getRrYamlName($host, $port)
    {
        return sprintf("%s_%s.rr.yaml", $host, $port);
    }

    public static function fixedQueueName($name)
    {
        return str_replace([".", "#"], ['_'], $name);
    }
}