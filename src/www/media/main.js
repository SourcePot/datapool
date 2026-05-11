jQuery(document).ready(function(){
	
/** BASIC PAGE CONTENT STYLING **/
	if (jQuery('article.transparent').length){
		jQuery('div.bg-media').hide();
		jQuery('article').fadeIn(300,function(){
			var posHeight={'pos':jQuery('article.transparent').position(),'height':jQuery('article.transparent').outerHeight()};
			jQuery('div.bg-media').css({'height':(posHeight['pos']['top']+posHeight['height'])}).fadeIn(300);
		});
	} else {
		jQuery('article').fadeIn(300);
	}
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

    initTriggerIds();
    function initTriggerIds(){
        jQuery('[trigger-id]').each(function(i){
            let btnSelector='#'+jQuery(this).attr('trigger-id');
			if (jQuery(this).attr("type")==='text' || jQuery(this).attr("type")==='password'){
				jQuery(this).unbind('keydown');
                jQuery(this).on('keydown',function(e){
                    if (e.keyCode===13){
                        e.preventDefault();
                        jQuery(btnSelector).click();
                    }
                });
            } else {
                jQuery(this).unbind('change');
                jQuery(this).on('change',function(e){
                    jQuery(btnSelector).click();
                });
                jQuery(btnSelector).hide();
            }
        });
    }

/** Geo location **/
	const options = {
		maximumAge: 300000,
		timeout: 15000, //Longer timeout for low accuracy
		enableHighAccuracy: true // for faster, less accurate result
	};

	function success(pos) {
		const crd = pos.coords;
		jQuery.ajax({
			method:"POST",
			url:'js.php',
			context:document.body,
			data:{'function':'updateUserLocationHook','Geo':{'lat':crd.latitude,'lon':pos.coords.longitude,'accuracy':pos.coords.accuracy}},
			dataType: "json"
		}).done(function(data){
            console.log(data);
		}).fail(function(data){
			console.log(data);
		}).always(function(){
			busyLoadingEntry=false;
		});
	}

	function error(err) {
		console.warn(`ERROR(${err.code}): ${err.message}`);
	}

	if (jQuery('#user-location-hook').length>0){
		navigator.geolocation.getCurrentPosition(success, error, options);
	}
	
/** STEP-BY-STEP ENTRY PRESENTATION (loadEntry), used e.g. by the forum **/
	var busyLoadingEntry=false,mediaElements2process=[],mediaElements=[];
	function loadNextEntry(){
		let obj=jQuery('[function=loadEntry]:visible').first();
		if (busyLoadingEntry===false){
			let arr={
				'selector':{'Source':jQuery(obj).attr('source'),'EntryId':jQuery(obj).attr('entry-id')},
				'settings':{'presentEntry':jQuery(obj).attr('class')},
				'style':jQuery(obj).attr('style'),'class':jQuery(obj).attr('class'),
				'function':jQuery(obj).attr('function'),
				'replaceSelector':'[function=loadEntry][entry-id='+jQuery(obj).attr('entry-id')+']'
			};
			if (arr['selector']['EntryId']==undefined){
				if (mediaElements2process.length>0){
					addEvents2media();
				}
			} else {
				loadNextSelectedView(arr);
			}
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
				jQuery(arr['htmlSelector']).html(data['arr']);
			} else {
				jQuery(arr['replaceSelector']).replaceWith(data['arr']);
			}
			jQuery('[id=js-refresh]').click();
			if (jQuery(data['arr']).find('video','audio').length>0){
				mediaId=jQuery(data['arr']).find('video','audio').attr('id');
				jQuery('#'+mediaId).get(0).pause();
				mediaElements2process.push(mediaId);
			}
		}).fail(function(data){
			console.log(data);
		}).always(function(){
			busyLoadingEntry=false;
		});
	}

	const playMedia=function(mediaId){
		for(const mediaElement of mediaElements){
			if (mediaElement==mediaId){
				document.getElementById(mediaElement).play();
			} else {
				document.getElementById(mediaElement).pause();
			}
		}
	}
	
	const addEvents2media=function(){
		while(mediaElements2process.length>0){
			mediaId=mediaElements2process.pop();
			mediaElements.push(mediaId);
			jQuery('#'+mediaId).on('play',function(event){
				playMedia(jQuery(this).attr('id'));
			});
		}
	}

/** Image shuffle **/
	function nextMediaShuffleItem(){
		jQuery('div[class=mediashuffle]').each(function(shuffleIndex){
			var lastElemetWasVisible=false,noneUpdated=true,shuffle=jQuery(this);
			jQuery(shuffle).children('article').each(function(entryIndex){
				if (lastElemetWasVisible){
					jQuery(this).css('z-index',10);
					lastElemetWasVisible=false;
					noneUpdated=false;
				} else {
					lastElemetWasVisible=(parseInt(jQuery(this).css('z-index'))>5);
					jQuery(this).css('z-index',5);
				}
			});		
			if (noneUpdated){
				jQuery(shuffle).children('article').eq(0).css('z-index',10);
			}
		});
	}

/** GeoTools dynamic map **/
    var mapEntryObjArr={};
    var mapEntries={};
    loadDynamicMap()
    
    function loadDynamicMap(){
        mapEntries={};
        mapEntryGeoArr=Object.values(jQuery('[data-lat][data-lon]'));
		if (mapEntryGeoArr.length>0 && jQuery('[function=getDynamicMap]').length>0){
			loadMapGeoArr();
		}
		mapEntryObjArr=Object.values(jQuery('[entry-id]'));
		if (mapEntryObjArr.length>0 && jQuery('[function=getDynamicMap]').length>0){
			loadMapEntry();
		} else {
			entries2dynamicMap();
		}
	}
    
	function loadMapGeoArr(){
		jQuery('[data-lat][data-lon]').each(function(i){
			let lat=parseFloat(jQuery(this).attr('data-lat')),lon=parseFloat(jQuery(this).attr('data-lon'));
			if (isNaN(lat) || isNaN(lon)){return true;}
			mapEntries[i]={'EntryId':i,'Name':jQuery(this).attr('data-datetime'),'Folder':'Way point','Params':{'Geo':{'lat':lat,'lon':lon}}};
		});
	}

    function loadMapEntry(){
        let obj=mapEntryObjArr.shift();
		if (obj == undefined){
			entries2dynamicMap();
			return true;
		}
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
				loadMapEntry();
            });
        } else {
            loadMapEntry();
        }
    }
    function entries2dynamicMap(){
		if (jQuery('[data-location]').length==0){return true;}
		let location=jQuery('[data-location]').attr('data-location').split(',');
		var map=false;
        map=L.map('dynamic-map');
        map.setView([location[0],location[1]],location[2]);
        const tiles=L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',{
				maxZoom: 19,
				attribution: '&copy; <a href="http://www.openstreetmap.org/copyright">OpenStreetMap</a>'
			}).addTo(map);
        var selectBtns={};
        for (const [key,value] of Object.entries(mapEntries)){
            if (typeof value['Params']['Geo']!=='undefined'){
				let lat=parseFloat(value['Params']['Geo']['lat']);
				let lon=parseFloat(value['Params']['Geo']['lon']);
				if (isNaN(lat) || isNaN(lon)){continue;}
				var marker=L.marker([lat,lon]).addTo(map);
                var tooltip=L.tooltip().setLatLng([lat,lon]).setContent(value['Folder']+'<br/>'+value['Name']).addTo(map);
                selectBtns[jQuery(marker).attr('_leaflet_id')]=value['EntryId'];
				marker.on('click',function(e){
                    var selectBtnEntryId=selectBtns[jQuery(this).attr('_leaflet_id')];
					for (const buttonObj of jQuery('button[entry-id="'+selectBtnEntryId+'"]')){
						if (buttonObj.innerHTML === "✦"){
							jQuery(buttonObj).click();
						}
					}
                });
            }
        }
    }
/** CLIPBOARD **/
    jQuery('button[id^=clipboard]').on('click',function(e){
        e.preventDefault();
        let id=jQuery(this).attr('id').split('-').pop();
        let text=document.getElementById(id).innerHTML;
        jQuery('button[id^=clipboard]').parent().css({backgroundColor:""});
        try{
            navigator.clipboard.writeText(text);
            jQuery(this).parent().addClass('blue');
        } catch (e){
			console.log(e);
            jQuery(this).addClass('attention');
        }
        
    });

/** PRINTING **/
    jQuery('button:contains("❐")').on('click',function(e){
        e.preventDefault();
        //console.log(jQuery(this).parent('article'));
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
		let text=jQuery(selector).val()+jQuery(this).html().replace(/<\/?[^>]+(>|$)/g,"");
		jQuery(selector).val(text);
	}

/** IMAGE VIEWER **/
	let imgs=[];
	loadImageData();
	function loadImageData(){
		imgs=[];
		jQuery('div[class*=preview]').each(function(imgIndex){
			let img={'id':jQuery(this).attr('id'),'Source':jQuery(this).attr('source'),'EntryId':jQuery(this).attr('entry-id'),'index':imgs.length};
			imgs.push(img);
			jQuery(this).unbind('click').bind('click',loadImageByItem);
		});
    }
    
    function loadImageByItem(){
		var itemId=jQuery(this).attr('id');
        jQuery(imgs).each(function(index){
			if (imgs[index]['id']!=undefined){
				if (imgs[index]['id'].localeCompare(itemId)===0){
					loadImage(index);
					return false;
				}
			}
        });
    }
    
	function loadImage(index){
        let data={'function':'loadImage','selector':imgs[index]};
        jQuery.ajax({
			method:"POST",
			url:'js.php',
			context:document.body,
			data:data,
			dataType: "json"
		}).done(function(data){
			addImageToOverlay(data['arr'],index);
		}).fail(function(data){
			console.log(data);
		});
	}
	function addImageToOverlay(img,index){
        let lastIndex=imgs.length-1,page=index+1,lastPage=lastIndex+1;
		let html='';
		let imgBtn='<a style="float:inherit;" id="prev-img-btn">&#10096;&#10096;</a><a style="float:inherit;" id="next-img-btn">&#10097;&#10097;</a>';
		html=html+'<p style="float:inherit;" id="img-index">'+page+' of '+lastPage+'</p>';
		html=html+'<img style="float:inherit;" id="overlay-image" src="'+img['src']+'" class="'+img['class']+'" title="'+img['title']+'" index="'+index+'"></img>';
		html=html+'<p style="float:inherit;" id="overlay-title">'+img['title']+'</p>';
		html=html+imgBtn;
		let htmlObj=$(html);
		jQuery('#overlay').fadeIn(500).fadeIn(200,function(){
			jQuery('#overlay-image-container').html(htmlObj).fadeIn(400);	
			jQuery('#overlay,#overlay-image').on('click',function(e){
				jQuery('#overlay').hide();
				jQuery('#overlay-image-container').hide();
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
		});
	}
	document.addEventListener("keydown",function(e){
		if (e.key=="ArrowLeft"){
			if (jQuery('#overlay').is(":visible")){
				jQuery('#prev-img-btn').click();
			}
		} else if (e.key=="ArrowRight"){
			if (jQuery('#overlay').is(":visible")){
				jQuery('#next-img-btn').click();
			}
		}
	});

/** JS-BUTTONS **/
	initJsButtonEvents();
	function initJsButtonEvents(){
		jQuery('.js-button').unbind('click');
		jQuery('.js-button').on('click',function(e){
			var idCmps=jQuery(this).attr('id').split('-');
			var cmd=idCmps.pop(),containerId=idCmps.pop(),method=idCmps.pop();
		});
	}

/** CANVAS INTERACTIVITY **/
	initDraggable();
	function initDraggable(){
		let editoBtn=jQuery('.canvas-cntr-btn').html();
		if (editoBtn!="✖"){
			return false;
		}
		jQuery("div[class^='canvas-']").each(function(containerIndex){
			if (typeof jQuery(this).attr('entry-id')!=='undefined'){
				jQuery(this).draggable({
					containment:'parent',
					grid:[5,5],
					start: function(){},
					drag: function(){},
					stop: function(){
						let top=5*Math.round(parseInt(jQuery(this).css('top'))/5);
						let left=5*Math.round(parseInt(jQuery(this).css('left'))/5);
						let arr={
							'Content':{'Style':{'top':top,'left':left}},
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

	let isBusyUDS=[],originalCss=[];
	function updateDynamicStyle(){
		jQuery("[dynamic-style-id]").each(function(canvasElementIndex){
			let data={'function':'getDynamicStyle','dynamic-style-id':jQuery(this).attr("dynamic-style-id")};
			let canvasElement=jQuery(this),dynamicStyleId=data['dynamic-style-id'];
			if (typeof originalCss[dynamicStyleId]=='undefined'){
				originalCss[dynamicStyleId]=jQuery(canvasElement).attr('style');
			}
			if (isBusyUDS[dynamicStyleId]==true){
				return false;
			}
			isBusyUDS[dynamicStyleId]=true;
			jQuery.ajax({
				method:"POST",
				url:'js.php',
				context:document.body,
				data:data,
				dataType: "json"
			}).done(function(data){
				if (Object.hasOwn(data['arr'],"property")===false || Object.hasOwn(data['arr'],"value")===false){
					jQuery('[dynamic-style-id='+dynamicStyleId+']').attr('style',originalCss[dynamicStyleId]);
				} else {
					console.log(data['arr']);
					jQuery(canvasElement).css(data['arr']['property'],data['arr']['value']);
				}
			}).fail(function(data){
				console.log(data);
			}).always(function(data){
				isBusyUDS[dynamicStyleId]=false;
			});
		});
		
	}

/** USER ACTIONS **/
	showUserActions();
	function showUserActions(){
		jQuery.ajax({
			method:"POST",
			url:'js.php',
			context:document.body,
		    data:{'function':'getUserActions'},
            dataType:"json"
        }).done(function(data){
			jQuery('.user-action').remove();
			var style='';
			for(const [key,arr] of Object.entries(data['arr'])){
				style="border:2px solid "+arr['color']+";";
				jQuery('[entry-id='+arr['canvas-element']+']').append('<div class="user-action" style="'+style+'">'+arr['User']+'</div>');
			};
        }).fail(function(data){
			jQuery('.user-action').remove();
			console.log(data);
		}).always(function(){
			
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

/** DOWNLOAD **/
    const xmlns = "http://www.w3.org/2000/xmlns/";
    const xlinkns = "http://www.w3.org/1999/xlink";
    const svgns = "http://www.w3.org/2000/svg";
    function serialize(svg){
        svg=svg.cloneNode(true);
        const fragment=window.location.href+"#";
        const walker = document.createTreeWalker(svg, NodeFilter.SHOW_ELEMENT);
        while (walker.nextNode()){
            for (const attr of walker.currentNode.attributes){
                if (attr.value.includes(fragment)){
                    attr.value = attr.value.replace(fragment,"#");
                }
            }
        }
        svg.setAttributeNS(xmlns, "xmlns", svgns);
        svg.setAttributeNS(xmlns, "xmlns:xlink", xlinkns);
        const serializer = new window.XMLSerializer;
        const string = serializer.serializeToString(svg);
        return new Blob([string], {type: "image/svg+xml"});
    };

/** OPTION FILTER **/
    function addFilter(){
        jQuery('.filter').on('keyup',function(e){
            var selectId=jQuery(this).attr('id').split('-').pop(),filterText=jQuery(this).val().toUpperCase(),count=0;
            jQuery('#'+selectId).children('option').each(function(i){
                if (jQuery(this).html().toUpperCase().indexOf(filterText)===-1){
                    jQuery(this).hide();
                } else {
                    jQuery(this).show();
                    count++;
                }
            });
            jQuery('#count-'+selectId).html(count);
        });
    }
    addFilter();

/** MISC-HELPERS **/
	let heartbeats=0;
	(function heartbeat(){
    	setTimeout(heartbeat,100);
		heartbeats++;
		if (heartbeats%3===0){
			loadNextEntry();
		}
		if (heartbeats%17===0){
			updateDynamicStyle();
		}
		if (heartbeats%10===0){
			nextMediaShuffleItem();
		}
		if (heartbeats%25===0){
			showUserActions();
		}
	})();
	markChages();
	function markChages(){
		jQuery('input[type=text],input[type=email],input[type=tel],input[type=password],textarea').on('keypress',function(e){
			jQuery(this).parent().parent().filter('tr').css({'background-color':'var(--attentionColorTransparent)'});
		});
		jQuery('select,input[type=date],input[type=datetime-local]').on('change',function(e){
			jQuery(this).parent().parent().filter('tr').css({'background-color':'var(--attentionColorTransparent)'});
		});
	}

	jQuery('[id=js-refresh]').on('click',function(event){
		event.preventDefault();
		addSafetyCoverEvent();
		addEmojiEvent();
		addSymbolLoginEvents();
		loadImageData();
		initJsButtonEvents();
		addFilter();
		markChages();
	});

	animateBackground('swing');
	function animateBackground(easing){
		let width=Math.floor(100+Math.random()*40),height=Math.floor(100+Math.random()*40);
		let widthOffset=Math.floor(Math.random()*width),heightOffset=Math.floor(Math.random()*height);
		jQuery('div.bg-media[function=Home]').animate({
			'width':width+'%',
			'height':height+'%',
			'background-position-x':widthOffset+'%',
			'background-position-y':heightOffset+'%'
		},20000,easing,function(){
			animateBackground('linear');
		});
	}
	showBackgroundInfo()
	function showBackgroundInfo(){
		jQuery('p.bg-media').fadeIn(1000);
	}
	
});