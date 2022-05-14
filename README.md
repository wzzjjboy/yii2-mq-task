Yii2 MQ TASK
================================

1. 特性

2. 安装

   composer require alan/yii2-mq-task:dev-master

3. 配置

   > 1.0.x有消费者卡死的Bug, 解决的办法是升级了swoole到4.5.*的版本，使用Pool代替原来的swoole_service。
   >
   > 2.0 依然依赖swoole
   >
   > 3.0 采用开源项目roadrunner-server/roadrunner 无论是性能还是可控性都大大增强，建设使用3.0版本

   
   
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
     
   - 添加组件配置
    ```php
    // common/config/params.php
    'yii2_mq_task_config' =>  [
        'logger' => function(){ //配置日志，需要实现Psr\Log\LoggerInterface接口
            return \common\components\log\LogFactory::getLogger('yii2_mq_task');
        },
        'beforeProcess' => function(\yii2\mq_task\basic\Task $task) { //用于在处理每个任务时更新logId,Request对象本身没有setLogId方法需要配置行为
            Yii::$app->getRequest()->setLogId();
        }
    ]
    // console/config/main.php
    'components' => [
        'request' => [
            'as beforeAction' => [
                'class' => \common\behaviors\LogIDBehavior::class,
                'name'  => 'console',
            ]
        ],
        'logger' => [
            'class' => 'common\components\log\Logger',
            'processors' => [
                new \Monolog\Processor\PsrLogMessageProcessor(),
                new \common\components\log\UidProcessor(),
            ],
            'handler' => function(){
                $handler = new \Monolog\Handler\RotatingFileHandler(
                    \Yii::$app->getRuntimePath() . DIRECTORY_SEPARATOR. 'logs'. DIRECTORY_SEPARATOR . date('Ym') . DIRECTORY_SEPARATOR ."service.log",
                    0,
                    \Monolog\Logger::INFO,
                    true
                );
                $handler->setFilenameFormat("{date}", "Ymd");
                return $handler;
            },
        ]
    ]    
    //common/components/log/UidProcessor.php
    namespace common\components\log;
   
    use Yii;
   
    class UidProcessor
    {
   
        public function __construct($length = 7)
        {
            if (!is_int($length) || $length > 32 || $length < 1) {
                throw new \InvalidArgumentException('The uid length must be an integer between 1 and 32');
            }
        }
   
        public function __invoke(array $record): array
        {
            $record['extra']['uid'] = $this->getUid();
            return $record;
        }
   
        /**
        * @return string
        */
        public function getUid(): string
        {
            return Yii::$app->getRequest()->getLogId();
        }
    }
    ```
   
   -   添加管理脚本
   
     ```php
     namespace console\controllers;
     
     use Yii;
     use yii\console\Controller;
     use yii2\mq_task\basic\ActionTrait;
     
     class MqController extends Controller
     {
         use ActionTrait;
        
        //测评队列名
        public $queueName = 'invoice.event#from.redis2';
     }
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
   
   - 生成yaml配置文件：php yii mq/get-yaml
   - 生成docker-composer配置文件 php yii mq/get-docker-compose
   - Docker image编译 docker build --no-cache -t yii2_mq_roadrunner:latest .
   - 初始化脚本 docker run --rm -it -v `pwd`:/app --privileged=true  serviceclient_roadrunner init_task.sh
   - docker run --rm -it -v `pwd`:/app --privileged=true  serviceclient_roadrunner /bin/ash /app/vendor/alan/yii2-mq-task/src/bin/init_task.sh
   - 启动  docker-compose -f yii2-mq-roadrunner-docker-compose.yaml  up
   - 进入容器 docker-compose  -f yii2-mq-roadrunner-docker-compose.yaml exec roadrunner_10_21_32_3_5672 ash
   - 测试单任务投递 php  yii mq/product-single
   - 测试批量投递 php  yii mq/product-batch
   - 测试延时任务投递 php  yii mq/product-delay



升级方案：

* 原来task类的

  * ```php
    common\mqTask\basic\Task => yii2\mq_task\basic\Task
    ```

- 注释原来messageQueue组件

- 配置yii2_mq_task_config

  ```php
  //common/config/params.php
  'yii2_mq_task_config' =>  [
          'logger' => function(){ //替换日志组件，默认是打在控制台上
              return \common\components\log\LogFactory::getLogger('yii2_mq_task');
          },
          'beforeProcess' => function(\yii2\mq_task\basic\Task $task) {
              Yii::$app->getRequest()->setLogId(); //在任务处理前生成logId
          },
          'tasks' => [//配置要启动的任务及进程数量
                  'testTask'          => 1
          ]
      ]

- 安装组件 

  ```shell
  compose require alan/yii2-mq-task:^3.0
  ```

  

- 初始化组件 

  ```shell
  docker run --rm -v `pwd`:/app --privileged=true  serviceclient_roadrunner /bin/ash /app/vendor/alan/yii2-mq-task/src/bin/init_task.sh
  ```

  初始化会在两个目录生成配置文件

  console/runtime/yii2-mq-roadrunner 生成roadrunner的配置文件 命名规则rabbitmq的 {ip}_{port}.rr.yaml

  在项目根目录生成docker-compose文件 yii2-mq-roadrunner-docker-compose.yaml

- 启动

  ```shell
  docker-compose -f yii2-mq-roadrunner-docker-compose.yaml  up
  ```

  程序会在每次启动时根据Task消费类和yii2_mq_task_config['tasks']的配置更新配置