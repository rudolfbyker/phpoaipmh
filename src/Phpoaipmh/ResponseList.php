<?php

namespace Phpoaipmh;

class ResponseList {

    /**
     * @var Client
     */
    private $httpClient;

    /**
     * @var string
     */
    private $verb;

    /**
     * @var array
     */
    private $params;

    /**
     * @var int
     */
    private $totalEntities;

    /**
     * Recordset expiration date, converted to Unixtime
     *
     * @var int
     * @TODO implement this...
     */
    private $expireDate;

    /** 
     * @var string
     */
    private $resumptionToken;

    /**
     * Array of records
     *
     * @var array
     */
    private $batch;

    /**
     * Total processed
     *
     * @var int
     */
    private $totalProcessed = 0;

    /**
     * Initial Request Made
     *
     * @var boolean
     */
    private $numRequests = 0;

    // -------------------------------------------------------------------------

    /**
     * Constructor
     *
     * @param Client $httpClient
     * @param string $verb
     * @param int $offset
     * @param int $limit
     */
    public function __construct(Client $httpClient, $verb, $params = array())
    {
        //Set paramaters
        $this->httpClient = $httpClient;
        $this->verb   = $verb;
        $this->params = $params;

        //Node name error?
        if ( ! $this->getItemNodeName()) {
            throw new \RuntimeException('Cannot determine item name for verb: ' . $this->verb);
        }        
    }

    // -------------------------------------------------------------------------

    /**
     * Get the total number of requests made during this run
     *
     * @param int
     * The number of HTTP reqeusts made
     */
    public function getNumRequests() {
        return $this->numRequests;
    }

    // -------------------------------------------------------------------------

    /**
     * Get the total number of records processed during this run
     *
     * @return int
     * The number of records processed 
     */
    public function getNumProcessed() {
        return $this->totalProcessed;
    }

    // -------------------------------------------------------------------------

    /**
     * Get the next item
     *
     * Return an item from the current batch, try to get a new item from a request,
     * or return false if both fail.
     *
     * @return boolean|SimpleXMLElement
     */
    public function nextItem()
    {
        //If no items in batch, and we have a resumptionToken or need to make initial request...
        if (count($this->batch) == 0 && ($this->resumptionToken OR $this->numRequests == 0)) {
            $this->retrieveBatch();            
        }

        //if still items in current batch, return one
        if (count($this->batch) > 0) {
            $this->totalProcessed++; 

            $item = array_shift($this->batch);
            $item = new \SimpleXMLElement($item->asXML());
            return $item;
        }
        else {
            return false;
        }
    }

    // -------------------------------------------------------------------------

    /**
     * Do a request to get the next batch of items
     *
     * @return int
     * The number of items in the batch after the retrieve
     */
    private function retrieveBatch() {

        //Params
        $params = ($this->resumptionToken)
            ? array('resumptionToken' => $this->resumptionToken)
            : $this->params;        
        $nodeName = $this->getItemNodeName();
        $verb = $this->verb;

        //Do it..
        $resp = $this->httpClient->request($verb, $params); 
        $this->numRequests++;

        //Result format error?
        if ( ! isset($resp->$verb->$nodeName)) {
            throw new \RuntimeException(sprintf("Expected XML element list '%s' missing for verb '%s'", $nodeName, $verb));
        }

        //Process the results
        foreach($resp->$verb->$nodeName as $node) {
            $this->batch[] = $node;
        }

        //Set the resumption token and expiration date, if any
        if (isset($resp->$verb->resumptionToken)) {
            $this->resumptionToken = (string) $resp->$verb->resumptionToken;

            if (isset($resp->$verb->resumptionToken['completeListSize'])) {
                $this->totalEntities = (int) $resp->$verb->resumptionToken['completeListSize'];
            }
        }

        //Return a count
        return count($this->batch);
    }

    // -------------------------------------------------------------------------

    /**
     * Get Item Node Name
     *
     * Map the item node name based on the verb
     *
     * @return string|boolean
     * The element name for the mapping, or false if unmapped
     */
    private function getItemNodeName() {

        $mappings = array(
            'ListMetadataFormats' => 'metadataFormat',
            'ListSets'            => 'set',
            'ListIdentifiers'     => 'header',
            'ListRecords'         => 'record'
        );

        return (isset($mappings[$this->verb])) ? $mappings[$this->verb] : false;
    }
}

/* EOF: ClientRecordIterator.php */