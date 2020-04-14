<?php
/**
 * Created by PhpStorm.
 * User: user
 * Date: 18-7-11
 * Time: 上午10:53
 */

namespace common\unit;

class ToolFun
{
    public static function getDayStartEndTimeByLately($vat) {
        $start_time = date('Y-m-d');
        $end_time   = date('Y-m-d', strtotime('-1 day'));

        switch ($vat) {
            case '3day':
                $start_time = date("Y-m-d", strtotime('-3 day'));
                break;
            case '7day':
                $start_time = date("Y-m-d", strtotime('-7 day'));
                break;
            case 'month':
                $start_time = date('Y-m-01', strtotime('-1 month'));
                break;
            case 'time':
                $start_time = isset($_GET['day_start_time']) ? $_GET['day_start_time'] : date("Y-m-d", strtotime("-1 week"));;
                $end_time   = isset($_GET['day_end_time']) ? $_GET['day_end_time'] : date('Y-m-d');
                break;
        }

        return [
            'day_start_time' => $start_time,
            'day_end_time' => $end_time,
        ];
    }

    public static function dayDataToTableData($data) {

    }

    public static function selectMonth($months) {
        $len = count($months);
        $a = "";
        for ($i=0; $i<$len; $i++) {
            if ($i == ($len-1)) {
                $a .= "SELECT '".$months[$i]."' as create_date";
            }else {
                $a .= "SELECT '".$months[$i]."' as create_date union all ";
            }
        }

        return $a;
    }

    public static function selectNearHours($n=24) {
        $a = "";
        for ($i=$n; $i>=0; $i--) {
            if ($i == 0) {
                $a .= "SELECT DATE_FORMAT(NOW(), '%Y-%m-%d %H') AS create_date ";
            }else {
                $a .= "SELECT DATE_FORMAT((NOW() - interval $i hour), '%Y-%m-%d %H') AS create_date union all ";
            }
        }

        return $a;
    }

    public static function selectNearDay($d) {
        $a = "";
        for ($i=$d; $i>=1; $i--) {
            if ($i == 1) {
                $a .= "SELECT date_sub(curdate(), interval $i day) as create_date ";
            }else {
                $a .= "SELECT date_sub(curdate(), interval $i day) as create_date union all ";
            }
        }

        return $a;
    }

    /**
     * 1、微信扫码打开
     * 2、支付宝扫码打开
     * 0、其他扫码工具打开（如：浏览器，QQ扫码等）
     * @return int|string
     */
    public static function clientType(){
        $result = 0;
        if (strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') !== false) {
            $result = 1;
        }else if(strpos($_SERVER['HTTP_USER_AGENT'], 'AlipayClient') !== false){
            $result = 2;
        }

        return $result;
    }

    /**
     * 将用户数组中用户ID转为`,` 连接的字符串
     *
     * @param $users
     * @return bool|string
     */
    public static function usersToIds($users) {
        $result = '';
        if (is_array($users)) {
            foreach ($users as $user) {
                $result .= $user['id'].',';
            }
            $result = substr($result, -1);
        }
        return $result;
    }

    /**
     * 获取指定日期段内每一天的日期 日
     * @param $startdate
     * @param $enddate
     * @return array
     */
    public static function getDateFromRange($startdate, $enddate) {
        $stimestamp = strtotime($startdate);
        $etimestamp = strtotime($enddate);

        // 计算日期段内有多少天
        $days = ($etimestamp - $stimestamp) / 86400 + 1;

        // 保存每天日期
        $date = array();

        for ($i = 0; $i < $days; $i++) {
            $date[] = date('Y-m-d', $stimestamp + (86400 * $i));
        }

        return $date;
    }


    /**
     * 获取当前访问的完整url地址
     *
     * @return string
     */
    public static function get_current_url(){
        $current_url='http://';
        if(isset($_SERVER['HTTPS'])&&$_SERVER['HTTPS']=='on'){
            $current_url='https://';
        }
        if($_SERVER['SERVER_PORT']!='80'){
            $current_url.=$_SERVER['SERVER_NAME'].':'.$_SERVER['SERVER_PORT'].$_SERVER['REQUEST_URI'];
        }else{
            $current_url.=$_SERVER['SERVER_NAME'].$_SERVER['REQUEST_URI'];
        }

        return $current_url;
    }

    // 校验身份证号码是否正确
    public static function validation_filter_id_card($id_card)
    {
        if (strlen($id_card) == 18) {
            return  self::idcard_checksum18($id_card);
        } elseif ((strlen($id_card) == 15)) {
            $id_card = self::idcard_15to18($id_card);
            return self::idcard_checksum18($id_card);
        } else {
            return false;
        }
    }

    // 计算身份证校验码，根据国家标准GB 11643-1999
    public static function idcard_verify_number($idcard_base)
    {
        if (strlen($idcard_base) != 17) {
            return false;
        }
        //加权因子
        $factor = array(7, 9, 10, 5, 8, 4, 2, 1, 6, 3, 7, 9, 10, 5, 8, 4, 2);
        //校验码对应值
        $verify_number_list = array('1', '0', 'X', '9', '8', '7', '6', '5', '4', '3', '2');
        $checksum = 0;
        for ($i = 0; $i < strlen($idcard_base); $i++) {
            $checksum += substr($idcard_base, $i, 1) * $factor[$i];
        }
        self::FileDebug($checksum);
        $mod = $checksum % 11;
        $verify_number = $verify_number_list[$mod];
        return $verify_number;
    }

    // 将15位身份证升级到18位
    public static function idcard_15to18($idcard)
    {
        if (strlen($idcard) != 15) {
            return false;
        } else {
            // 如果身份证顺序码是996 997 998 999，这些是为百岁以上老人的特殊编码
            if (array_search(substr($idcard, 12, 3), array('996', '997', '998', '999')) !== false) {
                $idcard = substr($idcard, 0, 6) . '18' . substr($idcard, 6, 9);
            } else {
                $idcard = substr($idcard, 0, 6) . '19' . substr($idcard, 6, 9);
            }
        }
        $idcard = $idcard . self::idcard_verify_number($idcard);
        return $idcard;
    }

    // 18位身份证校验码有效性检查
    public static function idcard_checksum18($idcard)
    {
        if (strlen($idcard) != 18) {
            return false;
        }
        $idcard_base = substr($idcard, 0, 17);
        if (self::idcard_verify_number($idcard_base) != strtoupper(substr($idcard, 17, 1))) {
            return false;
        } else {
            return true;
        }
    }

    public static function strToNum($num) {
        if (!$num) {
            return 0;
        }

        $n = implode("", explode(",", $num));
        return $n;
    }
    public static function numToStr($num) {
        return number_format($num,2);
    }


    /**
     * 获取两个日期之间所有月份
     *
     * @param $start
     * @param $end
     * @return array
     */
    public static function prMonths($start, $end){ // 两个日期之间的所有月份
        $end = date('Y-m', strtotime($end)); // 转换为月
        $range = [];
        $i = 0;
        do {
            $month = date('Y-m', strtotime($start . ' + ' . $i . ' month'));
            //echo $i . ':' . $month . '<br>';
            $range[] = $month;
            $i++;
        } while ($month < $end);

        return $range;
    }

    /**
     * 获取两个日期之间所有日期
     * @param $start
     * @param $end
     * @return array
     */
    public static function prDates($start, $end){ // 两个日期之间的所有日期
	    $dt_start = strtotime($start);
	    $dt_end   = strtotime($end);
	    $res = [];
	    while ($dt_start<=$dt_end){
		    $res[]    = date('Y-m-d',$dt_start);
		    $dt_start = strtotime('+1 day',$dt_start);
	    }
	    if (count($res) <= 0) {
		    $res[] = date('Y-m-d', $dt_start);
	    }
	    return $res;
    }

    public static function selectDay($days) {
        $a = "";

        for ($i=0; $i<count($days); $i++) {
            $day = $days[$i];
            if ($i == (count($days) - 1) ) {
                $a .= "SELECT '$day' as create_date ";
            }else {
                $a .= "SELECT '$day' as create_date union all ";
            }
        }

        return $a;
    }

    /**
     * $val1 当前数据
     * $val2 上期数据
     *
     * @param $val1
     * @param $val2
     * @return array
     */
    public static function getIncreaseRatio($val1, $val2) {
        $val1 = self::strToNum($val1);
        $val2 = self::strToNum($val2);

        $res = [
            'increase' => 0,
            'increase_type' => 0
        ];
        if ($val1 > $val2) {
            $res['increase_type'] = 1; // 涨
        }

        // TODO 使用公式如下 (当前数据－上期数据)÷上期数据×100%
        if ($val1 != 0 && $val2!=0) {
            $res['increase'] = round( ( ( $val1 - $val2 ) / $val2 ),4) * 100;
        }

        return $res;
    }

    /** 获取本周开始时间 **/
    public static function getStartWeek() {
        //当前日期
        $sdefaultDate = date("Y-m-d");
        //$first =1 表示每周星期一为开始日期 0表示每周日为开始日期
        $first=1;
        //获取当前周的第几天 周日是 0 周一到周六是 1 - 6
        $w=date('w',strtotime($sdefaultDate));

        //获取本周开始日期，如果$w是0，则表示周日，减去 6 天
        $week_start=date('Y-m-d H:i:s',strtotime("$sdefaultDate -".($w ? $w - $first : 6).' days'));

        //本周结束日期
        $week_end=date('Y-m-d',strtotime("$week_start +6 days"));

        return $week_start;
    }

    public static function my_base64decode($str) {
	    if($str == base64_encode(base64_decode($str))) 
		    return base64_decode($str);
	    return $str;
    }

    public static function imgToBase64 ($image_file) {
        $base64_image = '';
        $image_info = getimagesize($image_file);
        $image_data = fread(fopen($image_file, 'r'), filesize($image_file));
        $base64_image = 'data:' . $image_info['mime'] . ';base64,' . chunk_split(base64_encode($image_data));
        return $base64_image;
    }

    public static function ArrMap($data, $key, $val, $tp=false) {
        $arr = [];
        foreach ($data as $datum) {
            $arr[] = [
                'title' => !$tp ? $datum[$key] : ('【' . $datum->typeStr['text'] . '】' . $datum[$key]),
                'value' => $datum[$val]
            ];
        }
        return $arr;
    }

    // 计算环比
    public static function ChainRatio($val1, $val2, $precision=1) {
        if ($val2<=0) {
            return 0;
        }
        return round(($val1 - $val2) / $val2, $precision);
    }

    // 将模型错误转为字符串
    public static function getModelError($model) {
        $errors = $model->getErrors();
        if (!is_array($errors)) return '';

        $fistError = array_shift($errors);
        if (!is_array($fistError)) return '';

        return array_shift($fistError);
    }

    // 获取随机字符串
    public static function RandomStr($length = 8) {
        // 字符集
        $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        $random_str ='';
        for ( $i = 0; $i < $length; $i++ ) {
            $random_str .= $chars[ mt_rand(0, strlen($chars) - 1) ];
        }
        return $random_str;
    }

    /**
     * 求两个日期之间相差的天数
     * @param string $day1
     * @param string $day2
     * @return number
     */
    public static function diffBetweenTwoDays ($day1, $day2) {
        $second1 = strtotime($day1);
        $second2 = strtotime($day2);

        if ($second1 < $second2) {
            $tmp = $second2;
            $second2 = $second1;
            $second1 = $tmp;
        }
        return floor( (($second1 - $second2) / 86400) );
    }

    /**
     * 获取某星期的开始时间和结束时间
     * time 时间
     * first 表示每周星期一为开始日期 0表示每周日为开始日期
     * @param string $time
     * @param int $first
     * @return array
     */
    public static function getWeekMyActionAndEnd($time = '', $first = 1) {
        //当前日期
        if (!$time) $time = time();
        $sdefaultDate = date("Y-m-d", $time);
        //$first =1 表示每周星期一为开始日期 0表示每周日为开始日期
        //获取当前周的第几天 周日是 0 周一到周六是 1 - 6
        $w = date('w', strtotime($sdefaultDate));
        //获取本周开始日期，如果$w是0，则表示周日，减去 6 天
        $week_start = date('Y-m-d', strtotime("$sdefaultDate -" . ($w ? $w - $first : 6) . ' days'));
        //本周结束日期
        $week_end = date('Y-m-d', strtotime("$week_start +6 days"));
        return array("start_time" => $week_start, "end_time" => $week_end);
    }

    /**
     * 判断字符串是否base64编码
     */
    public static function is_base64_str($str)
    {
        return $str == base64_encode(base64_decode($str)) ? true : false;
    }

    /**
     * 是否移动端访问访问
     *
     * @return bool
     */
    public static function isMobile()
    {
        // 如果是移动端微信浏览器打开
        if (\Yii::$app->wechat->isWechat)
            return true;

        // 如果有HTTP_X_WAP_PROFILE则一定是移动设备
        if (isset ($_SERVER['HTTP_X_WAP_PROFILE']))
            return true;

        // 如果via信息含有wap则一定是移动设备,部分服务商会屏蔽该信息
        if (isset ($_SERVER['HTTP_VIA']))
        {
            // 找不到为flase,否则为true
            return stristr($_SERVER['HTTP_VIA'], "wap") ? true : false;
        }
        // 脑残法，判断手机发送的客户端标志,兼容性有待提高
        if (isset ($_SERVER['HTTP_USER_AGENT']))
        {
            $clientkeywords = array ('nokia','sony','ericsson','mot','samsung','htc','sgh','lg','sharp','sie-','philips','panasonic','alcatel','lenovo','iphone','ipod','blackberry','meizu','android','netfront','symbian','ucweb','windowsce','palm','operamini','operamobi','openwave','nexusone','cldc','midp','wap','mobile');
            // 从HTTP_USER_AGENT中查找手机浏览器的关键字
            if (preg_match("/(" . implode('|', $clientkeywords) . ")/i", strtolower($_SERVER['HTTP_USER_AGENT'])))
                return true;
        }
        // 协议法，因为有可能不准确，放到最后判断
        if (isset ($_SERVER['HTTP_ACCEPT']))
        {
            // 如果只支持wml并且不支持html那一定是移动设备
            // 如果支持wml和html但是wml在html之前则是移动设备
            if ((strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') !== false) && (strpos($_SERVER['HTTP_ACCEPT'], 'text/html') === false || (strpos($_SERVER['HTTP_ACCEPT'], 'vnd.wap.wml') < strpos($_SERVER['HTTP_ACCEPT'], 'text/html'))))
            {
                return true;
            }
        }
        return false;
    }

    public static function FileDebug($msg) {
        file_put_contents('file.json', $msg . "   \n\n", FILE_APPEND);
    }
    public static function MetabaseUrl($payload, $embed_type='/embed/dashboard/') {
	    //add exp time to url
	    $payl = json_decode($payload);
	    $exp_time = time()+(60*10);
	    $payl->{'exp'} = $exp_time;

	    $METABASE_SITE_URL = "https://wx.mjyun.com";
	    $METABASE_SECRET_KEY = "120fd7e510a41c0ee882847e4f833c8632ec8b4bb60b5efbb3c08634afa88f8e";
	    $token = jwt_encode($payl, $METABASE_SECRET_KEY);
	    $iframeUrl = $METABASE_SITE_URL . $embed_type . $token . "#bordered=false&titled=false";
	    return $iframeUrl; 
    }

    // 二维数组排序
    public static function assoc_unique($arr, $key) {

        $tmp_arr = array();

        foreach ($arr as $k => $v) {

            if (in_array($v[$key], $tmp_arr)) {//搜索$v[$key]是否在$tmp_arr数组中存在，若存在返回true

                unset($arr[$k]);

            } else {

                $tmp_arr[] = $v[$key];

            }

        }

        sort($arr); //sort函数对数组进行排序

        return $arr;
    }
}
