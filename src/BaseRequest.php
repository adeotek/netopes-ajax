<?php
/**
 * NETopes AJAX requests class file.
 *
 * The NETopes class used for working with ajax requests.
 *
 * @package    NETopes\Ajax
 * @author     George Benjamin-Schonberger
 * @copyright  Copyright (c) 2013 - 2019 AdeoTEK Software SRL
 * @license    LICENSE.md
 * @version    1.1.0.0
 * @filesource
 */
namespace NETopes\Ajax;
use ErrorHandler;
use NETopes\Core\AppConfig;
use NETopes\Core\AppException;
use NETopes\Core\AppSession;
use NApp;

/**
 * Class Request
 *
 * @package  NETopes\Ajax
 * @access   public
 */
abstract class BaseRequest {
	/**
	 * @var    string Session sub-array key for storing ARequest data
	 * @access protected
	 */
	protected $subSession = NULL;
	/**
	 * @var    array Custom post params to be sent with the ajax request
	 * @access protected
	 */
	protected $postParams = [];
	/**
	 * @var    array List of actions to be executed on the ajax request
	 * @access protected
	 */
	protected $requestActions = [];
	/**
	 * @var    string NETopes AJAX request session data ID
	 * @access protected
	 */
	protected $requestId = NULL;
	/**
	 * @var    string Control key for securing the request session data
	 * @access protected
	 */
	protected $requestKey = '';
	/**
	 * @var    int Session keys case
	 * @access protected
	 */
	public static $sessionKeysCase = CASE_UPPER;
	/**
	 * @var    string Separator for ajax request arguments
	 * @access protected
	 */
	public static $requestSeparator = ']!r![';
	/**
	 * @var    string Separator for function arguments
	 * @access protected
	 */
	public static $argumentSeparator = ']!r!a![';
	/**
	 * @var    string Separator for ajax actions
	 * @access protected
	 */
	public static $actionSeparator = ']!r!s![';
	/**
	 * @var    string Parsing arguments separator
	 * @access protected
	 */
	protected $paramsSeparator = ',';
	/**
	 * @var    string Array elements separator
	 * @access protected
	 */
	protected $arrayParamsSeparator = '~';
	/**
	 * @var    string Array key-value separator
	 * @access protected
	 */
	protected $arrayKeySeparator = '|';
    /**
     * Execute a method of the AJAX Request implementing class
     *
     * @param  array                  $postParams Parameters to be send via post on ajax requests
     * @param  string|array           $subSession Sub-session key/path
     * @return void
     * @access public
     * @throws \NETopes\Core\AppException
     */
	public static function PrepareAndExecuteRequest(array $postParams = [],$subSession = NULL) {
		$errors = '';
		$request = array_key_exists('req',$_POST) ? $_POST['req'] : NULL;
		if(!$request) { $errors .= 'Empty Request!'; }
		$php = NULL;
		$sessionId = NULL;
		$request_id = NULL;
		$classFile = NULL;
		$class = NULL;
		$function = NULL;
		$requests = NULL;
		if(!$errors) {
			/* Start session and set ID to the expected paf session */
			list($php,$sessionId,$request_id) = explode(static::$requestSeparator,$request);
			/* Validate this request */
			$spath = [
			    NApp::$currentNamespace,
				AppSession::ConvertToSessionCase(AppConfig::GetValue('app_session_key'),static::$sessionKeysCase),
				AppSession::ConvertToSessionCase('NAPP_AREQUEST',static::$sessionKeysCase),
			];
			$requests = AppSession::GetGlobalParam(AppSession::ConvertToSessionCase('AREQUESTS',static::$sessionKeysCase),FALSE,$spath,FALSE);
			if(\GibberishAES::dec(rawurldecode($sessionId),AppConfig::GetValue('app_encryption_key'))!=session_id() || !is_array($requests)) {
				$errors .= 'Invalid Request!';
			} elseif(!in_array(AppSession::ConvertToSessionCase($request_id,static::$sessionKeysCase),array_keys($requests))) {
				$errors .= 'Invalid Request Data!';
			}//if(\GibberishAES::dec(rawurldecode($sessionId),AppConfig::GetValue('app_encryption_key'))!=session_id() || !is_array($requests))
		}//if(!$errors)
		if(!$errors) {
			/* Get function name and process file */
			$REQ = $requests[AppSession::ConvertToSessionCase($request_id,static::$sessionKeysCase)];
			$method = $REQ[AppSession::ConvertToSessionCase('METHOD',static::$sessionKeysCase)];
			$lkey = AppSession::ConvertToSessionCase('CLASS',static::$sessionKeysCase);
			$class = (array_key_exists($lkey,$REQ) && $REQ[$lkey]) ? $REQ[$lkey] : AppConfig::GetValue('ajax_class_name');
			if(!class_exists($class)) {
			    $lkey = AppSession::ConvertToSessionCase('CLASS_FILE',static::$sessionKeysCase);
                if(array_key_exists($lkey,$REQ) && isset($REQ[$lkey])) {
                    $classFile = $REQ[$lkey];
                } else {
                    $app_class_file = AppConfig::GetValue('ajax_class_file');
                    $classFile = $app_class_file ? NApp::$appPath.$app_class_file : '';
                }//if(array_key_exists($lkey,$REQ) && isset($REQ[$lkey]))
                if(strlen($classFile)) {
                    if(file_exists($classFile)) {
                        require($classFile);
                    } else {
                        $errors = 'Class file ['.$classFile.'] not found!';
                    }//if(file_exists($classFile))
                }//if(strlen($classFile))
			}//if(!class_exists($class))
			if(!$errors) {
			    NApp::SetAjaxRequest(new $class($subSession,$postParams));
			    /* Execute the requested function */
			    try {
			    	NApp::Ajax()->ExecuteRequest($method,$php);
				} catch(\Error $er) {
            		NApp::Elog($er);
			    } catch(AppException $e) {
			        NApp::Elog($e);
			    }//END try
				NApp::SessionCommit(FALSE,TRUE);
				if(NApp::Ajax()->HasActions()) { echo NApp::Ajax()->Send(); }
				$content = NApp::GetOutputBufferContent();
			} else {
				$content = $errors;
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
     * @param  string                 $subSession Sub-session key/path
     * @param array|null              $postParams
     * @access public
     * @throws \NETopes\Core\AppException
     */
	public final function __construct($subSession = NULL,?array $postParams = []) {
		if(is_string($subSession) && strlen($subSession)) {
			$this->subSession = array($subSession,AppConfig::GetValue('app_session_key'));
		} elseif(is_array($subSession) && count($subSession)) {
			$subSession[] = AppConfig::GetValue('app_session_key');
			$this->subSession = $subSession;
		} else {
			$this->subSession = AppConfig::GetValue('app_session_key');
		}//if(is_string($subSession) && strlen($subSession))
		$this->Init();
		if(is_array($postParams) && count($postParams)) { $this->SetPostParams($postParams); }
	}//END public final function __construct
    /**
     * Get AJAX Request javascript initialize script
     *
     * @param string $jsRootUrl
     * @return string
     * @throws \NETopes\Core\AppException
     */
    public function GetJsScripts(string $jsRootUrl): string {
	    $ajaxTargetScript = AppConfig::GetValue('app_ajax_target');
	    $appBaseUrl = NApp::$appBaseUrl;
	    $js = <<<HTML
        <script type="text/javascript">
            const NAPP_TARGET = '{$appBaseUrl}/{$ajaxTargetScript}';
            const NAPP_UID = '{$this->requestKey}';
        </script>
        <script type="text/javascript" src="{$jsRootUrl}/arequest.min.js?v=1901081"></script>
HTML;
        return $js;
	}//END public function GetJsScripts
    /**
     * Initialize AJAX Request session data (generate session data id) if is not initialized
     *
     * @return void
     * @access protected
     * @throws \NETopes\Core\AppException
     */
	protected function Init() {
		$laapp_req_id = AppSession::GetGlobalParam(AppSession::ConvertToSessionCase('NAPP_RID',self::$sessionKeysCase),FALSE,$this->subSession,FALSE);
		if(strlen($laapp_req_id)) {
			$this->requestId = $laapp_req_id;
		} else {
			$this->requestId = AppSession::GetNewUID();
			AppSession::SetGlobalParam(AppSession::ConvertToSessionCase('NAPP_RID',self::$sessionKeysCase),$this->requestId,FALSE,$this->subSession,FALSE);
		}//if(strlen($laapp_req_id))
		$this->StartSecureHttp();
	}//END protected function Init
    /**
     * Clear ARequest session data and re-initialize it
     *
     * @return void
     * @access protected
     * @throws \NETopes\Core\AppException
     */
	protected function ClearState() {
		AppSession::UnsetGlobalParam(AppSession::ConvertToSessionCase('NAPP_RID',self::$sessionKeysCase),FALSE,$this->subSession,FALSE);
		AppSession::UnsetGlobalParam(AppSession::ConvertToSessionCase('NAPP_UID',self::$sessionKeysCase),FALSE,$this->subSession,FALSE);
		$this->requestId = $this->requestKey = NULL;
		$this->Init();
	}//END protected function ClearState
    /**
     *
     * @throws \NETopes\Core\AppException
     */
	protected function StartSecureHttp() {
		if(!AppConfig::GetValue('app_secure_http')) { return; }
		$this->requestKey = AppSession::GetGlobalParam(AppSession::ConvertToSessionCase('NAPP_UID',self::$sessionKeysCase),FALSE,$this->subSession,FALSE);
		if(!strlen($this->requestKey)) {
			$this->requestKey = AppSession::GetNewUID(AppConfig::GetValue('app_session_key'),'sha256');
			AppSession::SetGlobalParam(AppSession::ConvertToSessionCase('NAPP_UID',self::$sessionKeysCase),$this->requestKey,FALSE,$this->subSession,FALSE);
		}//if(!strlen($this->requestKey))
	}//END protected function StartSecureHttp
    /**
     *
     * @throws \NETopes\Core\AppException
     */
	protected function ClearSecureHttp() {
		AppSession::UnsetGlobalParam(AppSession::ConvertToSessionCase('NAPP_UID',self::$sessionKeysCase),FALSE,$this->subSession,FALSE);
		$this->requestKey = NULL;
	}//END protected function ClearSecureHttp
	/**
	 * Sets params to be send via post on the ajax request
	 *
	 * @param  array $params Key-value array of parameters to be send via post
	 * @return void
	 * @access public
	 */
	public function SetPostParams($params) {
		if(is_array($params) && count($params)) { $this->postParams = $params; }
	}//END public function SetPostParams
	/**
	 * @return bool
	 */
	public function HasActions() {
		return (bool)count($this->requestActions);
	}//END public function HasActions
    /**
     * Sets params to be send via post on the ajax request
     *
     * @param string $targetId
     * @return bool
     * @access public
     */
	public function SetDynamicTarget(string $targetId): bool {
		if(!strlen(trim($targetId))) { return FALSE; }
		header('HTMLTargetId: '.$targetId);
		return TRUE;
	}//END public function SetPostParams
    /**
     * @param bool $withOutput
     * @return string
     * @throws \NETopes\Core\AppException
     */
	public function JsInit(bool $withOutput = TRUE) {
		$js = '<script type="text/javascript">'."\n";
		$js .= "\t".'var NAPP_PHASH="'.NApp::GetPhash().'";'."\n";
		$js .= "\t".'var NAPP_TARGET="'.NApp::$appBaseUrl.'/'.AppConfig::GetValue('app_ajax_target').'";'."\n";
		$js .= "\t".'var NAPP_UID="'.$this->requestKey.'";'."\n";
		$js .= "\t".'var NAPP_JS_PATH="'.NApp::$appBaseUrl.AppConfig::GetValue('app_js_path').'";'."\n";
		$js .= '</script>'."\n";
		$js .= '<script type="text/javascript" src="'.NApp::$appBaseUrl.AppConfig::GetValue('app_js_path').'/gibberish-aes.min.js?v=1411031"></script>'."\n";
		$js .= '<script type="text/javascript" src="'.NApp::$appBaseUrl.AppConfig::GetValue('app_js_path').'/arequest.min.js?v=1811011"></script>'."\n";
		if(NApp::GetDebuggerState()) {
			$dbgScripts = NApp::$debugger->GetScripts();
			if(is_array($dbgScripts) && count($dbgScripts)) {
				foreach($dbgScripts as $dsk=>$ds) {
					$js .= '<script type="text/javascript" src="'.NApp::$appBaseUrl.AppConfig::GetValue('app_js_path').'/debug'.$ds.'?v=1712011"></script>'."\n";
				}//END foreach
			}//if(is_array($dbgScripts) && count($dbgScripts))
		}//if(NApp::GetDebuggerState())
		if($withOutput===TRUE) { echo $js; }
		return $js;
	}//END public function JsInit
	/**
     * @param $function
     * @param $args
     * @return void
     * @throws \NETopes\Core\AppException
     */
	public function ExecuteRequest($function,$args) {
		//Kill magic quotes if they are on
		if(get_magic_quotes_gpc()) { $args = stripslashes($args); }
		//decode encrypted HTTP data if needed
		$args = utf8_decode(rawurldecode($args));
		if(AppConfig::GetValue('app_secure_http')) {
			if(!$this->requestKey) { echo "ARequest ERROR: [{$function}] Not validated."; }
			$args = \GibberishAES::dec($args,$this->requestKey);
		}//if(AppConfig::GetValue('app_secure_http'))
		//limited to 100 arguments for DNOS attack protection
		$args = explode(self::$argumentSeparator,$args,100);
		for($i=0; $i<count($args); $i++) {
			$args[$i] = $this->Utf8UnSerialize(rawurldecode($args[$i]));
			$args[$i] = str_replace(self::$argumentSeparator,'',$args[$i]);
		}//END for
		if(method_exists($this,$function)) { return call_user_func_array([$this,$function],$args); }
		echo "ARequest ERROR: [{$function}] Not validated.";
	}//END public function ExecuteRequest
	/**
	 * Generate command parameters string for AjaxRequest request
	 *
	 * @param      $val
	 * @param null $key
	 * @return string
	 * @access public
	 */
	public function GetCommandParameters($val,$key = NULL) {
		$result = '';
		if(is_array($val)) {
			foreach($val as $k=>$v) {
				if(strlen($key)) {
					$lk = $key.'['.(is_numeric($k) ? '' : $k).']';
				} else {
					$lk = (is_numeric($k) ? '' : $k);
				}//if(strlen($key))
				$result .= (strlen($result) ? $this->arrayParamsSeparator : '');
				$result .= $this->GetCommandParameters($v,$lk);
			}//END foreach
		} elseif(strlen($key) || strlen($val)) {
			if(is_numeric($key) && is_string($val) && strpos($val,':')!==FALSE) {
				$result = $val;
			} else {
				$result = (strlen($key) ? "'{$key}'".$this->arrayKeySeparator : '').(strpos($val,':')!==FALSE ? $val : "'{$val}'");
			}//if(is_numeric($key) && is_string($val) && strpos($val,':')!==FALSE)
		}//if(is_array($value) && count($value))
		return $result;
	}//END public function GetCommandParameters
	/**
	 * Generate commands string for AjaxRequest request
	 *
	 * @param array $params
	 * @return string
	 * @access public
	 */
	public function GetCommands($params = NULL) {
		if(!is_array($params) || !count($params)) { return NULL; }
		$module = get_array_value($params,'module',NULL,'is_notempty_string');
		$method = get_array_value($params,'method',NULL,'is_notempty_string');
		if(!$module || !$method) { return NULL; }
		$call = get_array_value($params,'call','AjaxRequest','is_notempty_string');
		$target = get_array_value($params,'target','','is_string');
		$lparams = get_array_value($params,'params',[],'is_array');
		$commands = "{$call}('{$module}','{$method}'";
		if(array_key_exists('target',$lparams)) {
			$ptarget = $lparams['target'];
			unset($lparams['target']);
		} else {
			$ptarget = NULL;
		}//if(array_key_exists('target',$lparams))
		$parameters = $this->GetCommandParameters($lparams);
		// NApp::Dlog($parameters,'$parameters');
		if(strlen($parameters)) { $commands .= ','.$parameters; }
		if(strlen($ptarget)) { $commands .= (strlen($parameters) ? '' : ",''").",'{$ptarget}'"; }
		$commands .= ")".(strlen($target) ? '->'.$target : '');
		return $commands;
	}//END public function GetCommands
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
     * @access public
     */
	public function Prepare($commands,$loader = 1,$confirm = NULL,$jsScript = NULL,$async = 1,$runOnInitEvent = 1,$postParams = NULL,$classFile = NULL,$className = NULL,$interval = NULL,$callback = NULL) {
		$paramsEncrypt = AppConfig::GetValue('app_params_encrypt');
		$commands = self::TrimExplode(';',$commands);
		$allCommands = '';
		foreach($commands as $command) {
			$command = str_replace('\\','\\\\',$command);
			$functions = '';
			$targets = '';
			$eParams = '';
			$jParams = '';
			if(strpos($command,'-<')!==FALSE) {
				$jParams = '{ ';
				foreach(self::TrimExplode('-<',$command) as $k=>$v) {
					switch($k) {
						case 0:
							$command = trim($v);
							break;
						case 1:
						default:
							$jParams .= ($k>1 ? $this->paramsSeparator : '').trim($v).':'.trim($v);
							break;
					}//END switch
				}//END foreach
				$jParams .= ' }';
			}//if(strpos($command,'-<')!==FALSE)
			$tmp = self::TrimExplode('->',$command);
			if(isset($tmp[0])) { $functions = trim($tmp[0]); }
			if(isset($tmp[1])) { $targets = trim($tmp[1]); }
			if(isset($tmp[2])) { $eParams = trim($tmp[2]); }
			if(strstr($functions,'(')) {
				$action = '';
				$target = '';
				$targetId = '';
				$targetProperty = '';
				$inputArray = explode('(',$functions,2);
				list($function,$args) = $inputArray;
				$args = substr($args,0,-1);
				$tmp = self::TrimExplode(',',$targets);
				if(isset($tmp[0])) { $target = $tmp[0]; }
				if(isset($tmp[1])) { $action = $tmp[1]; }
				$tmp = self::TrimExplode(':',$target);
				if(isset($tmp[0])) { $targetId = $tmp[0]; }
				if(isset($tmp[1])) { $targetProperty = $tmp[1]; }
				if(!$action) { $action = 'r'; }
				if(!$targetProperty) { $targetProperty = 'innerHTML'; }
				if(!$targets) { $action = $targetProperty = $targetId = ''; }
				if($function) {
					$requestId = AppSession::GetNewUID($function.$this->requestId,'sha256',TRUE);
					if($classFile || $className) {
						$classFile = strlen($classFile) ? $classFile : AppConfig::GetValue('ajax_class_file');
						$className = strlen($className) ? $className : AppConfig::GetValue('ajax_class_name');
						$reqSessionParams = [
							AppSession::ConvertToSessionCase('METHOD',self::$sessionKeysCase)=>$function,
							AppSession::ConvertToSessionCase('CLASS_FILE',self::$sessionKeysCase)=>$classFile,
							AppSession::ConvertToSessionCase('CLASS',self::$sessionKeysCase)=>$className,
						];
					} else {
						$reqSessionParams = [AppSession::ConvertToSessionCase('METHOD',self::$sessionKeysCase)=>$function];
					}//if($classFile || $className || $this->customClass)
					$subSession = is_array($this->subSession) ? $this->subSession : array($this->subSession);
					$subSession[] = AppSession::ConvertToSessionCase('NAPP_AREQUEST',self::$sessionKeysCase);
					$subSession[] = AppSession::ConvertToSessionCase('AREQUESTS',self::$sessionKeysCase);
					AppSession::SetGlobalParam(AppSession::ConvertToSessionCase($requestId,self::$sessionKeysCase),$reqSessionParams,FALSE,$subSession,FALSE);
					$sessionId = rawurlencode(\GibberishAES::enc(session_id(),AppConfig::GetValue('app_encryption_key')));
					$postParams = $this->PreparePostParams($postParams);
					$argsSeparators = [$this->paramsSeparator,$this->arrayParamsSeparator,$this->arrayKeySeparator];
					$phash = AppConfig::GetValue('app_use_window_name') ? "'+ARequest.get(window.name)+'".self::$argumentSeparator : '';
					$jsArguments = $paramsEncrypt ? \GibberishAES::enc($phash.$this->ParseArguments($args,$argsSeparators),$requestId) : $phash.$this->ParseArguments($args,$argsSeparators);
					$pConfirm = $this->PrepareConfirm($confirm,$requestId);
					$jsCallback = strlen($callback) ? $callback : '';
					if(strlen($jsCallback) && $paramsEncrypt) { $jsCallback = \GibberishAES::enc($jsCallback,$requestId); }
					if(is_numeric($interval) && $interval>0) {
						$allCommands .= "ARequest.runRepeated({$interval},'".str_replace("'","\\'",$jsArguments)."',".((int)$paramsEncrypt).",'{$targetId}','{$action}','{$targetProperty}','{$sessionId}','{$requestId}','{$postParams}',{$loader},'{$async}','{$jsScript}',{$pConfirm},".(strlen($jParams) ? $jParams : 'undefined').",".(strlen($jsCallback) ? $jsCallback : 'false').",".($runOnInitEvent==1 ? 1 : 0).','.(strlen($eParams) ? $eParams : 'undefined').");";
					} else {
						$allCommands .= 'ARequest.run('."'{$jsArguments}',".((int)$paramsEncrypt).",'{$targetId}','{$action}','{$targetProperty}','{$sessionId}','{$requestId}','{$postParams}',{$loader},'{$async}','{$jsScript}',{$pConfirm},".(strlen($jParams) ? $jParams : 'undefined').",".(strlen($jsCallback) ? $jsCallback : 'false').",".($runOnInitEvent==1 ? 1 : 0).','.(strlen($eParams) ? $eParams : 'undefined').");";
					}//if(is_numeric($interval) && $interval>0)
				}//if($function)
			}//if(strstr($functions,'('))
		}//foreach($commands as $command)
		return $allCommands;
	}//END public function Prepare
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
     * @access public
     */
	public function PrepareWithCallback($commands,$callback,$loader = 1,$confirm = NULL,$jsScript = NULL,$async = 1,$runOnInitEvent = 1,$postParams = NULL,$classFile = NULL,$className = NULL) {
		return $this->Prepare($commands,$loader,$confirm,$jsScript,$async,$runOnInitEvent,$postParams,$classFile,$className,NULL,$callback);
	}//END public function PrepareWithCallback
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
     * @access public
     */
	public function PrepareRepeated($interval,$commands,$loader = 1,$jsScript = NULL,$async = 1,$runOnInitEvent = 1,$confirm = NULL,$postParams = NULL,$classFile = NULL,$className = NULL) {
		return $this->Prepare($commands,$loader,$confirm,$jsScript,$async,$runOnInitEvent,$postParams,$classFile,$className,$interval,NULL);
	}//END public function PrepareRepeated
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
     * @access public
     */
	public function Execute($commands,$loader = 1,$confirm = NULL,$jsScript = NULL,$async = 1,$runOnInitEvent = 1,$postParams = NULL,$classFile = NULL,$className = NULL) {
		$this->AddAction($this->Prepare($commands,$loader,$confirm,$jsScript,$async,$runOnInitEvent,$postParams,$classFile,$className,NULL,NULL));
	}//END public function Execute
    /**
     * Adds a new paf run action to the queue (with callback)
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
     * @return void
     * @throws \NETopes\Core\AppException
     * @access public
     */
	public function ExecuteWithCallback($commands,$callback,$loader = 1,$confirm = NULL,$jsScript = NULL,$async = 1,$runOnInitEvent = 1,$postParams = NULL,$classFile = NULL,$className = NULL) {
		$this->AddAction($this->Prepare($commands,$loader,$confirm,$jsScript,$async,$runOnInitEvent,$postParams,$classFile,$className,NULL,$callback));
	}//END public function ExecuteWithCallback
    /**
     * Generate and execute javascript for AjaxRequest request
     *
     * @param array $params Parameters object (instance of [Params])
     * @param int   $loader
     * @param null  $confirm
     * @param null  $jsScript
     * @param int   $async
     * @param int   $runOnInitEvent
     * @param null  $postParams
     * @param null  $classFile
     * @param null  $className
     * @return void
     * @throws \NETopes\Core\AppException
     * @access public
     */
	public function ExecuteAjaxRequest($params = [],$loader = 1,$confirm = NULL,$jsScript = NULL,$async = 1,$runOnInitEvent = 1,$postParams = NULL,$classFile = NULL,$className = NULL) {
		$this->AddAction($this->PrepareAjaxRequest($params,$loader,$confirm,$jsScript,$async,$runOnInitEvent,$postParams,$classFile,$className));
	}//END public function ExecuteAjaxRequest
    /**
     * Generate javascript for AjaxRequest request
     *
     * @param array $params Parameters object (instance of [Params])
     * @param null  $callback
     * @param int   $loader
     * @param null  $confirm
     * @param null  $jsScript
     * @param int   $async
     * @param int   $runOnInitEvent
     * @param null  $postParams
     * @param null  $classFile
     * @param null  $className
     * @param null  $interval
     * @return string
     * @throws \NETopes\Core\AppException
     * @access public
     */
	public function PrepareAjaxRequestWithCallback($params = [],$callback = NULL,$loader = 1,$confirm = NULL,$jsScript = NULL,$async = 1,$runOnInitEvent = 1,$postParams = NULL,$classFile = NULL,$className = NULL,$interval = NULL) {
		return $this->PrepareAjaxRequest($params,$loader,$confirm,$jsScript,$async,$runOnInitEvent,$postParams,$classFile,$className,$interval,$callback);
	}//END public function PrepareAjaxRequestWithCallback
    /**
     * Generate javascript for AjaxRequest request
     *
     * @param array $params Parameters object (instance of [Params])
     * @param int   $loader
     * @param null  $confirm
     * @param null  $jsScript
     * @param int   $async
     * @param int   $runOnInitEvent
     * @param null  $postParams
     * @param null  $classFile
     * @param null  $className
     * @param null  $interval
     * @param null  $callback
     * @return string
     * @throws \NETopes\Core\AppException
     * @access public
     */
	public function PrepareAjaxRequest($params = [],$loader = 1,$confirm = NULL,$jsScript = NULL,$async = 1,$runOnInitEvent = 1,$postParams = NULL,$classFile = NULL,$className = NULL,$interval = NULL,$callback = NULL) {
		if(!is_array($params) || !count($params)) { return NULL; }
		$commands = $this->GetCommands($params);
		if(!strlen($commands)) { return NULL; }
		return $this->Prepare($commands,$loader,$confirm,$jsScript,$async,$runOnInitEvent,$postParams,$classFile,$className,$interval,$callback);
	}//END public function PrepareAjaxRequest
	/**
	 * @param $args
	 * @param $separators
	 * @return string
	 */
	private function ParseArguments($args,$separators) {
		if(strlen($args)==0) { return ''; }
		$inner = '';
		$separator = NULL;
		$separators = is_string($separators) ? array($separators) : $separators;
		if(is_array($separators)) {
			$separator = array_shift($separators);
			$prefix = $separator==$this->paramsSeparator ? self::$argumentSeparator : "'+'".$separator;
			foreach(self::TrimExplode($separator,$args) as $v) {
				$inner .= $inner ? $prefix : '';
				if(self::StringContains($v,$separators)) {
					$inner .= $this->ParseArguments($v,$separators);
				} else {
					$inner .= $this->PrepareArgument($v);
				}//if(self::StringContains($v,$separators))
			}//foreach(explode($separator,$args) as $v)
		}//if(is_array($separators))
		return $inner;
	}//END private function ParseArguments
	/**
	 * @param $arg
	 * @return string
	 */
	private function PrepareArgument($arg) {
		$id = $property = $attribute = '';
		/* If arg contains ':', arg is element:property syntax */
		if(self::StringContains($arg,':')) {
			$tmp = self::TrimExplode(':',$arg);
			if(isset($tmp[0])) { $id = $tmp[0]; }
			if(isset($tmp[1])) { $property = $tmp[1]; }
			if(isset($tmp[2])) { $attribute = $tmp[2]; }
			$arg = '';
		}//if(self::StringContains($arg,':'))
		if($property) {
			if($attribute) { return "'+ARequest.get('{$id}','{$property}','{$attribute}')+'"; }
			return "'+ARequest.get('{$id}','{$property}')+'";
		}//if($property)
		if($id) { return "'+ARequest.get({$id})+'"; }
		else { return "'+ARequest.get({$arg})+'"; }
	}//END private function PrepareArgument
    /**
     * @param $confirm
     * @param $requestId
     * @return mixed|string
     * @throws \NETopes\Core\AppException
     */
	private function PrepareConfirm($confirm,$requestId) {
		if(is_string($confirm)) {
			$ctxt = $confirm;
			$ctype = 'js';
		} else {
			$ctxt = get_array_value($confirm,'text','','is_string');
			$ctype = get_array_value($confirm,'type','js','is_notempty_string');
		}//if(is_string($confirm))
		if(!strlen($ctxt)) { return 'undefined'; }
		switch($ctype) {
			case 'jqui':
				$confirmStr = str_replace('"',"'",json_encode(array(
					'type'=>'jqui',
					'message'=>rawurlencode($ctxt),
					'title'=>get_array_value($confirm,'title','','is_string'),
					'ok'=>get_array_value($confirm,'ok','','is_string'),
					'cancel'=>get_array_value($confirm,'cancel','','is_string'),
				)));
				if(AppConfig::GetValue('app_params_encrypt')) { $confirmStr = "'".\GibberishAES::enc($confirmStr,$requestId)."'"; }
				break;
			case 'js':
			default:
				if(AppConfig::GetValue('app_params_encrypt')) {
					$confirmStr = str_replace('"',"'",json_encode(array('type'=>'std','message'=>rawurlencode($ctxt))));
					$confirmStr = "'".\GibberishAES::enc($confirmStr,$requestId)."'";
				} else {
					$confirmStr = "'".rawurlencode($ctxt)."'";
				}//if(AppConfig::GetValue('app_params_encrypt'))
				break;
		}//END switch
		return $confirmStr;
	}//END private function PrepareConfirm
	/**
	 * Transforms the post params array into a string to be posted by the javascript method
	 *
	 * @param  array  $params An array of parameters to be sent with the request
	 * @return string The post params as a string
	 * @access private
	 */
	private function PreparePostParams($params = NULL) {
		$result = '';
		if(is_array($this->postParams) && count($this->postParams)) {
			foreach($this->postParams as $k=>$v) { $result .= '&'.$k.'='.$v; }
		}//if(is_array($this->aapp_post_params) && count($this->aapp_post_params))
		if(is_array($params) && count($params)) {
			foreach($params as $k=>$v) { $result .= '&'.$k.'='.$v; }
		}//if(is_array($params) && count($params))
		return $result;
	}//END private function PreparePostParams
	/**
	 * @param $action
	 */
	private function AddAction($action) {
		$this->requestActions[] = $action;
	}//private function AddAction
	/**
	 * @return null|string
	 */
	public function GetActions() {
		if(!$this->HasActions()) { return NULL; }
		$actions = implode(';',array_map(function($value){return trim($value,';');},$this->requestActions)).';';
		return self::$actionSeparator.$actions.self::$actionSeparator;
	}//END public function GetActions
/*** NETopes js response functions ***/
	/**
	 * Execute javascript code
	 *
	 * @param $jsScript
	 */
	public function ExecuteJs($jsScript) {
		if(is_string($jsScript) && strlen($jsScript)) { $this->AddAction($jsScript); }
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
		$this->AddAction("alert(\"".addslashes($text)."\")");
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
	 * @param  string $content The content to be inserted in the element
	 * @param  string $target The id of the element
	 * @return void
	 * @access public
	 */
	public function InnerHtml($content,$target) {
		$action = '';
		$targetProperty = '';
		$target_arr = self::TrimExplode(',',$target);
		$target = $target_arr[0];
		if(count($target_arr)>1) { $action = $target_arr[1]; }
		$target_arr2 = self::TrimExplode(':',$target);
		$targetId = $target_arr2[0];
		if(count($target_arr2)>1) { $targetProperty = $target_arr2[1]; }
		if(!$action) { $action = 'r'; }
		if(!$targetProperty) { $targetProperty = 'innerHTML'; }
		$action = "ARequest.put(decodeURIComponent('".rawurlencode($content)."'),'{$targetId}','{$action}','{$targetProperty}')";
		$this->AddAction($action);
	}//END public function InnerHtml
	/**
	 * Hides an element (sets css display property to none)
	 *
	 * @param  string $element Id of element to be hidden
	 * @return void
	 * @access public
	 */
	public function Hide($element) {
		$this->AddAction("ARequest.put('none','{$element}','r','style.display')");
	}//END public function Hide
	/**
	 * Shows an element (sets css display property to '')
	 *
	 * @param  string $element Id of element to be shown
	 * @return void
	 * @access public
	 */
	public function Show($element) {
		$this->AddAction("ARequest.put('','{$element}','r','style.display')");
	}//END public function Show
	/**
	 * Set style for an element
	 *
	 * @param  string $element Id of element to be set
	 * @param  string $styleString Style to be set
	 * @return void
	 * @access public
	 */
	public function Style($element,$styleString) {
		$this->AddAction("ARequest.setStyle('{$element}','{$styleString}')");
	}//END public function Style
	/**
	 * Return response actions to javascript for execution and clears actions property
	 *
	 * @return string The string enumeration containing all actions to be executed
	 * @access public
	 */
	public function Send() {
		$actions = $this->GetActions();
		$this->requestActions = [];
		return $actions;
	}//END public function Send
//END NETopes js response functions
	/**
	 * @param $str
	 * @return array|null|string|string[]
	 */
	private function Utf8UnSerialize($str) {
		$rsearch = array('^[!]^','^[^]^');
		$rreplace = array('|','~');
		if(strpos(trim($str,'|'),'|')===FALSE && strpos(trim($str,'~'),'~')===FALSE) { return $this->ArrayNormalize(str_replace($rsearch,$rreplace,unserialize($str))); }
		$ret = [];
		foreach(explode('~',$str) as $arg) {
			$sarg = explode('|',$arg);
			if(count($sarg)>1) {
				$rval = $this->ArrayNormalize(str_replace($rsearch,$rreplace,unserialize($sarg[1])));
				$rkey = $this->ArrayNormalize(str_replace($rsearch,$rreplace,unserialize($sarg[0])),$rval);
				if(is_array($rkey)) {
					$ret = array_merge_recursive($ret,$rkey);
				} else {
					$ret[$rkey] = $rval;
				}//if(is_array($rkey))
			} else {
				$tmpval = $this->ArrayNormalize(str_replace($rsearch,$rreplace,unserialize($sarg[0])));
				if(is_array($tmpval) && count($tmpval)) {
					foreach($tmpval as $k=>$v) { $ret[$k] = $v; }
				} else {
					$ret[] = $tmpval;
				}//if(is_array($tmpval) && count($tmpval))
			}//if(count($sarg)>1)
		}//END foreach
		return $ret;
	}//END private function Utf8UnSerialize
	/**
	 * @param      $arr
	 * @param null $val
	 * @return array|null|string|string[]
	 */
	private function ArrayNormalize($arr,$val = NULL) {
		if(is_string($arr)) {
			$res = preg_replace('/\A#k#_/','',$arr);
			if(is_null($val) || $res!=$arr || strpos($arr,'[')===FALSE || strpos($arr,']')===FALSE) { return $res; }
			$tres = explode('][',trim(preg_replace('/^\w+/','${0}]',$arr),'['));
			$res = $val;
			foreach(array_reverse($tres) as $v) {
				$rk = trim($v,']');
				$res = strlen($rk) ? array($rk=>$res) : array($res);
			}//END foreach
			return $res;
		}//if(is_string($arr))
		if(!is_array($arr) || !count($arr)) { return $arr; }
		$result = [];
		foreach($arr as $k=>$v) { $result[preg_replace('/\A#k#_/','',$k)] = is_array($v) ? $this->ArrayNormalize($v) : $v; }
		return $result;
	}//END private function ArrayNormalize
	/**
     * String explode function based on standard php explode function.
     * After exploding the string, for each non-numeric element, all leading and trailing spaces will be trimmed.
     *
     * @param   string $separator The string used as separator.
     * @param   string $string The string to be exploded.
     * @return  array The exploded and trimmed string as array.
     */
    public static function TrimExplode(string $separator,string $string): array {
        return array_map(function($val){ return is_string($val) && strlen($val) ? trim($val) : $val; },explode($separator,$string));
    }//END public static function TrimExplode
    /**
     * Check if a string contains one or more strings.
     *
     * @param   string $haystack The string to be searched.
     * @param   mixed $needle The string to be searched for.
     * To search for multiple strings, needle can be an array containing this strings.
     * @param   integer $offset The offset from which the search to begin (default 0, the begining of the string).
     * @param   bool $allArray Used only if the needle param is an array, sets the search type:
     * * if is set TRUE the function will return TRUE only if all the strings contained in needle are found in haystack,
     * * if is set FALSE (default) the function will return TRUE if any (one, several or all)
     * of the strings in the needle are found in haystack.
     * @return  bool Returns TRUE if needle is found in haystack or FALSE otherwise.
     */
    public static function StringContains(string $haystack,$needle,int $offset = 0,bool $allArray = FALSE): bool {
        if(is_array($needle)) {
            if(!$haystack || count($needle)==0) { return FALSE; }
            foreach($needle as $n) {
                $tr = strpos($haystack,$n,$offset);
                if(!$allArray && $tr!==FALSE) { return TRUE; }
                if($allArray && $tr===FALSE) { return FALSE; }
            }//foreach($needle as $n)
            return $allArray;
        }//if(is_array($needle))
        return strpos($haystack,$needle,$offset)!==FALSE;
    }//END public static function StringContains
}//END abstract class BaseRequest