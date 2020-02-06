<?php
/**
 * NETopes AJAX requests class file.
 * The NETopes class used for working with ajax requests.
 *
 * @package    NETopes\Ajax
 * @author     George Benjamin-Schonberger
 * @copyright  Copyright (c) 2013 - 2019 AdeoTEK Software SRL
 * @license    LICENSE.md
 * @version    1.2.1.0
 * @filesource
 */
namespace NETopes\Ajax;
use Error;
use ErrorHandler;
use Exception;
use GibberishAES;
use NApp;
use NETopes\Core\App\ModulesProvider;
use NETopes\Core\AppConfig;
use NETopes\Core\AppException;
use NETopes\Core\AppSession;

/**
 * Class Request
 *
 * @package  NETopes\Ajax
 */
abstract class BaseRequest {
    /**
     * @var    string Session sub-array key for storing AjaxRequest data
     */
    protected $subSession=NULL;
    /**
     * @var    array Custom post params to be sent with the ajax request
     */
    protected $postParams=[];
    /**
     * @var    array List of actions to be executed on the ajax request
     */
    protected $requestActions=[];
    /**
     * @var    string NETopes AJAX request session data ID
     */
    protected $requestId=NULL;
    /**
     * @var    string Control key for securing the request session data
     */
    protected $requestKey='';
    /**
     * @var    string Default AJAX method
     */
    protected $defaultMethod='AjaxRequest';
    /**
     * @var    string Escape string for double quotes character
     */
    protected $jsGetMethodName='NAppRequest.get';
    /**
     * @var    int Session keys case
     */
    public static $sessionKeysCase=CASE_UPPER;
    /**
     * @var    string Separator for ajax request arguments
     */
    public static $requestSeparator=']!r![';
    /**
     * @var    string Separator for function arguments
     */
    public static $argumentSeparator=']!r!a![';
    /**
     * @var    string Separator for ajax actions
     */
    public static $actionSeparator=']!r!s![';
    /**
     * @var    string Escape string for double quotes character
     */
    public static $doubleQuotesEscape='``';
    /**
     * @var    string Javascript getter method marker
     */
    public static $jsGetterMarker='nGet|';
    /**
     * @var    string Javascript eval marker
     */
    public static $jsEvalMarker='nEval|';

    /**
     * String explode function based on standard php explode function.
     * After exploding the string, for each non-numeric element, all leading and trailing spaces will be trimmed.
     *
     * @param string $separator The string used as separator.
     * @param string $string    The string to be exploded.
     * @return  array The exploded and trimmed string as array.
     */
    public static function TrimExplode(string $separator,string $string): array {
        return array_map(function($val) { return is_string($val) && strlen($val) ? trim($val) : $val; },explode($separator,$string));
    }//END public static function TrimExplode

    /**
     * @param array|string|null $input
     * @param string|null       $key
     * @param bool              $rawValues
     * @param bool              $escapeQuotes
     * @return string The javascript object string representation
     */
    public static function ConvertToJsObject($input,?string $key=NULL,bool $rawValues=FALSE,bool $escapeQuotes=FALSE): string {
        $result='';
        if(is_scalar($input)) {
            $key=$key ?: (is_string($input) && strlen($input) ? $input : '');
            if($key) {
                $isArray=FALSE;
                $result="'{$key}': ";
            } else {
                $isArray=TRUE;
            }//if($key)
            if(is_string($input)) {
                $result.=$rawValues ? $input : '\''.addcslashes($input,'\'').'\'';
            } elseif(is_numeric($input)) {
                $result.=$input;
            } elseif(is_bool($input)) {
                $result.=($input ? 'true' : 'false');
            }//if(is_string($input))
            if(!$rawValues) {
                $result=($isArray ? '[ '.$result.' ]' : '{ '.$result.' }');
            }
        } elseif(is_array($input)) {
            if($rawValues) {
                $result='';
                $isArray=FALSE;
                $first=TRUE;
                foreach($input as $k=>$v) {
                    if($first) {
                        $isArray=(bool)(!is_string($k) || !strlen($k));
                        $first=FALSE;
                    }
                    $result.=(strlen($result) ? ', ' : '').static::ConvertToJsObject($v,$k,TRUE,$escapeQuotes);
                }//END foreach
                $result=($isArray ? '[ '.$result.' ]' : '{ '.$result.' }');
            } else {
                array_walk_recursive($input,function($v) {
                    if(is_string($v) && strlen($v)) {
                        $v=addcslashes($v,'\'');
                    }
                    return $v;
                });
                $result=str_replace('"','\'',json_encode($input));
            }
        } else {
            $result='{}';
        }//if(is_scalar($input))
        return ($escapeQuotes ? addcslashes($result,'\'') : $result);
    }//END public static function ConvertToJsObject

    /**
     * Execute a method of the AJAX Request implementing class
     *
     * @param array        $postParams Parameters to be send via post on ajax requests
     * @param string|array $subSession Sub-session key/path
     * @return void
     * @throws \NETopes\Core\AppException
     */
    public static function PrepareAndExecuteRequest(array $postParams=[],$subSession=NULL) {
        $errors='';
        $request=array_key_exists('req',$_POST) ? $_POST['req'] : NULL;
        if(!$request) {
            $errors.='Empty Request!';
            $errors.="\nURL: ".(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '-');
            $errors.="\n".print_r($_POST,1);
        }//if(!$request)
        $params=NULL;
        $serializeMode=NULL;
        $sessionId=NULL;
        $requestId=NULL;
        $classFile=NULL;
        $class=NULL;
        $function=NULL;
        $requests=NULL;
        if(!$errors) {
            $serializeMode=array_key_exists('serializemode',$_POST) ? $_POST['serializemode'] : 'json';
            /* Start session and set ID to the expected paf session */
            [$params,$sessionId,$requestId]=explode(static::$requestSeparator,$request);
            /* Validate this request */
            $spath=[
                NApp::$currentNamespace,
                AppSession::ConvertToSessionCase(AppConfig::GetValue('app_session_key'),static::$sessionKeysCase),
                AppSession::ConvertToSessionCase('NAPP_AREQUEST',static::$sessionKeysCase),
            ];
            $requests=AppSession::GetGlobalParam(AppSession::ConvertToSessionCase('AREQUESTS',static::$sessionKeysCase),FALSE,$spath,FALSE);
            if(GibberishAES::dec(rawurldecode($sessionId),AppConfig::GetValue('app_encryption_key'))!=session_id() || !is_array($requests)) {
                $errors.='Invalid Request!';
            } elseif(!in_array(AppSession::ConvertToSessionCase($requestId,static::$sessionKeysCase),array_keys($requests))) {
                $errors.='Invalid Request Data!';
            }//if(\GibberishAES::dec(rawurldecode($sessionId),AppConfig::GetValue('app_encryption_key'))!=session_id() || !is_array($requests))
        }//if(!$errors)
        if(!$errors) {
            /* Get function name and process file */
            $REQ=$requests[AppSession::ConvertToSessionCase($requestId,static::$sessionKeysCase)];
            $method=$REQ[AppSession::ConvertToSessionCase('METHOD',static::$sessionKeysCase)];
            $lkey=AppSession::ConvertToSessionCase('CLASS',static::$sessionKeysCase);
            if(array_key_exists($lkey,$REQ) && strlen($REQ[$lkey])) {
                $isModule=(array_key_exists(AppSession::ConvertToSessionCase('IS_MODULE',static::$sessionKeysCase),$REQ) ? (bool)$REQ[AppSession::ConvertToSessionCase('METHOD',static::$sessionKeysCase)] : FALSE);
                $targetClass=$REQ[$lkey];
                $class=(is_subclass_of($targetClass,self::class) ? $targetClass : AppConfig::GetValue('ajax_class_name'));
            } else {
                $isModule=FALSE;
                $class=$targetClass=AppConfig::GetValue('ajax_class_name');
            }//if(array_key_exists($lkey,$REQ) && strlen($REQ[$lkey]))
            if(!class_exists($class)) {
                $errors='Class ['.$class.'] not found!';
            }//if(!class_exists($class))
            if(!$errors) {
                NApp::SetAjaxRequest(new $class($subSession,$postParams));
                /* Execute the requested function */
                try {
                    NApp::Ajax()->ExecuteRequest($targetClass,$method,$params,$serializeMode,$isModule);
                } catch(Error $er) {
                    NApp::Elog($er);
                    ErrorHandler::AddError($er);
                } catch(AppException $e) {
                    NApp::Elog($e);
                    ErrorHandler::AddError($e);
                } catch(Exception $e) {
                    NApp::Elog($e);
                    ErrorHandler::AddError($e);
                }//END try
                NApp::SessionCommit(FALSE,TRUE);
                if(NApp::Ajax()->HasActions()) {
                    echo NApp::Ajax()->Send();
                }
                $content=NApp::GetOutputBufferContent();
            } else {
                $content=$errors;
            }//if(!$errors)
            echo $content;
        } else {
            NApp::Log2File(['type'=>'error','message'=>$errors,'no'=>-1,'file'=>__FILE__,'line'=>__LINE__],NApp::$appPath.AppConfig::GetValue('logs_path').'/'.AppConfig::GetValue('errors_log_file'));
            // vprint($errors);
            echo static::$actionSeparator.'window.location.href = "'.NApp::GetAppBaseUrl().'";';
        }//if(!$errors)
    }//END public static function PrepareAndExecuteRequest

    /**
     * AJAX Request constructor function
     *
     * @param string     $subSession Sub-session key/path
     * @param array|null $postParams
     * @throws \NETopes\Core\AppException
     */
    public final function __construct($subSession=NULL,?array $postParams=[]) {
        if(is_string($subSession) && strlen($subSession)) {
            $this->subSession=[$subSession,AppConfig::GetValue('app_session_key')];
        } elseif(is_array($subSession) && count($subSession)) {
            $subSession[]=AppConfig::GetValue('app_session_key');
            $this->subSession=$subSession;
        } else {
            $this->subSession=AppConfig::GetValue('app_session_key');
        }//if(is_string($subSession) && strlen($subSession))
        $this->Init();
        if(is_array($postParams) && count($postParams)) {
            $this->SetPostParams($postParams);
        }
    }//END public final function __construct

    /**
     * Initialize AJAX Request session data (generate session data id) if is not initialized
     *
     * @return void
     * @throws \NETopes\Core\AppException
     */
    protected function Init() {
        $appReqId=AppSession::GetGlobalParam(AppSession::ConvertToSessionCase('NAPP_RID',static::$sessionKeysCase),FALSE,$this->subSession,FALSE);
        if(strlen($appReqId)) {
            $this->requestId=$appReqId;
        } else {
            $this->requestId=AppSession::GetNewUID();
            AppSession::SetGlobalParam(AppSession::ConvertToSessionCase('NAPP_RID',static::$sessionKeysCase),$this->requestId,FALSE,$this->subSession,FALSE);
        }//if(strlen($appReqId))
        $this->StartSecureHttp();
    }//END protected function Init

    /**
     * @return string
     * @throws \NETopes\Core\AppException
     */
    public function GetEncryptedSessionId(): string {
        return rawurlencode(GibberishAES::enc(session_id(),AppConfig::GetValue('app_encryption_key')));
    }//END public function GetEncryptedSessionId

    /**
     * @param string      $method
     * @param string|null $class
     * @return string
     * @throws \NETopes\Core\AppException
     */
    public function GenerateNewRequestUid(string $method,?string $class=NULL): string {
        $requestId=AppSession::GetNewUID($method.$this->requestId,'sha256',TRUE);
        if(strlen($class)) {
            $class=strlen($class) ? $class : AppConfig::GetValue('ajax_class_name');
            $reqSessionParams=[
                AppSession::ConvertToSessionCase('METHOD',static::$sessionKeysCase)=>$method,
                AppSession::ConvertToSessionCase('CLASS',static::$sessionKeysCase)=>$class,
            ];
        } else {
            $reqSessionParams=[AppSession::ConvertToSessionCase('METHOD',static::$sessionKeysCase)=>$method];
        }//if(strlen($class))
        $subSession=is_array($this->subSession) ? $this->subSession : [$this->subSession];
        $subSession[]=AppSession::ConvertToSessionCase('NAPP_AREQUEST',static::$sessionKeysCase);
        $subSession[]=AppSession::ConvertToSessionCase('AREQUESTS',static::$sessionKeysCase);
        AppSession::SetGlobalParam(AppSession::ConvertToSessionCase($requestId,static::$sessionKeysCase),$reqSessionParams,FALSE,$subSession,FALSE);
        return $requestId;
    }//END public function GenerateNewRequestUid

    /**
     * Get AJAX Request javascript initialize script
     *
     * @param string $jsRootUrl
     * @return string
     * @throws \NETopes\Core\AppException
     */
    public function GetJsScripts(string $jsRootUrl): string {
        $ajaxTargetScript=AppConfig::GetValue('app_ajax_target');
        $appBaseUrl=NApp::$appBaseUrl;
        $js=<<<HTML
        <script type="text/javascript">
            const NAPP_TARGET = '{$appBaseUrl}/{$ajaxTargetScript}';
            const NAPP_UID = '{$this->requestKey}';
        </script>
        <script type="text/javascript" src="{$jsRootUrl}/ajax-request.min.js?v=1911081"></script>
HTML;
        return $js;
    }//END public function GetJsScripts

    /**
     * @param bool $withOutput
     * @return string
     * @throws \NETopes\Core\AppException
     */
    public function JsInit(bool $withOutput=TRUE) {
        $js='<script type="text/javascript">'."\n";
        $js.="\t".'var NAPP_PHASH="'.NApp::GetPhash().'";'."\n";
        $js.="\t".'var NAPP_TARGET="'.NApp::$appBaseUrl.'/'.AppConfig::GetValue('app_ajax_target').'";'."\n";
        $js.="\t".'var NAPP_UID="'.$this->requestKey.'";'."\n";
        $js.="\t".'var NAPP_JS_PATH="'.NApp::$appBaseUrl.AppConfig::GetValue('app_js_path').'";'."\n";
        $js.='</script>'."\n";
        $js.='<script type="text/javascript" src="'.NApp::$appBaseUrl.AppConfig::GetValue('app_js_path').'/gibberish-aes.min.js?v=1411031"></script>'."\n";
        $js.='<script type="text/javascript" src="'.NApp::$appBaseUrl.AppConfig::GetValue('app_js_path').'/ajax-request.min.js?v=1911081"></script>'."\n";
        if(NApp::GetDebuggerState()) {
            $dbgScripts=NApp::$debugger->GetScripts();
            if(is_array($dbgScripts) && count($dbgScripts)) {
                foreach($dbgScripts as $dsk=>$ds) {
                    $js.='<script type="text/javascript" src="'.NApp::$appBaseUrl.AppConfig::GetValue('app_js_path').'/debug'.$ds.'?v=1712011"></script>'."\n";
                }//END foreach
            }//if(is_array($dbgScripts) && count($dbgScripts))
        }//if(NApp::GetDebuggerState())
        if($withOutput===TRUE) {
            echo $js;
        }
        return $js;
    }//END public function JsInit

    /**
     * Clear AjaxRequest session data and re-initialize it
     *
     * @return void
     * @throws \NETopes\Core\AppException
     */
    protected function ClearState() {
        AppSession::UnsetGlobalParam(AppSession::ConvertToSessionCase('NAPP_RID',static::$sessionKeysCase),FALSE,$this->subSession,FALSE);
        AppSession::UnsetGlobalParam(AppSession::ConvertToSessionCase('NAPP_UID',static::$sessionKeysCase),FALSE,$this->subSession,FALSE);
        $this->requestId=$this->requestKey=NULL;
        $this->Init();
    }//END protected function ClearState

    /**
     * @throws \NETopes\Core\AppException
     */
    protected function StartSecureHttp() {
        if(!AppConfig::GetValue('app_secure_http')) {
            return;
        }
        $this->requestKey=AppSession::GetGlobalParam(AppSession::ConvertToSessionCase('NAPP_UID',static::$sessionKeysCase),FALSE,$this->subSession,FALSE);
        if(!strlen($this->requestKey)) {
            $this->requestKey=AppSession::GetNewUID(AppConfig::GetValue('app_session_key'),'sha256');
            AppSession::SetGlobalParam(AppSession::ConvertToSessionCase('NAPP_UID',static::$sessionKeysCase),$this->requestKey,FALSE,$this->subSession,FALSE);
        }//if(!strlen($this->requestKey))
    }//END protected function StartSecureHttp

    /**
     * @throws \NETopes\Core\AppException
     */
    protected function ClearSecureHttp() {
        AppSession::UnsetGlobalParam(AppSession::ConvertToSessionCase('NAPP_UID',static::$sessionKeysCase),FALSE,$this->subSession,FALSE);
        $this->requestKey=NULL;
    }//END protected function ClearSecureHttp

    /**
     * Sets params to be send via post on the ajax request
     *
     * @param array $params Key-value array of parameters to be send via post
     * @return void
     */
    public function SetPostParams(array $params) {
        $this->postParams=$params;
    }//END public function SetPostParams

    /**
     * Sets params to be send via post on the ajax request
     *
     * @param string $targetId
     * @return bool
     */
    public function SetDynamicTarget(string $targetId): bool {
        if(!strlen(trim($targetId))) {
            return FALSE;
        }
        header('HTMLTargetId: '.$targetId);
        return TRUE;
    }//END public function SetPostParams

    /**
     * @return bool
     */
    public function HasActions() {
        return (bool)count($this->requestActions);
    }//END public function HasActions

    /**
     * @param $action
     */
    protected function AddAction($action) {
        $this->requestActions[]=$action;
    }//protected function AddAction

    /**
     * @return null|string
     */
    public function GetActions() {
        if(!$this->HasActions()) {
            return NULL;
        }
        $actions=implode(';',array_map(function($value) { return trim($value,';'); },$this->requestActions)).';';
        return static::$actionSeparator.$actions.static::$actionSeparator;
    }//END public function GetActions
    /*** NETopes js response functions ***/
    /**
     * Execute javascript code
     *
     * @param $jsScript
     */
    public function ExecuteJs($jsScript) {
        if(is_string($jsScript) && strlen($jsScript)) {
            $this->AddAction($jsScript);
        }
    }//END public function ExecuteJs

    /**
     * Redirect the browser to a URL
     *
     * @param $url
     */
    public function Redirect($url) {
        $this->AddAction("window.location.href = '{$url}'");
    }//END public function Redirect

    /**
     * Reloads current page
     */
    public function Refresh() {
        $this->AddAction("window.location.reload()");
    }//END public function Refresh

    /**
     * Display a javascript alert
     *
     * @param $text
     */
    public function Alert($text) {
        $this->AddAction("alert('".addcslashes($text,'\'')."')");
    }//END public function Alert

    /**
     * Submit a form on the page
     *
     * @param $form
     */
    public function Submit($form) {
        $this->AddAction("document.forms['{$form}'].submit()");
    }//END public function Submit

    /**
     * Used for placing complex/long text into an element (text or html)
     *
     * @param string $content The content to be inserted in the element
     * @param string $target  The id of the element
     * @return void
     */
    public function InnerHtml($content,$target) {
        $action='';
        $targetProperty='';
        $target_arr=static::TrimExplode(',',$target);
        $target=$target_arr[0];
        if(count($target_arr)>1) {
            $action=$target_arr[1];
        }
        $target_arr2=static::TrimExplode(':',$target);
        $targetId=$target_arr2[0];
        if(count($target_arr2)>1) {
            $targetProperty=$target_arr2[1];
        }
        if(!$action) {
            $action='r';
        }
        if(!$targetProperty) {
            $targetProperty='innerHTML';
        }
        $action="NAppRequest.put(decodeURIComponent('".rawurlencode($content)."'),'{$targetId}','{$action}','{$targetProperty}')";
        $this->AddAction($action);
    }//END public function InnerHtml

    /**
     * Hides an element (sets css display property to none)
     *
     * @param string $element Id of element to be hidden
     * @return void
     */
    public function Hide($element) {
        $this->AddAction("NAppRequest.put('none','{$element}','r','style.display')");
    }//END public function Hide

    /**
     * Shows an element (sets css display property to '')
     *
     * @param string $element Id of element to be shown
     * @return void
     */
    public function Show($element) {
        $this->AddAction("NAppRequest.put('','{$element}','r','style.display')");
    }//END public function Show

    /**
     * Set style for an element
     *
     * @param string $element     Id of element to be set
     * @param string $styleString Style to be set
     * @return void
     */
    public function Style($element,$styleString) {
        $this->AddAction("NAppRequest.setStyle('{$element}','{$styleString}')");
    }//END public function Style

    /**
     * Return response actions to javascript for execution and clears actions property
     *
     * @return string The string enumeration containing all actions to be executed
     */
    public function Send() {
        $actions=$this->GetActions();
        $this->requestActions=[];
        return $actions;
    }//END public function Send

    /**
     * Transforms the post params array into a string to be posted by the javascript method
     *
     * @param array|null $params An array of parameters to be sent with the request
     * @return string The post params as a string
     */
    protected function PreparePostParams(?array $params=NULL): string {
        $result='';
        if(is_array($this->postParams) && count($this->postParams)) {
            foreach($this->postParams as $k=>$v) {
                $result.='&'.$k.'='.rawurlencode($v);
            }
        }//if(is_array($this->postParams) && count($this->postParams))
        if(is_array($params) && count($params)) {
            foreach($params as $k=>$v) {
                $result.='&'.$k.'='.rawurlencode($v);
            }
        }//if(is_array($params) && count($params))
        return $result;
    }//END protected function PreparePostParams

    /**
     * @param $confirm
     * @param $requestId
     * @return mixed|string
     * @throws \NETopes\Core\AppException
     */
    protected function PrepareConfirm($confirm,string $requestId) {
        if(is_string($confirm)) {
            $cTxt=$confirm;
            $cType='js';
        } else {
            $cTxt=get_array_value($confirm,'text','','is_string');
            $cType=get_array_value($confirm,'type','js','is_notempty_string');
        }//if(is_string($confirm))
        if(!strlen($cTxt)) {
            return 'undefined';
        }
        switch($cType) {
            case 'jqui':
                $confirmStr=str_replace('"','\'',json_encode([
                    'type'=>'jqui',
                    'message'=>rawurlencode($cTxt),
                    'title'=>get_array_value($confirm,'title','','is_string'),
                    'ok'=>get_array_value($confirm,'ok','','is_string'),
                    'cancel'=>get_array_value($confirm,'cancel','','is_string'),
                ]));
                if(AppConfig::GetValue('app_params_encrypt')) {
                    $confirmStr='\''.GibberishAES::enc($confirmStr,$requestId).'\'';
                }
                break;
            case 'js':
            default:
                if(AppConfig::GetValue('app_params_encrypt')) {
                    $confirmStr=str_replace('"','\'',json_encode(['type'=>'std','message'=>rawurlencode($cTxt)]));
                    $confirmStr='\''.GibberishAES::enc($confirmStr,$requestId).'\'';
                } else {
                    $confirmStr='\''.rawurlencode($cTxt).'\'';
                }//if(AppConfig::GetValue('app_params_encrypt'))
                break;
        }//END switch
        return $confirmStr;
    }//END protected function PrepareConfirm

    /**
     * @param array|string|null $params
     * @return string The javascript object string representation
     */
    public static function PrepareJsPassTroughParams($params): string {
        if(is_array($params)) {
            $result='';
            foreach($params as $k=>$v) {
                $result.=(strlen($result) ? ', ' : '').'\''.(!is_integer($k) ? $k : $v).'\': '.$v;
            }//END foreach
            return '{ '.$result.' }';
        }//if(is_array($params))
        if(is_string($params) && strlen($params)) {
            return '{ '.trim($params,'{}[]').' }';
        }//if(is_string($params) && strlen($params))
        return '{}';
    }//END public static function PrepareJsPassTroughParams

    /**
     * @param string            $method
     * @param string            $params
     * @param string|null       $targetId
     * @param string|null       $action
     * @param string|null       $property
     * @param int|bool|null     $loader
     * @param null              $confirm
     * @param int|bool|null     $async
     * @param string|null       $callback
     * @param array|string|null $jiParams
     * @param array|string|null $eParams
     * @param int|null          $interval
     * @param int|bool|null     $triggerOnInitEvent
     * @param array|null        $postParams
     * @param array|null        $jsScripts
     * @param string|null       $class
     * @param string|null       $eventsSufix
     * @return string|null
     * @throws \NETopes\Core\AppException
     */
    public function PrepareRequestCommand(string $method,string $params,?string $targetId=NULL,?string $action=NULL,?string $property=NULL,$loader=TRUE,$confirm=NULL,$async=TRUE,?string $callback=NULL,$jiParams=NULL,$eParams=NULL,?int $interval=NULL,$triggerOnInitEvent=TRUE,?array $postParams=NULL,?array $jsScripts=NULL,?string $class=NULL,?string $eventsSufix=NULL): ?string {
        if(!strlen($method)) {
            return NULL;
        }
        $encryptParams=AppConfig::GetValue('app_params_encrypt');
        $requestUid=$this->GenerateNewRequestUid($method,$class);
        $sessionId=$this->GetEncryptedSessionId();
        $postParams=$this->PreparePostParams($postParams);
        $pConfirm=$this->PrepareConfirm($confirm,$requestUid);
        $jsCallback=strlen($callback) ? ($encryptParams ? '\''.GibberishAES::enc($callback,$requestUid).'\'' : $callback) : 'false';
        $jiParamsString=static::PrepareJsPassTroughParams($jiParams);
        $eParamsString=static::ConvertToJsObject($eParams);
        $jsScriptsString=static::ConvertToJsObject($jsScripts);
        if($encryptParams && $requestUid) {
            $params='\''.GibberishAES::enc($params,$requestUid).'\'';
        }
        $action=strlen($action) ? $action : 'r';
        $property=isset($property) ? $property : 'innerHTML';
        if(isset($interval) && $interval>0) {
            $command="NAppRequest.executeRepeated({$interval},'".$targetId ?? ''."','{$action}','{$property}',{$params},".((int)$encryptParams).",".((int)$loader).",".((int)$async).",".((int)$triggerOnInitEvent).",{$pConfirm},{$jiParamsString},{$eParamsString},{$jsCallback},'{$postParams}','{$sessionId}','{$requestUid}',{$jsScriptsString},undefined);";
        } else {
            $command="NAppRequest.execute('".($targetId ?? '')."','{$action}','{$property}',{$params},".((int)$encryptParams).",".((int)$loader).",".((int)$async).",".((int)$triggerOnInitEvent).",{$pConfirm},{$jiParamsString},{$eParamsString},{$jsCallback},'{$postParams}','{$sessionId}','{$requestUid}',{$jsScriptsString},undefined,'{$eventsSufix}');";
        }//if(isset($interval) &&$interval>0)
        return $command;
    }//END public function PrepareRequestCommand

    /**
     * @param string|null $params
     * @return bool
     */
    protected function ReplaceDynamicReferences(?string &$params): bool {
        if(is_null($params)) {
            return FALSE;
        }
        $dynamicParams=[];
        preg_match_all('/{'.addcslashes(static::$jsGetterMarker,'|').'[^}]*}/i',$params,$dynamicParams);
        $result=isset($dynamicParams[0]) && is_array($dynamicParams[0]) && count($dynamicParams[0]);
        if($result) {
            foreach($dynamicParams[0] as $dParam) {
                if(strpos($params,$dParam)===FALSE) {
                    continue;
                }
                $dParamArray=explode(':',trim(str_replace('{'.static::$jsGetterMarker,'',$dParam),'{}'));
                $dpValue='\''.trim($dParamArray[0]).'\'';
                if(count($dParamArray)>1) {
                    $dpValue.=',\''.trim($dParamArray[1]).'\'';
                }
                if(count($dParamArray)>2) {
                    $dpValue.=',\''.trim($dParamArray[2]).'\'';
                }
                $dParamValue=$this->jsGetMethodName.'('.$dpValue.')';
                $params=str_replace('\''.$dParam.'\'',$dParamValue,$params);
            }//END foreach
        }//if($result)
        unset($dParamArray);
        preg_match_all('/{'.addcslashes(static::$jsEvalMarker,'|').'[^}]*}/i',$params,$dynamicParams);
        $result=$result || (isset($dynamicParams[0]) && is_array($dynamicParams[0]) && count($dynamicParams[0]));
        if($result) {
            foreach($dynamicParams[0] as $dParam) {
                if(strpos($params,$dParam)===FALSE) {
                    continue;
                }
                $dpValue=str_replace('\\\'','\'',trim(str_replace('{'.static::$jsEvalMarker,'',$dParam),'{}\''));
                $params=str_replace('\''.$dParam.'\'',$dpValue,$params);
            }//END foreach
        }//if($result)
        return $result;
    }//END protected function ReplaceDynamicReferences

    /**
     * @param string|null $params
     * @return string|null
     */
    protected function ProcessParamsString(?string $params): ?string {
        if(substr($params,0,1)=='\'') {
            $params=str_replace('"',static::$doubleQuotesEscape,$params);
        } elseif(substr($params,0,1)=='"') {
            $params=addcslashes($params,'\'');
            $params=str_replace('"','\'',$params);
        } else {
            $params=str_replace('"',static::$doubleQuotesEscape,$params);
        }
        $params=addcslashes($params,'\\');
        return $params;
    }//END protected function ProcessParamsString

    /**
     * @param array|null $params
     * @param bool       $doNotEscape
     * @return string|null
     */
    protected function ProcessParamsArray(?array $params,bool $doNotEscape=FALSE): ?string {
        if(is_null($params)) {
            return NULL;
        }
        if(array_key_exists('array_params',$params)) {
            $params['arrayParams']=$params['array_params'];
            unset($params['array_params']);
        }//if(array_key_exists('array_params',$params))
        array_walk_recursive(
            $params,
            function(&$value) {
                if(is_string($value) && strlen($value)) {
                    $value=str_replace('"',static::$doubleQuotesEscape,$value);
                    // $value=addcslashes($value,'\'');
                }//if(is_string($value) && strlen($value))
            }
        );
        $result=str_replace('"','\'',json_encode($params));
        if($doNotEscape) {
            $result=str_replace('\\\\','\\',$result);
        }
        return $result;
    }//END protected function ProcessParamsArray

    /**
     * @param array|null $params
     * @param bool       $doNotEscape
     * @return string|null
     */
    public function GetCommand(?array $params,bool $doNotEscape=FALSE): ?string {
        return $this->ProcessParamsArray($params,$doNotEscape);
    }//END public function GetCommand

    /**
     * Generate javascript for ajax request
     *
     * @param string      $params
     * @param string|null $targetId
     * @param array|null  $jsPassTroughParams
     * @param bool        $loader
     * @param null        $confirm
     * @param bool        $async
     * @param string|null $callback
     * @param null        $eParams
     * @param bool        $triggerOnInitEvent
     * @param string|null $method
     * @param int|null    $interval
     * @param array|null  $postParams
     * @param array|null  $jsScripts
     * @param string|null $class
     * @param string|null $eventsSufix
     * @return string|null
     * @throws \NETopes\Core\AppException
     */
    public function Prepare(string $params,?string $targetId=NULL,$jsPassTroughParams=NULL,$loader=TRUE,$confirm=NULL,$async=TRUE,?string $callback=NULL,$eParams=NULL,$triggerOnInitEvent=TRUE,?string $method=NULL,?int $interval=NULL,?array $postParams=NULL,?array $jsScripts=NULL,?string $class=NULL,?string $eventsSufix=NULL): ?string {
        $params=$this->ProcessParamsString($params);
        $this->ReplaceDynamicReferences($params);
        $params='{ \'pHash\': '.(AppConfig::GetValue('app_use_window_name') ? 'window.name' : '').', \'targetId\': \''.$targetId.'\', '.trim($params,'{ ');
        return $this->PrepareRequestCommand($method ?? $this->defaultMethod,$params,$targetId,NULL,NULL,$loader,$confirm,$async,$callback,$jsPassTroughParams,$eParams,$interval,$triggerOnInitEvent,$postParams,$jsScripts,$class,$eventsSufix);
    }//END public function Prepare

    /**
     * Generate javascript for AjaxRequest request
     *
     * @param array $params      Parameters array
     * @param array $extraParams Extra parameters array
     * @return string|null
     * @throws \NETopes\Core\AppException
     */
    public function PrepareAjaxRequest(array $params,array $extraParams=[]): ?string {
        $paramsString=$this->ProcessParamsArray($params);
        $this->ReplaceDynamicReferences($paramsString);
        $targetId=get_array_value($extraParams,'target_id',NULL,'?is_string');
        $paramsString='{ \'pHash\': '.(AppConfig::GetValue('app_use_window_name') ? 'window.name' : '').', \'targetId\': \''.$targetId.'\', '.trim($paramsString,'{ ');
        return $this->PrepareRequestCommand(
            get_array_value($extraParams,'method',$this->defaultMethod,'is_notempty_string'),
            $paramsString,
            $targetId,
            NULL,
            NULL,
            get_array_value($extraParams,'loader',TRUE,'bool'),
            get_array_value($extraParams,'confirm',NULL),
            get_array_value($extraParams,'async',TRUE,'bool'),
            get_array_value($extraParams,'callback',NULL,'?is_string'),
            get_array_value($extraParams,'js_pass_trough_params',NULL,'?is_array'),
            get_array_value($extraParams,'e_params',NULL,'?is_array'),
            get_array_value($extraParams,'interval',NULL,'?is_integer'),
            get_array_value($extraParams,'trigger_on_init_event',TRUE,'bool'),
            get_array_value($extraParams,'post_params',NULL,'?is_array'),
            get_array_value($extraParams,'js_scripts',NULL,'?is_array'),
            NULL,
            get_array_value($extraParams,'events_sufix',NULL,'?is_string')
        );
    }//END public function PrepareAjaxRequest

    /**
     * Generate javascript call for repeated ajax request
     *
     * @param int         $interval
     * @param string      $params
     * @param string|null $targetId
     * @param array|null  $jsPassTroughParams
     * @param bool        $loader
     * @param null        $confirm
     * @param bool        $async
     * @param string|null $callback
     * @param null        $eParams
     * @param bool        $triggerOnInitEvent
     * @param string|null $method
     * @param array|null  $postParams
     * @param array|null  $jsScripts
     * @param string|null $class
     * @return string|null
     * @throws \NETopes\Core\AppException
     */
    public function PrepareRepeated(int $interval,string $params,?string $targetId=NULL,$jsPassTroughParams=NULL,$loader=TRUE,$confirm=NULL,$async=TRUE,?string $callback=NULL,$eParams=NULL,$triggerOnInitEvent=TRUE,?string $method=NULL,?array $postParams=NULL,?array $jsScripts=NULL,?string $class=NULL): ?string {
        return $this->Prepare($params,$targetId,$jsPassTroughParams,$loader,$confirm,$async,$callback,$eParams,$triggerOnInitEvent,$method,$interval,$postParams,$jsScripts,$class);
    }//END public function PrepareRepeated

    /**
     * Generate javascript call for ajax request with javascript event
     *
     * @param string      $params
     * @param string|null $eventsSufix
     * @param string|null $targetId
     * @param array|null  $jsPassTroughParams
     * @param bool        $loader
     * @param null        $confirm
     * @param bool        $async
     * @param string|null $callback
     * @param null        $eParams
     * @param bool        $triggerOnInitEvent
     * @param string|null $method
     * @param array|null  $postParams
     * @param array|null  $jsScripts
     * @param string|null $class
     * @return string|null
     * @throws \NETopes\Core\AppException
     */
    public function PrepareWithEvent(string $params,?string $eventsSufix=NULL,?string $targetId=NULL,$jsPassTroughParams=NULL,$loader=TRUE,$confirm=NULL,$async=TRUE,?string $callback=NULL,$eParams=NULL,$triggerOnInitEvent=TRUE,?string $method=NULL,?array $postParams=NULL,?array $jsScripts=NULL,?string $class=NULL): ?string {
        return $this->Prepare($params,$targetId,$jsPassTroughParams,$loader,$confirm,$async,$callback,$eParams,$triggerOnInitEvent,$method,NULL,$postParams,$jsScripts,$class,$eventsSufix);
    }//END public function PrepareWithEvent

    /**
     * Adds a new paf run action to the queue
     *
     * @param string      $params
     * @param string|null $targetId
     * @param array|null  $jsPassTroughParams
     * @param bool        $loader
     * @param null        $confirm
     * @param bool        $async
     * @param string|null $callback
     * @param null        $eParams
     * @param bool        $triggerOnInitEvent
     * @param string|null $method
     * @param array|null  $postParams
     * @param array|null  $jsScripts
     * @param string|null $class
     * @param string|null $eventsSufix
     * @return void
     * @throws \NETopes\Core\AppException
     */
    public function Execute(string $params,?string $targetId=NULL,$jsPassTroughParams=NULL,$loader=TRUE,$confirm=NULL,$async=TRUE,?string $callback=NULL,$eParams=NULL,$triggerOnInitEvent=TRUE,?string $method=NULL,?array $postParams=NULL,?array $jsScripts=NULL,?string $class=NULL,?string $eventsSufix=NULL): void {
        $this->AddAction($this->Prepare($params,$targetId,$jsPassTroughParams,$loader,$confirm,$async,$callback,$eParams,$triggerOnInitEvent,$method,NULL,$postParams,$jsScripts,$class,$eventsSufix));
    }//END public function Execute

    /**
     * Adds a new paf run action to the queue
     *
     * @param string      $params
     * @param string|null $targetId
     * @param array|null  $jsPassTroughParams
     * @param bool        $loader
     * @param null        $confirm
     * @param bool        $async
     * @param string|null $callback
     * @param null        $eParams
     * @param bool        $triggerOnInitEvent
     * @param string|null $method
     * @param array|null  $postParams
     * @param array|null  $jsScripts
     * @param string|null $class
     * @param string|null $eventsSufix
     * @return void
     * @throws \NETopes\Core\AppException
     */
    public function ExecuteWithEvent(string $params,?string $eventsSufix=NULL,?string $targetId=NULL,$jsPassTroughParams=NULL,$loader=TRUE,$confirm=NULL,$async=TRUE,?string $callback=NULL,$eParams=NULL,$triggerOnInitEvent=TRUE,?string $method=NULL,?array $postParams=NULL,?array $jsScripts=NULL,?string $class=NULL): void {
        $this->Execute($params,$targetId,$jsPassTroughParams,$loader,$confirm,$async,$callback,$eParams,$triggerOnInitEvent,$method,$postParams,$jsScripts,$class,$eventsSufix);
    }//END public function ExecuteWithEvent

    /**
     * Generate and execute javascript for AjaxRequest request
     *
     * @param array $params      Parameters array
     * @param array $extraParams Extra parameters array
     * @return void
     * @throws \NETopes\Core\AppException
     */
    public function ExecuteAjaxRequest(array $params,array $extraParams=[]): void {
        $this->AddAction($this->PrepareAjaxRequest($params,$extraParams));
    }//END public function ExecuteAjaxRequest

    /**
     * @param string      $class
     * @param string      $method
     * @param string      $params
     * @param string|null $serializeMode
     * @param bool        $isModule
     * @return mixed
     * @throws \NETopes\Core\AppException
     */
    public function ExecuteRequest(string $class,string $method,string $params,?string $serializeMode=NULL,bool $isModule=FALSE) {
        if(!class_exists($class)) {
            echo "AjaxRequest ERROR: [{$class}] Not validated.";
            return NULL;
        }
        if($isModule && !ModulesProvider::ModuleMethodExists($class,$method)) {
            echo "AjaxRequest ERROR: [{$class}::{$method}] Not validated.";
            return NULL;
        } elseif(!$isModule && !method_exists($class,$method)) {
            echo "AjaxRequest ERROR: [{$class}::{$method}] Not validated.";
            return NULL;
        }
        //decode encrypted HTTP data if needed
        $params=utf8_decode(rawurldecode($params));
        if(AppConfig::GetValue('app_secure_http')) {
            if(!$this->requestKey) {
                echo "AjaxRequest ERROR: [{$class}::{$method}] Not validated.";
                return NULL;
            }
            $params=GibberishAES::dec($params,$this->requestKey);
        }//if(AppConfig::GetValue('app_secure_http'))
        if($serializeMode=='php') {
            //limited to 100 arguments for DNOS attack protection
            $params=explode(static::$argumentSeparator,$params,100);
            for($i=0; $i<count($params); $i++) {
                $params[$i]=$this->Utf8UnSerialize(rawurldecode($params[$i]));
                $params[$i]=str_replace(static::$argumentSeparator,'',$params[$i]);
            }//END for
        } else {
            $params=json_decode($params,TRUE);
        }//if($serializeMode=='php')
        if(trim($class,'\\')==trim(get_called_class(),'\\')) {
            if($serializeMode=='php') {
                return call_user_func_array([$this,$method],$params);
            }
            return $this->$method($params);
        } elseif($isModule) {
            return ModulesProvider::Exec($class,$method,$params);
        }//if(trim($class,'\\')==trim(get_called_class(),'\\'))
        $instance=new $class();
        if($serializeMode=='php') {
            return call_user_func_array([$instance,$method],$params);
        }
        return $instance->$method($params);
    }//END public function ExecuteRequest

    /**
     * @param $str
     * @return array|null|string|string[]
     */
    protected function Utf8UnSerialize($str) {
        $rsearch=['^[!]^','^[^]^'];
        $rreplace=['|','~'];
        if(strpos(trim($str,'|'),'|')===FALSE && strpos(trim($str,'~'),'~')===FALSE) {
            return $this->ArrayNormalize(str_replace($rsearch,$rreplace,unserialize($str)));
        }
        $ret=[];
        foreach(explode('~',$str) as $arg) {
            $sarg=explode('|',$arg);
            if(count($sarg)>1) {
                $rval=$this->ArrayNormalize(str_replace($rsearch,$rreplace,unserialize($sarg[1])));
                $rkey=$this->ArrayNormalize(str_replace($rsearch,$rreplace,unserialize($sarg[0])),$rval);
                if(is_array($rkey)) {
                    $ret=array_merge_recursive($ret,$rkey);
                } else {
                    $ret[$rkey]=$rval;
                }//if(is_array($rkey))
            } else {
                $tmpval=$this->ArrayNormalize(str_replace($rsearch,$rreplace,unserialize($sarg[0])));
                if(is_array($tmpval) && count($tmpval)) {
                    foreach($tmpval as $k=>$v) {
                        $ret[$k]=$v;
                    }
                } else {
                    $ret[]=$tmpval;
                }//if(is_array($tmpval) && count($tmpval))
            }//if(count($sarg)>1)
        }//END foreach
        return $ret;
    }//END protected function Utf8UnSerialize

    /**
     * @param      $arr
     * @param null $val
     * @return array|null|string|string[]
     */
    protected function ArrayNormalize($arr,$val=NULL) {
        if(is_string($arr)) {
            $res=preg_replace('/\A#k#_/','',$arr);
            if(is_null($val) || $res!=$arr || strpos($arr,'[')===FALSE || strpos($arr,']')===FALSE) {
                return $res;
            }
            $tres=explode('][',trim(preg_replace('/^\w+/','${0}]',$arr),'['));
            $res=$val;
            foreach(array_reverse($tres) as $v) {
                $rk=trim($v,']');
                $res=strlen($rk) ? [$rk=>$res] : [$res];
            }//END foreach
            return $res;
        }//if(is_string($arr))
        if(!is_array($arr) || !count($arr)) {
            return $arr;
        }
        $result=[];
        foreach($arr as $k=>$v) {
            $result[preg_replace('/\A#k#_/','',$k)]=is_array($v) ? $this->ArrayNormalize($v) : $v;
        }
        return $result;
    }//END protected function ArrayNormalize

    // DEPRECATED
    /**
     * @var    string Parsing arguments separator
     */
    protected $paramsSeparator=',';
    /**
     * @var    string Array elements separator
     */
    protected $arrayParamsSeparator='~';
    /**
     * @var    string Array key-value separator
     */
    protected $arrayKeySeparator='|';

    /**
     * Check if a string contains one or more strings.
     *
     * @param string  $haystack   The string to be searched.
     * @param mixed   $needle     The string to be searched for.
     *                            To search for multiple strings, needle can be an array containing this strings.
     * @param integer $offset     The offset from which the search to begin (default 0, the begining of the string).
     * @param bool    $allArray   Used only if the needle param is an array, sets the search type:
     *                            * if is set TRUE the function will return TRUE only if all the strings contained in needle are found in haystack,
     *                            * if is set FALSE (default) the function will return TRUE if any (one, several or all)
     *                            of the strings in the needle are found in haystack.
     * @return  bool Returns TRUE if needle is found in haystack or FALSE otherwise.
     */
    public static function StringContains(string $haystack,$needle,int $offset=0,bool $allArray=FALSE): bool {
        if(is_array($needle)) {
            if(!$haystack || count($needle)==0) {
                return FALSE;
            }
            foreach($needle as $n) {
                $tr=strpos($haystack,$n,$offset);
                if(!$allArray && $tr!==FALSE) {
                    return TRUE;
                }
                if($allArray && $tr===FALSE) {
                    return FALSE;
                }
            }//foreach($needle as $n)
            return $allArray;
        }//if(is_array($needle))
        return strpos($haystack,$needle,$offset)!==FALSE;
    }//END public static function StringContains

    /**
     * @param $arg
     * @return string
     */
    private function PrepareArgument($arg) {
        if(self::StringContains($arg,':')) {
            return '\'{'.self::$jsGetterMarker.$arg.'}\'';
        }
        if(trim($arg,'\'')==$arg && strpos($arg,'(')!==FALSE && strpos($arg,')')!==FALSE) {
            return '\'{'.self::$jsEvalMarker.$arg.'}\'';
        }
        return $arg;
    }//END private function PrepareArgument

    /**
     * @param string|null $params
     * @param string|null $arrayParams
     * @return string|null
     */
    public function LegacyProcessExtraParamsString(?string $params,?string &$arrayParams=NULL): ?string {
        if(!strlen($params)) {
            return NULL;
        }
        $ppPrams='';
        foreach(explode($this->arrayParamsSeparator,$params) as $p) {
            if(strpos($p,'|')!==FALSE) {
                $pArray=explode('|',$p);
                $ppPrams.=(strlen($ppPrams) ? ', ' : '').$pArray[0].': '.$this->PrepareArgument($pArray[1]);
            } else {
                $arrayParams.=(strlen($arrayParams) ? ', ' : '').$this->PrepareArgument($p);
            }
        }
        return $ppPrams;
    }//END public function LegacyProcessExtraParamsString

    /**
     * @param string|null $command
     * @param string|null $targetId
     * @param array|null  $jParams
     * @param string|null $eParams
     * @param string|null $method
     * @return string|null
     */
    public function LegacyProcessParamsString(?string $command,?string &$targetId=NULL,?array &$jParams=NULL,?string &$eParams=NULL,?string &$method=NULL): ?string {
        $params=NULL;
        if(!strlen($command)) {
            return $params;
        }
        $functions='';
        $targets='';
        if(strpos($command,'-<')!==FALSE) {
            foreach(static::TrimExplode('-<',$command) as $k=>$v) {
                switch($k) {
                    case 0:
                        $command=trim($v);
                        break;
                    case 1:
                    default:
                        if(strlen(trim($v))) {
                            if(!is_array($jParams)) {
                                $jParams=[];
                            }
                            $jParams[trim($v)]=trim($v);
                        }
                        break;
                }//END switch
            }//END foreach
        }//if(strpos($command,'-<')!==FALSE)
        $tmp=static::TrimExplode('->',$command);
        if(isset($tmp[0])) {
            $functions=trim($tmp[0]);
        }
        if(isset($tmp[1])) {
            $targets=trim($tmp[1]);
        }
        if(isset($tmp[2])) {
            $eParams=strlen(trim($tmp[2])) ? trim($tmp[2]) : NULL;
        }
        if(strstr($functions,'(')) {
            $target='';
            $inputArray=explode('(',$functions,2);
            [$method,$args]=$inputArray;
            $args=substr($args,0,-1);
            $tmp=static::TrimExplode(',',$targets);
            if(isset($tmp[0])) {
                $target=$tmp[0];
            }
            $tmp=static::TrimExplode(':',$target);
            if(isset($tmp[0])) {
                $targetId=$tmp[0];
            }
            if($method) {
                $params='';
                if(strlen($args)) {
                    $pLvl1=explode($this->paramsSeparator,$args);
                    $params.='\'module\': '.get_array_value($pLvl1,0,'','is_string').',';
                    $params.='\'method\': '.get_array_value($pLvl1,1,'','is_string').',';
                    $pParams=get_array_value($pLvl1,2,'','is_string');
                    $apParams=NULL;
                    $ppParams=$this->LegacyProcessExtraParamsString($pParams,$apParams);
                    $targetValue=get_array_value($pLvl1,3,'','is_string');
                    if(strlen($targetValue)) {
                        $ppParams.=(strlen($ppParams) ? ', ' : '').'\'target\': '.$targetValue.'';
                    }
                    $params.='\'params\': { '.$ppParams.' }';
                    if(strlen($apParams)) {
                        $params.=",\n".'\'arrayParams\': [ '.$apParams.' ]';
                    }
                }
                $params='{ '.$params.' }';
            }//if($method)
        }//if(strstr($functions,'('))
        return $params;
    }//END public function ProcessParamsString

    /**
     * Generate javascript for ajax request
     * $js_script -> js script or js file name (with full link) to be executed before or after the ajax request
     *
     * @param      $commands
     * @param int  $loader
     * @param null $confirm
     * @param null $jsScript
     * @param int  $async
     * @param int  $runOnInitEvent
     * @param null $postParams
     * @param null $classFile
     * @param null $className
     * @param null $interval
     * @param null $callback
     * @return string
     * @throws \NETopes\Core\AppException
     */
    public function LegacyPrepare($commands,$loader=1,$confirm=NULL,$jsScript=NULL,$async=1,$runOnInitEvent=1,$postParams=NULL,$classFile=NULL,$className=NULL,$interval=NULL,$callback=NULL) {
        $allCommands='';
        $commands=static::TrimExplode(';',$commands);
        foreach($commands as $command) {
            $eParams=NULL;
            $jParams=NULL;
            $params=$this->LegacyProcessParamsString($command,$targetId,$jParams,$eParams,$method);
            if(strlen($params)) {
                $interval=is_numeric($interval) && $interval>0 ? $interval : NULL;
                $jsScript=is_array($jsScript) ? $jsScript : NULL;
                $allCommands.=$this->Prepare($params,$targetId,$jParams,$loader,$confirm,$async,$callback,$eParams,$runOnInitEvent,$method,$interval,$postParams,$jsScript,$className);
            }//if(strlen($params))
        }//foreach($commands as $command)
        return $allCommands;
    }//END public function LegacyPrepare

    /**
     * Generate javascript call for ajax request (with callback)
     * $js_script -> js script or js file name (with full link) to be executed before or after the ajax request
     *
     * @param      $commands
     * @param      $callback
     * @param int  $loader
     * @param null $confirm
     * @param null $jsScript
     * @param int  $async
     * @param int  $runOnInitEvent
     * @param null $postParams
     * @param null $classFile
     * @param null $className
     * @return string
     * @throws \NETopes\Core\AppException
     */
    public function LegacyPrepareWithCallback($commands,$callback,$loader=1,$confirm=NULL,$jsScript=NULL,$async=1,$runOnInitEvent=1,$postParams=NULL,$classFile=NULL,$className=NULL) {
        return $this->LegacyPrepare($commands,$loader,$confirm,$jsScript,$async,$runOnInitEvent,$postParams,$classFile,$className,NULL,$callback);
    }//END public function LegacyPrepareWithCallback

    /**
     * Generate javascript call for repeated ajax request
     *
     * @param        $interval
     * @param        $commands
     * @param int    $loader
     * @param null   $jsScript
     * @param int    $async
     * @param int    $runOnInitEvent
     * @param null   $confirm
     * @param null   $postParams
     * @param null   $classFile
     * @param null   $className
     * @return string
     * @throws \NETopes\Core\AppException
     */
    public function LegacyPrepareRepeated($interval,$commands,$loader=1,$jsScript=NULL,$async=1,$runOnInitEvent=1,$confirm=NULL,$postParams=NULL,$classFile=NULL,$className=NULL) {
        return $this->LegacyPrepare($commands,$loader,$confirm,$jsScript,$async,$runOnInitEvent,$postParams,$classFile,$className,$interval,NULL);
    }//END public function LegacyPrepareRepeated

    /**
     * Adds a new paf run action to the queue
     *
     * @param      $commands
     * @param int  $loader
     * @param null $confirm
     * @param null $jsScript
     * @param int  $async
     * @param int  $runOnInitEvent
     * @param null $postParams
     * @param null $classFile
     * @param null $className
     * @return void
     * @throws \NETopes\Core\AppException
     */
    public function LegacyExecute($commands,$loader=1,$confirm=NULL,$jsScript=NULL,$async=1,$runOnInitEvent=1,$postParams=NULL,$classFile=NULL,$className=NULL) {
        $this->AddAction($this->LegacyPrepare($commands,$loader,$confirm,$jsScript,$async,$runOnInitEvent,$postParams,$classFile,$className,NULL,NULL));
    }//END public function LegacyExecute
}//END abstract class BaseRequest