<?php
/**
 * An interface to allow support for Ziggi modules.
 */

interface ZiggiModule {

    public function parseBuffer();
    
    public function listen($to_buffer = false, $access = true);

    public function setAccessLevel($to = false);
    
    public function setBotName($to);
   
    public function isEmpty();
   
    public function pm($what, $to);
    
    public function send($message);
    
    public function getUser();
    
    public function getUserHost();
    
    public function getHostName();
    
    public function getIdent();
    
    public function getOrigin();
    
    public function getArg($i);
    
    public function getArgs();
    
    public function getCommand();
    
    public function getCmd();
    
    public function getEvent();
    
    public function getInput();
    
    public function getText();
    
    public function getArgText();
    
    public function hasTimedEvents();
    
    public function addTimedEvent($what, $timeTillNext, $channel = false);
    
    public function clearTimedEvent($i);
    
    public function checkTimedEvents();
    
    public function addCron($cron);
    
    public function getCron($what);
    
    public function piped();
    
    public function addReturn($type, $data);
    
    public function z_finish();
}