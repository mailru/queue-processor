<?php

namespace MailRu\QueueProcessor\Util;

use InvalidArgumentException;

class MapperUtils
{
    const SERIALIZE_TYPE_GZIP = 'gzip';
    const SERIALIZE_TYPE_BASE64_GZIPED_PHP_SERIALIZE = 'base64|gzip|phpSerialize';
    const SERIALIZE_TYPE_GZIPED_PHP_SERIALIZE = 'gzip|phpSerialize';
    const SERIALIZE_TYPE_PHP_SERIALIZE = 'phpSerialize';
    const SERIALIZE_TYPE_GZIPED_JSON = 'gzip|json';
    const SERIALIZE_TYPE_JSON = 'json';
    const SERIALIZE_TYPE_BASE64 = 'base64';

    const GZIP_MIN_LENGTH = 1000;

    public static function serialize($data, $type)
    {
        $types = explode('|', $type, 2);
        switch ($types[0]) {
            case self::SERIALIZE_TYPE_BASE64:
                if (!empty($types[1])) {
                    $data = self::serialize($data, $types[1]);
                }

                return self::SERIALIZE_TYPE_BASE64.' '.base64_encode($data);
            case self::SERIALIZE_TYPE_GZIP:
                if (!empty($types[1])) {
                    $data = self::serialize($data, $types[1]);
                }
                if (strlen($data) < self::GZIP_MIN_LENGTH) {
                    return $data;
                }

                return self::SERIALIZE_TYPE_GZIP.' '.gzcompress($data);
            case self::SERIALIZE_TYPE_PHP_SERIALIZE:
                return self::SERIALIZE_TYPE_PHP_SERIALIZE.' '.serialize($data);
            case self::SERIALIZE_TYPE_JSON:
                return self::SERIALIZE_TYPE_JSON.' '.json_encode($data);
            default:
                throw new InvalidArgumentException();
        }
    }

    /**
     * @param string      $string The serialized data
     * @param string|null $type   If <em>NULL</em> detect type of serialization automatically
     *
     * @return mixed The unserialized data
     *
     * @throws InvalidArgumentException
     */
    public static function unserialize($string, $type = null)
    {
        if ($type === null) {
            list($type, $serializedData) = explode(' ', $string, 2);
        } else {
            $serializedData = $string;
        }

        switch ($type) {
            case self::SERIALIZE_TYPE_BASE64:
                $serializedData = base64_decode($serializedData, true);
                if ($serializedData === false) {
                    return $serializedData;
                }

                return self::unserialize($serializedData);
            case self::SERIALIZE_TYPE_GZIP:
                $serializedData = gzuncompress($serializedData);
                if ($serializedData === false) {
                    return $serializedData;
                }

                return self::unserialize($serializedData);
            case self::SERIALIZE_TYPE_PHP_SERIALIZE:
                return unserialize($serializedData);
            case self::SERIALIZE_TYPE_JSON:
                return json_decode($serializedData, true);
            default:
                throw new InvalidArgumentException();
        }
    }
}
