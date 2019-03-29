<?php
/**
 * NETopes AJAX Request class file
 * This class extends NETopes\Ajax\BaseRequest
 *
 * @package    NETopes\Ajax
 * @author     George Benjamin-Schonberger
 * @copyright  Copyright (c) 2013 - 2019 AdeoTEK Software SRL
 * @license    LICENSE.md
 * @version    1.1.0.0
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
 */
class Request extends BaseRequest {

    /**
     * Generic ajax call
     *
     * @param array|string|null $params
     * @return mixed
     */
    public function AjaxRequest(?array $params) {
        $windowName=get_array_value($params,'phash',NULL,'?is_string');
        $module=get_array_value($params,'module','','is_string');
        $method=get_array_value($params,'method','','is_string');
        $target=get_array_value($params,'targetId',NULL,'?is_string');
        $nonCustom=get_array_value($params,'non_custom',0,'is_integer');
        $resetSessionParams=get_array_value($params,'reset_session_params',0,'is_integer');
        if(!strlen($windowName)) {
            $this->ExecuteJs("window.name = '".NApp::GetPhash()."'");
        }
        $result=NULL;
        try {
            $oldUserId=NApp::GetPageParam(NApp::GetUserIdKey());
            $userId=NApp::GetParam(NApp::GetUserIdKey());
            if($oldUserId && $userId!=$oldUserId) {
                NApp::SetPageParam(NApp::GetUserIdKey(),$userId);
                $this->ExecuteJs("window.location.href = '".NApp::GetAppBaseUrl()."';");
            }//if($oldUserId && $userId!=$oldUserId)
            NApp::SetPageParam(NApp::GetUserIdKey(),$userId);
            $pParams=get_array_value($params,'params',[],'is_array');
            if(array_key_exists('arrayParams',$params) && ($aParams=get_array_value($params,'arrayParams',[],'is_array'))) {
                foreach($aParams as $aParam) {
                    $pParams=array_merge($pParams,$aParam);
                }//END foreach
            }//if(array_key_exists('arrayParams',$params) && ($aParams=get_array_value($params,'arrayParams',[],'is_array')))
            $oParams=new Params($pParams);
            $oParams->set('target',$target);
            $oParams->set('phash',$windowName);
            if($nonCustom) {
                $result=ModulesProvider::ExecNonCustom($module,$method,$oParams,NULL,(bool)$resetSessionParams);
            } else {
                $result=ModulesProvider::Exec($module,$method,$oParams,NULL,(bool)$resetSessionParams);
            }//if($nonCustom)
            if(strlen(AppConfig::GetValue('app_arequest_js_callback'))) {
                $this->ExecuteJs(AppConfig::GetValue('app_arequest_js_callback'));
            }
        } catch(\Error $e) {
            $result=NULL;
            \ErrorHandler::AddError($e);
        } catch(AppException $ae) {
            $result=NULL;
            \ErrorHandler::AddError($ae);
        }//END try
        return $result;
    }//END public function AjaxRequest

    /**
     * Generic ajax call for controls
     *
     * @param array|null $params
     * @return void
     */
    public function ControlAjaxRequest(?array $params) {
        $windowName=get_array_value($params,'phash',NULL,'?is_string');
        $controlHash=get_array_value($params,'control_hash','','is_string');
        $method=get_array_value($params,'method','','is_string');
        $control=get_array_value($params,'control',NULL,'?is_string');
        $viaPost=get_array_value($params,'via_post',0,'is_integer');
        if(!strlen($windowName)) {
            $this->ExecuteJs("window.name = '".NApp::GetPhash()."'");
        }
        // \NApp::Dlog($controlhash,'$controlhash');
        // \NApp::Dlog($method,'$method');
        // \NApp::Dlog($params,'$params');
        try {
            $olduserid=NApp::GetPageParam(NApp::GetUserIdKey());
            $userid=NApp::GetParam(NApp::GetUserIdKey());
            if($olduserid && $userid!=$olduserid) {
                NApp::SetPageParam(NApp::GetUserIdKey(),$userid);
                $this->ExecuteJs("window.location.href = '".NApp::GetAppBaseUrl()."';");
            }//if($olduserid && $userid!=$olduserid)
            NApp::SetPageParam(NApp::GetUserIdKey(),$userid);
            if($viaPost) {
                $lControl=strlen($control) ? unserialize(GibberishAES::dec($control,$controlHash)) : NULL;
            } else {
                $lControl=NApp::GetPageParam($controlHash);
                $lControl=strlen($lControl) ? unserialize($lControl) : NULL;
            }//if($viaPost)
            if(!is_object($lControl) || !method_exists($lControl,$method)) {
                throw new AppException('Invalid class or method',E_ERROR,1);
            }
            $oParams=new Params(get_array_value($params,'params',[],'is_array'));
            $oParams->set('output',TRUE);
            $oParams->set('phash',$windowName);
            $lControl->$method($oParams);
        } catch(\Error $e) {
            \ErrorHandler::AddError($e);
        } catch(AppException $ae) {
            \ErrorHandler::AddError($ae);
        }//END try
    }//END public function ControlAjaxRequest

    /**
     * Ajax call to set language
     *
     * @param array|null $params
     * @return void
     * @throws \NETopes\Core\AppException
     */
    public function SetLanguage(?array $params) {
        $selectedLang=get_array_value($params,'selected_lang','','is_string');
        $alang=explode('^',$selectedLang);
        $old_lang=NApp::GetLanguageCode();
        NApp::SetPageParam('language_code',$alang[1]);
        NApp::SetPageParam('id_language',$alang[0]);
        $this->ExecuteJs("window.location.href = window.location.href.toString().replace('/{$old_lang}/','/".$alang[1]."/');");
    }//END public function SetLanguage
}//END class Request extends BaseRequest