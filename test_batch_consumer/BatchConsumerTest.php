<?php
require_once("../HinaSdk.php");
date_default_timezone_set("Asia/Shanghai");
use PHPUnit\Framework\TestCase;

class BatchConsumerTest extends TestCase {


    private $ha;
    private $consumer;




    // 初始化
    protected function setUp(): void {
        echo "\n 设置初始属性\n";
        // $SERVER_URL="https://test-hicloud.hinadt.com/gateway/hina-cloud-engine/gather?project=new_category&token=ui5scybH";
        $SERVER_URL="https://test-hicloud.hinadt.com/gateway/hina-cloud-engine/gather?project=phpSDKTest&token=XmAD6Saq";
        $this->consumer=new BatchConsumer($SERVER_URL,5);
        $this->ha=new HinaSdk($this->consumer);
        // 注册全局属性
        $super_properties=array(
            "super_property1"=>"value1",
            "super_property2"=>"value2",
            "super_property3"=>"value3"
        );
        $this->ha->registerSuperProperties($super_properties);
        // 设置属性值最大长度
        $this->ha->set_max_value_length(6);

    }

    // 结束
    protected function tearDown(): void {
        echo "\n 设置结束属性\n";
    }


    public function test_simple(){
        echo "\n =====开始测试======\n";
        $this->assertTrue(true);
    }

    // 测试登录事件
    public function test_login_event(){

        $uuid = bin2hex(random_bytes(16));
        $properties=array(
            "e_name"=>"zhaoshangaaa",
            "e_type"=>"onlineaaa",
            "e_money"=>25888
        );
        // 登录的true改成false，可以测试登录的事件
        $this->ha->track($uuid,true, "tuze_test_event", $properties);
        $this->ha->flush();
        $this->assertTrue(true);
    }

    // 测试未登录事件
    public function test_nologin_event(){

        $uuid = bin2hex(random_bytes(16));
        $properties=array(
            "e_name"=>"zhaoshangaaa",
            "e_type"=>"onlineaaa",
            "e_money"=>25888,
            "H_timezone_offset"=>"720",
            "H_lib"=>"Java"
        );
        // 登录的true改成false，可以测试登录的事件
        $this->ha->track($uuid,false, "tuze_test_event", $properties);
        $this->ha->flush();
        $this->assertTrue(true);
    }

    public function test_object_event(){

        $uuid = bin2hex(random_bytes(16));
        $properties=array(
            "e_name"=>"zhaoshangaaa",
            "e_type"=>"onlineaaa",
            "e_object"=>[
                "name"=>"zhaoshang",
                "age"=>"25",
                "sex"=>"male",
                "address"=>[
                    "province"=>"Beijing",
                    "city"=>"Beijing"
                ],
                "hobby"=>[
                    "reading",
                    "swimming",
                    "running"
                ]
            ],
            "e_money"=>25888
        );
        // 登录的true改成false，可以测试登录的事件
        $this->ha->track($uuid,true, "tuze_test_event", $properties);
        $this->ha->flush();
        $this->assertTrue(true);
    }

    // 测试绑定ID
    public function test_bind_id(){
        $uuid = bin2hex(random_bytes(16));
        $uuid2 = "1234567890";
        $this->ha->bindId($uuid, $uuid2);

    }

    // 测试用户属性设置
    public function test_user_set(){
        $uuid = bin2hex(random_bytes(16));
        $properties=array(
            "u_name"=>"zhaoshangaaa",
            "u_type"=>"onlineaaa",
            "u_money"=>25888
        );
        $this->ha->userSet($uuid,true,  $properties);
        $this->ha->flush();
        $this->assertTrue(true);
    }







    // 测试数组合并
    public function test_array(){
        $arr1=[
            "name"=>"11111"
        ];
        $arr2=[
            "name"=>"22222"
        ];
        $arr3=[
            "name"=>"33333"
        ];

        $marr=array_merge($arr1,$arr2,$arr3);
        print_r($marr);
    }



}