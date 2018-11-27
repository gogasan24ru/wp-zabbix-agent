<?php
$zbxt = new zabbixtraps();

function testConnection()
{
//    foreach (glob(__DIR__."/php-zabbix-agent/*.php") as $filename) {
//        include $filename;
//    }
    $a=array();
    try{
    $agent = ZabbixAgent::create(10351);
    $agent->setupActive($_POST['generalSettings']['hostname'],
        $_POST['generalSettings']['port'],
        $_POST['generalSettings']['local_hostname'],
        $_POST['generalSettings']['metadata']);

    $a = $agent->checkForActiveChecksUpdates();
    }
    catch (Exception $e)
    {
        $a['message']=$e->getMessage();
        ob_clean();//another best way to get rid of menu panel in god damn json answer????
        wp_send_json_error($a);
        wp_die();
    }

    ob_clean();//another best way to get rid of menu panel in god damn json answer????
    wp_send_json_success($a);
    wp_die();
}
//testConnection();
if (isset($_POST['general_setup']))
{
    switch ($_POST['general_setup'])
    {
        case 'json':
            testConnection();
            break;
        case 'agentSettings':
            $data=$zbxt->ZBXT_getAgentActiveConfiguration();
            ob_clean();
            if(!$data)
            {
                wp_send_json_error();
                wp_die();
            }
            wp_send_json_success($data);
            wp_die();
            break;
        case 'listKeys':
            $data=array();
            try{
            $data=array_keys($zbxt->ZBXT_getAgentItems());
            }
            catch (Exception $e)
            {
                ob_clean();
                wp_send_json_error(array('data'=>$e->getMessage()));
                wp_die();
            }
            ob_clean();
            if(!$data)
            {
                wp_send_json_error();
                wp_die();
            }
            wp_send_json_success($data);
            wp_die();
            break;
        case 'processKeys':
            $data=($zbxt->ZBXT_processKeys(array_keys($zbxt->ZBXT_getAgentItems())));
            ob_clean();
            if(!$data)
            {
                wp_send_json_error();
                wp_die();
            }
            wp_send_json_success($data);
            wp_die();
            break;
        case '1':
            $zbxt->updateServerSettings($_POST['generalSettings']);
            break;
        default:

    }

}

wp_register_script( 'zbx-script', plugins_url('wp-zbx.js', __FILE__) );
wp_enqueue_script( 'zbx-script' );


function ZBXT_genFields($d)
{
    $ret='';
    $ret.='Enabled <input type="checkbox" name="settings[enabled]" '.($d['enabled']?"checked":"").'><br>';
    $ret.='SNMP community <input type="text" name="settings[community]" value="'.($d['community']).'"><br>';
    $ret.='Trap server ip/hostname <input type="text" name="settings[trap_server]" value="'.($d['trap_server']).'"><br>';
    $ret.='Server port <input type="text" name="settings[port]" value="'.($d['port']).'"><br>';

    foreach($d['data'] as $key=>$value)
    {
        $ret.=$key;
        $ret.=' <input type="checkbox" name="settings[data]['.$key.'][enabled]" '.($value['enabled']?"checked":"").'>';
        $ret.='OID: <input type="text" name="settings[data]['.$key.'][oid]" value="'.($value['oid']).'">';
        $ret.="<br>";
    }

    return $ret;

}


// Save access code
if(isset($_POST['reset']))
{
    $zbxt->ZBXT_uninstall();
    $zbxt->ZBXT_install();
}
if ( isset( $_POST["settings"]) ) {
    $data=$_POST['settings'];
    if(isset($data['enabled']))
        {
            unset($data['enabled']);
            $data['enabled']=true;
        }
        else $data['enabled']=false;
    foreach($data['data'] as $key=>$value) {
        if(isset($value['enabled']))
            {
                    unset($data['data'][$key]['enabled']);
                    $data['data'][$key]['enabled']=true;
            }
            else $data['data'][$key]['enabled']=false;
    }
//    return;
    $zbxt->ZBXT_setSettings($data);
//    if( $wp_analytify->wpa_save_data( $_POST["access_code"] )){
//        $update_message = '<div id="setting-error-settings_updated" class="updated settings-error below-h2"><p><strong>Access code saved.</strong></p></div>';
//    }
}


$serverSettings=$zbxt->getServerSettings();
if(!isset($serverSettings)||(!$serverSettings))
{
    $serverSettings=array(
            'enabled'=>false,
            'hostname'=>'127.0.0.1',
            'port'=>10051,
            'metadata'=>get_bloginfo('name'),
            'local_hostname'=>str_replace(array('http://','https://'),'',get_bloginfo('url'))
    );
}
?>
<div style="display: none;" id="hidden.loadingURL"><?php echo plugins_url('../images/loading.gif', __FILE__) ?></div>
<h1>Wordpress zabbix agent configuration page</h1>
<a href="https://www.zabbix.com/" target="_blank">Zabbix official website</a>
<h3>General setup</h3>
<form
        id="generalSetup"
        action="<?php echo $_SERVER['REQUEST_URI'] ?>"
        method="post"
    >
    <input type="hidden" name="general_setup" value="1">
    <table>
        <tbody>
            <tr>
                <td>Agent enabled</td>
                <td><input name="generalSettings[enabled]" type="checkbox" <?php echo $serverSettings['enabled']?"checked":""; ?>> </td>
                <td>Is active checks are enabled? </td>
            </tr>
            <tr>
                <td>Zabbix server/proxy hostname/IP</td>
                <td><input name="generalSettings[hostname]" type="text" value="<?php echo $serverSettings['hostname']; ?>"> </td>
                <td>Ask your monitoring provider for this.</td>
            </tr>
            <tr>
                <td>Zabbix server/proxy port</td>
                <td><input name="generalSettings[port]" type="number" value="<?php echo $serverSettings['port']; ?>"> </td>
                <td>Usually 10051, if didn't worked, ask your monitoring provider.</td>
            </tr>
            <tr>
                <td>Local zabbix agent metadata</td>
                <td><input name="generalSettings[metadata]" type="text" value="<?php echo $serverSettings['metadata']; ?>"> </td>
                <td>Used for automatic registration on server side. If registration not allowed, you should use correct hostname</td>
            </tr>
            <tr>
                <td>Local zabbix agent hostname</td>
                <td><input name="generalSettings[local_hostname]" type="text" value="<?php echo $serverSettings['local_hostname']; ?>"> </td>
                <td><?php echo str_replace(array('http://','https://'),'',get_bloginfo('url'));?> for example</td>
            </tr>
        </tbody>
    </table>
    <input type="hidden" name="general_setup" value="1">
    <input class="button button-primary" type="submit" value="Update">
    <input class="button button-primary" type="button" value="Test configuration" onclick="testGeneralValues()">

    <input class="button button-primary" type="button" value="Pull current agent configuration" onclick="pullCurrentAgentConfig()">
    <input class="button button-primary" type="button" value="Process keys" onclick="processKeys()">
    <input class="button button-primary" type="button" value="List supported keys" onclick="listSupportedKeys()">
</form>
<div id="generalDynamic"></div>
<div id="generalDynamic2"></div>

<hr>


<form
        action="<?php echo $_SERVER['REQUEST_URI'] ?>"
        method="post"
>
    <input type="hidden" name="reset" val="1">
    <input class="button button-primary" type="submit" value="Reset settings">
</form>



<textarea style="display: none;" id="TA_agentConfiguration">
</textarea>
<?php return; ?>
<form
    action="<?php echo $_SERVER['REQUEST_URI'] ?>"
    method="post"
    >
    <?php echo ZBXT_genFields($zbxt->ZBXT_getSettings()); ?>
    <br>
    <input class="button button-primary" type="submit" value="Update">
</form>




