jQuery(document).ready(function(){

    var fileStore=[];
    var fileCount=0;
    
    jQuery('[type=file]').each(function(){
        let name=jQuery(this).attr('name');
        if (name.includes('[]')){
            jQuery(this).unbind('change');
            this.addEventListener("drop",handleFileEvent);
            this.addEventListener("change",handleFileEvent);
            jQuery(this).css({'height':50,'width':200});
            jQuery(this).parent().append('<progress id="upload-progress" style="float:left;clear:both;width:200px;padding:0.2rem 0.6rem;" max="100" value="2"></progress>');
        } else {
            // use this script only for multiple files
        }
    });

    function handleFileEvent(event){
        console.log(event);
        event.preventDefault();
        if (event.type=="drop" || event.type=="change"){
            let tagName=jQuery(event.target).attr('name').replace('[]','');
            let btnId=jQuery(event.target).attr('trigger-id');
            let btnName=jQuery('[id='+btnId+']').attr('name');
            let btnValue=jQuery('[id='+btnId+']').value;
            let files={};
            if (event.type=="drop"){
                let dt=event.dataTransfer;
                files=dt.files;
            } else {
                files=this.files;    
            }
            fileCount=files.length;
            for (var i=0; i<fileCount; i++) {
                fileStore[i]=files[i];
                fileStore[i].tagName=tagName;
                fileStore[i].btnName=btnName;
                fileStore[i].btnValue=btnValue;
            }
            postFile();
        }
    }

    function postFile(){
        let file=fileStore.shift();
        setProgress(fileStore.length,fileCount);
        if (file == undefined){
            jQuery('[id=page-refresh]').click();
        } else {
            const formData = new FormData();
            formData.append(file.tagName,file);
            formData.append(file.btnName,file.btnValue);
            const xhr = new XMLHttpRequest();
            addXhrListeners(xhr);
            xhr.open('POST','js.php');
            xhr.send(formData);
        }
    }

    function handleXhrEvent(event){
        if (event.type=="loadstart"){
        
        } else if (event.type=="progress"){
            
        } else if (event.type=="load"){
        
        } else if (event.type=="loadend"){
        
            postFile();
        }
    }

    function addXhrListeners(xhr){
        xhr.addEventListener("loadstart", handleXhrEvent);
        xhr.addEventListener("load", handleXhrEvent);
        xhr.addEventListener("loadend", handleXhrEvent);
        xhr.addEventListener("progress", handleXhrEvent);
        xhr.addEventListener("error", handleXhrEvent);
        xhr.addEventListener("abort", handleXhrEvent);
    }

    function setProgress(value,maxValue){
        const percent=Math.round((1-value/maxValue)*100);
        console.log(percent);
        document.getElementById('upload-progress').value=percent;
    }

});