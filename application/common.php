<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2016 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: 流年 <liu21st@gmail.com>
// +----------------------------------------------------------------------

// 应用公共文件
/**
 * 通用API返回接口
 * @param $error_code int 是否存在错误
 * @param $message string 返回具体信息
 * @param array $data 返回数据
 * @param int $httpCode HTTP状态码
 *
 * @return \think\response\Json
 */
function show($message, $data = [],$error_code = 0, $header = [], $httpCode = 200)
{
    $result = [
        'error_code' => $error_code,
        'msg' => $message,
        'data' => $data
    ];
    //如果data没有值,$data将不显示
    if (empty($data)){
        unset($result['data']);
    }
    if ($header){
        return json($result, $httpCode)->header($header);
    }else{
        return json($result, $httpCode);
    }
}

function curl_post($url = '', $param = '') {
    if (empty($url) || empty($param)) {
        return false;
    }
    $postUrl = $url;
    $curlPost = $param;
    $UserAgent = "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.4.2661.102 Safari/537.36; 360Spider";
    $ch = curl_init();//初始化curl
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // 使用自动跳转
    curl_setopt($ch, CURLOPT_TIMEOUT, 5); // 设置超时限制防止死循环
    curl_setopt($ch, CURLOPT_URL,$postUrl);//抓取指定网页
    curl_setopt($ch, CURLOPT_HEADER, 0);//设置header
    curl_setopt($ch, CURLOPT_USERAGENT, $UserAgent);
    //curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-FORWARDED-FOR:'. generateIP(), 'CLIENT-IP:' . generateIP())); //构造IP
    curl_setopt($ch, CURLOPT_REFERER, $url);//模拟来路
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//要求结果为字符串且输出到屏幕上
    curl_setopt($ch, CURLOPT_POST, 1);//post提交方式
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($curlPost));
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5 );//连接超时，这个数值如果设置太短可能导致数据请求不到就断开了
    $data = curl_exec($ch);//运行curl
    curl_close($ch);
    return $data;
}

function curl_get($url = '') {
    if (empty($url)) {
        return false;
    }
    $szUrl = $url;
    $UserAgent = "Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/50.4.2661.102 Safari/537.36; 360Spider";
    $curl = curl_init();
    curl_setopt($curl, CURLOPT_URL, $szUrl);
    curl_setopt($curl, CURLOPT_HEADER, 0);  //0表示不输出Header，1表示输出
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($curl, CURLOPT_TIMEOUT, 10); // 设置超时限制防止死循环
    curl_setopt($curl, CURLOPT_ENCODING, '');
    curl_setopt($curl, CURLOPT_HTTPHEADER, array('X-FORWARDED-FOR:'. generateIP(), 'CLIENT-IP:' . generateIP())); //构造IP
    curl_setopt($curl, CURLOPT_REFERER, $url);//模拟来路
    curl_setopt($curl, CURLOPT_USERAGENT, $UserAgent);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 10 );//连接超时，这个数值如果设置太短可能导致数据请求不到就断开了
    $data = curl_exec($curl);
    curl_close($curl);
    return $data;
}

function generateIP(){
    $ip2id= round(rand(600000, 2550000) / 10000); //第一种方法，直接生成
    $ip3id= round(rand(600000, 2550000) / 10000);
    $ip4id= round(rand(600000, 2550000) / 10000);
    //下面是第二种方法，在以下数据中随机抽取
    $arr_1 = array("218","218","66","66","218","218","60","60","202","204","66","66","66","59","61","60","222","221","66","59","60","60","66","218","218","62","63","64","66","66","122","211");
    $randarr= mt_rand(0,count($arr_1)-1);
    $ip1id = $arr_1[$randarr];
    return $ip1id.".".$ip2id.".".$ip3id.".".$ip4id;
}

function asyncRequest($url, $method = 'POST', array $param = []) {
    if (empty($url) || empty($param)) {
        return false;
    }
	$postUrl = $url;
    $curlPost = $param;
    $ch = curl_init();//初始化curl
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1); // 使用自动跳转
    curl_setopt ($ch,CURLOPT_NOSIGNAL,true);
    curl_setopt($ch, CURLOPT_TIMEOUT_MS, 8000); // 设置超时限制防止死循环
    curl_setopt($ch, CURLOPT_URL,$postUrl);//抓取指定网页
    curl_setopt($ch, CURLOPT_HEADER, 0);//设置header
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);//要求结果为字符串且输出到屏幕上
    curl_setopt($ch, CURLOPT_POST, 1);//post提交方式
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($curlPost));
    $data = curl_exec($ch);//运行curl
    curl_close($ch);
    return $data;
}

/**
 * 异步curl
 * @param string $url
 * @param string $type
 * @param array $param
 * @return false|int|string
 */
function asyncRequestFS($url, $method = 'GET', array $param = [], $headers = array()) {

    $parse = parse_url($url);

    isset($parse['host']) ||$parse['host'] = '';
    isset($parse['path']) || $parse['path'] = '';
    isset($parse['query']) || $parse['query'] = '';
    isset($parse['port']) || $parse['port'] = '';

    $path = $parse['path'] ? $parse['path'].($parse['query'] ? '?'.$parse['query'] : '') : '/';
    $host = $parse['host'];

    //协议
    if ($parse['scheme'] == 'https') {
        $version = '1.1';
        $port = empty($parse['port']) ? 443 : $parse['port'];
        $host = 'ssl://'.$host;
    } else {
        $version = '1.0';
        $port = empty($parse['port']) ? 80 : $parse['port'];
    }

    //Headers
    $headers[] = "Host: {$parse['host']}";
    $headers[] = 'Connection: Close';
    $headers[] = "User-Agent: fsockopen";
    $headers[] = 'Accept: */*';

    //包体信息
    if ($method == 'POST') {
        if(is_array($param)){
            $param = http_build_query($param);
        }
        $headers[] = "Content-type: application/x-www-form-urlencoded";
        $headers[] = 'Content-Length: '.strlen($param);
        $out = "POST $path HTTP/$version\r\n".join("\r\n", $headers)."\r\n\r\n".$param;
    } else {
        $out = "GET $path HTTP/$version\r\n".join("\r\n", $headers)."\r\n\r\n";
    }

    //发送请求
    $limit = 0;
    $fp = fsockopen($host, $port, $errno, $errstr, 8);

    if (!$fp) {
        exit('Fsockopen failed to establish socket connection: '.$url);
    } else {
        $header = $content = '';
        //集阻塞/非阻塞模式流,$block==true则应用流模式
        stream_set_blocking($fp, true);
        //设置流的超时时间
        stream_set_timeout($fp, 8);
        $result = fwrite($fp, $out);
        usleep(500); // 延迟1毫秒，如果没有这延时，可能在nginx服务器上就无法执行成功
        fclose($fp);
        return $result;
    }
}

function asyncRequest1($url, $type = 'GET', array $param = [])
{
    $url_info = parse_url($url);
    $host = $url_info['host'];
    $path = $url_info['path'];
    if ($type == 'POST'){
        $query = isset($param) ? http_build_query($param) : '';
    }
    $port = 80;
    $errno = 0;
    $errstr = '';
    $timeout = 30; //连接超时时间（S）

    $fp = @fsockopen($host, $port, $errno, $errstr, $timeout);
    //$fp = stream_socket_client("tcp://".$host.":".$port, $errno, $errstr, $timeout);

    if (!$fp) {
        logs('连接失败', 'async_request_logs');
        return '连接失败';
    }

    if ($errno || !$fp) {
        logs($errstr, 'async_request_logs');
        return $errstr;
    }

    stream_set_blocking($fp, 0); //非阻塞
    stream_set_timeout($fp, 10);//响应超时时间（S）
    $out = $type . ' ' . $path . " HTTP/1.1\r\n";
    $out .= "host:" . $host . "\r\n";
    if ($type == 'GET'){
        $out .= "connection:close\r\n\r\n";
    }else{
        $out .= "content-length:" . strlen($query) . "\r\n";
        $out .= "content-type:application/x-www-form-urlencoded\r\n";
        $out .= "connection:close\r\n\r\n";
        $out .= $query;
    }
    $result = @fputs($fp, $out);
    usleep(1000); // 延迟1毫秒，如果没有这延时，可能在nginx服务器上就无法执行成功
    @fclose($fp);
    return $result;
}

function user_agent(){
    $rand_number = mt_rand(300,9999);
    $redis = new \app\spider\controller\RedisController();
    $agent = $redis::exists('proxy:agent');
    if (!$agent){
        $data = [
            'Mozilla/5.0 (Windows NT 6.1; WOW64) Gecko/537.36 (KHTML, like Gecko) Chrome/63.0.'.$rand_number.'.132 Safari/537.36',
            'Mozilla/5.0 (Windows NT 6.1; Win64; x64; rv:70.0) AppleWebKit Gecko/'.$rand_number.' Firefox/70.0',
            'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/39.0.'.$rand_number.'.95 Safari/537.36 OPR/26.0.1656.60',
            'Mozilla/5.0 (Windows NT 6.1; WOW64; rv:34.0) Gecko/'.$rand_number.' Firefox/34.0',
            'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/534.57.2 (KHTML, like Gecko) Version/5.1.'.$rand_number.' Safari/534.57.2',
            'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/39.0.'.$rand_number.'.71 Safari/537.36',
        ];
        $data2 = [
            'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/39.0.2171.71 Safari/537.36',
            'Mozilla/5.0 (Windows NT 6.1; WOW64) AppleWebKit/534.57.2 (KHTML, like Gecko) Version/5.1.7 Safari/534.57.2',
            'Mozilla/5.0 (Windows NT 5.1; U; en; rv:1.8.1) Gecko/20061208 Firefox/2.0.0 Opera 9.50',
            'Mozilla/5.0 (iPhone; U; CPU iPhone OS 4_3_3 like Mac OS X; en-us) AppleWebKit/533.17.9 (KHTML, like Gecko) Version/5.0.2 Mobile/8J2 Safari/6533.18.5',
            'Mozilla/5.0 (Windows NT 6.0) AppleWebKit/536.5 (KHTML, like Gecko) Chrome/19.0.1084.36 Safari/536.5',
            'Mozilla/5.0 (Windows NT 6.2) AppleWebKit/536.3 (KHTML, like Gecko) Chrome/19.0.1061.0 Safari/536.3',
        ];
        $rand = mt_rand(0,count($data2)-1);
        $agent = $data[$rand];
        $redis::set('proxy:agent', $agent);
    }
    return $agent;
}

/**
 * 把 3 min ago 这种格式的时间转换成时间戳
 * @param $string_time
 */
function timeConvert($string_time){
    $str_time = explode(' ', $string_time);
    if (count($str_time) == 3){
        switch ($str_time[1])
        {
            case 'sec':
                $pass_time = time() - (int)$str_time[0];
                break;
            case 'minute':
            case 'min':
                $pass_time = time() - ((int)$str_time[0] * 60);
                break;
            case 'hour':
            case 'h':
                /*$current_hour = date('H');
                $pass_time_hour = $current_hour - $str_time[0];
                //如果出现负数
                if ($pass_time_hour < 0){
                    $pass_time_hour = 24 - abs($pass_time_hour);
                    //如果时间为1号，月份也需要-1
                    if (date('d') == 1){
                        //计算当前月份上一月的最后一天
                        $time_s =  strtotime(date('Y-m-01'));
                        $day = date('d',strtotime('-1 day',$time_s));

                        $pass_time = strtotime(date('Y-m-'.$day .' ' . $pass_time_hour . ':0'));
                    }else{
                        $pass_time = strtotime(date('Y-m-'.(date('d') - 1) .' ' . $pass_time_hour . ':0'));
                    }

                }else{
                    $pass_time = strtotime(date('Y-m-d ' . $pass_time_hour . ':0'));
                }*/
                $pass_time = time() - ((int)$str_time[0] * 3600);
                break;
            default:
                $pass_time = '';
        }
        return $pass_time;
    }
    return '';
}

//获取当天最后一秒的时间撮
function getTodayEndTimestamp(){
    return strtotime(date('Y-m-d' . ' 23:59:59')) - time();
}

//获取公网ip
function publicIP(){
    $url = 'https://www.uc.cn/ip';
    $ip = explode(':',file_get_contents($url));
    return $ip[1];
}

/**
 * 打印日志
 * @param $title 日志标题
 * @param $type 日志类型
 * @param false $time 是否显示时间
 * @param false $write 是否实时写入
 */
function printLog($title, $type = 'notice', $time = true, $write = false){
    //根据配置判断是否需要打印输出日志
    if (\think\facade\Config::get('log.print_log')){
        if ($write){
            if ($time){
                \think\facade\Log::write('['.date('Y-m-d H:i:s').'] '. $title, $type);
            }else{
                \think\facade\Log::write($title, $type);
            }
        }else{
            if ($time){
                \think\facade\Log::$type('['.date('Y-m-d H:i:s').'] '. $title);
            }else{
                \think\facade\Log::$type($title);
            }
        }
    }
}