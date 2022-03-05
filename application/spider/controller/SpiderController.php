<?php

namespace app\spider\controller;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Exception\RequestException;
use QL\QueryList;
use think\Exception;
use think\facade\Request;
use think\facade\Validate;

class SpiderController
{
    protected $redis_key = 'spider:message:';

    public function test()
    {
        $account = [
            [
                'http://webapi.http.zhimacangku.com/getip?num=1&type=2&pro=&city=0&yys=0&port=1&pack=67654&ts=0&ys=0&cs=0&lb=1&sb=0&pb=4&mr=1&regions=',
                '82540',
                'bf0fff1f9aa718e7a83b60cf0cde386e',
                '18142618863',
                'chenjuan5200'
            ],
            [
                'http://webapi.http.zhimacangku.com/getip?num=1&type=2&pro=&city=0&yys=0&port=1&pack=85831&ts=0&ys=0&cs=0&lb=1&sb=0&pb=4&mr=1&regions=',
                '104770',
                'c13e0d43d2a02e9cc8acd4444dc7142f',
                '17873026796',
                'chenjuan5200'
            ],
            [
                'http://webapi.http.zhimacangku.com/getip?num=1&type=2&pro=&city=0&yys=0&port=1&pack=71711&ts=0&ys=0&cs=0&lb=1&sb=0&pb=4&mr=1&regions=',//芝麻活动每天20条   15263819409 15263819410
                '85543',
                '97f62c9d43993aa198b44e2a629b7518',
                '18142618803',
                'chenjuan5200'
            ],
            [
                'http://webapi.http.zhimacangku.com/getip?num=1&type=2&pro=&city=0&yys=0&port=1&time=1&ts=0&ys=0&cs=0&lb=1&sb=0&pb=4&mr=1&regions=',
                '82540',
                'bf0fff1f9aa718e7a83b60cf0cde386e',
                '18142618863',
                'chenjuan5200'
            ]
        ];
        (new RedisController())::set('proxy:account', json_encode($account), -1);
        return 'test';
    }

    public function index()
    {
        $redis = new RedisController();
        $redis::inc('curl:count_request');
        if (!Request::isPost()) {
            return show('error');
        }
        $data = input('post.');
        printLog(Request::scheme(), 'notice');
        $validate = Validate::make([
            'from' => 'require|url',
            'phone_num' => 'require|max:15|number',
            'bh' => 'max:3|number',
            'phone_id' => 'max:32|alphaNum',
            'site' => 'require|max:20|alphaNum',
            'mode' => 'in:yi,guzzle'
        ]);
        if (!$validate->check($data)) {
            printLog($validate->getError(), 'error');
            return show($validate->getError(), '', 5000);
        }
        $data['time'] = time();
        //加入队列，判断当前是否运行状态，如果不是运行状态，启动spider主程序
        //例外情况，如果主站请求频繁，比如连续十次刷新，但是队列不能连续写入十次，约定，十秒钟内相同的请求，仅一条写入队列。这个需要在请求端控制
        //号码请求成功后，数据写入redis等待10秒，如果10秒内有相同的请求，直接返回redis缓存数据
        //获取到最新数据，对旧数据进行比对（旧数据的第一条for循环，看新数据是否存在）如果存在新数据，则替换掉现有的缓存，并回调请求在线服务器的入库接口。
        //加入队列
        $result = $redis::lpush('spider_queue', json_encode($data));
        if (!$result) {
            printLog('队列写入失败', 'error');
            return show('队列写入失败', '', 4000);
        } else {
            printLog('写入队列成功：' . $data['phone_num'], 'notice');
            //运行spider主程序
            if (!$redis::exists('spider:running')) {
                printLog('spider主进程空闲，启动主程序', 'notice');
                $this->spider();
                return show('队列写入成功，成功开启spider主程序');
            } else {
                printLog('spider主进程锁定，等待队列统一消费', 'notice');
            }
            return show('队列写入成功');
        }
    }

    //执行spider主程序
    public function spider()
    {
        printLog('spider主程序启动...', 'notice');
        libxml_use_internal_errors(true);   //屏蔽报错
        $redis = new RedisController();
        do {
            $data = $redis::lpop('spider_queue', 'last');
            $data_yi = $data;
            $data = json_decode($data, true);

            if ($data) {
                //Log::write('消费队列' . json_encode($data), 'write');
                $key_hz = 'spider:site:' . $data['site'];

                //启动后，对每个网站请求需要有频率限制，如果存在该值，重新插入到队尾等待处理
                if ($redis::exists($key_hz) && $data['site'] != 'Storytrain') {
                    printLog($data['site'] . '站点等待3秒释放');
                    $data['time'] = time();
                    $data['new'] = 'later';
                    $redis::lpush('spider_queue', json_encode($data));
                    sleep(1);
                    continue;
                }

                /**
                 * 两种采集方式
                 * 1.php curl采集，直接按流程处理即可
                 * 2.判断如果是Storytrain，且mode=yi的话，就需要通过exe程序采集
                 *   重新开始一个exe采集专用队列 spider_queue_yi,当易采集成功后
                 *   会把源码放放spider:html:里面，并且会再次插入队列，通过spider
                 *   再次进行消费。
                 */
                $use_key = 'spider:use:' . $data['phone_num'];
                if ($data['mode'] == 'yi' && !$redis::exists($use_key)){
                    //加入队列
                    $yi_queue = $redis::lpush('spider_queue_yi', $data_yi);
                    if (!$yi_queue){
                        printLog($data['site'] . '加入exe采集队列失败', 'notice');
                    }else{
                        //为了防止exe重复采集同一个号码，降低采集频率，队列短时间内只允许同时存在一个号码
                        $redis::set($use_key, 1,60);
                        printLog($data['site'] . '加入exe采集队列成功', 'notice');
                    }
                    continue;
                }

                $redis::set('spider:running', 1, 60);
                $function = 'curl' . $data['site'];
                $result = $this->$function($data);
                //删除exe队列请求限制
                $redis::del($use_key);
                printLog($data['site'] . '冻洁3秒', 'notice');
                if ($result == 'null') {
                    //标记该网站请求的时间，做频率限制
                    $redis::setnx($key_hz, time(), 3);
                    echo '采集成功，并未发现新内容';
                }
                if ($result > 0) {
                    //标记该网站请求的时间，做频率限制
                    $redis::setnx($key_hz, time(), 3);
                    echo '采集成功' . $result . '条，新数据回调成功';
                }
                if (!$result) {
                    //如果单个请求只第一次失败，
                    echo '请求失败';
                    //如果失败，向服务器回调失败的号码，清除限制，继续请求
                    try {
                        $result = asyncRequest($data['from'] . 'callbackCurlFailNumber', 'POST', ['phone_num'=>$data['phone_num']]);
						if($result){
							trace('callbackCurlFailNumber错误回调请求失败', 'error');
						}else{
							trace('['.date('Y-m-d H:i:s').'] curl请求失败，已发送回调通知', 'notice');
						}
                    }catch (\Exception $e){
                        trace('callbackCurlFailNumber错误回调请求失败', 'error');
                        trace($e->getMessage(), 'error');
                    }
                }
            } else {
                $redis::del('spider:running');
                printLog('spider主进程释放', 'notice');
            }
        } while ($data);
    }

    //获取新数据处理
    protected function newData($spider_data, $params)
    {
        printLog('采集数据分析', 'notice');
        $spider_data_count = count($spider_data);
        $redis = new RedisController();
        if ($spider_data_count < 1) {
            //curl请求失败情况，1.记录该网站总错误数量，缓存过期时间为一天 2.如果第一次错误，是否考虑需要重新请求一次
            $fail = $redis::incrEx('spider:fail:' . $params['site'], 1, 86400);
            if ($fail > 50) {
                //todo 通知处理
            }
            printLog('采集数据不全', 'notice');
            return false;
        } else {
            $redis::inc('spider:success:' . $params['site']);
        }
        $key = $this->redis_key . $params['phone_num'];
        $callback_url = 'http://imagecdn1566.iyunzhi.top/index.php/index/msg_queue/receiveNumber';
        //判断是否是最新数据
        //获取缓存最新一条跟当前循环对比
        $cache = json_decode($redis::get($key), true);
        if (!$cache) {
            //回调通知
            try {
                $result = asyncRequest($callback_url, 'POST', $spider_data);
                if ($result){
                    $redis::set($key, json_encode($spider_data), -1);
                }
            }catch (\Exception $e){
                trace("缓存不存在，直接回调出错", 'error');
                trace($e->getMessage(), 'error');
            }
            printLog($params['phone_num'] . '不存在缓存，直接回调', 'notice');
            return count($spider_data);
        } else {
            printLog('新数据比对', 'notice');
            $new_data = [];
            $new_k = 0;
            foreach ($spider_data as $value) {
                //内容比对
                if ($value['smsContent'] == $cache[0]['smsContent']) {
                    break;
                } else {
                    //有新的内容，把新内容缓存到redis
                    //内容过滤
                    //$value['smsContent'] = $this->filterKey($value['smsContent']);
                    //确定项目的名称来源
                    //$value['url'] = $this->smsNumber($value['smsContent']);
                    $new_data[$new_k] = $value;
                    $new_k++;
                }
            }
			//trace($spider_data, 'notice');
			//trace($new_data, 'notice');
            $new_data_count = count($new_data);
            if ($new_data_count > 0) {
                //回调通知
                try {
                    $result = asyncRequest($callback_url, 'POST', $new_data);
                    if ($result) {
                        $redis::set($key, json_encode($new_data), -1);
						trace($params['phone_num'] . '采集成功' . $new_data_count . '条，新数据回调成功', 'notice');
						return $new_data_count;
                    }
                }catch (\Exception $e){
                    trace("存在新消息回调消息失败", 'error');
                    trace($e->getMessage(), 'error');
                }
            } else {
                printLog($params['phone_num'] . '采集成功，并未发现新内容', 'notice');
                return 'null';
            }
        }
    }

    //实际拓展方法
    protected function curlReceivesms($params)
    {
        printLog('开始请求' . $params['site'] . '数据', 'notice');
        $url = 'https://www.receivesms.org/us-number/' . $params['phone_id'] . '/';
        $html = $this->guzzle($url);
        if (!$html) {
            return false;
        }
        $smsContent = QueryList::html($html)->find('.msg-padding')->texts();
        $smsNumber = QueryList::html($html)->find('.font-weight-bold')->texts();
        $smsDate = QueryList::html($html)->find('.btn-time')->texts();

        $data = [];
        //拼接数据
        try {
            for ($i = 0; $i < 20; $i++) {
                $data[$i]['smsContent'] = $smsContent[$i];
                $data[$i]['smsNumber'] = $smsNumber[$i];
                $data[$i]['smsDate'] = timeConvert($smsDate[$i]);
                $data[$i]['PhoNum'] = $params['phone_num'];
            }
        }catch (\Exception $e){
            //curl请求成功，但是获取采集内容失败，这里需要记录，以便后面进行更换
            $redis = new RedisController();
            //每个网站当天错误数量
            $redis::incrEx('spider:error:' . $params['site'], 1, getTodayEndTimestamp());
            trace('获取采集内容失败，请检查网站是否限制采集', 'error');
            return false;
        }

        //数据去重处理
        return $this->newData($data, $params);
    }

    /**
     * https://www.storytrain.info/
     * https://www.storytrain.info/content/7529908826
     * @param $params
     * @return false|int|string
     */
    protected function curlStorytrain($params)
    {
        printLog('开始请求' . $params['site'] . '数据', 'notice');
        $key = "spider:html:" . $params['phone_num'];
        $redis = new RedisController();
        $html = $redis::get($key);
        if (!$html) {
            trace('exe源码不存在', 'notice');
            return false;
        }
        $table = QueryList::html($html)->find('table');
        $tableRows = $table->find('tr:gt(0)')->map(function($row){
            return $row->find('td')->texts()->all();
        });
        $html_array = $tableRows->all();
        $html_array_count = count($html_array);
        if ($html_array_count < 9) {
            trace('采集数据有误', 'notice');
            return false;
        }
        $data = [];
        //拼接数据
        try {
            for ($i = 0; $i < $html_array_count; $i++) {
                $data[$i]['smsContent'] = $html_array[$i][2];
                $data[$i]['smsNumber'] = $html_array[$i][1];
                $data[$i]['smsDate'] = strtotime($html_array[$i][3]);
                $data[$i]['PhoNum'] = $params['phone_num'];
            }
        }catch (\Exception $e){
            trace('拼接出现异常，无需处理', 'error');
            trace($e->getMessage(), 'error');
            //trace($html_array, 'error');
            //trace($data, 'error');
            //return false;
        }

        //数据去重处理
        return $this->newData($data, $params);
    }

    //guzzle封装
    protected function guzzle($url, $method = 'GET', $params = [])
    {
        $redis = new RedisController();
        $proxy = $this->getProxy();
        try {
            $guzzle = new Client();
            $result_html = $guzzle->request($method, $url, [
                'timeout' => 10,
                'heardes' => ['User-Agent' => user_agent()],
                'http_errors' => false,
                'proxy' => $proxy,
                'form_params' => $params
            ]);
            $html = $result_html->getBody();
            if ($proxy) {
                //删除是为了获取连续错误数量
                $redis::del('proxy:error');
                $redis::del('proxy:lock');
                $redis::inc('proxy:success');
            }
            $redis::incrEx('proxy:success_today', 1, getTodayEndTimestamp());
        } catch (RequestException $e) {
            //代理请求错误标记，便于统计更换ip
            $redis::inc('proxy:error');
            $redis::incrEx('proxy:error_today', 1, getTodayEndTimestamp());
            //判断如果出现这个错误，并且计算一下该代理的生效时间，如果差不多20多分钟，就直接重新获取
            /*if ($redis::get('proxy:get_time') > 30*60 && $redis::get('proxy:error') > 2){
                $redis::del('proxy:proxy');
                //TODO 后期需要观察一下IP使用情况后再细处理
            }*/
            trace('['.date('Y-m-d H:i:s').'] curl请求错误RequestException, proxy:error +1', 'error');
            return false;
        } catch (ConnectException $c) {
            //代理请求错误标记，便于统计更换ip
            $redis::inc('proxy:error');
            $redis::incrEx('proxy:error_today', 1, getTodayEndTimestamp());
            trace('['.date('Y-m-d H:i:s').'] curl请求错误ConnectException proxy:error +1', 'error');
            return false;
        }
        printLog('request请求成功', 'notice');
        return $html;
    }

    public function updateProxy(){
        (new RedisController())::set('proxy:error', 10);
        $result = $this->getProxy();
        if ($result){
            return 1;
        }else{
            return '';
        }
    }
    //请求proxy前置
    public function getProxy()
    {
        $key_proxy = 'proxy:proxy';
        $key_error = 'proxy:error';
        $redis = new RedisController();

        if (!$redis::exists($key_proxy)) {
            printLog('代理不存在，开始获取新的代理', 'notice');
            $proxy = $this->getProxyIP();
        } else {
            //如果存在的话，需要判断他的错误次数决定是否需要重新请求
            if ($redis::get($key_error) > 2) {
                printLog('代理存在，但错误次数过多，重新获取代理', 'notice');
                $redis::del('proxy:lock');
                $proxy = $this->getProxyIP();
            } else {
                return $redis::get($key_proxy);
            }
        }

        if ($proxy) {
            //开锁，为了防止程序出错，每获取一个ip,强制锁10分钟，等到缓存过期
            //$redis::del($key_lock);
            return $proxy;
        } else {
            //返回空，系统默认不使用代理
            printLog('代理IP获取失败，使用当前网络', 'notice');
            return '';
        }
    }

    //请求获取
    protected function getProxyIP()
    {
        printLog('开始获取代理ip', 'notice');
        $redis = new RedisController();
        $key_current = 'proxy:current_number';
        $key_today_get = 'proxy:today_get_proxy';
        $key_proxy = 'proxy:proxy';
        $key_lock = 'proxy:lock';
        $key_error = 'proxy:error';
        $key_success = 'proxy:success';
        $key_agent = 'proxy:agent';
        $key_get_time = 'proxy:get_time';
        $key_account = 'proxy:account';
        //如果proxy不存在，需要请求
        if ($redis::exists($key_lock)) {
            printLog('proxy上锁状态', 'notice');
            return false;
        }
        //防止同一时间多个用户请求，添加锁, 为了防止程序出错，每获取一个ip,强制锁10分钟，等到缓存过期
        $redis::set($key_lock, 1, 10 * 60);
        $account = json_decode($redis::get($key_account), true);
        //获取当前调用代理ip的账号
        //$proxy_current_number,设置过期时间为当天最后一秒
        if (!$redis::exists($key_current)) {
            $init_value = 0;
            $expire = getTodayEndTimestamp();
            $redis::set($key_current, $init_value, $expire);
            $redis::set($key_today_get, 0, $expire);
            $proxy_current_number = $init_value;
        } else {
            $proxy_current_number = $redis::get($key_current);
        }
        printLog('第【' . $proxy_current_number . '】个账号', 'notice');
        try {
            $proxy_data = json_decode(file_get_contents($account[$proxy_current_number][0]), true);
            printLog(json_encode($proxy_data), 'notice');
        }catch (\Exception $e){
            trace($e->getMessage(), 'error');
            trace('curl芝麻代理失败', 'error');
            return false;
        }

        switch ($proxy_data['code']) {
            case 0:
                $proxy_ip = $proxy_data['data'][0]['ip'];
                $proxy_port = $proxy_data['data'][0]['port'];
                //设置今天总共获取数量
                $redis::inc($key_today_get);
                //清除当前错误数量
                $redis::del($key_error);
                $redis::del($key_success);
                $redis::del($key_agent);
                $redis::set($key_get_time, time()); //获取时间
                $proxy = $proxy_ip . ':' . $proxy_port;
                $redis::set($key_proxy, $proxy, 3600);
                printLog('新代理获取成功：' . $proxy, 'notice');
				if($redis::get('proxy:today_get_proxy') > 55){
					curl_get('http://notice.bilulanlv.com/?key=qywsxxl&title=' . '代理更换成功：' . $redis::get($key_current) . '/' . $redis::get($key_today_get));
				}
                return $proxy;
            case 115:
			case 121:
            case 116:
                //116 今日套餐已用完, 1.增加当前使用账号下标，2.领取当天免费额度 3.加入白名单
                //115 您的该套餐已经过期了
                if ($proxy_data['code'] == 116) {
                    if ($redis::inc($key_current) + 1 > count($account)){
                        $redis::dec($key_current);
                    }
                    $redis::del($key_lock);
                    printLog('套餐已经用完，获取下一个账号,开始领取免费套餐', 'notice');
                    if ($redis::get($key_current > 2)) {
                        //提醒免费用完了
                        //todo 暂时不使用收费账户，
                        return false;
                    }
                }
                if ($proxy_data['code'] == 115) {
                    printLog('您的该套餐已经过期了，开始领取当天免费套餐', 'notice');
                }
                $result = $this->zhimaGetFree($account[$redis::get($key_current)]);
                if ($result) {
                    printLog('免费套餐领取成功，重新获取代理ip', 'notice');
                    $redis::del($key_lock);
                    $this->zhimaWhiteIP($account[$redis::get($key_current)]);
                    self::getProxyIP();
                } else {
                    printLog('免费套餐领取失败', 'notice');
                    return false;
                }
                break;
            case 117:
			case 401:
            case 113:
                //请添加白名单22
                printLog('需要添加白名单', 'notice');
                $result = $this->zhimaWhiteIP($account[$redis::get($key_current)]);
                if ($result) {
                    printLog('白名单添加成功，重新获取代理Ip', 'notice');
                    $redis::del($key_lock);
                    self::getProxyIP();
                } else {
                    printLog('白名单添加失败', 'notice');
                    return false;
                }
                break;
            case 111:
                //请2秒后再试
                printLog('速度过快，等待2秒继续', 'notice');
                sleep(2);
                $redis::del($key_lock);
                self::getProxyIP();
                break;
            default:
				trace($proxy_data, 'error');
				curl_get('http://notice.bilulanlv.com/?key=qywsxxl&title=代理获取未知异常，请检查');
                return false;
        }
    }

    //免费领取芝麻活动
    protected function zhimaGetFree($account)
    {
        $url = 'https://wapi.http.linkudp.com/index/users/login_do';
        try {
            $guzzle = new Client(['cookies' => true]);
            $guzzle->request('POST', $url, [
                'form_params' => [
                    'phone' => $account[3],
                    'password' => $account[4],
                    'remember' => 0
                ],
                'timeout' => 10,
                'heardes' => ['User-Agent' => user_agent()],
                'http_errors' => false,
            ]);
            $my = $guzzle->request('POST', 'https://wapi.http.linkudp.com/index/users/get_day_free_pack', [
                'form_params' => [
                    'geetest_challenge' => '',
                    'geetest_validate' => '',
                    'geetest_seccode' => ''
                ],
                'timeout' => 10,
                'heardes' => ['User-Agent' => user_agent()],
                'http_errors' => false,
            ]);
            $result = json_decode($my->getBody(), true);
        }catch (\Exception $e){
            trace('免费领取芝麻活动请求出错', 'error');
            return false;
        }

        trace('['.date('Y-m-d H:i:s').'] ' . json_encode($result), 'notice');
        if ($result['code'] == -1) {
            printLog('今日已经领取过免费ip', 'notice');
            return false;
        }
        if ($result['code'] == 1) {
            return true;
        }
        return false;
    }

    //设置白名单
    protected function zhimaWhiteIP($account)
    {
        $url = 'https://wapi.http.linkudp.com/index/index/save_white?neek=' . $account[1] . '&appkey=' . $account[2] . '&white=' . publicIP();
        try {
            $result = json_decode(curl_get($url), true);
            if ($result['code'] == 0 || $result['code'] == 115) {
                return true;
            } else {
                return false;
            }
        }catch (\Exception $e){
            trace('芝麻设置白名单请求出错', 'error');
            return false;
        }
    }
	
}