<?php
/**
 * Utsubot - Includes.php
 * Date: 05/04/2016
 */

declare(strict_types = 1);

namespace Utsubot\Includes;
use Utsubot\{
    Module, IRCBot, IRCMessage, ModuleException
};


class IncludesException extends ModuleException {}

class Includes extends Module {

    const INCLUDES_TYPE_CLASS = 1;
    const INCLUDES_TYPE_INTERFACE = 2;
    const INCLUDES_TYPE_TRAIT = 3;
    const INCLUDES_DISPLAY_MAX = 15;

    public function __construct(IRCBot $irc) {
        parent::__construct($irc);

        $this->triggers = array(
            'includes'		=> "showIncludes",
        );
    }

    /**
     * Give information about included files
     *
     * @param IRCMessage $msg
     * @throws IncludesException
     */
    public function showIncludes(IRCMessage $msg) {
        $parameters = $msg->getCommandParameters();
        $mode = $parameters[0] ?? "";

        $output = array();
        switch ($mode) {
            case "class":
            case "classes":
                $output = self::classList(self::INCLUDES_TYPE_CLASS);
                break;

            case "interface":
            case "interfaces":
                $output = self::classList(self::INCLUDES_TYPE_INTERFACE);
                break;

            case "trait":
            case "traits":
                $output = self::classList(self::INCLUDES_TYPE_TRAIT);
                break;

            case "file":
            case "files":
                $files = self::includeInfo();
                $output = array_column($files, "details");
                break;

            case "":
                $files = self::includeInfo();

                $totalLines = array_sum(array_column($files, "lines"));
                $totalSize = array_sum(array_column($files, "sizes")) / 1024;
                $totalFiles = count($files);

                $this->respond($msg, sprintf(
                    "There are a total of %d lines (%.2fKiB) over %d files, for an average of %.2f lines (%.2fKiB) per file.",
                    $totalLines, $totalSize, $totalFiles, $totalLines / $totalFiles, $totalSize / $totalFiles
                ));

                $classCount = count(self::classList(self::INCLUDES_TYPE_CLASS));
                $interfaceCount = count(self::classList(self::INCLUDES_TYPE_INTERFACE));
                $traitCount = count(self::classList(self::INCLUDES_TYPE_TRAIT));

                $this->respond($msg, sprintf(
                    "There are %d custom classes, %d custom interfaces, and %d custom traits defined.",
                    $classCount, $interfaceCount, $traitCount
                ));
                break;

            default:
                throw new IncludesException("Invalid includes category '$mode'.");
        }

        if (count($output)) {
            $numClasses = count($output);
            if ($numClasses > self::INCLUDES_DISPLAY_MAX) {
                $output   = array_slice($output, 0, self::INCLUDES_DISPLAY_MAX);
                $output[] = "and " . ($numClasses - self::INCLUDES_DISPLAY_MAX) . " more.";
            }

            $this->respond($msg, sprintf(
                "There are %d entries: %s",
                $numClasses, implode(", ", $output)
            ));

        }
    }

    private static function includeInfo(): array {
        $files = get_included_files();
        $details = array();

        foreach ($files as $file) {
            $lineCount = count(file($file));
            $fileSize = filesize($file);

            $details[$file]['lines'] = $lineCount;
            $details[$file]['sizes'] = $fileSize;
            $details[$file]['details'] = sprintf("%s (%d li.)", basename($file), $lineCount);
        }

        usort($details, function($a, $b) {
            return $b['lines'] - $a['lines'];
        });

        return $details;
    }

    private static function classList(int $type): array {
        $classes = array();

        switch ($type) {
            case self::INCLUDES_TYPE_CLASS:
                $classes = get_declared_classes();
                break;
            case self::INCLUDES_TYPE_INTERFACE:
                $classes = get_declared_interfaces();
                break;
            case self::INCLUDES_TYPE_TRAIT:
                $classes = get_declared_traits();
                break;
        }

        $classes = array_filter(
            $classes,
            function ($class) {
                $reflection = new \ReflectionClass($class);
                return !($reflection->isInternal());
            }
        );

        sort($classes);

        return $classes;
    }
}