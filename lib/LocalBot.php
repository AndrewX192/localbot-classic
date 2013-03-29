<?php
/**
 * LocalBot Classic - PHP IRC bot.
 * @author Andrew Sorensen <andrew@localcoast.net>
 * $Id: localbot.class.php,v 3.36a 2009/3/19 14:24:59 $
 */
 
// command character for user functions.
// while not necessary, it is recommended - DEPRECIATED in 3.38a
define('CMD_CHAR', '!');
define('LB_VERSION', '3.38a dev Unstable/Testing');
define('MD_VERSION', '3.38a dev Unstable/Testing');
define('ABI_VERSION', 5000);
define('LB_PATH', $_SERVER['PWD'] . '/');

// where the core classes are found
define('LB_LIB_PATH', LB_PATH . 'lib/');

// directory where module classes are found
define('MD_SRC_PATH', LB_PATH . 'modules/');

// directory for permanent data
define('PERM_DATA', LB_PATH . 'var/');

// log directory and file
define('LOG_PATH',  PERM_DATA . 'log/');
define('LOG_FILE', LOG_PATH . 'general.log');

// Core Libraries.
require_once(LB_LIB_PATH . 'Module.php');
require_once(LB_LIB_PATH . 'DataStore.php');
require_once(LB_LIB_PATH . 'FileStorage.class.php');
require_once(LB_LIB_PATH . 'DataProvider.class.php');

class LocalBot {
    /**
     * An array of modules.
     */
    private $modules = array();
    
    /**
     * The bot's configuration.
     */
    private $config = array();

    /**
     * Runtime configuration.
     */
    private $runtime = array(
	'logging' => false,
    );
    
    /**
     * The connection.
     */
    private static $connection;
    
    /**
     * The current message buffer.
     */
    private static $buffer;
    public $reconnect = true; // reconnection attempts on by default
    
    /**
     * Constructs a new bot with the given configuration.
     *
     * @parm string $config The bot configuration.
     */
    function __construct($config) {
        $this->config = $config;
        declare (ticks = 1);
        set_time_limit(0);
        error_reporting(E_ALL ^ E_NOTICE);
        date_default_timezone_set($this->config['time_zone']);
        $this->pwds = array();
        $this->runtime['nick'] = $this->config['nick'];
        self::$connection = false;
        $this->runtime['logging'] = true;
        $this->oper_file = $this->config['oper_file'];
        $this->runtime['logchan'] = $this->config['logchan'];
        
        if ($this->config['signal_handler'] == true) {
            pcntl_signal(SIGINT, array(self, 'handleSig'));
            pcntl_signal(SIGTERM, array(self, 'handleSig'));
            pcntl_signal(SIGHUP, array(self, 'handleSig'));
            pcntl_signal(SIGUSR1, array(self, 'handleSig'));
            pcntl_signal(SIGUSR2, array(self, 'handleSig'));
            set_error_handler(array(self, 'handleError'));
        }

        $this->runtimeaprovider = new DataProvider();
        $this->setBotName($this->config['nick']);
        $this->loadOpers();
        $fs = new FileStorage($this->config['pid_file'], FS_WRITE);
        $fs->write(getmypid());
        $this->syslog("LocalBot " . LB_VERSION 
                . " Starting at (Local Console) [Core size: " 
                . round(memory_get_peak_usage() / 1024, 2) . " KB]");
        $this->addModules($this->config['modules']);
    }

    /**
     * Send a message to LocalBot's system logger.
     *
     * @param string $message The actual message to be sent to syslog
     */
    function syslog($message) {
        echo date("[n/j/Y-H:i:s ") . get_class($this) . ": " . $message . "]" 
                  . PHP_EOL;
    }

    /**
     * Connects to the server using the internal configuration.
     *
     * @return whether or not the connection was successful.
     */
    function connect() {
        if ($this->config['ssl'] == true && $this->config['ssl_mod'] == 'tls' 
                || $this->config['ssl_mod'] == 'ssl') {
            $this->config['active_server'] = $this->config['ssl_mod'] 
                    . '://' . $this->config['server'];
	}
        else {
            $this->config['active_server'] = $this->config['server'];
	}
        
        if (isset($this->config['network'])) {
            echo "[LocalBot: Connecting to " . $this->config['network'] 
                    . ". Server: " . $this->config['server'] 
                    . " on port " . $this->config['port'] . "]\n";
	}
        else {
            echo("[LocalBot: Connecting to Server: " 
                    . $this->config['server'] . " on port " 
                    . $this->config['port'] . "]\n");
	}

        $this->runtime['connectiontime'] = microtime(true);
        self::$connection = @fsockopen($this->config['active_server'],
                                       $this->config['port']);
        if (!self::$connection) {
            $this->output("A connection with server " . $this->config['server'] 
                    . " could not be established using port " 
                    . $this->config['port']);
            return false;
        }
        echo("[LocalBot: Connected. Now logging in..]\n.");
        $this->send("USER " . $this->config['nick'] . " " 
                . $this->config['hostname'] . " " . $this->config['servername'] 
                . " :" . $this->config['realname']);
        $this->send("NICK " . $this->config['nick']);
        //$this->send("PROTOCTL NAMESX UHNAMES");

        $i = 0;
        while (!feof(self::$connection)) {
            self::$buffer['raw'] = trim(fgets(self::$connection, 4096));

            self::output(date("[d/m @ H:i:s]") . "<- " . self::$buffer['raw'] 
                    . "");
            if (strpos(
                    self::$buffer['raw'], 'Nickname is already in use.'
                ) !== FALSE
                || strpos(
                    self::$buffer['raw'],
                    'Services reserved nickname: Registered nickname.'
                ) !== FALSE) {
                $i++;
                $this->runtime['nick'] = $this->config['nick'] . "-" . $i;
                $this->send("NICK " . $this->runtime['nick']);
            } else {
                if (strpos(LocalBot::$buffer['raw'], '376') !== FALSE) {
                    if (isset($this->config['nickserv_pass']) 
                            && $this->config['nickserv_pass'] != '')
                        $this->send("PRIVMSG NickServ :IDENTIFY " 
                                . $this->config['nickserv_pass']);
                    if (isset($this->config['oper_username']) 
                            && isset($this->config['oper_pass'])) {
                        $this->send("OPER " . $this->config['oper_username'] 
                                . " " . $this->config['oper_pass']);
                    }
                        if (isset($this->config['user_modes']) 
                                && $this->config['user_modes'] != '') {
                            $this->send("MODE " . $this->runtime['nick'] 
                                    . " " . $this->config['user_modes']);
                        }
                }
                $this->join($this->config['logchan']);
                return true;
            }
        }
    }

    /**
     * Join a channel and gather the required information about it.
     * @param string $channel The channel to join.
     */
    function join($channel) {
        $this->syslog("Attempting to join channel: " . $channel);
        $this->send("JOIN " . $channel);
        $this->send("MODE " . $channel);
        $this->send("MODE " . $channel . ' b');
        $this->send("WHO "  . $channel);
    }

    /**
      The main business of the bot. While a connection exists it will
      forward all input to the plugin classes and issue any output they have have.
      logging if on.
      @return void
     */
    function listen() {
        if ($this->config['kernel_tick']) {
            $this->config['kernel_tick'] = (11 * 1000);
	}
	
        socket_set_blocking(self::$connection, false);
        while (!feof(self::$connection)) {
            usleep($this->config['kernel_tick']);
            self::$buffer['raw'] = trim(fgets(self::$connection, 4096));

            // If all is quiet, proceed once per second. (letting modules do timed events)
            if (strlen(self::$buffer['raw']) <= 0) {
                if ($t == time())
                    continue;
            }

            $t = time();

            // respond to PINGs
            if (substr(self::$buffer['raw'], 0, 6) == 'PING :') {
                $this->send('PONG :' . substr(self::$buffer['raw'], 6));
                continue;
            }

            // make sense of the buffer
            $this->parseBuffer();

            if (self::$buffer['0'] != '') {
                if (!isset(self::$buffer['channel']))
                    self::$buffer['channel'] = "";
                $c = str_replace(":", "", self::$buffer['channel']);
                if (strcmp(self::$buffer['channel'], $c) == 0 
                        && strpos(self::$buffer['channel'], ':') !== true 
                        && isset(self::$buffer['text'])) {
                    $this->output(date("[d/m @ H:i:s]") . " [" . $c . "] " 
                            . self::$buffer['text']);
                } elseif (isset(self::$buffer['text'])) {
                    $this->output(date("[d/m @ H:i:s]") . " (" 
                            . self::$buffer['channel'] . ") <" 
                            . self::$buffer['username'] . "> " 
                            . self::$buffer['text']);
                }
            }
            // now process any commands issued to the bot
            $this->process();
            if (isset(self::$buffer['raw'])) {
                $last = self::$buffer['raw'];
	    }
        }
    }

    /**
     * Performs an operation "cycle".
     *
     */
    function process() {
        $buff = & self::$buffer;
        if (is_array($this->modules)) {
            foreach (array_keys($this->modules) as $moduleId) {
		if (!is_object($this->modules[$moduleId])) {
		    $this->syslog("Problem with module $moduleId, removing...");
		    unset($this->modules[$moduleId]);
		    continue;
		}
		// Runs operations for each module
		$response = $this->modules[$moduleId]->listen($buff);
		
		// Ziggi/batch compatibility system
		if (is_array($response)) {
		    // send any server commands (quit, kick, etc) DEPRECIATED in 3.38a
		    if (isset($response['md_send']) && is_array($response['md_send'])) {
			foreach ($response['md_send'] as $e)
			    $this->send($e);
		    }
		    if (isset($response['pm']) && is_array($response['pm'])) {
			foreach ($response['pm'] as $pm) {
			    if (is_array($pm)) {
				$to = ($pm[1] == false ? $channel : $pm[1]);
				$this->pm($pm[0], $to);
			    }
			    else {
				$this->pm($pm, $response['channel']);
			    }
			}
		    }
		    if (isset($response['notice']) && is_array($response['notice'])) {
			foreach ($response['notice'] as $notice) {
			    if (is_array($notice)) {
				$to = ($notice[1] == false ? $channel : $notice[1]);
				$this->notice($notice[0], $to);
			    }
			    else {
				$this->notice($notice, $response['channel']);
			    }
			}
		    }
		}
            }
            unset($this->last_invcommand);
            unset($this->last_vcommand);
        }
    }

    /**
     * Parses incomming IRC messages.
     *
     */
    function parseBuffer() {
        global $data; // TODO remove globals.
        $buffer = explode(" ", self::$buffer['raw'], 4);
        if (strpos($buffer[0], "!")) {
            $buffer['username'] = substr($buffer[0], 1, strpos($buffer[0], "!") - 1);
        } else {
            $buffer['username'] = $buffer[0];
        }
        $a = strpos($buffer[0], "!");
        $b = strpos($buffer[0], "@");
        $buffer['ident'] = substr($buffer[0], $a + 1, $b - $a - 1);
        $buffer['hostname'] = substr($buffer[0], strpos($buffer[0], "@") + 1);
        $buffer['user_host'] = substr($buffer[0], 1);
        if (!isset($buffer[1])) {
            $buffer['command'] = null;
            $buffer['channel'] = null;
            // Looks like it was from another person.
            $buffer['channel'] = $buffer['username'];
            $buffer['msgtype'] = "PM";

            $buffer['text'] = null;
        }
        else
            switch (strtoupper($buffer[1])) {
                case "JOIN":
                    $buffer['text'] = "*JOINS: " . $buffer['username'] . " ( " . $buffer['user_host'] . " )";
                    $buffer['command'] = "JOIN";
                    $buffer['channel'] = $buffer[2];
                    $buffer['channel'] = str_replace(':', '', $buffer['channel']);
                    $data[$buffer['channel']]['USERLIST'][$buffer['username']] = '';
                    $data['USERS'][$buffer['username']]['host'] = $buffer['user_host'];
                    break;
                case "QUIT":
                    $buffer['text'] = "*QUITS: " . $buffer['username'] . " ( " . $buffer['user_host'] . " )";
                    $buffer['command'] = "QUIT";
                    $buffer['channel'] = "unknown";
                    foreach ($data as $i => $channel) {
                        unset($data[$i]['USERLIST'][$buffer['username']]);
                        $data[$i]['USERCOUNT'] = count($data[$i]['USERLIST']);
                    }
                    break;
                case "NOTICE":
                    $buffer['text'] = "*NOTICE: " . $buffer['username'];
                    $buffer['command'] = "NOTICE";
                    if (isset($buffer['channel']) && strpos($buffer['channel'], "#") === false) {
                        // Looks like it was from another person.
                        $buffer['channel'] = $buffer['username'];
                        $buffer['msgtype'] = "PM";
                    }
                    else
                        $buffer['channel'] = null;
                    $buffer['text'] = substr($buffer[3], 1);
                    break;
                case "KICK":
                    $buffer['text'] = "*KICKED: " . $buffer['username'] . " ( " . $buffer['user_host'] . " )";
                    $buffer['command'] = "KICK";
                    $buffer['channel'] = $buffer[2];
                    $buffer['kicked'] = $buffer[3];
                    unset($data[$buffer['channel']]['USERLIST'][$buffer['username']]);
                    $kicked = explode(' ', $buffer['kicked']);
                    $x = explode(" ", trim($buffer['kicked']), 2);
                    $reason = $x[1];
                    if ($kicked[0] == $this->runtime['nick'])
                        unset($data[$buffer['channel']]);
                    if ($kicked[0] == $this->runtime['nick']) {
                        if ($reason == ':(This channel has been closed)') {
                            $this->pm("CHANSERV:CLOSE " . $buffer['channel'], $this->runtime['logchan']);
                            return;
                        } else {
                            $this->join($buffer['channel']);
                            $this->pm("Please do not kick " . $this->runtime['nick'] . " from the channel; Instead type /msg " . $this->runtime['nick'] . " Unassign " . $buffer['channel'], $buffer['channel']);
                        }
                    }
                    break;
                case "PART":
                    $buffer['text'] = "*PARTS: " . $buffer['username'] . " ( " . $buffer['user_host'] . " )";
                    $buffer['command'] = "PART";
                    $buffer['channel'] = $buffer[2];
                    if ($buffer['username'] == $this->runtime['nick'])
                        unset($data[$buffer['channel']]);
                    unset($data[$buffer['channel']]['USERLIST'][$buffer['username']]);
                    break;
                case "352":
                    $b = $buffer[3];
                    $t = explode(" ", $b, 2);
                    $r = explode(" ", $t[1]);
                    $buffer['command'] = $buffer[1];

                    $chan = $t[0];
                    $user = strtolower($r[3]);
                    $host = $r[1];
                    $data['USERS'][$user]['host'] = $host;
                    break;
                case "353":
                    $t = explode(" ", $buffer[3], 2);
                    $t = explode(" ", $t[1], 2);
                    $buffer['channel'] = $t[0];
                    $users = str_replace(':', '', $t[1]);
                    $users2 = explode(" ", $users);
                    $buffer['command'] = $buffer[1];
                    $total = count($users2);
                    $prefixes = '~&@%+';
                    for ($i = 0; $i < $total; $i++) {
                        for ($s = 0; $s < strlen($prefixes); $s++) {
                            $v = strpos($users2[$i], $prefixes[$s]);
                            if ($v === false) {
                                $u = str_replace('~', '', $users2[$i]);
                                $u = explode("!", $u);
                                $u = $u[0];
                                $u = str_replace('&', '', $u);
                                $u = str_replace('@', '', $u);
                                $u = str_replace('%', '', $u);
                                $u = str_replace('+', '', $u);
                                if (!isset($data[$buffer['channel']]['USERLIST'][$u]))
                                    $data[$buffer['channel']]['USERLIST'][$u] = '';
                                $data[$buffer['channel']]['USERLIST'][$u].='';
                            }
                            else {

                                $u = str_replace('~', '', $users2[$i]);
                                $u = explode("!", $u);
                                $u = $u[0];
                                $u = str_replace('&', '', $u);
                                $u = str_replace('@', '', $u);
                                $u = str_replace('%', '', $u);
                                $u = str_replace('+', '', $u);
                                if (!isset($data[$buffer['channel']]['USERLIST'][$u]))
                                    $data[$buffer['channel']]['USERLIST'][$u] = '';
                                $data[$buffer['channel']]['USERLIST'][$u].=$prefixes[$s];
                            }
                        }
                    }
                    $data[$buffer['channel']]['USERCOUNT'] = count($users2);
                    break;
                case "367":
                    $t = explode(" ", $buffer[3], 2);
                    $buffer['channel'] = $t[0];
                    $t = explode(" ", $t[1], 2);
                    $users = str_replace(':', '', $t[1]);
                    $users2 = explode(" ", $users);
                    unset($data[$buffer['channel']]['BANS'][$t[0]]);
                    $data[$buffer['channel']]['BANS'][$t[0]] = array($users2[1], $users2[0]);
                    break;
                case "MODE":
                    $buffer['modes'] = $buffer[3];
                    $buffer['text'] = $buffer['username'] . " sets mode: " . $buffer[3];
                    $buffer['command'] = "MODE";
                    $buffer['channel'] = $buffer[2];
                    //handle modes in localbot
                    $modes = explode(' ', $buffer['modes']);
                    $mode = $modes[0];
                    $recv = explode(" ", $buffer['modes'], 2);
                    if (isset($recv[1])) {
                        $recv = explode(' ', $recv[1]);
                        $this->handleMode($buffer['channel'], $mode, $recv);
                    }

                    break;
                case "324":
                    $t = explode(" ", $buffer[3], 2);
                    $r = explode(" ", $t[1], 2);
                    $buffer['channel'] = $t[0];
                    $buffer['modes'] = $buffer[3];
                    $modes = explode(' ', $buffer['modes']);
                    $mode = $modes[1];
                    $recv = explode(" ", $buffer['modes'], 2);
                    $recv = explode(' ', $recv[1]);
                    /** FIXME **/
                    $recv[0] = $recv[1];
                    $this->handleMode($buffer['channel'], $mode, $recv);
                    break;
                case "329":
                    $t = explode(" ", $buffer[3], 2);
                    $buffer['channel'] = $t[0];
                    $data[$buffer['channel']]['CHANNEL_CREATED'] = $t[1];
                    break;
                case "NICK":
                    $buffer['text'] = "*NICK: " . $buffer['username'] . " => " . substr($buffer[2], 1) . " ( " . $buffer['user_host'] . " )";
                    $buffer['command'] = "NICK";
                    $buffer['channel'] = "unknown";
                    foreach ($data as $i => $channel) {
                        $data[$i]['USERLIST'][$buffer[2]] = $channel['USERLIST'][$buffer['username']];
                        unset($data[$i]['USERLIST'][$buffer['username']]);
                        $data[$i]['USERCOUNT'] = count($channel['USERLIST']);
                    }
                    break;

                default:
                    // it is probably a PRIVMSG
                    $buffer['command'] = $buffer[1];
                    $buffer['channel'] = $buffer[2];
                    if (strpos($buffer['channel'], "#") === false) {
                        // Looks like it was from another person.
                        $buffer['channel'] = $buffer['username'];
                        $buffer['msgtype'] = "PM";
                    }
                    $buffer['text'] = substr($buffer[3], 1);
                    break;
            }
        if (isset($buffer['channel'])) {
            if (substr($buffer['channel'], 0, 1) == ':')
                $buffer['channel'] = substr($buffer['channel'], 1);
        }
        self::$buffer = $buffer;
    }

    function handleMode($channel, $modes, $recv) {
        global $data; // FIXME
        $add = false;
        $remove = false;
        $modelen = strlen($modes);
        for ($i = 0; $i < $modelen; $i++) {
            switch ($modes[$i]) {
                case '-':
                    $remove = true;
                    $add = false;
                    break;
                case '+':
                    $add = true;
                    $remove = false;
                    break;
                case 'q':
                    if ($add)
                        $data[$channel]['USERLIST'][$recv[0]].='~';
                    if ($remove)
                        $data[$channel]['USERLIST'][$recv[0]] = str_replace('~', '', $data[$channel]['USERLIST'][$recv[0]]);
                    array_shift($recv);
                    break;
                case 'a':
                    if ($add)
                        $data[$channel]['USERLIST'][$recv[0]].='&';
                    if ($remove)
                        $data[$channel]['USERLIST'][$recv[0]] = str_replace('&', '', $data[$channel]['USERLIST'][$recv[0]]);
                    array_shift($recv);
                    break;
                case 'o':
                    if ($add)
                        $data[$channel]['USERLIST'][$recv[0]].='@';
                    if ($remove)
                        $data[$channel]['USERLIST'][$recv[0]] = str_replace('@', '', $data[$channel]['USERLIST'][$recv[0]]);
                    array_shift($recv);
                    break;
                case 'h':
                    if ($add)
                        $data[$channel]['USERLIST'][$recv[0]].='%';
                    if ($remove)
                        $data[$channel]['USERLIST'][$recv[0]] = str_replace('%', '', $data[$channel]['USERLIST'][$recv[0]]);
                    array_shift($recv);
                    break;
                case 'v':
                    if ($add)
                        $data[$channel]['USERLIST'][$recv[0]].='+';
                    if ($remove)
                        $data[$channel]['USERLIST'][$recv[0]] = str_replace('+', '', $data[$channel]['USERLIST'][$recv[0]]);
                    array_shift($recv);
                    break;
                case 'b':
                    if ($add)
                        $data[$channel]['BANS'][$recv[0]] = array(time(), $buffer['username']);
                    if ($remove)
                        unset($data[$channel]['BANS'][$recv[0]]);
                    array_shift($recv);
                    break;
                case 'G':
                    if ($add)
                        $data[$channel]['CENSORED'] = array(time(), 'YES');
                    if ($remove)
                        $data[$channel]['CENSORED'] = array(time(), 'NO');
                    break;
                case 'S':
                    if ($add)
                        $data[$channel]['COLORSTRIP'] = array(time(), 'YES');
                    if ($remove)
                        $data[$channel]['COLORSTRIP'] = array(time(), 'NO');
                    break;
                case 'C':
                    if ($add)
                        $data[$channel]['NOCHANCTCP'] = array(time(), 'YES');
                    if ($remove)
                        $data[$channel]['NOCHANCTCP'] = array(time(), 'NO');
                    break;
                case 'T':
                    if ($add)
                        $data[$channel]['NOCHANNOTICE'] = array(time(), 'YES');
                    if ($remove)
                        $data[$channel]['NOCHANNOTICE'] = array(time(), 'NO');
                    break;
                case 'n':
                    if ($add)
                        $data[$channel]['NOEXTERNALMSG'] = array(time(), 'YES');
                    if ($remove)
                        $data[$channel]['NOEXTERNALMSG'] = array(time(), 'NO');
                    break;
                case 't':
                    if ($add)
                        $data[$channel]['PROTECTEDTOPIC'] = array(time(), 'YES');
                    if ($remove)
                        $data[$channel]['PROTECTEDTOPIC'] = array(time(), 'NO');
                    break;
                case 'm':
                    if ($add)
                        $data[$channel]['MODERATED'] = array(time(), 'YES');
                    if ($remove)
                        $data[$channel]['MODERATED'] = array(time(), 'NO');
                    break;
                case 'l':
                    if ($add)
                        $data[$channel]['LIMIT'] = array(time(), $recv[0]);
                    if ($remove)
                        $data[$channel]['LIMIT'] = array(time(), '0');
                    break;
                case 'f':
                    if ($add)
                        $data[$channel]['FLOOD'] = array(time(), $recv[0]);
                    if ($remove)
                        $data[$channel]['FLOOD'] = array(time(), 'NO');
                    break;
                case 'k':
                    if ($add)
                        $data[$channel]['KEY'] = array(time(), $recv[0]);
                    if ($remove)
                        $data[$channel]['KEY'] = array(time(), '');
                    break;
                default:
                    if ($add)
                    //$this->pm("Channel mode +".$mode[$i]." set (extdata: ".$recv[0] .")",$channel);
                        if ($remove)
                        //$this->pm("Channel mode -".$mode[$i]." set (extdata: ".$recv[0] .")",$channel);
                            break;
            }
        }
    }

    /**
     * Sends a PRIVMSG to a source.
     *
     * @param string What to say
     * @param string Where to say it.
     */
    public static function pm($message, $channel = '') {
        $channel = ($channel == "") ? self::$buffer['channel'] : $channel;
        
        if (is_array($message)) {
            foreach ($message as $msg) {
                $this->send('PRIVMSG ' . $channel . ' :' . $msg);
            }
        } else {
            self::send('PRIVMSG ' . $channel . ' :' . $message);
        }
    }

    /**
     * Sends a NOTICE to a source.
     *
     * @param string What to say
     * @param string Where to say it.
     */
    public static function notice($message, $channel = "") {
        // If a channel was defined, use it, else use the channel the command came from.
        $channel = ($channel == "") ? self::$buffer['channel'] : $channel;
        if (is_array($message)) {
            foreach ($message as $msg) {
                $pm = 'NOTICE ' . $channel . ' :' . $msg;
                self::send($pm);
            }
        } else {
            $pm = 'NOTICE ' . $channel . ' :' . $message;
            self::send($pm);
        }
    }

    /**
     * Disconnects the bot from the IRC server.
     */
    public static function disconnect($message) {
        self::send("QUIT :" . $message);

        $this->con->pending_quit = true;
    }

    /**
     * Prints output to the stdout, logs to log file.
     * 
     * @param string buffer line
     */
    public static function output($line) {
        echo $line . "\n";

        // Don't print PING or PONG messages.
        if (strpos($line, 'PONG') === FALSE && strpos($line, 'PING') === FALSE) {
            self::log($line, LOG_FILE);
	}
    }

    /**
     * Logs a line.
     * 
     * @param string line to log
     * @param string /path/to/logfile
     * @return void
     */
    public static function log($line, $file) {
        if (!$this->runtime['logging']) {
            return;
        }

        $fs = new FileStorage($file, FS_APPEND);
        $fs->write($line);
    }

    /**
     * Sends text to the IRC Server.
     *
     * @param string The command to send.
     */
    public static function send($command) {
        fputs(self::$connection, $command . "\n\r");

        self::output(date("[d/m @ H:i:s]") . "-> " . $command);
    }

    /**
     * Returns the nick or bot's name if false (used to avoid certain situations)
     * 
     * @return string 
     */
    function getUsername() {
        return self::$buffer['username'];
    }

    /**
     * Returns the name of the bot.
     * 
     * @return string The bot's name.
     */
    public function getBotName() {
        return $this->runtime['nick'];
    }

    /**
     * Sets the name of the bot.
     * 
     * @param string $name The new name of the bot.
     * 
     * @return LocalBot
     */
    function setBotName($name) {
        $this->runtime['nick'] = $name;
        
        return $this;
    }

    /**
     * load several plugin class files
      @access public
      @param array of mixed vars , module filename or array(filename,params)
      @return void
     */
    function addModules($modules) {
        if (!is_array($modules)) {
            return;
        }
        foreach ($modules as $module) {
            if (is_scalar($module)) {
                $this->addmodule($module);
            } else if (is_array($module) && isset($module[1])) {
                $this->addmodule($module[0], $module[1]);
            } else {
                $this->addmodule($module[0]);
            }
        }
        $this->syslog("Loaded all modules [Memory use: " 
                . round(memory_get_peak_usage() / 1024, 2) . " KB]");
        
        // Modules loaded into memory - start initializing modules.
        if ($this->runtime['initializing']) {
            foreach (array_keys($this->modules) as $mid) {
                if (method_exists($this->modules[$mid], 'moduleReady')) {
                    $this->modules[$mid]->moduleReady();
                }
            }
        }
    }

    /**
      load a single plugin class file
      @access public
      @param string file name
      @param array additional parameters
      @return boolean load success
     */
    function addmodule($z, $params = false) {
        /**if (isset($this->modules) && !is_array($this->modules)) {
            $this->modules = array();
	}**/

        $f = MD_SRC_PATH . $z;
        if ($cls = $this->getmoduleClassName($f)) {
            require_once($f);
            eval(" \$a = new $cls();");
            $this->modules[$cls] = $a;
            $this->modules[$cls]->setLogChan($this->config['logchan']);
            if ($params['cron']) {
                $this->modules[$cls]->addCron($params['cron']);
	    }

	    $this->syslog("\033[0;36mPlugin '$cls' loaded\033[0m");
            
            return true;
        }
        else {
            $this->syslog("$cls $f not a class");
            return false;
        }
    }

    /**
     * Removes a module from LocalBot.
     * 
     * @param   string  $module
     * @return  boolean
     */
    public function removeModule($module) {
        if (!isset($this->modules[$module])) {
            return false;
        }
        if (method_exists($this->modules[$module], 'moduleUnload')) {
            $this->modules[$module]->moduleUnload();
        }
        unset ($this->modules[$module]);
        $this->syslog("\033[0;36mPlugin '$module' removed\033[0m");
    }

    /**
     * Returns a class name, given a file.
     * 
     * @param    string     $filename
     * 
     * @return   string     The name of the class.
     */
    private function getmoduleClassName($f) {
        if (!is_readable($f)) {
            return false;
        }

        if (!$lines = file($f)) {
            return false;
        }

        foreach ($lines as $t) {
            $x = explode(" ", strtolower(trim($t)));
            if ($x[0] == 'class' && $x[2] == 'extends') {
                return $x[1];
            }
        }
        return false;
    }

    function taint($call = false, $reason = '') {
        if (isset($call)) {
            $this->runtime['tainted'] = true;
	}

        $this->syslog("WARNING: One or more of your modules have tainted localbot.");
        $this->syslog("WARNING: You will not be able to receive support for LocalBot until you remody this issue.");
    }

    /**
     * Unloads all modules.
     */
    private function shutdown() {
        foreach (array_keys($this->modules) as $mid) {
            $this->removeModule($mid);
        }
    }

    /**
     * Exits the bot.
     */
    function shutdownBot() {
        foreach (array_keys($this->modules) as $moduleId) {
            $this->removeModule($moduleId);
        }

        $this->send("QUIT :Shutting down.");
        sleep(1); // FIXME: flush the buffer
        exit();
    }

    function rehash() {
        if (file_exists($this->oper_file)) {
            include $this->oper_file;
            foreach ($opers as $oper => $op) {
                $this->opers[$oper] = $op;
            }
        }
    }

    function loadOpers() {
        if (file_exists($this->oper_file)) {
            require $this->oper_file;
            foreach ($opers as $oper => $op) {
                $this->opers[$oper] = $op;
            }
        }
    }

    /**
     * Signal handler.
     */
    function handleSig($sig) {
        switch ($sig) {
            case SIGTERM:
                $this->shutdown();
                $this->send("QUIT :Shutting down on SIGTERM");
                sleep(1); // FIXME: Hack to flush buffer.
                exit();
                break;
            case SIGINT:
                $this->shutdown();
                $this->send("QUIT :Shutting down on SIGINT");
                sleep(1); // FIXME: Hack to flush buffer.
                exit();
                break;
            case SIGUSR1:
                $this->shutdown();
                $this->send("QUIT :Shutting down on advanced control protocol (Signal 1)");
                sleep(1); // FIXME: Hack to flush buffer.
                exit();
                break;
            case SIGUSR2:
                $this->shutdown();
                $this->send("QUIT :Shutting down on advanced control protocol (Signal 2)");
                sleep(1); // FIXME: Hack to flush buffer.
                exit();
                break;
            case SIGHUP:
                $this->rehash();
                break;
            default:
        }
    }

    /**
     * Finds the name of a class given a particular file.
     *
     * @param 	string $filename The name of the file to look in.
     *
     * @return 	string The name of the class.
     */
    private function getmoduleClassAccess($filename) {
        if (!is_readable($filename)) {
            return false;
        }

        if (!$lines = file($filename)) {
            return false;
        }

        foreach ($lines as $t) {
            $x = explode(" ", strtolower(trim($t)));

            if ($x[0] == 'class' && $x[2] == 'extends') {
                return $x[1];
            }
        }

        return false;
    }

    /**
     * Handles errors.
     *
     * @param int	$errorno 	The error number
     * @param string 	$errstr 	A string representing the error.
     */
    function handleError($errno, $errstr, $errfile, $errline) {
        switch ($errno) {
            case E_USER_ERROR:
                $this->pm("LocalBot has run into a fatal error and needs to close, sorry.", '#sys-localbot');
                $this->pm("Please file a bug a the bugtracker at http://support.localcoast.net/", '#sys-localbot');
                $this->pm("Fatal error on line $errline in file $errfile", '#sys-localbot');

                echo "PHP ERROR: [$errno] $errstr\n";
                echo "  Fatal error on line $errline in file $errfile";
                echo ", PHP " . PHP_VERSION . " (" . PHP_OS . ")<br />\n";
                echo "Aborting...\n";
                exit(1);
                break;

            case E_USER_WARNING:
                $this->pm("LocalBot recieved a WARNING: [$errno] $errstr", '#sys-localbot');
                echo "PHP WARNING: [$errno] $errstr<br />\n";
                break;
            case E_USER_NOTICE:
                echo "PHP NOTICE: [$errno] $errstr<br />\n";
                break;
            default:
                $this->syslog("Error: $errstr on line $errline in file $errfile", 'DEBUG');
                //self::pm("Unknown error has occured: Type $errstr on line $errline in file $errfile", '#sys-localbot');
                break;
        }
        return true;
    }
}
