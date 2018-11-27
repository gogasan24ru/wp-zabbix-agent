<?php

/*
Plugin Name: Wp Zabbix Agent
Plugin URI: http://URI_Of_Page_Describing_Plugin_and_Updates
Description: A brief description of the Plugin.
Version: 1.0
Author: gogasan
Author URI: http://URI_Of_The_Plugin_Author
License: A "Slug" license name e.g. GPL2
*/
//global $wp_object_cache;
//var_dump($wp_object_cache);
//die();
foreach (glob(__DIR__."/inc/php-zabbix-agent/src/*.php") as $filename) {
    include $filename;
}

if(function_exists('xdebug_disable'))
{
    xdebug_disable();
}


class zabbixtraps
{

    function __construct() {
        add_action( 'admin_menu', array( $this, 'ZBXT_add_menu' ));
        register_activation_hook( __FILE__, array( $this, 'ZBXT_install' ) );
        register_deactivation_hook( __FILE__, array( $this, 'ZBXT_uninstall' ) );

        if(isset($_POST['general_setup']))if($_POST['general_setup']!=='1' )return; //TODO: fix it
        $settings=$this->getServerSettings();
        if(!$settings||!$settings['enabled'])
        {
            //if not enabled, or no configuration stored
            return;//do nothing
        }
        $agent=$this->createAgent($settings);
        if($agent) {
            $this->setupKeys($agent,$settings);
            $this->runHooks($agent);
        }
    }

    public function ZBXT_processKeys(array $keys)
    {
        $keys[]='demo.unsupported.key';//TODO remove demo
        $return=array();
        $agent= new ZabbixAgent("0.0.0.0","1");
        $this->setupKeys($agent,$this->getServerSettings());


        foreach ( $keys as $key)
        {
            $value='';
            try
            {
                ob_start();
                var_dump($agent->getItem($key)->toValue());
                $value = ob_get_clean();
//                $value=var_export($agent->getItem($key)->toValue(),true);
            }
            catch (Exception $e)
            {
                $value=$e->getMessage();
            }
            $return[]=array(
                    'key'=>$key,
                    'value'=>$value,
                );
        }
        return $return;
    }

    /**
     * @return array of agent items
     * @throws ZabbixAgentException
     */
    public function ZBXT_getAgentItems()
    {
        $agent= new ZabbixAgent("0.0.0.0","1");
        $this->setupKeys($agent,$this->getServerSettings());
        return $agent->getItems();
    }

    /**
     * Configure keys for $a
     * @param ZabbixAgent $a
     * @param $settings
     */
    private function setupKeys(ZabbixAgent $a,$settings)
    {
        //get_bloginfo
        //some wordpress keys:
        $a->setItem("blog.name", ZabbixPrimitiveItem::create(get_bloginfo("name")));
        $a->setItem("blog.description", ZabbixPrimitiveItem::create(get_bloginfo("description")));
        $a->setItem("blog.wpurl", ZabbixPrimitiveItem::create(get_bloginfo("wpurl")));
        $a->setItem("blog.url", ZabbixPrimitiveItem::create(get_bloginfo("url")));
        $a->setItem("blog.admin_email", ZabbixPrimitiveItem::create(get_bloginfo("admin_email")));
        $a->setItem("blog.charset", ZabbixPrimitiveItem::create(get_bloginfo("charset")));
        $a->setItem("blog.version", ZabbixPrimitiveItem::create(get_bloginfo("version")));
        $a->setItem("blog.html_type", ZabbixPrimitiveItem::create(get_bloginfo("html_type")));
        $a->setItem("blog.language", ZabbixPrimitiveItem::create(get_bloginfo("language")));
        $a->setItem("blog.stylesheet_url", ZabbixPrimitiveItem::create(get_bloginfo("stylesheet_url")));
        $a->setItem("blog.pingback_url", ZabbixPrimitiveItem::create(get_bloginfo("pingback_url")));
        $a->setItem("blog.count_users", ZabbixPrimitiveItem::create(count_users()['total_users']));
        if(!function_exists("get_plugins"))
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $a->setItem("blog.count_plugins", ZabbixPrimitiveItem::create(count(get_plugins())));
        $a->setItem("blog.is404", ZabbixPrimitiveItem::create(is_404()?"1":"0"));
        $a->setItem("blog.isRobots", ZabbixPrimitiveItem::create(is_robots()?"1":"0"));



        global $timestart;
        $a->setItem("profiler.pageGenTime", ZabbixPrimitiveItem::create(number_format(floatval(timer_stop()),3,'.','')));
        $a->setItem("profiler.queries", ZabbixPrimitiveItem::create((get_num_queries())));
        $a->setItem("profiler.lastUrl", ZabbixPrimitiveItem::create($_SERVER['REQUEST_URI']));
        $a->setItem("profiler.memoryUsage", ZabbixPrimitiveItem::create(memory_get_usage()));
        $a->setItem("profiler.memoryRealUsage", ZabbixPrimitiveItem::create(memory_get_usage(true)));
        $a->setItem("profiler.wp_db_version", ZabbixPrimitiveItem::create($wp_db_version));
        $a->setItem("profiler.admin_url", ZabbixPrimitiveItem::create(admin_url()));

        global $wp_object_cache;
        $a->setItem("profiler.cache_misses", ZabbixPrimitiveItem::create($wp_object_cache->cache_misses));
        $a->setItem("profiler.cache_hits", ZabbixPrimitiveItem::create($wp_object_cache->cache_hits));
//        $a->setItem("blog.pingback_url", ZabbixPrimitiveItem::create(get_bloginfo("pingback_url")));
//        $a->setItem("blog.pingback_url", ZabbixPrimitiveItem::create(get_bloginfo("pingback_url")));
//        $a->setItem("blog.pingback_url", ZabbixPrimitiveItem::create(get_bloginfo("pingback_url")));

        $getFileHash=function($args){return dechex(crc32(file_get_contents($args[0])));};
        $a->setItem("vfs.files.crc32",ZabbixArgumentedItem::create($getFileHash));
        $a->setItem("vfs.files.discovery",ZabbixArgumentedItem::create(
            function ($args){
                $trapper=ZabbixDiscoveryTrap::create();
                //$trapper->addItem(array(1));
                $path=$args[0];
                function getDirContents($dir, &$results = array()){
                    $files = scandir($dir);
                    $ignoreRegex=
                        '/\.git|\.idea|(^.+\.(png|jpg|jpeg|gif|mp3|mp4)$)|(^uploads$)|(^cache$)'
                        .'|(\.css$)'
                        .'|(\.scss$)'
                        .'|(\.svg$)'
                        .'|(\.js$)'
                        .'|(\.html$)'
                        .'|(\.md$)'
                        .'|(\.json$)'
                        .'|(\.txt$)'
                        .'|(\.mo$)'
                        .'|(\.po$)'
                        .'|(\.gz$)'
                        .'|(\.tar$)'
                        .'|(\.conf$)'
                        .'|(\.ini$)'
                        .'/i';
                    $whitelistRegex='/^.+\.php/i';
                    foreach($files as $key => $value){
                        $path = realpath($dir.DIRECTORY_SEPARATOR.$value);
                        if(!is_dir($path)) {
                            if(preg_match($whitelistRegex,$value))
                                $results[] = $path;
                        } else if($value != "." && $value != "..") {
                            if(!preg_match($ignoreRegex,$value))
                                getDirContents($path, $results);
                            //$results[] = $path;
                        }
                    }

                    return $results;
                }
                $tree=getDirContents($path);
                $count=0;
                foreach ($tree as $value)
                {
                    if($count++>100)break;//TODO remove. Added during debugging (weak zabbix-server host)
                    $trapper->addItem(array("{#PATH}"=>$value));
                }
                return $trapper->toValue();
            },array('/var/www/wordpress/')
                ));
        $a->setItem("blog.plugins.discovery",ZabbixArgumentedItem::create(
            function ($args){
                return "";
            }//,array('/var/www/wordpress/')
                ));
    }

    /**
     * @param $s array server settings
     * @return null|ZabbixAgent returns agent instance if configured correctly, null otherwise
     */
    private function createAgent($s)
    {
        $agent=null;
        try{
            $agent=ZabbixAgent::create();
            $agent -> setupActive($s['hostname'],
                $s['port'],
                $s['local_hostname'],
                $s['metadata'],
                120,
                10);
            //$agent->setDebugLevel();
        }
        catch(Exception $e){
            $this->showMessage("Error creating agent instance: ".$e->getMessage(),"notice-error");
        }
        return $agent;
    }

    /**
     * Set up hooks to run checks
     * @param ZabbixAgent $a
     */
    private function runHooks(ZabbixAgent $a)
    {

        $s = array(
                'agent'=>$a
        );

        add_action("shutdown",
            function () use ($s) {
                $a=$s['agent'];
                try{
                    $optionsStored=false;
                    if($conf=get_option( 'ZBXT_activeConfiguration' )) {
                        $a->setServerActiveConfiguration(json_decode($conf, true));
                        $optionsStored=true;
                    }
                    $a->checkForActiveChecksUpdates();
                    $a->processActiveChecks();
                    $a->sendActiveChecksResults();
                    if($optionsStored)
                    {
                        update_option('ZBXT_activeConfiguration',
                            json_encode($a->getServerActiveConfiguration()));
                    }
                    else
                    {
                        add_option("ZBXT_activeConfiguration",
                            json_encode($a->getServerActiveConfiguration()));
                    }
                } catch (Exception $e)
                {
                    //TODO walkaround
                }
            },99999999999
        );
        return;
        $s=$this->ZBXT_getSettings();
        if($s['enabled'])
        {
            add_action("shutdown",
                function () use ($s) {
                    $args = Array(
                        'host' => $s['trap_server'],
                        'community' => $s['community'],
                        'port' => $s['port']
                    );
                    if ($s['data']['sendKeepalive']['enabled'])//wp_before_admin_bar_render
                    {
                        $args['payload'] = array(
                            'oid' => $s['data']['sendKeepalive']['oid'],
                            'value' => '1',
                            'type' => 's'
                        );
                        $vars[] = $args['payload'];
                        //SNMP::trap($args['host'], $vars, $args['community']);

                    }
                    if ($s['data']['pageGenTime']['enabled'])//wp_before_admin_bar_render
                    {
                        global $timestart;
                        $args['payload'] = array(
                            'oid' => $s['data']['pageGenTime']['oid'],
                            'value' => '1,111',//(''.(microtime( true ) - $timestart).''),
                            'type' => 's'
                        );
                        $vars[] = $args['payload'];
                        //SNMP::trap($args['host'], $vars, $args['community'],"default",$args['port']);

                    }

                    //remove_action("shutdown");
            });
        }
    }
    /**
      * Actions perform at loading of admin menu
      */
    function ZBXT_add_menu() {

        add_menu_page( 'Zabbix traps settings',
            'ZBXT settings',
            'manage_options',
            'zbxt-settings', array(
            __CLASS__,
            'ZBXT_page_file_path'
        ), plugins_url('images/zbxt-logo.png', __FILE__),'2.2.9');

    }


    private function ZBXT_updateHooks($s)
    {
        if(!$s['enabled']) {
            try{
                //TODO: disable hooks
                throw new Exception("Not implemented");
            }
            catch (Exception $e)
            {

                $this->showMessage($e->getMessage(),"notice-error");
                return;
            }
            finally
            {

            }
            $this->showMessage("Hooks disabled","notice-success");
            return;
        }
        try{
            //TODO: enable hooks
            $args=Array(
                'host'=>$s['trap_server'],
                'community'=>$s['community']
            );
            if($s['data']['sendKeepalive']['enabled'])//wp_before_admin_bar_render
            {
                $args['payload']=Array(
                    'oid'=>$s['data']['sendKeepalive']['oid'],
                    'value'=>'hello'
                );
//                remove_action("shutdown");
////                return;
//                add_action("shutdown",
//                    function() use ( $args ) {
//                        SNMP::trap($args['host'], $args['payload'], $args['community']);
//                    });
            }
//            throw new Exception("Not implemented");
        }
        catch (Exception $e)
        {

            $this->showMessage($e->getMessage(),"notice-error");
            return;
        }
        finally
        {

        }
        $this->showMessage("Hooks enabled","notice-success");
    }

    /**
     * Deprecated. remove
     * @param $s
     */
    public function ZBXT_setSettings($s)
    {
        update_option('ZBXT_settings',json_encode($s));
        $this->ZBXT_updateHooks($s);
    }

    /**
     * Deprecated. remove
     * @return mixed
     */
    public function ZBXT_getSettings()
    {
        return json_decode(get_option( 'ZBXT_settings' ),true);
    }

    /**
     * Actions perform on loading of menu pages
     */
    function ZBXT_page_file_path() {
        require_once "inc/zbxt-settings.php";
    }

    /**
     * @return bool|mixed return assoc array of settings if exist, false otherwise
     */
    function getServerSettings()
    {
        if(!get_option( 'ZBXT_serverSettings' ))
        {
            return false;
        }
        return json_decode(get_option( 'ZBXT_serverSettings' ),true);
    }

    /**
     * Update settings storage
     * @param $settings
     */
    function updateServerSettings($settings){
        try{
            if(isset($settings['enabled']))$settings['enabled']=true; else $settings['enabled']=false;
            if(!get_option( 'ZBXT_serverSettings' ))
            {
                add_option("ZBXT_serverSettings",json_encode($settings));
            }
            else{
                update_option('ZBXT_serverSettings',json_encode($settings));
            }
            $this->showMessage("Server settings updated successfully.","notice-success");
        }catch (Exception $e)
        {
            $this->showMessage("Error updating server settings: ".$e->getMessage(),"notice-error");
        }

    }

    public function ZBXT_getAgentActiveConfiguration()
    {
        return json_decode(get_option( 'ZBXT_activeConfiguration' ));
    }

    /**
     * Actions perform on activation of plugin
     */
    function ZBXT_install() {
        $serverSettings=array(
            'enabled'=>false,
            'hostname'=>'127.0.0.1',
            'port'=>10051,
            'metadata'=>get_bloginfo('name'),
            'local_hostname'=>get_bloginfo('name')
        );
        $this->updateServerSettings($serverSettings);
        return;
        $settings = $this->ZBXT_getSettings();
        if(!isset($settings))
        {
            $settings=Array(
                "enabled" => true,
                "community" => "public",
                "trap_server" => "127.0.0.1",
                "port" => "123",

                "data" => Array(
                    "sendKeepalive" =>Array(
                        "oid"=>"",
                        "enabled" => true
                    ),

                    "pageGenTime" =>Array(
                        "oid"=>"",
                        "enabled" => true
                    ),
                    "memoryUsage" =>Array(
                        "oid"=>"",
                        "enabled" => true
                    ),
                    "dbQueryTime" =>Array(
                        "oid"=>"",
                        "enabled" => true
                    ),

                    "sendUri" =>Array(
                        "oid"=>"",
                        "enabled" => false
                    ),
                    "phpErrorsCount" =>Array(
                        "oid"=>"",
                        "enabled" => true
                    ),
                    "cacheRate" =>Array(
                        "oid"=>"",
                        "enabled" => true
                    ),

                    "WPversion" =>Array(
                        "oid"=>"",
                        "enabled" => true
                    )
                )

            );
            //set_op
            add_option("ZBXT_settings",json_encode($settings));
        }


    }

    /**
     * Actions perform on de-activation of plugin
     */
    function ZBXT_uninstall() {
        delete_option("ZBXT_serverSettings");
        delete_option("ZBXT_activeConfiguration");
        return;
        delete_option("ZBXT_settings");


    }

    private function showMessage($message, $type="notice-success", $header="Zabbix traps:")
    {?>
            <div class="notice <?php echo $type; ?> is-dismissible">
                <h4><?php echo $header; ?></h4>
                    <?php echo $message; ?>
            </div>
    <?php
    }
}

new zabbixtraps();