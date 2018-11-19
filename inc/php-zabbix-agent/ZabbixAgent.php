<?php

/**
 * Class of zabbix agent server
 */
class ZabbixAgent
{
    /**
     * @var int log level
     */
    protected $logLevel=PHPZA_LL_ERROR;

    /**
     * Items on this agent
     * @var array
     */
    protected $items = array();

    /**
     * Listen socket itself
     * @var resource
     */
    protected $listenSocket;

    /**
     * Hostname for active checks and must match hostname as configured on the server.
     * @var string
     */
    protected $agentHostName;

    /**
     * Host metadata is used at host auto-registration process.
     * @var string
     */
    protected $agentHostMetadata;

    /**
     * Active agent configuration available. //TODO: convert to function, returning bool from isset($serverActive)
     * @var bool
     */
    protected $activeAvailable=false;

    /**
     * Hostname of Zabbix server for active checks.
     * @var string
     */
    protected $serverActive;

    /**
     * Port number of Zabbix server for active checks.
     * @var int
     */
    protected $serverActivePort;

    /**
     * Active configuration update interval in seconds.
     * @var int
     */
    protected $serverActiveUpdateInterval=120;

    /**
     * @return array current active configuration with ['payload'] config array, ['last'] unix timestamp last check
     */
    public function getServerActiveConfiguration()
    {
        return array(
            'payload'=>$this->serverActiveConfiguration,
            'last'=>$this->serverActiveUpdateLast
            );
    }

    /**
     * @param array $source - ['payload'] config array, ['last'] unix timestamp last check
     */
    public function setServerActiveConfiguration(array $source)
    {
        $this->serverActiveConfiguration=$source['payload'];
        $this->serverActiveUpdateLast=$source['last'];
    }

    /**
     * Last active configuration update timestamp.
     * @var int
     */
    protected $serverActiveUpdateLast=0;

    /**
     * @var int active send interval
     */
    protected $activeSendInterval=180;

    /**
     * @var int last active send timestamp
     */
    protected $activeSendLast=0;

    /**
     * @var array - current active configuration
     */
    protected $serverActiveConfiguration;

    /**
     * @var array - active checks stored results
     */
    protected $activeChecksResultsBuffer;

    //TODO: add items?
    //TODO: native tls required

    /**
     * Default port for zabbix agent
     * @var int
     */
    protected $port = 10050;

    /**
     * Host for server listen socket
     * @var string
     */
    protected $host = "0.0.0.0";

    /**
     * Create zabbix agent object
     * @param string $host
     * @param int $port
     * @throws ZabbixAgentException
     */
    function __construct($host, $port)
    {
        if (empty($host)) {
            throw new ZabbixAgentException("You must set host");
        }

        if (empty($port)) {
            throw new ZabbixAgentException("You must set port");
        }

        $this->port = $port;
        $this->host = $host;
    }

    public function setDebugLevel($level=PHPZA_LL_DEBUG)
    {
        $this->logLevel=$level;
    }

    /**
     * Setup parameters, required for active mode.
     * @param string $serverActive Server for active checks, hostname or ip
     * @param int $port Server for active checks, port number
     * @param string $agentHostName
     * @param string $agentHostMetadata
     * @param int $updateInterval Active configuration update interval
     */
    public function setupActive($serverActive,
                                   $port=10051,
                                   $agentHostName=null,
                                   $agentHostMetadata=null,
                                   $updateInterval=120)
    {
        $this->serverActive=$serverActive;
        $this->serverActivePort=$port;
        $this->serverActiveUpdateInterval=$updateInterval;
        if(isset($agentHostName))
        {
            $this->agentHostName=$agentHostName;
        }
        else
        {
            if (gethostname()) {
                $this->agentHostName=gethostname().'-php-zabbix-agent';
            }
            else {
                $this->agentHostName='php-zabbix-agent';
            }
        }
        if(!isset($agentHostMetadata)){
            $this->agentHostMetadata = "";// should add some phpinfo info?
        }else $this->agentHostMetadata = $agentHostMetadata;
        $this->activeAvailable=true;
        $this->serverActiveUpdateLast=0;
        $this->logger(PHPZA_LL_INFO,__FUNCTION__." done.");
    }

    /**
     * Builds active checks request string.
     * @return string with placed values
     */
    public function getActiveRequest()
    {
        return '{
	        "request":"active checks",
        	"host":"'.$this->agentHostName.'",
	        "host_metadata":"'.$this->agentHostMetadata.'"
            }';
    }

    /**
     * Create zabbix agent object
     * @param int $port
     * @param string $host
     * @return ZabbixAgent
     * @throws ZabbixAgentException
     */
    public static function create($port, $host = "0.0.0.0")
    {
        return new ZabbixAgent($host, $port);
    }

    /**
     * Start listen socket.
     * @throws ZabbixAgentSocketException
     * @return ZabbixAgent
     */
    public function start()
    {
        $this->listenSocket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP);
        if ($this->listenSocket === false) {
            throw new ZabbixAgentSocketException('Create socket error.');
        }

        $setOptionResult = socket_set_option($this->listenSocket, SOL_SOCKET, SO_REUSEADDR, 1);
        if ($setOptionResult === false) {
            throw new ZabbixAgentSocketException('Set socket option error.');
        }

        $bindResult = socket_bind($this->listenSocket, $this->host, $this->port);
        if ($bindResult === false) {
            throw new ZabbixAgentSocketException('Socket bind error.');
        }

        $listenResult = socket_listen($this->listenSocket, 0);
        if ($listenResult === false) {
            throw new ZabbixAgentSocketException('Socket listen error.');
        }

        $nonBlockResult = socket_set_nonblock($this->listenSocket);
        if ($nonBlockResult === false) {
            throw new ZabbixAgentSocketException('Socket set nonblocking error.');
        }
        $this->logger(PHPZA_LL_INFO,__FUNCTION__." done");
        return $this;
    }


    /**
     * Performs log action. This function should be overrided due inheritance
     * @param $logLevel int log level for accepted $message
     * @param $message string message to log
     */
    private function logger($logLevel,$message)
    {
        if($logLevel<=$this->logLevel)
        {
            echo "[".date("Y-m-d H:i:s ".$this->microsec())."] ".$message.PHP_EOL;
        }
    }

    /**
     * @return int nanoseconds from $this->microsec()
     */
    private function nanosec()
    {
        return intval($this->microsec()*1e6);
    }

    /**
     * @return int microtime, microseconds part only
     */
    private function microsec()
    {
        return intval(explode(" ", microtime())[0]);
    }


    /**
     *
     * @throws ZabbixActiveAgentException
     */
    public function sendActiveChecksResults()
    {
        if((time()-$this->activeSendLast)<$this->activeSendInterval){
            return;
        }
        $this->activeSendLast=time();
        $JSONbuf=json_encode($this->activeChecksResultsBuffer);
        $this->logger(PHPZA_LL_DEBUG,__FUNCTION__." sending: ".$JSONbuf);
        unset($this->activeChecksResultsBuffer);

        $ans='';
        $fp = fsockopen($this->serverActive, $this->serverActivePort, $errno, $errstr, 30);
        if (!$fp) {

            $this->logger(PHPZA_LL_ERROR,__FUNCTION__.$errstr ($errno));
            throw new ZabbixActiveAgentException($errstr ($errno));
        } else {
            $out = ZabbixProtocol::buildPacket($JSONbuf);
            fwrite($fp, $out);
            while (!feof($fp)) {
                $ans .= fgets($fp, 128);
            }
            fclose($fp);
            if(ZabbixProtocol::ZABBIX_MAGIC!=substr($ans,0,4))
                throw new ZabbixActiveAgentException("Invalid packet received. Packet header mismatch.");
            //if(ZabbixProtocol::ZABBIX_DELIMETER!=unpack("C",substr($ans,4,1)))
            //    throw new ZabbixActiveAgentException("Invalid packet received."); //GOT 0x00
            $payloadLength=ZabbixProtocol::getLengthFromPacket(substr($ans,5,8));
            $payloadJson=substr($ans,13,$payloadLength);

            if(strlen($payloadJson)!=$payloadLength)
                throw new ZabbixActiveAgentException("Invalid packet received. Wrong payload length.");

            $payload=json_decode($payloadJson,true);

            switch (json_last_error()) {
                case JSON_ERROR_NONE:{
                    $this->logger(PHPZA_LL_DEBUG,__FUNCTION__."JSON no errors");
                    break;
                }
                case JSON_ERROR_DEPTH:throw new ZabbixActiveAgentException("Configuration update error, JSON_ERROR_DEPTH");
                case JSON_ERROR_STATE_MISMATCH:throw new ZabbixActiveAgentException("Configuration update error, JSON_ERROR_STATE_MISMATCH");
                case JSON_ERROR_CTRL_CHAR:throw new ZabbixActiveAgentException("Configuration update error, JSON_ERROR_CTRL_CHAR");
                case JSON_ERROR_SYNTAX:throw new ZabbixActiveAgentException("Configuration update error, JSON_ERROR_SYNTAX");
                case JSON_ERROR_UTF8:throw new ZabbixActiveAgentException("Configuration update error, JSON_ERROR_UTF8");
                default:throw new ZabbixActiveAgentException("Configuration update error, no errorcode provided.");
            }

            $this->logger(PHPZA_LL_INFO,__FUNCTION__." done. Server answer: ".$payloadJson);
            //TODO walkaround for
            /**
             * {
            "response":"success",
            "info":"processed: 3;
            failed: 0;
            total: 3;
            seconds spent: 0.000437"
            }
             */

        }
        $this->logger(PHPZA_LL_INFO,__FUNCTION__." done");
    }

    /**
     * @throws Exception
     */
    public function processActiveChecks()
    {
        $processed=0;
        $failed=0;
        $currentTime=time();
        if(!isset($this->activeChecksResultsBuffer)) {
            $this->activeChecksResultsBuffer = array();
            $this->activeChecksResultsBuffer['data'] = array();
        }
        $this->activeChecksResultsBuffer['request']="agent data";
        $key=count($this->activeChecksResultsBuffer['data']);
        foreach ($this->serverActiveConfiguration as $checkKey => $check)
        {
            //var_dump($check);
            if(!isset($this->serverActiveConfiguration[$checkKey]['lastRun']))
                $this->serverActiveConfiguration[$checkKey]['lastRun']=0;
                //$check['lastRun']=0;
            if(($currentTime-$this->serverActiveConfiguration[$checkKey]['lastRun'])>
                $this->serverActiveConfiguration[$checkKey]['delay']){
                $this->logger(PHPZA_LL_DEBUG,__FUNCTION__." processing: ".$check['key']);
                try{
                    $this->activeChecksResultsBuffer['data'][$key]['value']=$this->getItem($check['key'])->toValue();
                    $processed++;
                }catch (ZabbixNotSupportedItem $e){
                    //unset($check['value']);//=$e->getMessage();
                    $failed++;
                }
                catch (Exception $e)
                {
                    throw $e; //unregistered exception type TODO: walkaround
                }
                $check['lastRun']=$currentTime;
                $this->serverActiveConfiguration[$checkKey]['lastRun']=$currentTime;

                $this->activeChecksResultsBuffer['data'][$key]['key']=$check['key'];
                $this->activeChecksResultsBuffer['data'][$key]['clock']=time();
                $this->activeChecksResultsBuffer['data'][$key]['ns']=$this->nanosec();
                $this->activeChecksResultsBuffer['data'][$key]['host']=$this->agentHostName;

                $key++;
            }
        }
        $this->activeChecksResultsBuffer['clock']=time();
        $this->activeChecksResultsBuffer['ns']=$this->nanosec();
        $this->logger(PHPZA_LL_INFO,
            __FUNCTION__.
            " done, processed:".$processed.
            ", failed:".$failed.
            ", buffer count:".count($this->activeChecksResultsBuffer['data']));
    }

    /**
     * Check if active configuration needs to be updated, update it if required.
     * @throws ZabbixActiveAgentException
     */
    public function checkForActiveChecksUpdates()
    {
        $currentTime=time();
        if(($currentTime-$this->serverActiveUpdateLast)>$this->serverActiveUpdateInterval)
        {
            $ans='';
            $fp = fsockopen($this->serverActive, $this->serverActivePort, $errno, $errstr, 30);
            if (!$fp) {
                throw new ZabbixActiveAgentException($errstr ($errno));
            } else {
                $data=$this->getActiveRequest();
                $out=ZabbixProtocol::buildPacket($data);
                fwrite($fp, $out);
                while (!feof($fp)) {
                    $ans.=fgets($fp, 128);
                }
                fclose($fp);


                if(ZabbixProtocol::ZABBIX_MAGIC!=substr($ans,0,4))
                    throw new ZabbixActiveAgentException("Invalid packet received. Packet header mismatch.");
                //if(ZabbixProtocol::ZABBIX_DELIMETER!=unpack("C",substr($ans,4,1)))
                //    throw new ZabbixActiveAgentException("Invalid packet received."); //GOT 0x00
                $payloadLength=ZabbixProtocol::getLengthFromPacket(substr($ans,5,8));
                $payloadJson=substr($ans,13,$payloadLength);

                if(strlen($payloadJson)!=$payloadLength)
                    throw new ZabbixActiveAgentException("Invalid packet received. Wrong payload length.");

                $payload=json_decode($payloadJson,true);

                switch (json_last_error()) {
                    case JSON_ERROR_NONE:{break;}
                    case JSON_ERROR_DEPTH:throw new ZabbixActiveAgentException("Configuration update error, JSON_ERROR_DEPTH");
                    case JSON_ERROR_STATE_MISMATCH:throw new ZabbixActiveAgentException("Configuration update error, JSON_ERROR_STATE_MISMATCH");
                    case JSON_ERROR_CTRL_CHAR:throw new ZabbixActiveAgentException("Configuration update error, JSON_ERROR_CTRL_CHAR");
                    case JSON_ERROR_SYNTAX:throw new ZabbixActiveAgentException("Configuration update error, JSON_ERROR_SYNTAX");
                    case JSON_ERROR_UTF8:throw new ZabbixActiveAgentException("Configuration update error, JSON_ERROR_UTF8");
                    default:throw new ZabbixActiveAgentException("Configuration update error, no errorcode provided.");
                }
                if(!isset($payload['data']))
                    throw new ZabbixActiveAgentException("Configuration update error, payload does not contain data, got value:".$payload['data']);

                $this->serverActiveConfiguration=$payload['data'];
                $this->serverActiveUpdateLast=$currentTime;

                $this->logger(PHPZA_LL_DEBUG,__FUNCTION__." update: ".$payloadJson);
                return $payload;
            }
            $this->logger(PHPZA_LL_INFO,__FUNCTION__." done");
        }
    }

    /**
     * Method implements unit of work for server.
     * @throws ZabbixAgentException
     * @return ZabbixAgent
     * @throws ZabbixActiveAgentException
     */
    public function tick()
    {
        //setup socket for incoming connections
        try {
            /**
             * @todo fix @
             */
            $connection = @socket_accept($this->listenSocket);
        } catch (Exception $e) {
            /*
             * Some implementations could transform php-errors to exceptions
             */
            throw new ZabbixAgentSocketException('Socket error on accept.');
        }

        //commands processing
        if ($connection > 0) {
            $commandRaw = socket_read($connection, 1024);

            if ($commandRaw !== false) {
                $command = trim($commandRaw);
//                var_dump($commandRaw);
//                echo "<hr>";
//                var_dump($command);
                try {
                    $agentItem = $this->getItem($command);
                    $buf = ZabbixProtocol::serialize($agentItem);
                } catch (Exception $e) {
                    socket_close($connection);
                    throw new ZabbixAgentException("Serialize item error.", 0, $e);
                }

                $writeResult = socket_write($connection, $buf, strlen($buf));
                socket_close($connection);
                if ($writeResult === false) {
                    throw new ZabbixAgentSocketException('Socket write error.');
                }
            } else {
                throw new ZabbixAgentSocketException('Socket read error.');
            }
        }

        if($this->activeAvailable)
        {
            $this->checkForActiveChecksUpdates();
            $this->processActiveChecks();
            $this->sendActiveChecksResults();
            //TODO store and send stored checks result to reduce network activity (is it required?)
        }

        return $this;
    }

    /**
     * Get item from agent item storage
     * @param string $key
     * @return InterfaceZabbixItem
     */
    public function getItem($key)
    {
        if (!isset($this->items[$key])) {
            return new ZabbixNotSupportedItem(" Key '${key}' not registered.");
        }

        return $this->items[$key];
    }

    public function getItems()
    {
        return $this->items;
    }

    /**
     * Set item to agent storage
     * @param string $key
     * @param InterfaceZabbixItem $val
     */
    public function setItem($key, $val)
    {
        $this->items[$key] = $val;
    }

    public function __toString() {
        return "ZabbixAgent[]";
    }

}
