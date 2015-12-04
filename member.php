<?php

/**
 * Created by PhpStorm.
 * User: suliang
 * Date: 15/11/23
 * Time: 13:50
 */
class member extends BaseController
{
    
    /*******************第三方登录接口*********************/
    /**
     * 获取authorize_code 跳转之第三方授权
     * @param type tencent weibo wechat
     */
    public function third_party_login($type='tencent')
    {
        //初始化传入参数
        $param_data = array(
            'platform_type' => 1,//pc或wap  weixin不使用此参数
            'callback_uri' => 'http://www'.BASE_DOMAIN.'/member/third_party_login/'.$type  //回调页面地址
        );
        switch($type) {
            case 'tencent' :
            case 'weibo' :
            case 'wechat' :
                $this->load->library($type.'_user_info', $param_data, 'third_party');
                break;
            default:
                exit("暂未支持的类型");
        }

        $gets = $this->input->get();
        if( isset($gets['code'])&&isset($gets['state']) ){
            //授权回调
            $login_state = $this->session->userdata($type.'_login_state');
            if( $login_state ){
                if( $login_state==$gets['state'] ){
                    switch($type){
                        case 'tencent':
                            //获取access_token
                            $access_token = $this->third_party->get_access_token($gets['code']);
                            $open_id = $this->third_party->get_openid($access_token);

                            //到会员表查询该open_id
                            $id = $this->member_model->where(array('role_id'=>2,'qq_openid'=>$open_id))->get_field('id');
                            if( !$id ){
                                $user_info = $this->third_party->get_user_info($access_token,$open_id);
                                $id = $this->third_party_regist($type, $user_info, $open_id);
                            }
                            $this->session->set_userdata(array(
                                'login_member' => array(
                                    'id' => $id
                                )
                            ));
                            break;
                        case 'weibo':
                            //获取access_token
                            $token_info = $this->third_party->get_access_token($gets['code']);

                            //到会员表查询该open_id
                            $id = $this->member_model->where(array('role_id'=>3,'wb_openid'=>$token_info['uid']))->get_field('id');
                            if( !$id ){
                                //获取用户信息
                                $user_info = $this->third_party->get_user_info($token_info['access_token'],$token_info['uid']);
                                if( isset($user_info['error'])&&$user_info['error'] ){
                                    exit($user_info['error'].$user_info['error_code']);
                                }
                                $id = $this->third_party_regist($type, $user_info, $token_info['uid']);
                                $this->session->set_userdata(array(
                                    'login_member' => array(
                                        'id' => $id
                                    )
                                ));
                            }
                            $this->session->set_userdata(array(
                                'login_member' => array(
                                    'id' => $id
                                )
                            ));
                            break;
                        case 'wechat':
                            //获取access_token
                            $token_info = $this->third_party->get_access_token($gets['code']);

                            //到会员表查询该open_id
                            $id = $this->member_model->where(array('role_id'=>4,'wx_openid'=>$token_info['openid']))->get_field('id');
                            if( !$id ){
                                //获取用户信息
                                $user_info = $this->third_party->get_user_info($token_info['access_token'],$token_info['openid']);
                                $id = $this->third_party_regist($type, $user_info, $token_info['openid']);
                                $this->session->set_userdata(array(
                                    'login_member' => array(
                                        'id' => $id
                                    )
                                ));
                            }
                            $this->session->set_userdata(array(
                                'login_member' => array(
                                    'id' => $id
                                )
                            ));
                            break;
                    }
                }else{
                    exit('状态信息验证失败');
                }
                $this->session->unset_userdata($type.'_login_state');
            }
        }else{
            //去第三方授权
            $this->third_party->get_code();
            exit("第三方登录");
        }

        //回调后的内容处理
        /**
         * 关闭本页面 跟js交互
         */
        $this->load->view( $this->dcm, $this->data );
    }

    private function third_party_regist($type,$user_info,$open_id)
    {
        switch($type){
            case 'tencent':
                $save_data['nickname'] = $user_info['nickname'];
                $save_data['username'] = 'qq'.md5($open_id);
                $save_data['logo'] = $user_info['figureurl_qq_2'] ? :$user_info['figureurl_qq_1'];
                if ($user_info['gender']=='男') {
                    $save_data['sex'] = 1;
                }else if( $user_info['gender']=='女' ){
                    $save_data['sex'] = 2;
                }else{
                    $save_data['sex'] = 0;
                }
                $save_data['role_id'] = 2;
                $save_data['qq_openid'] = $open_id;
                $save_data['last_ip'] = $this->input->ip_address();
                $save_data['dt_login'] = $save_data['dt_add'] = time();
                $id = $this->member_model->add($save_data);
                if( !$id ){
                    exit("快速登录失败");
                }
                break;
            case 'weibo':
                $save_data['nickname'] = $user_info['screen_name'];
                $save_data['username'] = 'wb'.md5($open_id);
                $save_data['logo'] = $user_info['avatar_large'];
                if ($user_info['gender']=='m') {
                    $save_data['sex'] = 1;
                }else if( $user_info['f']=='女' ){
                    $save_data['sex'] = 2;
                }else{
                    $save_data['sex'] = 0;
                }

                $save_data['role_id'] = 3;
                $save_data['wb_openid'] = $user_info['id'];
                $save_data['last_ip'] = $this->input->ip_address();
                $save_data['dt_login'] = $save_data['dt_add'] = time();
                $id = $this->member_model->add($save_data);
                if( !$id ){
                    exit("快速登录失败");
                }
                break;
            case 'wechat':
                $save_data['nickname'] = $user_info['nickname'];
                $save_data['username'] = 'wx'.md5($open_id);
                $save_data['logo'] = $user_info['headimgurl'];
                $save_data['sex'] = $user_info['sex'];
                $save_data['wx_openid'] = $open_id;

                $save_data['role_id'] = 4;
                $save_data['last_ip'] = $this->input->ip_address();
                $save_data['dt_login'] = $save_data['dt_add'] = time();
                $id = $this->member_model->add($save_data);
                if( !$id ){
                    exit("快速登录失败");
                }
                break;
            default:
                exit('用户登录失败');
        }
        return $id;
    }
    /*******************end*********************/


    
    

}
