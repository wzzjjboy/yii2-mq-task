<?php

namespace console\mqTask;


use yii2\mq_task\basic\Task;

class InvoiceRedisEvent extends Task {

    /**
     * @param array $data
     * @return bool
     */
    public function consume(array $data): bool {
        // TODO: Implement consume() method.
//        print_r($data);
        sleep(60);
        return true;
    }
}