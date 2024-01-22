jQuery(document).ready(function(){
	
/** BASIC PAGE CONTENT STYLING **/
	jQuery('article').fadeIn(300);
	
	jQuery('div.second-menu').css({'height':0,'overflow':'hidden'});
	jQuery('a.first-menu').on('click',function(element){
        element.preventDefault();
		if (jQuery('div.second-menu').css('height').length>3){
			collapseMenu(true);
		} else {
			jQuery('div.second-menu').css({'height':'auto'});
		}
	});
	jQuery('select.menu,ul.menu').on('click',function(element){
		collapseMenu(false);
	});
	function collapseMenu(animate){
		if (animate){
			jQuery('div.second-menu').css({'height':'auto'}).animate({'height':0},200);
		} else {
			jQuery('div.second-menu').css({'height':0});
		}
	}
	addSafetyCoverEvent();
	function addSafetyCoverEvent(){
		jQuery('p.cover').unbind('click');
		jQuery('p.cover').bind('click',safetyCoverClick);
	}
	function safetyCoverClick(element){
		let height=jQuery(this).parent('.cover-wrapper').height()+'px';
		jQuery(this).animate({'top':height},500).delay(5000).animate({'top':0},200);
	}

    function callbackDummy(data){
        console.log(data);
    }

/** STEP-BY-STEP  ENTRY PRESENTATION, used e.g. by the forum **/
	var busyLoadingEntry=false;
	function loadNextEntry(){
		let obj=jQuery('[function=loadEntry]').first();
		if (isVisible(obj)===true && busyLoadingEntry===false){
			let arr={'selector':{'Source':jQuery(obj).attr('source'),'EntryId':jQuery(obj).attr('entry-id')},
					 'settings':{'presentEntry':'Forum'},
					 'function':jQuery(obj).attr('function'),
					 'replaceSelector':'[function=loadEntry][entry-id='+jQuery(obj).attr('entry-id')+']'
					};
			loadNextSelectedView(arr);
		}
	}
	const loadNextSelectedView=function(arr){
		busyLoadingEntry=true;
		jQuery.ajax({
			method:"POST",
			url:'js.php',
			context:document.body,
			data:arr,
			dataType: "json"
		}).done(function(data){
            if ('htmlSelector' in arr){
				jQuery(arr['htmlSelector']).html(data['html']);
			} else {
				jQuery(arr['replaceSelector']).replaceWith(data['html']);
			}
			attachMissingContainerEvents();
			resetAll();
		}).fail(function(data){
			console.log(data);
		}).always(function(){
			busyLoadingEntry=false;
		});
	}

/** GeoTools dynamic map **/
    var mapEntryObjArr={};
    var mapEntries={};
    loadDynamicMap()
    
    function loadDynamicMap(){
        mapEntryObjArr=Object.values(jQuery('[entry-id]'));
        mapEntries={};
        if (mapEntryObjArr.length>0 && jQuery('[function=getDynamicMap]').length>0){loadMapEntry();}
    }
    
    function loadMapEntry(){
        let obj=mapEntryObjArr.shift();
        let arr={'Source':jQuery(obj).attr('source'),
                 'EntryId':jQuery(obj).attr('entry-id'),
                 'function':'entryById',
                };
        if (typeof arr['Source']!=='undefined' && typeof arr['EntryId']!=='undefined'){
            jQuery.ajax({
                method:"POST",
                url:'js.php',
                context:document.body,
                data:arr,
                dataType:"json"
            }).done(function(data){
                mapEntries[data['arr']['EntryId']]=data['arr'];
            }).fail(function(data){
                console.log(data);
            }).always(function(){
                if (mapEntryObjArr.length>0){loadMapEntry();} else {entries2dynamicMap();}
            });
        } else {
            if (mapEntryObjArr.length>0){loadMapEntry();} else {entries2dynamicMap();}
        }
    }
    function entries2dynamicMap(){
        var map=false;
        map=L.map('dynamic-map');
        var location=[51.505,0];
        map.setView(location,4);
        const tiles=L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{
                        maxZoom: 19,
                        attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
                        }).addTo(map);
        var selectBtns={};
        for (const [key,value] of Object.entries(mapEntries)){
            if (typeof value['Params']['Geo']!=='undefined'){
                var selectId=jQuery('button[entry-id='+value['EntryId']+']:contains("✦")').first().attr('id');
                location=[parseFloat(value['Params']['Geo']['lat']),parseFloat(value['Params']['Geo']['lon'])];
                var marker=L.marker(location).addTo(map);
                var tooltip=L.tooltip().setLatLng(location).setContent(value['Folder']+'<br/>'+value['Name']).addTo(map);
                selectBtns[jQuery(marker).attr('_leaflet_id')]=selectId;
                marker.on('click',function(e){
                    var selectBtnSelector='#'+selectBtns[jQuery(this).attr('_leaflet_id')];
                    console.log(value);
					jQuery(selectBtnSelector).click();
                });
            }
        }
        map.setView(location,4);
        
    }
/** CLIPBOARD **/
    jQuery('button[id^=clipboard]').on('click',function(e){
        e.preventDefault();
        let id=jQuery(this).attr('id').split('-').pop();
        let text=document.getElementById(id).innerHTML;
        jQuery('button[id^=clipboard]').parent().css({backgroundColor:""});
        try{
            navigator.clipboard.writeText(text);
            jQuery(this).parent().css({backgroundColor:"#8c4"});
        } catch (err){
            jQuery(this).css({backgroundColor:"#faa"});
        }
        
    });

    
/** CLIPBOARD **/
    jQuery('button[title=Print]').on('click',function(e){
        e.preventDefault();
        console.log(jQuery(this).parent('article'));
        window.print();
    });

/** EMOJI PROCESSING **/
	addEmojiEvent();
	function addEmojiEvent(){
		jQuery('a.emoji').unbind('click');
		jQuery('a.emoji').bind('click',emojiClick);
	}
	function emojiClick(element){
		element.preventDefault();
		element.stopPropagation();
		let selector='#'+jQuery(this).attr('target');
		let text=jQuery(selector).val()+jQuery(this).html();
		jQuery(selector).val(text);
	}


/** HTML-CONTAINER MANAGEMENT **/	
	initTriggerIds();
	function initTriggerIds(){
		jQuery('[trigger-id]').each(function(i){
			let btnSelector='#'+jQuery(this).attr('trigger-id');
			jQuery(this).unbind('change');
			jQuery(this).on('change',function(e){
				jQuery(btnSelector).click();
			});
			jQuery(btnSelector).hide();
		});
	}
	function containerBusy(containerId,isBusy){
		if (isBusy===true){
			jQuery('div[busy-id=busy-'+containerId+']').show();
		} else {
			jQuery('div[busy-id=busy-'+containerId+']').hide();
		}
	}
    var containerMonitorBusy={};
    const containerMonitor=function(){
        jQuery('article[container-id]').each(function(containerIndex){
            // loop through all containers present on the page
			let containerId=jQuery(this).attr('container-id');
            if (typeof containerMonitorBusy[containerId]==="undefined"){
                containerMonitorBusy[containerId]=false;
            }
            if (containerMonitorBusy[containerId]===false){
                containerMonitorBusy[containerId]=true;
                jQuery.ajax({
                    method:"POST",
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
                    containerMonitorBusy[containerId]=false;
                });
            }
		});
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
		jQuery(wrapper).find('[type=text],[type=password],[type=email],[type=tel],[type=file]').each(function(i){
			if (jQuery(this).attr('excontainer')===undefined){
				jQuery(this).unbind('focusout');
				jQuery(this).focusout(function(e){
					jQuery('button[container-id=btn-'+containerId+']').click();
				});
			}
		});
		jQuery(wrapper).find('[type=range]').each(function(i){
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
						/*
						if (e.lengthComputable){
							$('progress').attr({
								value: e.loaded,
								max: e.total,
							});
						}
						*/
					},false);		
					myXhr.addEventListener('load',function(e){
						try{
							var jsonResp=JSON.parse(this.response);
							jQuery('article[container-id='+containerId+']').replaceWith(jsonResp['html']);
							attachEventsToContainer(containerId);
							resetAll();
						} catch(e){
							console.log(this.response);
						}
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


/** IMAGE VIEWER **/
	let imgs={};
	let imgId2index={};
	loadImageData();
	function loadImageData(){
		imgs={};
		imgId2index={};
		let index=0;
		jQuery('div[class*=preview]').each(function(imgIndex){
			let img={'id':jQuery(this).attr('id'),'Source':jQuery(this).attr('source'),'EntryId':jQuery(this).attr('entry-id'),'index':index};
			imgs[index]=img;
			imgId2index[img['id']]=index;
			jQuery(this).unbind('click').bind('click',loadImage);
			index++;
		});	
	}
	function loadImage(item){
		let index;
		if (typeof(item)==='object'){
			let id=jQuery(this).attr('id');
			index=imgId2index[id];
		} else {
			index=item;
		}
		let data={'loadImage':imgs[index]};
		jQuery.ajax({
			method:"POST",
			url:'js.php',
			context:document.body,
			data:data,
			dataType: "json"
		}).done(function(data){
			addImageToOverlay(data);
		}).fail(function(data){
			console.log(data);
		});
	}
	function addImageToOverlay(img){
		let index=parseInt(img['index']);
		let lastIndex=Object.keys(imgs).length-1,page=index+1,lastPage=lastIndex+1;
		let html='';
		let imgBtn='<a style="float:inherit;" id="prev-img-btn">&#10096;&#10096;</a><a style="float:inherit;" id="next-img-btn">&#10097;&#10097;</a>';
		html=html+'<p style="float:inherit;" id="img-index">'+page+' of '+lastPage+'</p>';
		html=html+'<img style="float:inherit;" id="overlay-image" src="'+img['src']+'" class="'+img['class']+'" title="'+img['title']+'" index="'+index+'"></img>';
		html=html+'<p style="float:inherit;" id="overlay-title">'+img['title']+'</p>';
		html=html+imgBtn;
		let htmlObj=$(html);
		jQuery('#overlay').html(htmlObj).fadeIn(500);
		jQuery('#overlay,#overlay-image').on('click',function(e){
			jQuery('#overlay').hide();
		});
		jQuery('#prev-img-btn').on('click',function(e){
			e.stopPropagation();
			if (index==0){index=lastIndex;} else {{index--;}}
			loadImage(index);
		});
		jQuery('#next-img-btn').on('click',function(e){
			e.stopPropagation();
			if (index>=lastIndex){index=0;} else {{index++;}}
			loadImage(index);
		});
	}
	document.addEventListener("keydown",function(e){
		if (e.key=="ArrowLeft"){
			if (jQuery('#overlay').is(":visible")){
				jQuery('#prev-img-btn').click();
			} else {
				jQuery("[id^='getImageShuffle']").filter("[id$='-next']").click();
			}
		} else if (e.key=="ArrowRight"){
			if (jQuery('#overlay').is(":visible")){
				jQuery('#next-img-btn').click();
			} else {
				jQuery("[id^='getImageShuffle']").filter("[id$='-prev']").click();
			}
		}
	});


/** IMAGE SHUFFLE **/
	var entryShuffleEntries={};
	initShuffleEntriesMap();
	function initShuffleEntriesMap(){
		jQuery("div.preview").each(function(entryIndex){
			var id='#'+jQuery(this).attr('id'),zIndex=parseInt(jQuery(this).css('z-index'));
			var containerId=id.split('-').pop();
			if (containerId in entryShuffleEntries===false){entryShuffleEntries[containerId]= new Map();}
			entryShuffleEntries[containerId].set(id,zIndex);
			if (zIndex>1){presentEntry(containerId,id);}
		});
	}
    initShuffleImages();
	function initShuffleImages(){
		var triggerNext={};
		jQuery("img[function=getImageShuffle]").each(function(entryIndex){
			var img=jQuery(this);
			jQuery(this).ready(function(){
				var containerId=jQuery(img).attr('container-id');
                var imgId=jQuery(img).attr('id');
                if (document.getElementById(imgId).hasAttribute("orgwidth") && document.getElementById(imgId).hasAttribute("orgheight")){
                    var width={'wrapper':jQuery(img).parent().width(),'img':jQuery(img).attr('orgwidth')};
                    var height={'wrapper':jQuery(img).parent().height(),'img':jQuery(img).attr('orgheight')};                        
                } else {
                    var width={'wrapper':jQuery(img).parent().width(),'img':img.width()};
                    var height={'wrapper':jQuery(img).parent().height(),'img':img.height()};
                }
				if (!(containerId in triggerNext)){triggerNext[containerId]=true;}
				if (jQuery('#btns-'+containerId+'-wrapper').is(":hidden")){
					var widthDiff=width['img']-width['wrapper'];heightDiff=height['img']-height['wrapper'];
					if (widthDiff>heightDiff){
						animateImage(img,'marginLeft',0,width['wrapper']-width['img'],width['img'],triggerNext[containerId]);
					} else if (height['img']>height['wrapper']){
						animateImage(img,'marginTop',height['wrapper']-height['img'],0,width['img'],triggerNext[containerId]);
					}
				}
				triggerNext[containerId]=false;
			});
		});
	}
	function animateImage(selector,property,start,end,width,triggerNext){
		jQuery(selector).animate(
			{[property]:(end+'px'),'width':1.2*width},
			{'duration':7000,
			 'easing':"linear",
			 'complete':function(){
					if (triggerNext){
						var nextBtnSelector='#getImageShuffle-'+jQuery(selector).attr('container-id')+'-next';
						jQuery(nextBtnSelector).click();
					}
					jQuery(selector).css({[property]:(start+'px'),'width':width});
					animateImage(selector,property,start,end,width,triggerNext);
				}
			});
	}
	function entryShuffleEntriesStep(containerId,isRev){
		if (!(containerId in entryShuffleEntries)){return false;}
		var toResetId=false,toSetId=false,firstId=false;
		if (isRev){
			var entryShuffleEntriesArr=Array.from(entryShuffleEntries[containerId]).reverse();
		} else {
			var entryShuffleEntriesArr=Array.from(entryShuffleEntries[containerId]);
		}
		for (let keyValueArr of entryShuffleEntriesArr){
			var key=keyValueArr[0],value=parseInt(keyValueArr[1]);
			if (firstId===false){firstId=key;}
			if (toResetId!==false && toSetId===false){toSetId=key;}
			if (value>1){toResetId=key;}
		}
		if (toSetId===false){toSetId=firstId;}
		jQuery(toResetId).css('z-index','1');
		entryShuffleEntries[containerId].set(toResetId,1);
		jQuery(toSetId).css('z-index','2');
		entryShuffleEntries[containerId].set(toSetId,2);
		presentEntry(containerId,toSetId);
        return true;
	}
	function presentEntry(containerId,selector){
		var presentEntrySelector='#present-'+containerId+'-entry';
		let arr={'selector':{'Source':jQuery(selector).attr('source'),'EntryId':jQuery(selector).attr('entry-id')},
				 'settings':{'presentEntry':'Image shuffle '+jQuery(presentEntrySelector).attr('title')},
				 'function':'loadEntry',
				 'htmlSelector':presentEntrySelector
				};
        jQuery('button[title^=Select]').css({'background-color':'#fff','color':'#fff'});
        jQuery('button[entry-id='+arr['selector']['EntryId']+'][title^=Select]').css({'background-color':'#000','color':'#000'});
        loadNextSelectedView(arr);
	}

	
/** JS-BUTTONS **/
	initJsButtonEvents();
	function initJsButtonEvents(){
		jQuery('.js-button').unbind('click');
		jQuery('.js-button').on('click',function(e){
			var idCmps=jQuery(this).attr('id').split('-');
			var cmd=idCmps.pop(),containerId=idCmps.pop(),method=idCmps.pop();
			if (method.localeCompare('getImageShuffle')===0){
				if (cmd.localeCompare('next')){
					entryShuffleEntriesStep(containerId,true);
				} else if (cmd.localeCompare('prev')){
					entryShuffleEntriesStep(containerId,false);
				}
			}
		});
	}

/** CANVAS INTERACTIVITY **/
	initDraggable();
	function initDraggable(){
		jQuery("div[class^='canvas-']").each(function(containerIndex){
			if (typeof jQuery(this).attr('entry-id')!=='undefined'){
				jQuery(this).draggable({
					containment:'parent',
					grid:[5,5],
					start: function(){},
					drag: function(){},
					stop: function(){
						let arr={'Content':{'Style':{'top':jQuery(this).css('top'),'left':jQuery(this).css('left')}},
                                 'Source':jQuery(this).attr('source'),
                                 'EntryId':jQuery(this).attr('entry-id'),
                                 'function':'setCanvasElementStyle',
                                 };
						jQuery.ajax({
                            method:"POST",
                            url:'js.php',
                            context:document.body,
                            data:arr,
                            dataType:"json"
                        }).done(function(data){
                            return data;
                        }).fail(function(data){
                            console.log(data);
                        }).always(function(){
                        
                        });
					}
				});
			}
		});
	}

/** SYMBOL LOGIN FORM **/
	addSymbolLoginEvents();
	function addSymbolLoginEvents(){
		jQuery('[id*=_loginSymbol]').unbind('click');
		jQuery('[id*=_loginSymbol]').bind('click',loginSymbolClick);
		jQuery('a.phrase-preview').unbind('click');
		jQuery('a.phrase-preview').bind('click',clearPhrasePreview);
	}
	function loginSymbolClick(){
		let symbol=jQuery(this).html();
		let symbolIds=jQuery(this).attr('id').split('_');
		symbolId=symbolIds.shift();
		jQuery(this).remove();
		jQuery('div.phrase-preview').append(symbol);
		if (Math.random()>0.5){addScrambledSymbol();}
		addSymbol(symbolId)
		if (Math.random()>0.5){addScrambledSymbol();}	
	}
	function clearPhrasePreview(){
		jQuery('div.phrase-preview').html('');
		jQuery('input.pass-phrase').val('');
	}
	function addSymbol(symbolId){
		let pass=jQuery('.pass-phrase').val();
		if (pass.length>0){pass=pass+'|'+symbolId;} else {pass=symbolId;}
		jQuery('.pass-phrase').val(pass);
	}
	function addScrambledSymbol(){
		let symbols=jQuery('.pass-phrase').val().split('|');
		if (symbols.length==0){return false;}
		let newSymbolId='',charArr=symbols[Math.floor(Math.random()*symbols.length)].split('');
		charArr.forEach(function(character,index){
			if (Math.random()>0.5){
				newSymbolId=newSymbolId+character;
			} else {
				newSymbolId=character+newSymbolId;	
			}
		});
		if (charArr.join('').localeCompare(newSymbolId)==0){return false;}
		addSymbol(newSymbolId);
	}
	
	
/** TOOLBOX **/
    function checkToolboxUpdate(containerId){
        var needsUpdate=jQuery('details.toolbox').children('[container-id="'+containerId+'"]').length;
        if (needsUpdate>0){
            jQuery('details.toolbox').css({'z-index':'100'}).prop("open",true);
        }
    }

/** MISC-HELPERS **/
	let heartbeats=0;
	(function heartbeat(){
    	setTimeout(heartbeat,100);
		heartbeats++;
		if (heartbeats%11===0){
			containerMonitor();
		} else if (heartbeats%5===0){
			loadNextEntry();
		}
	})();
	function isVisible(obj){
		if (obj.length==0){return false;}
		let element={'top':jQuery(obj).offset().top};
		element['bottom']=element['top']+jQuery(obj).outerHeight();
		let viewport={'top':jQuery(window).scrollTop()};
		viewport['bottom']=viewport['top']+jQuery(window).innerHeight();
		if ((element['top']<viewport['top'] && element['bottom']<viewport['top']) || (element['top']>viewport['bottom'] && element['bottom']>viewport['bottom'])){
			return false;
		} else {
			return true;
		}
	}
	function resetAll(){
		addSafetyCoverEvent();
		addEmojiEvent();
		addSymbolLoginEvents();
		loadImageData();
		initJsButtonEvents();
        loadDynamicMap();
    }
	
});