<?php

namespace Utsubot\Calculator;


/**
 * Class CalculatorException
 *
 * @package Utsubot\Calculator
 */
class CalculatorException extends \Exception {}

/**
 * Class Calculator
 * A utility to safely evaluate a user-input mathematical expression
 *
 * @package Utsubot\Calculator
 */
class Calculator {

    const VALID_FUNCTIONS = array(
        "ceil", "floor", "min", "max", "rand", "ln", "log", "sqrt", "cbrt", "nrt", "round", "sin", "sin", "sinh", "asinh", "cos", "acos", "cosh", "acosh",
        "tan", "atan", "tanh", "atanh", "csc", "acsc", "csch", "acsch", "sec", "asec", "sech", "asech", "cot", "acot", "coth", "acoth"
    );
	private $expression = "";

	public function __construct($expression) {
        $this->setExpression($expression);
	}

    /**
     * @param string $expression Mathematical expression to save to this object
     */
    public function setExpression($expression) {
        
        //  Replace math constants and implicit multiplication        
        $this->expression = preg_replace_callback(
            "/\b(pi|euler|e)\b/",
        
            function ($match) { 
                return constant("M_".strtoupper($match[1])); 
            },
        
            preg_replace(
                "/([0-9\. \)]+)(?=[a-z\(])|([a-z \)]+)(?=\()/",
                '$1$2*',
                preg_replace(
                    "/\s/", 
                    "", 
                    strtolower($expression)
                )
            )
        );
    }

    /**
     * Calculates and returns this parser's saved expression
     *
     * @return float
     * @throws CalculatorException Malformed expression
     */
	public function calculate(): float {
		return self::evaluate($this->expression);
	}

    /**
     * Recursive function to evaluate math expressions
     *
     * @param string $expression
     * @return float
     * @throws CalculatorException Malformed expression
     */
	public static function evaluate(string $expression): float {

        //  Check for instances of custom functions
        foreach (self::VALID_FUNCTIONS as $function) {
            //  Find position of function call
            $funcPos = strpos($expression, $function."(");
            if ($funcPos !== false) {
                //  Start evaluating after opening parentheses
                $startPos = $funcPos + strlen($function) + 1;

                $characters = str_split(substr($expression, $startPos));
                $depth = 1;
                $length = -1;
                //  Iterate characters to find end of expression
                foreach ($characters as $character) {
                    $length++;
                    if ($character == "(")
                        $depth++;
                    elseif ($character == ")")
                        $depth--;
                    else
                        continue;
                    if (!$depth)
                        break;
                }
                //  Parentheses didn't match up
                if ($depth)
                    throw new CalculatorException("Bracket mismatch in math parser expression.");

                
                //  Section of expression current function encompasses
                $evaluate = substr($expression, $startPos, $length);
                //  Invalid characters in expression, there may be nested functions, evaluate recursively
                if (preg_match('/[^0-9 +\-\/*,^]/', $evaluate))
                    $evaluate = self::evaluate($evaluate);

                //  Prevent rounding errors for trig functions
                $evaluate = round(self::math($function, $evaluate), 8);
                //  Piece together the expression by replacing the newly evaluated portion
                $expression = substr($expression, 0, $funcPos). $evaluate. substr($expression, $startPos + $length + 1);
            }

        }

        //  Invalid characters in expression after functions have been evaluated, abort
        if (preg_match('/[^0-9 \.+\-\/\*\^\(\)]/', $expression))
            throw new CalculatorException("Invalid characters in math parser expression: $expression");

        //  Parentheses with no numbers inside
        if (preg_match('/(\([^0-9]*\))/', $expression, $match))
            throw new CalculatorException("Invalid group in math parser expression: {$match[1]}");
        //  Closing parentheses with no opening
        if (preg_match('/((?:^|[^\(]+)\))/', $expression, $match))
            throw new CalculatorException("Invalid group in math parser expression: {$match[1]}");

        //  Decimals with no numbers after them
        if (preg_match('/(\.[^0-9]+)/', $expression, $match))
            throw new CalculatorException("Invalid decimal in math parser expression: {$match[1]}");

        //  Operations with invalid right operand
        if (preg_match('/([+\-\/\*\^](?:[^0-9\(]+|$))/', $expression, $match))
            throw new CalculatorException("Invalid symbol usage in math parser expression: {$match[1]}");
        //  Operations with invalid left operand
        if (preg_match('/((?:^|[^0-9\)]+)[+\-\/\*\^])/', $expression, $match))
            throw new CalculatorException("Invalid symbol usage in math parser expression: {$match[1]}");

        //  Replace bitwise operator to intended exponent
        $expression = str_replace("^", "**", $expression);

        return (float)eval("return $expression;");

	}


    /**
     * Evaluate custom math functions
     *
     * @param string $function
     * @param float|string $parameters
     * @return float|string
     */
	public static function math($function, $parameters) {
        //  Call existing php functions directly
        if (function_exists($function))
			return call_user_func_array($function, (strlen($parameters)) ? explode(",", $parameters) : array());

        //  Supplemental functions
		switch ($function) {
            //  Roots
            case 'cbrt':
                return pow($parameters, 1/3);
            case 'nrt':
                $parameters = explode(",", $parameters);
                return pow($parameters[1], 1 / $parameters[2]);

            //  Logarithms
			case 'ln':
				return log($parameters);

            //  Trigonometry
			case 'sec':
				return 1 / cos($parameters);
			case 'asec':
				return acos(1 / $parameters);
			case 'sech':
				return 1 / cosh($parameters);
			case 'asech':
				return acosh(1 / $parameters);

			case 'csc':
				return 1 / sin($parameters);
			case 'acsc':
				return asin(1 / $parameters);
			case 'csch':
				return 1 / sinh($parameters);
			case 'acsch':
				return asinh(1 / $parameters);

			case 'cot':
				return 1 / tan($parameters);
			case 'acot':
				return M_PI / 2 - atan($parameters);
			case 'coth':
				return 1 / tanh($parameters);
			case 'acoth':
				return M_PI / 2 - atanh($parameters);
		}

		return "";
	}

}