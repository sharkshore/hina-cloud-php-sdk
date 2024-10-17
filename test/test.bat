@echo off

REM 设置默认测试过滤器为 "test_simple"，如果传入参数则使用参数值
set filter=%1
if "%filter%"=="" set filter=test_simple

REM 运行 phpunit 测试
..\vendor\bin\phpunit --filter %filter% MyTest.php
