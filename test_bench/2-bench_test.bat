@echo off
ab -n 1000 -c 10 http://localhost:18000/MultiTest.php
pause
