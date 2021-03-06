<?php
/**
 * LocalBot Generic Module Interface
 * @author Andrew Sorensen <andrew@localcoast.net>
 *
 * $Id: module.php,v 3.3 2009/03/20 11:22:13 $
 */
 
define('AC_NONE',   '0');
define('AC_VOICE',  '1');
define('AC_HALFOP', '2');
define('AC_OP',     '3');
define('AC_ADMIN',  '4');
define('AC_OWNER',  '5');
define('UA_NONE',   'UA_NONE');
define('FANTASY',   'FANTASY');
define('PRIVMSG',   'PRIVMSG');

abstract class module {
    /**
     * Contains an instance of LocalBot.
     * 
     * @var LocalBot 
     */
    private $localbot;
    
    /**
     * The LocalBot configuration.
     * 
     * @var array 
     */
    private $config = null;
    
    /**
     * Constructs a module.
     * 
     * @param LocalBot $localbot
     */
    public function __construct(LocalBot $localbot) {
        $this->localbot = $localbot;
    }

    /**
     * Sets the instance of LocalBot.
     *
     * @param LocalBot $localbot
     */
    public function setLocalBot(LocalBot $localbot) {
        $this->localbot = $localbot;
    }

    /**
     * Called when a module should process an event.
     * 
     * @param  array    $buffer
     * @param  boolean  $access
     * 
     * @return array
     */
    public function listen($buffer = array(), $access = true) {
        $this->md_buffer = $buffer;
        $this->md_ret = false;

        if (!$this->checkConfig()) {
            return;
        }
        $this->checkTimedEvents();
        $this->current_hook = $this->_generateHook();
        $this->processHooks();
        $this->processCommands();
        $this->runMethods();
        $this->parseBuffer();
        return $this->m_finish();
    }

    /**
     * Checks the module configuration.
     * 
     * @return boolean
     */
    protected function checkConfig() {
        if (NULL === $this->config) {
            $this->syslog("\033[0;31mNo suitable configuration could be " 
                    . "found for the module; will continue with defaults."
                    . "\033[0m", get_class($this));
            
            $this->config = array();
            
            // TODO implement this.
            $this->syslog("\033[0;31mConfiguration not yet ready; " 
                    . " will try late-startup.\033[0m", get_class($this));
            return false;
        }

        if (NULL === $this->localbot) {
            $this->localbot("LocalBot instance undefined on module. Module cannot operate.");
        }

        return true;
    }

    /**
     * Run the methods of the plugin, for OOP extention.
     * @return true
     */
    protected function runMethods() {
        return true;
    }

    /**
     * Logs an event.
     *
     * @var $message 	the message to log.
     * @var $from 	the source of the message.
     */
    function syslog($message, $from = false) {
        if (!$from) {
            $from = get_class($this);
	}
	
        echo date('[n/j/Y-H:i:s ') . $from . ': ' . $message . "]\n";
    }

    /**
     * Wrapper class for LocalBot->setBotName()
     * 
     * @param string $name The name to give the bot
     */
    protected function setBotName($name) {
        $this->setBotName($name);
    }

    /**
     * Returns the bot's nick.
     * 
     * @return string The bot's name.
     */
    protected function getBotName() {
        return $this->localbot->getBotName();
    }

    /**
     * Returns whether or not the message sent to the bot was empty.
     * 
     * @return boolean
     */
    protected function isEmpty() {
        return (trim($this->getText()) == '');
    }

    /**
     * Sends a message to a given user.
     *
     * @param string	What to say.
     * @param string 	a nick or channel. When false, responds to where to request came from.
     */
    protected function pm($message, $to = null) {
        if (!$to) {
            $to = $this->getOrigin();
	}
        
        $this->localbot->pm($message, $to);
    }
    
    /**
     * Sends a IRC action message to a given target.
     *
     * @param string	What to say.
     * @param string 	a nick or channel. When false, responds to where to request came from.
     */
    protected function pmAction($what, $to = null) {
        if (!$to) {
            $to = $this->getOrigin();
        }

        $this->pm("\001ACTION " . $what . "\001", $to);
    }

    /**
     * Send an IRC Notice to a given target.
     * 
     * @param   string  $what
     * @param   string $to
     */
    protected function notice($what, $to = false) {
        if (!$to) {
            $to = $this->getUser();
        }

        $this->localbot->notice($what, $to);
    }

    /**
     * Sends an IRC Notice action to a given target.
     * 
     * @param   string  $what
     * @param   string $to
     */
    protected function noticeAction($what, $to = false) {
        if (!$to) {
            $to = $this->getUser();
        }

        $this->notice("\001ACTION " . $what . "\001", $to);
    }

    /**
     * Wrapper function: calls localbot::join()
     * join a channel and gather basic information about it.
     * @param string $channel the channel to join
     */
    protected function join($channel) {
        $this->join($channel);
    }

    /**
     * Sends a RAW IRC message to the server.
     * 
     * @param   string  $message    What to send.
     */
    protected function send($message) {
        $this->localbot->send($message);
    }

    /**
      @return string Nick of the user that said something
     */
    protected function getUser() {
        return $this->md_buffer['username'];
    }

    /**
     * Returns any modes that were set.
     * 
     * @return string
     */
    protected function getMode() {
        return $this->md_buffer['modes'];
    }

    /**
     * Returns the user and hostname of the user that made the request.
     * 
     * @return string
     */
    protected function getUserHost() {
        return $this->md_buffer['user_host'];
    }

    /**
      @return string Hostname of the user
     */
    function getHostName() {
        return $this->md_buffer['hostname'];
    }

    /**
      @return string User Indent
     */
    function getIdent() {
        return $this->md_buffer['ident'];
    }

    /**
      @return string Channel name (or nick if a private msg)
     */
    function getOrigin() {
        if (isset($this->md_buffer['channel']))
            return $this->md_buffer['channel'];
        return false;
    }

    /**
      @return string The $i-nth argument, separated by spaces
     */
    function getArg($i) {
        if (isset($this->md_buffer['text']))
            $a = ( $this->md_buffer['text'] ? $this->md_buffer['text'] : '' );
        //if(isset($this->md_buffer['event']))
        //$a = ( $this->md_buffer['event'] ? $this->md_buffer['event'] : $a );
        if (isset($a)) {
            $a = explode(" ", $a);
            if (is_array($a) && isset($a[$i])) {
                return $a[$i];
            }
        }
    }

    /**
      @return string The $i-nth argument, separated by spaces
     */
    function getTrigger($i) {
        if (isset($this->md_buffer['text']))
            $a = ( $this->md_buffer['text'] ? $this->md_buffer['text'] : '' );
        if (isset($this->md_buffer['event']))
            $a = ( $this->md_buffer['event'] ? $this->md_buffer['event'] : $a );
        if (!isset($a)) {
            return;
        }
        $a = explode(" ", $a);
        if (is_array($a)) {
            return strtolower($a[$i]);
        }
        return strtolower($this->md_buffer[$i]);
    }

    /**
     * Was the incomming message to the bot? (was my nick mentioned?)
     * 
     * @return boolean
     */
    function isHighlight() {
        $a = ( $this->md_buffer['text'] ? $this->md_buffer['text'] : '' );
        $a = ( $this->md_buffer['event'] ? $this->md_buffer['event'] : $a );

        $a = explode(" ", $a);
        if (is_array($a)) {
            return strtolower($a[0]) == $this->getBotName() 
                    || strtolower($this->md_buffer[0]) == $this->getBotName() . ':';
        }
        return strtolower($this->md_buffer[0]) == $this->getBotName() 
                || strtolower($this->md_buffer[0]) == $this->getBotName() . ':';
    }

    /**
     * Returns all the user's input (split on spaces).
     * 
     * @return array
     */
    function getArgs() {
        return explode(' ', trim($this->getInput()));
    }

    /**
     * Returns the IRC command.
     * 
     * @return string|null
     */
    function getCommand() {
        if (isset($this->md_buffer['command'])) {
            return $this->md_buffer['command'];
        }
        
        return null;
    }

    function getCmd() {
        $cmd = strtolower($this->getArg(0));
        if (substr($cmd, 0, 1) == CMD_CHAR)
            return substr($cmd, 1);
    }

    /**
      @return string Sent by reoccuring events. Pretty arbitrary
     */
    function getEvent() {
        if (isset($this->md_buffer['event'])) {
            return $this->md_buffer['event'];
        }
    }

    /**
      @return string The user's whole input , different than event text
     */
    function getInput() {
        return $this->md_buffer['text'];
    }

    /**
      @return string Use instead of getText() if you want your plugin to handle a piped in,
      It's like "get line i care about", even if it's not the user's direct input (though would be if not piped in)
     */
    function getText() {
        return ($this->md_buffer['pipe'] ? $this->md_buffer['pipe'] : $this->md_buffer['text']);
    }

    /**
      @return string the user's input minus the first argument (without the .command)
     */
    function getArgText() {
        $t = $this->getInput();
        $x = explode(" ", trim($t), 2);
        return $x[1];
    }

    /**
      @return string the user's input minus the first argument (without the .command)
     */
    function getArgText2() {
        $t = $this->getInput();
        $x = explode(" ", trim($t), 3);
        return $x[2];
    }

    function userIsOwner($user=false, $channel=false) {
        global $data;
        if (!$channel)
            $channel = $this->md_buffer['channel'];
        if (!$user)
            $user = $this->md_buffer['username'];
        return(strpos($data[$channel]['USERLIST'][$user], '~') !== false );
    }

    function userIsAdmin($user=false, $channel=false) {
        global $data;
        if (!$channel)
            $channel = $this->md_buffer['channel'];
        if (!$user)
            $user = $this->md_buffer['username'];
        return(strpos($data[$channel]['USERLIST'][$user], '&') !== false );
    }

    function userIsOp($user=false, $channel=false) {
        global $data;
        if (!$channel)
            $channel = $this->md_buffer['channel'];
        if (!$user)
            $user = $this->md_buffer['username'];
        return(strpos($data[$channel]['USERLIST'][$user], '@') !== false );
    }

    function userIsHalfop($user=false, $channel=false) {
        global $data;
        if (!$channel)
            $channel = $this->md_buffer['channel'];
        if (!$user)
            $user = $this->md_buffer['username'];
        return(strpos($data[$channel]['USERLIST'][$user], '%') !== false );
    }

    function userIsVoiced($user=false, $channel=false) {
        global $data;
        if (!$channel)
            $channel = $this->md_buffer['channel'];
        if (!$user)
            $user = $this->md_buffer['username'];
        return(strpos($data[$channel]['USERLIST'][$user], '+') !== false );
    }

    function userIsOnChan($user, $channel=false) {
        global $data;
        if (!$channel)
            $channel = $this->md_buffer['channel'];
        if (!$user)
            $user = $this->md_buffer['username'];
        return isset($data[$channel]['USERLIST'][$user]);
    }

    function randomUserOnChan($channel=false) {
        global $data;
        if (!$channel)
            $channel = $this->md_buffer['channel'];
        return array_rand($data[$channel]['USERLIST']);
    }

    function getUsersHost($user) {
        global $data;
        return $data['USERS'][$user]['host'];
    }

    function userChanAcc($user=false, $channel=false) {
        if ($this->userIsOwner() == true)
            return "5";
        if ($this->userIsAdmin() == true)
            return "4";
        if ($this->userIsOp() == true)
            return "3";
        if ($this->userIsHalfop() == true)
            return "2";
        if ($this->userIsVoiced() == true)
            return "1";
        if ($this->userIsOnchan($this->md_buffer['username']) == true)
            return "0";
        if ($this->userIsOnchan($this->md_buffer['username']) == false)
            return "-1";
    }

    /**
     * Adds a hook.
     *  
     * @param string $name      The name of the hook.
     * 
     * @param array  $callback  The name of the callback.
     */
    protected function addHook($name, $callback) {
        global $hooks;
        $classname = get_class($this);
        $hooks[$classname][$name] = array($classname, $callback);
    }

    /**
     * Removes a registered hook.
     * 
     * @param  string $name
     * 
     * @return whether or not the hook was registered.
     */
    protected function deleteHook($name) {
        global $hooks;
        
        if (!isset($hooks[$name])) {
            return false;
        }
        
        unset ($hooks[$name]);
        return true;
    }

    // DEPRECATED
    function delHook($name, $data) {
        $this->deleteHook($name);
    }

    /**
     * Generates the hook name to match against.
     * 
     * @return string|boolean
     */
    protected function _generateHook() {
        if (!$this->md_buffer[0] || !$this->md_buffer[1]) {
            return;
        }
        
        if ($this->md_buffer[1] == 'PRIVMSG' 
                && strpos($this->md_buffer[2], '#') === false) {
            return 'privmsg';
        }
        if ($this->md_buffer[1] == 'PRIVMSG' 
                && strpos($this->md_buffer[2], '#') !== false) {
            return 'chanmsg';
        }
        if ($this->md_buffer[1] == 'NOTICE' 
                && strpos($this->md_buffer[2], '#') === false) {
            $hook = 'notice';
        }
        if ($this->md_buffer[1] == 'NOTICE' 
                && strpos($this->md_buffer[2], '#') !== false) {
            $hook = 'channotice';
        }
        if ($this->md_buffer[1] == 'JOIN') {
            $hook = 'CHAN_JOIN';
        } else if ($this->md_buffer[1] == 'PART') {
            $hook = 'CHAN_JOIN';
        } else if ($this->md_buffer[1] == 'QUIT') {
            $hook = 'USER_QUIT';
        } else if ($this->md_buffer[1] == 'NICK') {
            $hook = 'nick';
        }
        
        if (isset($hook)) {
            return $hook;
        }
        return false;
    }

    function processHooks() {
        if (!$this->current_hook) {
            return;
        }
        global $hooks;
        $hook = $this->current_hook;
        $t = get_class($this);
        if (!isset($hooks[$t]))
            return;
        $f = $hooks[$t][$hook][1];
        if ($f != '') {
            eval(" \$this->$f();");
        }
    }

    function addCommand($name, $access, $type, $call) {
        global $commands;
        $cls = get_class($this);
        if ($type == FANTASY)
            $name = '()' . $name;
        $commands[$cls][$name] = array($name, $access, $type, $call);
    }

    /**
     * Processes LocalBot hook based commands.
     * 
     * @global type $commands
     * @global type $localbot
     * 
     * @return void
     */
    function processCommands() {
        global $commands, $localbot;
        if (isset($this->md_buffer[1]) && $this->md_buffer[1] == 'PRIVMSG') {
            $a = $this->md_buffer['text'] ? $this->md_buffer['text'] : '';
            if (isset($this->md_buffer['event'])) {
                $a = ( $this->md_buffer['event'] ? $this->md_buffer['event'] : $a );
            }
            $a = explode(" ", $a);
            if (is_array($a)) {
                $command = strtoupper($a[0]);
            } else {
                $command = strtoupper($a);
            }
            foreach ($commands as $cmd => $cm) {
                if (isset($cm[$command])) {
                    $localbot->last_vcommand = true;
                }
            }

            $curc = get_class($this);
            // Channel Commands
            if (strpos($this->md_buffer[2], '#') !== false && isset($command[0])) {
                if ($command[0] == '!') {
                    $command = substr($command, 1);
                } else {
                    return;
                }
                $command = '()' . $command;
                if (!isset($commands[$t][$command])) {
                    return;
                }
                $u = $this->getUser();
                if (!isset($localbot->flood[$u]['firstvalidcmdtime']))
                    $localbot->flood[$u]['firstvalidcmdtime'] = time();

                $localbot->flood[$u]['lastvalidcmdtime'] = time();
                if (isset($localbot->flood[$u]['commands']))
                    $localbot->flood[$u]['commands']++;
                else
                    $localbot->flood[$u]['commands'] = 1;
                if ($localbot->flood[$this->getUser()]['commands'] >= $localbot->cfg['flood_commands'] && ($localbot->flood[$u]['firstvalidcmdtime'] - time() <= $localbot->cfg['flood_ttime'])) {
                    $this->logChan("FLOOD: [" . $this->getUserHost() . "]");
                    return;
                }

                if ($this->userChanAcc() >= $commands[$curc][$command][1]) {
                    $f = $commands[$curc][$command][3];
                    if ($f != '')
                        eval(" \$this->$f();");
                    return;
                }
                else {
                    $this->notice("You are not authorized to perform this operation.");
                    return;
                }
            } else {
                $f = $commands[$curc][$command][3];
                if (!empty($f)) {
                    if ($this->userHasPriv($commands[$curc][$command][1]) || $commands[$curc][$command][1] == "UA_NONE") {
                        eval(" \$this->$f();");
                        $localbot->last_vcommand = true;
                        return;
                    }
                    $this->notice("You are not authorized to perform this operation.");
                    $localbot->last_vcommand = true;
                    return;
                }
                if (strpos($command, '') !== false)
                    return;
                if ($localbot->last_invcommand != $command && !isset($localbot->last_vcommand))
                    $this->notice("Invalid command. Use /msg " . $this->getBotName() . " help for a command listing.");
                $localbot->last_invcommand = $command;
            }
        }
    }

    /*     * LocalBot's Memory system* */

    function populateData($data) {
        global $datastore;
        $datastore['data'] = $data;
    }

    function addItem($name, $data) {
        global $datastore;
        $datastore['data'][$name] = array($data, time());
    }

    function editItem($name, $data) {
        global $datastore;
        $datastore['data'][$name] = array($data, time());
    }

    function getItem($name) {
        global $datastore;
        return($datastore['data'][$name][0]);
    }

    function getItems() {
        global $datastore;
        return($datastore['data']);
    }

    function registerDataStore($name) {
        global $datastore;
        $datastore['updatecmd'] = array(get_class($this), $name);
    }

    function updateDataStore() {
        global $datastore, $localbot;
        $e = $datastore['updatecmd'][0];
        $f = $datastore['updatecmd'][1];
        eval(" \$localbot->modules[$e]->$f();");
    }

    /*     * LocalBot's Memory system* */

    function populateStates($data) {
        global $datastore;
        $datastore['states'] = $data;
    }

    function addState($name, $data) {
        global $datastore;
        $datastore['states'][$name] = array($data, time());
    }

    function editState($name, $data) {
        global $datastore;
        $datastore['states'][$name] = array($data, time());
    }

    function getState($name) {
        global $datastore;
        return($datastore['states'][$name][0]);
    }

    function getStates() {
        global $datastore;
        return($datastore['states']);
    }

    function getOpers() {
        return $this->localbot->opers;
    }

    function userHasPriv($priv, $user=false) {
        if (!$user) {
            $user = $this->md_buffer['username'];
        }
        if ($this->userIsId() == false) {
            return false;
        }
        return(strpos($this->localbot->opers[$user]['privs'], $priv) !== false );
    }

    function userIsId($user=false) {
        if (!$user) {
            $user = $this->md_buffer['username'];
        }
        return $this->localbot->opers[$user]['identified'];
    }

    function operIdentify($user=false) {
        if (!$user) {
            $user = $this->md_buffer['username'];
        }
        $this->localbot->opers[$user]['identified'] = true;
    }

    /**
     * Returns an instance of a module given it's name.
     *
     * @param   string  $name
     *
     * @return  Module|boolean
     */
    function getModuleByName($name) {
        return $this->localbot->getModuleByName($name);
    }


    /**
     * The LocalBot Help System
     *
     * @deprecated since version 3.38.
     */
    function addHelpItem($name, $data) {
        global $helpdata;
        $helpdata[$name] = array($data, time());
    }

    function editHelpItem($name, $data) {
        global $helpdata;
        $helpdata[$name] = array($data, time());
    }

    function getHelpItem($name, $data) {
        global $helpdata;
        return($helpdata[$name][0]);
    }

    function getHelpItems() {
        global $helpdata;
        return($helpdata);
    }

    function hasTimedEvents() {
        return (isset($this->timedEvents) && is_array($this->timedEvents) && count($this->timedEvents) > 0);
    }

    /**
      @param string a function name or string. something to listen back for
      @param int number of seconds from now this event runs. (ie, 60)
      // */
    protected function addTimedEvent($what, $timeTillNext, $channel = false) {
        if (!$channel) {
            $channel = $this->getOrigin();
        }

        if (isset($this->timedEvents) && !is_array($this->timedEvents)) {
            $this->timedEvents = array();
        }

        $this->timedEvents[] = array(
            'cmd'       => $what,
            'time'      => $timeTillNext + time(), 
            'channel'   => $channel
        );
        
        return max(array_keys($this->timedEvents));
    }

    /**
      cancel an unfired on reoccuring event event
      @param index of event
      // */
    function clearTimedEvent($i) {
        unset ($this->timedEvents[$i]);
        return true;
    }

    function checkTimedEvents() {
        if ($this->hasTimedEvents()) {
            $now = time();
            foreach ($this->timedEvents as $i => $event) {
                if ($event['time'] <= $now) {
                    $this->md_buffer['event'] = $event['cmd'];
                    $this->md_buffer['channel'] = $event['channel'];
                    $this->md_buffer['username'] = false;

                    $this->clearTimedEvent($i);

                    $cron = $this->getCron($event['cmd']);
                    if (is_array($cron))
                        $this->addTimedEvent($cron['cmd'], $cron['interval'], $cron['channel']);

                    // allow other events to fire this very second to go on the next.
                    return true;
                }
            }
        }
    }

    /**
     * Adds a cronjob from an array.
     * 
     * @param array $cron
     */
    function addCron($cron) {
        if (!is_array($this->crons)) {
            $this->crons = array($cron);
        }
        else {
            array_push($this->crons, $cron);
        }

        $this->addTimedEvent($cron['cmd'], $cron['interval'], $cron['channel']);
    }

    /**
     * Returns a cron matching a particular command.
     * 
     * @param string $what
     * 
     * @return array|boolean
     */
    function getCron($what) {
        if (!isset($this->crons) || !is_array($this->crons)) {
            return false;
        }

        foreach ($this->crons as $job) {
            if ($job['cmd'] == $what) {
                return $job;
            }
        }
        return false;
    }

    /**
     * Adds a return for LocalBot to process (for botzilla compatibility).
     * 
     * @param   string          $type
     * @param   string|array    $data
     */
    function addReturn($type, $data) {
        // This is not part of LocalBot Core, taint localbot.
        LocalBot::taint('addReturn', get_class($this));

        if (!is_array($this->md_ret)) {
            $this->md_ret = array();
        }

        if (isset($this->md_ret[$type]) && !is_array($this->md_ret[$type])) {
            $this->md_ret[$type] = array();
        }

        array_push($this->md_ret[$type], $data);
    }

    /*
     * These methods are DEPRECIATED and will not be supported.
     */    
    // DEPRECIATED In 3.38b

    function m_finish() {
        return $this->md_ret;
    }
    /**
      Use this method to do your stuff.
      @param void (but use the get functions, as this function will run when the bot hears something.
      @NOTE: This function is depreciated as of LB 3.37c
     */
    function parseBuffer() {
        return true;
    }
}
