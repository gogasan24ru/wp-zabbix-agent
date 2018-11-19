<?php
interface InterfaceZabbixItem
{
    public function toValue();
}
interface InterfaceZabbixItemCreatable
{
    public static function create($value);
}
interface InterfaceZabbixItemTime
{
    public static function now();

    public function getTime();

    public function setTime($time);
}