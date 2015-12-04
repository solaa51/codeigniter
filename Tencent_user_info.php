<?php

/**
 * Created by PhpStorm.
 * User: suliang
 * Date: 15/11/23
 * Time: 17:46
 *
 * 需要自己初始化过session 或 按自己的需要缓存
 */
class Tencent_user_info
{
    private $_ci = NULL;

    private $app_param = array( //配置参数
        'app_id'  => '',
        'app_key' => ''
    );
    private $input_param = array( //初始化传入参数
        'platform_type' => 1, //QQ授权页显示样子   1pc版 或 2wap版
        'callback_uri' => ''
    );

    public function __construct($input_config=array())
    {
        if( isset($input_config['platform_type']) ){
            $this->input_param['platform_type'] = $input_config['platform_type'];
        }
        if( isset($input_config['callback_uri']) ){
            $this->input_param['callback_uri'] = $input_config['callback_uri'];
        }

        //初始化ci类
        $this->_ci = &get_instance();
    }

    public function get_code()
    {
        $url = 'https://graph.qq.com/oauth2.0/authorize';

        $request_data['response_type'] = 'code';
        $request_data['client_id'] = $this->app_param['app_id'];
        $request_data['scope'] = 'get_user_info';
        $request_data['state'] = md5(uniqid(rand(), TRUE));
        if( $this->input_param['platform_type']==1 ){
            $request_data['display'] = '';//默认pc时显示的样式
        }else{
            $request_data['display'] = 'mobile';//wap版 mobile
        }
        if( $this->input_param['platform_type']!=1 ){
            $request_data['g_ut'] = 1;
        }
        $request_data['redirect_uri'] = $this->input_param['callback_uri'];

        $build_str = http_build_query($request_data);
        $url = $url . ( strpos($url,'?') ? '&'.$build_str : '?'.$build_str );

        $sess_arr = array(
            'tencent_login_state' => $request_data['state']
        );
        $this->_ci->session->set_userdata($sess_arr);

        redirect($url);
    }

    /**
     * @param $code
     * @return mixed
     */
    public function get_access_token($code)
    {
        $url = 'https://graph.qq.com/oauth2.0/token';
        $request_data['grant_type'] = 'authorization_code';
        $request_data['client_id'] = $this->app_param['app_id'];
        $request_data['client_secret'] = $this->app_param['app_key'];
        $request_data['code'] = $code;
        $request_data['redirect_uri'] = $this->input_param['callback_uri'];

        $build_str = http_build_query($request_data);
        $url = $url . ( strpos($url,'?') ? '&'.$build_str : '?'.$build_str );

        $response = $this->send_content($url);

        if (strpos($response, "callback") !== false){
            $lpos = strpos($response, "(");
            $rpos = strrpos($response, ")");
            $response  = substr($response, $lpos + 1, $rpos - $lpos -1);
            $msg = json_decode($response,true);
            if (isset($msg['error'])){
                exit($msg['error'].'错误：'.$msg['error_description']);
            }
        }
        /**
         * TODO 错误处理
         * access_token
         * expires_in 过期时长
         * refresh_token
         */
        $res = array();
        parse_str($response,$res);

        return $res['access_token'];
    }

    public function get_openid($access_token)
    {
        $url = 'https://graph.qq.com/oauth2.0/me';
        $url .= '?access_token='.$access_token;

        $response  = $this->send_content($url);
        if (strpos($response, "callback") !== false){
            $lpos = strpos($response, "(");
            $rpos = strrpos($response, ")");
            $response  = substr($response, $lpos + 1, $rpos - $lpos -1);
        }

        $res = json_decode($response,true);
        if (isset($res['error'])){
            exit('获取用户信息失败，请重新授权');
        }
        /**
         * client_id 101261383
         * openid    D5C62E3B4A724064D25511C16640D68B
         */
        return $res['openid'];
    }

    public function get_user_info($access_token,$open_id)
    {
        $get_user_info = "https://graph.qq.com/user/get_user_info?"
            . "access_token=" . $access_token
            . "&oauth_consumer_key=" . $this->app_param['app_id']
            . "&openid=" . $open_id
            . "&format=json";

        $info = $this->send_content($get_user_info);
        $arr = json_decode($info, true);

        return $arr;
    }

    private function send_content($url)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_URL, $url);

        $response =  curl_exec($ch);
        curl_close($ch);

        return $response;
    }

}
