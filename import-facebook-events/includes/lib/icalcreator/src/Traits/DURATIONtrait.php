<?php
/**
 * iCalcreator, the PHP class package managing iCal (rfc2445/rfc5445) calendar information.
 *
 * copyright (c) 2007-2021 Kjell-Inge Gustafsson, kigkonsult, All rights reserved
 * Link      https://kigkonsult.se
 * Package   iCalcreator
 * Version   2.30
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

namespace Kigkonsult\Icalcreator\Traits;

use DateInterval;
use Exception;
use InvalidArgumentException;
use Kigkonsult\Icalcreator\Util\DateIntervalFactory;
use Kigkonsult\Icalcreator\Util\ParameterFactory;
use Kigkonsult\Icalcreator\Util\StringFactory;
use Kigkonsult\Icalcreator\Util\Util;

use function is_array;

/**
 * DURATION property functions
 *
 * @author Kjell-Inge Gustafsson, kigkonsult <ical@kigkonsult.se>
 * @since  2.27.3 - 2018-12-22
 */
trait DURATIONtrait
{
    /**
     * @var array component property DURATION value
     */
    protected $duration = null;

    /**
     * Return formatted output for calendar component property duration
     *
     * @return string
     * @throws Exception
     * @since  2.29.2 - 2019-06-27
     */
    public function createDuration()
    {
        if( empty( $this->duration )) {
            return null;
        }
        if( empty( $this->duration[Util::$LCvalue] )) {
            return $this->getConfig( self::ALLOWEMPTY )
                ? StringFactory::createElement( self::DURATION )
                : null;
        }
        if( DateIntervalFactory::isDateIntervalArrayInvertSet( $this->duration[Util::$LCvalue] )) { // fix pre 7.0.5 bug
            try {
                $dateInterval = DateIntervalFactory::DateIntervalArr2DateInterval(
                    $this->duration[Util::$LCvalue]
                );
            }
            catch( Exception $e ) {
                throw $e;
            }
        }
        else {
            $dateInterval = $this->duration[Util::$LCvalue];
        }
        return StringFactory::createElement(
            self::DURATION,
            ParameterFactory::createParams( $this->duration[Util::$LCparams] ),
            DateIntervalFactory::dateInterval2String( $dateInterval )
        );
    }

    /**
     * Delete calendar component property duration
     *
     * @return bool
     * @since  2.27.1 - 2018-12-15
     */
    public function deleteDuration()
    {
        $this->duration = null;
        return true;
    }

    /**
     * Get calendar component property duration
     *
     * @param bool   $inclParam
     * @param bool   $specform
     * @return bool|array|DateInterval
     * @throws Exception
     * @since  2.29.2 - 2019-06-29
     */
    public function getDuration( $inclParam = false, $specform = false )
    {
        if( empty( $this->duration )) {
            return false;
        }
        if( empty( $this->duration[Util::$LCvalue] )) {
            return ( $inclParam ) ? $this->duration : $this->duration[Util::$LCvalue];
        }
        if( DateIntervalFactory::isDateIntervalArrayInvertSet( $this->duration[Util::$LCvalue] )) { // fix pre 7.0.5 bug
            try {
                $value = DateIntervalFactory::DateIntervalArr2DateInterval(
                    $this->duration[Util::$LCvalue]
                );
            }
            catch( Exception $e ) {
                throw $e;
            }
        }
        else {
            $value = $this->duration[Util::$LCvalue];
        }
        $params = $this->duration[Util::$LCparams];
        if( $specform && ! empty( $this->dtstart )) {
            $dtStart = $this->dtstart;
            $dtValue = clone $dtStart[Util::$LCvalue];
            DateIntervalFactory::modifyDateTimeFromDateInterval( $dtValue, $value );
            $value   = $dtValue;
            if( $inclParam && isset( $dtStart[Util::$LCparams][self::TZID] )) {
                $params = array_merge( $params, $dtStart[Util::$LCparams] );
            }
        }
        return ( $inclParam )
            ? [ Util::$LCvalue  => $value, Util::$LCparams => (array) $params, ]
            : $value;
    }

    /**
     * Set calendar component property duration
     *
     * @param mixed $value
     * @param array $params
     * @return static
     * @throws InvalidArgumentException
     * @throws Exception
     * @since  2.29.2 - 2019-06-20
     * @todo "When the "DURATION" property relates to a
     *        "DTSTART" property that is specified as a DATE value, then the
     *        "DURATION" property MUST be specified as a "dur-day" or "dur-week"
     *        value."
     */
    public function setDuration( $value  = null, $params = [] )
    {
        switch( true ) {
            case ( empty( $value ) && ( empty( $params ) || is_array( $params ))) :
                $this->assertEmptyValue( $value, self::DURATION );
                $this->duration = [
                    Util::$LCvalue  => Util::$SP0,
                    Util::$LCparams => []
                ];
                return $this;
                break;
            case( $value instanceof DateInterval ) :
                $value = DateIntervalFactory::conformDateInterval( $value );
                break;
            case DateIntervalFactory::isStringAndDuration( $value ) :
                $value = StringFactory::trimTrailNL( $value );
                $value = DateIntervalFactory::removePlusMinusPrefix( $value ); // can only be positive
                try {
                    $dateInterval = new DateInterval( $value );
                    $value        = DateIntervalFactory::conformDateInterval( $dateInterval );
                }
                catch( Exception $e ) {
                    throw new InvalidArgumentException( $e->getMessage(), null, $e ); // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                }
                break;
            default :
                throw new InvalidArgumentException(
                    sprintf(
                        self::$FMTERRPROPFMT, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                        self::DURATION, // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
                        var_export( $value, true ) // phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped, WordPress.PHP.DevelopmentFunctions.error_log_var_export
                    )
                );
                break;
        } // end switch
        $this->duration = [
            Util::$LCvalue  => (array) $value,  // fix pre 7.0.5 bug
            Util::$LCparams => ParameterFactory::setParams( $params ),
        ];
        return $this;
    }
}
