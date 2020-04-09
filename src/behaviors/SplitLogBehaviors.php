<?php
/**
 * Created by PhpStorm.
 * User: alan
 * Date: 2018/9/14
 * Time: 11:46
 */

namespace yii2\mq_task\behaviors;

use swoole_process;
use yii\base\Behavior;
use yii2\mq_task\basic\Log;
use yii2\mq_task\basic\MQEngine;

class SplitLogBehaviors extends Behavior
{
    /**
     * @var MQEngine
     */
    public $engine;

    /**
     * @var Log
     */
    public $log;

    /**
     * @var integer 日志刷新时间间隔
     */
    public $interval = 86400;


    public function events()
    {
        return [
            MQEngine::EVENT_START => [$this, "onStart"],
        ];
    }

    public function onStart()
    {
        list($firstInterval, $nextInterval)  = $this->calcInterval();
        $this->log->info(sprintf("计算日志切割时间间隔:%d %d", $firstInterval, $nextInterval));
        if ($firstInterval){
            swoole_timer_after($firstInterval, function() use ($nextInterval){
                $this->action();
                $this->tickRun($nextInterval);
            });
        } else {
            $this->tickRun($nextInterval);
        }
    }

    /**
     * 计算时间时隔
     * @return array
     */
    private function calcInterval()
    {
        $tomorrow = strtotime("+1 day");
        return [
            1000 * (mktime(0,0,0, date("m", $tomorrow), date("d", $tomorrow), date("Y", $tomorrow))  - time()),
            1000 * $this->interval,
        ];
    }

    /**
     * 首次执行
     * @param $nextInterval
     */
    private function tickRun($nextInterval){
        swoole_timer_tick($nextInterval, [$this, 'action']);
    }

    /**
     * 主要操作入口
     */
    public function action()
    {
        if($this->changeLogFile()){
            $this->log->info("修改日志文件成功");
            $this->restartLog();
            $this->log->info("重启swoole日志成功");
        } else {
            $this->log->error("切换swoole日志失败!");
        }
    }

    /**
     * 修改日志文件名
     * @return bool
     */
    public function changeLogFile()
    {
        $path = $this->engine->getLogPath();
        $p_info = pathinfo($path);
        $ext = '.' . ltrim($p_info['extension'], '.');
        $b_name = $p_info['basename'];
        $yesterday = strtotime("yesterday");
        $nb_name = date('Ym', $yesterday) . DIRECTORY_SEPARATOR . date('d', $yesterday) . $ext;
        $new = str_replace($b_name, $nb_name, $path);
        if (($dir_name = dirname($new)) && !is_dir($dir_name)){
            @mkdir($dir_name, 0777);
        }
//        $this->log->info(sprintf("copy %s to %s and clear %s", basename($path), basename($new), basename($path)));
        return copy($path, $new) && (false !== file_put_contents($path, "", 0));
    }

    /**
     * 重启日志
     */
    private function restartLog()
    {
        $this->engine->getPid() && swoole_process::kill($this->engine->getPid(), 34);
    }
}