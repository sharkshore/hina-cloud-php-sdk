@echo off

REM 设置默认测试过滤器为 "test_login_event"，如果传入参数则使用参数值
set filter=%1
if "%filter%"=="" set filter=test_login_event

REM 运行 phpunit 测试
..\vendor\bin\phpunit --filter %filter%  DebugConsumerTest.php
