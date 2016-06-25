<?php
/**
 * Utsubot - Help.php
 * User: Benjamin
 * Date: 17/12/2014
 */

namespace Utsubot\Help;
use Utsubot\{
    IRCBot,
    IRCMessage,
    Trigger,
    ModuleException,
    Timers,
    Timer
};
use Utsubot\Permission\ModuleWithPermission;
use function Utsubot\bold;


/**
 * Class HelpModuleException
 *
 * @package Utsubot\Help
 */
class HelpModuleException extends ModuleException {}

/**
 * Class HelpModule
 *
 * @package Utsubot\Help
 */
class HelpModule extends ModuleWithPermission implements IHelp {

    use THelp;
    use Timers;

    const HelpDirectory = "../help";
    const Separator     = " \x02\x0304¦\x03\x02 ";

    /** @var HelpEntries[] $helpEntries  */
    private $helpEntries = [ ];

    /**
     * HelpModule constructor.
     *
     * @param IRCBot $irc
     */
    public function __construct(IRCBot $irc) {
        parent::__construct($irc);
        
        //  Command triggers
        $triggers = [ ];
        $triggers['help']       = new Trigger("help",       [$this, "help"]);
        $triggers['showhelp']   = new Trigger("showhelp",   [$this, "help"]);

        foreach ($triggers as $trigger)
            $this->addTrigger($trigger);
        
        
        //  Help entries
        $help = new HelpEntry("Help Text", $triggers['help']);
        $help->addParameterTextPair("[TOPIC]", "Private messages help text for TOPIC. If TOPIC is omitted, shows the list of commands for which help text is available.");
        $help->addNotes("HelpModule Syntax: Parameters that should be filled in by the user are CAPITALIZED, and parameters that are optional are [bracketed].");
        $this->addHelp($help);

        $showHelp = new HelpEntry("Help Text", $triggers['showhelp']);
        $showHelp->addParameterTextPair("[TOPIC]", "Shows help text in channel for TOPIC. If TOPIC is omitted, shows the list of commands for which help text is available.");
        $showHelp->addNotes("HelpModule Syntax: Parameters that should be filled in by the user are CAPITALIZED, and parameters that are optional are [bracketed].");
        $this->addHelp($showHelp);

        
        //  Initialization
        $this->addTimer(new Timer(
            0,
            array($this, "loadHelp"),
            [ ]
        ));
    }

    /**
     * Cache information from modules which implement help
     */
    public function loadHelp() {
        $this->helpEntries = [ ];
        $modules = $this->IRCBot->getModules();

        foreach ($modules as $module) {
            //  Only load from modules that implement IHelp interface
            /** @var IHelp $module */
            if (is_subclass_of($module, "Utsubot\\Help\\IHelp")) {
                $entries = $module->getHelpEntries();
                
                /** @var HelpEntry $entry */
                foreach ($entries as $entry) {
                    $category = $entry->getCategory();
                    
                    //  Initialize category if necessary
                    if (!isset($this->helpEntries[$category]) || !($this->helpEntries[$category] instanceof HelpEntries))
                        $this->helpEntries[$category] = new HelpEntries();
                    
                    $this->helpEntries[$category]->append($entry);
                }
            }
        }

    }

    /**
     * Search for help on the given topic, or list all available commands if no topic given
     *
     * @param IRCMessage $msg
     * @throws HelpModuleException If no help is available
     */
    public function help(IRCMessage $msg) {
        //  !showhelp to publicly message help, otherwise it is privately shown
        if (strtolower($msg->getCommand()) == "showhelp")
            $responseTarget = $msg->getResponseTarget();
        else
            $responseTarget = $msg->getNick();

        //  $parameters is the help topic to search for
        $topic = strtolower($msg->getCommandParameterString());
        $return = null;        
        
        //  If a topic was specified
        if (strlen($topic)) {
            $help = $this->findHelp($topic);            

            //  Show help for a single command trigger
            if ($help instanceof HelpEntry)
                $return = $this->formatHelpEntry($help);

            //  Show help for an entire command category
            elseif ($help instanceof HelpEntries)
                $return = sprintf(
                    "Available commands for %s: %s",
                    bold(ucwords($topic)),
                    implode(" ", $this->addPrefixes($help->getArrayCopy()))
                );
            
        }

        //  No command given, list all available help triggers
        else {
            foreach ($this->helpEntries as $category => $entries)
                $return[] = sprintf(
                    "%s: %s",
                    bold($category),
                    implode(" ", $this->addPrefixes($entries->getArrayCopy()))
                );

            $return = bold("Available commands: "). implode(self::Separator, $return);
        }

        if ($return)
            $this->IRCBot->message($responseTarget, $return);
    }

    /**
     * Get the array of help information for a topic (command or category) from the internal cache
     *
     * @param string $topic
     * @return HelpEntry|HelpEntries
     * @throws HelpModuleException If no help is available
     */
    private function findHelp(string $topic) {
        
        $topic = strtolower($topic);
        $help = null;        
        foreach ($this->helpEntries as $category => $entries) {

            //  Get help for all of the commands in a single module
            if ($topic === strtolower($category)) {
                $help = $entries;
                break;
            }

            //  Try and match to an individual command
            /** @var HelpEntry $entry */
            foreach ($entries as $entry) {
                if ($entry->matches($topic)) {
                    $help = $entry;
                    break 2;
                }
            }

        }

        //  No help was found
        if ($help === null)
            throw new HelpModuleException("No help available for '$topic'");
        
        return $help;
    }

    /**
     * Prepend IRCBot command prefix to all items in an array, for help output formatting
     * 
     * @param array $items
     * @return array 
     */
    private function addPrefixes(array $items): array {
        $commandPrefix = $this->IRCBot->getIRCNetwork()->getCommandPrefixes()[0] ?? "";
        
        return array_map(
            function($item) use ($commandPrefix) {
                return $commandPrefix. $item;
            },
            $items
        );
    }

    /**
     * Format a single HelpEntry for output
     * 
     * @param HelpEntry $entry
     * @return string
     */
    private function formatHelpEntry(HelpEntry $entry): string {
        $return = [ ];
        
        //  Build parameter/text lines
        foreach ($entry->getParameterTextPairs() as $parameters => $text) {
            $return[] =
                $entry->getTrigger()." ".
                (($parameters) ? "$parameters " : ""). "- ".    //  Optionally include parameters if not empty
                $text;
        }
        $return = $this->addPrefixes($return);

        //  Append command aliases
        $aliases = $entry->getAliases();
        if ($aliases)
            $return[] = bold("Aliases: "). implode(" ", $this->addPrefixes($aliases));

        //  Append any additional notes for command
        $notes = $entry->getNotes();
        if ($notes)
            $return[] = bold("Notes: "). implode("\n", $notes);

        return implode("\n", $return);
    }
    
} 