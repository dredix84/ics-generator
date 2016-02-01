/**
 * Class used to generate an .ics file
 */
class icsHelper {

    private $data = array();
    private $name = 'events';  //Name of .ics file to be generated
    private $timezone = '';

    /**
     * Consructor for the ics file generating class
     * @param string $name Output filename
     * @param string $timezone Timezone to use
     */
    function icsHelper($name = '', $timezone = '') {
        $this->name = !empty($name) ? $name : date("YmdHis");
        $this->timezone = empty($timezone) ? date_default_timezone_get() : $timezone;
    }

    /**
     * Used to add an event to the ics file
     * @param string $start Start date
     * @param string $end End date
     * @param string $name Event name
     * @param string $description Event description
     * @param string $uid   Optional: Unique ID for event
     * @param string $location  Optional: Location of event
     * @param boolean $allDateEvent Optional: Is this an all dat event
     */
    function add($start, $end, $name, $description, $uid = '', $location = '', $allDateEvent = false) {
        $inData = array();
        $inData = compact('name', 'start', 'end', 'description', 'allDateEvent');
        $inData['uid'] = $uid == '' ? md5("$start,$end,$name,$description,$location") : $uid;
        if (!empty($location)) {
            $inData['location'] = $location;
        }
        $this->data[] = $inData;
    }

    /**
     * Function returns an arrat list of timezone and offsets. The timezone will be will be the key and offset will be the value
     * @return array
     */
    private function timezone_list() {
        static $regions = array(
            DateTimeZone::AFRICA,
            DateTimeZone::AMERICA,
            DateTimeZone::ANTARCTICA,
            DateTimeZone::ASIA,
            DateTimeZone::ATLANTIC,
            DateTimeZone::AUSTRALIA,
            DateTimeZone::EUROPE,
            DateTimeZone::INDIAN,
            DateTimeZone::PACIFIC,
        );

        $timezones = array();
        foreach ($regions as $region) {
            $timezones = array_merge($timezones, DateTimeZone::listIdentifiers($region));
        }

        $timezone_offsets = array();
        foreach ($timezones as $timezone) {
            $tz = new DateTimeZone($timezone);
            $timezone_offsets[$timezone] = $tz->getOffset(new DateTime);
        }

        asort($timezone_offsets);

        $timezone_list = array();
        foreach ($timezone_offsets as $timezone => $offset) {
            $offset_prefix = $offset < 0 ? '-' : '+';
            $offset_formatted = gmdate('Hi', abs($offset));

            $pretty_offset = "UTC${offset_prefix}${offset_formatted} $timezone";
            $pretty_offset = "${offset_prefix}${offset_formatted}";

            $timezone_list[$timezone] = "${pretty_offset}";
        }

        return $timezone_list;
    }

    /**
     * Returns the offset based on the timezone
     * @param type $timezone_name
     * @return string
     */
    private function getTimezoneOffset($timezone_name) {
        $timezone = new DateTimeZone($timezone_name);
        $offset = $timezone->getOffset(new DateTime("now")); // Offset in seconds
        return ($offset < 0 ? '-' : '+') . str_pad(abs(round($offset / 3600)), 2, '0', STR_PAD_LEFT) . '00'; // prints "+1100"
    }

    /**
     * Returns the string for the .ics file header
     * @return string
     */
    function getHeader() {
        $timezone = $this->timezone;
        $fromOffset =  $this->getTimezoneOffset($timezone);
        return "BEGIN:VCALENDAR
PRODID:-//Mozilla.org/NONSGML Mozilla Calendar V1.1//EN
VERSION:2.0
BEGIN:VTIMEZONE
TZID:$timezone
X-LIC-LOCATION:$timezone
BEGIN:DAYLIGHT
TZOFFSETFROM:$fromOffset
TZOFFSETTO:$fromOffset
TZNAME:MDT
DTSTART:19700308T020000
RRULE:FREQ=YEARLY;BYDAY=2SU;BYMONTH=3
END:DAYLIGHT
BEGIN:STANDARD
TZOFFSETFROM:$fromOffset
TZOFFSETTO:$fromOffset
TZNAME:MST
DTSTART:19701101T020000
RRULE:FREQ=YEARLY;BYDAY=1SU;BYMONTH=11
END:STANDARD
END:VTIMEZONE";
    }

    /**
     * Returns the string for the .ics file footer
     * @return string
     */
    function getFooter() {
        return "\n\nEND:VCALENDAR";
    }

    /**
     * Generates and returns the body section of the .ics
     * @return string
     */
    function getBody() {
        $outStr = '';
        foreach ($this->data as $d) {

            $outStr .= "\n\nBEGIN:VEVENT
CREATED:" . date("Ymd\THis\Z") . "
LAST-MODIFIED:" . date("Ymd\THis\Z") . "
DTSTAMP:" . date("Ymd\THis\Z");
            $outStr .= "\nUID:" . $d['uid'];
            $outStr .= "\nSUMMARY:" . $d['name'];
            //date("Ymd\THis\Z", strtotime($start))
            if ($d['allDateEvent']) {
                $outStr .= "\nDTSTART;VALUE=DATE:" . date("Ymd", strtotime($d['start']));
                $outStr .= "\nDTEND;VALUE=DATE:" . date("Ymd", strtotime($d['end']));
            } else {
                $outStr .= "\nDTSTART;TZID=$this->timezone:" . date("Ymd\THis", strtotime($d['start']));
                $outStr .= "\nDTEND;TZID=$this->timezone:" . date("Ymd\THis", strtotime($d['end']));
            }


            $outStr .= "\nTRANSP:TRANSPARENT";
            if (!empty($d['location'])) {
                $outStr .= "\nLOCATION:" . $d['location'];
            }
            if (!empty(trim($d['description']))) {
                $description = str_replace(array("\r\n", "\n", '<br >', '<br />'), '\n', $d['description']);
                $outStr .= "\nDESCRIPTION:" . strip_tags($description);
            }
            $outStr .= "\nEND:VEVENT";
        }
        return $outStr;
    }

    /**
     * Get the file content string of the .ics file
     * @return string
     */
    public function getData() {
        $outDate = $this->getHeader();
        $outDate .= $this->getBody();
        $outDate .= $this->getFooter();
        return $outDate;
    }

    /**
     * This function will generate the .ics file and instruct the browser to download as a file.
     */
    public function show() {
        $finalData = $this->getData();
        //die(print_object($finalData));
        header("Content-type:text/calendar");
        header('Content-Disposition: attachment; filename="' . $this->name . '.ics"');
        Header('Content-Length: ' . strlen($finalData));
        Header('Connection: close');
        echo $finalData;
    }

}
