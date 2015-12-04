<?php

/**
 * Created by PhpStorm.
 * User: suliang
 * Date: 15/11/24
 * Time: 10:29
 */
class Weibo_user_info
{
    private $_ci = NULL;

    private $app_param = array( //配置参数
        'app_id'  => '',
        'app_key' => ''
    );
    private $input_param = array( //初始化传入参数
        'platform_type' => 1, //微博授权页显示样子   1pc版 或 2wap版
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
        $url = 'https://api.weibo.com/oauth2/authorize';

        $request_data['client_id'] = $this->app_param['app_id'];
        $request_data['redirect_uri'] = $this->input_param['callback_uri'];

        $request_data['scope'] = '';//TODO 具体看看类型

        if( $this->input_param['platform_type']==1 ){
            $request_data['display'] = 'default';//默认pc时显示的样式
        }else{
            $request_data['display'] = 'mobile';//wap版 mobile
        }
        $request_data['state'] = md5(uniqid(rand(), TRUE));



        $build_str = http_build_query($request_data);
        $url = $url . ( strpos($url,'?') ? '&'.$build_str : '?'.$build_str );

        $sess_arr = array(
            'weibo_login_state' => $request_data['state']
        );
        $this->_ci->session->set_userdata($sess_arr);

        redirect($url);
    }

    public function get_access_token($code)
    {
        $url = 'https://api.weibo.com/oauth2/access_token';
        $request_data['client_id'] = $this->app_param['app_id'];
        $request_data['client_secret'] = $this->app_param['app_key'];
        $request_data['grant_type'] = 'authorization_code';
        $request_data['code'] = $code;
        $request_data['redirect_uri'] = $this->input_param['callback_uri'];

        $build_str = http_build_query($request_data);

        $response = $this->send_content($url,'POST',$build_str);

        //{"access_token":"2.00mnNr4BPUPRGDde8f8621c0nFRubC","remind_in":"157679999","expires_in":157679999,"uid":"1268673002","scope":"follow_app_official_microblog"}
        /**
         * access_token	string	用于调用access_token，接口获取授权后的access token
         * expires_in	string	access_token的生命周期，单位是秒数
         * remind_in 废弃
         * uid	string	当前授权用户的UID
         */

        $res = json_decode($response,true);

        if( isset($res['error']) ){
            exit($res['error']);
        }
        return $res;
    }

    public function get_user_info($access_token,$uid)
    {
        $url = 'https://api.weibo.com/2/users/show.json';

        $request_data['access_token'] = $access_token;
        $request_data['uid'] = $uid;

        $build_str = http_build_query($request_data);
        $url = $url . ( strpos($url,'?') ? '&'.$build_str : '?'.$build_str );

        $response = $this->send_content($url);
        $response = json_decode($response,true);
        return $response;
    }



    private function send_content($url,$type='GET',$data=array())
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_URL, $url);
        if( $type=='POST' ){
            curl_setopt($ch, CURLOPT_POST, TRUE);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }

        $response =  curl_exec($ch);
        curl_close($ch);

        return $response;
    }

}
