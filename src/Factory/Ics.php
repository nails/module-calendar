<?php

namespace Nails\Calendar\Factory;

use Nails\Calendar\Exception\IcsException;
use Nails\Factory;

class Ics implements \JsonSerializable {

    /**
     * @var array The data used to create the ICS
     */
    protected $aIcsData;

    // --------------------------------------------------------------------------

    /**
     * @var array Any errors detected when isValid() is called
     */
    protected $aErrors;

    // --------------------------------------------------------------------------

    /**
     * The format of the date time objects when displaying as a string
     */
    const DATE_FORMAT = 'Ymd\THis\Z';

    // --------------------------------------------------------------------------

    /**
     * Exception values
     */
    const EXCEPTION_HEADERS_SENT = 1;
    const EXCEPTION_INVALID      = 2;

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

        //  Generate a UID if not defined
        if (empty($aProperties['uid'])) {
            $this->setProperty('uid', uniqid(rand(0, getmypid())));
        }
    }

    // --------------------------------------------------------------------------

    /**
     * Sets a property of the .ics file
     * @param  $sProperty string The name of the property to set
     * @param  $mValue    mixed  The value of the property
     * @return $this
     */
    protected function setProperty($sProperty, $mValue)
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
    protected function getProperty($sProperty)
    {
        return isset($this->aIcsData[$sProperty]) ? $this->aIcsData[$sProperty] : null;
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
    public function jsonSerialize()
    {
        return $this->toArray();
    }

    // --------------------------------------------------------------------------

    public function isValid()
    {
        $this->aErrors = array();

        //  Summary is required
        if (empty($this->getSummary())) {
            $this->aErrors[] = 'Summary is required';
        }

        //  Start date/time is required
        $oDateStart = $this->getStart();
        if (empty($oDateStart)) {
            $this->aErrors[] = 'Date start is required';
        }

        //  End date/time is required
        $oDateEnd = $this->getStart();
        if (empty($oDateEnd)) {
            $this->aErrors[] = 'Date end is required';
        }

        //  End date/time must be after start date/time
        if ($oDateEnd < $oDateStart) {
            $this->aErrors[] = 'Date end must be after date start.';
        }

        return count($this->aErrors) === 0;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the contents of the .ics file
     * Useful reading: https://tools.ietf.org/html/rfc2446#page-35
     * @return string The file data
     * @throws IcsException
     */
    public function getData()
    {
        //  Validate
        if (!$this->isValid()) {
            throw new IcsException(
                'ICS is invalid. Errors: ' . implode('; ', $this->aErrors),
                static::EXCEPTION_INVALID
            );
        }

        $oOrganiser = $this->getOrganiser();
        $aAttendees = $this->getAttendees();

        //  Set the method
        $sData  = "BEGIN:VCALENDAR\n";
        $sData .= "VERSION:2.0\n";
        $sData .= "PRODID:-//Nails//Calendar Module//EN";
        $sData .= "CALSCALE:GREGORIAN\n";
        $sData .= "BEGIN:VEVENT\n";
        $sData .= "UID:{$this->getUid()}\n";
        $sData .= "DTSTART:{$this->getStart(true)}\n";
        $sData .= "DTEND:{$this->getEnd(true)}\n";
        $sData .= "DTSTAMP:{$this->getStart(true)}\n";

        if (!empty($oOrganiser)) {
            $sData .= "ORGANIZER;CN=\"{$oOrganiser->name}\":mailto:{$oOrganiser->email}\n";
        }

        if (!empty($aAttendees)) {
            foreach ($aAttendees as $oAttendee)
            {
                $sData .= "ATTENDEE;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;CN={$oAttendee->name};";
                $sData .= "X-NUM-GUESTS=0:mailto:{$oAttendee->email}\n";
            }
        }

        $sData .= "DESCRIPTION:{$this->getDescription()}\n";
        $sData .= "LAST-MODIFIED:{$this->getStart(true)}\n";
        $sData .= "LOCATION:{$this->getLocation()}\n";
        $sData .= "SUMMARY:{$this->getSummary()}\n";
        $sData .= "SEQUENCE:0\n";
        $sData .= "TRANSP:OPAQUE\n";
        $sData .= "END:VEVENT\n";
        $sData .= "END:VCALENDAR";

        return $sData;
    }

    // --------------------------------------------------------------------------


    /**
     * Save the .ics file as a file on disk
     * @param $sPath string The full path of the file to save
     * @return bool
     * @throws IcsException
     */
    public function save($sPath)
    {
        $sData = $this->getData();
        $fh    = fopen($sPath, 'w+');

        if (!$fh) {
            return false;
        }

        if (fwrite($fh, $sData) === false) {
            return false;
        }

        fclose($fh);

        return true;
    }

    // --------------------------------------------------------------------------

    /**
     * Streams the contents of the file to the browser
     * @param string $sFilename The name of the file to download
     * @return $this
     * @throws IcsException
     */
    public function download($sFilename = 'invite.ics') {

        if (headers_sent()) {
            throw new IcsException(
                'Headers already sent.',
                static::EXCEPTION_HEADERS_SENT
            );
        }

        //  Generate the file
        $sData = $this->getData();

        //  Set headers
        /** @var \Nails\Common\Service\Output $oOutput */
        $oOutput = Factory::servicesave('Output');
        $oOutput
            ->setHeader('Pragma: public')
            ->setHeader('Expires: 0')
            ->setHeader('Cache-Control: must-revalidate, post-check=0, pre-check=0')
            ->setHeader('Cache-Control: public')
            ->setHeader('Content-Description: File Transfer')
            ->setHeader('Content-Type: text/calendar;charset=utf-8')
            ->setHeader('Content-Disposition: attachment; filename="' . $sFilename . '"')
            ->setHeader('Content-Transfer-Encoding: binary')
            ->setHeader('Content-Length: ' . strlen($sData))
            ->setOutput($sData)
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
     * @return string|null
     */
    public function getType()
    {
        return $this->getProperty('type');
    }


    // --------------------------------------------------------------------------

    /**
     * Set the "uid" property
     * @param $sValue string The value to set
     * @return Ics
     */
    public function setUid($sValue)
    {
        return $this->setProperty('uid', $sValue);
    }

    // --------------------------------------------------------------------------

    /**
     * Return the value of the "uid" property
     * @return Ics
     */
    public function getUid()
    {
        return $this->getProperty('uid');
    }

    // --------------------------------------------------------------------------

    /**
     * Set the "start" property
     * @param $sValue string The value to set
     * @return Ics
     */
    public function setStart($sValue)
    {
        $oDate = new \DateTime($sValue);
        return $this->setProperty('start', $oDate);
    }

    // --------------------------------------------------------------------------

    /**
     * Return the value of the "start" property
     * @param boolean $bAsString Whether to format the date as a string
     * @return string|null
     */
    public function getStart($bAsString = false)
    {
        $oDate = $this->getProperty('start');
        if (!empty($oDate) && $bAsString) {
            return $oDate->format(static::DATE_FORMAT);
        }

        return $oDate;
    }

    // --------------------------------------------------------------------------

    /**
     * Set the "end" property
     * @param $sValue string The value to set
     * @return Ics
     */
    public function setEnd($sValue)
    {
        $oDate = new \DateTime($sValue);
        return $this->setProperty('end', $oDate);
    }

    // --------------------------------------------------------------------------

    /**
     * Return the value of the "end" property
     * @param boolean $bAsString Whether to format the date as a string
     * @return string|null
     */
    public function getEnd($bAsString = false)
    {
        $oDate = $this->getProperty('end');
        if (!empty($oDate) && $bAsString) {
            return $oDate->format(static::DATE_FORMAT);
        }

        return $oDate;
    }

    // --------------------------------------------------------------------------

    /**
     * Set the "organiser" property
     * @param $sName string The name of the organiser
     * @param $sEmail string The email of the organiser
     * @return Ics
     */
    public function setOrganiser($sName, $sEmail)
    {
        $oOrganiser = (object) array(
            'name'  => trim($sName),
            'email' => trim($sEmail)
        );

        return $this->setProperty('organiser', $oOrganiser);
    }

    // --------------------------------------------------------------------------

    /**
     * Return the value of the "organiser" property
     * @return \stdClass|null
     */
    public function getOrganiser()
    {
        return $this->getProperty('organiser');
    }

    // --------------------------------------------------------------------------

    /**
     * Set the "attendees" property
     * @param $aValue array The value to set
     * @return Ics
     */
    public function setAttendees($aValue)
    {
        return $this->setProperty('attendees', $aValue);
    }

    // --------------------------------------------------------------------------

    /**
     * Return the value of the "attendees" property
     * @return array|null
     */
    public function getAttendees()
    {
        return $this->getProperty('attendees');
    }

    // --------------------------------------------------------------------------

    /**
     * Set the "description" property
     * @param $sValue string The value to set
     * @return Ics
     */
    public function setDescription($sValue)
    {
        return $this->setProperty('description', $sValue);
    }

    // --------------------------------------------------------------------------

    /**
     * Return the value of the "description" property
     * @return string|null
     */
    public function getDescription()
    {
        return $this->getProperty('description');
    }

    // --------------------------------------------------------------------------

    /**
     * Set the "location" property
     * @param $sValue string The value to set
     * @return Ics
     */
    public function setLocation($sValue)
    {
        return $this->setProperty('location', $sValue);
    }

    // --------------------------------------------------------------------------

    /**
     * Return the value of the "location" property
     * @return string|null
     */
    public function getLocation()
    {
        return $this->getProperty('location');
    }

    // --------------------------------------------------------------------------

    /**
     * Set the "summary" property
     * @param $sValue string The value to set
     * @return Ics
     */
    public function setSummary($sValue)
    {
        return $this->setProperty('summary', $sValue);
    }

    // --------------------------------------------------------------------------

    /**
     * Return the value of the "summary" property
     * @return string|null
     */
    public function getSummary()
    {
        return $this->getProperty('summary');
    }

    // --------------------------------------------------------------------------

    public function addAttendee($sEmail, $sName = '')
    {
        $sEmail = trim($sEmail);
        $sName  = trim($sName);

        //  Validate
        //  Valid email - @todo

        $aAttendees = $this->getAttendees() ?: array();

        //  Add the attendee if they aren't already attending
        $bAttending = false;
        foreach ($aAttendees as $oAttendee) {
            if ($oAttendee->email == $sEmail) {
                $bAttending = true;
                break;
            }
        }

        if (!$bAttending) {
            $aAttendees[] = (object) array(
                'email' => $sEmail,
                'name'  => $sName
            );
        }

        return $this->setAttendees($aAttendees);
    }
}
