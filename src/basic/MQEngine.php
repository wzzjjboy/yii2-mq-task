<?php

namespace yii2\mq_task\basic;


use Yii;
use swoole_process;
use swoole_server;
use yii\base\Component;
use yii2\mq_task\behaviors\SplitLogBehaviors;

/**
 *
 */
class MQEngine extends Component implements Engine
{
    const EVENT_START = 'mq_task_start';

    /**
     * @var ILog
     */
    public $log = 'common\mqTask\basic\Log';

    /**
     * @var string
     */
    public $host = '127.0.0.1';

    /**
     * @var int
     */
    public $port = '9502';

    /**
     * @var bool
     */
    public $daemonize = false;

    /**
     * @var bool 是否启用自定进程名称
     */
    public $namedProcess = false;

    /**
     * @var string 自定义进程名的前缀
     */
    public $processNamePrefix;

    public $tasks = [];

    private $worker_num = 0;

    private $task_sets = [];

    public function behaviors() {
        return [
            'splitLog' => [
                'class' => SplitLogBehaviors::class,
                'engine' => $this,
                'log' => $this->log,
            ]
        ];
    }


    /**
     * @var swoole_server
     */
    private $server;

    private $pid;

    public function init()
    {
        parent::init(); // TODO: Change the autogenerated stub
        if (!is_object($this->log)){
            $this->log = Yii::createObject($this->log);
        }
        if (empty($this->pid)){
            $this->pid = Yii::$app->getRuntimePath() . '/mq.pid';
        }
        if (empty($this->tasks)){
            throw new TaskException("未配置MQ任务,无法启动");
        }
    }

    /**
     * @inheritDoc
     */
    public function start()
    {
        if ($this->namedProcess && empty($this->processNamePrefix)){
            die(sprintf("开启自定义进程名需要配置进程名前缀\n"));
        }
        if($pid = $this->getPid()){
            $this->serverRunning($pid);
        }

        $this->server = new swoole_server('127.0.0.1', $this->port);
        $cnf = [
            'worker_num' => $this->calcWorkerNum(),
            'task_worker_num' => 1,
            'daemonize' => $this->daemonize,
            'log_file' => $this->getLogPath(),
            'pid_file' => $this->pid,
        ];
//        $this->log->info(sprintf("start server:%s", json_encode($cnf)));
        $this->server->set($cnf);
        foreach ([
                     'Start',
                     'ManagerStart',
                     'ManagerStop',
                     'WorkerStart',
                     'WorkerStop',
                     'Connect',
                     'Receive',
                     'Close',
                     'Task',
                     'Finish',
                 ] as $event) {
            $method = "on{$event}";
            if (method_exists($this, $method)){
                $this->server->on($event, [$this, $method]);
            }
        }
        $this->server->start();
    }

    public function onManagerStart(swoole_server $server)
    {
        if ($this->namedProcess) {
            swoole_set_process_name(sprintf("%sManagerProcess", $this->processNamePrefix));
        }
    }
    public function onStart(swoole_server $server)
    {
        if ($this->namedProcess) {
            swoole_set_process_name(sprintf("%sMasterProcess", $this->processNamePrefix));
        }
    }

    private function setProcessName($pName, $server, $worker_id) {
        if ($this->namedProcess){
            swoole_set_process_name($pName);
            $this->log->info(sprintf("自定义%s进程名%s Worker Id:%d", ($server->taskworker ? "Task" : "Worker"), $pName, $worker_id));
        }
    }

    public function onWorkerStart(swoole_server $server, int $worker_id)
    {
        try{
            if ($server->taskworker){
                $pName= sprintf("%sTask%s", $this->processNamePrefix, 'process');
                $this->trigger(self::EVENT_START);
                $this->setProcessName($pName, $server, $worker_id);
            }else {
                $componentId = $this->getTaskByWorkerId($worker_id);
                $task = Yii::$app->get($componentId);
                $pName = sprintf("%sWorker%s", $this->processNamePrefix, $componentId);
                $this->setProcessName($pName, $server, $worker_id);
                /** @var ITask $task */
                $task->start($server, $worker_id);
            }

        }catch (TaskException $taskException){
            $this->handlerTaskException($taskException);
        }catch (\Exception $exception){
            $this->handlerException($exception);
        }catch (\Throwable $throwable){
            $this->log->error(sprintf("PHP Fatal error:%s\nat file:%s\nat line:%s\ntrace:%s", $throwable->getMessage(), $throwable->getFile(), $throwable->getLine(), $throwable->getTraceAsString()));
        }
    }

    public function onReceive(swoole_server $server, int $fd, int $reactor_id, string $taskId)
    {
        $this->log->info("onReceive taskId:{$taskId}");
    }

    public function OnTask(swoole_server $server, int $task_id, int $src_worker_id, $data){
        $this->log->info(sprintf("OnTask taskId:%d srcWorkerId:%d data:%s", $task_id, $src_worker_id, is_array($data) ? json_encode($data, JSON_UNESCAPED_UNICODE) : (is_object($data) ? get_class($data) : $data)));
    }

    function onFinish(swoole_server $server, int $task_id, string $data) {
        $this->log->info(sprintf("onFinish taskId:%d data:%s", $task_id, $data));
    }

    public function getLogPath()
    {
        $path = Yii::$app->getRuntimePath() . '/logs/mq/entry.log';
        is_dir($dir = dirname($path)) or mkdir($dir, 0777, true);
        touch($path);

        return $path;
    }

    /**
     * @param TaskException $taskException
     */
    public function handlerTaskException($taskException)
    {
        $this->log->warning(implode(PHP_EOL, [
            $taskException->getName(),
            $taskException->getMessage(),
            $taskException->getFile(),
            $taskException->getLine(),
            $taskException->getTraceAsString(),
        ]));
    }

    /**
     * @param \Exception $exception
     */
    public function handlerException($exception)
    {
        $this->log->error(implode(PHP_EOL, [
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString(),
        ]));
    }

    private function calcWorkerNum()
    {
        if ($this->worker_num){
            return $this->worker_num;

        }
        foreach ($this->tasks as $name => $num) {
            $this->worker_num += $num;
            for ($i = 0; $i < $num; $i++){
                $this->task_sets[] = $name;
            }
        }

        return $this->worker_num;
    }

    /**
     * @param $worker_id
     * @return string
     */
    private function getTaskByWorkerId($worker_id)
    {
        return $this->task_sets[$worker_id];
    }

    /**
     * @inheritDoc
     */
    public function stop()
    {
        if (!($pid = $this->getPid())){
            $this->serverNotRun();
        } else {
            swoole_process::kill(intval($this->getPid()), SIGTERM);
        }
    }

    /**
     * @inheritDoc
     */
    public function status()
    {
        if($pid = $this->getPid()){
            $this->serverRunning($pid);
        } else {
            $this->serverNotRun();
        }
    }

    /**
     *@inheritDoc
     */
    public function reload()
    {
        if (!($pid = $this->getPid())){
            $this->serverNotRun();
        }
        swoole_process::kill(intval($pid), SIGUSR1);
    }

    public function restart()
    {
        $this->stop();
        sleep(1);
        $this->start();
    }

    public function getPid()
    {
        if(!file_exists($this->pid)){
            return false;
        }
        $pid = file_get_contents($this->pid);
        return swoole_process::kill(intval($pid), 0) ? $pid : false;
    }

    private function serverNotRun()
    {
        die("mqTask服务未启动" . PHP_EOL);
    }

    private function serverRunning($pid)
    {
        die("mqTask服务正运行中... pid:{$pid}" . PHP_EOL);
    }
}
