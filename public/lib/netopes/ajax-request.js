/**
 * NETopes AJAX javascript file.
 * The NETopes AJAX javascript object used on ajax requests.
 * Copyright (c) 2013 - 2019 AdeoTEK Software SRL
 * License    LICENSE.md
 * @author     George Benjamin-Schonberger
 * @version    1.2.0.0
 */

const NAppRequest={
    onNAppRequestInitEvent: true,
    onNAppRequestCompleteEvent: true,
    actionSeparator: ']!r!s![',
    requestSeparator: ']!r![',
    doubleQuotesEscape: '``',
    serializeMode: 'json', // Values: php/json
    escapeStringMode: 'custom',
    procOn: {},
    timers: [],
    updateProcOn: function(addVal,loader) {
        if(loader) {
            let lKey='LOADER-' + String(loader).replaceAll(' ','_').replaceAll('\'','-').replaceAll('"','-');
            if(!NAppRequest.procOn.hasOwnProperty(lKey)) { NAppRequest.procOn[lKey]=0; }
            NAppRequest.procOn[lKey]+=addVal;
            if(NAppRequest.procOn[lKey]<=0) {
                NAppRequest.updateIndicator(loader,0);
            } else {
                NAppRequest.updateIndicator(loader,1);
            }//if(NAppRequest.procOn[lKey]<=0)
        }//if(loader)
    },//END updateProcOn
    updateIndicator: function(loader,newStatus) {
        if(!loader) { return; }
        if(typeof (loader)==='function') {
            loader(newStatus);
        } else {
            let event=new CustomEvent(newStatus===1 ? 'onNAppRequestLoaderOn' : 'onNAppRequestLoaderOff',{detail: loader});
            window.dispatchEvent(event);
        }//if(typeof(loader)=='function')
    },//END updateIndicator
    escapeString: function(val) {
        if(NAppRequest.escapeStringMode!=='custom') { return val; }
        let nValue=String(val);
        nValue=nValue.replace(new RegExp('"','g'),NAppRequest.doubleQuotesEscape);
        return nValue;
    },//END escapeString
    getRadioValueFromObject: function(obj,parent,name) {
        let result=null;
        let radios=null;
        if(typeof (obj)=='object' && obj!=null) {
            if(typeof (name)!='string' || name.length===0) {
                name=(typeof (obj.name)=='string' && obj.name.length>0 ? obj.name : '');
            }//if(typeof(name)!='string' || name.length==0)
            if(name.length>0) {
                if(typeof (parent)!='object' || parent==null) {
                    let dparent=obj.dataset.parent;
                    if(dparent) {
                        parent=document.getElementById(dparent);
                    } else {
                        parent=document.getElementsByTagName('body')[0];
                    }//if(dparent)
                }//if(typeof(parent)!='object')
                if(typeof (parent)=='object' && parent!=null) {
                    radios=parent.querySelectorAll('[name=' + name + ']');
                    for(let i=0; i<radios.length; i++) {
                        if(radios[i].checked) {
                            result=radios[i].value;
                            break;
                        }//if(radios[i].checked)
                    }//END for
                }//if(typeof(parent)=='object' && parent!=null)
            }//if(name.length>0)
        } else if(typeof (parent)=='object' && parent!=null && typeof (name)=='string' && name.length>0) {
            radios=parent.querySelectorAll('[name=' + name + ']');
            for(let i=0; i<radios.length; i++) {
                if(radios[i].checked) {
                    result=radios[i].value;
                    break;
                }//if(radios[i].checked)
            }//END for
        }//if(typeof(obj)=='object' && obj!=null)
        return result;
    },//END getRadioValueFromObject
    getFromObject: function(obj,property,attribute) {
        let val='';
        if(typeof (obj)!=='object' || obj==null || !property) { return val; }
        let dFormat;
        switch(property) {
            case 'option':
                if(typeof (attribute)==='string' && attribute.length>0) {
                    val=obj.options[obj.selectedIndex].getAttribute(attribute);
                } else {
                    val=obj.options[obj.selectedIndex].text;
                }//if(typeof(attribute)=='string' && attribute.length>0)
                break;
            case 'radio':
                if(typeof (attribute)==='string' && attribute.length>0) {
                    val=NAppRequest.getRadioValueFromObject(null,obj,attribute);
                }//if(typeof(attribute)=='string' && attribute.length>0)
                break;
            case 'visible':
                val=!!(obj.offsetWidth || obj.offsetHeight || obj.getClientRects().length) ? 1 : 0;
                break;
            case 'slider_start':
                val=$(obj).slider('values',0);
                break;
            case 'slider_end':
                val=$(obj).slider('values',1);
                break;
            case 'content':
                val=obj.innerHTML;
                break;
            case 'nvalue':
                val=obj.value;
                let nFormat=obj.getAttribute('data-format');
                if(nFormat) {
                    let farr=nFormat.split('|');
                    val=val.replaceAll(farr[3],'').replaceAll(farr[2],'');
                    if(farr[1]) { val=val.replaceAll(farr[1],'.'); }
                }//if(nFormat)
                break;
            case 'dvalue':
                val=obj.value;
                dFormat=obj.getAttribute('data-format');
                if(dFormat) {
                    let tFormat=obj.getAttribute('data-timeformat');
                    if(tFormat) { dFormat+=' ' + tFormat; }
                    let dt=getDateFromFormat(val,dFormat);
                    if(dt>0) {
                        if(dFormat.split(' ').length>1) {
                            val=formatDate(new Date(dt),'yyyy-MM-dd HH:mm:ss');
                        } else {
                            val=formatDate(new Date(dt),'yyyy-MM-dd 00:00:00');
                        }//if(dFormat.split(' ').length>1)
                    } else {
                        val='';
                    }//if(dt>0)
                }//if(dFormat)
                break;
            case 'fdvalue':
                val=obj.value;
                dFormat=obj.getAttribute('data-format');
                if(dFormat) {
                    let tFormat=obj.getAttribute('data-timeformat');
                    if(tFormat) { dFormat+=' ' + tFormat; }
                    let dt=getDateFromFormat(val,dFormat);
                    if(dt>0) {
                        let outFormat=obj.getAttribute('data-out-format');
                        if(outFormat) {
                            val=formatDate(new Date(dt),outFormat);
                        } else {
                            if(dFormat.split(' ').length>1) {
                                val=formatDate(new Date(dt),'yyyy-MM-dd HH:mm:ss');
                            } else {
                                val=formatDate(new Date(dt),'yyyy-MM-dd 00:00:00');
                            }//if(dFormat.split(' ').length>1)
                        }//if(outFormat)
                    } else {
                        val='';
                    }//if(dt>0)
                }//if(dFormat)
                break;
            case 'attr':
                if(typeof (attribute)==='string' && attribute.length>0) {
                    val=obj.getAttribute(attribute);
                } else {
                    val=obj[property];
                }//if(typeof(attribute)=='string' && attribute.length>0)
                break;
            case 'function':
                if(typeof (attribute)==='string' && attribute.length>0) {
                    if(window.hasOwnProperty(attribute)) {
                        val=window[attribute](obj);
                    } else {
                        console.log('NETopes Error: Unable to find method [' + attribute + ']!');
                    }//if(window.hasOwnProperty(attribute))
                }//if(typeof(attribute)==='string' && attribute.length>0)
                break;
            default:
                // Removed call "attr--" (replaced by "attr:")
                if(typeof (obj.type)==='string' && obj.type==='radio' && property==='value') {
                    val=NAppRequest.getRadioValueFromObject(obj,null,null);
                } else {
                    val=obj[property];
                }//if(typeof(obj.type)=='string' && obj.type=='radio')
                break;
        }//END switch
        if(val && typeof (val)==='string') { val=val.split(NAppRequest.actionSeparator).join(''); }
        return val;
    },//END getFromObject
    serialize: function(val) {
        if(NAppRequest.serializeMode==='php') { return NAppRequest.phpSerialize(val); }
        return JSON.stringify(val);
    },//END serialize
    getByName: function(obj,objName) {
        let val=null;
        let nName=obj.nodeName.toLowerCase();
        if(nName==='input' || nName==='select' || nName==='textarea') {
            switch(obj.getAttribute('type')) {
                case 'checkbox':
                    if(obj.checked===true) {
                        if(obj.value) {
                            val=NAppRequest.escapeString(obj.value.split(NAppRequest.actionSeparator).join(''));
                        } else {
                            val=0;
                        }//if(obj.value)
                    } else {
                        val=0;
                    }//if(obj.checked===true)
                    break;
                case 'radio':
                    let rval=document.querySelector('input[type=radio][name=' + objName + ']:checked').value;
                    if(rval) {
                        val=NAppRequest.escapeString(rval.split(NAppRequest.actionSeparator).join(''));
                    } else {
                        val=0;
                    }//if(rval)
                    break;
                default:
                    let pafprop=obj.getAttribute('data-paf-prop');
                    if(typeof (pafprop)=='string' && pafprop.length>0) {
                        let pp_arr=pafprop.split(':');
                        if(pp_arr.length>1) {
                            val=NAppRequest.getFromObject(obj,pp_arr[0],pp_arr[1]);
                        } else {
                            val=NAppRequest.getFromObject(obj,pp_arr[0]);
                        }//if(pp_arr.length>1)
                    } else {
                        val=NAppRequest.getFromObject(obj,'value');
                    }//if(typeof(pafprop)=='string' && pafprop.length>0)
                    val=NAppRequest.escapeString(val);
                    break;
            }//END switch
        } else {
            val=NAppRequest.escapeString(NAppRequest.getFromObject(obj,'content'));
        }//if(nName=='input' || nName=='select' || nName=='textarea')
        return val;
    },//END getByName
    getToArray: function(targetObj) {
        let aResult={};
        if(typeof (targetObj)!=='object' || targetObj==null) { return aResult; }
        let targetObjName=targetObj.getAttribute('name');
        if(!targetObjName || targetObjName.length<=0) { targetObjName=targetObj.getAttribute('data-name'); }
        if(!targetObjName) { return aResult; }
        let val=NAppRequest.getByName(targetObj,targetObjName);
        let names=targetObjName.replace(/^[\w|\-|_]+/,'$&]').replace(/]$/,'').split('][');
        if(names.length>0) {
            let newObj=val;
            for(let i=names.length - 1; i>=0; i--) {
                let tmpObj=newObj;
                if(names[i].length>0) {
                    newObj={};
                    newObj[names[i]]=tmpObj;
                } else {
                    newObj=[];
                    newObj.push(tmpObj);
                }
            }//END for
            aResult=newObj;
        } else {
            aResult[targetObjName]=val;
        }//if(names.length>0)
        return aResult;
    },//END getToArray
    getFormContent: function(id) {
        let result={};
        let frm=document.getElementById(id);
        let rbElements={};
        if(typeof (frm)==='object' && frm!==null) {
            let elements=frm.getElementsByClassName('postable');
            for(let i=0; i<elements.length; i++) {
                if(elements[i].nodeName.toLowerCase()==='input' && elements[i].getAttribute('type')==='radio') {
                    if(!rbElements.hasOwnProperty(elements[i].getAttribute('name'))) { continue; }
                    rbElements[elements[i].getAttribute('name')]=1;
                }//if(elements[i].nodeName.toLowerCase()=='input' && elements[i].getAttribute('type')=='radio')
                if(NAppRequest.serializeMode==='php') {
                    result=NAppRequest.getToArrayForPhp(elements[i],result);
                } else {
                    result=arrayMerge(result,NAppRequest.getToArray(elements[i]),true);
                }
            }//END for
        } else {
            console.log('Invalid form: ' + id);
        }//if(frm)
        return result;
    },//END getFormContent
    get: function(id,property,attribute) {
        let result=null;
        if(!property) {
            result=NAppRequest.escapeString(id);
        } else if(property==='form') {
            result=NAppRequest.getFormContent(id);
        } else {
            let eObj=document.getElementById(id);
            let val=null;
            if(typeof (eObj)!='object') {
                console.log('Invalid element: ' + id);
            } else if(eObj==null) {
                console.log('Null element: ' + id);
            } else {
                val=NAppRequest.getFromObject(document.getElementById(id),property,attribute);
            }//if(typeof(obj)!='object')
            if(typeof (val)!='string') {
                result=val;
            } else {
                result=NAppRequest.escapeString(val);
            }//if(typeof(val)!='string')
        }//if(!property)
        if(NAppRequest.serializeMode==='php') {
            return NAppRequest.serialize(result);
        }
        return result;
    },//END get
    put: function(content,targetId,action,property) {
        if(!targetId) return;
        if(property) {
            try {
                let ob=document.getElementById(targetId);
                if(action==='p') {
                    ob[property]=content + ob[property];
                } else if(action==='a') {
                    ob[property]+=content;
                } else if(property.split('.')[0]==='style') {
                    ob.style[property.split('.')[1]]=content;
                } else if(ob) {
                    ob[property]=content;
                }//if(act=='p')
            } catch(e) {}
        } else {
            window[targetId]=content;
        }//if(property)
    },//END put
    loadScripts: (scripts,async=true,defer=true) => {
        return new Promise((resolve,reject) => {
            let loaders=[];
            if(Array.isArray(scripts) && scripts.length>0) {
                for(let i=0; i<scripts.length; i++) {
                    if(typeof (scripts[i])!=='object' || typeof (scripts[i]['value'])!=='string' || scripts[i]['value'].length===0) { continue; }
                    let scriptType=scripts[i]['type'] || 'eval';
                    switch(scriptType) {
                        case 'file':
                            loaders.push(new Promise((fiResolve,fiReject) => {
                                try {
                                    let script=document.createElement('script');
                                    script.async=async;
                                    script.defer=defer;
                                    script.src=scripts[i]['value'] + (script.src.includes('?') ? '&' : '?') + 'v=' + (new Date()).getTime();
                                    script.onload=script.onreadystatechange=function() {
                                        if(!this.readyState || /loaded|complete/.test(this.readyState)) {
                                            this.onload=null;
                                            this.onreadystatechange=null;
                                            fiResolve();
                                        }
                                    };
                                    document.getElementsByTagName('head')[0].appendChild(script);
                                } catch(fe) {
                                    console.log(fe);
                                    console.log(scripts[i]['value']);
                                    fiReject();
                                }//END try
                            }));
                            break;
                        case 'eval':
                            loaders.push(new Promise((evResolve,evReject) => {
                                try {
                                    Function(scripts[i]['value'])();
                                    evResolve();
                                } catch(ee) {
                                    console.log(ee);
                                    console.log(scripts[i]['value']);
                                    evReject();
                                }//END try
                            }));
                            break;
                    }//END switch
                }//END for
            }//if(Array.isArray(scripts) && scripts.length>0)
            if(loaders.length>0) {
                Promise.all(loaders).then(resolve,reject).catch(err => console.log(err));
            } else {
                resolve();
            }//if(loaders.length>0)
        });
    },//END loadScripts
    sendRequest: function(targetId,action,property,content,loader,async,callback,callType,jsScripts) {
        let ajaxRequest=new Promise((aResolve,aReject) => {
            let req=new XMLHttpRequest();
            let lAsync=typeof (async)!=='undefined' ? ((!(async===0 || async===false || async==='0'))) : true;
            req.open('POST',NAPP_TARGET,lAsync);
            req.setRequestHeader('Content-type','application/x-www-form-urlencoded;charset=UTF-8');
            req.onreadystatechange=function() {
                if(req.readyState===4) {
                    if(req.status===200) {
                        aResolve({responseText: this.responseText,htmlTarget: this.getResponseHeader('HTMLTargetId')});
                    } else {
                        console.log(req);
                        aReject();
                    }//if(req.status==200)
                }//if(req.readyState==4)
            };//req.onreadystatechange=function()
            req.send(content);
        });
        ajaxRequest.then((resolution) => {
            return new Promise((cResolve,cReject) => {
                let actions=resolution.responseText.split(NAppRequest.actionSeparator);
                let content=actions[0] + (actions[2] ? actions[2] : '');
                if(typeof (resolution.htmlTarget)==='string' && resolution.htmlTarget.length>0) {
                    NAppRequest.put(content,resolution.htmlTarget,action,property);
                } else if(targetId) {
                    NAppRequest.put(content,targetId,action,property);
                } else {
                    let event=new CustomEvent('onNAppRequestDataReceived',{detail: content});
                    window.dispatchEvent(event);
                }//if(typeof(htmlTarget)==='string' && htmlTarget.length>0)
                if(actions[1]) {
                    try {
                        Function(actions[1])();
                    } catch(ee) {
                        console.log(ee);
                        console.log(actions[1]);
                    }//END try
                }//if(actions[1])
                if(jsScripts) {
                    NAppRequest.loadScripts(jsScripts).then(cResolve,cReject);
                } else {
                    cResolve();
                }
            });
        }).then(function() {
            NAppRequest.updateProcOn(-1,loader);
            if(NAppRequest.onNAppRequestCompleteEvent) {
                let event=new CustomEvent('onNAppRequestComplete',{detail: callType});
                window.dispatchEvent(event);
            }//if(NAppRequest.onNAppRequestCompleteEvent)
            if(callback) {
                if(callback instanceof Function) {
                    callback();
                } else if(typeof (callback)==='string') {
                    try {
                        Function(callback)();
                    } catch(ee) {
                        console.log(ee);
                        console.log(callback);
                    }//END try
                }//if(callback instanceof Function)
            }//if(callback)
        });
    },//END sendRequest
    executeRequest: function(sessionId,requestId,targetId,action,property,loader,async,params,encrypted,jParams,eParam,postParams,callback,triggerOnInitEvent,callType,jsScripts) {
        if(triggerOnInitEvent && NAppRequest.onNAppRequestInitEvent) {
            let event=new CustomEvent('onNAppRequestInit',{detail: {callType: callType,target: targetId,action: action,property: property}});
            window.dispatchEvent(event);
        }//if(triggerOnInitEvent && NAppRequest.onNAppRequestInitEvent)
        NAppRequest.updateProcOn(1,loader);
        if(sessionId===decodeURIComponent(sessionId)) { sessionId=encodeURIComponent(sessionId); }
        let jParamsString='';
        if(typeof (jParams)==='object') {
            for(let pn in jParams) { jParamsString+='var ' + pn + ' = ' + JSON.stringify(jParams[pn]) + '; '; }
        }//if(typeof(jParams)=='object')
        if(typeof (params)==='string') {
            params=encrypted===1 ? GibberishAES.dec(params,requestId) : params;
            params=eval(jParamsString + ' (' + params + ')');
        } else if(typeof (params)!=='object') {
            console.log('NAppRequest error: invalid parameters!');
            return false;
        }//if(typeof(params)==='string')
        let requestString=JSON.stringify(params);
        if(typeof (eParam)==='object') {
            for(let ep in eParam) { requestString=requestString.replace(new RegExp('#' + ep + '#','g'),eParam[ep]); }
        }//if(typeof(eParam)=='object')
        let postData='req=' + encodeURIComponent((NAPP_UID ? GibberishAES.enc(requestString,NAPP_UID) : requestString)) + NAppRequest.requestSeparator + sessionId + NAppRequest.requestSeparator + (requestId || '') + '&serializemode=' + NAppRequest.serializeMode + '&phash=' + window.name + postParams;
        if(encrypted===1 && typeof (callback)==='string') { callback=GibberishAES.dec(callback,requestId); }
        let endJsScripts=[];
        if(Array.isArray(jsScripts) && jsScripts.length>0) {
            for(let i=0; i<jsScripts.length; i++) {
                if(typeof (jsScripts[i])!=='object' || !jsScripts[i]['executeAfter']) {
                    continue;
                }
                endJsScripts.push(jsScripts[i]);
                jsScripts.splice(i,1);
            }//END for
            NAppRequest.loadScripts(jsScripts).then(NAppRequest.sendRequest(targetId,action,property,postData,loader,async,callback,callType || 'execute',endJsScripts));
        } else {
            NAppRequest.sendRequest(targetId,action,property,postData,loader,async,callback,callType || 'execute',endJsScripts);
        }//if(Array.isArray(jsScripts) && jsScripts.length>0)
    },//END executeRequest
    execute: function(targetId,action,property,params,encrypted,loader,async,triggerOnInitEvent,confirm,jParams,eParam,callback,postParams,sessionId,requestId,jsScripts,callType) {
        if(confirm) {
            let cObj=false;
            if(encrypted===1) {
                eval('cObj = ' + GibberishAES.dec(confirm,requestId));
            } else {
                cObj=typeof (confirm)==='object' ? confirm : {type: 'std',message: confirm};
            }//if(encrypted==1)
            if(cObj && typeof (cObj)==='object') {
                if(cObj.type==='jqui') {
                    NAppRequest.jquiConfirmDialog(cObj,function() {
                        NAppRequest.executeRequest(sessionId,requestId,targetId,action,property,loader,async,params,encrypted,jParams,eParam,postParams,callback,triggerOnInitEvent,callType,jsScripts);
                    });
                } else {
                    if(confirm(decodeURIComponent(cObj.message))!==true) { return; }
                }//if(cObj.type==='jqui')
            }//if(cObj && typeof(cObj)=='object')
        }//if(confirm)
        NAppRequest.executeRequest(sessionId,requestId,targetId,action,property,loader,async,params,encrypted,jParams,eParam,postParams,callback,triggerOnInitEvent,callType,jsScripts);
    },//END execute
    executeFromString: function(data) {
        let objData={};
        eval('objData = ' + data);
        let sParams=GibberishAES.dec(objData.params,'xSTR');
        sParams=eval('\'' + sParams.replaceAll('\\\'','\'') + '\'');
        let callType=objData.callType ? objData.callType : 'executeFromString';
        NAppRequest.execute(objData.targetId,objData.action,objData.property,sParams,objData.encrypted,objData.loader,objData.async,objData.triggerOnInitEvent,objData.confirm,objData.jParams,objData.eParam,objData.callback,objData.postParams,objData.sessionId,objData.requestId,objData.jsScripts,callType);
    },//END executeFromString
    executeFromObject: function(data) {
        let objData=Object.assign({},{
            targetId: '',
            action: 'r',
            property: 'innerHTML',
            params: '',
            encrypted: 0,
            loader: 1,
            async: 1,
            triggerOnInitEvent: 1,
            confirm: undefined,
            jParams: {},
            eParam: {},
            callback: false,
            postParams: '',
            sessionId: '',
            requestId: '',
            jsScripts: {},
            callType: 'executeFromString',
        },data);
        NAppRequest.execute(objData.targetId,objData.action,objData.property,objData.params,objData.encrypted,objData.loader,objData.async,objData.triggerOnInitEvent,objData['confirm'],objData.jParams,objData.eParam,objData['callback'],objData.postParams,objData.sessionId,objData.requestId,objData.jsScripts,objData.callType);
    },//END executeFromObject
    timerExecute: function(interval,timer,data) {
        if(data && timer) {
            NAppRequest.executeFromString(GibberishAES.dec(data,timer));
            if(NAppRequest.timers[timer]) { clearTimeout(NAppRequest.timers[timer]); }
            NAppRequest.timers[timer]=setTimeout(function() { NAppRequest.timerExecute(interval,timer,data); },interval);
        }//if(data && timer)
    },//END timerExecute
    executeRepeated: function(interval,targetId,action,property,params,encrypted,loader,async,triggerOnInitEvent,confirm,jParams,eParam,callback,postParams,sessionId,requestId,jsScripts,callType) {
        if(interval && interval>0) {
            let lTimer=requestId + (new Date().getTime());
            let execData=GibberishAES.enc(JSON.stringify({
                targetId: targetId,
                action: action,
                property: property,
                params: GibberishAES.enc(params,'xSTR'),
                encrypted: encrypted,
                loader: loader,
                async: async,
                triggerOnInitEvent: triggerOnInitEvent,
                confirm: confirm,
                jParams: jParams,
                eParam: eParam,
                callback: callback,
                postParams: postParams,
                sessionId: sessionId,
                requestId: requestId,
                jsScripts: jsScripts,
                callType: callType || 'executeRepeated'
            }),lTimer);
            NAppRequest.timerExecute(interval,lTimer,execData);
        }//if(interval && interval>0)
    },//END executeRepeated
    getToArrayForPhp: function(obj,initial) {
        if(typeof (obj)!=='object' || obj==null) { return initial; }
        let aResult=null;
        let objName=obj.getAttribute('name');
        if(!objName || objName.length<=0) { objName=obj.getAttribute('data-name'); }
        if(objName) {
            let names=objName.replace(/^[\w|\-|_]+/,'$&]').replace(/]$/,'').split('][');
            let val=NAppRequest.getByName(obj,objName);
            if(names.length>0) {
                for(let i=names.length - 1; i>=0; i--) {
                    let tmp;
                    if(names[i]!=='') {
                        tmp={};
                        tmp['#k#_' + names[i]]=(i===(names.length - 1) ? val : aResult);
                    } else {
                        tmp=[(i===(names.length - 1) ? val : aResult)];
                    }//if(names[i]!='')
                    aResult=tmp;
                }//END for
            } else {
                aResult=[val];
            }//if(names.length>0)
        }//if(objName)
        if(typeof (initial)!='object') { return aResult; }
        return arrayMerge(initial,aResult,true);
    },//END getToArrayForPhp
    phpSerialize: function(mixed_value) {
        let val,key,okey,
            ktype='',
            vals='',
            count=0,
            end='';
        let _utf8Size=function(str) {
            let size=0,
                l=str.length,
                code='';
            for(let i=0; i<l; i++) {
                code=str.charCodeAt(i);
                if(code<0x0080) {
                    size+=1;
                } else if(code<0x0800) {
                    size+=2;
                } else {
                    size+=3;
                }//if(code < 0x0080)
            }//END for
            return size;
        };//_utf8Size = function(str)
        let _getType=function(inp) {
            let match,key,cons,types,type=typeof inp;
            if(type==='object' && !inp) { return 'null'; }
            if(type==='object') {
                if(!inp.constructor) { return 'object'; }
                cons=inp.constructor.toString();
                match=cons.match(/(\w+)\(/);
                if(match) { cons=match[1].toLowerCase(); }
                types=['boolean','number','string','array'];
                for(key in types) {
                    if(cons===types[key]) {
                        type=types[key];
                        break;
                    }//if(cons == types[key])
                }//END for
            }//if(type === 'object')
            return type;
        };//_getType = function(inp)
        let type=_getType(mixed_value);
        if(type!=='object' && type!=='array') { end=';'; }
        switch(type) {
            case 'function':
                val='';
                break;
            case 'boolean':
                val='b:' + (mixed_value ? '1' : '0');
                break;
            case 'number':
                val=(Math.round(mixed_value)===mixed_value ? 'i' : 'd') + ':' + mixed_value;
                break;
            case 'string':
                let lval=NAppRequest.escapeString(mixed_value);
                val='s:' + _utf8Size(lval) + ':"' + lval + '"';
                break;
            case 'array':
            case 'object':
                val='a';
                for(key in mixed_value) {
                    if(mixed_value.hasOwnProperty(key)) {
                        ktype=_getType(mixed_value[key]);
                        if(ktype==='function') { continue; }
                        okey=(key.match(/^[0-9]+$/) ? parseInt(key,10) : key);
                        vals+=NAppRequest.serialize(okey) + NAppRequest.serialize(mixed_value[key]);
                        count++;
                    }//if(mixed_value.hasOwnProperty(key))
                }//END for
                val+=':' + count + ':{' + vals + '}';
                break;
            case 'undefined':
            // Fall-through
            default:
                // if the JS object has a property which contains a null value, the string cannot be unserialized by PHP
                val='N';
                break;
        }//END switch
        val+=end;
        return val;
    },//END phpSerialize
    setStyle: function(ob,styleString) {
        document.getElementById(ob).style.cssText=styleString;
    },//END SetStyle
    jquiConfirmDialog: function(options,callback) {
        let cfg={
            type: 'jqui',
            message: '',
            title: '',
            ok: '',
            cancel: '',
            targetid: ''
        };
        if(options && typeof (options)=='object') { $.extend(cfg,options); }
        if(typeof (cfg.targetid)!='string' || cfg.targetid.length===0) { cfg.targetid=getUid(); }
        if(typeof (cfg.message)!='string' || cfg.message.length===0) { cfg.message='???'; }
        if(typeof (cfg.title)!='string') { cfg.title=''; }
        if(typeof (cfg.ok)!='string' || cfg.ok.length===0) { cfg.ok='OK'; }
        if(typeof (cfg.cancel)!='string' || cfg.cancel.length===0) { cfg.cancel='Cancel'; }
        let lButtons={};
        lButtons[decodeURIComponent(cfg.ok)]=function() {
            $(this).dialog('destroy');
            callback();
        };
        lButtons[decodeURIComponent(cfg.cancel)]=function() { $(this).dialog('destroy'); };
        let $element=$('#' + cfg.targetid);
        if(!$element.length) { $('body').append('<div id="' + cfg.targetid + '" style="display: none;"></div>'); }
        $element.html(decodeURIComponent(cfg.message));
        let minWidth=$(window).width()>500 ? 500 : ($(window).width() - 20);
        let maxWidth=$(window).width()>600 ? ($(window).width() - 80) : ($(window).width() - 20);
        $element.dialog({
            title: decodeURIComponent(cfg.title),
            dialogClass: 'ui-alert-dlg',
            minWidth: minWidth,
            maxWidth: maxWidth,
            minHeight: 'auto',
            resizable: false,
            modal: true,
            autoOpen: true,
            show: {effect: 'slide',duration: 300,direction: 'up'},
            hide: {effect: 'slide',duration: 300,direction: 'down'},
            closeOnEscape: true,
            buttons: lButtons
        });
    },//END jquiConfirmDialog
    doWork: function(interval,timer,data) {
        postMessage(data);
        NAppRequest.timers[timer]=setTimeout(function() { NAppRequest.doWork(interval,timer,data); },interval);
    },//END function doWork
    onMessage: function(e) {
        let obj=JSON.parse(e.data);
        if(NAppRequest.timers[obj.timer]) { clearTimeout(NAppRequest.timers[obj.timer]); }
        NAppRequest.doWork(obj.interval,obj.timer,obj.data);
    }//END onMessage
};