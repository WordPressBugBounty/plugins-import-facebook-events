<?php
/**
  * iCalcreator, the PHP class package managing iCal (rfc2445/rfc5445) calendar information.
 *
 * copyright (c) 2007-2021 Kjell-Inge Gustafsson, kigkonsult, All rights reserved
 * Link      https://kigkonsult.se
 * Package   iCalcreator
 * Version   2.30.10
 * License   Subject matter of licence is the software iCalcreator.
 *           The above copyright, link, package and version notices,
 *           this licence notice and the invariant [rfc5545] PRODID result use
 *           as implemented and invoked in iCalcreator shall be included in
 *           all copies or substantial portions of the iCalcreator.
 *
 *           iCalcreator is free software: you can redistribute it and/or modify
 *           it under the terms of the GNU Lesser General Public License as published
 *           by the Free Software Foundation, either version 3 of the License,
 *           or (at your option) any later version.
 *
 *           iCalcreator is distributed in the hope that it will be useful,
 *           but WITHOUT ANY WARRANTY; without even the implied warranty of
 *           MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 *           GNU Lesser General Public License for more details.
 *
 *           You should have received a copy of the GNU Lesser General Public License
 *           along with iCalcreator. If not, see <https://www.gnu.org/licenses/>.
 *
 * This file is a part of iCalcreator.
*/

namespace Kigkonsult\Icalcreator\Util;

use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Exception;
use Kigkonsult\Icalcreator\Vcalendar;
use RuntimeException;

use function explode;
use function get_object_vars;
use function is_array;
use function is_object;
use function sprintf;
use function substr;

/**
 * iCalcreator DateTime support class
 *
 * @author Kjell-Inge Gustafsson, kigkonsult <ical@kigkonsult.se>
 * @since  2.30.10 - 2021-12-01
 */
class UtilDateTime extends DateTime
{
    /**
     * @var string default object instance date[-time] 'key'
     */
    public $key = null;

    /**
     * @var array date[-time] origin
     */
    public $SCbools = [];

    /**
     * @var string default date[-time] format
     */
    public $dateFormat = null;

    /**
     * Constructor for UtilDateTime
     *
     * @param string       $time
     * @param DateTimeZone $timezone
     * @throws Exception
     * @since  2.27.8 - 2019-01-12
     */
    public function __construct( $time = "now" , DateTimeZone $timezone = null )
    {
        parent::__construct( $time, $timezone );
        $this->dateFormat = DateTimeFactory::$YMDHISe;
    }

    /**
     * @link https://php.net/manual/en/language.oop5.cloning.php#116329
     */
    public function __clone()
    {
        $object_vars = get_object_vars( $this );

        foreach( $object_vars as $attr_name => $attr_value ) {
            if( is_object($this->$attr_name )) {
                $this->$attr_name = clone $this->$attr_name;
            }
            else if( is_array( $this->$attr_name )) {
                // Note: This copies only one dimension arrays
                foreach( $this->$attr_name as &$attr_array_value ) {
                    if( is_object( $attr_array_value )) {
                        $attr_array_value = clone $attr_array_value;
                    }
                    unset( $attr_array_value);
                }
            }
        }
    }

    /**
     * Return clone
     *
     * @return static
     * @since  2.26.2 - 2018-11-14
     */
    public function getClone()
    {
        return clone $this;
    }

    /**
     * Return time (His) array
     *
     * @return array
     * @since  2.23.20 - 2017-02-07
     */
    public function getTime()
    {
        static $H_I_S = 'H:i:s';
        $res = [];
        foreach( explode( Util::$COLON, $this->format( $H_I_S )) as $t ) {
            $res[] = (int) $t;
        }
        return $res;
    }

    /**
     * set date and time from YmdHis string
     *
     * @param string $YmdHisString
     * @since  2.26.2 - 2018-11-14
     */
    public function setDateTimeFromString( $YmdHisString )
    {
        $this->setDate(
            (int) substr( $YmdHisString, 0, 4 ),
            (int) substr( $YmdHisString, 4, 2 ),
            (int) substr( $YmdHisString, 6, 2 )
        );
        $this->setTime(
            (int) substr( $YmdHisString, 8, 2 ),
            (int) substr( $YmdHisString, 10, 2 ),
            (int) substr( $YmdHisString, 12, 2 )
        );
    }

    /**
     * Return the timezone name
     *
     * @return string
     * @since  2.21.7 - 2015-03-07
     */
    public function getTimezoneName()
    {
        $tz = $this->getTimezone();
        return $tz->getName();
    }

    /**
     * Return formatted date
     *
     * @param string $format
     * @return string
     * @since  2.21.7 - 2015-03-07
     */
    public function format( $format = null )
    {
        if( empty( $format ) && isset( $this->dateFormat )) {
            $format = $this->dateFormat;
        }
        return parent::format( $format );
    }

    /**
     * Return UtilDateTime object instance based on date array and timezone(s)
     *
     * @param DateTimeInterface $date
     * @param array    $params
     * @param string   $dtstartTz
     * @return static
     * @throws Exception
     * @throws RuntimeException
     * @static
     * @since  2.30.10 - 2021-12-01
     */
    public static function factory( DateTimeInterface $date, $params = null, $dtstartTz = null )
    {
        static $Y_M_D  = 'Y-m-d';
        static $MSG1   = '#%d Can\'t create DateTimeZone from \'%s\'';
        static $MSG2   = '#%d Can\'t create (to-)DateTime : \'%s\'';
        static $MSG4   = '#%s Can\'t set DateTimeZone \'%s\'';

        $YmdHise = $date->format( DateTimeFactory::$YMDHISe );
        try {
            $iCaldateTime = new UtilDateTime( $YmdHise );
        }
        catch( Exception $e ) {
            throw new RuntimeException( sprintf( $MSG2, 3, $YmdHise ), null, $e ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
        }
        if( Vcalendar::Z == $dtstartTz ) {
            $dtstartTz = Vcalendar::UTC;
        }
        if( ! empty( $dtstartTz ) &&
            ( $dtstartTz != $iCaldateTime->getTimezone()->getName())) {
            // set the same timezone as dtstart
            if( $dtstartTz != $iCaldateTime->getTimezoneName()) {
                try {
                    $timeZone = DateTimeZoneFactory::factory( $dtstartTz );
                }
                catch( Exception $e ) {
                    throw new RuntimeException( // -- #2
                        sprintf( $MSG1, 5, $dtstartTz ), // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                        null,
                        $e // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                    );
                }
                if( false === $iCaldateTime->setTimezone( $timeZone )) {
                    throw new RuntimeException(  // -- #3
                        sprintf( $MSG4, 6, $dtstartTz ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                    );
                }
            }
        } // end if
        if( ParameterFactory::isParamsValueSet(
            [ Util::$LCparams => $params ],
            Vcalendar::DATE )
        ) {
            $iCaldateTime->dateFormat = $Y_M_D;
            $iCaldateTime->key        = $iCaldateTime->format( DateTimeFactory::$Ymd );
        }
        else {
            $iCaldateTime->key = $iCaldateTime->format( DateTimeFactory::$YmdHis );
        }
        return $iCaldateTime;
    }
}
