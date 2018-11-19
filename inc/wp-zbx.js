function processKeys() {

    function buildKeysTable(array) {
        var ret='<table><thead>' +
            '<tr><td>key</td><td>processed value</td></tr>' +
            '</thead><tbody>';
        for(var i=0;i<array.length;i++)
        {
            var bold=array[i]['value'].includes("ZBX_NOTSUPPORTED");
            ret+='<tr><td>'+(bold?"<b>":"")+array[i]['key']+(bold?"</b>":"")+'</td><td>'+(bold?"<b>":"")+array[i]['value']+(bold?"</b>":"")+'</td></tr>';
        }

        ret+='</tbody></table>';
        return ret;
    }

    var processed=0;

    var timoutHandler = setInterval(function () {
        if(processed==0)
        {
            xmlhttp.abort();
            document.getElementById("generalDynamic").innerHTML='Request timeout';
        }
    },10*1000);

    var imageLink=document.getElementById("hidden.loadingURL").innerHTML;
    document.getElementById("generalDynamic").innerHTML=
        '<img src="'+imageLink+'">';
    var type="success";
    var xmlhttp = new XMLHttpRequest();
    // xmlhttp.timeout=10;
    xmlhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {

            processed=1;
            clearInterval(timoutHandler);
            var data=this.responseText;
            var json=JSON.parse(data);

            if(json['success']==false)
            {
                type="warning";
                document.getElementById("generalDynamic").innerHTML=
                    '<div class="notice notice-'+type+' is-dismissible">\n' +
                    '<h4>Warning</h4>\n' +
                    'Data unavailable.'+
                    //this.responseText +'<br>'+
                    'Try again later'+
                    '</div>';
            }
            else
            {
                var notRegisteredFound=false;
                for (var i=0;i<json['data'].length;i++)
                {
                    notRegisteredFound=notRegisteredFound||json['data'][i]['value'].includes("ZBX_NOTSUPPORTED");
                }
                type="success";
                if(notRegisteredFound)type="warning";
                document.getElementById("generalDynamic").innerHTML=
                    '<div class="notice notice-'+type+' is-dismissible">\n' +
                    '<h4>Semi-success :|</h4>\n' +
                    (notRegisteredFound?"Some of keys could not be processed, see details below<br>":"")+
                    'Json encoded data:<br>'+buildKeysTable(json['data'])+'<br>'+
                    // this.responseText +
                    '</div>';

            }


        }
    };
    // xmlhttp.ontimeout=function(){
    //     document.getElementById("generalDynamic").innerHTML="[X] Request timed out.";
    // };
    var formData = new FormData(document.getElementById('generalSetup'));
    formData.set("general_setup","processKeys");
    xmlhttp.open("POST", '');
    xmlhttp.send(formData);

}
function listSupportedKeys() {
    function buildKeysTable(array) {
        var ret='<table><thead>' +
            '<tr><td>key</td></tr>' +
            '</thead><tbody>';
        for(var i=0;i<array.length;i++)
        {
            ret+='<tr><td>'+array[i]+'</td></tr>';
        }

        ret+='</tbody></table>';
        return ret;
    }

    var processed=0;

    var timoutHandler = setInterval(function () {
        if(processed==0)
        {
            xmlhttp.abort();
            document.getElementById("generalDynamic").innerHTML='Request timeout';
        }
    },10*1000);

    var imageLink=document.getElementById("hidden.loadingURL").innerHTML;
    document.getElementById("generalDynamic").innerHTML=
        '<img src="'+imageLink+'">';
    var type="success";
    var xmlhttp = new XMLHttpRequest();
    // xmlhttp.timeout=10;
    xmlhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {

            processed=1;
            clearInterval(timoutHandler);
            var data=this.responseText;
            var json=JSON.parse(data);

            if(json['success']==false)
            {
                type="warning";
                document.getElementById("generalDynamic").innerHTML=
                    '<div class="notice notice-'+type+' is-dismissible">\n' +
                    '<h4>Warning</h4>\n' +
                    'Data unavailable.'+
                    //this.responseText +'<br>'+
                    'Try again later'+
                    '</div>';
            }
            else
            {
                type="success";
                document.getElementById("generalDynamic").innerHTML=
                    '<div class="notice notice-'+type+' is-dismissible">\n' +
                    '<h4>Success</h4>\n' +
                    'Json encoded data:<br>'+buildKeysTable(json['data'])+'<br>'+
                    //this.responseText +
                    '</div>';

            }


        }
    };
    // xmlhttp.ontimeout=function(){
    //     document.getElementById("generalDynamic").innerHTML="[X] Request timed out.";
    // };
    var formData = new FormData(document.getElementById('generalSetup'));
    formData.set("general_setup","listKeys");
    xmlhttp.open("POST", '');
    xmlhttp.send(formData);
}

function pullCurrentAgentConfig()
{
    var processed=0;

    var timoutHandler = setInterval(function () {
        if(processed==0)
        {
            xmlhttp.abort();
            document.getElementById("generalDynamic").innerHTML='Request timeout';
        }
    },10*1000);

    var imageLink=document.getElementById("hidden.loadingURL").innerHTML;
    document.getElementById("generalDynamic").innerHTML=
        '<img src="'+imageLink+'">';
    var type="success";
    var xmlhttp = new XMLHttpRequest();
    // xmlhttp.timeout=10;
    xmlhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {

            processed=1;
            clearInterval(timoutHandler);
            var data=this.responseText;
            var json=JSON.parse(data);

            if(json['success']==false)
            {
                type="warning";
                document.getElementById("generalDynamic").innerHTML=
                    '<div class="notice notice-'+type+' is-dismissible">\n' +
                    '<h4>Warning</h4>\n' +
                    'Data unavailable.'+
                    //this.responseText +'<br>'+
                    'Try again later'+
                    '</div>';
            }
            else
            {
                    type="success";
                    document.getElementById("generalDynamic").innerHTML=
                        '<div class="notice notice-'+type+' is-dismissible">\n' +
                        '<h4>Success</h4>\n' +
                        'Json encoded data:<br>'+JSON.stringify(json['data'], null, 2)+'<br>'+
                        //this.responseText +
                        '</div>';

            }


        }
    };
    // xmlhttp.ontimeout=function(){
    //     document.getElementById("generalDynamic").innerHTML="[X] Request timed out.";
    // };
    var formData = new FormData(document.getElementById('generalSetup'));
    formData.set("general_setup","agentSettings");
    xmlhttp.open("POST", '');
    xmlhttp.send(formData);
}

function testGeneralValues()
{
    function buildKeysTable(array) {
        var ret='<table><thead>' +
            '<tr><td>key</td><td>update interval, seconds</td></tr>' +
            '</thead><tbody>';
            for(var i=0;i<array.length;i++)
            {
                ret+='<tr><td>'+array[i]['key']+'</td><td>'+array[i]['delay']+'</td></tr>';
            }

        ret+='</tbody></table>';
            return ret;
    }

    var processed=0;

    var timoutHandler = setInterval(function () {
        if(processed==0)
        {
            xmlhttp.abort();
            document.getElementById("generalDynamic").innerHTML='' +
                '<div class="notice notice-error is-dismissible">\n' +
                '<h4>Error</h4>\n' +
                "[X] Request timed out." +
                '</div>';
        }
    },10*1000);

    var imageLink=document.getElementById("hidden.loadingURL").innerHTML;
    document.getElementById("generalDynamic").innerHTML=
        '<img src="'+imageLink+'">';
    var type="success";
    var xmlhttp = new XMLHttpRequest();
    // xmlhttp.timeout=10;
    xmlhttp.onreadystatechange = function() {
        if (this.readyState == 4 && this.status == 200) {

            processed=1;
            clearInterval(timoutHandler);
            var data=this.responseText;
            var json=JSON.parse(data);

            if(json['success']==false)
            {
                type="warning";
                document.getElementById("generalDynamic").innerHTML=
                    '<div class="notice notice-'+type+' is-dismissible">\n' +
                    '<h4>Warning</h4>\n' +
                    'Something goes wrong!<br><b>Error message:</b>'+json['data']['message']+'<br>'+
                    this.responseText +'<br>'+
                    'Try again later'+
                    '</div>';
            }
            else
            {
                if((json['data']['data'].length==0)&&(json['data']['response']=="success")){
                    type="warning";
                    document.getElementById("generalDynamic").innerHTML=
                        '<div class="notice notice-'+type+' is-dismissible">\n' +
                        '<h4>Semi-success :|</h4>\n' +
                        'Zabbix connection ok, but no active checks received. Try again in few seconds. <br>'+
                        this.responseText +
                        '</div>';
                }
                else if((json['data']['data'].length!=0)&&(json['data']['response']=="success")){

                    type="success";
                    document.getElementById("generalDynamic").innerHTML=
                        '<div class="notice notice-'+type+' is-dismissible">\n' +
                        '<h4>Success</h4>\n' +
                        'Zabbix connection ok!<br>Request list:<br>'+buildKeysTable(json['data']['data'])+'<br>'+
                        this.responseText +
                        '</div>';
                }
            }


        }
    };
    // xmlhttp.ontimeout=function(){
    //     document.getElementById("generalDynamic").innerHTML="[X] Request timed out.";
    // };
    var formData = new FormData(document.getElementById('generalSetup'));
    formData.set("general_setup","json");
    xmlhttp.open("POST", '');
    xmlhttp.send(formData);

}
