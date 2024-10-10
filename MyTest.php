<?php
require_once("HinaSdk.php");
date_default_timezone_set("Asia/Shanghai");
use PHPUnit\Framework\TestCase;

class MyTest extends TestCase {


    private $ha;
    private $consumer;




    // 初始化
    protected function setUp(): void {
        echo "\n =====设置初始属性======\n";
        $SERVER_URL="https://test-hicloud.hinadt.com/gateway/hina-cloud-engine/ha?project=yituiAll&token=yt888";
        $this->consumer=new BatchConsumer($SERVER_URL,5);
        $this->ha=new HinaSdk($this->consumer);
        // 注册全局属性
        $super_properties=array(
            "super_property1"=>"value1",
            "super_property2"=>"value2",
            "super_property3"=>"value3"
        );
        $this->ha->registerSuperProperties($super_properties);

    }

    // 结束
    protected function tearDown(): void {
        echo "\n =====设置结束属性======\n";
    }


    public function test_simple(){
        echo "\n =====开始测试======\n";
        $this->assertTrue(true);
    }

    // 测试事件
    public function test_event(){

        $uuid = bin2hex(random_bytes(16));
        $properties=array(
            "cat_name"=>"zhaoshangaaa",
            "pay_type"=>"onlineaaa",
            "pay_money"=>25888
        );
        // 登录的true改成false，可以测试未登录的事件
        $this->ha->track($uuid,true, "test_event_nologin", $properties);
        $this->ha->flush();
        $this->assertTrue(true);
    }


    // 测试绑定ID
    public function test_bind_id(){
        $uuid = bin2hex(random_bytes(16));
        $uuid2 = "1234567890";
        $this->ha->bindId($uuid, $uuid2);

    }

    // 用户属性设置
    public function test_user_set(){
        $uuid = bin2hex(random_bytes(16));
        $properties=array(
            "cat_name"=>"zhaoshangaaa",
            "pay_type"=>"onlineaaa",
            "pay_money"=>25888
        );
        $this->ha->userSet($uuid,true,  $properties);
        $this->ha->flush();
        $this->assertTrue(true);
    }





}