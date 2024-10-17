<?php
require_once("../HinaSdk.php");
date_default_timezone_set("Asia/Shanghai");


$SERVER_URL="https://test-hicloud.hinadt.com/gateway/hina-cloud-engine/gather?project=phpSDKTest&token=XmAD6Saq";
$ha = new HinaSdk(new BatchConsumer($SERVER_URL, 5));

// 注册全局属性
$super_properties = array(
    "super_property1" => "value1",
    "super_property2" => "value2",
    "super_property3" => "value3"
);
$ha->registerSuperProperties($super_properties);

// 设置属性值最大长度
$ha->set_max_value_length(6);

// 执行 test_event 方法的逻辑
$uuid = bin2hex(random_bytes(16));
$properties = array(
    "e_name" => "zhaoshangaaa",
    "e_type" => "onlineaaa",
    "e_money" => 25888
);

echo $uuid. "\n";

// 这里的 true 可以根据需要调整
$ha->track($uuid, true, "tuze_test_event", $properties);
$ha->flush();