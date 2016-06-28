<?php

namespace Nails\Calendar\Factory;

class Ics implements \JsonSerializable {
    
    /**
     * @var array The data used to create the ICS
     */
    protected $aIcsData;

    // --------------------------------------------------------------------------

    /**
     * Ics constructor.
     * @param array $aProperties Initial properties to set
     */
    public function __construct($aProperties = array())
    {
        //  Set up the default property values
        $this->aIcsData = array();
        foreach ($aProperties as $sProperty => $mValue) {
            $this->setProperty($sProperty, $mValue);
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Sets a property of the .ics file
     * @param  $sProperty string The name of the property to set
     * @param  $mValue    mixed  The value of the property
     * @return $this
     */
    public function setProperty($sProperty, $mValue)
    {
        $this->aIcsData[$sProperty] = $mValue;
        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Retrieve a property value
     * @param  $sProperty string The property to retrieve
     * @return mixed|null
     */
    public function getProperty($sProperty)
    {
        return isset($this->aIcsData[$sProperty]) ? $this->aIcsData[$sProperty] : null;
    }

    // --------------------------------------------------------------------------

    /**
     * Set the value of the "type" property
     * @param $sValue string The value to set
     * @return Ics
     */
    public function setType($sValue)
    {
        return $this->setProperty('type', $sValue);
    }

    // --------------------------------------------------------------------------

    /**
     * Retrieve the value of the "type" property
     * @return mixed|null
     */
    public function getType()
    {
        return $this->getProperty('type');
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the class properties as an array
     * @return array
     */
    public function toArray()
    {
        return $this->aIcsData;
    }
    
    // --------------------------------------------------------------------------

    /**
     * @return array
     */
    public function jsonSerialize() {
        return $this->toArray();
    }
    
    // --------------------------------------------------------------------------

    /**
     * Returns the contents of the .ics file
     * @return string The file data
     */
    public function getData()
    {
        return '';
    }
    
    // --------------------------------------------------------------------------
    
    /**
     * Streams the contents of the file to the browser
     * @param string $sFilename The name of the file to download
     * @return $this
     */
    public function download($sFilename = '') {
        
        //  Set headers
        //  @todo

        //  Send file
        //  @todo
        echo $this->getData();
        
        return $this;
    }
}
