jQuery(document).ready(function(){

    /** DOWNLOAD SVG**/
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

    /** PLOTS **/
    import("https://cdn.jsdelivr.net/npm/@observablehq/plot@0.6/+esm").then((Plot)=>{
        import("https://cdn.jsdelivr.net/npm/d3@7/+esm").then((d3)=>{
            var saveData=(function(){
                            var a = document.createElement("a");
                            document.body.appendChild(a);
                            a.style = "display: none";
                            return function (plot,fileName) {
                                var url = window.URL.createObjectURL(serialize(plot));
                                a.href = url;
                                a.download = fileName;
                                a.click();
                                window.URL.revokeObjectURL(url);
                            };
                        }());
            function drawPlots(){
                jQuery('div.plot').each(function(i){
                    var plotObj=this;
                    var arr={'function':'plotDataProvider','id':jQuery(plotObj).attr('id')};
                    jQuery.ajax({
                            method:"POST",
                            url:'js.php',
                            context:document.body,
                            data:arr,
                            dataType:"json"
                        }).done(function(plotData){
                            if (plotData['use']=='signalPlot'){
                                signalPlot(plotData);
                            } else if (plotData['use']=='timeYplot'){
                                plotData['xTypeIsDate']=true;
                                XYplot(plotData);
                            } else if (plotData['use']=='XYplot'){
                                plotData['xTypeIsDate']=false;
                                XYplot(plotData);
                            }
                        }).fail(function(data){
                            console.log(data);
                        }).always(function(){
                            
                        });
                });
            }

            function signalPlot(plotData){
                plotData['data']['DateTime']=new Date(plotData['data']['DateTime']);
                let color='blue';
                if ("color" in plotData['meta']){color=plotData['meta']['color'];}
                let domain=[d3.min(plotData['data'],(d) => d['Value']),d3.max(plotData['data'],(d) => d['Value'])];
                if ("min" in plotData['meta']){domain[0]=plotData['meta']['min'];}
                if ("max" in plotData['meta']){domain[1]=plotData['meta']['max'];}
                let y={grid: true,label: "value"};
                var plotDef={
                    x:{},
                    y:{grid: true,domain:domain},
                    marks:[
                        //Plot.ruleY([0]),
                        Plot.ruleX([0]),
                        Plot.areaY(plotData['data'],{x:"History [sec]",y:"Value",curve:"step",fill:color,'fillOpacity':0.2}),
                        Plot.lineY(plotData['data'],{x:"History [sec]",y:"Value",curve:"step",'tip':'xy',stroke:color})
                        ],
                    marginLeft: 60    
                    };
                if ("height" in plotData['meta']){plotDef['height']=plotData['meta']['height'];}
                if ("title" in plotData['meta']){plotDef['title']=plotData['meta']['title'];}
                const plot=Plot.plot(plotDef);
                jQuery('[id='+plotData['meta']['id']+']').html(plot);
                jQuery('#svg-'+plotData['meta']['id']).on('click',function(element){
                    saveData(plot,plotData['meta']['id']+'.svg');
                });
            }

            function XYplot(plotData){
                //console.log(plotData);
                var marks=[],xDef={grid:true},yDef={grid:true};
                for (const traceName in plotData['traces']){
                    var dataArr=[],dataDef={stroke:"name",'tip':'xy'};
                    for (const index in plotData['traces'][traceName]){
                        var isDate=true;
                        var data={'name':traceName};
                        for (const key in plotData['traces'][traceName][index]){
                            if (isDate){
                                dataDef['x']=key;
                                if (plotData['xTypeIsDate']===true){
                                    data[key]=new Date(plotData['traces'][traceName][index][key]);
                                } else {
                                    data[key]=parseFloat(plotData['traces'][traceName][index][key]);
                                }
                            } else {
                                dataDef['y']=key;
                                data[key]=parseFloat(plotData['traces'][traceName][index][key]);
                            }
                            isDate=false;
                        }
                        dataArr.push(data);
                    }
                    if (plotData['property']['Normalize']=='y'){
                        dataDef=Plot.normalizeY(dataDef);
                        yDef['label']="Change %";
                        yDef['tickFormat']=((f) => (y) => f((y - 1) * 100))(d3.format("+d"));
                    } else if (plotData['property']['Normalize']=='x'){
                        dataDef=Plot.normalizeX(dataDef);
                        xDef['label']="Change %";
                        xDef['tickFormat']=((f) => (x) => f((x - 1) * 100))(d3.format("+d"));
                    }
                    marks.push(Plot.lineY(dataArr,dataDef));
                };
                jQuery("a[id^='svg-']").off('click');
                jQuery('[id='+plotData['meta']['id']+']').html('');
                const plot=Plot.plot({
                    height:parseInt(plotData['property']['Height']),
                    width:parseInt(plotData['property']['Width']),
                    color: {legend: true},
                    x:xDef,
                    y:yDef,
                    marks:marks
                });
                jQuery('[id='+plotData['meta']['id']+']').append(plot);
                jQuery('#svg-'+plotData['meta']['id']).on('click',function(element){
                    saveData(plot,plotData['meta']['id']+'.svg');
                });
            }

            (function heartbeat(){
                setTimeout(heartbeat,4900);
                drawPlots();
            })();

        });
    });
});