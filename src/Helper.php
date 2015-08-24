<?php

namespace Ornament;

abstract class Helper
{
    /**
     * Normalize the inputted string for use in Ornament models.
     *
     * A normalized string is a classname mapped to a table name, or a virtual
     * property named mapped to an "actual" property name. E.g.
     * `My\Awesome\Table` becomes `my_awesome_table`, and `someVirtualField`
     * becomes `some_virtual_field`.
     *
     * @param string $input The string to normalized.
     * @return string The normalized string.
     */
    public static function normalize($input)
    {
        $input = strtolower(preg_replace_callback(
            '@([a-z0-9])(_|\\\\)?([A-Z])@',
            function ($match) {
                return $match[1].'_'.strtolower($match[3]);
            },
            $input
        ));
        return trim(strtolower($input), '_');
    }

    /**
     * The inverse of `Helper::normalize`. Note that this assumes the underscore
     * implies camelCase; we have no way of knowing if namespaces were intended
     * instead, or if the original class used underscores plus Capitals.
     *
     * This is mostly used to denormalize virtual properties where this isn't
     * an issue anyway.
     *
     * @param string $input The normalize input to denormalize.
     * @return string The denormalized string.
     */
    public static function denormalize($input)
    {
        return preg_replace_callback(
            '@_([a-z])@',
            function($match) {
                return '_'.strtoupper($match[1]);
            },
            $input
        );
    }

    /**
     * Exports the object as an array of public key/value pairs. Basically a
     * simple wrapper for `get_object_vars` but usefull when calling from a
     * `$this` context.
     *
     * @param object The object to export.
     * @return array An array of public properties with their values.
     */
    public static function export($object)
    {
        return get_object_vars($object);
    }
}

