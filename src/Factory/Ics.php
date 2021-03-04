<?php

namespace Nails\Calendar\Factory;

use Nails\Calendar\Exception\IcsException;
use Nails\Factory;

/**
 * Class Ics
 *
 * @package Nails\Calendar\Factory
 */
class Ics implements \JsonSerializable
{
    /**
     * The data used to create the ICS
     *
     * @var array
     */
    protected $aIcsData;

    // --------------------------------------------------------------------------

    /**
     * Any errors detected when isValid() is called
     *
     * @var array
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
     *
     * @param array $aProperties Initial properties to set
     */
    public function __construct($aProperties = [])
    {
        //  Set up the default property values
        $this->aIcsData = [];
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
     *
     * @param string $sProperty The name of the property to set
     * @param mixed  $mValue    The value of the property
     *
     * @return $this
     */
    protected function setProperty($sProperty, $mValue): self
    {
        $this->aIcsData[$sProperty] = $mValue;
        return $this;
    }

    // --------------------------------------------------------------------------

    /**
     * Retrieve a property value
     *
     * @param string $sProperty The property to retrieve
     *
     * @return mixed|null
     */
    protected function getProperty(string $sProperty)
    {
        return isset($this->aIcsData[$sProperty])
            ? $this->aIcsData[$sProperty]
            : null;
    }

    // --------------------------------------------------------------------------

    /**
     * Returns the class properties as an array
     *
     * @return array
     */
    public function toArray(): array
    {
        return $this->aIcsData;
    }

    // --------------------------------------------------------------------------

    /**
     * @return array
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    // --------------------------------------------------------------------------

    /**
     * Whether the ICS data is valid
     *
     * @return bool
     */
    public function isValid(): bool
    {
        $this->aErrors = [];

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
     *
     * @return string
     * @throws IcsException
     */
    public function getData(): string
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
        $aData   = [];
        $aData[] = 'BEGIN:VCALENDAR';
        $aData[] = 'VERSION:2.0';
        $aData[] = 'PRODID:-//Nails//Calendar Module//EN';
        $aData[] = 'CALSCALE:GREGORIAN';
        $aData[] = 'BEGIN:VEVENT';
        $aData[] = 'UID:' . $this->getUid();
        $aData[] = 'DTSTART:' . $this->getStart(true);
        $aData[] = 'DTEND:' . $this->getEnd(true);
        $aData[] = 'DTSTAMP:' . $this->getStart(true);

        if (!empty($oOrganiser)) {
            $aData[] = 'ORGANIZER;CN="' . $oOrganiser->name . '":mailto:' . $oOrganiser->email;
        }

        if (!empty($aAttendees)) {
            foreach ($aAttendees as $oAttendee) {
                $aData[] = 'ATTENDEE;PARTSTAT=NEEDS-ACTION;RSVP=TRUE;CN=' . $oAttendee->name . ';';
                $aData[] = 'X-NUM-GUESTS=0:mailto:' . $oAttendee->email;
            }
        }

        $aData[] = 'DESCRIPTION:' . $this->getDescription();
        $aData[] = 'LAST-MODIFIED:' . $this->getStart(true);
        $aData[] = 'LOCATION:' . $this->getLocation();
        $aData[] = 'SUMMARY:' . $this->getSummary();
        $aData[] = 'SEQUENCE:0';
        $aData[] = 'TRANSP:OPAQUE';
        $aData[] = 'END:VEVENT';
        $aData[] = 'END:VCALENDAR';

        return implode(PHP_EOL, $aData);
    }

    // --------------------------------------------------------------------------


    /**
     * Save the .ics file as a file on disk
     *
     * @param string $sPath The full path of the file to save
     *
     * @return bool
     * @throws IcsException
     */
    public function save(string $sPath): bool
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
     *
     * @param string $sFilename The name of the file to download
     *
     * @return $this
     * @throws IcsException
     */
    public function download(string $sFilename = 'invite.ics'): self
    {
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
            ->setOutput($sData);
    }

    // --------------------------------------------------------------------------

    /**
     * Set the value of the "type" property
     *
     * @param string $sValue The value to set
     *
     * @return $this
     */
    public function setType(string $sValue): self
    {
        return $this->setProperty('type', $sValue);
    }

    // --------------------------------------------------------------------------

    /**
     * Retrieve the value of the "type" property
     *
     * @return string|null
     */
    public function getType(): ?string
    {
        return $this->getProperty('type');
    }


    // --------------------------------------------------------------------------

    /**
     * Set the "uid" property
     *
     * @param string $sValue The value to set
     *
     * @return $this
     */
    public function setUid(string $sValue): self
    {
        return $this->setProperty('uid', $sValue);
    }

    // --------------------------------------------------------------------------

    /**
     * Return the value of the "uid" property
     *
     * @return string|null
     */
    public function getUid(): ?string
    {
        return $this->getProperty('uid');
    }

    // --------------------------------------------------------------------------

    /**
     * Set the "start" property
     *
     * @param string $sValue The value to set
     *
     * @return $this
     */
    public function setStart(string $sValue): self
    {
        return $this->setProperty('start', new \DateTime($sValue));
    }

    // --------------------------------------------------------------------------

    /**
     * Return the value of the "start" property
     *
     * @param bool $bAsString Whether to format the date as a string
     *
     * @return \DateTime|string|null
     */
    public function getStart(bool $bAsString = false)
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
     *
     * @param string $sValue The value to set
     *
     * @return $this
     */
    public function setEnd(string $sValue): self
    {
        $oDate = new \DateTime($sValue);
        return $this->setProperty('end', $oDate);
    }

    // --------------------------------------------------------------------------

    /**
     * Return the value of the "end" property
     *
     * @param bool $bAsString Whether to format the date as a string
     *
     * @return \DateTime|string|null
     */
    public function getEnd(bool $bAsString = false)
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
     *
     * @param string $sName  The name of the organiser
     * @param string $sEmail The email of the organiser
     *
     * @return $this
     */
    public function setOrganiser(string $sName, string $sEmail): self
    {
        $oOrganiser = (object) [
            'name'  => trim($sName),
            'email' => trim($sEmail),
        ];

        return $this->setProperty('organiser', $oOrganiser);
    }

    // --------------------------------------------------------------------------

    /**
     * Return the value of the "organiser" property
     *
     * @return \stdClass|null
     */
    public function getOrganiser(): ?\stdClass
    {
        return $this->getProperty('organiser');
    }

    // --------------------------------------------------------------------------

    /**
     * Set the "attendees" property
     *
     * @param array $aValue The value to set
     *
     * @return $this
     */
    public function setAttendees(array $aValue): self
    {
        return $this->setProperty('attendees', $aValue);
    }

    // --------------------------------------------------------------------------

    /**
     * Return the value of the "attendees" property
     *
     * @return array|null
     */
    public function getAttendees(): ?array
    {
        return $this->getProperty('attendees');
    }

    // --------------------------------------------------------------------------

    /**
     * Set the "description" property
     *
     * @param string $sValue The value to set
     *
     * @return $this
     */
    public function setDescription(string $sValue): self
    {
        return $this->setProperty('description', $sValue);
    }

    // --------------------------------------------------------------------------

    /**
     * Return the value of the "description" property
     *
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->getProperty('description');
    }

    // --------------------------------------------------------------------------

    /**
     * Set the "location" property
     *
     * @param string $sValue The value to set
     *
     * @return $this
     */
    public function setLocation($sValue): self
    {
        return $this->setProperty('location', $sValue);
    }

    // --------------------------------------------------------------------------

    /**
     * Return the value of the "location" property
     *
     * @return string|null
     */
    public function getLocation(): ?string
    {
        return $this->getProperty('location');
    }

    // --------------------------------------------------------------------------

    /**
     * Set the "summary" property
     *
     * @param string $sValue The value to set
     *
     * @return $this
     */
    public function setSummary($sValue): self
    {
        return $this->setProperty('summary', $sValue);
    }

    // --------------------------------------------------------------------------

    /**
     * Return the value of the "summary" property
     *
     * @return string|null
     */
    public function getSummary(): ?string
    {
        return $this->getProperty('summary');
    }

    // --------------------------------------------------------------------------

    /**
     * @param string $sEmail The attendee's email
     * @param string $sName  The attendee's name
     *
     * @return $this
     */
    public function addAttendee(string $sEmail, string $sName = ''): self
    {
        $sEmail = trim($sEmail);
        $sName  = trim($sName);

        //  Validate
        //  Valid email - @todo

        $aAttendees = $this->getAttendees() ?: [];

        //  Add the attendee if they aren't already attending
        $bAttending = false;
        foreach ($aAttendees as $oAttendee) {
            if ($oAttendee->email == $sEmail) {
                $bAttending = true;
                break;
            }
        }

        if (!$bAttending) {
            $aAttendees[] = (object) [
                'email' => $sEmail,
                'name'  => $sName,
            ];
        }

        return $this->setAttendees($aAttendees);
    }
}
