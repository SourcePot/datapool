jQuery(document).ready(function(){

    var fileStore={};
    var btnName2process=[];
    
    initFileUpload();
    
    function initFileUpload(){
        jQuery('.file-upload[type=file]').each(function(){
            jQuery(this).unbind('change');
            jQuery(this).unbind('drop');
            this.addEventListener("drop",handleFileEvent);
            this.addEventListener("change",handleFileEvent);
        });    
    }
    
    function handleFileEvent(event){
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
            let filesArr=[];
            for (var i=0; i<fileCount; i++) {
                filesArr[i]=files[i];
                filesArr[i].tagName=tagName;
                filesArr[i].btnName=btnName;
                filesArr[i].btnValue=btnValue;
            }
            fileStore[btnName]={'count':fileCount,'filesArr':filesArr};
            btnName2process.unshift(btnName);
            postFile();
        }
    }

    function postFile(){
        let btnName=btnName2process[0];
        if (btnName == undefined){
            return true;
        }   
        let fileCount=fileStore[btnName]['count'];
        let file=fileStore[btnName]['filesArr'].shift();
        if (file == undefined){
            setProgress(btnName,0,fileCount);
            btnName2process.shift();
            jQuery('[id=page-refresh]').click();
        } else {
            setProgress(btnName,fileStore[btnName]['filesArr'].length,fileCount);
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
        let infoSelector='#'+btnName2process[0]+'_info';
        let file=fileStore[btnName2process[0]]['filesArr'][0];
        if (event.type=="loadstart" && typeof file!=='undefined'){
            jQuery(infoSelector).text('Start:'+file.name);
        } else if (event.type=="progress" && typeof file!=='undefined'){
            jQuery(infoSelector).text('Processing: '+file.name);
        } else if (event.type=="load" && typeof file!=='undefined'){
            jQuery(infoSelector).text('Loading: '+file.name);
        } else if (event.type=="loadend" && typeof file!=='undefined'){
            postFile();
        } else if (event.type=="loadend"){
            jQuery(infoSelector).text('Please wait...');
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

    function setProgress(btnTagName,value,maxValue){
        const percent=Math.round((1-value/maxValue)*100);
        document.getElementById(btnTagName+'_progress').value=percent;
    }

	jQuery('[id=js-refresh]').on('click',function(event){
		event.preventDefault();
        initFileUpload();
	});

});