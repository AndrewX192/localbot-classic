<?php
/**
 * Container class for storing data.
 *
 * @author Andrew <andrew@localcoast.net>
 * $Id: DataStore.php,v 0.1 2009/12/09 09:21:35 Andrew $
 */
class DataStore {

    /**
     * The data to store in the datastore.
     */
    private $datastore;

    /**
     * Populates the datastore with an array of data.
     */
    function populateData($data) {
        $this->datastore['data'] = $data;
    }

    /**
     * Adds an item to the datastore.
     *
     * @throws Exception if the item exists.
     */
    function addItem($name, $data) {
        if (array_key_exists($this->datastore['data'])) {
            throw new Exception("Item already exists", 400);
        }

        $this->datastore['data'][$name] = array($data, time());
    }

    /**
     * Edits an item in the datastore.
     */
    function editItem($name, $data) {
        $this->datastore['data'][$name] = array($data, time());
    }

    /**
     * Retrives an item from the datastore.
     */
    function getItem($name) {
        return $this->datastore['data'][$name][0];
    }
    
    /**
     * Retrives all items in the datastre.
     */
    function getItems() {
        return $this->datastore['data'];
    }
}