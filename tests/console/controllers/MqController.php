<?php
/**
 * Created by PhpStorm.
 * User: alan
 * Date: 2018/7/5
 * Time: 17:22
 */

namespace console\controllers;



use Yii;
use yii\console\Controller;
use yii2\mq_task\basic\MQEngine;

class MqController extends Controller
{
    /**
     * @return MQEngine
     * @throws \yii\base\InvalidConfigException
     */
    public function getMQ(){
        return Yii::$app->get("messageQueue");
    }

    /**
     * 启动MQ
     */
    public function actionStart()
    {
        $this->getMQ()->start();
    }

    /**
     * 停止MQ
     */
    public function actionStop()
    {

        $this->getMQ()->stop();
    }

    /**
     * MQ状态查询
     */
    public function actionStatus()
    {
        $this->getMQ()->status();
    }

    /**
     * 服务热重启
     */
    public function actionReload()
    {
        $this->getMQ()->reload();
    }

    /**
     * 重启服务
     */
    public function actionRestart()
    {
        $this->getMQ()->restart();
    }
}