<?php

/**
 * Created by PhpStorm.
 * User: suliang
 * Date: 15/11/23
 * Time: 17:46
 *
 * 需要自己初始化过session 或 按自己的需要缓存
 */
class Wechat_user_info
{
    private $_ci = NULL;

    private $app_param = array( //配置参数
        'app_id'  => 'wx3087b2736d4a3574',
        'app_key' => '1dedcba1fe05f1146541d790486acd6c'
    );
    private $input_param = array( //初始化传入参数
        'callback_uri' => ''
    );

    public function __construct($input_config=array())
    {
        if( isset($input_config['callback_uri']) ){
            $this->input_param['callback_uri'] = $input_config['callback_uri'];
        }

        //初始化ci类
        $this->_ci = &get_instance();
    }

    public function get_code()
    {
        $url = 'https://open.weixin.qq.com/connect/qrconnect';

        $request_data['appid'] = $this->app_param['app_id'];
        $request_data['redirect_uri'] = $this->input_param['callback_uri'];
        $request_data['response_type'] = 'code';
        $request_data['scope'] = 'snsapi_login';
        $request_data['state'] = md5(uniqid(rand(), TRUE));

        $build_str = http_build_query($request_data);
        $url = $url . ( strpos($url,'?') ? '&'.$build_str : '?'.$build_str );

        $sess_arr = array(
            'wechat_login_state' => $request_data['state']
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
        $url = 'https://api.weixin.qq.com/sns/oauth2/access_token';
        $request_data['grant_type'] = 'authorization_code';
        $request_data['appid'] = $this->app_param['app_id'];
        $request_data['secret'] = $this->app_param['app_key'];
        $request_data['code'] = $code;

        $build_str = http_build_query($request_data);
        $url = $url . ( strpos($url,'?') ? '&'.$build_str : '?'.$build_str );

        $response = $this->send_content($url);
        $response = json_decode($response,true);
        if( isset($response['errcode'])&&(intval($response['errcode'])>0) ){
            exit($response['errcode'].':'.$response['errmsg']);
        }

        /**
         * access_token
         * expires_in 过期时长
         * refresh_token
         */
        return $response;
    }

    public function get_user_info($access_token,$open_id)
    {
        $get_user_info = "https://api.weixin.qq.com/sns/userinfo?"
            . "access_token=" . $access_token
            . "&openid=" . $open_id;

        $info = $this->send_content($get_user_info);
        $response = json_decode($info, true);

        if( isset($response['errcode'])&&(intval($response['errcode'])>0) ){
            exit($response['errcode'].':'.$response['errmsg']);
        }

        return $response;
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
