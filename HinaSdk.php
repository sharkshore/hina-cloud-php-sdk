<?php

define('HINA_SDK_VERSION', '2.0.0');



// 日志函数
function log_message($type, $message, $annotation,$switch)
{

    if(!$switch) return;
    if (strcmp($type, 'echo') == 0) {
        echo "\n===========$annotation=============\n";
        echo $message . PHP_EOL;
        echo "\n==========================================\n";
    } elseif (strcmp($type, 'print') == 0) {
        echo "\n===========$annotation=============\n";
        print_r($message);
        echo "\n==========================================\n";
    } else {
        echo "\n===========type类型设置错误=============\n";
    }
}


// SDK异常基类
class HinaSdkException extends \Exception
{
}

// 在发送的数据格式有误时，SDK会抛出此异常，用户应当捕获并处理。
class HinaSdkIllegalDataException extends HinaSdkException
{
}

// 在因为网络或者不可预知的问题导致数据无法发送时，SDK会抛出此异常，用户应当捕获并处理。
class HinaSdkNetworkException extends HinaSdkException
{
}

// 当且仅当DEBUG模式中，任何网络错误、数据异常等都会抛出此异常，用户可不捕获，用于测试SDK接入正确性
class HinaSdkDebugException extends \Exception
{
}


class HinaSdk
{

    // 执行器
    private $_consumer;
    // 公共属性
    private $_super_properties;
    // 属性值最大长度
    private $_max_value_length = 1024;



    /**
     * 初始化一个 HinaSdk 的实例用于数据发送。
     *
     * @param AbstractConsumer $consumer
     */
    private function __construct($consumer)
    {
        $this->_consumer = $consumer;
        $this->clearSuperProperties();
    }



    /**
     * 初始化FileConsumer，用于本地文件存储。
     * @param mixed $filename
     * @return HinaSdk
     */
    public static function initWithFile($filename)
    {
        $consumer = new FileConsumer($filename);
        return new self($consumer);
    }

    /**
     * 
     * 初始化DebugConsumer，用于测试SDK接入正确性。
     * @param mixed $url
     * @return HinaSdk
     */
    public static function initWithDev($url, $request_timeout = 2000)
    {
        $consumer = new DebugConsumer($url, $request_timeout);
        return new self($consumer);
    }

    /**
     * 
     * 初始化BatchConsumer，用于批量发送接口
     * @param mixed $url
     * @return HinaSdk
     */
    public static function initWithBatch($url, $max_size = 200, $log_switch = false,$request_timeout = 2000, $filename = false)
    {
        $consumer = new BatchConsumer($url, $max_size, $log_switch,$request_timeout, $filename);
        return new self($consumer);
    }

    /**
     * 设置属性值最大长度，超长的属性值会被截断。
     * @param mixed $length
     * @throws \HinaSdkException
     * @return void
     */
    public function setLimitLength($length)
    {
        if ($length == null) {
            throw new HinaSdkException("属性值最大长度不能为null");
        }
        if (!is_numeric($length)) {
            throw new HinaSdkException("属性值最大长度必须为数字");
        }

        if ($length > 5120) {
            $this->_max_value_length = 5120;
            error_log("属性值最大长度不能超过5120，已自动调整为5120");
        } elseif ($length < 1) {
            $this->_max_value_length = 1024;
            error_log("属性值最大长度不能小于1，已自动调整为1024");
        } else {
            $this->_max_value_length = $length;
            error_log("属性值最大长度已设置为：{$length}");
        }
    }


    /**
     * 属性名规则
     * @param mixed $key
     * @throws \HinaSdkIllegalDataException
     * @return void
     */
    private function _assert_key_with_regex($key)
    {
        $name_pattern = "/^((?!^account_id$|^original_id$|^time$|^properties$|^id$|^first_id$|^second_id$|^users$|^events$|^event$|^user_id$|^date$|^datetime$|^user_group|^user_tag)[a-zA-Z_$][a-zA-Z\\d_$]{0,99})$/i";
        if (!preg_match($name_pattern, $key)) {
            throw new HinaSdkIllegalDataException("key must be a valid variable key. [key='{$key}']");
        }
    }

    /**
     * 检查时间戳是否为13位
     */
    private function _assert_13_digit_timestamp($timestamp)
    {
        return preg_match('/^\d{13}$/', $timestamp);
    }


    /**
     * properties 规则
     * @param mixed $properties
     * @throws \HinaSdkIllegalDataException
     * @return void
     */
    private function _assert_properties($properties = array())
    {
        $name_pattern = "/^((?!^account_id$|^original_id$|^properties$|^id$|^first_id$|^second_id$|^users$|^events$|^event$|^user_id$|^date$|^datetime$)[a-zA-Z_$][a-zA-Z\\d_$]{0,99})$/i";

        if (!$properties) {
            return;
        }

        foreach ($properties as $key => $value) {
            if (!is_string($key)) {
                throw new HinaSdkIllegalDataException("property key must be a str. [key=$key]");
            }
            if (strlen($key) > 255) {
                throw new HinaSdkIllegalDataException("the max length of property key is 256. [key=$key]");
            }

            if (!preg_match($name_pattern, $key)) {
                throw new HinaSdkIllegalDataException("property key must be a valid variable name. [key='$key']]");
            }

            // 只支持简单类型或数组或DateTime类
            if (!is_scalar($value) && !is_array($value)) {
                throw new HinaSdkIllegalDataException("property value must be a str/int/float/list. [key='$key']");
            }

        }
    }

    // 属性值超长，截断处理
    private function _truncation_properties($properties = array())
    {
        $max_length = $this->_max_value_length;
        foreach ($properties as $key => $value) {
            if (is_string($value) && strlen($value) > $max_length) {
                $properties[$key] = substr($value, 0, $max_length);
            }
        }
        return $properties;
    }


    /**
     * 发送给 HinaCloud 的数据。
     * @param mixed $data
     * @throws \HinaSdkIllegalDataException
     * @return mixed
     */
    private function _normalize_data($data)
    {
        // 检查 account_id
        if (isset($data['account_id'])) {
            if (strlen($data['account_id']) > 255) {
                throw new HinaSdkIllegalDataException("the max length of [account_id] is 255");
            }
            $data['account_id'] = strval($data['account_id']);
        }

        // 检查 anonymous_id
        if (isset($data['anonymous_id'])) {
            if (strlen($data['anonymous_id']) > 255) {
                throw new HinaSdkIllegalDataException("the max length of [anonymous_id] is 255");
            }
            $data['anonymous_id'] = strval($data['anonymous_id']);
        }

        // 检查time
        // 检查并规范化 time 字段
        if (!is_numeric($data['time'])) {
            throw new HinaSdkIllegalDataException("property [time] must be a numeric value");
        }
        $ts = (int) $data['time'];
        $ts_num = strlen((string) $ts); // 获取时间戳的长度
        // 检查时间戳是否为10位或13位
        if ($ts_num == 10) {
            $ts *= 1000; // 如果是10位（秒级），转换为13位（毫秒级）
        } elseif ($ts_num !== 13) {
            throw new HinaSdkIllegalDataException("property [time] must be a 10 or 13 digit timestamp");
        }
        $data['time'] = $ts;

        // 检查 Event Name
        if (isset($data['event'])) {
            $this->_assert_key_with_regex($data['event']);
        }

        // 检查 properties
        if (isset($data['properties']) && is_array($data['properties'])) {
            $this->_assert_properties($data['properties']);
            $data['properties'] = $this->_truncation_properties($data['properties']);

            // XXX: 解决 PHP 中空 array() 转换成 JSON [] 的问题
            if (count($data['properties']) == 0) {
                $data['properties'] = new \ArrayObject();
            }
        } else {
            throw new HinaSdkIllegalDataException("property must be an array.");
        }

        return $data;
    }


    /**
     * 如果用户传入了 $time 字段，则不使用当前时间。
     *
     * @param array $properties
     * @return int/string
     */
    private function _extract_user_time(&$properties = array())
    {
        if (array_key_exists('$time', $properties)) {
            $time = $properties['$time'];
            unset($properties['$time']);
            return $time;
        }
        return substr((microtime(true) * 1000), 0, 13);
    }



    /**
     * 序列化 JSON
     *
     * @param $data
     * @return string
     */
    private function _json_dumps($data)
    {
        return json_encode($data);
    }

    /**
     * 设置 _track_id 的值，如 properties 包含 $track_id 且符合格式，则使用传入的值。
     *
     * @param array $data 事件的日志。
     * @return int
     */
    private function _set_track_id($data)
    {

        $data['_track_id'] = $this->generateId();
        return $data;
    }

    /**
     * 生成ID的方法，使用UUID和随机数的方式
     *
     * @return string
     */
    private function generateId()
    {
        // 生成UUID
        $uuid = bin2hex(random_bytes(16));
        // 去掉中间的横线
        $cleanUuid = str_replace('-', '', $uuid);
        // 生成4位随机数
        $fourDigitNumber = random_int(1000, 9999);
        // 返回拼接结果
        return $cleanUuid . $fourDigitNumber;
    }



    /**
     * 发送事件
     *
     * @param string $account_id 用户的唯一标识。
     * @param bool $is_login_id 用户标识是否是登录 ID，false 表示该标识是一个匿名 ID。
     * @param string $event_name 事件名称。
     * @param array $properties 事件的属性。
     * @return bool
     */
    public function track($account_id, $is_login_id, $event_name, $properties = array(), $event_time = null)
    {
        try {
            if (!is_string($event_name)) {
                throw new HinaSdkIllegalDataException("event_name 必须填写且为字符串.");
            }
            if (!is_bool($is_login_id)) {
                throw new HinaSdkIllegalDataException("is_login_id 必须是 bool.");
            }
            if (is_null($event_time)) {
                $event_time = substr((microtime(true) * 1000), 0, 13);
                $properties['time'] = $event_time;
            } elseif ($this->_assert_13_digit_timestamp($event_time)) {
                $properties['time'] = $event_time;
            } else {
                throw new HinaSdkIllegalDataException("event_time 必须是13位时间戳.");
            }
            $default_properties = [
                'H_timezone_offset' => '-480',
            ];
            if ($properties) {
                $all_properties = array_merge($default_properties, $this->_super_properties, $properties);
            } else {
                $all_properties = array_merge($default_properties, $this->_super_properties, array());
            }

            if ($is_login_id) {
                $new_account_id = $account_id;
                $new_original_id = null;
            } else {
                $new_account_id = null;
                $new_original_id = $account_id;
            }
            return $this->_track_event('track', $event_name, $new_account_id, $new_original_id, $all_properties);
        } catch (Exception $e) {
            echo '<br>' . $e . '<br>';
        }
    }

    /**
     * 匿名ID和账号ID绑定
     *
     * @param string $account_id 用户注册之后的唯一标识。
     * @param string $original_id 用户注册前的唯一标识。
     * @param array $properties 事件的属性。
     * @return bool
     * @throws HinaSdkIllegalDataException
     */
    public function bindId($account_id, $original_id)
    {
        try {
            $all_properties = array_merge($this->_super_properties, array());
            // 检查 original_id
            if (!$original_id or strlen($original_id) == 0) {
                throw new HinaSdkIllegalDataException("property [original_id] must not be empty");
            }
            if (strlen($original_id) > 255) {
                throw new HinaSdkIllegalDataException("the max length of [original_id] is 255");
            }
            $this->_track_event('track_signup', 'H_SignUp', $account_id, $original_id, $all_properties);
            $this->flush();

        } catch (Exception $e) {
            echo '<br>' . $e . '<br>';
            return false;
        }
    }

    /**
     * 用户属性设置
     *
     * @param string $account_id 用户的唯一标识。
     * @param bool $is_login_id 用户标识是否是登录 ID，false 表示该标识是一个匿名 ID。
     * @param array $profiles
     * @return bool
     */
    public function userSet($account_id, $profiles = array())
    {
        try {
            $this->_track_event('user_set', null, $account_id, null, $profiles);
            $this->flush();
        } catch (Exception $e) {
            echo '<br>' . $e . '<br>';
            return false;
        }
    }

    /**
     * 用户属性设置，如果已存在则不设置。
     *
     * @param string $account_id 用户的唯一标识。
     * @param bool $is_login_id 用户标识是否是登录 ID，false 表示该标识是一个匿名 ID。
     * @param array $profiles
     * @return bool
     */
    public function userSetOnce($account_id, $profiles = array())
    {
        try {
            $this->_track_event('user_setOnce', null, $account_id, null, $profiles);
            $this->flush();
        } catch (Exception $e) {
            echo '<br>' . $e . '<br>';
            return false;
        }
    }

    /**
     * 用户属性，数值类型增加。
     *
     * @param string $account_id 用户的唯一标识。
     * @param bool $is_login_id 用户标识是否是登录 ID，false 表示该标识是一个匿名 ID。
     * @param array $profiles
     * @return bool
     */
    public function userAdd($account_id, $profiles = array())
    {
        try {
            $this->_track_event('user_add', null, $account_id, null, $profiles);
            $this->flush();
        } catch (Exception $e) {
            echo '<br>' . $e . '<br>';
            return false;
        }
    }

    /**
     * 用户属性，集合类型追加元素
     *
     * @param string $account_id 用户的唯一标识。
     * @param bool $is_login_id 用户标识是否是登录 ID，false 表示该标识是一个匿名 ID。
     * @param array $profiles
     * @return bool
     */
    public function userAppend($account_id, $profiles = array())
    {
        try {
            $this->_track_event('user_append', null, $account_id, null, $profiles);
            $this->flush();
        } catch (Exception $e) {
            echo '<br>' . $e . '<br>';
            return false;
        }
    }

    /**
     * 删除用户属性
     *
     * @param string $account_id 用户的唯一标识。
     * @param bool $is_login_id 用户标识是否是登录 ID，false 表示该标识是一个匿名 ID。
     * @param array $profile_keys
     * @return bool
     */
    public function userUnset($account_id, $profile_keys = array())
    {
        try {
            if ($profile_keys != null && array_key_exists(0, $profile_keys)) {
                $new_profile_keys = array();
                foreach ($profile_keys as $key) {
                    $new_profile_keys[$key] = true;
                }
                $profile_keys = $new_profile_keys;
            }
            $this->_track_event('user_unset', null, $account_id, null, $profile_keys);
            $this->flush();
        } catch (Exception $e) {
            echo '<br>' . $e . '<br>';
            return false;
        }
    }


    /**
     * 设置公共属性
     *
     * @param array $super_properties
     */
    public function registerCommonProperties(array $super_properties)
    {
        $this->_super_properties = array_merge($this->_super_properties, $super_properties);
    }

    /**
     * 删除公共属性
     */
    public function clearSuperProperties()
    {
        $this->_super_properties = array(
            'H_lib' => 'php',
            'H_lib_version' => HINA_SDK_VERSION,
            'H_timezone_offset' => '-480'
        );
    }

    /**
     * 对于不立即发送数据的 Consumer，调用此接口应当立即进行已有数据的发送。
     *
     */
    public function flush()
    {
        return $this->_consumer->flush();
    }

    /**
     * 在进程结束或者数据发送完成时，应当调用此接口，以保证所有数据被发送完毕。
     * 如果发生意外，此方法将抛出异常。
     */
    public function close()
    {
        return $this->_consumer->close();
    }

    /**
     * track 事件的实现方法。
     * @param string $update_type
     * @param string $event_name
     * @param string $account_id
     * @param string $original_id
     * @param array $properties
     * @return bool
     * @internal param array $profiles
     */
    public function _track_event($update_type, $event_name, $account_id, $original_id, $properties)
    {
        $event_time = $this->_extract_user_time($properties);

        // 覆盖以下属性
        $properties['H_lib'] = 'php';
        $properties['H_lib_version'] = HINA_SDK_VERSION;

        $data = array(
            'type' => $update_type,
            'properties' => $properties,
            'time' => $event_time,
            'account_id' => $account_id,
            "event" => $event_name,
            "anonymous_id" => $original_id,
            "send_time" => round(microtime(true) * 1000),
        );

        $data = $this->_set_track_id($data);
        $data = $this->_normalize_data($data);
        return $this->_consumer->send($this->_json_dumps($data));
    }




}



abstract class AbstractConsumer
{

    /**
     * 发送一条消息。
     *
     * @param string $msg 发送的消息体
     * @return bool
     */
    public abstract function send($msg);

    /**
     * 立即发送所有未发出的数据。
     *
     * @return bool
     */
    public function flush()
    {
    }

    /**
     * 关闭 Consumer 并释放资源。
     *
     * @return bool
     */
    public function close()
    {
    }
}


/**
 * 文件执行器
 */
class FileConsumer extends AbstractConsumer
{

    private $file_handler;

    public function __construct($filename)
    {
        $this->file_handler = fopen($filename, 'a+');
    }

    public function send($msg)
    {
        if ($this->file_handler === null) {
            return false;
        }
        return fwrite($this->file_handler, $msg . "\n") === false ? false : true;
    }

    public function close()
    {
        if ($this->file_handler === null) {
            return false;
        }
        return fclose($this->file_handler);
    }
}

/**
 * Debug执行器
 */
class DebugConsumer extends AbstractConsumer
{

    private $_debug_url_prefix;
    private $_request_timeout;

    /**
     * DebugConsumer constructor,用于调试模式.
     * 
     * 
     * @param string $url_prefix 服务器的URL地址
     * @param bool $write_data 是否把发送的数据真正写入
     * @param int $request_timeout 请求服务器的超时时间,单位毫秒.
     * @throws HinaSdkDebugException
     */
    public function __construct($url_prefix, $request_timeout = 2000)
    {

        $this->_debug_url_prefix = $url_prefix;

        $this->_request_timeout = $request_timeout;

    }

    /**
     * HTTP发送数据给远程服务器。
     * @param mixed $msg
     * @throws \HinaSdkDebugException
     * @return void
     */
    public function send($msg)
    {
        $buffers = array();
        $buffers[] = $msg;
        $response = $this->_do_request(array(
            "data_list" => $this->_encode_msg_list($buffers),
            "gzip" => 1
        ));
        printf("\n=========================================================================\n");
        if ($response['ret_code'] === 200) {
            printf("valid message: %s\n", $msg);
        } else {
            printf("invalid message: %s\n", $msg);
            printf("ret_code: %d\n", $response['ret_code']);
            printf("ret_content: %s\n", $response['ret_content']);
        }

        if ($response['ret_code'] >= 300) {
            throw new HinaSdkDebugException("Unexpected response from HinaSdk.");
        }
    }

    /**
     * 发送数据包给远程服务器。
     *
     * @param array $data
     * @return array
     * @throws HinaSdkDebugException
     */
    protected function _do_request($data)
    {
        $params = array();
        foreach ($data as $key => $value) {
            $params[] = $key . '=' . urlencode($value);
        }

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $this->_debug_url_prefix);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, $this->_request_timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, $this->_request_timeout);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, implode('&', $params));
        curl_setopt($ch, CURLOPT_USERAGENT, "PHP SDK");

        //judge https
        $pos = strpos($this->_debug_url_prefix, "https");
        if ($pos === 0) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }

        $http_response_header = curl_exec($ch);
        if (!$http_response_header) {
            throw new HinaSdkDebugException(
                "Failed to connect to HinaSdk. [error='" . curl_error($ch) . "']"
            );
        }

        $result = array(
            "ret_content" => $http_response_header,
            "ret_code" => curl_getinfo($ch, CURLINFO_HTTP_CODE)
        );
        curl_close($ch);
        return $result;
    }

    /**
     * 对待发送的数据进行编码
     *
     * @param array $msg_list
     * @return string
     */
    private function _encode_msg_list($msg_list)
    {
        return base64_encode($this->_gzip_string("[" . implode(",", $msg_list) . "]"));
    }

    /**
     * GZIP 压缩一个字符串
     *
     * @param string $data
     * @return string
     */
    private function _gzip_string($data)
    {
        return gzencode($data);
    }
}

/**
 * 批量发送执行器
 */
class BatchConsumer extends AbstractConsumer
{

    private $_buffers;
    private $_max_size;
    private $_url_prefix;
    private $_request_timeout;
    private $file_handler;
    private $log_switch;

    /**
     * @param string $url_prefix 服务器的 URL 地址。
     * @param int $max_size 批量发送的阈值。
     * @param int $request_timeout 请求服务器的超时时间，单位毫秒。
     * @param string $filename 发送数据请求的返回状态及数据落盘记录，必须同时 $response_info 为 ture 时，才会记录。
     */
    public function __construct($url_prefix, $max_size = 200, $log_switch = false, $request_timeout = 2000, $filename = false)
    {
        $this->_buffers = array();
        $this->_max_size = $max_size;
        $this->_url_prefix = $url_prefix;
        $this->_request_timeout = $request_timeout;
        $this->log_switch = $log_switch;
        try {
            if ($filename !== false) {
                $this->file_handler = fopen($filename, 'a+');
            }
        } catch (\Exception $e) {
            echo $e;
        }
    }


    /**
     * HTTP发送数据给远程服务器。
     * @param mixed $msg
     * @return array|bool
     */
    public function send($msg)
    {
        $this->_buffers[] = $msg;
        if (count($this->_buffers) >= $this->_max_size) {
            return $this->flush();
        }
        $result = array(
            "ret_content" => "data into cache buffers",
            "ret_origin_data" => "",
            "ret_code" => 900,
        );
        if ($this->file_handler !== null) {
            // need to write log
            fwrite($this->file_handler, stripslashes(json_encode($result)) . "\n");
        }
        return $result;
    }
    /**
     * 发送HTTP请求的时候，设置当前时间
     * @return void
     */
    protected function set_current_time()
    {
        $newJsonArray = [];
        foreach ($this->_buffers as $msg) {
            $dataArray = json_decode($msg, true);
            if (is_array($dataArray)) {
                $dataArray['time'] = time();
                $newJsonArray[] = json_encode($dataArray);
            }
        }
        return $newJsonArray;
    }

    /**
     * 清空缓存数据
     * @return bool
     */
    public function flush()
    {
        if (empty($this->_buffers)) {
            $ret = false;
        } else {
            $new_buffer = $this->set_current_time();
            $ret = $this->_do_request(array(
                "data_list" => $this->_encode_msg_list($this->_buffers),
                "gzip" => 1
            ), $this->_buffers);
        }
        if ($ret) {
            $this->_buffers = array();
        }
        return $ret;
    }



    /**
     * 发送数据包给远程服务器。
     *
     * @param array $data
     * @return array 响应数据
     */
    protected function _do_request($data, $origin_data)
    {
        $params = array();
        foreach ($data as $key => $value) {
            $params[] = $key . '=' . urlencode($value);
        }

        log_message('print', $origin_data, "发送的原始json", $this->log_switch);
        log_message('print', $data, "发送的data",$this->log_switch);
        log_message('print', $this->_url_prefix, "发送的url",$this->log_switch);
        log_message('print', implode('&', $params), "发送的body",$this->log_switch);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_URL, $this->_url_prefix);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT_MS, $this->_request_timeout);
        curl_setopt($ch, CURLOPT_TIMEOUT_MS, $this->_request_timeout);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, implode('&', $params));
        curl_setopt($ch, CURLOPT_USERAGENT, "PHP SDK");

        //judge https
        $pos = strpos($this->_url_prefix, "https");
        if ($pos === 0) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        }

        $ret = curl_exec($ch);

        $result = array(
            "ret_content" => $ret,
            "ret_origin_data" => $origin_data,
            "ret_code" => curl_getinfo($ch, CURLINFO_HTTP_CODE),
        );
        if ($this->file_handler !== null) {
            // need to write log
            fwrite($this->file_handler, stripslashes(json_encode($result)) . "\n");
        }
        curl_close($ch);
        log_message('print', $ret, "响应数据：ret",$this->log_switch);
        log_message('print', $result, "响应数据：result",$this->log_switch);

        return $result;
    }

    /**
     * 对待发送的数据进行编码
     *
     * @param array $msg_list
     * @return string
     */
    private function _encode_msg_list($msg_list)
    {
        return base64_encode($this->_gzip_string("[" . implode(",", $msg_list) . "]"));
    }

    /**
     * GZIP 压缩一个字符串
     *
     * @param string $data
     * @return string
     */
    private function _gzip_string($data)
    {
        return gzencode($data);
    }

    public function close()
    {
        $closeResult = $this->flush();
        if ($this->file_handler !== null) {
            fclose($this->file_handler);
        }
        return $closeResult;
    }
}
