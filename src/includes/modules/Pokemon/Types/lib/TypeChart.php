<?php
/**
 * Utsubot - TypeEffectiveness.php
 * Date: 12/05/2016
 */

declare(strict_types = 1);

namespace Utsubot\Pokemon\Types;

use Utsubot\{
    Enum,
    EnumException
};


/**
 * Class TypeChartException
 *
 * @package Utsubot\Pokemon\Types
 */
class TypeChartException extends EnumException {}


/**
 * Class TypeChart
 *
 * @package Utsubot\Pokemon\Types
 */
class TypeChart extends Enum {

    const Bug = [
        TypeEffectiveness::NotVeryEffective =>
            [
                Type::Fairy,
                Type::Fighting,
                Type::Fire,
                Type::Flying,
                Type::Ghost,
                Type::Poison,
                Type::Steel
            ],
        TypeEffectiveness::SuperEffective   =>
            [
                Type::Dark,
                Type::Grass,
                Type::Psychic
            ]
    ];

    const Dark = [
        TypeEffectiveness::NotVeryEffective =>
            [
                Type::Dark,
                Type::Fairy,
                Type::Fighting
            ],
        TypeEffectiveness::SuperEffective   =>
            [
                Type::Ghost,
                Type::Psychic
            ]
    ];

    const Dragon = [
        TypeEffectiveness::Immune           =>
            [
                Type::Fairy
            ],
        TypeEffectiveness::NotVeryEffective =>
            [
                Type::Steel
            ],
        TypeEffectiveness::SuperEffective   =>
            [
                Type::Dragon
            ]
    ];

    const Electric = [
        TypeEffectiveness::Immune           =>
            [
                Type::Ground
            ],
        TypeEffectiveness::NotVeryEffective =>
            [
                Type::Dragon,
                Type::Electric,
                Type::Grass
            ],
        TypeEffectiveness::SuperEffective   =>
            [
                Type::Flying,
                Type::Water
            ]
    ];

    const Fairy = [
        TypeEffectiveness::NotVeryEffective =>
            [
                Type::Fire,
                Type::Poison,
                Type::Steel
            ],
        TypeEffectiveness::SuperEffective   =>
            [
                Type::Dark,
                Type::Dragon,
                Type::Fighting
            ]
    ];

    const Fighting = [
        TypeEffectiveness::Immune           =>
            [
                Type::Ghost
            ],
        TypeEffectiveness::NotVeryEffective =>
            [
                Type::Bug,
                Type::Fairy,
                Type::Flying,
                Type::Poison,
                Type::Psychic
            ],
        TypeEffectiveness::SuperEffective   =>
            [
                Type::Dark,
                Type::Ice,
                Type::Normal,
                Type::Rock,
                Type::Steel
            ]
    ];

    const Fire = [
        TypeEffectiveness::NotVeryEffective =>
            [
                Type::Dragon,
                Type::Fire,
                Type::Rock,
                Type::Water
            ],
        TypeEffectiveness::SuperEffective   =>
            [
                Type::Bug,
                Type::Grass,
                Type::Ice,
                Type::Steel
            ]
    ];

    const Flying = [
        TypeEffectiveness::NotVeryEffective =>
            [
                Type::Electric,
                Type::Rock,
                Type::Steel
            ],
        TypeEffectiveness::SuperEffective   =>
            [
                Type::Bug,
                Type::Fighting,
                Type::Grass
            ]
    ];

    const Ghost = [
        TypeEffectiveness::Immune           =>
            [
                Type::Normal
            ],
        TypeEffectiveness::NotVeryEffective =>
            [
                Type::Dark
            ],
        TypeEffectiveness::SuperEffective   =>
            [
                Type::Ghost,
                Type::Psychic
            ]
    ];

    const Grass = [
        TypeEffectiveness::NotVeryEffective =>
            [
                Type::Bug,
                Type::Dragon,
                Type::Fire,
                Type::Flying,
                Type::Grass,
                Type::Poison,
                Type::Steel
            ],
        TypeEffectiveness::SuperEffective   =>
            [
                Type::Ground,
                Type::Rock,
                Type::Water
            ]
    ];

    const Ground = [
        TypeEffectiveness::Immune           =>
            [
                Type::Flying
            ],
        TypeEffectiveness::NotVeryEffective =>
            [
                Type::Bug,
                Type::Grass
            ],
        TypeEffectiveness::SuperEffective   =>
            [
                Type::Electric,
                Type::Fire,
                Type::Poison,
                Type::Rock,
                Type::Steel
            ]
    ];

    const Ice = [
        TypeEffectiveness::NotVeryEffective =>
            [
                Type::Fire,
                Type::Ice,
                Type::Steel,
                Type::Water
            ],
        TypeEffectiveness::SuperEffective   =>
            [
                Type::Dragon,
                Type::Flying,
                Type::Grass,
                Type::Ground
            ]
    ];

    const Normal = [
        TypeEffectiveness::Immune           =>
            [
                Type::Ghost
            ],
        TypeEffectiveness::NotVeryEffective =>
            [
                Type::Rock,
                Type::Steel
            ]
    ];

    const Poison = [
        TypeEffectiveness::Immune           =>
            [
                Type::Steel
            ],
        TypeEffectiveness::NotVeryEffective =>
            [
                Type::Ghost,
                Type::Ground,
                Type::Poison,
                Type::Rock
            ],
        TypeEffectiveness::SuperEffective   =>
            [
                Type::Fairy,
                Type::Grass
            ]
    ];

    const Psychic = [
        TypeEffectiveness::Immune           =>
            [
                Type::Dark
            ],
        TypeEffectiveness::NotVeryEffective =>
            [
                Type::Psychic,
                Type::Steel
            ],
        TypeEffectiveness::SuperEffective   =>
            [
                Type::Fighting,
                Type::Poison
            ]
    ];

    const Rock = [
        TypeEffectiveness::NotVeryEffective =>
            [
                Type::Fighting,
                Type::Ground,
                Type::Steel
            ],
        TypeEffectiveness::SuperEffective   =>
            [
                Type::Bug,
                Type::Fire,
                Type::Flying,
                Type::Ice
            ]
    ];

    const Steel = [
        TypeEffectiveness::NotVeryEffective =>
            [
                Type::Electric,
                Type::Fire,
                Type::Steel,
                Type::Water
            ],
        TypeEffectiveness::SuperEffective   =>
            [
                Type::Fairy,
                Type::Ice,
                Type::Rock
            ]
    ];

    const Water = [
        TypeEffectiveness::NotVeryEffective =>
            [
                Type::Dragon,
                Type::Grass,
                Type::Water
            ],
        TypeEffectiveness::SuperEffective   =>
            [
                Type::Fire,
                Type::Ground,
                Type::Rock
            ]
    ];

    const FlyingPress = [
        TypeEffectiveness::Immune           =>
            [
                Type::Ghost
            ],
        TypeEffectiveness::NotVeryEffective =>
            [
                Type::Electric,
                Type::Fairy,
                Type::Flying,
                Type::Poison,
                Type::Psychic
            ],
        TypeEffectiveness::SuperEffective   =>
            [
                Type::Dark,
                Type::Fighting,
                Type::Grass,
                Type::Ice,
                Type::Normal
            ]
    ];

    const FreezeDry = [
        TypeEffectiveness::NotVeryEffective =>
            [
                Type::Fire,
                Type::Ice,
                Type::Steel
            ],
        TypeEffectiveness::SuperEffective   =>
            [
                Type::Dragon,
                Type::Flying,
                Type::Grass,
                Type::Ground,
                Type::Water
            ]
    ];


    /**
     * Get the effectiveness of a particular type defending against this chart's type
     *
     * @param Type $type
     * @return TypeEffectivenessMultiplier
     */
    public function getTypeEffectivenessMultiplier(Type $type): TypeEffectivenessMultiplier {
        $effectiveness = 1.0;

        foreach ($this->value as $multiplier => $items) {
            if (in_array($type->getValue(), $items, true)) {
                $effectiveness = (float)$multiplier;
                break;
            }
        }

        $effectiveness = new TypeEffectiveness($effectiveness);
        return TypeEffectivenessMultiplier::fromTypeEffectiveness($effectiveness);
    }

}
