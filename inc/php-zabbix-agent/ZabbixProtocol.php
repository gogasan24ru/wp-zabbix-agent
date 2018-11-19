<?php

/**
 * Zabbix protocol implementation
 * @see https://www.zabbix.com/documentation/3.4/ru/manual/appendix/items/activepassive
 */
final class ZabbixProtocol
{
    /**
     * Zabbix protocol magic constant
     */
    const ZABBIX_MAGIC = "ZBXD";

    /**
     * Header delimeter character code
     */
    const ZABBIX_DELIMETER = 1;

    /**
     * Construct <HEADER>
     * @return string
     */
    public static function getHeader()
    {
        return self::ZABBIX_MAGIC . pack("C", self::ZABBIX_DELIMETER);
    }

    /**
     * Return actual payload size from provided packet fragment
     * @param $data string packet fragment
     * @return int encoded int value
     */
    public static function getLengthFromPacket($data)
    {
        $bytes=unpack("V*",$data);
        return $bytes[1]+($bytes[2]<<32);
    }

    /**
     * Get length in zabbix protocol format
     * @param mixed $value
     * @return string
     */
    public static function getLength($value)
    {
        $len = strlen($value);

        $lo = (int)$len & 0x00000000FFFFFFFF;

        $hi = ((int)$len & 0xFFFFFFFF00000000) >> 32;

        return pack("V*", $lo, $hi);
    }

    /**
     * Serialize item to zabbix answer format
     * @param InterfaceZabbixItem $item
     * @return string
     */
    public static function serialize(InterfaceZabbixItem $item)
    {
        $value = $item->toValue();

        return self::getHeader() . self::getLength($value) . $value;
    }

    /**
     * @param string $data JSON-encoded data
     * @return string
     */
    public static function buildPacket($data)
    {
        return self::getHeader() . self::getLength($data) . $data;
    }
}
