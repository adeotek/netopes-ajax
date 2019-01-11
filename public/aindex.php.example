<?php
/**
 * NETopes AJAX requests entry point file
 *
 * All AJAX request have this file as target.
 *
 * @package    NETopes\Ajax
 * @author     George Benjamin-Schonberger
 * @copyright  Copyright (c) 2013 - 2019 AdeoTEK Software SRL
 * @license    LICENSE.md
 * @version    1.0.0.0
 * @filesource
 */
use NETopes\Core\App\ModulesProvider;
define('_VALID_NAPP_REQ',TRUE);
require_once('pathinit.php');
if(defined('_NAPP_OFFLINE') && _NAPP_OFFLINE) { die('OFFLINE!'); }
/* Let browser know that response is utf-8 encoded */
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate'); // HTTP 1.1.
header('Pragma: no-cache'); // HTTP 1.0.
header('Expires: 0'); // Proxies.
header('X-Frame-Options: GOFORIT');
header('Content-Language: en');
if(in_array('globals',array_keys(array_change_key_case($_REQUEST,CASE_LOWER)))) { exit(); }
if(in_array('_post',array_keys(array_change_key_case($_REQUEST,CASE_LOWER)))) { exit(); }
require_once(_NAPP_ROOT_PATH._NAPP_APPLICATION_PATH._NAPP_CONFIG_PATH.'/Configuration.php');
require_once(_NAPP_ROOT_PATH._NAPP_APPLICATION_PATH.'/vendor/autoload.php');
require_once(NETopes\Ajax\AppPath::GetBootFile());
require_once(NETopes\Core\AppPath::GetBootFile());
$cnamespace = array_key_exists('namespace',$_POST) ? $_POST['namespace'] : NULL;
$dont_keep_alive = get_array_param($_GET,'dnka',get_array_param($_POST,'do_not_keep_alive',FALSE,'bool'),'bool');
$napp = NApp::GetInstance(TRUE,array('namespace'=>$cnamespace),TRUE,$dont_keep_alive);
$napp->LoadAppSettings();
header('Content-Language: '.$napp->GetLanguageCode(),TRUE);
$url_arhash = get_array_param($_GET,'arhash',NULL,'is_string');
$module = get_array_param($_GET,'module',get_array_param($_POST,'module',NULL,'is_string'),'is_string');
$method = get_array_param($_GET,'method',get_array_param($_POST,'method',NULL,'is_string'),'is_string');
if(!$url_arhash && (!$module || !$method)) {
    $napp->ExecuteAjaxRequest(['namespace'=>$napp->current_namespace],$napp->current_namespace);
} else {
    $rtype = get_array_param($_GET,'type',get_array_param($_POST,'type',NULL,'is_string'),'is_string');
    $uid = get_array_param($_GET,'uid',get_array_param($_POST,'uid',NULL,'is_string'),'is_string');
    $params = array_merge($_POST,$_GET);
    $params['response_type'] = $rtype;
    unset($params['arhash']);
    if($url_arhash) {
        $url_arhash = GibberishAES::dec(rawurldecode($url_arhash),'xJS');
        if(!$url_arhash) { die('Invalid request parameters!'); }
        $url_params = [];
        foreach(explode('&',$url_arhash) as $raw) {
            $raw_arr = explode('=',$raw);
            $url_params[strtolower($raw_arr[0])] = isset($raw_arr[1]) ? $raw_arr[1] : NULL;
        }//END foreach
        $module = get_array_param($url_params,'module',$module,'is_string');
        $method = get_array_param($url_params,'method',$method,'is_string');
        $uid = get_array_param($url_params,'uid',$uid,'is_string');
        $params = array_merge($params,$url_params);
    }//if($url_arhash)
    if(!$napp->CheckSessionAcceptedRequest($uid)) { die('Unauthorized access!'); }
    $module = convert_to_camel_case($module,FALSE,TRUE);
    $method = convert_to_camel_case($method);
    if(!ModulesProvider::ModuleMethodExists($module,$method)) { die('Invalid request!'); }
    ob_start();
    try {
        $result = ModulesProvider::Exec($module,$method,$params);
        $content = ob_get_contents();
        ob_end_clean();
    } catch(\NETopes\Core\AppException $e) {
        ob_end_clean();
        $result = $e->getMessage();
        $napp->Write2LogFile($e->getMessage(),'error');
        throw $e;
    }//END try
    $napp->NamespaceSessionCommit(NULL,TRUE);
    switch(strtolower($rtype)) {
        case 'json':
            header('Content-type: application/json');
            echo json_encode($result);
            break;
        case 'jsonp':
            header('Content-type: application/jsonp');
            echo json_encode($result);
            break;
        case 'php':
            echo serialize($result);
            break;
        case 'html':
        default:
            echo utf8_encode($content.(is_string($result) ? $result : ''));
            break;
    }//END switch
}//if(!$url_arhash && (!$module || !$method))
