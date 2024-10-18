#!/bin/bash

# 总共请求100次，并发数为10
ab -n 1000 -c 10 http://localhost:18000/MultiTest.php