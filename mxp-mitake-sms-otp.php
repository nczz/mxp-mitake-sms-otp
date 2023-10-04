<?php
/*
Plugin Name: 三竹 OTP 簡訊發送 API
Plugin URI:
Description: 透過指定接收的 Webhook 發送簡訊
Author: Chun
Version: 1.0
Author URI: https://www.mxp.tw/
 */

if (!defined('WPINC')) {
    die;
}

if (!defined("MXP_B32ECS")) {
    define("MXP_B32ECS", 'AGGA BMMB CXXC DPPD ETTE ZWWZ');
}

function mxp_mitake_sms_get_query() {
    $sms_account  = get_option("mxp_mitake_sms_account", "");
    $sms_password = get_option("mxp_mitake_sms_password", "");
    $args         = array(
        'timeout'     => 10,
        'redirection' => 5,
        'httpversion' => '1.1',
        'user-agent'  => 'WordPress',
        'blocking'    => true,
        'headers'     => array(),
        'cookies'     => array(),
        'body'        => array(
            'username' => $sms_account,
            'password' => $sms_password,
        ),
        'compress'    => false,
        'decompress'  => true,
        'sslverify'   => false,
        'stream'      => false,
        'filename'    => null,
    );
    $response = wp_remote_post("https://smsapi.mitake.com.tw/api/mtk/SmQuery", $args);
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        return array('code' => 504, 'data' => '', 'msg' => $error_message);

    } else {
        $body = wp_remote_retrieve_body($response);
        if ($body == "") {
            return array('code' => 504, 'data' => $response, 'msg' => 'FAIL!');
        } else {
            return array('code' => 200, 'data' => $body);
        }
    }
}

function mxp_mitake_sms_send($mobile = "", $msg = "", $prefix = "") {
    if ($mobile == "" || $msg == "") {
        return false;
    }
    $sms_text     = str_replace(array("/n", "\r\n", "\n"), chr(6), $msg);
    $sms_account  = get_option("mxp_mitake_sms_account", "");
    $sms_password = get_option("mxp_mitake_sms_password", "");
    $text         = '';
    $mobile       = str_replace(array(' ', '-', '+'), '', $mobile);
    $uuid         = str_replace('.', "", microtime(true));
    //優先實作批次發送機制，避免後續有大批寄送需求，請求過多會有效能問題
    // if (strpos($mobile, '09') == 0 && strpos($mobile, '09') !== false && strlen($mobile) == 10) {
    // $line1 = "[{$key}]\n";
    // $line2 = "DestName={$entry['name']}\n";
    // $line3 = "dstaddr={$mobile}\n";
    // $line4 = "smbody={$sms_text}\n";
    // $text .= $line1 . $line2 . $line3 . $line4;
    $dlvtime   = "";
    $vldtime   = "";
    $destname  = "";
    $response  = "";
    $sender_id = !empty($prefix) ? $prefix . $uuid : $uuid;
    $text .= "{$sender_id}" . '$$' . "{$mobile}" . '$$' . "{$dlvtime}" . '$$' . "{$vldtime}" . '$$' . "{$destname}" . '$$' . "{$response}" . '$$' . "{$sms_text}\r\n";
    // }
    $smbody = $text; //trim($text);
    $url    = "";
    if ($sms_password != "" && $sms_account != "") {
        $url = 'https://smsapi.mitake.com.tw/api/mtk/SmBulkSend?username=' . $sms_account . '&password=' . $sms_password . '&Encoding_PostIn=UTF-8&objectID=' . $uuid;
    }

    if ($url == "") {
        return array('code' => 504, 'data' => '', 'msg' => '帳密不正確');
    }

    $args = array(
        'timeout'     => 10,
        'redirection' => 5,
        'httpversion' => '1.1',
        'user-agent'  => 'WordPress',
        'blocking'    => true,
        'headers'     => array(),
        'cookies'     => array(),
        'body'        => $smbody,
        'compress'    => false,
        'decompress'  => true,
        'sslverify'   => false,
        'stream'      => false,
        'filename'    => null,
    );
    $response = wp_remote_post($url, $args);
    if (is_wp_error($response)) {
        $error_message = $response->get_error_message();
        return array('code' => 504, 'data' => '', 'msg' => $error_message);
    } else {
        $body = wp_remote_retrieve_body($response);
        if ($body == "" || strpos($body, 'Error') !== false) {
            return array('code' => 504, 'data' => $response, 'msg' => 'FAIL!');
        } else {
            return array('code' => 200, 'data' => $body);
        }
    }
}

function mxp_mitake_get_otp_sms() {
    include dirname(__FILE__) . '/vendor/autoload.php';
    $totp      = PedroSancao\OTP\TOTP::create(MXP_B32ECS);
    $timestamp = time();
    $sms_text  = '[MXP] 簡訊驗證碼： ' . $totp->getPassword($timestamp);
    $text      = '';
    $mobile    = str_replace(array(' ', '-', '+'), '', $_POST['mobile']);
    if (!is_numeric($mobile) || empty($mobile)) {
        wp_send_json_error(array('code' => 500, 'msg' => '錯誤手機號碼或格式。'));
    }
    if (username_exists($mobile)) {
        wp_send_json_error(array('code' => 500, 'msg' => '已經註冊過的手機號碼。'));
    }

    if (false === ($cache = get_transient('mxp_mobile-' . $mobile))) {
        set_transient('mxp_mobile-' . $mobile, time(), 120);
        $ret = mxp_mitake_sms_send($mobile, $sms_text);
    }
    if (time() - $cache >= 120) {
        $ret = mxp_mitake_sms_send($mobile, $sms_text);
    } else {
        wp_send_json_error(array('code' => 500, 'msg' => '請確認手機簡訊，或等待倒數時間結束重新發送。', 'data' => 120 - (time() - $cache)));
    }
    if (!is_array($ret) || $ret['code'] != 200) {
        wp_send_json_error(array('code' => 500, 'msg' => '簡訊服務異常，請稍後再試。'));
    } else {
        wp_send_json_success(array('code' => 200, 'data' => $timestamp, 'msg' => '請查收簡訊，填寫回網站驗證。'));
    }
}
add_action('wp_ajax_mxp_mitake_get_otp_sms', 'mxp_mitake_get_otp_sms');
add_action('wp_ajax_nopriv_mxp_mitake_get_otp_sms', 'mxp_mitake_get_otp_sms');

function mxp_verify_otp($code, $timestamp = '') {
    if ($code == '') {
        return false;
    }
    include dirname(__FILE__) . '/vendor/autoload.php';
    $totp = PedroSancao\OTP\TOTP::create(MXP_B32ECS);
    if ($timestamp == '') {
        $timestamp = time();
    }
    $otp_match = $totp->getPassword($timestamp);
    return $totp->verify($code, $timestamp, 4);
}

function mxp_verify_sms_otp() {
    include dirname(__FILE__) . '/vendor/autoload.php';
    $totp = PedroSancao\OTP\TOTP::create(MXP_B32ECS);
    if (!isset($_POST['otp']) || $_POST['otp'] == '' || strlen($_POST['otp']) != 6) {
        wp_send_json_error(array('code' => 500, 'msg' => '無效的輸入'));
    }
    if (isset($_POST['timestamp']) && is_numeric($_POST['timestamp'])) {
        $otp_match = $totp->getPassword($_POST['timestamp']);
        if ($totp->verify($_POST['otp'], $_POST['timestamp'], 4)) {
            wp_send_json_success(array('code' => 200, 'msg' => '驗證成功'));
        } else {
            wp_send_json_error(array('code' => 403, 'msg' => '驗證失敗'));
        }
    } else {
        $otp_match = $totp->getPassword();
        if ($totp->verify($_POST['otp'], null, 4)) {
            wp_send_json_success(array('code' => 200, 'msg' => '驗證成功'));
        } else {
            wp_send_json_error(array('code' => 403, 'msg' => '驗證失敗'));
        }
    }
}
add_action('wp_ajax_mxp_verify_sms_otp', 'mxp_verify_sms_otp');
add_action('wp_ajax_nopriv_mxp_verify_sms_otp', 'mxp_verify_sms_otp');

function mxp_add_woocommerce_submenu_pages() {
    add_submenu_page('woocommerce', '三竹簡訊餘額檢視', '三竹簡訊餘額檢視', 'manage_woocommerce', 'mxp-sms-setting', 'mxp_sms_setting_page');
}
add_action('admin_menu', 'mxp_add_woocommerce_submenu_pages');

function mxp_sms_setting_page() {
    if (isset($_POST['sms_account']) &&
        isset($_POST['sms_password']) &&
        $_POST['sms_account'] != '' &&
        $_POST['sms_password'] != '' &&
        check_admin_referer('mxp-sms-setting')
    ) {
        update_option("mxp_mitake_sms_account", sanitize_text_field($_POST['sms_account']));
        update_option("mxp_mitake_sms_password", sanitize_text_field($_POST['sms_password']));
    }
    $res    = mxp_mitake_sms_get_query();
    $points = -1;
    if (isset($res['data']) && $res['data'] != '') {
        $parsing = explode('=', $res['data']);
        $points  = end($parsing);
    }
    echo '<h1>簡訊剩餘點數：' . $points . '</h1>';
    echo '<h2>設定簡訊帳密</h2>';
    echo '<form method="POST" action="">';
    wp_nonce_field('mxp-sms-setting');
    echo '<p>三竹簡訊帳號：<input type="text" name="sms_account" value="' . get_option("mxp_mitake_sms_account", "") . '"></p>';
    echo '<p>三竹簡訊密碼：<input type="text" name="sms_password" value="' . get_option("mxp_mitake_sms_password", "") . '"></p>';
    echo '<button type="submit" class="button">存檔</button></br>';
    echo '</form>';
}
