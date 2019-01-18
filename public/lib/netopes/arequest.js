/**
 * NETopes AJAX javascript file.
 *
 * The NETopes AJAX javascript object used on ajax requests.
 * Copyright (c) 2013 - 2019 AdeoTEK Software SRL
 * License    LICENSE.md
 *
 * @author     George Benjamin-Schonberger
 * @version    1.1.0.0
 */

const ARequest = {
	procOn : {},
	reqSeparator : ']!r![',
	actSeparator : ']!r!s![',
	pipeChar: '^[!]^',
	tildeChar: '^[^]^',
	serializeMode: 'php',
	escapeStringMode: 'custom',
	onARequestInitEvent : true,
	onARequestCompleteEvent : true,
	timers : [],
	updateProcOn : function(add_val,loader) {
		if(loader) {
			let lkey = 'LOADER-'+String(loader).replaceAll(' ','_').replaceAll("'",'-').replaceAll('"','-');
			if(!ARequest.procOn.hasOwnProperty(lkey)) { ARequest.procOn[lkey] = 0; }
			ARequest.procOn[lkey] += add_val;
			if(ARequest.procOn[lkey]<=0) {
				ARequest.updateIndicator(loader,0);
			} else {
				ARequest.updateIndicator(loader,1);
			}//if(ARequest.procOn[lkey]<=0)
		}//if(loader)
	},//END updateProcOn
	updateIndicator : function(loader,new_status) {
		if(!loader) { return; }
		if(typeof(loader)==='function') {
			loader(new_status);
		} else {
				if(new_status===1) {
					$.event.trigger({ type: 'onARequestLoaderOn', loader: loader });
				} else {
					$.event.trigger({ type: 'onARequestLoaderOff', loader: loader });
				}//if(new_status==1)
		}//if(typeof(loader)=='function')
	},//END updateIndicator
	getRequest : function() {
	    try {
            return new XMLHttpRequest();
        } catch (e) {
            console.log(e);
        }//try
	},//END getRequest
	run : function(php,encrypted,id,act,property,session_id,request_id,post_params,loader,async,js_script,conf,jparams,callback,run_oninit_event,eparam,call_type) {
		if(conf) {
			let cobj = false;
			if(encrypted===1) {
				eval('cobj = '+GibberishAES.dec(conf,request_id));
			} else {
				cobj = typeof(conf)==='object' ? conf : { type: 'std', message: conf };
			}//if(encrypted==1)
			if(cobj && typeof(cobj)==='object') {
				switch(cobj.type) {
					case 'jqui':
						ARequest.jquiConfirmDialog(cobj,function() {
							ARequest.runExec(php,encrypted,id,act,property,session_id,request_id,post_params,loader,async,js_script,jparams,callback,run_oninit_event,eparam,call_type);
						});
						return;
					default:
						if(confirm(decodeURIComponent(cobj.message))!==true) { return; }
						break;
				}//END switch
			}//if(cobj && typeof(cobj)=='object')
		}//if(conf)
		ARequest.runExec(php,encrypted,id,act,property,session_id,request_id,post_params,loader,async,js_script,jparams,callback,run_oninit_event,eparam,call_type);
	},//END run
	runExec : function(php,encrypted,id,act,property,session_id,request_id,post_params,loader,async,js_script,jparams,callback,run_oninit_event,eparam,call_type) {
		if(run_oninit_event && ARequest.onARequestInitEvent) {
			$.event.trigger({ type: 'onARequestInit', callType: call_type, target: id, action: act, property: property });
		}//if(run_oninit_event && ARequest.onARequestInitEvent)
		let end_js_script = '';
		if(js_script && js_script!=='') {
			let scripts = js_script.split('~');
			if(scripts[0]) { ARequest.runScript(scripts[0]); }
			if(scripts[1]) { end_js_script = scripts[1]; }
		}//if(js_script && js_script!='')
		ARequest.updateProcOn(1,loader);
		if(encrypted===1) {
			php = GibberishAES.dec(php,request_id);
			if(typeof(eparam)==='object') {
				for(let ep in eparam) { php = php.replace(new RegExp('#'+ep+'#','g'),eparam[ep]); }
			}//if(typeof(eparam)=='object')
			let jparams_str = '';
			if(typeof(jparams)==='object') {
				for(let pn in jparams) { jparams_str += 'var '+pn+' = '+JSON.stringify(jparams[pn])+'; '; }
			}//if(typeof(jparams)=='object')
			php = eval(jparams_str+'\''+php+'\'');
		}//if(encrypted==1)
		if(session_id===decodeURIComponent(session_id)) { session_id = encodeURIComponent(session_id); }
		let requestString = 'req=' + encodeURIComponent((NAPP_UID ? GibberishAES.enc(php,NAPP_UID) : php))
			+ ARequest.reqSeparator + session_id + ARequest.reqSeparator + (request_id || '');
		requestString += '&phash='+window.name+post_params;
		if(encrypted===1 && typeof(callback)==='string') { callback = GibberishAES.dec(callback,request_id); }
		let l_call_type = call_type ? call_type : 'run';
		ARequest.sendRequest(id,act,property,requestString,loader,end_js_script,async,callback,l_call_type);
	},//END runExec
	runFromString : function(data) {
		eval('var objData = '+data);
		let sphp = GibberishAES.dec(objData.php,'xSTR');
		sphp = eval("'"+sphp.replaceAll("\\'","'")+"'");
		let call_type = objData.call_type ? objData.call_type : 'runFromString';
		ARequest.run(sphp,objData.encrypted,objData.id,objData.act,objData.property,objData.session_id,objData.request_id,objData.post_params,objData.loader,objData.async,objData.js_script,objData.conf,objData.jparams,objData.callback,objData.run_oninit_event,objData.eparam,call_type);
	},//END runFromString
	timerRun : function(interval,timer,data) {
		if(data && timer) {
			ARequest.runFromString(GibberishAES.dec(data,timer));
			if(ARequest.timers[timer]) { clearTimeout(ARequest.timers[timer]); }
			ARequest.timers[timer] = setTimeout(function(){ ARequest.timerRun(interval,timer,data); },interval);
		}//if(data && timer)
	},//END timerRun
	runRepeated : function(interval,php,encrypted,id,act,property,session_id,request_id,post_params,loader,async,js_script,conf,jparams,callback,run_oninit_event,eparam) {
		if(interval && interval>0) {
			let ltimer = request_id+(new Date().getTime());
			let run_data = GibberishAES.enc(JSON.stringify({ php: GibberishAES.enc(php,'xSTR'), encrypted: encrypted, id: id, act: act, property: property, session_id: session_id, request_id: request_id, post_params: post_params, loader: loader, async: async, js_script: js_script, conf: conf, jparams: jparams, callback: callback, run_oninit_event: run_oninit_event, eparam: eparam, call_type: 'runRepeated' }),ltimer);
			ARequest.timerRun(interval,ltimer,run_data);
		}//if(interval && interval>0)
	},//END runRepeated
	runScript : function(scriptString) {
		if(!scriptString || scriptString==='') { return false; }
		let script_type = 'eval';
		let script_val = '';
		let script = scriptString.split('|');
		if(script[0] && script[1]) {
			if(script[0]!=='') { script_type = script[0]; }
			script_val = script[1];
		}else{
			if(script[0]) { script_val = script[0]; }
		}//if(script[0] && script[1])
		if(!script_val || script_val==='' || !script_type || script_type==='') { return false; }
		switch(script_type){
			case 'file':
				$.getScript(script_val);
				break;
			case 'eval':
				eval(script_val);
				break;
			default:
				return false;
		}//END switch
	},//END runScript
	sendRequest : function(id,act,property,requestString,loader,js_script,async,callback,call_type) {
		let req = ARequest.getRequest();
		let lasync = typeof(async)!=='undefined' ? ((!(async===0 || async===false || async==='0'))) : true;
		req.open('POST',NAPP_TARGET,lasync);
		req.setRequestHeader('Content-type','application/x-www-form-urlencoded');
		req.send(requestString);
		req.onreadystatechange = function() {
			if(req.readyState===4) {
				if(req.status===200) {
				    let actions = req.responseText.split(ARequest.actSeparator);
                    let content = actions[0]+(actions[2] ? actions[2] : '');
					let htmlTarget = req.getResponseHeader('HTMLTargetId');
                    if(typeof(htmlTarget)==='string' && htmlTarget.length>0) {
                        ARequest.put(content,htmlTarget,act,property);
                    } else if(id) {
                        ARequest.put(content,id,act,property);
                    } else {
                        $.event.trigger({ type: 'onARequestDataReceived', responseData: content });
                    }//if(typeof(htmlTarget)==='string' && htmlTarget.length>0)
                    if(actions[1]) {
                    	try {
                    		eval(actions[1]);
                    	} catch (ee) {
							console.log(ee);
							console.log(actions[1]);
                        }//END try
                    }//if(actions[1])
                    if(js_script && js_script!=='') { ARequest.runScript(js_script); }
                    ARequest.updateProcOn(-1,loader);
                    if(ARequest.onARequestCompleteEvent) { $.event.trigger({ type: 'onARequestComplete', callType: call_type }); }
                    if(callback) {
                        if(callback instanceof Function) {
                            callback();
                        } else if(typeof(callback)==='string') {
                            eval(callback);
                        }//if(callback instanceof Function)
                    }//if(callback)
				} else {
					console.log(req);
				}//if(req.status==200)
			}//if(req.readyState==4)
		};//END req.onreadystatechange
	},//END sendRequest
	put : function(content,id,act,property) {
		if(!id) return;
		if(property) {
			try {
				let ob = document.getElementById(id);
				if(act==='p') {
					ob[property] = content + ob[property];
				} else if(act==='a') {
					ob[property] += content;
				} else if(property.split('.')[0]==='style') {
					ob.style[property.split('.')[1]] = content;
				} else if(ob) {
					ob[property] = content;
				}//if(act=='p')
			} catch (e) {}
		} else {
			window[id] = content;
		}//if(property)
	},//END put
	getRadioValueFromObject : function(obj,parent,name) {
		let result = null;
		let radios = null;
		if(typeof(obj)=='object' && obj!=null) {
			if(typeof(name)!='string' || name.length===0) {
				name = (typeof(obj.name)=='string' && obj.name.length>0 ? obj.name : '');
			}//if(typeof(name)!='string' || name.length==0)
			if(name.length>0) {
				if(typeof(parent)!='object' || parent==null) {
					let dparent = obj.dataset.parent;
					if(dparent) {
						parent = document.getElementById(dparent);
					} else {
						parent = document.getElementsByTagName('body')[0];
					}//if(dparent)
				}//if(typeof(parent)!='object')
				if(typeof(parent)=='object' && parent!=null) {
					radios = parent.querySelectorAll('[name='+name+']');
					for(let i=0;i<radios.length;i++) {
						if(radios[i].checked) {
							result = radios[i].value;
							break;
						}//if(radios[i].checked)
					}//END for
				}//if(typeof(parent)=='object' && parent!=null)
			}//if(name.length>0)
		} else if(typeof(parent)=='object' && parent!=null && typeof(name)=='string' && name.length>0) {
			radios = parent.querySelectorAll('[name='+name+']');
			for(let i=0;i<radios.length;i++) {
				if(radios[i].checked) {
					result = radios[i].value;
					break;
				}//if(radios[i].checked)
			}//END for
		}//if(typeof(obj)=='object' && obj!=null)
		return result;
	},//END getRadioValueFromObject
	getFromObject : function(obj,property,attribute) {
		let val = '';
		if(typeof(obj)!=='object' || obj==null || !property) { return val; }
		let dformat;
		switch(property) {
			case 'option':
				if(typeof(attribute)==='string' && attribute.length>0) {
					val = obj.options[obj.selectedIndex].getAttribute(attribute);
				} else {
					val = obj.options[obj.selectedIndex].text;
				}//if(typeof(attribute)=='string' && attribute.length>0)
				break;
			case 'radio':
				if(typeof(attribute)==='string' && attribute.length>0) {
					val = ARequest.getRadioValueFromObject(null,obj,attribute);
				} else {
					val = null;
				}//if(typeof(attribute)=='string' && attribute.length>0)
				break;
			case 'visible':
				val = !!(obj.offsetWidth || obj.offsetHeight || obj.getClientRects().length) ? 1 : 0;
				break;
			case 'slider_start':
				val = $(obj).slider('values',0);
				break;
			case 'slider_end':
				val = $(obj).slider('values',1);
				break;
			case 'content':
				val = obj.innerHTML;
				break;
			case 'nvalue':
				val = obj.value;
				let nformat = obj.getAttribute('data-format');
				if(nformat) {
					let farr = nformat.split('|');
					val = val.replaceAll(farr[3],'').replaceAll(farr[2],'');
					if(farr[1]) { val = val.replaceAll(farr[1],'.'); }
				}//if(nformat)
				break;
			case 'dvalue':
				val = obj.value;
				dformat = obj.getAttribute('data-format');
				if(dformat) {
					let tformat = obj.getAttribute('data-timeformat');
					if(tformat) { dformat += ' ' + tformat; }
					let dt = getDateFromFormat(val,dformat);
					if(dt>0) {
						if(dformat.split(' ').length>1) {
							val = formatDate(new Date(dt),'yyyy-MM-dd HH:mm:ss');
						} else {
							val = formatDate(new Date(dt),'yyyy-MM-dd 00:00:00');
						}//if(dformat.split(' ').length>1)
					} else {
						val = '';
					}//if(dt>0)
				}//if(dformat)
				break;
			case 'fdvalue':
				val = obj.value;
				dformat = obj.getAttribute('data-format');
				if(dformat) {
					let tformat = obj.getAttribute('data-timeformat');
					if(tformat) { dformat += ' ' + tformat; }
					let dt = getDateFromFormat(val,dformat);
					if(dt>0) {
						let outformat = obj.getAttribute('data-out-format');
						if(outformat) {
							val = formatDate(new Date(dt),outformat);
						} else {
							if(dformat.split(' ').length>1) {
								val = formatDate(new Date(dt),'yyyy-MM-dd HH:mm:ss');
							} else {
								val = formatDate(new Date(dt),'yyyy-MM-dd 00:00:00');
							}//if(dformat.split(' ').length>1)
						}//if(outformat)
					} else {
						val = '';
					}//if(dt>0)
				}//if(dformat)
				break;
			case 'attr':
				if(typeof(attribute)==='string' && attribute.length>0) {
					val = obj.getAttribute(attribute);
				} else {
					val = obj[property];
				}//if(typeof(attribute)=='string' && attribute.length>0)
				break;
			case 'function':
				if(typeof(attribute)==='string' && attribute.length>0) {
				    if(window.hasOwnProperty(attribute)) {
				        val = window[attribute](obj);
				    } else {
				        console.log('NETopes Error: Unable to find method [' + attribute + ']!');
				    }//if(window.hasOwnProperty(attribute))
				}//if(typeof(attribute)==='string' && attribute.length>0)
                break;
			default:
				// Removed call "attr--" (replaced by "attr:")
				if(typeof(obj.type)==='string' && obj.type==='radio' && property==='value') {
					val = ARequest.getRadioValueFromObject(obj,null,null);
				} else {
					val = obj[property];
				}//if(typeof(obj.type)=='string' && obj.type=='radio')
				break;
		}//END switch
		if(val && typeof(val)==='string') { val = val.split(ARequest.actSeparator).join(''); }
		return val;
	},//END getFromObject
	getToArray : function(obj,initial) {
		if(typeof(obj)!=='object' || obj==null) { return initial; }
		let aresult, val;
		let nName = obj.nodeName.toLowerCase();
		let objName = obj.getAttribute('name');
		if(!objName || objName.length<=0) { objName = obj.getAttribute('data-name'); }
		if(objName) {
			let names = objName.replace(/^[\w|\-|_]+/,"$&]").replace(/]$/,"").split("][");
			if(nName==='input' || nName==='select' || nName==='textarea') {
				switch(obj.getAttribute('type')) {
					case 'checkbox':
						if(obj.checked===true) {
							if(obj.value) {
								val = ARequest.escapeString(obj.value.split(ARequest.actSeparator).join(''));
							} else {
								val = 0;
							}//if(obj.value)
						} else {
							val = 0;
						}//if(obj.checked===true)
						break;
					case 'radio':
						let rval = document.querySelector('input[type=radio][name='+objName+']:checked').value;
						if(rval) {
							val = ARequest.escapeString(rval.split(ARequest.actSeparator).join(''));
						} else {
							val = 0;
						}//if(rval)
						break;
					default:
						let pafprop = obj.getAttribute('data-paf-prop');
						if(typeof(pafprop)=='string' && pafprop.length>0) {
							let pp_arr = pafprop.split(':');
							if(pp_arr.length>1) {
								val = ARequest.getFromObject(obj,pp_arr[0],pp_arr[1]);
							} else {
								val = ARequest.getFromObject(obj,pp_arr[0]);
							}//if(pp_arr.length>1)
						} else {
							val = ARequest.getFromObject(obj,'value');
						}//if(typeof(pafprop)=='string' && pafprop.length>0)
						val = ARequest.escapeString(val);
						break;
				}//END switch
			} else {
				val = ARequest.escapeString(ARequest.getFromObject(obj,'content'));
			}//if(nName=='input' || nName=='select' || nName=='textarea')
		    if(names.length>0) {
		    	for(let i=names.length-1;i>=0;i--) {
		    		let tmp;
		    		if(names[i]!=='') {
			    		tmp = {};
			    		tmp['#k#_'+names[i]] = (i===(names.length-1) ? val : aresult);
			    	} else {
			    		tmp = [ (i===(names.length-1) ? val : aresult) ];
			    	}//if(names[i]!='')
			    	aresult = tmp;
		    	}//END for
		    } else {
		    	aresult = [ val ];
		    }//if(names.length>0)
		}//if(objName)
		if(typeof(initial)!='object' && typeof(initial)!=='array') { return aresult; }
	    return arrayMerge(initial,aresult,true);
	},//END getToArray
	getFormContent : function(id) {
		let result = '';
		let frm = document.getElementById(id);
		let rbelements = {};
		if(typeof(frm)=='object') {
			$(frm).find('.postable').each(function() {
				if(this.nodeName.toLowerCase()==='input' && this.getAttribute('type')==='radio') {
					if(!rbelements.hasOwnProperty(this.getAttribute('name'))) {
                        rbelements[this.getAttribute('name')] = 1;
                        result = ARequest.getToArray(this,result);
					}//if(!rbelements.hasOwnProperty(this.getAttribute('name')))
				} else {
					result = ARequest.getToArray(this,result);
				}//if(this.nodeName.toLowerCase()=='input' && this.getAttribute('type')=='radio')
			});
		} else {
			console.log('Invalid form: '+id);
		}//if(frm)
		return result;
	},//END getFormContent
	get : function(id,property,attribute) {
		let result = null;
		if(!property) {
			result = ARequest.escapeString(id);
		} else if(property=='form') {
			result = ARequest.getFormContent(id);
		} else {
			let eObj = document.getElementById(id);
			let val = null;
			if(typeof(eObj)!='object') {
				console.log('Invalid element: '+id);
			} else if(eObj==null) {
				console.log('Null element: '+id);
			} else {
				val = ARequest.getFromObject(document.getElementById(id),property,attribute);
			}//if(typeof(obj)!='object')
			if(typeof(val)!='string') {
				result = val;
			} else {
				result = ARequest.escapeString(val);
			}//if(typeof(val)!='string')
		}//if(!property)
		return ARequest.serialize(result);
	},//END get
	escapeString : function(val) {
		if(ARequest.escapeStringMode!=='custom') { return val; }
		let nval = String(val);
		nval = nval.replace(new RegExp('\\|','g'),ARequest.pipeChar);
		nval = nval.replace(new RegExp('~','g'),ARequest.tildeChar);
		return nval;
	},//END escapeString
	serialize: function(val) {
		if(ARequest.serializeMode==='php') { return ARequest.phpSerialize(val); }
		return JSON.stringify(val);
	},//END serialize
	phpSerialize : function(mixed_value) {
		let val,key,okey,
	    ktype = '',
	    vals = '',
	    count = 0,
	    end = '';
	    _utf8Size = function(str) {
      		let size = 0,
	        i = 0,
	        l = str.length,
	        code = '';
			for(i = 0; i < l; i++) {
		    	code = str.charCodeAt(i);
		    	if(code < 0x0080) {
		    		size += 1;
		    	} else if (code < 0x0800) {
		    		size += 2;
		    	} else {
		    		size += 3;
		    	}//if(code < 0x0080)
			}//END for
      		return size;
		};//_utf8Size = function(str)
		_getType = function(inp) {
			let match,key,cons,types,type = typeof inp;
		    if(type === 'object' && !inp) { return 'null'; }
			if(type === 'object') {
      			if(!inp.constructor) { return 'object'; }
      			cons = inp.constructor.toString();
      			match = cons.match(/(\w+)\(/);
      			if(match) { cons = match[1].toLowerCase(); }
      			types = ['boolean', 'number', 'string', 'array'];
      			for(key in types) {
        			if(cons === types[key]) {
          				type = types[key];
          				break;
        			}//if(cons == types[key])
      			}//END for
    		}//if(type === 'object')
    		return type;
		};//_getType = function(inp)
		type = _getType(mixed_value);
		if(type !== 'object' && type !== 'array') { end = ';'; }
		switch(type) {
			case 'function':
				val = '';
				break;
			case 'boolean':
				val = 'b:' + (mixed_value ? '1' : '0');
				break;
    		case 'number':
				val = (Math.round(mixed_value) === mixed_value ? 'i' : 'd') + ':' + mixed_value;
				break;
			case 'string':
				let lval = ARequest.escapeString(mixed_value);
				val = 's:' + _utf8Size(lval) + ':"' + lval + '"';
      			break;
		    case 'array':
		    case 'object':
				val = 'a';
			    for(key in mixed_value) {
        			if(mixed_value.hasOwnProperty(key)) {
          				ktype = _getType(mixed_value[key]);
          				if(ktype === 'function') { continue; }
          				okey = (key.match(/^[0-9]+$/) ? parseInt(key, 10) : key);
          				vals += ARequest.serialize(okey) + ARequest.serialize(mixed_value[key]);
          				count++;
        			}//if(mixed_value.hasOwnProperty(key))
      			}//END for
      			val += ':' + count + ':{' + vals + '}';
      			break;
    		case 'undefined':
      			// Fall-through
    		default:
      			// if the JS object has a property which contains a null value, the string cannot be unserialized by PHP
      			val = 'N';
      			break;
  		}//END switch
  		val += end;
  		return val;
	},//END phpSerialize
	setStyle : function(ob,styleString) {
		document.getElementById(ob).style.cssText = styleString;
	},//END SetStyle
	getForm : function(f) {
		let vals = {};
		for(let i=0; i<f.length; i++){
			if(f[i].id) { vals[f[i].id] = f[i].value; }
		}//for(let i=0; i<f.length; i++)
		return vals;
	},//END getForm
	jquiConfirmDialog : function(options,callback) {
		let cfg = {
			type: 'jqui',
			message: '',
			title: '',
			ok: '',
			cancel: '',
			targetid: ''
		};
		if(options && typeof(options)=='object') { $.extend(cfg,options); }
		if(typeof(cfg.targetid)!='string' || cfg.targetid.length===0) { cfg.targetid = getUid(); }
		if(typeof(cfg.message)!='string' || cfg.message.length===0) { cfg.message = '???'; }
		if(typeof(cfg.title)!='string') { cfg.title = ''; }
		if(typeof(cfg.ok)!='string' || cfg.ok.length===0) { cfg.ok = 'OK'; }
		if(typeof(cfg.cancel)!='string' || cfg.cancel.length===0) { cfg.cancel = 'Cancel'; }
		let lbuttons = {};
		lbuttons[decodeURIComponent(cfg.ok)] = function() { $(this).dialog('destroy'); callback(); };
		lbuttons[decodeURIComponent(cfg.cancel)] = function() { $(this).dialog('destroy'); };
		if(!$('#'+cfg.targetid).length) { $('body').append('<div id="'+cfg.targetid+'" style="display: none;"></div>'); }
		$('#'+cfg.targetid).html(decodeURIComponent(cfg.message));
		let minWidth = $(window).width()>500 ? 500 : ($(window).width() - 20);
		let maxWidth = $(window).width()>600 ? ($(window).width() - 80) : ($(window).width() - 20);
		$('#'+cfg.targetid).dialog({
			title: decodeURIComponent(cfg.title),
			dialogClass: 'ui-alert-dlg',
			minWidth: minWidth,
			maxWidth: maxWidth,
			minHeight: 'auto',
			resizable: false,
			modal: true,
			autoOpen: true,
			show: {effect: 'slide', duration: 300, direction: 'up'},
			hide: {effect: 'slide', duration: 300, direction: 'down'},
			closeOnEscape: true,
			buttons: lbuttons
	    });
	},//END jquiConfirmDialog
	doWork : function(interval,timer,data) {
		postMessage(data);
		ARequest.timers[timer] = setTimeout(function(){ ARequest.doWork(interval,timer,data); },interval);
	},//END function doWork
	onMessage : function(e) {
		let obj = JSON.parse(e.data);
		if(ARequest.timers[obj.timer]) { clearTimeout(ARequest.timers[obj.timer]); }
		ARequest.doWork(obj.interval,obj.timer,obj.data);
	}//END onMessage
};//END const ARequest
function nappEscapeElement(elementid) {
	let nval = String(val);
	nval = nval.replace(new RegExp('\\|','g'),ARequest.pipeChar);
	nval = nval.replace(new RegExp('~','g'),ARequest.tildeChar);
	return nval;
}//END function nappEscapeElement