<?php
if(!defined('_VALID_NAPP_REQ') || _VALID_NAPP_REQ!==TRUE) { die('Invalid request!'); }
// Load NETopes AJAX specific AppConfig structure
require_once(__DIR__.'/napp_cfg_structure.php');
$_CUSTOM_CONFIG_STRUCTURE = array_merge((isset($_NAPP_AJAX_CONFIG_STRUCTURE) && is_array($_NAPP_AJAX_CONFIG_STRUCTURE) ? $_NAPP_AJAX_CONFIG_STRUCTURE : []),(isset($_CUSTOM_CONFIG_STRUCTURE) && is_array($_CUSTOM_CONFIG_STRUCTURE) ? $_CUSTOM_CONFIG_STRUCTURE : []));