<?php
/**
 * This file is part of the Lifo\IP PHP Library.
 *
 * (c) Jason Morriss <lifo2013@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Lifo\IP;

/**
 * IP Address helper class.
 *
 * Provides routines to translate IPv4 and IPv6 addresses between human readable
 * strings, decimal, hexidecimal and binary.
 *
 * Requires BCmath extension and IPv6 PHP support
 */
abstract class IP
{
    const MAX_UINT_32  = '4294967295';
    const MAX_INT_32   = '2147483647';
    const MAX_UINT_128 = '340282366920938463463374607431768211455';
    const MAX_INT_128  = '170141183460469231731687303715884105727';

    /**
     * Convert a human readable (presentational) IP address string into a decimal string.
     */
    public static function inet_ptod($ip)
    {
        // shortcut for IPv4 addresses
        if (strpos($ip, ':') === false && strpos($ip, '.') !== false) {
            return sprintf('%u', ip2long($ip));
        }

        // remove any cidr block notation
        if (($o = strpos($ip, '/')) !== false) {
            $ip = substr($ip, 0, $o);
        }

        // unpack into 4 32bit integers
        $parts = unpack('N*', inet_pton($ip));
        foreach ($parts as &$part) {
            if ($part < 0) {
                // since PHP stores ints as unsigned we need to convert negative
                // numbers into their positive value in order for the BC math
                // below to perform calculations.
                $part = sprintf('%u', $part);
                //$part = bcadd($part, '4294967296');
            }
        }

        // add each 32bit integer to the proper bit location in our big decimal
        $decimal = $parts[4];                                                           // << 0
        $decimal = bcadd($decimal, bcmul($parts[3], '4294967296'));                     // << 32
        $decimal = bcadd($decimal, bcmul($parts[2], '18446744073709551616'));           // << 64
        $decimal = bcadd($decimal, bcmul($parts[1], '79228162514264337593543950336'));  // << 96

        return $decimal;
    }

    /**
     * Convert a decimal string into a human readable IP address.
     */
    public static function inet_dtop($decimal, $expand = false)
    {
        $parts = array();
        $parts[1] = bcdiv($decimal,                  '79228162514264337593543950336', 0);   // >> 96
        $decimal  = bcsub($decimal, bcmul($parts[1], '79228162514264337593543950336'));
        $parts[2] = bcdiv($decimal,                  '18446744073709551616', 0);            // >> 64
        $decimal  = bcsub($decimal, bcmul($parts[2], '18446744073709551616'));
        $parts[3] = bcdiv($decimal,                  '4294967296', 0);                      // >> 32
        $decimal  = bcsub($decimal, bcmul($parts[3], '4294967296'));
        $parts[4] = $decimal;                                                               // >> 0

        foreach ($parts as &$part) {
            if (bccomp($part, '2147483647') == 1) {
                $part = bcsub($part, '4294967296');
            }
            $part = (int) $part;
        }

        $packed = pack('N4', $parts[1], $parts[2], $parts[3], $parts[4]);
        $ip = inet_ntop($packed);

        // Turn IPv6 to IPv4 if it's IPv4
        if (preg_match('/^::\d+\./', $ip)) {
            return substr($ip, 2);
        }

        return $expand ? self::inet_expand($ip) : $ip;
    }

    /**
     * Convert a human readable (presentational) IP address into a HEX string.
     */
    public static function inet_ptoh($ip)
    {
        return bin2hex(inet_pton($ip));
        //return BC::bcdechex(self::inet_ptod($ip));
    }

    /**
     * Convert a human readable (presentational) IP address into a BINARY string.
     */
    public static function inet_ptob($ip)
    {
        return BC::bcdecbin(self::inet_ptod($ip), 128);
    }

    /**
     * Convert a HEX string into a human readable (presentational) IP address
     */
    public static function inet_htop($hex)
    {
        return self::inet_dtop(BC::bchexdec($hex));
    }

    /**
     * Expand an IP address. IPv4 addresses are returned as-is.
     *
     * Example:
     *      2001::1     expands to 2001:0000:0000:0000:0000:0000:0000:0001
     *      ::127.0.0.1 expands to 0000:0000:0000:0000:0000:0000:7f00:0001
     *      127.0.0.1   expands to 127.0.0.1
     */
    public static function inet_expand($ip)
    {
        $bytes = unpack('n*', inet_pton($ip));
        if (count($bytes) > 2) {
            return implode(':', array_map(function ($b) {
                return sprintf("%04x", $b);
            }, $bytes));
        }
        return $ip;
    }
}