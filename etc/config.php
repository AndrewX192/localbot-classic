<?php
/*
 * Welcome to the configuration file for LocalBot!
 * Next to each setting their is a comment telling you what each setting does.
 * This configuration file supports C/C++ style commenting.
 */
$config = array(
    /** LocalBot features * */
    'signal_handler' => true,
    'error_handler' => true,
    'verbosity' => '10',
    /** Modules
     * Please specifiy the modules you want localbot to load below.
     * Remember that order is important.
     */
    'modules' => array(
        'helpsys.class.php',
    ),
    /* Default wait in between commands (in nano seconds)
     * Do NOT change this unless you know what you are doing!
     **/
    'kernel_tick' => 12000,
    /** Timezone settings
      You MUST set this correctly or LocalBot may print out thousands of error messages.
      See http://php.net/manual/en/timezones.php for a complete list of valid timezones.
     * */
    'time_zone' => 'America/Los_Angeles',
    /** Network and server settings
     * Network name is used for vanity settings, and is not required.
     * * */
    'network' => 'Example',
    'server' => "irc.example.org", // The IP or Hostname of the irc server
    'port' => "6697", //The port LocalBot should connect to, if you want to use SSL please turn on ssl below.
    /** SSL
      LocalBot supports 2 types of SSL; TLS and SSL.
      if you wish to use SSL, please be sure that you have told the bot to connect to a SSL port,
      Also be sure to enable ssl below and set a ssl_mod (SSL or TLS)
      Note: Your PHP installation _MUST_ support ssl for any of this to work.
     * */
    'ssl' => true,
    'ssl_mod' => 'tls',
    /** Automatic Reconnection
     * Should LocalBot reconnect, and if so; how many times?
     */
    'reconnect' => true,
    'reconnect_limit' => 10,
    /** LocalBot Will attempt to register its nick on its first start* */
    'nickserv_pass' => '',
    /** LocalBots internal settings? * */
    'logchan' => '#localbot', // Where should localbot log?
    /** LocalBot will automaticly pick a new nickname if the selected nickname is in use.
      For example if LocalBot is taken it will use LocalBot-1 * */
    'nick' => "LocalBot", // the IRC nickname LocalBot will use.
    'realname' => "LocalBot", // The GECOS name LocalBot will use (shows up in /whois)
    /**
      Some IRC networks require bots to be set +B, if this is the case for you, Be sure to enter "+B" below.
     * */
    'user_modes' => "+BH", // Modes LocalBot will set on connect
    /**
      Fantasy commands prefix
      Used for channel commands eg: !help
     * */
    'fantasy_prefix' => "!",
    /** IRCOP configuration
      LocalBot can take advantage of a O:Line (or Oper) on IRC.
      Some benefits to giving LocalBot a O:Line are:
      1) Flood control exemption (Prevents the bot from being throttled while sending messages)
      2) Get the real IP of users on IRC (only usefull if your network has cloaking enabled)
      3) Insert-your-other use for LocalBot here :)
      Note: This is not require to use the bot, however features that send a lot of messages back may have issues
      And even cause LocalBot to get disconnected from the IRC server.
     * */
    'oper_username' => '',
    'oper_pass' => '',
    /** The next two lines are what goes in the USER command when you connect,
      You can safely leave these at the default with no harm * */
    'hostname' => 'localbot',
    'servername' => 'localbot',
    /** Be sure to edit the opers.conf.php file to define bot Operators * */
    'oper_file' => 'opers.conf.php',
    /** LocalBot has to store the process id in a file, if you use the start script then be sure this is correct
      If its not basic features like bot restart wont work.
     * */
    'pid_file' => 'var/localbot.pid',
    /** LocalBot offers built in flood control against abusive users.
      If you wish you can disable this feature, but We recommend you keep it enabled.* */
    'flood_reset' => '8', // How many seconds must the user wait when ignored before they can use commands again
    'flood_ttime' => '8', // How many seconds of time can they run commands in?
    'flood_commands' => '5', // How many commands can they run in the time frame defined above?
);
/* * You must comment the next line or LocalBot wont start.	Just put // infront of it, or delete it. * */
      die("Wait! What are you doing? I think you forgot something!\nRemove line " . __LINE__ . " in config.php to start the bot\n");
