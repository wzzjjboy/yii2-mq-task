<?php

namespace yii2\mq_task\basic;

use Yii;
use Swoole\Process;
use Swoole\Process\Pool;
use yii\base\Component;
use yii\base\NotSupportedException;

/**
 *
 */
class MQEngine extends Component implements Engine
{
    use ResponseTrait;

    /**
     * @var ILog
     */
    public $log;

    /**
     * @var string
     */
    public $pidName = 'mqTask';

    /**
     * task 名称和数量的配置关系
     * @var array
     */
    public $tasks = [];

    /**
     * @var string 自定义进程名的前缀
     */
    public $processNamePrefix;

    /**
     * @var array
     */
    private $taskSets = [];

    /**
     * @var int
     */
    private $workerNum = 0;

    /**
     * @throws \yii\base\InvalidConfigException
     */
    public function init()
    {
        if (empty($this->log)) {
            $this->log = [
                'class' => Log::class,
            ];
        }
        if (is_array($this->log)) {
            $this->log = Yii::createObject($this->log);
        }
        if (!$this->log instanceof ILog) {
            $this->InvalidArgument("无效的log配置");
        }
    }

    public function start()
    {
        if (($pid = $this->isRunning())) {
            $this->Running($pid);
            return;
        }

        $pool = new Pool($this->calcWorkerNum());
        $pool->on("WorkerStart", function (Pool $pool, $workerId) {
            /** @var Process $process */
            $process = $pool->getProcess($workerId);
            $this->log->info("onWorkerStart: workerId:{$workerId}  pid:{$process->pid}");
            $this->savePid(posix_getppid());
            $componentId = $this->getTaskByWorkerId($workerId);
            $task = Yii::$app->get($componentId);
            $running = true;
            pcntl_signal(SIGTERM, function () use (&$running, $process) {
                $running = false;
                $this->log->info("pid:{$process->pid} 收到SIGTERM信号，准备退出...");
            });
            $pName = sprintf("%sWorker%s", $this->processNamePrefix, $componentId);
            $process->name($pName);
            while ($running) {
                $free = false;
                /** @var ITask $task */
                $task->start($workerId, $free);
                pcntl_signal_dispatch();
                if ($free === true){
//                    $process->close();
                    Process::kill($process->pid, SIGTERM);
                }
            }
        });
        $pool->start();
    }

    public function stop()
    {
        if (!($pid = $this->getPid())) {
            $this->Term();
            return;
        }
        Process::kill($pid, SIGTERM);
    }

    public function status()
    {
        if (!($pid = $this->isRunning())) {
            $this->Term();
            return;
        }
        echo sprintf("MqTask正在运行：%s。。。\n", $pid);
    }

    private function isRunning(): int
    {
        if (!($pid = $this->getPid()) || (!Process::kill($pid, 0))) {
            return 0;
        }
        return $pid;
    }

    public function reload()
    {
        if (!($pid = $this->getPid())) {
            $this->Term();
            return;
        }
        Process::kill($pid, SIGUSR1);
    }

    /**
     * @throws NotSupportedException
     */
    public function restart()
    {
        throw new NotSupportedException();
    }

    private function calcWorkerNum(): int
    {
        if ($this->workerNum) {
            return $this->workerNum;

        }
        foreach ($this->tasks as $name => $num) {
            $this->workerNum += $num;
            for ($i = 0; $i < $num; $i++) {
                $this->taskSets[] = $name;
            }
        }

        return $this->workerNum;
    }

    /**
     * @param $worker_id
     * @return string
     */
    public function getTaskByWorkerId($worker_id): string
    {
        return $this->taskSets[$worker_id];
    }

    private function savePid($pid)
    {
        file_put_contents($this->getPidFile(), $pid);
    }

    private function getPid()
    {
        if (!file_exists($this->getPidFile())) {
            return 0;
        }
        return file_get_contents($this->getPidFile());
    }

    private function getPidFile(): string
    {
        return Yii::$app->getRuntimePath() . DIRECTORY_SEPARATOR . $this->pidName .'.pid';
    }
}
