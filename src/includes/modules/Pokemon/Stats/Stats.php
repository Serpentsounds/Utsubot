<?php
/**
 * Utsubot - Stats.php
 * Date: 18/04/2016
 */

declare(strict_types = 1);

namespace Utsubot\Pokemon\Stats;
use Utsubot\{
    IRCBot,
    IRCMessage,
    Trigger,
    Color
};
use Utsubot\Pokemon\{
    ModuleWithPokemon,
    Stat,
    Language
};
use function Utsubot\{
    bold,
    colorText,
    Pokemon\Types\colorType
};

class Stats extends ModuleWithPokemon {

    public function __construct(IRCBot $IRCBot) {
        parent::__construct($IRCBot);

        $this->addTrigger(new Trigger("phiddenpower",   array($this, "hiddenPower"  )));
        $this->addTrigger(new Trigger("php",            array($this, "hiddenPower"  )));

        $this->addTrigger(new Trigger("piv",            array($this, "calculateIVs" )));

        $this->addTrigger(new Trigger("maxtobase",      array($this, "baseMax"      )));
        $this->addTrigger(new Trigger("m2b",            array($this, "baseMax"      )));
        $this->addTrigger(new Trigger("mtob",           array($this, "baseMax"      )));
        $this->addTrigger(new Trigger("basetomax",      array($this, "baseMax"      )));
        $this->addTrigger(new Trigger("b2m",            array($this, "baseMax"      )));
        $this->addTrigger(new Trigger("btom",           array($this, "baseMax"      )));
        $this->addTrigger(new Trigger("maxtobase50",    array($this, "baseMax"      )));
        $this->addTrigger(new Trigger("m2b50",          array($this, "baseMax"      )));
        $this->addTrigger(new Trigger("mtob50",         array($this, "baseMax"      )));
        $this->addTrigger(new Trigger("basetomax50",    array($this, "baseMax"      )));
        $this->addTrigger(new Trigger("b2m50",          array($this, "baseMax"      )));
        $this->addTrigger(new Trigger("btom50",         array($this, "baseMax"      )));

        $this->addTrigger(new Trigger("pstat",          array($this, "baseStat"     )));
        $this->addTrigger(new Trigger("pstats",         array($this, "baseStat"     )));
    }

    public function baseMax(IRCMessage $msg) {
        $this->_require("Utsubot\\Pokemon\\ParameterParser");

        $parser = new ParameterParser();
        $result = $parser->parseBaseMaxParameters($msg->getCommandParameters(), $msg->getCommand());

        if ($result->getFrom() == "base")
            $calculated = calculateStat($result->getStat(), 31, 252, $result->getLevel(), 1.1, $result->isHp());
        else
            $calculated = calculateBase($result->getStat(), 31, 252, $result->getLevel(), 1.1, $result->isHp());

        $this->respond($msg, sprintf(
            "%s %s = %s %s",
            $result->getStat(),
            $result->getFrom(),
            $calculated,
            $result->getTo()
        ));
    }

    public function baseStat(IRCMessage $msg) {
        $this->_require("Utsubot\\Pokemon\\ParameterParser");

        $parser = new ParameterParser();
        $parser->injectManager("Pokemon", $this->getOutsideManager("Pokemon"));
        $parser->injectManager("Nature", $this->getOutsideManager("Nature"));
        $result = $parser->parseIVStatParameters($msg->getCommandParameters());

        $output = array();
        for ($i = 0; $i <= 5; $i++) {
            $stat = new Stat($i);
            $statName = $stat->getName();
            
            $statValue = calculateStat(
                $result->getPokemon()->getBaseStat($stat),
                $result->getStatValue($i),
                $result->getEV($i),
                $result->getLevel(),
                $result->getNatureMultiplier($i),
                $i == 0
            );
            
            if ($statName == $result->getNatureIncreases())
                $statName = colorText($statName, new Color(Color::Red));
            elseif ($statName == $result->getNatureDecreases())
                $statName = colorText($statName, new Color(Color::Blue));

            $output[] = sprintf(
                "%s: %s",
                bold($statName),
                $statValue
            );
        }

        $this->respond($msg, sprintf(
            "Stats for %s: %s",
            bold($result->getPokemon()->getName(new Language(Language::English))),
            implode(" ", $output)
        ));

    }

    public function calculateIVs(IRCMessage $msg) {
        $this->_require("Utsubot\\Pokemon\\ParameterParser");

        $parser = new ParameterParser();
        $parser->injectManager("Pokemon", $this->getOutsideManager("Pokemon"));
        $parser->injectManager("Nature", $this->getOutsideManager("Nature"));
        $result = $parser->parseIVStatParameters($msg->getCommandParameters());

        $output = array();
        for ($i = 0; $i <= 5; $i++) {
            $stat     = new Stat($i);
            $statName = $stat->getName();
            
            $IVRange = getIVRange(
                $result->getPokemon()->getBaseStat($stat),
                $result->getStatValue($i),
                $result->getEV($i),
                $result->getLevel(),
                $result->getNatureMultiplier($i),
                $i == 0
            );

            if ($statName == $result->getNatureIncreases())
                $statName = colorText($statName, new Color(Color::Red));
            elseif ($statName == $result->getNatureDecreases())
                $statName = colorText($statName, new Color(Color::Blue));

            $output[] = sprintf(
                "%s: %s",
                bold($statName),
                implode("-", $IVRange)
            );
        }

        $this->respond($msg, sprintf(
            "Possible IVs for %s: %s",
            bold($result->getPokemon()->getName(new Language(Language::English))),
            implode(" ", $output)
        ));
    }

    /**
     * Output information about a pokemon's Hidden Power stats, given a list of their IVs
     *
     * @param IRCMessage $msg
     */
    public function hiddenPower(IRCMessage $msg) {
        $parameterString = $msg->getCommandParameterString();

        //	Match 6 numbers in a row
        if (preg_match('/^(\d{1,2}[\/ \\\\]){5}\d{1,2}$/', $parameterString)) {
            //	Split into individual numbers
            $ivs = array_map("intval", preg_split('/[\/ \\\\]/', $parameterString));

            $hiddenPower = (new HiddenPowerCalculator(...$ivs))->calculate();

            $this->respond($msg, sprintf(
                "Your hidden power is %s-type, with a base power of %s.",
                bold(colorType($hiddenPower->getType())),
                bold($hiddenPower->getPower())
            ));
        }
    }



}