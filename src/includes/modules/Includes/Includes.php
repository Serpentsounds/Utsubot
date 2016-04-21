<?php
/**
 * Includes Module
 *
 * Provides an !includes command that shows statistics about included files and user defined classes
 */

declare(strict_types = 1);

namespace Utsubot\Includes;
use Utsubot\{
    Module, IRCBot, IRCMessage, ModuleException
};


/**
 * Class IncludesException
 *
 * @package Utsubot\Includes
 */
class IncludesException extends ModuleException {}

/**
 * Class Includes
 *
 * @package Utsubot\Includes
 */
class Includes extends Module {

    //  Max number of files/classes to output before cutoff
    const INCLUDES_DISPLAY_MAX = 10;

    /**
     * @param IRCBot $irc
     */
    public function __construct(IRCBot $irc) {
        parent::__construct($irc);

        $this->triggers = array(
            'includes'		=> "includes",
        );
    }

    /**
     * Give information about included files
     *
     * @param IRCMessage $msg
     * @throws IncludesException
     */
    public function includes(IRCMessage $msg) {
        $parameters = $msg->getCommandParameters();
        $mode = (string)array_shift($parameters);
        $search = (string)array_shift($parameters);

        $output = "";
        $list = null;
        switch ($mode) {
            //  List user defined classes
            case "class":
            case "classes":
                $list = new ClassList(get_declared_classes());
                break;

            //  List interfaces
            case "interface":
            case "interfaces":
                $list = new ClassList(get_declared_interfaces());
                break;

            //  List traits
            case "trait":
            case "traits":
                $list = (new ClassList(get_declared_traits()));
                break;

            //  List files and line count
            case "file":
            case "files":
                $list = (new FileList(get_included_files()));
                break;

            //  List totals for all categories
            case "":
                //  File section
                $fileList = new FileList(get_included_files());

                $output = sprintf(
                    "There are a total of %d lines (%s) over %d files, for an average of %.2f lines (%s) per file.",
                    $fileList->getTotalLines(),
                    $fileList->getTotalFormattedSize(),
                    $fileList->getItemCount(),
                    $fileList->getAverageLines(),
                    $fileList->getAverageFormattedSize()
                );

                //  Class section
                $classList = new ClassList(get_declared_classes());
                $interfaceList = new ClassList(get_declared_interfaces());
                $traitList = new ClassList(get_declared_traits());
                
                $output .= sprintf(
                    "\nThere are %d custom classes, %d custom interfaces, and %d custom traits defined.",
                    $classList->getItemCount(), $interfaceList->getItemCount(), $traitList->getItemCount()
                );
                break;


            default:
                throw new IncludesException("Invalid includes category '$mode'.");
        }

        //  Item list saved, optionally filter list, and get output
        if ($list instanceof ItemList) {
            if (strlen($search))
                $list->filterList($search);
            $output = $list->getFormattedList(self::INCLUDES_DISPLAY_MAX);
        }

        $this->respond($msg, $output);
    }
    
}
