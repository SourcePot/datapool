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
                            } else if (plotData['use']=='clientPlot'){
                                clientPlot(plotData);
                            }
                        }).fail(function(data){
                            console.log(data);
                        }).always(function(){
                            
                        });
                });
            }

            function signalPlot(plotData){
                plotData['data']['DateTime']=new Date(plotData['data']['DateTime']);
                var plotDef={
                    x:{},
                    y:{grid: true},
                    marks:[
                        Plot.ruleY([0]),
                        Plot.ruleX([0]),
                        Plot.areaY(plotData['data'],{x:"History [sec]",y:"Value",curve:"step",fill:'blue','fillOpacity':0.2}),
                        Plot.lineY(plotData['data'],{x:"History [sec]",y:"Value",curve:"step",'tip':'xy','stroke':'blue'})
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

            function clientPlot(plotData){
                var plots={activity:{color:'blue','ruleYzero':0},cpuTemperature:{color:'black','ruleYzero':40},alarm:{color:'red','ruleYzero':0},escalate:{color:'orange','ruleYzero':0},light:{color:'green','ruleYzero':0}};
                plotData['data']['DateTime']=new Date(plotData['data']['DateTime']);
                var plotDefs=[];
                for (const property in plots){
                    var plotDef={
                        x:{},
                        y:{axis: "left",grid: true},
                        marks:[
                            Plot.ruleY([plots[property]['ruleYzero']]),
                            Plot.ruleX([0]),
                            ([plots[property]['ruleYzero']]==0) ? Plot.areaY(plotData['data'],{x:"History [sec]",y:property,curve:"step",fill:plots[property]['color'],'fillOpacity':0.2}):Plot.dot(plotData['data'],{x:"History [sec]",y:property,curve:"step",'r':1,'tip':'xy','stroke':plots[property]['color']}),
                            Plot.lineY(plotData['data'],{x:"History [sec]",y:property,curve:"step",'tip':'xy','stroke':plots[property]['color']}),
                        ]
                    };
                    if ("height" in plotData['meta']){plotDef['height']=plotData['meta']['height'];}
                    if ("title" in plotData['meta']){plotDef['title']=plotData['meta']['title'];}
                    delete plotData['meta']['title'];
                    plotDefs.push(plotDef);
                };
                jQuery("a[id^='svg-']").off('click');
                jQuery('[id='+plotData['meta']['id']+']').html('');
                for (const plotDef of plotDefs){
                    const plot=Plot.plot(plotDef)
                    jQuery('[id='+plotData['meta']['id']+']').append(plot);
                    jQuery('#svg-'+plotData['meta']['id']).on('click',function(element){
                        saveData(plot,plotData['meta']['id']+'.svg');
                    });
                }
            }

            (function heartbeat(){
                setTimeout(heartbeat,4900);
                drawPlots();
            })();

        });
    });
});