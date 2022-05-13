<?php


namespace yii2\mq_task\basic;


use common\components\log\LogFactory;

trait ActionTrait
{
    /**
     * 根据配置生成yaml配置文件
     */
    public function actionGetYaml()
    {
        $y = new YamlTool();
        $y->outputConfigYaml();
    }

    /**
     * 根据配置生成docker-composer.yaml文件
     */
    public function actionGetDockerComposeYaml()
    {
        $y = new YamlTool();
        $y->outputDockerCompose();
    }

    public function getTestData()
    {
        $data = [
            'channel' => 'make_invoice_success_topic',
            'payload' => json_encode(["invoice_id" => "6755005998341209174", "order_sn" => "6755005998341209174"])
        ];
        return $data;
    }

    public function actionProductSingle()
    {
        $data = $this->getTestData();
        $p = new Product();
        $p->single($this->queueName, $data);
    }

    public function actionProductBatch()
    {
        $data = [];
        for ($i = 0; $i < 500; $i++) {
            $data[] = $this->getTestData();
        }
        $p = new Product();
        $p->batch($this->queueName, ...$data);
    }

    public function actionProductDelay()
    {
        $data = $this->getTestData();
        $p = new Product();
        $p->delay($this->queueName, $data, 10);
        echo date("Y-m- H:i:s") ."\n";
    }

}