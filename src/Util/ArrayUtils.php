<?php

namespace MailRu\QueueProcessor\Util;

use InvalidArgumentException;

/**
 * Utilities for arrays manipulations.
 */
class ArrayUtils
{
    /**
     * Рекурсивно все строковые значения в массиве переконверчиваем в нужную кодировку.
     *
     * @param mixed[]     $array
     * @param string      $charsetTo
     * @param string|null $charsetFrom
     *
     * @return mixed[]
     *
     * @throws \DomainException
     * @throws InvalidArgumentException
     */
    public static function convertEncoding(array $array, $charsetTo, $charsetFrom = null)
    {
        if ($charsetFrom === null) {
            $charsetFrom = mb_internal_encoding();
        }

        if (!is_string($charsetTo)) {
            throw new InvalidArgumentException('Type mismatch for $charsetTo: expected string but `'.gettype($charsetTo).'` got');
        }

        if (!is_string($charsetFrom)) {
            throw new InvalidArgumentException('Type mismatch for $charsetTo: expected string but `'.gettype($charsetFrom).'` got');
        }

        $result = [];

        foreach ($array as $key => $value) {
            if (is_array($value)) {
                $result[$key] = self::convertEncoding($value, $charsetTo, $charsetFrom);
            } elseif (is_string($value)) {
                $result[$key] = mb_convert_encoding($value, $charsetTo, $charsetFrom);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Получить хеш из массива, как вариант для сравнения 2-х массивов.
     *
     * @param array $array
     *
     * @return string
     */
    public static function hash(array $array)
    {
        return md5(serialize($array));
    }

    /**
     * Функция взята из Yii-фреймворка http://www.yiiframework.com/doc/api/1.1/CMap#mergeArray-detail
     * и немного переделана.
     *
     * Merges two or more arrays into one recursively.
     * If each array has an element with the same string key value, the latter
     * will overwrite the former (different from array_merge_recursive).
     * Recursive merging will be conducted if both arrays have an element of array
     * type and are having the same key.
     * For integer-keyed elements, the elements from the latter array will
     * be appended to the former array.
     *
     * @param array $a array to be merged to
     * @param array $b array to be merged from. You can specifiy additional
     *                 arrays via third argument, fourth argument etc.
     *
     * @return array the merged array (the original arrays are not changed.)
     *
     * @see mergeWith
     */
    public static function mergeArray($a, $b)
    {
        return self::doMergeArray(func_get_args(), false);
    }

    private static function doMergeArray(array $arrays, $isIntegerSafe)
    {
        $res = array_shift($arrays);
        while ($arrays) {
            $next = array_shift($arrays);
            foreach ($next as $k => $v) {
                if (is_int($k) && !$isIntegerSafe) {
                    isset($res[$k]) ? $res[] = $v : $res[$k] = $v;
                } elseif (is_array($v) && isset($res[$k]) && is_array($res[$k])) {
                    $res[$k] = self::doMergeArray([$res[$k], $v], $isIntegerSafe);
                } else {
                    $res[$k] = $v;
                }
            }
        }

        return $res;
    }
}
