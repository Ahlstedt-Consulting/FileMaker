<?php
/**
 * FileMaker API for PHP
 *
 * @package FileMaker
 *
 * @copyright Copyright (c) 2016 by 1-more-thing (http://1-more-thing.com) All rights reserved.
 * @license BSD
 */

namespace airmoi\FileMaker\Helpers;

/**
 * Class DateTimeHelper
 * Provide methods to convert date input/output
 * @package airmoi\FileMaker\Helpers
 *
 */
class DateFormat
{
    public static $omitOperatorsPattern = [
        '/^=*/' => null,
        '/[#|@]+/' => "*",
        //'/#/' => "*",
        '/~/' => null
    ];

    public static $byPassOperators = ['!', '?', ];
    /**
     * @param string $value
     * @param string $inputFormat
     * @param string $outputFormat
     * @return string
     * @throws \Exception
     */
    public static function convert($value, $inputFormat = null, $outputFormat = null)
    {
        if (empty($value) || $inputFormat === null || $outputFormat === null) {
            return $value;
        }

        //Parse value to detect incorrect date format
        $parsedDate = date_parse_from_format($inputFormat, $value);
        if ($parsedDate['error_count'] || $parsedDate['warning_count']) {
            throw new \Exception('invalid date format');
        }

        $date = \DateTime::createFromFormat($inputFormat, $value);

        return $date->format($outputFormat);
    }

    /**
     * @param $value
     * @param string|null $inputFormat
     * @param string|null $outputFormat
     * @return string
     */
    public static function convertSearchCriteria($value, $inputFormat = null, $outputFormat = null)
    {
        if (empty($value)
            || in_array($value, self::$byPassOperators)
            || $inputFormat == null
            || $outputFormat == null
        ) {
            return $value;
        }

        $value = self::sanitizeDateSearchString($value);

        $inputRegExp = '#' . self::dateFormatToRegex($inputFormat) . '#';

        //$regex = "#[<|>|≤|≥|<=|>=]?($inputRegExp)\.{0}|\.{3}($inputRegExp)?#";
        $value = preg_replace_callback(
            $inputRegExp,
            function ($matches) use ($inputFormat, $outputFormat) {
                return self::convertWithWildCards($matches[0], $inputFormat, $outputFormat);
            },
            $value
        );

        return $value;
    }

    /**
     * @param string $value
     * @return string
     */
    public static function sanitizeDateSearchString($value)
    {
        foreach (self::$omitOperatorsPattern as $pattern => $replacement) {
            $value = preg_replace($pattern, $replacement, $value);
        }
        return $value;
    }

    /**
     * @param $format
     * @return string
     */
    public static function dateFormatToRegex($format)
    {
        $keys = [
            'Y' => ['year', '\d{4}|\*'],
            'y' => ['year', '\d{2}|\*'],
            'm' => ['month', '\d{2}|\*'],
            'n' => ['month', '\d{1,2}|\*'],
            //'M' => ['month', '[A-Z][a-z]{3}'],
            //'F' => ['month', '[A-Z][a-z]{2,8}'],
            'd' => ['day', '\d{2}|\*'],
            'j' => ['day', '\d{1,2}|\*'],
            //'D' => ['day', '[A-Z][a-z]{2}'],
            //'l' => ['day', '[A-Z][a-z]{6,9}'],
            'u' => ['hour', '\d{1,6}'],
            'h' => ['hour', '\d{2}|\*'],
            'H' => ['hour', '\d{2}|\*'],
            'g' => ['hour', '\d{1,2}|\*'],
            'G' => ['hour', '\d{1,2}|\*'],
            'i' => ['minute', '\d{2}|\*'],
            's' => ['second', '\d{2}|\*']
        ];

        // convert format string to regex
        $regex = '';
        $chars = str_split($format);
        foreach ($chars as $n => $char) {
            $lastChar = isset($chars[$n - 1]) ? $chars[$n - 1] : '';
            $skipCurrent = '\\' == $lastChar;
            if (!$skipCurrent && isset($keys[$char])) {
                $regex .= '(?P<' . $keys[$char][0] . '>' . $keys[$char][1] . ')';
            } elseif ('\\' == $char) {
                $regex .= $char;
            } else {
                $regex .= preg_quote($char);
            }
        }
        return $regex;
    }

    /**
     * @param string $value
     * @param string $inputFormat
     * @param string $outputFormat
     * @return string
     */
    public static function convertWithWildCards($value, $inputFormat, $outputFormat)
    {
        $inputRegex = "#" . self::dateFormatToRegex($inputFormat) . "#";
        preg_match($inputRegex, $value, $parsedDate);

        $keys = [
            'Y' => ['year', '%04d'],
            'y' => ['year', '%02d'],
            'm' => ['month', '%02d'],
            'n' => ['month', '%02d'],
            //'M' => [('month', '%3s'],
            //'F' => array('month', '%8s'],
            'd' => ['day', '%02d'],
            'j' => ['day', '%02d'],
            //'D' => ['day', '%2s'],
            //'l' => ['day', '%9s'],
            //'u' => ['hour', '%06d'],
            'h' => ['hour', '%02d'],
            'H' => ['hour', '%02d'],
            'g' => ['hour', '%02d'],
            'G' => ['hour', '%02d'],
            'i' => ['minute', '%02d'],
            's' => ['second', '%02d']
        ];

        //convert to output format
        $string = '';
        $chars = str_split($outputFormat);
        foreach ($chars as $char) {
            if (isset($keys[$char])) {
                $val = @$parsedDate[$keys[$char][0]];
                $format = $keys[$char][1];
                $string .= $val == "*" || $val == null ? "*" : sprintf($format, $val);
            } else {
                $string .= $char;
            }
        }
        return $string;
    }
}
