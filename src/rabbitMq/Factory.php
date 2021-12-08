<?php


namespace yii2\metrics\rabbitMq;

use Yii;

class Factory {

    private $cnf;

    public $taskMap;

    private $taskObj;

    public function __construct(array $taskMap) {
        $tasks = Yii::$app->messageQueue->tasks;
        $this->taskMap = $taskMap;
        foreach ($tasks as $task => $_taskNum) {
            $component = Yii::$app->get($task);
            $this->cnf[$task]['host'] = $component->host;
            $this->cnf[$task]['port'] = $component->port;
            $this->cnf[$task]['username'] = $component->username;
            $this->cnf[$task]['password'] = $component->password;
            $this->cnf[$task]['exchange_name'] = $component->exchange_name;
            $this->cnf[$task]['queue_name'] = $component->queue_name;
            $this->cnf[$task]['routing_key'] = $component->routing_key;
        }
        foreach ($this->cnf as $task => $item) {
            $this->taskObj[$task] = $this->createTask($task, $item);
        }
    }

    /**
     * @return Task[]
     */
    public function getIterator() {
        return $this->taskObj;
    }

    public function getTaskNotifyNum($task) {
        return $this->taskMap[$task][0] ?? 100;
    }

    private function getServerName(string $task) {
        return $this->taskMap[$task][1] ?? "未知服务";
    }

    /**
     * @param $task
     * @param $item
     * @return Task
     * @throws \yii\base\InvalidConfigException
     */
    private function createTask($task, $item) {
        return Yii::createObject([
            'class' => Task::class,
            'task'  => $task,
            'mqHost' => $item['host'],
            'mqPort' => $item['port'],
            'queryName' => $item['queue_name'],
            'mqUsername' => $item['username'],
            'mqPassword' => $item['password'],
            'notifyTaskNum' => $this->getTaskNotifyNum($task),
            'serverName' => $this->getServerName($task),
            'date'       => date("Y-m-d H:i:s"),
        ]);
    }
}