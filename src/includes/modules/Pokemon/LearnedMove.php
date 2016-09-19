<?php
/**
 * Created by PhpStorm.
 * User: benny
 * Date: 9/10/2016
 * Time: 5:58 AM
 */

namespace Utsubot\Pokemon;

use Utsubot\Manager\Manageable;
use Utsubot\Manager\ManagerException;
use Utsubot\Pokemon\Move\MoveManager;



/**
 * Class LearnedMoveException
 *
 * @package Utsubot\Pokemon
 */
class LearnedMoveException extends \Exception {}


/**
 * Class LearnedMove
 *
 * @package Utsubot\Pokemon
 */
class LearnedMove implements Manageable {

    private $moveID;
    private $move = null;

    private $version;
    private $method;
    private $level = 0;


    /**
     * @return int
     */
    public function getMoveID() {
        return $this->moveID;
    }


    /**
     * @return Version
     */
    public function getVersion() {
        return $this->version;
    }


    /**
     * @return MoveMethod
     */
    public function getMethod() {
        return $this->method;
    }


    /**
     * @return int
     */
    public function getLevel() {
        return $this->level;
    }


    /**
     * LearnedMove constructor.
     *
     * @param int        $moveID
     * @param Version    $version
     * @param MoveMethod $method
     * @param int        $level
     */
    public function __construct(int $moveID, Version $version, MoveMethod $method, int $level = 0) {
        $this->moveID = $moveID;
        $this->version = $version;
        $this->method = $method;
        $this->level = $level;
    }


    /**
     * @param MoveManager    $MoveManager
     * @throws LearnedMoveException
     */
    public function injectMove(MoveManager $MoveManager) {
        try {
            $this->move = $MoveManager->get($this->moveID);
        }
        catch (ManagerException $e) {
            throw new LearnedMoveException("Unable to inject Move with ID {$this->moveID} into LearnedMove.");
        }
    }


    /**
     * @param mixed $terms
     * @return bool
     */
    public function search($terms): bool {
        return $terms === $this->moveID;
    }

}