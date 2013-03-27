<?php
/**
 * LocalBot Help system.
 * @author Andrew Sorensen <andrew@localcoast.net>
 */
class helpsys extends module {

    /**
     * Registers hooks for the commands.
     */
    function __construct() {
        $this->addCommand("HELP", AC_NONE, FANTASY, 'helpsys_help');
        $this->addCommand("HELP", UA_NONE, PRIVMSG, 'helpsys_help');
    }

    /**
     * Callback function for the help commands.
     */
    function helpsys_help() {
        $helpItems = $this->getHelpItems();
        
        if (!$this->getArg(1)) {
            $this->notice("***** " . $this->getBotName() . " Help *****");
            $this->notice("" . $this->getBotName() . " allows custom control of channels");
            $this->notice("For more information on a command, type:");
            $this->notice("/msg " . $this->getBotName() . " help <command>");
            $this->notice("For a verbose listing of all commands, type:");
            $this->notice("/msg " . $this->getBotName() . " help commands");
            $this->notice("The following commands are available:");
            foreach ($helpItems as $i => $help) {
                $this->notice("" . $i . " " . $helpItems[$i][0]);
            }
            $this->notice("***** End of Help *****");
            return;
        }

        if (strtoupper($this->getArg(1)) == 'COMMANDS') {
            $this->notice("***** " . $this->getBotName() . " *****");
            $this->notice("The following commands are available:");
            foreach ($helpItems as $i => $help) {
                $this->notice("" . $i . " " . $helpItems[$i][0]);
            }
            $this->notice("***** End of Help *****");

            return;
        }
        if (isset($helpItems[strtoupper($this->getArg(1))])) {
            $this->notice("***** " . $this->getBotName() . " *****");
            $this->notice("Help for " . $this->getArg(1) . ":");
            $this->notice("***** End of Help *****");

            return;
        }
        if (!isset($helpItems[strtoupper($this->getArg(1))]))
            $this->notice("No help available for " . $this->getArg(1) . ".");
    }

}
