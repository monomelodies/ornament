<?php

namespace Ornament;

use StdClass;

/**
 * An abstract helper class containing some static methods use here and there
 * (mostly internally).
 */
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
     * @param string $input The normalized input to denormalize.
     * @return string The denormalized string.
     */
    public static function denormalize($input)
    {
        return preg_replace_callback(
            '@_([a-z])@',
            function($match) {
                return strtoupper($match[1]);
            },
            $input
        );
    }

    /**
     * Returns the name of the save function if the input is an
     * Ornament-compatible object, null otherwise.
     *
     * todo: Add aliases for other ORMs so the user can mix and match.
     *
     * @param object $object The object to check.
     * @return string|null A function name or null.
     */
    public static function modelSaveMethod($object)
    {
        if (is_object($object)) {
            if (in_array('Ornament\Model', class_uses($object))
                || method_exists($object, 'save')
            ) {
                return 'save';
            }
        }
        return null;
    }
}

