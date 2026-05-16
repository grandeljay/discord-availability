<?php

namespace Grandeljay\Availability\Helpers;

use Grandeljay\Availability\Config;
use Sabre\VObject\Reader;

class Nextcloud
{
    private static function getUserCalendars(): string
    {
        $config = new Config();

        $calDavUrl         = 'https://treescloud.online/remote.php/dav/calendars/jay';
        $calDavRequest     = \curl_init($calDavUrl);
        $calDavRequestBody = <<<XML
        <d:propfind xmlns:d="DAV:">
            <d:prop>
                <d:displayname/>
            </d:prop>
        </d:propfind>
        XML;

        $calDavUser     = $config->getNextcloudAppUser();
        $calDavPassword = $config->getAPITokenNextcloud();

        \curl_setopt_array(
            $calDavRequest,
            [
                \CURLOPT_RETURNTRANSFER => true,
                \CURLOPT_USERPWD        => "$calDavUser:$calDavPassword",
                \CURLOPT_HTTPAUTH       => \CURLAUTH_BASIC,
                \CURLOPT_CUSTOMREQUEST  => "PROPFIND",
                \CURLOPT_POSTFIELDS     => $calDavRequestBody,
                \CURLOPT_HTTPHEADER     => [
                    "Content-Type: application/xml; charset=utf-8",
                    "Depth: 1",
                ],
            ]
        );

        $calDavResponse = \curl_exec($calDavRequest);

        \curl_close($calDavRequest);

        return $calDavResponse;
    }

    private static function getParsedUserCalendars(string $calDavResponse): array
    {
        $xml = new \SimpleXMLElement($calDavResponse);
        $xml->registerXPathNamespace('d', 'DAV:');

        $responses = $xml->xpath('//d:response');
        $calendars = [];

        foreach ($responses as $response) {
            $calendarUrl  = $response->xpath('./d:href')[0]         ?? '';
            $calendarName = $response->xpath('.//d:displayname')[0] ?? '';

            if (empty($calendarName)) {
                continue;
            }

            $calendarUrl     = \trim($calendarUrl, '/');
            $calendarIdParts = \explode('/', $calendarUrl);
            $calendarId      = \end($calendarIdParts);
            $Calendar        = [
                'id'   => $calendarId,
                'name' => $calendarName,
                'url'  => $calendarUrl,
            ];

            $calendars[] = $Calendar;
        }

        return $calendars;
    }

    private static function getParsedUserCalendarEvents(string $calDavResponse): array
    {
        $xml = new \SimpleXMLElement($calDavResponse);
        $xml->registerXPathNamespace('d', 'DAV:');
        $xml->registerXPathNamespace('cal', 'urn:ietf:params:xml:ns:caldav');

        $calendarEvents    = [];
        $calendarEventsXml = $xml->xpath('//cal:calendar-data');

        $dateTimeZoneUtc  = new \DateTimeZone('UTC');
        $dateTimeToday    = new \DateTimeImmutable('today 00:00:00', $dateTimeZoneUtc);
        $dateTimeTomorrow = new \DateTimeImmutable('tomorrow 00:00:00', $dateTimeZoneUtc);

        foreach ($calendarEventsXml as $calendarEventXml) {
            $vEventsIcs      = (string) $calendarEventXml;
            $vEventsParsed   = Reader::read($vEventsIcs);
            $vEventsExpanded = $vEventsParsed->expand($dateTimeToday, $dateTimeTomorrow,);
            $vEvents         = $vEventsExpanded->VEVENT;

            foreach ($vEvents as $vEvent) {
                $eventSummary  = $vEvent->SUMMARY->getValue();
                $eventType     = $vEvent->DTSTART->getValueType();
                $eventIsAllDay = 'DATE' === $eventType;
                $eventToAdd    = [
                    'summary'  => $eventSummary,
                    'isAllDay' => $eventIsAllDay,
                ];

                if (!$eventIsAllDay) {
                    $eventToAdd['timeStart'] = $vEvent->DTSTART->getDateTime();
                    $eventToAdd['timeEnd']   = $vEvent->DTEND->getDateTime();
                }

                $calendarEvents[] = $eventToAdd;
            }
        }

        return $calendarEvents;
    }

    public static function getCalendarEventsToday(): array
    {
        $config = new Config();

        $dateTimeFormat   = 'Ymd\THis\Z';
        $dateTimeZoneUtc  = new \DateTimeZone('UTC');
        $dateTimeToday    = new \DateTimeImmutable(
            'today 00:00:00',
            $dateTimeZoneUtc
        );
        $dateTimeTomorrow = new \DateTimeImmutable(
            'tomorrow 00:00:00',
            $dateTimeZoneUtc
        );

        $dateStart = $dateTimeToday->format($dateTimeFormat);
        $dateEnd   = $dateTimeTomorrow->format($dateTimeFormat);

        $calDavResponse  = self::getUserCalendars();
        $calendars       = self::getParsedUserCalendars($calDavResponse);
        $calendarsEvents = [];

        foreach ($calendars as $calendar) {
            $calDavUrl         =  \sprintf(
                'https://treescloud.online/%1$s',
                $calendar['url']
            );
            $calDavRequest     = \curl_init($calDavUrl);
            $calDavRequestBody = <<<XML
            <C:calendar-query xmlns:C="urn:ietf:params:xml:ns:caldav">
                <D:prop xmlns:D="DAV:">
                    <C:calendar-data/>
                </D:prop>

                <C:filter>
                    <C:comp-filter name="VCALENDAR">
                        <C:comp-filter name="VEVENT">
                            <C:time-range start="$dateStart" end="$dateEnd"/>
                        </C:comp-filter>
                    </C:comp-filter>
                </C:filter>
            </C:calendar-query>
            XML;

            $calDavUser     = $config->getNextcloudAppUser();
            $calDavPassword = $config->getAPITokenNextcloud();

            \curl_setopt_array(
                $calDavRequest,
                [
                    \CURLOPT_RETURNTRANSFER => true,
                    \CURLOPT_USERPWD        => "$calDavUser:$calDavPassword",
                    \CURLOPT_HTTPAUTH       => \CURLAUTH_BASIC,
                    \CURLOPT_CUSTOMREQUEST  => "REPORT",
                    \CURLOPT_POSTFIELDS     => $calDavRequestBody,
                    \CURLOPT_HTTPHEADER     => [
                        "Content-Type: application/xml; charset=utf-8",
                        "Depth: 1",
                    ],
                ]
            );

            $calDavResponse = \curl_exec($calDavRequest);

            \curl_close($calDavRequest);

            $calendarEvents = self::getParsedUserCalendarEvents($calDavResponse);

            foreach ($calendarEvents as $calendarEvent) {
                $calendarsEvents[] = $calendarEvent;
            }
        }

        return $calendarsEvents;
    }
}
