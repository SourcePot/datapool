jQuery(document).ready(function(){

    function formatTimeStamp(timestamp,timezone){
        const dtFormat=new Intl.DateTimeFormat('en-GB',{
            dateStyle:'short',
            timeStyle:'medium',
            timeZone: timezone
        });
        return dtFormat.format(new Date(timestamp*1000));
    }

    /** HTML-CONTAINER MANAGEMENT **/	
    function containerBusy(containerId,isBusy){
        if (isBusy===true){
            jQuery('div[busy-id=busy-'+containerId+']').show();
        } else {
            jQuery('div[busy-id=busy-'+containerId+']').hide();
        }
    }

    var containerArr=[];
    function initContainerArr(){
        jQuery('article[container-id]').each(function(containerIndex){
            let containerId=jQuery(this).attr('container-id');
            containerArr.push(containerId);
        });
    }

    var containerMonitorBusy=false;
    function containerMonitor(){
        if (containerMonitorBusy){return false;}
        if (containerArr.length==0){
            initContainerArr();
            if (containerArr.length==0){return true;}
        }
        containerMonitorBusy=true;
        var containerId=containerArr.pop();
        jQuery.ajax({method:"POST",
                    url:'js.php',
                    context:document.body,
                    data:{'function':'containerMonitor','container-id':containerId},
                    dataType: "json"
                }).done(function(data){
                    if (!data['arr']['isUp2date']){
                        containerId=data['arr']['container-id'];
                        data=jQuery(data).serializeArray();
                        reloadContainer(containerId,data);
                    }
                }).fail(function(data){
                    console.log(data);	
                }).always(function(){
                    containerMonitorBusy=false;
                });
        return true;
    }
    let attachedEvents={};
    attachMissingContainerEvents()
    function attachMissingContainerEvents(){
        jQuery('article[container-id]').each(function(containerIndex){
            let containerId=jQuery(this).attr('container-id');
            if (attachedEvents[containerId]===undefined){
                attachEventsToContainer(containerId);
                attachedEvents[containerId]=true;
            }
        });
    }
    function attachEventsToContainer(containerId){
        let wrapper=jQuery('article[container-id='+containerId+']');
        jQuery(wrapper).find('[type=submit],button').each(function(i){
            if (jQuery(this).attr('excontainer')===undefined){
                jQuery(this).unbind('click');
                jQuery(this).on('click',function(e){
                    e.preventDefault();
                    e.stopPropagation();
                    submitForm(this,containerId);
                });
            }
        });
        jQuery(wrapper).find('textarea,[type=text],[type=password],[type=email],[type=tel],[type=file]').each(function(i){
            if (jQuery(this).attr('excontainer')===undefined){
                jQuery(this).unbind('focusout');
                jQuery(this).focusout(function(e){
                    jQuery('button[container-id=btn-'+containerId+']').click();
                });
            }
        });
        jQuery(wrapper).find('[type=date],[type=datetime-local],[type=range],[type=checkbox]').each(function(i){
            if (jQuery(this).attr('excontainer')===undefined){
                jQuery(this).unbind('change');
                jQuery(this).on('change',function(e){
                    jQuery('button[container-id=btn-'+containerId+']').click();
                });
            }
        });
        jQuery(wrapper).find('[type=date],[type=datetime-local],[type=text]').each(function(i){
            if (jQuery(this).attr('excontainer')===undefined){
                jQuery(this).unbind('keypress');
                jQuery(this).on('keypress',function(e){
                    if (e.which==13){
                        e.preventDefault();
                        e.stopPropagation();
                        jQuery('button[container-id=btn-'+containerId+']').click();
                    }
                });
            }
        });
        jQuery(wrapper).find('select').each(function(i){
            if (jQuery(this).attr('excontainer')===undefined){
                jQuery(this).unbind('change');
                jQuery(this).on('change',function(e){
                    e.preventDefault();
                    e.stopPropagation();
                    jQuery('button[container-id=btn-'+containerId+']').click();
                });
            }
        });
    }
    function reloadContainer(containerId,data){
        var formData=new FormData();
        formData.append('function','container');
        formData.append('container-id',containerId);
        postRequest(containerId,formData);
        checkToolboxUpdate(containerId);
    }
    function submitForm(trigger,containerId){
        containerBusy(containerId,true);
        var formData=new FormData($('form')[0]);
        formData.append(trigger.name,trigger.value);
        formData.append('function','container');
        formData.append('container-id',containerId);
        postRequest(containerId,formData);
    }
    function postRequest(containerId,data){
        containerBusy(containerId,true);
        $.ajax({
            url:'js.php',
            type:'POST',
            data: data,
            cache: false,
            contentType: false,
            processData: false,
            xhr: function(){
                var myXhr=$.ajaxSettings.xhr();
                if (myXhr.upload){
                    myXhr.upload.addEventListener('progress',function(e){
                        //console.log(e.loaded+' '+e.total);
                    },false);		
                    myXhr.addEventListener('load',function(e){
                        try{
                            var jsonResp=JSON.parse(this.response);
                        } catch(e){
                            var jsonResp={'html':this.response};
                        }
                        jQuery('article[container-id='+containerId+']').replaceWith(jsonResp['html']);
                        attachEventsToContainer(containerId);
                        jQuery('[id=js-refresh]').click();
                        addPlotHoverFeatures(containerId);
                        containerBusy(containerId,false);
                    },false);
                    myXhr.addEventListener('error',function(e){
                        console.log(this.response);
                    },false);
                    myXhr.addEventListener('loaded',function(e){
                        containerBusy(containerId,false);
                    },false);
                }				
                return myXhr;
            }
        });	
    }
    /** Plots **/
    jQuery('div.signal-bar').hover(function(){
        hoveringSignalBar(jQuery(this));
    });

    function addPlotHoverFeatures(containerId){
        jQuery('article[container-id='+containerId+'] div.signal-bar').hover(function(){
            hoveringSignalBar(this);
        });
    }

    function hoveringSignalBar(bar){
        // get bar & plot id
        let barLeft=parseInt(jQuery(bar).css('left')),barBottom=parseInt(jQuery(bar).css('bottom')),barHeight=parseInt(jQuery(bar).css('height'));
        let plot=jQuery(bar).parent().first();
        let plotIdComps=jQuery(plot).attr('id').split('-');
        // get values
        jQuery('#'+plotIdComps[0]+'-label').text(jQuery(bar).attr('data-label'));
        var dateTime=formatTimeStamp(parseInt(jQuery(bar).attr('data-timestamp')),jQuery('#'+plotIdComps[0]+'-timezone').text());
        jQuery('#'+plotIdComps[0]+'-timestamp').text(dateTime);
        var value=jQuery(bar).attr('data-value');
        jQuery('#'+plotIdComps[0]+'-value').text(value);
        // get cursors
        let cursorX=jQuery(plot).children('.signal-cursor-x');
        let cursorY=jQuery(plot).children('.signal-cursor-y');
        jQuery(cursorX).fadeIn(100).css({'left':barLeft});
        if (value<0){
            jQuery(cursorY).fadeIn(100).css({'bottom':(barBottom)});
        } else {
            jQuery(cursorY).fadeIn(100).css({'bottom':(barBottom+barHeight)});
        }
    }

    /** TOOLBOX **/
    function checkToolboxUpdate(containerId){
        var needsUpdate=jQuery('details.toolbox').children('[container-id="'+containerId+'"]').length;
        if (needsUpdate>0){
            jQuery('details.toolbox').css({'z-index':'100'}).prop("open",true);
        }
    }

	let heartbeats=0;
	(function heartbeat(){
    	setTimeout(heartbeat,100);
		heartbeats++;
        if (heartbeats%6===0){
            containerMonitor();
        } else if (heartbeats%5===0){
            
        }
	})();

    jQuery('[id=js-refresh]').on('click',function(element){
        attachMissingContainerEvents();
    });

});