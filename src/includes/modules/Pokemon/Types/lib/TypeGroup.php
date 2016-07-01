<?php
/**
 * Utsubot - TypeGrou.php
 * Date: 27/06/2016
 */

declare(strict_types = 1);

namespace Utsubot\Pokemon\Types;

use Utsubot\TypedArray;


/**
 * Class TypeGroup
 *
 * @package Utsubot\Pokemon\Types
 */
class TypeGroup extends TypedArray {

    protected static $contains = "Utsubot\\Pokemon\\Types\\Type";

    /**
     * @param array $typeNames
     * @return TypeGroup
     * @throws \Utsubot\EnumException If any type name is invalid
     */
    public static function fromStrings(array $typeNames): TypeGroup {
        $types = [ ];
        foreach ($typeNames as $name)
            $types[ ] = Type::fromName($name);

        return new static($types);
    }

}