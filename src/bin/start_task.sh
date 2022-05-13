#!/bin/ash
cd /app \
&& /usr/local/bin/php yii mq/get-yaml \
&& /usr/local/bin/php yii mq/get-docker-compose-yaml \
&& cd /tmp \
&& /tmp/rr serve -c /tmp/.rr.yaml