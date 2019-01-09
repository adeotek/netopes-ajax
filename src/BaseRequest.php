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
 * @version    1.0.0.0
 * @filesource
 */
namespace NETopes\Ajax;
use NETopes\Core\App\IApp;
use NETopes\Core\AppConfig;
use NETopes\Core\AppSession;
/**
 * Class Request
 *
 * @package  NETopes\Ajax
 * @access   public
 */
abstract class BaseRequest {
	/**
	 * @var    \NETopes\Core\App\IApp Reference to the App object (for interacting with session data)
	 * @access protected
	 */
	protected $app = NULL;
	/**
	 * @var    string Session sub-array key for storing ARequest data
	 * @access protected
	 */
	protected $subsession = NULL;
	/**
	 * @var    array Custom post params to be sent with the ajax request
	 * @access protected
	 */
	protected $post_params = [];
	/**
	 * @var    array List of actions to be executed on the ajax request
	 * @access protected
	 */
	protected $request_actions = [];
	/**
	 * @var    string NETopes AJAX request session data ID
	 * @access protected
	 */
	protected $app_req_id = NULL;
	/**
	 * @var    string Control key for securing the request session data
	 * @access protected
	 */
	protected $app_req_key = '';
	/**
	 * @var    int Session keys case
	 * @access protected
	 */
	public static $session_keys_case = CASE_UPPER;
	/**
	 * @var    string Separator for ajax request arguments
	 * @access protected
	 */
	public static $app_req_sep = ']!r![';
	/**
	 * @var    string Separator for function arguments
	 * @access protected
	 */
	public static $app_arg_sep = ']!r!a![';
	/**
	 * @var    string Separator for ajax actions
	 * @access protected
	 */
	public static $app_act_sep = ']!r!s![';
	/**
	 * @var    string Parsing arguments separator
	 * @access protected
	 */
	protected $app_params_sep = ',';
	/**
	 * @var    string Array elements separator
	 * @access protected
	 */
	protected $app_arr_params_sep = '~';
	/**
	 * @var    string Array key-value separator
	 * @access protected
	 */
	protected $app_arr_key_sep = '|';
    /**
     * Execute a method of the AJAX Request implementing class
     *
     * @param  \NETopes\Core\App\IApp $app
     * @param  array                  $postParams Parameters to be send via post on ajax requests
     * @param  string|array           $subSession Sub-session key/path
     * @return void
     * @access public
     * @throws \NETopes\Core\AppException
     */
	public static function PrepareAndExecuteRequest(&$app,array $postParams = [],$subSession = NULL) {
	    /** @var \NETopes\Core\App\App $app */
		$errors = '';
		$request = array_key_exists('req',$_POST) ? $_POST['req'] : NULL;
		if(!$request) { $errors .= 'Empty Request!'; }
		$php = NULL;
		$session_id = NULL;
		$request_id = NULL;
		$class_file = NULL;
		$class = NULL;
		$function = NULL;
		$requests = NULL;
		if(!$errors) {
			/* Start session and set ID to the expected paf session */
			list($php,$session_id,$request_id) = explode(static::$app_req_sep,$request);
			/* Validate this request */
			$spath = array(
			    $app->current_namespace,
				AppSession::ConvertToSessionCase(AppConfig::app_session_key(),static::$session_keys_case),
				AppSession::ConvertToSessionCase('NAPP_AREQUEST',static::$session_keys_case),
			);
			$requests = AppSession::GetGlobalParam(AppSession::ConvertToSessionCase('AREQUESTS',static::$session_keys_case),FALSE,$spath,FALSE);
			if(\GibberishAES::dec(rawurldecode($session_id),AppConfig::app_encryption_key())!=session_id() || !is_array($requests)) {
				$errors .= 'Invalid Request!';
			} elseif(!in_array(AppSession::ConvertToSessionCase($request_id,static::$session_keys_case),array_keys($requests))) {
				$errors .= 'Invalid Request Data!';
			}//if(\GibberishAES::dec(rawurldecode($session_id),AppConfig::app_encryption_key())!=session_id() || !is_array($requests))
		}//if(!$errors)
		if(!$errors) {
			/* Get function name and process file */
			$REQ = $requests[AppSession::ConvertToSessionCase($request_id,static::$session_keys_case)];
			$method = $REQ[AppSession::ConvertToSessionCase('METHOD',static::$session_keys_case)];
			$lkey = AppSession::ConvertToSessionCase('CLASS',static::$session_keys_case);
			$class = (array_key_exists($lkey,$REQ) && $REQ[$lkey]) ? $REQ[$lkey] : AppConfig::ajax_class_name();
			if(!class_exists($class)) {
			    $lkey = AppSession::ConvertToSessionCase('CLASS_FILE',static::$session_keys_case);
                if(array_key_exists($lkey,$REQ) && isset($REQ[$lkey])) {
                    $class_file = $REQ[$lkey];
                } else {
                    $app_class_file = AppConfig::ajax_class_file();
                    $class_file = $app_class_file ? $app->app_path.$app_class_file : '';
                }//if(array_key_exists($lkey,$REQ) && isset($REQ[$lkey]))
                if(strlen($class_file)) {
                    if(file_exists($class_file)) {
                        require($class_file);
                    } else {
                        $errors = 'Class file ['.$class_file.'] not found!';
                    }//if(file_exists($class_file))
                }//if(strlen($class_file))
			}//if(!class_exists($class))
			if(!$errors) {
			    $app->arequest = new $class($app,$subSession,$postParams);
			    /* Execute the requested function */
			    $app->arequest->ExecuteRequest($method,$php);
				$app->SessionCommit(NULL,TRUE);
				if($app->arequest->HasActions()) { echo $app->arequest->Send(); }
				$content = $app->GetOutputBufferContent();
			} else {
				$content = $errors;
			}//if(!$errors)
			echo $content;
		} else {
			$app::Log2File(['type'=>'error','message'=>$errors,'no'=>-1,'file'=>__FILE__,'line'=>__LINE__],$app->app_path.AppConfig::logs_path().'/'.AppConfig::errors_log_file());
			// vprint($errors);
			echo static::$app_act_sep.'window.location.href = "'.$app->GetAppWebLink().'";';
		}//if(!$errors)
	}//END public static function PrepareAndExecuteRequest
    /**
     * AJAX Request constructor function
     *
     * @param  \NETopes\Core\App\IApp $app
     * @param  string                 $subSession Sub-session key/path
     * @param array|null              $postParams
     * @access public
     */
	public final function __construct(IApp &$app,$subSession = NULL,?array $postParams = []) {
		$this->app = &$app;
		if(is_string($subSession) && strlen($subSession)) {
			$this->subsession = array($subSession,AppConfig::app_session_key());
		} elseif(is_array($subSession) && count($subSession)) {
			$subSession[] = AppConfig::app_session_key();
			$this->subsession = $subSession;
		} else {
			$this->subsession = AppConfig::app_session_key();
		}//if(is_string($subSession) && strlen($subSession))
		$this->Init();
		if(is_array($postParams) && count($postParams)) { $this->SetPostParams($postParams); }
	}//END public final function __construct
    /**
     * Get AJAX Request javascript initialize script
     *
     * @param string $jsRootUrl
     * @return string
     */
    public function GetJsScripts(string $jsRootUrl): string {
	    $ajaxTargetScript = AppConfig::app_ajax_target();
	    $js = <<<HTML
        <script type="text/javascript">
            const NAPP_TARGET = '{$this->app->app_web_link}/{$ajaxTargetScript}';
            const NAPP_UID = '{$this->app_req_key}';
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
	 */
	protected function Init() {
		$laapp_req_id = AppSession::GetGlobalParam(AppSession::ConvertToSessionCase('NAPP_RID',self::$session_keys_case),FALSE,$this->subsession,FALSE);
		if(strlen($laapp_req_id)) {
			$this->app_req_id = $laapp_req_id;
		} else {
			$this->app_req_id = AppSession::GetNewUID();
			AppSession::SetGlobalParam(AppSession::ConvertToSessionCase('NAPP_RID',self::$session_keys_case),$this->app_req_id,FALSE,$this->subsession,FALSE);
		}//if(strlen($laapp_req_id))
		$this->StartSecureHttp();
	}//END protected function Init
	/**
	 * Clear ARequest session data and re-initialize it
	 *
	 * @return void
	 * @access protected
	 */
	protected function ClearState() {
		AppSession::UnsetGlobalParam(AppSession::ConvertToSessionCase('NAPP_RID',self::$session_keys_case),FALSE,$this->subsession,FALSE);
		AppSession::UnsetGlobalParam(AppSession::ConvertToSessionCase('NAPP_UID',self::$session_keys_case),FALSE,$this->subsession,FALSE);
		$this->app_req_id = $this->app_req_key = NULL;
		$this->Init();
	}//END protected function ClearState
	/**
	 *
	 */
	protected function StartSecureHttp() {
		if(!AppConfig::app_secure_http()) { return; }
		$this->app_req_key = AppSession::GetGlobalParam(AppSession::ConvertToSessionCase('NAPP_UID',self::$session_keys_case),FALSE,$this->subsession,FALSE);
		if(!strlen($this->app_req_key)) {
			$this->app_req_key = AppSession::GetNewUID(AppConfig::app_session_key(),'sha256');
			AppSession::SetGlobalParam(AppSession::ConvertToSessionCase('NAPP_UID',self::$session_keys_case),$this->app_req_key,FALSE,$this->subsession,FALSE);
		}//if(!strlen($this->app_req_key))
	}//END protected function StartSecureHttp
	/**
	 *
	 */
	protected function ClearSecureHttp() {
		AppSession::UnsetGlobalParam(AppSession::ConvertToSessionCase('NAPP_UID',self::$session_keys_case),FALSE,$this->subsession,FALSE);
		$this->app_req_key = NULL;
	}//END protected function ClearSecureHttp
	/**
	 * Sets params to be send via post on the ajax request
	 *
	 * @param  array $params Key-value array of parameters to be send via post
	 * @return void
	 * @access public
	 */
	public function SetPostParams($params) {
		if(is_array($params) && count($params)) { $this->post_params = $params; }
	}//END public function SetPostParams
	/**
	 * @return bool
	 */
	public function HasActions() {
		return (bool)count($this->request_actions);
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
	 * @param bool $with_output
	 * @return string
	 */
	public function JsInit($with_output = TRUE) {
		$js = '<script type="text/javascript">'."\n";
		$js .= "\t".'var NAPP_PHASH="'.$this->app->phash.'";'."\n";
		$js .= "\t".'var NAPP_TARGET="'.$this->app->app_web_link.'/'.AppConfig::app_ajax_target().'";'."\n";
		$js .= "\t".'var NAPP_UID="'.$this->app_req_key.'";'."\n";
		$js .= "\t".'var NAPP_JS_PATH="'.$this->app->app_web_link.AppConfig::app_js_path().'";'."\n";
		$js .= '</script>'."\n";
		$js .= '<script type="text/javascript" src="'.$this->app->app_web_link.AppConfig::app_js_path().'/gibberish-aes.min.js?v=1411031"></script>'."\n";
		$js .= '<script type="text/javascript" src="'.$this->app->app_web_link.AppConfig::app_js_path().'/arequest.min.js?v=1811011"></script>'."\n";
		if(is_object($this->app->debugger)) {
			$dbg_scripts = $this->app->debugger->GetScripts();
			if(is_array($dbg_scripts) && count($dbg_scripts)) {
				foreach($dbg_scripts as $dsk=>$ds) {
					$js .= '<script type="text/javascript" src="'.$this->app->app_web_link.AppConfig::app_js_path().'/debug'.$ds.'?v=1712011"></script>'."\n";
				}//END foreach
			}//if(is_array($dbg_scripts) && count($dbg_scripts))
		}//if(is_object($this->app->debugger))
		if($with_output===TRUE) { echo $js; }
		return $js;
	}//END public function JsInit
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
				$result .= (strlen($result) ? '~' : '');
				$result .= $this->GetCommandParameters($v,$lk);
			}//END foreach
		} elseif(strlen($key) || strlen($val)) {
			if(is_numeric($key) && is_string($val) && strpos($val,':')!==FALSE) {
				$result = $val;
			} else {
				$result = (strlen($key) ? "'{$key}'|" : '').(strpos($val,':')!==FALSE ? $val : "'{$val}'");
			}//if(is_numeric($key) && is_string($val) && strpos($val,':')!==FALSE)
		}//if(is_array($value) && count($value))
		return $result;
	}//END public function GetCommandParmeters
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
		// $this->app->Dlog($parameters,'$parameters');
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
	 * @param null $js_script
	 * @param int  $async
	 * @param int  $run_oninit_event
	 * @param null $post_params
	 * @param null $class_file
	 * @param null $class_name
	 * @param null $interval
	 * @param null $callback
	 * @return string
	 * @access public
	 */
	public function Prepare($commands,$loader = 1,$confirm = NULL,$js_script = NULL,$async = 1,$run_oninit_event = 1,$post_params = NULL,$class_file = NULL,$class_name = NULL,$interval = NULL,$callback = NULL) {
		$app_params_encrypt = AppConfig::app_params_encrypt();
		$commands = texplode(';',$commands);
		$all_commands = '';
		foreach($commands as $command) {
			$command = str_replace('\\','\\\\',$command);
			$functions = '';
			$targets = '';
			$eparams = '';
			$jparams = '';
			if(strpos($command,'-<')!==FALSE) {
				$jparams = '{ ';
				foreach(texplode('-<',$command) as $k=>$v) {
					switch($k) {
						case 0:
							$command = trim($v);
							break;
						case 1:
						default:
							$jparams .= ($k>1 ? ',' : '').trim($v).':'.trim($v);
							break;
					}//END switch
				}//END foreach
				$jparams .= ' }';
			}//if(strpos($command,'-<')!==FALSE)
			$tmp = texplode('->',$command);
			if(isset($tmp[0])) { $functions = trim($tmp[0]); }
			if(isset($tmp[1])) { $targets = trim($tmp[1]); }
			if(isset($tmp[2])) { $eparams = trim($tmp[2]); }
			if(strstr($functions,'(')) {
				$action = '';
				$target = '';
				$targetId = '';
				$targetProperty = '';
				$inputArray = explode('(',$functions,2);
				list($function,$args) = $inputArray;
				$args = substr($args,0,-1);
				$tmp = texplode(',',$targets);
				if(isset($tmp[0])) { $target = $tmp[0]; }
				if(isset($tmp[1])) { $action = $tmp[1]; }
				$tmp = texplode(':',$target);
				if(isset($tmp[0])) { $targetId = $tmp[0]; }
				if(isset($tmp[1])) { $targetProperty = $tmp[1]; }
				if(!$action) { $action = 'r'; }
				if(!$targetProperty) { $targetProperty = 'innerHTML'; }
				if(!$targets) { $action = $targetProperty = $targetId = ''; }
				if($function) {
					$request_id = AppSession::GetNewUID($function.$this->app_req_id,'sha256',TRUE);
					if($class_file || $class_name) {
						$class_file = strlen($class_file) ? $class_file : AppConfig::ajax_class_file();
						$class_name = strlen($class_name) ? $class_name : AppConfig::ajax_class_name();
						$req_sess_params = [
							AppSession::ConvertToSessionCase('METHOD',self::$session_keys_case)=>$function,
							AppSession::ConvertToSessionCase('CLASS_FILE',self::$session_keys_case)=>$class_file,
							AppSession::ConvertToSessionCase('CLASS',self::$session_keys_case)=>$class_name,
						];
					} else {
						$req_sess_params = [AppSession::ConvertToSessionCase('METHOD',self::$session_keys_case)=>$function];
					}//if($class_file || $class_name || $this->custom_class)
					$subsession = is_array($this->subsession) ? $this->subsession : array($this->subsession);
					$subsession[] = AppSession::ConvertToSessionCase('NAPP_AREQUEST',self::$session_keys_case);
					$subsession[] = AppSession::ConvertToSessionCase('AREQUESTS',self::$session_keys_case);
					AppSession::SetGlobalParam(AppSession::ConvertToSessionCase($request_id,self::$session_keys_case),$req_sess_params,FALSE,$subsession,FALSE);
					$session_id = rawurlencode(\GibberishAES::enc(session_id(),AppConfig::app_encryption_key()));
					$postparams = $this->PreparePostParams($post_params);
					$args_separators = [$this->app_params_sep,$this->app_arr_params_sep,$this->app_arr_key_sep];
					$phash = AppConfig::app_use_window_name() ? "'+ARequest.get(window.name)+'".self::$app_arg_sep : '';
					$jsarguments = $app_params_encrypt ? \GibberishAES::enc($phash.$this->ParseArguments($args,$args_separators),$request_id) : $phash.$this->ParseArguments($args,$args_separators);
					$pconfirm = $this->PrepareConfirm($confirm,$request_id);
					$jcallback = strlen($callback) ? $callback : '';
					if(strlen($jcallback) && $app_params_encrypt) {
						$jcallback = \GibberishAES::enc($jcallback,$request_id);
					}//if(strlen($callback) && $app_params_encrypt)
					if(is_numeric($interval) && $interval>0) {
						$all_commands .= "ARequest.runRepeated({$interval},'".str_replace("'","\\'",$jsarguments)."',".((int)$app_params_encrypt).",'{$targetId}','{$action}','{$targetProperty}','{$session_id}','{$request_id}','{$postparams}',{$loader},'{$async}','{$js_script}',{$pconfirm},".(strlen($jparams) ? $jparams : 'undefined').",".(strlen($jcallback) ? $jcallback : 'false').",".($run_oninit_event==1 ? 1 : 0).','.(strlen($eparams) ? $eparams : 'undefined').");";
					} else {
						$all_commands .= 'ARequest.run('."'{$jsarguments}',".((int)$app_params_encrypt).",'{$targetId}','{$action}','{$targetProperty}','{$session_id}','{$request_id}','{$postparams}',{$loader},'{$async}','{$js_script}',{$pconfirm},".(strlen($jparams) ? $jparams : 'undefined').",".(strlen($jcallback) ? $jcallback : 'false').",".($run_oninit_event==1 ? 1 : 0).','.(strlen($eparams) ? $eparams : 'undefined').");";
					}//if(is_numeric($interval) && $interval>0)
				}//if($function)
			}//if(strstr($functions,'('))
		}//foreach($commands as $command)
		return $all_commands;
	}//END public function Prepare
	/**
	 * Generate javascript call for ajax request (with callback)
	 * $js_script -> js script or js file name (with full link) to be executed before or after the ajax request
	 *
	 * @param      $commands
	 * @param      $callback
	 * @param int  $loader
	 * @param null $confirm
	 * @param null $js_script
	 * @param int  $async
	 * @param int  $run_oninit_event
	 * @param null $post_params
	 * @param null $class_file
	 * @param null $class_name
	 * @return string
	 * @access public
	 */
	public function PrepareWithCallback($commands,$callback,$loader = 1,$confirm = NULL,$js_script = NULL,$async = 1,$run_oninit_event = 1,$post_params = NULL,$class_file = NULL,$class_name = NULL) {
		return $this->Prepare($commands,$loader,$confirm,$js_script,$async,$run_oninit_event,$post_params,$class_file,$class_name,NULL,$callback);
	}//END public function PrepareWithCallback
	/**
	 * Generate javascript call for repeated ajax request
	 *
	 * @param        $interval
	 * @param        $commands
	 * @param int    $loader
	 * @param string $js_script
	 * @param int    $async
	 * @param int    $run_oninit_event
	 * @param null   $confirm
	 * @param null   $post_params
	 * @param null   $class_file
	 * @param null   $class_name
	 * @return string
	 * @access public
	 */
	public function PrepareRepeated($interval,$commands,$loader = 1,$js_script = '',$async = 1,$run_oninit_event = 1,$confirm = NULL,$post_params = NULL,$class_file = NULL,$class_name = NULL) {
		return $this->Prepare($commands,$loader,$confirm,$js_script,$async,$run_oninit_event,$post_params,$class_file,$class_name,$interval,NULL);
	}//END public function PrepareRepeated
	/**
	 * Adds a new paf run action to the queue
	 *
	 * @param      $commands
	 * @param int  $loader
	 * @param null $confirm
	 * @param null $js_script
	 * @param int  $async
	 * @param int  $run_oninit_event
	 * @param null $post_params
	 * @param null $class_file
	 * @param null $class_name
	 * @return void
	 * @access public
	 */
	public function Execute($commands,$loader = 1,$confirm = NULL,$js_script = NULL,$async = 1,$run_oninit_event = 1,$post_params = NULL,$class_file = NULL,$class_name = NULL) {
		$this->AddAction($this->Prepare($commands,$loader,$confirm,$js_script,$async,$run_oninit_event,$post_params,$class_file,$class_name,NULL,NULL));
	}//END public function Execute
	/**
	 * Adds a new paf run action to the queue (with callback)
	 *
	 * @param      $commands
	 * @param      $callback
	 * @param int  $loader
	 * @param null $confirm
	 * @param null $js_script
	 * @param int  $async
	 * @param int  $run_oninit_event
	 * @param null $post_params
	 * @param null $class_file
	 * @param null $class_name
	 * @return void
	 * @access public
	 */
	public function ExecuteWithCallback($commands,$callback,$loader = 1,$confirm = NULL,$js_script = NULL,$async = 1,$run_oninit_event = 1,$post_params = NULL,$class_file = NULL,$class_name = NULL) {
		$this->AddAction($this->Prepare($commands,$loader,$confirm,$js_script,$async,$run_oninit_event,$post_params,$class_file,$class_name,NULL,$callback));
	}//END public function ExecuteWithCallback
	/**
	 * Generate and execute javascript for AjaxRequest request
	 *
	 * @param array $params Parameters object (instance of [Params])
	 * @param int   $loader
	 * @param null  $confirm
	 * @param null  $js_script
	 * @param int   $async
	 * @param int   $run_oninit_event
	 * @param null  $post_params
	 * @param null  $class_file
	 * @param null  $class_name
	 * @return void
	 * @access public
	 */
	public function ExecuteAjaxRequest($params = [],$loader = 1,$confirm = NULL,$js_script = NULL,$async = 1,$run_oninit_event = 1,$post_params = NULL,$class_file = NULL,$class_name = NULL) {
		$this->AddAction($this->PrepareAjaxRequest($params,$loader,$confirm,$js_script,$async,$run_oninit_event,$post_params,$class_file,$class_name));
	}//END public function ExecuteAjaxRequest
	/**
	 * Generate javascript for AjaxRequest request
	 *
	 * @param array $params Parameters object (instance of [Params])
	 * @param null  $callback
	 * @param int   $loader
	 * @param null  $confirm
	 * @param null  $js_script
	 * @param int   $async
	 * @param int   $run_oninit_event
	 * @param null  $post_params
	 * @param null  $class_file
	 * @param null  $class_name
	 * @param null  $interval
	 * @return string
	 * @access public
	 */
	public function PrepareAjaxRequestWithCallback($params = [],$callback = NULL,$loader = 1,$confirm = NULL,$js_script = NULL,$async = 1,$run_oninit_event = 1,$post_params = NULL,$class_file = NULL,$class_name = NULL,$interval = NULL) {
		return $this->PrepareAjaxRequest($params,$loader,$confirm,$js_script,$async,$run_oninit_event,$post_params,$class_file,$class_name,$interval,$callback);
	}//END public function PrepareAjaxRequestWithCallback
	/**
	 * Generate javascript for AjaxRequest request
	 *
	 * @param array $params Parameters object (instance of [Params])
	 * @param int   $loader
	 * @param null  $confirm
	 * @param null  $js_script
	 * @param int   $async
	 * @param int   $run_oninit_event
	 * @param null  $post_params
	 * @param null  $class_file
	 * @param null  $class_name
	 * @param null  $interval
	 * @param null  $callback
	 * @return string
	 * @access public
	 */
	public function PrepareAjaxRequest($params = [],$loader = 1,$confirm = NULL,$js_script = NULL,$async = 1,$run_oninit_event = 1,$post_params = NULL,$class_file = NULL,$class_name = NULL,$interval = NULL,$callback = NULL) {
		if(!is_array($params) || !count($params)) { return NULL; }
		$commands = $this->GetCommands($params);
		if(!strlen($commands)) { return NULL; }
		return $this->Prepare($commands,$loader,$confirm,$js_script,$async,$run_oninit_event,$post_params,$class_file,$class_name,$interval,$callback);
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
			$prefix = $separator==$this->app_params_sep ? self::$app_arg_sep : "'+'".$separator;
			foreach(texplode($separator,$args) as $v) {
				$inner .= $inner ? $prefix : '';
				if(str_contains($v,$separators)) {
					$inner .= $this->ParseArguments($v,$separators);
				} else {
					$inner .= $this->PrepareArgument($v);
				}//if(str_contains($v,$separators))
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
		if(str_contains($arg,':')) {
			$tmp = texplode(':',$arg);
			if(isset($tmp[0])) { $id = $tmp[0]; }
			if(isset($tmp[1])) { $property = $tmp[1]; }
			if(isset($tmp[2])) { $attribute = $tmp[2]; }
			$arg = '';
		}//if(str_contains($arg,':'))
		if($property) {
			if($attribute) { return "'+ARequest.get('{$id}','{$property}','{$attribute}')+'"; }
			return "'+ARequest.get('{$id}','{$property}')+'";
		}//if($property)
		if($id) { return "'+ARequest.get({$id})+'"; }
		else { return "'+ARequest.get({$arg})+'"; }
	}//END private function PrepareArgument
	/**
	 * @param $confirm
	 * @param $request_id
	 * @return mixed|string
	 */
	private function PrepareConfirm($confirm,$request_id) {
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
				$confirm_str = str_replace('"',"'",json_encode(array(
					'type'=>'jqui',
					'message'=>rawurlencode($ctxt),
					'title'=>get_array_value($confirm,'title','','is_string'),
					'ok'=>get_array_value($confirm,'ok','','is_string'),
					'cancel'=>get_array_value($confirm,'cancel','','is_string'),
				)));
				if(AppConfig::app_params_encrypt()) { $confirm_str = "'".\GibberishAES::enc($confirm_str,$request_id)."'"; }
				// return 'undefined';
				break;
			case 'js':
			default:
				if(AppConfig::app_params_encrypt()) {
					$confirm_str = str_replace('"',"'",json_encode(array('type'=>'std','message'=>rawurlencode($ctxt))));
					$confirm_str = "'".\GibberishAES::enc($confirm_str,$request_id)."'";
				} else {
					$confirm_str = "'".rawurlencode($ctxt)."'";
				}//if(AppConfig::app_params_encrypt())
				break;
		}//END switch
		return $confirm_str;
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
		if(is_array($this->post_params) && count($this->post_params)) {
			foreach($this->post_params as $k=>$v) { $result .= '&'.$k.'='.$v; }
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
		$this->request_actions[] = $action;
	}//private function AddAction
	/**
	 * @return null|string
	 */
	public function GetActions() {
		if(!$this->HasActions()) { return NULL; }
		$actions = implode(';',array_map(function($value){return trim($value,';');},$this->request_actions)).';';
		return self::$app_act_sep.$actions.self::$app_act_sep;
	}//END public function GetActions
/*** NETopes js response functions ***/
	/**
	 * Execute javascript code
	 *
	 * @param $jscript
	 */
	public function ExecuteJs($jscript) {
		if(is_string($jscript) && strlen($jscript)) { $this->AddAction($jscript); }
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
		$this->AddAction("document.forms['$form'].submit()");
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
		$target_arr = texplode(',',$target);
		$target = $target_arr[0];
		if(count($target_arr)>1) { $action = $target_arr[1]; }
		$target_arr2 = texplode(':',$target);
		$targetId = $target_arr2[0];
		if(count($target_arr2)>1) { $targetProperty = $target_arr2[1]; }
		if(!$action) { $action = 'r'; }
		if(!$targetProperty) { $targetProperty = 'innerHTML'; }
		$action = "ARequest.put(decodeURIComponent('".rawurlencode($content)."'),'$targetId','$action','$targetProperty')";
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
		$this->AddAction("ARequest.put('none','$element','r','style.display')");
	}//END public function Hide
	/**
	 * Shows an element (sets css display property to '')
	 *
	 * @param  string $element Id of element to be shown
	 * @return void
	 * @access public
	 */
	public function Show($element) {
		$this->AddAction("ARequest.put('','$element','r','style.display')");
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
		$this->AddAction("ARequest.setStyle('$element', '$styleString')");
	}//END public function Style
	/**
	 * Return response actions to javascript for execution and clears actions property
	 *
	 * @return string The string enumeration containing all actions to be executed
	 * @access public
	 */
	public function Send() {
		$actions = $this->GetActions();
		$this->request_actions = [];
		return $actions;
	}//END public function Send
//END NETopes js response functions
	/**
	 * @param $function
	 * @param $args
	 * @return void
	 */
	public function ExecuteRequest($function,$args) {
		//Kill magic quotes if they are on
		if(get_magic_quotes_gpc()) { $args = stripslashes($args); }
		//decode encrypted HTTP data if needed
		$args = utf8_decode(rawurldecode($args));
		if(AppConfig::app_secure_http()) {
			if(!$this->app_req_key) { echo "ARequest ERROR: [{$function}] Not validated."; }
			$args = \GibberishAES::dec($args,$this->app_req_key);
		}//if(AppConfig::app_secure_http())
		//limited to 100 arguments for DNOS attack protection
		$args = explode(self::$app_arg_sep,$args,100);
		for($i=0; $i<count($args); $i++) {
			$args[$i] = $this->Utf8Unserialize(rawurldecode($args[$i]));
			$args[$i] = str_replace(self::$app_act_sep,'',$args[$i]);
		}//END for
		if(method_exists($this,$function)) {
			echo call_user_func_array(array($this,$function),$args);
		} else {
			echo "ARequest ERROR: [{$function}] Not validated.";
		}//if(method_exists($this,$function))
	}//END public function ExecuteRequest
	/**
	 * @param $str
	 * @return array|null|string|string[]
	 */
	private function Utf8Unserialize($str) {
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
	}//END private function Utf8Unserialize
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
}//END abstract class BaseRequest