<?php
/**
 * NETopes AJAX Request class file
 *
 * This class extends NETopes\Ajax\BaseRequest
 *
 * @package    NETopes\Ajax
 * @author     George Benjamin-Schonberger
 * @copyright  Copyright (c) 2013 - 2019 AdeoTEK Software SRL
 * @license    LICENSE.md
 * @version    1.0.0.0
 * @filesource
 */
namespace NETopes\Ajax;
use NETopes\Core\App\ModulesProvider;
use NETopes\Core\App\Params;
use NETopes\Core\AppConfig;
use NETopes\Core\AppException;
use GibberishAES;
use NApp;

/**
 * Class Request
 *
 * @package  NETopes\Ajax
 * @access   public
 */
class Request extends BaseRequest {
    /**
	 * Generic ajax call
	 *
	 * @param        $windowName
	 * @param        $module
	 * @param        $method
	 * @param string $params
	 * @param string $target
	 * @param int    $nonCustom
	 * @param int    $resetSessionParams
	 * @return void
	 * @access public
	 * @throws \NETopes\Core\AppException
	 */
	public function AjaxRequest($windowName,$module,$method,$params = NULL,$target = NULL,$nonCustom = 0,$resetSessionParams = 0) {
		if(!strlen($windowName)) { $this->ExecuteJs("window.name = '".NApp::GetPhash()."'"); }
		try {
			$olduserid = NApp::GetPageParam('user_id');
			$userid = NApp::GetParam('user_id');
			if($olduserid && $userid!=$olduserid) {
				NApp::SetPageParam('user_id',$userid);
				$this->ExecuteJs("window.location.href = '".NApp::GetAppBaseUrl()."';");
			}//if($olduserid && $userid!=$olduserid)
			NApp::SetPageParam('user_id',$userid);
			$o_params = new Params($params);
			$o_params->set('target',$target);
			$o_params->set('phash',$windowName);
			if($nonCustom) {
				ModulesProvider::ExecNonCustom($module,$method,$o_params,NULL,(bool)$resetSessionParams);
			} else {
				ModulesProvider::Exec($module,$method,$o_params,NULL,(bool)$resetSessionParams);
			}//if($nonCustom)
			if(strlen(AppConfig::GetValue('app_arequest_js_callback'))) { $this->ExecuteJs(AppConfig::GetValue('app_arequest_js_callback')); }
		} catch(AppException $e) {
			\ErrorHandler::AddError($e);
		}//END try
	}//END public function AjaxRequest
	/**
	 * Generic ajax call for controls
	 *
	 * @param        $window_name
	 * @param        $controlHash
	 * @param        $method
	 * @param string $params
	 * @param string $control
	 * @param int    $viaPost
	 * @return void
	 * @throws \NETopes\Core\AppException
	 * @access public
	 */
	public function ControlAjaxRequest($window_name,$controlHash,$method,$params = NULL,$control = NULL,$viaPost = 0) {
		if(!strlen($window_name)) { $this->ExecuteJs("window.name = '".NApp::GetPhash()."'"); }
		// \NApp::Dlog($controlhash,'$controlhash');
		// \NApp::Dlog($method,'$method');
		// \NApp::Dlog($params,'$params');
		try {
			$olduserid = NApp::GetPageParam('user_id');
			$userid = NApp::GetParam('user_id');
			if($olduserid && $userid!=$olduserid) {
				NApp::SetPageParam('user_id',$userid);
				$this->ExecuteJs("window.location.href = '".NApp::GetAppBaseUrl()."';");
			}//if($olduserid && $userid!=$olduserid)
			NApp::SetPageParam('user_id',$userid);
			if($viaPost) {
				$lcontrol = strlen($control) ? unserialize(GibberishAES::dec($control,$controlHash)) : NULL;
			} else {
				$lcontrol = NApp::GetPageParam($controlHash);
				$lcontrol = strlen($lcontrol)>0 ? unserialize($lcontrol) : NULL;
			}//if($viaPost)
			if(!is_object($lcontrol) || !method_exists($lcontrol,$method)) { throw new AppException('Invalid class or method',E_ERROR,1); }
			$o_params = new Params($params);
			$o_params->set('output',TRUE);
			$o_params->set('phash',$window_name);
			$lcontrol->$method($o_params);
		} catch(\Exception $e) {
			\ErrorHandler::AddError($e);
		}//END try
	}//END public function ControlAjaxRequest
	/**
	 * Ajax call to set language
	 *
	 * @param $selectedLang
	 * @return void
	 * @access public
	 */
	public function SetLanguage(string $selectedLang) {
		$alang = explode('^',$selectedLang);
		$old_lang = NApp::GetLanguageCode();
		NApp::SetPageParam('language_code',$alang[1]);
		NApp::SetPageParam('id_language',$alang[0]);
		$this->ExecuteJs("window.location.href = window.location.href.toString().replace('/{$old_lang}/','/".$alang[1]."/');");
	}//END public function SetLanguage
}//END class Request extends BaseRequest