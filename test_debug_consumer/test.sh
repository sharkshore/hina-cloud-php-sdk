#!/bin/bash

# vendor/bin/phpunit --filter testBatchConsumer TestSensorsAnalytics.php
../vendor/bin/phpunit --filter "${1:-test_login_event}" DebugConsumerTest.php
# vendor/bin/phpunit --filter testtuze TestSensorsAnalytics.php