<?php

require_once 'ZiggiInterface.php';

class ziggi extends module {
    
    /**
     * {@inheritDoc}
     */
    public function __construct(\LocalBot $localbot) {
        parent::__construct($localbot);
    }
}
