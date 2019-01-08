<?php
/**
 * NETopes AJAX configuration structure file
 *
 * Here are all the configuration elements definition for NETopes AJAX
 *
 * @package    NETopes\Ajax
 * @author     George Benjamin-Schonberger
 * @copyright  Copyright (c) 2013 - 2019 AdeoTEK Software SRL
 * @license    LICENSE.md
 * @version    1.0.0.0
 * @filesource
 */
if(!defined('_VALID_NAPP_REQ') || _VALID_NAPP_REQ!==TRUE) { die('Invalid request!'); }
$_NAPP_AJAX_CONFIG_STRUCTURE = [
//START AJAX configuration
    // Use NETopes AJAX extension
    'app_use_ajax_extension'=>['access'=>'readonly','default'=>TRUE,'validation'=>'bool'],
    // Target file for NETopes AJAX post (relative path from public folder + name)
    'app_ajax_target'=>['access'=>'readonly','default'=>'aindex.php','validation'=>'is_notempty_string'],
    // NETopes AJAX session key
    'app_session_key'=>['access'=>'readonly','default'=>'NAPP_DATA','validation'=>'is_notempty_string'],
    // NETopes AJAX implementing class name
    'ajax_class_name'=>['access'=>'readonly','default'=>'\NETopes\Ajax\Request','validation'=>'is_string'],
    // NETopes AJAX implementing class file full name (including path)
    'ajax_class_file'=>['access'=>'readonly','default'=>'','validation'=>'is_string'],
    // Javascript on request completed callback
    'app_arequest_js_callback'=>['access'=>'readonly','default'=>'','validation'=>'is_string'],
    // Secure http support on/off
    'app_secure_http'=>['access'=>'readonly','default'=>TRUE,'validation'=>'bool'],
    // Parameters sent as value encryption on/off
    'app_params_encrypt'=>['access'=>'readonly','default'=>FALSE,'validation'=>'bool'],
    // Mod rewrite support on/off
    'app_mod_rewrite'=>['access'=>'readonly','default'=>TRUE,'validation'=>'bool'],
    // Window name auto usage on/off
    'app_use_window_name'=>['access'=>'readonly','default'=>TRUE,'validation'=>'bool'],
//END AJAX configuration
];