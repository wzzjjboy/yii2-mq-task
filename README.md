Yii2 MQ TASK
================================

1. 特性

2. 安装

   composer require alan/yii2-mq-task:dev-master

3. 配置

   - 添加配置

     ```php
     'db' => [ //默认db配置
         'class' => 'yii\db\Connection',
         'dsn' => 'mysql:host=host;port=port;dbname=dbname',
         'username' => 'username',
         'password' => 'password',
         'charset' => 'utf8',
         'tablePrefix' => 'tablePrefix',
         'commandClass' => 'yii2\mq_task\components\DbCommand',
      ],
     'redis' => [	//redis配置
         'class' => 'yii2\mq_task\components\RedisConnection',
         'hostname' => 'hostname',
         'port' => 'port',
         'database' => 1, 
         'password' => 'password'
     ],
     'invoiceRedisEvent' => [ //mq_task的名字
         'class'         => 'console\mqTask\InvoiceRedisEvent',
         'host'          => 'host',
         'port'          => 'port',
         'username'      => 'username',
         'password'      => 'password',
         'exchange_name' => 'exchange_name',
         'queue_name'    => 'queue_name',
         'routing_key'   => 'routing_key',
     ],
     'messageQueue'              => [
             'class'     => 'yii2\mq_task\basic\MQEngine',
             'host'      => '127.0.0.1',
             'port'      => '9502',
             'daemonize' => true,
             'log'       => [
                 'class'    => 'yii2\mq_task\basic\Log',
                 'category' => 'mq_task',
             ],
             'tasks'     => [
                 'invoiceRedisEvent'          => 5, //要处理的mq_task和对应的进程数
             ]
     ]
     
     ```
     
     
   
   -   添加启动脚本
   
     ```php
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
     ```
     
     
     
   - 启动
   
     ```php
     php yii mq/start
     ```
     
    - 停止
        ```php
        php yii mq/stop
        ```
        
    - 热重启
      
           ```php
           php yii mq/reload
           ```
        
    - 重启             
           ```php
           php yii mq/restart
           ```
    - 查看状态             
          ```php
          php yii mq/status
          ```
   - 书写任务消费类
   
     ```php
     namespace console\mqTask;
     
     
     use yii2\mq_task\basic\Task;
     
     class InvoiceRedisEvent extends Task {
     
         /**
          * @param array $data
          * @return bool
          */
         public function consume(array $data): bool {
             // TODO: Implement consume() method.
             print_r($data);//在这里处理任务
             return true;
         }
     }
     ```