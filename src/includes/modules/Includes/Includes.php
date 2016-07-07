<?php
/**
 * Includes Module
 *
 * Provides an !includes command that shows statistics about included files and user defined classes
 */

declare(strict_types = 1);

namespace Utsubot\Includes;


use Utsubot\Help\{
    HelpEntry,
    IHelp,
    THelp
};
use Utsubot\{
    Module,
    IRCBot,
    IRCMessage,
    Trigger,
    ModuleException
};
use function Utsubot\bold;

/**
 * Class IncludesException
 *
 * @package Utsubot\Includes
 */
class IncludesException extends ModuleException {

}


/**
 * Class Includes
 *
 * @package Utsubot\Includes
 */
class Includes extends Module implements IHelp {

    use THelp;

    //  Max number of files/classes to output before cutoff
    const Display_Max = 10;


    /**
     * @param IRCBot $irc
     */
    public function __construct(IRCBot $irc) {
        parent::__construct($irc);

        $includes = new Trigger("includes", [ $this, "includes" ]);
        $this->addTrigger($includes);

        $help = new HelpEntry("IncludeInfo", $includes);
        $help->addParameterTextPair("", "Shows a summary of all includes categories.");
        $help->addParameterTextPair("[class|interface|traits|file|lines|namespace]", "Show information about includes with a focus on the given category.");
        $this->addHelp($help);
    }


    /**
     * Give information about included files
     *
     * @usage !includes [class|interface|trait|file]
     * @param IRCMessage $msg
     * @throws IncludesException
     */
    public function includes(IRCMessage $msg) {
        $parameters = $msg->getCommandParameters();
        $mode       = (string)array_shift($parameters);
        $search     = (string)array_shift($parameters);

        $output = "";
        $list   = null;
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

            //  List metrics of line contents
            case "line":
            case "lines":
                $output = $this->getSourceAnalysis();
                break;

            //  Group by namespace
            case "namespace":
                $output = $this->getNamespaceAnalysis();
                break;

            //  List totals for all categories
            case "":
                $output = $this->getTotals();
                break;

            default:
                throw new IncludesException("Invalid includes category '$mode'.");
        }

        //  Item list saved, optionally filter list, and get output
        if ($list instanceof ItemList) {
            if (strlen($search))
                $list->filterList($search);
            $output = $list->getFormattedList(self::Display_Max);
        }

        $this->respond($msg, $output);
    }


    /**
     * Get human readable stats from a FileList
     *
     * @param FileList $fileList
     * @return string
     */
    protected function getFilesOverview(FileList $fileList): string {
        return sprintf(
            "There are a total of %d lines (%s) over %d files, for an average of %.2f lines (%s) per file.",
            $fileList->getTotalLines(),
            $fileList->getTotalFormattedSize(),
            $fileList->getItemCount(),
            $fileList->getAverageLines(),
            $fileList->getAverageFormattedSize()
        );
    }


    /**
     * Get an overview for each category
     *
     * @return string
     */
    protected function getTotals(): string {
        $output = $this->getFilesOverview(new FileList(get_included_files()));

        //  Class section
        $classList     = new ClassList(get_declared_classes());
        $interfaceList = new ClassList(get_declared_interfaces());
        $traitList     = new ClassList(get_declared_traits());

        $output .= sprintf(
            "\nThere are %d custom classes, %d custom interfaces, and %d custom traits defined.",
            $classList->getItemCount(), $interfaceList->getItemCount(), $traitList->getItemCount()
        );

        return $output;
    }


    /**
     * Categorize included files into namespaces, and get summed stats for each namespace
     *
     * @return string
     */
    protected function getNamespaceAnalysis(): string {
        $fileList = new FileList(get_included_files());
        /** @var FileList[] $namespaces */
        $namespaces = [ ];

        //  Get source for each file
        foreach ($fileList as $file) {
            $source = file($file->getPath());

            //  Check lines until a namespace declaration is found
            foreach ($source as $line) {

                //  Save file and move on
                if (preg_match("/^\s*namespace\s+([^;]+);/", $line, $match)) {

                    //  Use first subsection of namespace
                    $namespace = $match[ 1 ];
                    if (strpos($namespace, "\\"))
                        $namespace = explode("\\", $namespace)[1];

                    //  Initialize if necessary
                    if (!isset($namespaces[ $namespace ]))
                        $namespaces[ $namespace ] = new FileList([ ]);

                    $namespaces[ $namespace ]->addFile($file);
                    break;
                }
            }
        }

        //  Sort namespaces by line count descending
        uasort($namespaces, function (FileList $a, FileList $b) {
            return $b->getTotalLines() - $a->getTotalLines();
        });

        //  Format output
        $output = [ ];
        foreach ($namespaces as $namespace => $fileList)
            $output[] = sprintf("%s: %d lines over %d files", bold($namespace), $fileList->getTotalLines(), $fileList->getItemCount());

        return implode(", ", $output);
    }


    /**
     * Break down source files into code, comments, and whitespace
     *
     * @return string
     */
    protected function getSourceAnalysis(): string {
        $fileList = new FileList(get_included_files());
        $code     = $comments = $whitespace = 0;

        //  Get source for each file
        foreach ($fileList as $file) {
            $source = file($file->getPath());

            $inComment = false;

            //  Each line of source
            foreach ($source as $line) {
                //  Whitespace
                if (preg_match("/^\s*$/", $line))
                    $whitespace++;

                //  Single line comment
                elseif (preg_match("/^\s*#/", $line) || preg_match("/^\s*\/\//", $line))
                    $comments++;

                else {
                    //  Begin block comment
                    if (preg_match("/^\s*\/\*/", $line))
                        $inComment = true;

                    //  End block comment, count and continue loop to simplify logic to determine code
                    if (preg_match("/\*\/\s*$/", $line)) {
                        $inComment = false;
                        $comments++;
                        continue;
                    }

                    //  Middle of block comment, or counting for beginning block comment
                    if ($inComment)
                        $comments++;

                    //  Not in comment, and ending comment block was 'continue'd...must be code!
                    else
                        $code++;

                }
            }
        }

        $output = $this->getFilesOverview($fileList);

        $total = $comments + $code + $whitespace;
        $output .= sprintf(
            "\nThe files are distributed into roughly %d (%.2f%%) lines of code, %d (%.2f%%) lines of comments, and %d (%.2f%%) lines of whitespace.",
            $code,
            100.0 * $code / $total,
            $comments,
            100.0 * $comments / $total,
            $whitespace,
            100.0 * $whitespace / $total
        );

        return $output;
    }

}
