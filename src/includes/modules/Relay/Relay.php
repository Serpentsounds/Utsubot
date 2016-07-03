<?php

declare(strict_types = 1);

namespace Utsubot\Relay;
use Utsubot\Permission\ModuleWithPermission;
use Utsubot\Help\{
    HelpEntry,
    IHelp,
    THelp
};
use Utsubot\{
    IRCBot,
    IRCMessage,
    Trigger,
    ModuleException,
    User
};


/**
 * Class RelayException
 *
 * @package Utsubot\Relay
 */
class RelayException extends ModuleException {}

/**
 * Class Relay
 *
 * This module allows relaying IRC events from a source (channel, user) to a destination
 * All events can be forward (e.g., messages, nick changes
 *
 * @package Utsubot\Relay
 */
class Relay extends ModuleWithPermission implements IHelp {
    
    use THelp;

    /** @var ActiveRelay[] $relays */
    protected $relays = [ ];

    /**
     * Relay constructor.
     *
     * @param IRCBot $IRCBot
     */
    public function __construct(IRCBot $IRCBot) {
        parent::__construct($IRCBot);

        
        //  Command triggers
        $triggers = [ ];        
        $triggers['relay']      = new Trigger("relay",      [$this, "newRelay"     ]);
        $triggers['unrelay']    = new Trigger("unrelay",    [$this, "removeRelay"  ]);
        $triggers['relays']     = new Trigger("relays",     [$this, "listRelays"   ]);
        
        foreach ($triggers as $trigger)
            $this->addTrigger($trigger);        
        
        
        //  Help entries
        $help = [ ];
        $category = "Relay Module";
        
        $help['relay'] = new HelpEntry($category, $triggers['relay']);
        $help['relay']->addParameterTextPair("NAME SOURCE DESTINATION [OPTIONS]", "Set up a local event relay (with a unique identifier NAME) to forward IRC events from SOURCE to DESTINATION.");
        $help['relay']->addNotes("This command requires level 75.");
        $help['relay']->addNotes("By default, all events will be forwarded, but you can manually select or remove events by with OPTIONS (e.g., +all -ctcp).");
        $help['relay']->addNotes("Valid OPTIONS events: privmsg, notice, ctcp, ctcpreply, join, part, quit, mode, topic, nick, and kick.");
        $help['relay']->addNotes("Special OPTIONS fields: message (includes privmsg, notice, ctcp, and ctcpreply), and all (includes all events, this is the default mode).");
        
        $help['unrelay'] = new HelpEntry($category, $triggers['unrelay']);
        $help['unrelay']->addNotes("This command requires level 75.");
        $help['unrelay']->addParameterTextPair("NAME", "Remove and active relay matching the identifier NAME.");

        $help['relays'] = new HelpEntry($category, $triggers['relays']);
        $help['relays']->addParameterTextPair("", "List details about all currently active relays.");
        
        foreach ($help as $entry)
            $this->addHelp($entry);        
        
    }

    /**
     * Forward PRIVMSG relays in addition to parsing commands
     *
     * @param IRCMessage $msg
     */
    public function privmsg(IRCMessage $msg) {
        //  Parse user commands
        parent::privmsg($msg);

        $this->relay(
            new RelayMode(RelayMode::PRIVMSG),
            $msg->getResponseTarget(),
            sprintf(
                ($msg->isAction()) ?
                    "* %s %s" :
                    "<%s> %s",
                $msg->getNick(),
                $msg->getParameterString()
            )
        );
    }

    /**
     * Forward NOTICE relays
     *
     * @param IRCMessage $msg
     */
    public function notice(IRCMessage $msg) {
        parent::notice($msg);

        $this->relay(
            new RelayMode(RelayMode::NOTICE),
            $msg->getResponseTarget(),
            sprintf(
                "%s-%s- %s",
                //  Optionally include op notice information
                ($msg->isOpNotice()) ?
                    "(". $msg->getOpNoticeTarget(). ") " :
                    "",
                $msg->getNick(),
                $msg->getParameterString()
            )
        );
    }

    /**
     * Forward CTCP relays
     *
     * @param IRCMessage $msg
     */
    public function ctcp(IRCMessage $msg) {
        parent::ctcp($msg);

        $this->relay(
            new RelayMode(RelayMode::CTCP),
            $msg->getResponseTarget(),
            sprintf(
                "[%s %s] %s",
                $msg->getNick(),
                $msg->getCTCP(),
                $msg->getParameterString()
            )
        );
    }

    /**
     * Forward CTCP response relays
     * 
     * @param IRCMessage $msg
     */
    public function ctcpResponse(IRCMessage $msg) {
        parent::ctcpResponse($msg);

        $this->relay(
            new RelayMode(RelayMode::CTCP_REPLY),
            $msg->getResponseTarget(),
            sprintf(
                "[%s %s reply]: %s",
                $msg->getNick(),
                $msg->getCTCP(),
                $msg->getParameterString()
            )
        );
    }

    /**
     * Forward JOIN relays
     *
     * @param IRCMessage $msg
     */
    public function join(IRCMessage $msg) {
        parent::join($msg);

        $this->relay(
            new RelayMode(RelayMode::JOIN),
            $msg->getResponseTarget(),
            sprintf(
                "* %s has joined %s",
                $msg->getNick(),
                $msg->getResponseTarget()
            )
        );
    }

    /**
     * Forward PART relays
     *
     * @param IRCMessage $msg
     */
    public function part(IRCMessage $msg) {
        parent::part($msg);

        $this->relay(
            new RelayMode(RelayMode::PART),
            $msg->getResponseTarget(),
            sprintf(
                "* %s has left %s%s",
                $msg->getNick(),
                $msg->getResponseTarget(),
                //  Optionally add part message
                ($msg->getParameterString()) ?
                    " (". $msg->getParameterString(). ")" :
                    ""
            )
        );
    }

    /**
     * Forward QUIT relays
     *
     * @param IRCMessage $msg
     */
    public function quit(IRCMessage $msg) {
        parent::quit($msg);

        $this->relay(
            new RelayMode(RelayMode::QUIT),
            $msg->getQuitUser(),
            sprintf(
                "* %s has quit IRC%s",
                $msg->getNick(),
                //  Optionally add quit message
                ($msg->getParameterString()) ?
                    " (". $msg->getParameterString(). ")" :
                    ""
            )
        );
    }

    /**
     * Forward MODE relays
     * 
     * @param IRCMessage $msg
     */
    public function mode(IRCMessage $msg) {
        parent::mode($msg);

        $this->relay(
            new RelayMode(RelayMode::MODE),
            $msg->getResponseTarget(),
            sprintf(
                "* %s sets mode %s",
                $msg->getNick(),
                $msg->getParameterString()
            )
        );
    }

    /**
     * Forward TOPIC relays
     *
     * @param IRCMessage $msg
     */
    public function topic(IRCMessage $msg) {
        parent::topic($msg);

        $this->relay(
            new RelayMode(RelayMode::TOPIC),
            $msg->getResponseTarget(),
            sprintf(
                "* %s has changed the topic to '%s'",
                $msg->getNick(),
                $msg->getParameterString()
            )
        );
    }

    /**
     * Forward NICK relays
     *
     * @param IRCMessage $msg
     */
    public function nick(IRCMessage $msg) {
        parent::nick($msg);

        //  Additionally relay sources configured for this nickname
        $this->relay(
            new RelayMode(RelayMode::NICK),
            $msg->getParameterString(), //  Check new nick, because the User object will have already updated
            sprintf(
                "* %s is now known as %s",
                $msg->getNick(),
                $msg->getParameterString()
            )
        );
    }

    /**
     * Forward KICK relays
     *
     * @param IRCMessage $msg
     */
    public function kick(IRCMessage $msg) {
        parent::kick($msg);

        $this->relay(
            new RelayMode(RelayMode::KICK),
            $msg->getResponseTarget(),
            sprintf(
                "* %s was kicked by %s%s",
                $msg->getKickTarget(),
                $msg->getNick(),
                //  Optionally add part message
                ($msg->getParameterString()) ?
                    " (". $msg->getParameterString(). ")" :
                    ""
            )
        );
    }


    /**
     * Internal function to activate relays
     *
     * @param RelayMode $event
     * @param mixed     $source
     * @param string    $text
     */
    protected function relay(RelayMode $event, $source, string $text) {
        foreach ($this->relays as $relay) {

            $doRelay = false;
            //  This relay is configured to forward this type of event
            if ($relay->getMode()->hasFlag($event->getValue())) {
                
                //  If the event is NICK, there won't be a channel target, so check if they're on the relayed channel instead
                if ($event->getValue() == RelayMode::NICK) {
                    if ($this->IRCBot->getUsers()->findFirst((string)$source)->isOn((string)$relay->getTo()))
                        $doRelay = true;
                }

                //  If the event is QUIT, there won't be a channel target, and a cloned User will be passed (as the original has been destroyed)
                elseif  ($event->getValue() == RelayMode::QUIT) {
                    if ($source instanceof User &&
                        ($source->isOn((string)$relay->getFrom()) ||
                         $source->getNick() == $relay->getFrom()))
                        $doRelay = true;
                }
                
                //  Other event, just match the targets
                elseif (strtolower((string)$relay->getFrom()) == strtolower((string)$source))
                    $doRelay = true;
                
            }

            
            if ($doRelay)
                $this->IRCBot->message(
                    (string)$relay->getTo(),
                    sprintf(
                        "[%s] %s",
                        $relay->getFrom(),
                        $text
                    )
                );
        }

    }

    /**
     * Set up a new local relay
     * 
     * @usage !relay <NAME> <FROM> <TO> [OPTIONS]
     * @param IRCMessage $msg
     * @throws RelayException
     */
    public function newRelay(IRCMessage $msg) {
        $this->requireLevel($msg, 75);
        $this->requireParameters($msg, 3);

        $parameters = $msg->getCommandParameters();
        //  Relay identifier
        $name = array_shift($parameters);

        //  Temporary function to parse source and destination
        $verify = function($item) {
            $itemObject = null;
            //  Verify Channel
            if (substr($item, 0, 1) == "#")
                $itemObject = $this->IRCBot->getChannels()->findFirst($item);
            //  Or verify User
            else
                $itemObject = $this->IRCBot->getUsers()->findFirst($item);

            return $itemObject;
        };

        //  Relay source
        $from = $verify(array_shift($parameters));
        //  Relay destination
        $to = $verify(array_shift($parameters));

        $mode = RelayMode::ALL;
        //  Parse options
        if (count($parameters)) {
            $mode = 0;

            //  Apply each word to a composite RelayMode
            foreach ($parameters as $word) {
                //  Must be setting or unsetting a flag
                if (!preg_match("/^([+\-])([a-z]+)$/", $word, $match))
                    throw new RelayException("Malformed option '$word'. Please specify + or - as well as a valid RelayMode.");

                //  Get relevant power of 2, or throw exception if identifier doesn't correspond to a RelayMode
                $value = RelayMode::findValue($match[2]);
                //  Use binary arithmetic to set the flag
                switch ($match[1]) {
                    case "+":
                        $mode |= $value;
                        break;
                    case "-":
                        $mode &= ~$value;
                        break;
                }
            }
        }

        $relayMode = new RelayMode($mode);
        //  If a relay using the same name is already in effect, it will be overwritten
        if (isset($this->relays[$name]))
            $this->respond($msg, sprintf(
                "An existing relay is set up with identifier '%s' (Source: %s, Destination: %s, Mode: %s). This relay will now be deactivated.",
                $name,
                $this->relays[$name]->getFrom(),
                $this->relays[$name]->getTo(),
                $this->relays[$name]->getMode()->getName()
            ));

        $this->relays[$name] = new ActiveRelay($from, $to, $relayMode);
        $this->respond($msg, "Relay has been activated from $from to $to.");
    }

    /**
     * Remove an existing relay
     *
     * @param IRCMessage $msg
     * @throws RelayException
     */
    public function removeRelay(IRCMessage $msg) {
        $this->requireLevel($msg, 75);
        $this->requireParameters($msg, 1);

        $id = $msg->getCommandParameters()[0];
        if (!isset($this->relays[$id]))
            throw new RelayException("There are no relays set up with identifier '$id'.");

        unset($this->relays[$id]);
        $this->respond($msg, "Relay '$id' has been removed.");
    }

    /**
     * List all active relays
     *
     * @param IRCMessage $msg
     * @throws RelayException
     */
    public function listRelays(IRCMessage $msg) {
        if (!$this->relays)
            throw new RelayException("There are no active relays.");

        foreach ($this->relays as $key => $relay)
            $this->respond($msg, "Relay id '{$key}' from {$relay->getFrom()} to {$relay->getTo()} using mode {$relay->getMode()->getName()}.");
    }
}
