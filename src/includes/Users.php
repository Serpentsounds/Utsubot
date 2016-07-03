<?php
/**
 * Utsubot - Users.php
 * User: Benjamin
 * Date: 19/11/14
 */

namespace Utsubot;


use Utsubot\Manager\{
    Manager,
    Manageable,
    ManagerException
};


/**
 * Class Users
 *
 * @package Utsubot
 * @method User findFirst($terms)
 */
class Users extends Manager {

    protected static $manages = "Utsubot\\User";
    private          $IRCBot;

    /** @var $collection User[] */
    protected $collection = [ ];

    protected static $customOperators = [ "on", "voice", "hop", "halfop", "op", "sop", "protect", "owner", "founder", "loggedIn" ];


    /**
     * Users constructor.
     *
     * @param IRCBot $irc
     */
    public function __construct(IRCBot $irc) {
        parent::__construct();
        $this->IRCBot = $irc;
    }


    /**
     * Add a user object to the collection
     *
     * @param Manageable $user User object
     * @param bool       $unique
     * @return int Key of new item
     * @throws ManagerException
     */
    public function addItem(Manageable $user, $unique = true): int {
        $key = parent::addItem($user, $unique);

        /** @var User $user */
        $user->setId($key);

        return $key;
    }


    /**
     * Ensure a User object exists, and get that object. If necessary, creates it and performs initialization.
     * This method should be used in all cases when a new User may need to be created.
     *
     * @param string $fullAddress nick!user@host
     * @return User The new or existing user object
     */
    public function createIfAbsent($fullAddress): User {
        list($nick, $address) = explode("!", $fullAddress);

        try {
            //  User exists, just do an address update
            $user = $this->findFirst($nick);
            /** @var User $user */

            if ($address && $address != $user->getAddress()) {
                $user->setAddress($address);
                $this->IRCBot->sendToModules("user", clone $user);
            }
        }

            //  New user needs to be created
        catch (ManagerException $e) {
            //  Create user
            $user = new User($nick, $address);
            $this->addItem($user);

            //  Creating user for the bot itself, update IRCBot properties with perceived address
            if ($nick == $this->IRCBot->getNickname() && $address)
                $this->IRCBot->setAddress($address);

            //  Send user creation information to modules for processing
            $this->IRCBot->sendToModules("user", clone $user);
        }

        return $user;
    }


    /**
     * Perform custom comparisons on a User, for checking channel presence and modes
     *
     * @param User   $object   A User object in this manager
     * @param string $field    Field name
     * @param string $operator Custom operator
     * @param string $value    Value to compare against
     * @return bool True or false depending on comparison result
     */
    protected function customComparison($object, $field, $operator, $value) {
        $isOn   = $object->isOn($value);
        $status = $object->status($value);

        switch ($operator) {
            case "on":
                return $isOn;
                break;

            case "voice":
            case "vop":
            case "halfop":
            case "hop":
            case "op":
            case "sop":
            case "protect":
            case "owner":
            case "founder":
                if (!$status)
                    return false;

                $modes = [
                    'voice'   => "v", 'vop' => "v",
                    'halfop'  => "h", 'hop' => "h",
                    'op'      => "o",
                    'sop'     => "a",
                    'protect' => "a",
                    'owner'   => "q", 'founder' => "q"
                ];
                if (strpos($status, $modes[ $operator ]) !== false)
                    return true;

                return false;
                break;
        }

        return false;
    }

}