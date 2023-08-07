jQuery(document).ready(function(){
    
    jQuery('span[itemid^=trace]').hide();
    
    jQuery('[id^=trace],[id^=plot]').on("mousemove",function(event){
        var relSvgX=event.pageX-jQuery(this).parent('svg').offset().left;
        var relSvgY=event.pageY-jQuery(this).parent('svg').offset().top;
        getDatapoint(relSvgX,relSvgY);
    });
    
    function getDatapoint(x,y){
        var minDist=false;
        var matchId='NOTHING';
        jQuery('circle[id^=trace]').hide();
        jQuery('circle[id^=trace]').each(function(i){
            $distance=Math.pow(Math.pow(Math.abs(parseInt(jQuery(this).attr('cx'))-x),2)+Math.pow(Math.abs(parseInt(jQuery(this).attr('cy'))-y),2),0.5);
            if (minDist==false || minDist>$distance){
                minDist=$distance;
                matchId=jQuery(this).attr('id');
            }
        });
        jQuery('#'+matchId).show();
        jQuery('span[itemid^=trace]').hide().filter('span[itemid='+matchId+']').show();
    }


});