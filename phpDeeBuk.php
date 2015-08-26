<?php

//
// phpDeeBuk - Debugger that outputs PHP data via JavaScript generated popup.
// Copyright (c) 2015 Marcel Joachim Kloubert, All rights reserved.
//
// This library is free software; you can redistribute it and/or
// modify it under the terms of the GNU Lesser General Public
// License as published by the Free Software Foundation; either
// version 3.0 of the License, or (at your option) any later version.
//
// This library is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the GNU
// Lesser General Public License for more details.
//
// You should have received a copy of the GNU Lesser General Public
// License along with this library.
//


/**
 * Debugger class.
 *
 * @author Marcel Joachim Kloubert <marcel.kloubert@gmx.net>
 */
final class phpDeeBuk implements \ArrayAccess {
    /**
     * @var int
     */
    private $_analyzeObjCounter;
    /**
     * @var int
     */
    private $_analyzeValCounter;
    /**
     * @var int
     */
    private $_assertCounter = 0;
    /**
     * @var array
     */
    private $_asserts;
    /**
     * @var int
     */
    private $_backtraceCounter;
    /**
     * @var string
     */
    private $_bgColor;
    /**
     * @var string
     */
    private $_console;
    /**
     * @var int
     */
    private $_dumpCounter;
    /**
     * @var string
     */
    private $_fgColor;
    /**
     * @var array
     */
    private static $_instances = array();
    /**
     * @var bool
     */
    private $_isBold;
    /**
     * @var bool
     */
    private $_isUnderline;
    /**
     * @var string
     */
    private $_name;
    /**
     * @var array
     */
    private $_tabs;
    /**
     * @var array
     */
    private $_vars;


    private function __construct() {
        $this->reset();
    }


    private function addAssert() {
        $this->_asserts[] = call_user_func_array(array($this, 'createAssertEntry'),
                                                 func_get_args());
    }

    private function addTab() {
        $this->_tabs[] = call_user_func_array(array($this, 'createTabEntry'),
                                              func_get_args());
    }

    /**
     * Adds a value/object for analyzation.
     *
     * @param mixed $val The value / object to add.
     * @param string $caption The optional caption to use.
     *
     * @return $this
     */
    public function analyze($val, $caption = null) {
        $nr = -1;

        $caption = trim($caption);
        if ('' == $caption) {
            if (is_object($val)) {
                $nr = ++$this->_analyzeObjCounter;
                $typeName = 'Object';
            }
            else {
                $nr = ++$this->_analyzeValCounter;
                $typeName = 'Value';
            }

            $caption = sprintf('%s #%s', $typeName, $nr);
        }

        $this->addTab($caption, 'getAnalyzeHtml',
                      $val, $nr);

        return $this;
    }

    /**
     * Checks if all variables are set or not.
     *
     * @param string|\Traversable|array ... List of variables to remove.
     *                                      If an argument is a string, it is used as regular expression pattern.
     *                                      If an argument is an array or traversable it is checked by variable name.
     *
     * @return bool Are set or not.
     */
    public function areVarsSet() {
        for ($i = 0; $i < func_num_args(); $i++) {
            $nameOrPattern = func_get_arg($i);

            $found = false;

            $predicate = self::getCheckVarNamePredicate($nameOrPattern);
            if (false !== $predicate) {
                foreach ($this->_vars as $i => $v) {
                    if (call_user_func($predicate, $v->name)) {
                        // matches
                        $found = true;
                    }
                }
            }

            if (!$found) {
                return false;
            }
        }

        return true;
    }

    /**
     * Tests if two values are equal.
     *
     * @param mixed $x The left value.
     * @param mixed $y The right value.
     * @param string null $caption
     *
     * @return phpDeeBuk
     */
    public function assertEqual($x, $y, $caption = null) {
        return $this->assertTrue($x == $y,
                                 $caption);
    }

    /**
     * Tests if two values have exactly the same value.
     *
     * @param mixed $x The left value.
     * @param mixed $y The right value.
     * @param string $caption The custom caption to use.
     *
     * @return $this
     */
    public function assertExact($x, $y, $caption = null) {
        return $this->assertTrue($x === $y,
                                 $caption);
    }

    /**
     * Tests if a value is exactly false.
     *
     * @param mixed $value The value to check.
     * @param string $caption The custom caption to use.
     *
     * @return $this
     */
    public function assertFalse($value, $caption = null) {
        return $this->assertTrue(false === $value,
                                 $caption);
    }

    /**
     * Checks if a file / directory exists.
     *
     * @param string $path The path of the file.
     * @param string $caption The custom caption to use.
     *
     * @return $this
     */
    public function assertFileExists($path, $caption = null) {
        return $this->assertTrue(file_exists($path),
                                 $caption);
    }

    /**
     * Tests if a left value is greater than a right one.
     *
     * @param mixed $x The left value.
     * @param mixed $y The right value.
     * @param string $caption The custom caption to use.
     *
     * @return $this
     */
    public function assertGreaterThan($x, $y, $caption = null) {
        return $this->assertTrue($x > $y,
                                 $caption);
    }

    /**
     * Tests if a left value is greater (or equal) than a right one.
     *
     * @param mixed $x The left value.
     * @param mixed $y The right value.
     * @param string $caption The custom caption to use.
     *
     * @return $this
     */
    public function assertGreaterThanOrEqual($x, $y, $caption = null) {
        return $this->assertTrue($x >= $y,
                                 $caption);
    }

    /**
     * Checks if a value is an instance of a class.
     *
     * @param string $className The name of the class.
     * @param mixed $value The value to check.
     * @param string $caption The custom caption to use.
     *
     * @return $this
     */
    public function assertInstanceOf($className, $value, $caption = null) {
        $rc = new ReflectionClass($className);

        return $this->assertTrue($rc->isInstance($value),
                                 $caption);
    }

    /**
     * Tests if a left value is less than a right one.
     *
     * @param mixed $x The left value.
     * @param mixed $y The right value.
     * @param string $caption The custom caption to use.
     *
     * @return $this
     */
    public function assertLessThan($x, $y, $caption = null) {
        return $this->assertTrue($x < $y,
                                 $caption);
    }

    /**
     * Tests if a left value is less (or equal) than a right one.
     *
     * @param mixed $x The left value.
     * @param mixed $y The right value.
     * @param string $caption The custom caption to use.
     *
     * @return $this
     */
    public function assertLessThanOrEqual($x, $y, $caption = null) {
        return $this->assertTrue($x <= $y,
                                 $caption);
    }

    /**
     * Tests if two values are NOT equal.
     *
     * @param mixed $x The left value.
     * @param mixed $y The right value.
     * @param string $caption The custom caption to use.
     *
     * @return $this
     */
    public function assertNotEqual($x, $y, $caption = null) {
        return $this->assertTrue($x != $y,
                                 $caption);
    }

    /**
     * Tests if two values have NOT exactly the same value.
     *
     * @param mixed $x The left value.
     * @param mixed $y The right value.
     * @param string $caption The custom caption to use.
     *
     * @return $this
     */
    public function assertNotExact($x, $y, $caption = null) {
        return $this->assertTrue($x !== $y,
                                 $caption);
    }

    /**
     * Checks if a file / directory does NOT exist.
     *
     * @param string $path The path of the file.
     * @param string $caption The custom caption to use.
     *
     * @return $this
     */
    public function assertNotFileExists($path, $caption = null) {
        return $this->assertTrue(!file_exists($path),
                                 $caption);
    }

    /**
     * Checks if a value is NOT an instance of a class.
     *
     * @param string $className The name of the class.
     * @param mixed $value The value to check.
     * @param string $caption The custom caption to use.
     *
     * @return $this
     */
    public function assertNotInstanceOf($className, $value, $caption = null) {
        $rc = new ReflectionClass($className);

        return $this->assertTrue(!$rc->isInstance($value),
                                 $caption);
    }

    /**
     * Tests if a value is NOT (null).
     *
     * @param mixed $value The value to check.
     * @param string $caption The custom caption to use.
     *
     * @return $this
     */
    public function assertNotNull($value, $caption = null) {
        return $this->assertTrue(null !== $value,
                                 $caption);
    }

    /**
     * Checks if a string does NOT match a regular expression.
     *
     * @param string $pattern The regular expression.
     * @param string $subject The string to check.
     * @param string $caption The custom caption to use.
     *
     * @return $this
     */
    public function assertNotRegExp($pattern, $subject, $caption = null) {
        return $this->assertTrue(1 !== preg_match($pattern, $subject),
                                 $caption);
    }

    /**
     * Tests if a value is (null).
     *
     * @param mixed $value The value to check.
     * @param string $caption The custom caption to use.
     *
     * @return $this
     */
    public function assertNull($value, $caption = null) {
        return $this->assertTrue(null === $value,
                                 $caption);
    }

    /**
     * Checks if a string matches a regular expression.
     *
     * @param string $pattern The regular expression.
     * @param string $subject The string to check.
     * @param string $caption The custom caption to use.
     *
     * @return $this
     */
    public function assertRegExp($pattern, $subject, $caption = null) {
        return $this->assertTrue(1 === preg_match($pattern, $subject),
                                 $caption);
    }

    /**
     * Tests if a value is exactly true.
     *
     * @param mixed $value The value to check.
     * @param string $caption The custom caption to use.
     *
     * @return $this
     */
    public function assertTrue($value, $caption = null) {
        $this->addAssert(true === $value,
                         $caption);

        return $this;
    }

    /**
     * Does a debug_backtrace.
     *
     * @param string $caption The custom caption to use.
     * @param int $limit The maximum number of entries.
     * @param int $options Custom options
     *
     * @return $this
     */
    public function backtrace($caption = null, $limit = 0, $options = DEBUG_BACKTRACE_PROVIDE_OBJECT) {
        $nr = $this->_backtraceCounter;

        $caption = trim($caption);
        if ('' == $caption) {
            $caption = sprintf('Backtrace #%s', ++$this->_backtraceCounter);
        }

        if (version_compare(PHP_VERSION, '5.4.0') < 0) {
            $bt = debug_backtrace($options);

            if ($limit > 0) {
                $bt = array_slice($bt, 0, $limit);
            }
        }
        else {
            $bt = debug_backtrace($options, $limit);
        }

        $this->addTab($caption, 'getBacktraceHtml',
                      array_reverse($bt), $nr);

        return $this;
    }

    /**
     * Removes all variables.
     *
     * @return $this
     */
    public function clearVars() {
        $this->_vars = array();

        return $this;
    }

    /**
     * Clears the console output.
     *
     * @return phpDeeBuk $this
     */
    public function clr() {
        $this->_console = array();
        return $this;
    }

    private function createAssertEntry($succeeded, $caption) {
        if (!is_callable($succeeded)) {
            $valueToCheck = $succeeded;

            $succeeded = function() use ($valueToCheck) {
                return true === $valueToCheck;
            };
        }

        $caption = trim($caption);
        if ('' == $caption) {
            $caption = 'Test #' . trim(++$this->_assertCounter);
        }

        $result          = new \stdClass();
        $result->caption = $caption;
        $result->state   = call_user_func($succeeded) ? 0 : 1;

        return $result;
    }

    private function createTabEntry($title, $method) {
        $result        = new stdClass();
        $result->title = trim($title);

        if (!is_array($method)) {
            $method = array($this, trim($method));
        }

        $result->contentProvider = $method;

        $args = array();
        if (func_num_args() > 1) {
            $args = array_slice(func_get_args(), 2);
        }

        $result->contentProviderArgs = $args;

        return $result;
    }

    /**
     * Dumps a value.
     *
     * @param mixed $val The value to dump.
     * @param string $caption The custom caption to use.
     *
     * @return $this
     */
    public function dump($val, $caption = null) {
        $caption = trim($caption);
        if ('' == $caption) {
            $caption = sprintf('Dump #%s', ++$this->_dumpCounter);
        }

        $this->addTab($caption, 'getDumpHtml',
                      $val);

        return $this;
    }

    private function getAnalyzeHtml($val, $index) {
        if (is_object($val)) {
            return $this->getAnalyzeHtml_Object($val, $index);
        }

        return $this->getAnalyzeHtml_Value($val, $index);
    }

    private function getAnalyzeHtml_Object($obj, $index) {
        $reflector = new \ReflectionObject($obj);

        $result = '';

        $result .= '<h4>About</h4>';
        {
            $result .= '<table class="aboutTable">';

            $result .= '<tbody>';
            $result .= '<tr>';
            $result .= '<td class="colLeft"><strong>Name</strong></td>';
            $result .= '<td class="colRight">' . htmlentities($reflector->getShortName()) . '</td>';
            $result .= '</tr>';

            $result .= '<tr>';
            $result .= '<td class="colLeft"><strong>Namespace</strong></td>';
            $result .= '<td class="colRight">' . htmlentities($reflector->getNamespaceName()) . '</td>';
            $result .= '</tr>';
            $result .= '</tbody>';

            $result .= '<tr>';
            $result .= '<td class="colLeft"><strong>File</strong></td>';
            $result .= '<td class="colRight">' . htmlentities($reflector->getFileName()) . '</td>';
            $result .= '</tr>';
            $result .= '</tbody>';

            $result .= '</table>';
        }

        $result .= '<h4>Members</h4>';
        {
            $result .= '<ul class="accordion" data-accordion>';

            // constants
            {
                $accId = "objConstants{$index}";

                $constants = $reflector->getConstants();
                uksort($constants, function($x, $y) {
                    return strcmp(trim(strtolower($x)),
                                  trim(strtolower($y)));
                });

                $content = 'No constants found.';
                if (!empty($constants)) {
                    $content = '<table class="memberTable">';

                    $content .= '<thead>';
                    $content .= '<tr>';
                    $content .= '<th class="memberName">Name</th>';
                    $content .= '<th>Value</th>';
                    $content .= '</tr>';
                    $content .= '</thead>';

                    $content .= '<tbody>';

                    foreach ($constants as $name => $value) {
                        $content .= '<tr>';
                        $content .= '<td>' . htmlentities($name) . '</td>';
                        $content .= '<td>' . htmlentities(var_export($value, true)) . '</td>';
                        $content .= '</tr>';
                    }

                    $content .= '</tbody>';

                    $content .= '</table>';
                }

                $result .= '<li class="accordion-navigation">';
                $result .= '<a href="#' . $accId . '" aria-expanded="false">Constants (' . trim(count($constants)) . ')</a>';
                $result .= '<div id="' . $accId . '" class="content">' . $content . '</div>';
                $result .= '</li>';
            }

            // methods
            {
                $accId = "objMethods{$index}";

                $methods = $reflector->getMethods();
                usort($methods, function(\ReflectionMethod $x, \ReflectionMethod $y) {
                    return strcmp(trim(strtolower($x->getName())),
                        trim(strtolower($y->getName())));
                });

                foreach ($methods as $i => $m) {
                    if (!$m->isPublic()) {
                        unset($methods[$i]);
                    }
                }

                $content = 'No methods found.';
                if (!empty($methods)) {
                    $content = '<table class="memberTable">';

                    $content .= '<thead>';
                    $content .= '<tr>';
                    $content .= '<th class="memberName">Name</th>';
                    $content .= '</tr>';
                    $content .= '</thead>';

                    $content .= '<tbody>';

                    foreach ($methods as $m) {
                        $content .= '<tr>';
                        $content .= '<td>' . htmlentities($m->getName()) . '</td>';
                        $content .= '</tr>';
                    }

                    $content .= '</tbody>';

                    $content .= '</table>';
                }

                $result .= '<li class="accordion-navigation">';
                $result .= '<a href="#' . $accId . '" aria-expanded="false">Methods (' . trim(count($methods)) . ')</a>';
                $result .= '<div id="' . $accId . '" class="content">' . $content . '</div>';
                $result .= '</li>';
            }

            // properties
            {
                $accId = "objProperties{$index}";

                $properties = $reflector->getProperties();
                usort($properties, function(\ReflectionProperty $x, \ReflectionProperty $y) {
                    return strcmp(trim(strtolower($x->getName())),
                                  trim(strtolower($y->getName())));
                });

                foreach ($properties as $i => $p) {
                    if (!$p->isPublic()) {
                        unset($properties[$i]);
                    }
                }

                $content = 'No properties found.';
                if (!empty($properties)) {
                    $content = '<table class="memberTable">';

                    $content .= '<thead>';
                    $content .= '<tr>';
                    $content .= '<th class="memberName">Name</th>';
                    $content .= '<th>Current value</th>';
                    $content .= '</tr>';
                    $content .= '</thead>';

                    $content .= '<tbody>';

                    foreach ($properties as $p) {
                        $content .= '<tr>';
                        $content .= '<td>' . htmlentities($p->getName()) . '</td>';
                        $content .= '<td>' . htmlentities(var_export($p->getValue($obj), true)) . '</td>';
                        $content .= '</tr>';
                    }

                    $content .= '</tbody>';

                    $content .= '</table>';
                }

                $result .= '<li class="accordion-navigation">';
                $result .= '<a href="#' . $accId . '" aria-expanded="false">Properties (' . trim(count($properties)) . ')</a>';
                $result .= '<div id="' . $accId . '" class="content">' . $content . '</div>';
                $result .= '</li>';
            }

            $result .= '</ul>';
        }

        return $result;
    }

    private function getAnalyzeHtml_Value($val, $index) {
        return htmlentities(sprintf('[%s] %s',
                                    gettype($val), $val));
    }

    private function getAssertHtml() {
        $result = '<ul class="accordion" data-accordion>';
        {
            foreach ($this->_asserts as $i => $assert) {
                $newAcc = '<li class="accordion-navigation">';
                {
                    $elementId = "assert{$i}";

                    $cssClass = ' testSucceeded';
                    if (1 == $assert->state) {
                        $cssClass = ' testFailed';
                    }

                    $newAcc .= '<a href="#' . trim($elementId) . '" class=' . $cssClass .'>' . htmlentities($assert->caption) . '</a>';
                }
                $newAcc .= '</li>';

                $result .= $newAcc;
            }
        }
        $result .= '</ul>';

        return $result;
    }

    private static function getBacktraceEntryOfCallingLine() {
        $bt = debug_backtrace();

        $result = $bt[1];
        unset($bt);

        return $result;
    }

    private function getBacktraceHtml(array $backtrace, $index) {
        $result = '<ul class="accordion" data-accordion>';

        $btSize = count($backtrace);
        foreach ($backtrace as $i => $entry) {
            $file = $entry['file'];
            $line = $entry['line'];

            // class
            $class = null;
            if (isset($entry['class'])) {
                $class = trim($entry['class']);
            }

            $func = null;
            if (isset($entry['function'])) {
                $func = trim($entry['function']);
            }

            $funcType = null;
            if (isset($entry['type'])) {
                $funcType = trim($entry['type']);
            }

            $funcArgs = null;
            if (isset($entry['args'])) {
                $funcArgs = $entry['args'];
            }

            //TODO
            $title = 'Step #' . trim($btSize - $i) . ' (';
            if (!empty($class)) {

            }
            else {

            }
            $title .= sprintf('%s; %s', basename($file), $line) . ')';

            $elementId = sprintf('backTrace%s', $i);

            $newAcc = '<li class="accordion-navigation">';

            $newAcc .= '<a href="#' . $elementId . '">' . htmlentities($title) . '</a>';

            $content = '<table class="nameValueTable">';
            {
                $content .= '<tbody>';
                {
                    // file
                    $content .= '<tr>';
                    {
                        $content .= '<td class="valueName"><strong>File</strong></td>';
                        $content .= '<td class="valueValue">' . htmlentities($file) . '</td>';
                    }
                    $content .= '</tr>';

                    // line
                    $content .= '<tr>';
                    {
                        $content .= '<td class="valueName"><strong>Line</strong></td>';
                        $content .= '<td class="valueValue">' . htmlentities($line) . '</td>';
                    }
                    $content .= '</tr>';

                    if (!empty($class)) {
                        $content .= '<tr>';
                        {
                            $content .= '<td class="valueName"><strong>Class</strong></td>';
                            $content .= '<td class="valueValue">' . htmlentities($class) . '</td>';
                        }
                        $content .= '</tr>';

                        if (!empty($funcType)) {
                            $method = $func;

                            if (!empty($method)) {
                                $content .= '<tr>';
                                {
                                    $content .= '<td class="valueName"><strong>Method</strong></td>';
                                    $content .= '<td class="valueValue">' . htmlentities($method) . '</td>';
                                }
                                $content .= '</tr>';
                            }
                        }
                    }
                    else {
                        if (!empty($func)) {
                            $content .= '<tr>';
                            {
                                $content .= '<td class="valueName"><strong>Function</strong></td>';
                                $content .= '<td class="valueValue">' . htmlentities($func) . '</td>';
                            }
                            $content .= '</tr>';
                        }
                    }

                    if (is_array($funcArgs)) {
                        if (!empty($funcArgs)) {
                            // try find reflector by function / method
                            $reflector = null;
                            if (!empty($func)) {
                                if (empty($class)) {
                                    $reflector = new \ReflectionFunction($func);
                                }
                                else {
                                    $rc = new \ReflectionClass($class);
                                    foreach ($rc->getMethods() as $m) {
                                        if ($m->getName() == $func) {
                                            $reflector = $m;
                                            break;
                                        }
                                    }
                                }
                            }

                            $content .= '<tr>';
                            {
                                $argList = '<table class="argumentTable">';
                                {
                                    $argList .= '<thead>';
                                    $argList .= '<tr>';
                                    $argList .= '<th class="argName">Name</th>';
                                    $argList .= '<th class="argType">Type</th>';
                                    $argList .= '<th>Value</th>';
                                    $argList .= '</tr>';
                                    $argList .= '</thead>';

                                    $argList .= '<tbody>';
                                    foreach ($funcArgs as $faNr => $faVal) {
                                        $faName = '#'. trim($faNr + 1);
                                        if (!is_null($reflector)) {
                                            // try find parameter

                                            $params = $reflector->getParameters();
                                            if (isset($params[$faNr])) {
                                                $faName = '$' . $params[$faNr]->getName();
                                            }
                                        }

                                        $fsValType = '(null)';
                                        if (!is_null($faVal)) {
                                            if (is_object($faVal)) {
                                                $fsValType = sprintf('object (%s)', get_class($faVal));
                                                if (method_exists($faVal, '__toString')) {
                                                    $fsValType = $faVal->__toString();
                                                }
                                            }
                                            else {
                                                $fsValType = gettype($faVal);
                                            }
                                        }

                                        $argList .= '<tr>';
                                        $argList .= '<td>' . htmlentities($faName) . '</td>';
                                        $argList .= '<td>' . htmlentities($fsValType) . '</td>';
                                        $argList .= '<td><pre>' . self::parseForHtmlOutput(self::valueToString($faVal, true)) . '</pre></td>';
                                        $argList .= '</tr>';
                                    }
                                    $argList .= '</tbody>';
                                }
                                $argList .= '</table>';

                                $content .= '<td class="valueName"><strong>Arguments</strong></td>';
                                $content .= '<td class="valueValue">' . $argList . '</td>';
                            }
                            $content .= '</tr>';
                        }
                    }
                }
                $content .= '</tbody>';
            }
            $content .= '</table>';

            $newAcc .= '<div id="' . $elementId . '" class="content">' . $content . '</div>';

            $newAcc .= '</li>';

            $result .= $newAcc;
        }

        $result .= '</ul>';

        return $result;
    }

    private static function getCheckVarNamePredicate($namesOrPattern) {
        if (is_array($namesOrPattern) || ($namesOrPattern instanceof \Traversable)) {
            // list of variable names

            return function($name) use ($namesOrPattern) {
                foreach ($namesOrPattern as $varName) {
                    if ($name == phpDeeBuk::getVarName($varName)) {
                        // found
                        return true;
                    }
                }

                return false;
            };
        }
        else {
            // regex patter

            return function($name) use ($namesOrPattern) {
                return 1 === preg_match($namesOrPattern, $name);
            };
        }

        return false;
    }

    /**
     * Returns the current content of the console buffer
     * for use in HTML document.
     *
     * @param bool $withTags Wirh tags or not.
     *
     * @return string The console buffer.
     */
    public function getConsoleHtml($withTags = false) {
        $result = '';
        foreach ($this->_console as $item) {
            $htmlToAppend = '<span';

            // define styles
            $styles = array();
            if (!empty($item->bgColor)) {
                $styles[] = "background-color: {$item->bgColor};";
            }
            if (!empty($item->fgColor)) {
                $styles[] = "color: {$item->fgColor};";
            }
            if ($item->isBold) {
                $styles[] = "font-weight: bold;";
            }
            if ($item->isUnderline) {
                $styles[] = "text-decoration: underline;";
            }

            if (!empty($styles)) {
                $htmlToAppend .= ' style="' . implode(' ', $styles) . '"';
            }
            $htmlToAppend .= '>';

            // value
            $htmlToAppend .= self::parseForHtmlOutput($item->value);

            $htmlToAppend .= '</span>';

            $result .= $htmlToAppend;
        }

        if ($withTags) {
            return "<pre class=\"debuggerConsole\">{$result}</pre>";
        }

        return $result;
    }

    /**
     * Gets the CSS code for the output page.
     *
     * @param bool $withHtmlTag With HTML tags or not.
     *
     * @return string The CSS code.
     */
    public function getCss($withHtmlTag = false) {
        $result = '';

        if ($withHtmlTag) {
            $result .= '
<style type="text/css">
';
        }

        $result .= '
section {
    padding: 0.5em !important;
}

table td {
    vertical-align: top;
}

table th {
    vertical-align: middle;
}

table.memberTable, table.aboutTable, table.nameValueTable, table.argumentTable {
    width: 100%;
}

table.memberTable th.memberName,
table.aboutTable td.colLeft,
table.nameValueTable td.valueName {
    width: 15em;
}

table.argumentTable th.argName,
table.argumentTable th.argType {
    width: 7em;
}

pre.debuggerConsole {
    background-color: #000;
    color: #fff;
    height: 80%;
    padding: 0.5em;
}

.testFailed {
    background-color: #cf2a0e !important;
    color: #fff !important;
    font-weight: bold !important;
}

.testSucceeded {
    background-color: #43ac6a !important;
    color: #fff !important;
}
';

        if ($withHtmlTag) {
            $result .= '
</style>
';
        }

        return $result;
    }

    private static function getCssColor($cssColor) {
        $result = trim($cssColor);
        if ('' == $result) {
            $result = null;
        }

        if (!empty($result)) {
            if (1 === preg_match("/^(?:[0-9a-fA-F]{3}){1,2}$/", $result)) {
                // hex color without leading #
                $result = '#' . $result;
            }
        }

        return $result;
    }

    private function getDumpHtml($val) {
        $result = '';

        $valueType = '(null)';
        if (!is_null($val)) {
            if (is_object($val)) {
                $valueType = get_class($val);
            }
            else {
                $valueType = gettype($val);
            }
        }

        $result .= '<h4>About</h4>';
        {
            $result .= '<table class="aboutTable">';

            $result .= '<tbody>';

            $result .= '<tr>';
            $result .= '<td class="colLeft"><strong>Type</strong></td>';
            $result .= '<td class="colRight">' . htmlentities($valueType) . '</td>';
            $result .= '</tr>';

            if (is_object($val)) {
                $reflector = new \ReflectionObject($val);

                $result .= '<tr>';
                $result .= '<td class="colLeft"><strong>File</strong></td>';
                $result .= '<td class="colRight">' . htmlentities($reflector->getFileName()) . '</td>';
                $result .= '</tr>';
            }

            $result .= '</tbody>';

            $result .= '</table>';
        }

        // output value
        $result .= '<h4>Dump</h4>';
        {
            $export = var_export($val, true);
            $result .= '<pre>' . self::parseForHtmlOutput($export) . '</pre>';
        }

        return $result;
    }

    /**
     * Returns the Foundation CSS code.
     *
     * @param bool $withHtmlTag With script HTML tag or not.
     *
     * @return string The CSS code.
     */
    public static function getFoundationCss($withHtmlTag = false) {
        $result = '';

        if ($withHtmlTag) {
            $result .= '
<style type="text/css">
';
        }

        $result .= 'meta.foundation-version{font-family:"/5.5.2/"}meta.foundation-mq-small{font-family:"/only screen/";width:0}meta.foundation-mq-small-only{font-family:"/only screen and (max-width: 40em)/";width:0}meta.foundation-mq-medium{font-family:"/only screen and (min-width:40.0625em)/";width:40.0625em}meta.foundation-mq-medium-only{font-family:"/only screen and (min-width:40.0625em) and (max-width:64em)/";width:40.0625em}meta.foundation-mq-large{font-family:"/only screen and (min-width:64.0625em)/";width:64.0625em}meta.foundation-mq-large-only{font-family:"/only screen and (min-width:64.0625em) and (max-width:90em)/";width:64.0625em}meta.foundation-mq-xlarge{font-family:"/only screen and (min-width:90.0625em)/";width:90.0625em}meta.foundation-mq-xlarge-only{font-family:"/only screen and (min-width:90.0625em) and (max-width:120em)/";width:90.0625em}meta.foundation-mq-xxlarge{font-family:"/only screen and (min-width:120.0625em)/";width:120.0625em}meta.foundation-data-attribute-namespace{font-family:false}html,body{height:100%}html{box-sizing:border-box}*,*:before,*:after{-webkit-box-sizing:inherit;-moz-box-sizing:inherit;box-sizing:inherit}html,body{font-size:100%}body{background:#fff;color:#222;cursor:auto;font-family:"Helvetica Neue",Helvetica,Roboto,Arial,sans-serif;font-style:normal;font-weight:normal;line-height:1.5;margin:0;padding:0;position:relative}a:hover{cursor:pointer}img{max-width:100%;height:auto}img{-ms-interpolation-mode:bicubic}#map_canvas img,#map_canvas embed,#map_canvas object,.map_canvas img,.map_canvas embed,.map_canvas object,.mqa-display img,.mqa-display embed,.mqa-display object{max-width:none !important}.left{float:left !important}.right{float:right !important}.clearfix:before,.clearfix:after{content:" ";display:table}.clearfix:after{clear:both}.hide{display:none}.invisible{visibility:hidden}.antialiased{-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}img{display:inline-block;vertical-align:middle}textarea{height:auto;min-height:50px}select{width:100%}.row{margin:0 auto;max-width:62.5rem;width:100%}.row:before,.row:after{content:" ";display:table}.row:after{clear:both}.row.collapse>.column,.row.collapse>.columns{padding-left:0;padding-right:0}.row.collapse .row{margin-left:0;margin-right:0}.row .row{margin:0 -0.9375rem;max-width:none;width:auto}.row .row:before,.row .row:after{content:" ";display:table}.row .row:after{clear:both}.row .row.collapse{margin:0;max-width:none;width:auto}.row .row.collapse:before,.row .row.collapse:after{content:" ";display:table}.row .row.collapse:after{clear:both}.column,.columns{padding-left:0.9375rem;padding-right:0.9375rem;width:100%;float:left}.column+.column:last-child,.columns+.column:last-child,.column+.columns:last-child,.columns+.columns:last-child{float:right}.column+.column.end,.columns+.column.end,.column+.columns.end,.columns+.columns.end{float:left}@media only screen{.small-push-0{position:relative;left:0;right:auto}.small-pull-0{position:relative;right:0;left:auto}.small-push-1{position:relative;left:8.33333%;right:auto}.small-pull-1{position:relative;right:8.33333%;left:auto}.small-push-2{position:relative;left:16.66667%;right:auto}.small-pull-2{position:relative;right:16.66667%;left:auto}.small-push-3{position:relative;left:25%;right:auto}.small-pull-3{position:relative;right:25%;left:auto}.small-push-4{position:relative;left:33.33333%;right:auto}.small-pull-4{position:relative;right:33.33333%;left:auto}.small-push-5{position:relative;left:41.66667%;right:auto}.small-pull-5{position:relative;right:41.66667%;left:auto}.small-push-6{position:relative;left:50%;right:auto}.small-pull-6{position:relative;right:50%;left:auto}.small-push-7{position:relative;left:58.33333%;right:auto}.small-pull-7{position:relative;right:58.33333%;left:auto}.small-push-8{position:relative;left:66.66667%;right:auto}.small-pull-8{position:relative;right:66.66667%;left:auto}.small-push-9{position:relative;left:75%;right:auto}.small-pull-9{position:relative;right:75%;left:auto}.small-push-10{position:relative;left:83.33333%;right:auto}.small-pull-10{position:relative;right:83.33333%;left:auto}.small-push-11{position:relative;left:91.66667%;right:auto}.small-pull-11{position:relative;right:91.66667%;left:auto}.column,.columns{position:relative;padding-left:0.9375rem;padding-right:0.9375rem;float:left}.small-1{width:8.33333%}.small-2{width:16.66667%}.small-3{width:25%}.small-4{width:33.33333%}.small-5{width:41.66667%}.small-6{width:50%}.small-7{width:58.33333%}.small-8{width:66.66667%}.small-9{width:75%}.small-10{width:83.33333%}.small-11{width:91.66667%}.small-12{width:100%}.small-offset-0{margin-left:0 !important}.small-offset-1{margin-left:8.33333% !important}.small-offset-2{margin-left:16.66667% !important}.small-offset-3{margin-left:25% !important}.small-offset-4{margin-left:33.33333% !important}.small-offset-5{margin-left:41.66667% !important}.small-offset-6{margin-left:50% !important}.small-offset-7{margin-left:58.33333% !important}.small-offset-8{margin-left:66.66667% !important}.small-offset-9{margin-left:75% !important}.small-offset-10{margin-left:83.33333% !important}.small-offset-11{margin-left:91.66667% !important}.small-reset-order{float:left;left:auto;margin-left:0;margin-right:0;right:auto}.column.small-centered,.columns.small-centered{margin-left:auto;margin-right:auto;float:none}.column.small-uncentered,.columns.small-uncentered{float:left;margin-left:0;margin-right:0}.column.small-centered:last-child,.columns.small-centered:last-child{float:none}.column.small-uncentered:last-child,.columns.small-uncentered:last-child{float:left}.column.small-uncentered.opposite,.columns.small-uncentered.opposite{float:right}.row.small-collapse>.column,.row.small-collapse>.columns{padding-left:0;padding-right:0}.row.small-collapse .row{margin-left:0;margin-right:0}.row.small-uncollapse>.column,.row.small-uncollapse>.columns{padding-left:0.9375rem;padding-right:0.9375rem;float:left}}@media only screen and (min-width: 40.0625em){.medium-push-0{position:relative;left:0;right:auto}.medium-pull-0{position:relative;right:0;left:auto}.medium-push-1{position:relative;left:8.33333%;right:auto}.medium-pull-1{position:relative;right:8.33333%;left:auto}.medium-push-2{position:relative;left:16.66667%;right:auto}.medium-pull-2{position:relative;right:16.66667%;left:auto}.medium-push-3{position:relative;left:25%;right:auto}.medium-pull-3{position:relative;right:25%;left:auto}.medium-push-4{position:relative;left:33.33333%;right:auto}.medium-pull-4{position:relative;right:33.33333%;left:auto}.medium-push-5{position:relative;left:41.66667%;right:auto}.medium-pull-5{position:relative;right:41.66667%;left:auto}.medium-push-6{position:relative;left:50%;right:auto}.medium-pull-6{position:relative;right:50%;left:auto}.medium-push-7{position:relative;left:58.33333%;right:auto}.medium-pull-7{position:relative;right:58.33333%;left:auto}.medium-push-8{position:relative;left:66.66667%;right:auto}.medium-pull-8{position:relative;right:66.66667%;left:auto}.medium-push-9{position:relative;left:75%;right:auto}.medium-pull-9{position:relative;right:75%;left:auto}.medium-push-10{position:relative;left:83.33333%;right:auto}.medium-pull-10{position:relative;right:83.33333%;left:auto}.medium-push-11{position:relative;left:91.66667%;right:auto}.medium-pull-11{position:relative;right:91.66667%;left:auto}.column,.columns{position:relative;padding-left:0.9375rem;padding-right:0.9375rem;float:left}.medium-1{width:8.33333%}.medium-2{width:16.66667%}.medium-3{width:25%}.medium-4{width:33.33333%}.medium-5{width:41.66667%}.medium-6{width:50%}.medium-7{width:58.33333%}.medium-8{width:66.66667%}.medium-9{width:75%}.medium-10{width:83.33333%}.medium-11{width:91.66667%}.medium-12{width:100%}.medium-offset-0{margin-left:0 !important}.medium-offset-1{margin-left:8.33333% !important}.medium-offset-2{margin-left:16.66667% !important}.medium-offset-3{margin-left:25% !important}.medium-offset-4{margin-left:33.33333% !important}.medium-offset-5{margin-left:41.66667% !important}.medium-offset-6{margin-left:50% !important}.medium-offset-7{margin-left:58.33333% !important}.medium-offset-8{margin-left:66.66667% !important}.medium-offset-9{margin-left:75% !important}.medium-offset-10{margin-left:83.33333% !important}.medium-offset-11{margin-left:91.66667% !important}.medium-reset-order{float:left;left:auto;margin-left:0;margin-right:0;right:auto}.column.medium-centered,.columns.medium-centered{margin-left:auto;margin-right:auto;float:none}.column.medium-uncentered,.columns.medium-uncentered{float:left;margin-left:0;margin-right:0}.column.medium-centered:last-child,.columns.medium-centered:last-child{float:none}.column.medium-uncentered:last-child,.columns.medium-uncentered:last-child{float:left}.column.medium-uncentered.opposite,.columns.medium-uncentered.opposite{float:right}.row.medium-collapse>.column,.row.medium-collapse>.columns{padding-left:0;padding-right:0}.row.medium-collapse .row{margin-left:0;margin-right:0}.row.medium-uncollapse>.column,.row.medium-uncollapse>.columns{padding-left:0.9375rem;padding-right:0.9375rem;float:left}.push-0{position:relative;left:0;right:auto}.pull-0{position:relative;right:0;left:auto}.push-1{position:relative;left:8.33333%;right:auto}.pull-1{position:relative;right:8.33333%;left:auto}.push-2{position:relative;left:16.66667%;right:auto}.pull-2{position:relative;right:16.66667%;left:auto}.push-3{position:relative;left:25%;right:auto}.pull-3{position:relative;right:25%;left:auto}.push-4{position:relative;left:33.33333%;right:auto}.pull-4{position:relative;right:33.33333%;left:auto}.push-5{position:relative;left:41.66667%;right:auto}.pull-5{position:relative;right:41.66667%;left:auto}.push-6{position:relative;left:50%;right:auto}.pull-6{position:relative;right:50%;left:auto}.push-7{position:relative;left:58.33333%;right:auto}.pull-7{position:relative;right:58.33333%;left:auto}.push-8{position:relative;left:66.66667%;right:auto}.pull-8{position:relative;right:66.66667%;left:auto}.push-9{position:relative;left:75%;right:auto}.pull-9{position:relative;right:75%;left:auto}.push-10{position:relative;left:83.33333%;right:auto}.pull-10{position:relative;right:83.33333%;left:auto}.push-11{position:relative;left:91.66667%;right:auto}.pull-11{position:relative;right:91.66667%;left:auto}}@media only screen and (min-width: 64.0625em){.large-push-0{position:relative;left:0;right:auto}.large-pull-0{position:relative;right:0;left:auto}.large-push-1{position:relative;left:8.33333%;right:auto}.large-pull-1{position:relative;right:8.33333%;left:auto}.large-push-2{position:relative;left:16.66667%;right:auto}.large-pull-2{position:relative;right:16.66667%;left:auto}.large-push-3{position:relative;left:25%;right:auto}.large-pull-3{position:relative;right:25%;left:auto}.large-push-4{position:relative;left:33.33333%;right:auto}.large-pull-4{position:relative;right:33.33333%;left:auto}.large-push-5{position:relative;left:41.66667%;right:auto}.large-pull-5{position:relative;right:41.66667%;left:auto}.large-push-6{position:relative;left:50%;right:auto}.large-pull-6{position:relative;right:50%;left:auto}.large-push-7{position:relative;left:58.33333%;right:auto}.large-pull-7{position:relative;right:58.33333%;left:auto}.large-push-8{position:relative;left:66.66667%;right:auto}.large-pull-8{position:relative;right:66.66667%;left:auto}.large-push-9{position:relative;left:75%;right:auto}.large-pull-9{position:relative;right:75%;left:auto}.large-push-10{position:relative;left:83.33333%;right:auto}.large-pull-10{position:relative;right:83.33333%;left:auto}.large-push-11{position:relative;left:91.66667%;right:auto}.large-pull-11{position:relative;right:91.66667%;left:auto}.column,.columns{position:relative;padding-left:0.9375rem;padding-right:0.9375rem;float:left}.large-1{width:8.33333%}.large-2{width:16.66667%}.large-3{width:25%}.large-4{width:33.33333%}.large-5{width:41.66667%}.large-6{width:50%}.large-7{width:58.33333%}.large-8{width:66.66667%}.large-9{width:75%}.large-10{width:83.33333%}.large-11{width:91.66667%}.large-12{width:100%}.large-offset-0{margin-left:0 !important}.large-offset-1{margin-left:8.33333% !important}.large-offset-2{margin-left:16.66667% !important}.large-offset-3{margin-left:25% !important}.large-offset-4{margin-left:33.33333% !important}.large-offset-5{margin-left:41.66667% !important}.large-offset-6{margin-left:50% !important}.large-offset-7{margin-left:58.33333% !important}.large-offset-8{margin-left:66.66667% !important}.large-offset-9{margin-left:75% !important}.large-offset-10{margin-left:83.33333% !important}.large-offset-11{margin-left:91.66667% !important}.large-reset-order{float:left;left:auto;margin-left:0;margin-right:0;right:auto}.column.large-centered,.columns.large-centered{margin-left:auto;margin-right:auto;float:none}.column.large-uncentered,.columns.large-uncentered{float:left;margin-left:0;margin-right:0}.column.large-centered:last-child,.columns.large-centered:last-child{float:none}.column.large-uncentered:last-child,.columns.large-uncentered:last-child{float:left}.column.large-uncentered.opposite,.columns.large-uncentered.opposite{float:right}.row.large-collapse>.column,.row.large-collapse>.columns{padding-left:0;padding-right:0}.row.large-collapse .row{margin-left:0;margin-right:0}.row.large-uncollapse>.column,.row.large-uncollapse>.columns{padding-left:0.9375rem;padding-right:0.9375rem;float:left}.push-0{position:relative;left:0;right:auto}.pull-0{position:relative;right:0;left:auto}.push-1{position:relative;left:8.33333%;right:auto}.pull-1{position:relative;right:8.33333%;left:auto}.push-2{position:relative;left:16.66667%;right:auto}.pull-2{position:relative;right:16.66667%;left:auto}.push-3{position:relative;left:25%;right:auto}.pull-3{position:relative;right:25%;left:auto}.push-4{position:relative;left:33.33333%;right:auto}.pull-4{position:relative;right:33.33333%;left:auto}.push-5{position:relative;left:41.66667%;right:auto}.pull-5{position:relative;right:41.66667%;left:auto}.push-6{position:relative;left:50%;right:auto}.pull-6{position:relative;right:50%;left:auto}.push-7{position:relative;left:58.33333%;right:auto}.pull-7{position:relative;right:58.33333%;left:auto}.push-8{position:relative;left:66.66667%;right:auto}.pull-8{position:relative;right:66.66667%;left:auto}.push-9{position:relative;left:75%;right:auto}.pull-9{position:relative;right:75%;left:auto}.push-10{position:relative;left:83.33333%;right:auto}.pull-10{position:relative;right:83.33333%;left:auto}.push-11{position:relative;left:91.66667%;right:auto}.pull-11{position:relative;right:91.66667%;left:auto}}button,.button{-webkit-appearance:none;-moz-appearance:none;border-radius:0;border-style:solid;border-width:0;cursor:pointer;font-family:"Helvetica Neue",Helvetica,Roboto,Arial,sans-serif;font-weight:normal;line-height:normal;margin:0 0 1.25rem;position:relative;text-align:center;text-decoration:none;display:inline-block;padding:1rem 2rem 1.0625rem 2rem;font-size:1rem;background-color:#008CBA;border-color:#007095;color:#fff;transition:background-color 300ms ease-out}button:hover,button:focus,.button:hover,.button:focus{background-color:#007095}button:hover,button:focus,.button:hover,.button:focus{color:#fff}button.secondary,.button.secondary{background-color:#e7e7e7;border-color:#b9b9b9;color:#333}button.secondary:hover,button.secondary:focus,.button.secondary:hover,.button.secondary:focus{background-color:#b9b9b9}button.secondary:hover,button.secondary:focus,.button.secondary:hover,.button.secondary:focus{color:#333}button.success,.button.success{background-color:#43AC6A;border-color:#368a55;color:#fff}button.success:hover,button.success:focus,.button.success:hover,.button.success:focus{background-color:#368a55}button.success:hover,button.success:focus,.button.success:hover,.button.success:focus{color:#fff}button.alert,.button.alert{background-color:#f04124;border-color:#cf2a0e;color:#fff}button.alert:hover,button.alert:focus,.button.alert:hover,.button.alert:focus{background-color:#cf2a0e}button.alert:hover,button.alert:focus,.button.alert:hover,.button.alert:focus{color:#fff}button.warning,.button.warning{background-color:#f08a24;border-color:#cf6e0e;color:#fff}button.warning:hover,button.warning:focus,.button.warning:hover,.button.warning:focus{background-color:#cf6e0e}button.warning:hover,button.warning:focus,.button.warning:hover,.button.warning:focus{color:#fff}button.info,.button.info{background-color:#a0d3e8;border-color:#61b6d9;color:#333}button.info:hover,button.info:focus,.button.info:hover,.button.info:focus{background-color:#61b6d9}button.info:hover,button.info:focus,.button.info:hover,.button.info:focus{color:#fff}button.large,.button.large{padding:1.125rem 2.25rem 1.1875rem 2.25rem;font-size:1.25rem}button.small,.button.small{padding:0.875rem 1.75rem 0.9375rem 1.75rem;font-size:0.8125rem}button.tiny,.button.tiny{padding:0.625rem 1.25rem 0.6875rem 1.25rem;font-size:0.6875rem}button.expand,.button.expand{padding-left:0;padding-right:0;width:100%}button.left-align,.button.left-align{text-align:left;text-indent:0.75rem}button.right-align,.button.right-align{text-align:right;padding-right:0.75rem}button.radius,.button.radius{border-radius:3px}button.round,.button.round{border-radius:1000px}button.disabled,button[disabled],.button.disabled,.button[disabled]{background-color:#008CBA;border-color:#007095;color:#fff;box-shadow:none;cursor:default;opacity:0.7}button.disabled:hover,button.disabled:focus,button[disabled]:hover,button[disabled]:focus,.button.disabled:hover,.button.disabled:focus,.button[disabled]:hover,.button[disabled]:focus{background-color:#007095}button.disabled:hover,button.disabled:focus,button[disabled]:hover,button[disabled]:focus,.button.disabled:hover,.button.disabled:focus,.button[disabled]:hover,.button[disabled]:focus{color:#fff}button.disabled:hover,button.disabled:focus,button[disabled]:hover,button[disabled]:focus,.button.disabled:hover,.button.disabled:focus,.button[disabled]:hover,.button[disabled]:focus{background-color:#008CBA}button.disabled.secondary,button[disabled].secondary,.button.disabled.secondary,.button[disabled].secondary{background-color:#e7e7e7;border-color:#b9b9b9;color:#333;box-shadow:none;cursor:default;opacity:0.7}button.disabled.secondary:hover,button.disabled.secondary:focus,button[disabled].secondary:hover,button[disabled].secondary:focus,.button.disabled.secondary:hover,.button.disabled.secondary:focus,.button[disabled].secondary:hover,.button[disabled].secondary:focus{background-color:#b9b9b9}button.disabled.secondary:hover,button.disabled.secondary:focus,button[disabled].secondary:hover,button[disabled].secondary:focus,.button.disabled.secondary:hover,.button.disabled.secondary:focus,.button[disabled].secondary:hover,.button[disabled].secondary:focus{color:#333}button.disabled.secondary:hover,button.disabled.secondary:focus,button[disabled].secondary:hover,button[disabled].secondary:focus,.button.disabled.secondary:hover,.button.disabled.secondary:focus,.button[disabled].secondary:hover,.button[disabled].secondary:focus{background-color:#e7e7e7}button.disabled.success,button[disabled].success,.button.disabled.success,.button[disabled].success{background-color:#43AC6A;border-color:#368a55;color:#fff;box-shadow:none;cursor:default;opacity:0.7}button.disabled.success:hover,button.disabled.success:focus,button[disabled].success:hover,button[disabled].success:focus,.button.disabled.success:hover,.button.disabled.success:focus,.button[disabled].success:hover,.button[disabled].success:focus{background-color:#368a55}button.disabled.success:hover,button.disabled.success:focus,button[disabled].success:hover,button[disabled].success:focus,.button.disabled.success:hover,.button.disabled.success:focus,.button[disabled].success:hover,.button[disabled].success:focus{color:#fff}button.disabled.success:hover,button.disabled.success:focus,button[disabled].success:hover,button[disabled].success:focus,.button.disabled.success:hover,.button.disabled.success:focus,.button[disabled].success:hover,.button[disabled].success:focus{background-color:#43AC6A}button.disabled.alert,button[disabled].alert,.button.disabled.alert,.button[disabled].alert{background-color:#f04124;border-color:#cf2a0e;color:#fff;box-shadow:none;cursor:default;opacity:0.7}button.disabled.alert:hover,button.disabled.alert:focus,button[disabled].alert:hover,button[disabled].alert:focus,.button.disabled.alert:hover,.button.disabled.alert:focus,.button[disabled].alert:hover,.button[disabled].alert:focus{background-color:#cf2a0e}button.disabled.alert:hover,button.disabled.alert:focus,button[disabled].alert:hover,button[disabled].alert:focus,.button.disabled.alert:hover,.button.disabled.alert:focus,.button[disabled].alert:hover,.button[disabled].alert:focus{color:#fff}button.disabled.alert:hover,button.disabled.alert:focus,button[disabled].alert:hover,button[disabled].alert:focus,.button.disabled.alert:hover,.button.disabled.alert:focus,.button[disabled].alert:hover,.button[disabled].alert:focus{background-color:#f04124}button.disabled.warning,button[disabled].warning,.button.disabled.warning,.button[disabled].warning{background-color:#f08a24;border-color:#cf6e0e;color:#fff;box-shadow:none;cursor:default;opacity:0.7}button.disabled.warning:hover,button.disabled.warning:focus,button[disabled].warning:hover,button[disabled].warning:focus,.button.disabled.warning:hover,.button.disabled.warning:focus,.button[disabled].warning:hover,.button[disabled].warning:focus{background-color:#cf6e0e}button.disabled.warning:hover,button.disabled.warning:focus,button[disabled].warning:hover,button[disabled].warning:focus,.button.disabled.warning:hover,.button.disabled.warning:focus,.button[disabled].warning:hover,.button[disabled].warning:focus{color:#fff}button.disabled.warning:hover,button.disabled.warning:focus,button[disabled].warning:hover,button[disabled].warning:focus,.button.disabled.warning:hover,.button.disabled.warning:focus,.button[disabled].warning:hover,.button[disabled].warning:focus{background-color:#f08a24}button.disabled.info,button[disabled].info,.button.disabled.info,.button[disabled].info{background-color:#a0d3e8;border-color:#61b6d9;color:#333;box-shadow:none;cursor:default;opacity:0.7}button.disabled.info:hover,button.disabled.info:focus,button[disabled].info:hover,button[disabled].info:focus,.button.disabled.info:hover,.button.disabled.info:focus,.button[disabled].info:hover,.button[disabled].info:focus{background-color:#61b6d9}button.disabled.info:hover,button.disabled.info:focus,button[disabled].info:hover,button[disabled].info:focus,.button.disabled.info:hover,.button.disabled.info:focus,.button[disabled].info:hover,.button[disabled].info:focus{color:#fff}button.disabled.info:hover,button.disabled.info:focus,button[disabled].info:hover,button[disabled].info:focus,.button.disabled.info:hover,.button.disabled.info:focus,.button[disabled].info:hover,.button[disabled].info:focus{background-color:#a0d3e8}button::-moz-focus-inner{border:0;padding:0}@media only screen and (min-width: 40.0625em){button,.button{display:inline-block}}form{margin:0 0 1rem}form .row .row{margin:0 -0.5rem}form .row .row .column,form .row .row .columns{padding:0 0.5rem}form .row .row.collapse{margin:0}form .row .row.collapse .column,form .row .row.collapse .columns{padding:0}form .row .row.collapse input{-webkit-border-bottom-right-radius:0;-webkit-border-top-right-radius:0;border-bottom-right-radius:0;border-top-right-radius:0}form .row input.column,form .row input.columns,form .row textarea.column,form .row textarea.columns{padding-left:0.5rem}label{color:#4d4d4d;cursor:pointer;display:block;font-size:0.875rem;font-weight:normal;line-height:1.5;margin-bottom:0}label.right{float:none !important;text-align:right}label.inline{margin:0 0 1rem 0;padding:0.5625rem 0}label small{text-transform:capitalize;color:#676767}.prefix,.postfix{border-style:solid;border-width:1px;display:block;font-size:0.875rem;height:2.3125rem;line-height:2.3125rem;overflow:visible;padding-bottom:0;padding-top:0;position:relative;text-align:center;width:100%;z-index:2}.postfix.button{border-color:true}.prefix.button{border:none;padding-left:0;padding-right:0;padding-bottom:0;padding-top:0;text-align:center}.prefix.button.radius{border-radius:0;-webkit-border-bottom-left-radius:3px;-webkit-border-top-left-radius:3px;border-bottom-left-radius:3px;border-top-left-radius:3px}.postfix.button.radius{border-radius:0;-webkit-border-bottom-right-radius:3px;-webkit-border-top-right-radius:3px;border-bottom-right-radius:3px;border-top-right-radius:3px}.prefix.button.round{border-radius:0;-webkit-border-bottom-left-radius:1000px;-webkit-border-top-left-radius:1000px;border-bottom-left-radius:1000px;border-top-left-radius:1000px}.postfix.button.round{border-radius:0;-webkit-border-bottom-right-radius:1000px;-webkit-border-top-right-radius:1000px;border-bottom-right-radius:1000px;border-top-right-radius:1000px}span.prefix,label.prefix{background:#f2f2f2;border-right:none;color:#333;border-color:#ccc}span.postfix,label.postfix{background:#f2f2f2;color:#333;border-color:#ccc}input[type="text"],input[type="password"],input[type="date"],input[type="datetime"],input[type="datetime-local"],input[type="month"],input[type="week"],input[type="email"],input[type="number"],input[type="search"],input[type="tel"],input[type="time"],input[type="url"],input[type="color"],textarea{-webkit-appearance:none;-moz-appearance:none;border-radius:0;background-color:#fff;border-style:solid;border-width:1px;border-color:#ccc;box-shadow:inset 0 1px 2px rgba(0,0,0,0.1);color:rgba(0,0,0,0.75);display:block;font-family:inherit;font-size:0.875rem;height:2.3125rem;margin:0 0 1rem 0;padding:0.5rem;width:100%;-webkit-box-sizing:border-box;-moz-box-sizing:border-box;box-sizing:border-box;-webkit-transition:border-color 0.15s linear,background 0.15s linear;-moz-transition:border-color 0.15s linear,background 0.15s linear;-ms-transition:border-color 0.15s linear,background 0.15s linear;-o-transition:border-color 0.15s linear,background 0.15s linear;transition:border-color 0.15s linear,background 0.15s linear}input[type="text"]:focus,input[type="password"]:focus,input[type="date"]:focus,input[type="datetime"]:focus,input[type="datetime-local"]:focus,input[type="month"]:focus,input[type="week"]:focus,input[type="email"]:focus,input[type="number"]:focus,input[type="search"]:focus,input[type="tel"]:focus,input[type="time"]:focus,input[type="url"]:focus,input[type="color"]:focus,textarea:focus{background:#fafafa;border-color:#999;outline:none}input[type="text"]:disabled,input[type="password"]:disabled,input[type="date"]:disabled,input[type="datetime"]:disabled,input[type="datetime-local"]:disabled,input[type="month"]:disabled,input[type="week"]:disabled,input[type="email"]:disabled,input[type="number"]:disabled,input[type="search"]:disabled,input[type="tel"]:disabled,input[type="time"]:disabled,input[type="url"]:disabled,input[type="color"]:disabled,textarea:disabled{background-color:#ddd;cursor:default}input[type="text"][disabled],input[type="text"][readonly],fieldset[disabled] input[type="text"],input[type="password"][disabled],input[type="password"][readonly],fieldset[disabled] input[type="password"],input[type="date"][disabled],input[type="date"][readonly],fieldset[disabled] input[type="date"],input[type="datetime"][disabled],input[type="datetime"][readonly],fieldset[disabled] input[type="datetime"],input[type="datetime-local"][disabled],input[type="datetime-local"][readonly],fieldset[disabled] input[type="datetime-local"],input[type="month"][disabled],input[type="month"][readonly],fieldset[disabled] input[type="month"],input[type="week"][disabled],input[type="week"][readonly],fieldset[disabled] input[type="week"],input[type="email"][disabled],input[type="email"][readonly],fieldset[disabled] input[type="email"],input[type="number"][disabled],input[type="number"][readonly],fieldset[disabled] input[type="number"],input[type="search"][disabled],input[type="search"][readonly],fieldset[disabled] input[type="search"],input[type="tel"][disabled],input[type="tel"][readonly],fieldset[disabled] input[type="tel"],input[type="time"][disabled],input[type="time"][readonly],fieldset[disabled] input[type="time"],input[type="url"][disabled],input[type="url"][readonly],fieldset[disabled] input[type="url"],input[type="color"][disabled],input[type="color"][readonly],fieldset[disabled] input[type="color"],textarea[disabled],textarea[readonly],fieldset[disabled] textarea{background-color:#ddd;cursor:default}input[type="text"].radius,input[type="password"].radius,input[type="date"].radius,input[type="datetime"].radius,input[type="datetime-local"].radius,input[type="month"].radius,input[type="week"].radius,input[type="email"].radius,input[type="number"].radius,input[type="search"].radius,input[type="tel"].radius,input[type="time"].radius,input[type="url"].radius,input[type="color"].radius,textarea.radius{border-radius:3px}form .row .prefix-radius.row.collapse input,form .row .prefix-radius.row.collapse textarea,form .row .prefix-radius.row.collapse select,form .row .prefix-radius.row.collapse button{border-radius:0;-webkit-border-bottom-right-radius:3px;-webkit-border-top-right-radius:3px;border-bottom-right-radius:3px;border-top-right-radius:3px}form .row .prefix-radius.row.collapse .prefix{border-radius:0;-webkit-border-bottom-left-radius:3px;-webkit-border-top-left-radius:3px;border-bottom-left-radius:3px;border-top-left-radius:3px}form .row .postfix-radius.row.collapse input,form .row .postfix-radius.row.collapse textarea,form .row .postfix-radius.row.collapse select,form .row .postfix-radius.row.collapse button{border-radius:0;-webkit-border-bottom-left-radius:3px;-webkit-border-top-left-radius:3px;border-bottom-left-radius:3px;border-top-left-radius:3px}form .row .postfix-radius.row.collapse .postfix{border-radius:0;-webkit-border-bottom-right-radius:3px;-webkit-border-top-right-radius:3px;border-bottom-right-radius:3px;border-top-right-radius:3px}form .row .prefix-round.row.collapse input,form .row .prefix-round.row.collapse textarea,form .row .prefix-round.row.collapse select,form .row .prefix-round.row.collapse button{border-radius:0;-webkit-border-bottom-right-radius:1000px;-webkit-border-top-right-radius:1000px;border-bottom-right-radius:1000px;border-top-right-radius:1000px}form .row .prefix-round.row.collapse .prefix{border-radius:0;-webkit-border-bottom-left-radius:1000px;-webkit-border-top-left-radius:1000px;border-bottom-left-radius:1000px;border-top-left-radius:1000px}form .row .postfix-round.row.collapse input,form .row .postfix-round.row.collapse textarea,form .row .postfix-round.row.collapse select,form .row .postfix-round.row.collapse button{border-radius:0;-webkit-border-bottom-left-radius:1000px;-webkit-border-top-left-radius:1000px;border-bottom-left-radius:1000px;border-top-left-radius:1000px}form .row .postfix-round.row.collapse .postfix{border-radius:0;-webkit-border-bottom-right-radius:1000px;-webkit-border-top-right-radius:1000px;border-bottom-right-radius:1000px;border-top-right-radius:1000px}input[type="submit"]{-webkit-appearance:none;-moz-appearance:none;border-radius:0}textarea[rows]{height:auto}textarea{max-width:100%}::-webkit-input-placeholder{color:#ccc}:-moz-placeholder{color:#ccc}::-moz-placeholder{color:#ccc}:-ms-input-placeholder{color:#ccc}select{-webkit-appearance:none !important;-moz-appearance:none !important;background-color:#FAFAFA;border-radius:0;background-image:url(data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZlcnNpb249IjEuMSIgeD0iMTJweCIgeT0iMHB4IiB3aWR0aD0iMjRweCIgaGVpZ2h0PSIzcHgiIHZpZXdCb3g9IjAgMCA2IDMiIGVuYWJsZS1iYWNrZ3JvdW5kPSJuZXcgMCAwIDYgMyIgeG1sOnNwYWNlPSJwcmVzZXJ2ZSI+PHBvbHlnb24gcG9pbnRzPSI1Ljk5MiwwIDIuOTkyLDMgLTAuMDA4LDAgIi8+PC9zdmc+);background-position:100% center;background-repeat:no-repeat;border-style:solid;border-width:1px;border-color:#ccc;color:rgba(0,0,0,0.75);font-family:inherit;font-size:0.875rem;line-height:normal;padding:0.5rem;border-radius:0;height:2.3125rem}select::-ms-expand{display:none}select.radius{border-radius:3px}select:hover{background-color:#f3f3f3;border-color:#999}select:disabled{background-color:#ddd;cursor:default}select[multiple]{height:auto}input[type="file"],input[type="checkbox"],input[type="radio"],select{margin:0 0 1rem 0}input[type="checkbox"]+label,input[type="radio"]+label{display:inline-block;margin-left:0.5rem;margin-right:1rem;margin-bottom:0;vertical-align:baseline}input[type="file"]{width:100%}fieldset{border:1px solid #ddd;margin:1.125rem 0;padding:1.25rem}fieldset legend{background:#fff;font-weight:bold;margin-left:-0.1875rem;margin:0;padding:0 0.1875rem}[data-abide] .error small.error,[data-abide] .error span.error,[data-abide] span.error,[data-abide] small.error{display:block;font-size:0.75rem;font-style:italic;font-weight:normal;margin-bottom:1rem;margin-top:-1px;padding:0.375rem 0.5625rem 0.5625rem;background:#f04124;color:#fff}[data-abide] span.error,[data-abide] small.error{display:none}span.error,small.error{display:block;font-size:0.75rem;font-style:italic;font-weight:normal;margin-bottom:1rem;margin-top:-1px;padding:0.375rem 0.5625rem 0.5625rem;background:#f04124;color:#fff}.error input,.error textarea,.error select{margin-bottom:0}.error input[type="checkbox"],.error input[type="radio"]{margin-bottom:1rem}.error label,.error label.error{color:#f04124}.error small.error{display:block;font-size:0.75rem;font-style:italic;font-weight:normal;margin-bottom:1rem;margin-top:-1px;padding:0.375rem 0.5625rem 0.5625rem;background:#f04124;color:#fff}.error>label>small{background:transparent;color:#676767;display:inline;font-size:60%;font-style:normal;margin:0;padding:0;text-transform:capitalize}.error span.error-message{display:block}input.error,textarea.error,select.error{margin-bottom:0}label.error{color:#f04124}meta.foundation-mq-topbar{font-family:"/only screen and (min-width:40.0625em)/";width:40.0625em}.contain-to-grid{width:100%;background:#333}.contain-to-grid .top-bar{margin-bottom:0}.fixed{position:fixed;top:0;width:100%;z-index:99;left:0}.fixed.expanded:not(.top-bar){height:auto;max-height:100%;overflow-y:auto;width:100%}.fixed.expanded:not(.top-bar) .title-area{position:fixed;width:100%;z-index:99}.fixed.expanded:not(.top-bar) .top-bar-section{margin-top:2.8125rem;z-index:98}.top-bar{background:#333;height:2.8125rem;line-height:2.8125rem;margin-bottom:0;overflow:hidden;position:relative}.top-bar ul{list-style:none;margin-bottom:0}.top-bar .row{max-width:none}.top-bar form,.top-bar input,.top-bar select{margin-bottom:0}.top-bar input,.top-bar select{font-size:0.75rem;height:1.75rem;padding-bottom:.35rem;padding-top:.35rem}.top-bar .button,.top-bar button{font-size:0.75rem;margin-bottom:0;padding-bottom:0.4125rem;padding-top:0.4125rem}@media only screen and (max-width: 40em){.top-bar .button,.top-bar button{position:relative;top:-1px}}.top-bar .title-area{margin:0;position:relative}.top-bar .name{font-size:16px;height:2.8125rem;margin:0}.top-bar .name h1,.top-bar .name h2,.top-bar .name h3,.top-bar .name h4,.top-bar .name p,.top-bar .name span{font-size:1.0625rem;line-height:2.8125rem;margin:0}.top-bar .name h1 a,.top-bar .name h2 a,.top-bar .name h3 a,.top-bar .name h4 a,.top-bar .name p a,.top-bar .name span a{color:#fff;display:block;font-weight:normal;padding:0 0.9375rem;width:75%}.top-bar .toggle-topbar{position:absolute;right:0;top:0}.top-bar .toggle-topbar a{color:#fff;display:block;font-size:0.8125rem;font-weight:bold;height:2.8125rem;line-height:2.8125rem;padding:0 0.9375rem;position:relative;text-transform:uppercase}.top-bar .toggle-topbar.menu-icon{margin-top:-16px;top:50%}.top-bar .toggle-topbar.menu-icon a{color:#fff;height:34px;line-height:33px;padding:0 2.5rem 0 0.9375rem;position:relative}.top-bar .toggle-topbar.menu-icon a span::after{content:"";display:block;height:0;position:absolute;margin-top:-8px;top:50%;right:0.9375rem;box-shadow:0 0 0 1px #fff,0 7px 0 1px #fff,0 14px 0 1px #fff;width:16px}.top-bar .toggle-topbar.menu-icon a span:hover:after{box-shadow:0 0 0 1px "",0 7px 0 1px "",0 14px 0 1px ""}.top-bar.expanded{background:transparent;height:auto}.top-bar.expanded .title-area{background:#333}.top-bar.expanded .toggle-topbar a{color:#888}.top-bar.expanded .toggle-topbar a span::after{box-shadow:0 0 0 1px #888,0 7px 0 1px #888,0 14px 0 1px #888}@media screen and (-webkit-min-device-pixel-ratio: 0){.top-bar.expanded .top-bar-section .has-dropdown.moved>.dropdown,.top-bar.expanded .top-bar-section .dropdown{clip:initial}.top-bar.expanded .top-bar-section .has-dropdown:not(.moved)>ul{padding:0}}.top-bar-section{left:0;position:relative;width:auto;transition:left 300ms ease-out}.top-bar-section ul{display:block;font-size:16px;height:auto;margin:0;padding:0;width:100%}.top-bar-section .divider,.top-bar-section [role="separator"]{border-top:solid 1px #1a1a1a;clear:both;height:1px;width:100%}.top-bar-section ul li{background:#333}.top-bar-section ul li>a{color:#fff;display:block;font-family:"Helvetica Neue",Helvetica,Roboto,Arial,sans-serif;font-size:0.8125rem;font-weight:normal;padding-left:0.9375rem;padding:12px 0 12px 0.9375rem;text-transform:none;width:100%}.top-bar-section ul li>a.button{font-size:0.8125rem;padding-left:0.9375rem;padding-right:0.9375rem;background-color:#008CBA;border-color:#007095;color:#fff}.top-bar-section ul li>a.button:hover,.top-bar-section ul li>a.button:focus{background-color:#007095}.top-bar-section ul li>a.button:hover,.top-bar-section ul li>a.button:focus{color:#fff}.top-bar-section ul li>a.button.secondary{background-color:#e7e7e7;border-color:#b9b9b9;color:#333}.top-bar-section ul li>a.button.secondary:hover,.top-bar-section ul li>a.button.secondary:focus{background-color:#b9b9b9}.top-bar-section ul li>a.button.secondary:hover,.top-bar-section ul li>a.button.secondary:focus{color:#333}.top-bar-section ul li>a.button.success{background-color:#43AC6A;border-color:#368a55;color:#fff}.top-bar-section ul li>a.button.success:hover,.top-bar-section ul li>a.button.success:focus{background-color:#368a55}.top-bar-section ul li>a.button.success:hover,.top-bar-section ul li>a.button.success:focus{color:#fff}.top-bar-section ul li>a.button.alert{background-color:#f04124;border-color:#cf2a0e;color:#fff}.top-bar-section ul li>a.button.alert:hover,.top-bar-section ul li>a.button.alert:focus{background-color:#cf2a0e}.top-bar-section ul li>a.button.alert:hover,.top-bar-section ul li>a.button.alert:focus{color:#fff}.top-bar-section ul li>a.button.warning{background-color:#f08a24;border-color:#cf6e0e;color:#fff}.top-bar-section ul li>a.button.warning:hover,.top-bar-section ul li>a.button.warning:focus{background-color:#cf6e0e}.top-bar-section ul li>a.button.warning:hover,.top-bar-section ul li>a.button.warning:focus{color:#fff}.top-bar-section ul li>a.button.info{background-color:#a0d3e8;border-color:#61b6d9;color:#333}.top-bar-section ul li>a.button.info:hover,.top-bar-section ul li>a.button.info:focus{background-color:#61b6d9}.top-bar-section ul li>a.button.info:hover,.top-bar-section ul li>a.button.info:focus{color:#fff}.top-bar-section ul li>button{font-size:0.8125rem;padding-left:0.9375rem;padding-right:0.9375rem;background-color:#008CBA;border-color:#007095;color:#fff}.top-bar-section ul li>button:hover,.top-bar-section ul li>button:focus{background-color:#007095}.top-bar-section ul li>button:hover,.top-bar-section ul li>button:focus{color:#fff}.top-bar-section ul li>button.secondary{background-color:#e7e7e7;border-color:#b9b9b9;color:#333}.top-bar-section ul li>button.secondary:hover,.top-bar-section ul li>button.secondary:focus{background-color:#b9b9b9}.top-bar-section ul li>button.secondary:hover,.top-bar-section ul li>button.secondary:focus{color:#333}.top-bar-section ul li>button.success{background-color:#43AC6A;border-color:#368a55;color:#fff}.top-bar-section ul li>button.success:hover,.top-bar-section ul li>button.success:focus{background-color:#368a55}.top-bar-section ul li>button.success:hover,.top-bar-section ul li>button.success:focus{color:#fff}.top-bar-section ul li>button.alert{background-color:#f04124;border-color:#cf2a0e;color:#fff}.top-bar-section ul li>button.alert:hover,.top-bar-section ul li>button.alert:focus{background-color:#cf2a0e}.top-bar-section ul li>button.alert:hover,.top-bar-section ul li>button.alert:focus{color:#fff}.top-bar-section ul li>button.warning{background-color:#f08a24;border-color:#cf6e0e;color:#fff}.top-bar-section ul li>button.warning:hover,.top-bar-section ul li>button.warning:focus{background-color:#cf6e0e}.top-bar-section ul li>button.warning:hover,.top-bar-section ul li>button.warning:focus{color:#fff}.top-bar-section ul li>button.info{background-color:#a0d3e8;border-color:#61b6d9;color:#333}.top-bar-section ul li>button.info:hover,.top-bar-section ul li>button.info:focus{background-color:#61b6d9}.top-bar-section ul li>button.info:hover,.top-bar-section ul li>button.info:focus{color:#fff}.top-bar-section ul li:hover:not(.has-form)>a{background-color:#555;color:#fff;background:#222}.top-bar-section ul li.active>a{background:#008CBA;color:#fff}.top-bar-section ul li.active>a:hover{background:#0078a0;color:#fff}.top-bar-section .has-form{padding:0.9375rem}.top-bar-section .has-dropdown{position:relative}.top-bar-section .has-dropdown>a:after{border:inset 5px;content:"";display:block;height:0;width:0;border-color:transparent transparent transparent rgba(255,255,255,0.4);border-left-style:solid;margin-right:0.9375rem;margin-top:-4.5px;position:absolute;top:50%;right:0}.top-bar-section .has-dropdown.moved{position:static}.top-bar-section .has-dropdown.moved>.dropdown{position:static !important;height:auto;width:auto;overflow:visible;clip:auto;display:block;position:absolute !important;width:100%}.top-bar-section .has-dropdown.moved>a:after{display:none}.top-bar-section .dropdown{clip:rect(1px, 1px, 1px, 1px);height:1px;overflow:hidden;position:absolute !important;width:1px;display:block;padding:0;position:absolute;top:0;z-index:99;left:100%}.top-bar-section .dropdown li{height:auto;width:100%}.top-bar-section .dropdown li a{font-weight:normal;padding:8px 0.9375rem}.top-bar-section .dropdown li a.parent-link{font-weight:normal}.top-bar-section .dropdown li.title h5,.top-bar-section .dropdown li.parent-link{margin-bottom:0;margin-top:0;font-size:1.125rem}.top-bar-section .dropdown li.title h5 a,.top-bar-section .dropdown li.parent-link a{color:#fff;display:block}.top-bar-section .dropdown li.title h5 a:hover,.top-bar-section .dropdown li.parent-link a:hover{background:none}.top-bar-section .dropdown li.has-form{padding:8px 0.9375rem}.top-bar-section .dropdown li .button,.top-bar-section .dropdown li button{top:auto}.top-bar-section .dropdown label{color:#777;font-size:0.625rem;font-weight:bold;margin-bottom:0;padding:8px 0.9375rem 2px;text-transform:uppercase}.js-generated{display:block}@media only screen and (min-width: 40.0625em){.top-bar{background:#333;overflow:visible}.top-bar:before,.top-bar:after{content:" ";display:table}.top-bar:after{clear:both}.top-bar .toggle-topbar{display:none}.top-bar .title-area{float:left}.top-bar .name h1 a,.top-bar .name h2 a,.top-bar .name h3 a,.top-bar .name h4 a,.top-bar .name h5 a,.top-bar .name h6 a{width:auto}.top-bar input,.top-bar select,.top-bar .button,.top-bar button{font-size:0.875rem;height:1.75rem;position:relative;top:0.53125rem}.top-bar.expanded{background:#333}.contain-to-grid .top-bar{margin-bottom:0;margin:0 auto;max-width:62.5rem}.top-bar-section{transition:none 0 0;left:0 !important}.top-bar-section ul{display:inline;height:auto !important;width:auto}.top-bar-section ul li{float:left}.top-bar-section ul li .js-generated{display:none}.top-bar-section li.hover>a:not(.button){background-color:#555;background:#222;color:#fff}.top-bar-section li:not(.has-form) a:not(.button){background:#333;line-height:2.8125rem;padding:0 0.9375rem}.top-bar-section li:not(.has-form) a:not(.button):hover{background-color:#555;background:#222}.top-bar-section li.active:not(.has-form) a:not(.button){background:#008CBA;color:#fff;line-height:2.8125rem;padding:0 0.9375rem}.top-bar-section li.active:not(.has-form) a:not(.button):hover{background:#0078a0;color:#fff}.top-bar-section .has-dropdown>a{padding-right:2.1875rem !important}.top-bar-section .has-dropdown>a:after{border:inset 5px;content:"";display:block;height:0;width:0;border-color:rgba(255,255,255,0.4) transparent transparent transparent;border-top-style:solid;margin-top:-2.5px;top:1.40625rem}.top-bar-section .has-dropdown.moved{position:relative}.top-bar-section .has-dropdown.moved>.dropdown{clip:rect(1px, 1px, 1px, 1px);height:1px;overflow:hidden;position:absolute !important;width:1px;display:block}.top-bar-section .has-dropdown.hover>.dropdown,.top-bar-section .has-dropdown.not-click:hover>.dropdown{position:static !important;height:auto;width:auto;overflow:visible;clip:auto;display:block;position:absolute !important}.top-bar-section .has-dropdown>a:focus+.dropdown{position:static !important;height:auto;width:auto;overflow:visible;clip:auto;display:block;position:absolute !important}.top-bar-section .has-dropdown .dropdown li.has-dropdown>a:after{border:none;content:"\00bb";top:0.1875rem;right:5px}.top-bar-section .dropdown{left:0;background:transparent;min-width:100%;top:auto}.top-bar-section .dropdown li a{background:#333;color:#fff;line-height:2.8125rem;padding:12px 0.9375rem;white-space:nowrap}.top-bar-section .dropdown li:not(.has-form):not(.active)>a:not(.button){background:#333;color:#fff}.top-bar-section .dropdown li:not(.has-form):not(.active):hover>a:not(.button){background-color:#555;color:#fff;background:#222}.top-bar-section .dropdown li label{background:#333;white-space:nowrap}.top-bar-section .dropdown li .dropdown{left:100%;top:0}.top-bar-section>ul>.divider,.top-bar-section>ul>[role="separator"]{border-right:solid 1px #4e4e4e;border-bottom:none;border-top:none;clear:none;height:2.8125rem;width:0}.top-bar-section .has-form{background:#333;height:2.8125rem;padding:0 0.9375rem}.top-bar-section .right li .dropdown{left:auto;right:0}.top-bar-section .right li .dropdown li .dropdown{right:100%}.top-bar-section .left li .dropdown{right:auto;left:0}.top-bar-section .left li .dropdown li .dropdown{left:100%}.no-js .top-bar-section ul li:hover>a{background-color:#555;background:#222;color:#fff}.no-js .top-bar-section ul li:active>a{background:#008CBA;color:#fff}.no-js .top-bar-section .has-dropdown:hover>.dropdown{position:static !important;height:auto;width:auto;overflow:visible;clip:auto;display:block;position:absolute !important}.no-js .top-bar-section .has-dropdown>a:focus+.dropdown{position:static !important;height:auto;width:auto;overflow:visible;clip:auto;display:block;position:absolute !important}}.breadcrumbs{border-style:solid;border-width:1px;display:block;list-style:none;margin-left:0;overflow:hidden;padding:0.5625rem 0.875rem 0.5625rem;background-color:#f4f4f4;border-color:#dcdcdc;border-radius:3px}.breadcrumbs>*{color:#008CBA;float:left;font-size:0.6875rem;line-height:0.6875rem;margin:0;text-transform:uppercase}.breadcrumbs>*:hover a,.breadcrumbs>*:focus a{text-decoration:underline}.breadcrumbs>* a{color:#008CBA}.breadcrumbs>*.current{color:#333;cursor:default}.breadcrumbs>*.current a{color:#333;cursor:default}.breadcrumbs>*.current:hover,.breadcrumbs>*.current:hover a,.breadcrumbs>*.current:focus,.breadcrumbs>*.current:focus a{text-decoration:none}.breadcrumbs>*.unavailable{color:#999}.breadcrumbs>*.unavailable a{color:#999}.breadcrumbs>*.unavailable:hover,.breadcrumbs>*.unavailable:hover a,.breadcrumbs>*.unavailable:focus,.breadcrumbs>*.unavailable a:focus{color:#999;cursor:not-allowed;text-decoration:none}.breadcrumbs>*:before{color:#aaa;content:"/";margin:0 0.75rem;position:relative;top:1px}.breadcrumbs>*:first-child:before{content:" ";margin:0}[aria-label="breadcrumbs"] [aria-hidden="true"]:after{content:"/"}.alert-box{border-style:solid;border-width:1px;display:block;font-size:0.8125rem;font-weight:normal;margin-bottom:1.25rem;padding:0.875rem 1.5rem 0.875rem 0.875rem;position:relative;transition:opacity 300ms ease-out;background-color:#008CBA;border-color:#0078a0;color:#fff}.alert-box .close{right:0.25rem;background:inherit;color:#333;font-size:1.375rem;line-height:.9;margin-top:-0.6875rem;opacity:0.3;padding:0 6px 4px;position:absolute;top:50%}.alert-box .close:hover,.alert-box .close:focus{opacity:0.5}.alert-box.radius{border-radius:3px}.alert-box.round{border-radius:1000px}.alert-box.success{background-color:#43AC6A;border-color:#3a945b;color:#fff}.alert-box.alert{background-color:#f04124;border-color:#de2d0f;color:#fff}.alert-box.secondary{background-color:#e7e7e7;border-color:#c7c7c7;color:#4f4f4f}.alert-box.warning{background-color:#f08a24;border-color:#de770f;color:#fff}.alert-box.info{background-color:#a0d3e8;border-color:#74bfdd;color:#4f4f4f}.alert-box.alert-close{opacity:0}.inline-list{list-style:none;margin-left:-1.375rem;margin-right:0;margin:0 auto 1.0625rem auto;overflow:hidden;padding:0}.inline-list>li{display:block;float:left;list-style:none;margin-left:1.375rem}.inline-list>li>*{display:block}.button-group{list-style:none;margin:0;left:0}.button-group:before,.button-group:after{content:" ";display:table}.button-group:after{clear:both}.button-group.even-2 li{display:inline-block;margin:0 -2px;width:50%}.button-group.even-2 li>button,.button-group.even-2 li .button{border-left:1px solid;border-color:rgba(255,255,255,0.5)}.button-group.even-2 li:first-child button,.button-group.even-2 li:first-child .button{border-left:0}.button-group.even-2 li button,.button-group.even-2 li .button{width:100%}.button-group.even-3 li{display:inline-block;margin:0 -2px;width:33.33333%}.button-group.even-3 li>button,.button-group.even-3 li .button{border-left:1px solid;border-color:rgba(255,255,255,0.5)}.button-group.even-3 li:first-child button,.button-group.even-3 li:first-child .button{border-left:0}.button-group.even-3 li button,.button-group.even-3 li .button{width:100%}.button-group.even-4 li{display:inline-block;margin:0 -2px;width:25%}.button-group.even-4 li>button,.button-group.even-4 li .button{border-left:1px solid;border-color:rgba(255,255,255,0.5)}.button-group.even-4 li:first-child button,.button-group.even-4 li:first-child .button{border-left:0}.button-group.even-4 li button,.button-group.even-4 li .button{width:100%}.button-group.even-5 li{display:inline-block;margin:0 -2px;width:20%}.button-group.even-5 li>button,.button-group.even-5 li .button{border-left:1px solid;border-color:rgba(255,255,255,0.5)}.button-group.even-5 li:first-child button,.button-group.even-5 li:first-child .button{border-left:0}.button-group.even-5 li button,.button-group.even-5 li .button{width:100%}.button-group.even-6 li{display:inline-block;margin:0 -2px;width:16.66667%}.button-group.even-6 li>button,.button-group.even-6 li .button{border-left:1px solid;border-color:rgba(255,255,255,0.5)}.button-group.even-6 li:first-child button,.button-group.even-6 li:first-child .button{border-left:0}.button-group.even-6 li button,.button-group.even-6 li .button{width:100%}.button-group.even-7 li{display:inline-block;margin:0 -2px;width:14.28571%}.button-group.even-7 li>button,.button-group.even-7 li .button{border-left:1px solid;border-color:rgba(255,255,255,0.5)}.button-group.even-7 li:first-child button,.button-group.even-7 li:first-child .button{border-left:0}.button-group.even-7 li button,.button-group.even-7 li .button{width:100%}.button-group.even-8 li{display:inline-block;margin:0 -2px;width:12.5%}.button-group.even-8 li>button,.button-group.even-8 li .button{border-left:1px solid;border-color:rgba(255,255,255,0.5)}.button-group.even-8 li:first-child button,.button-group.even-8 li:first-child .button{border-left:0}.button-group.even-8 li button,.button-group.even-8 li .button{width:100%}.button-group>li{display:inline-block;margin:0 -2px}.button-group>li>button,.button-group>li .button{border-left:1px solid;border-color:rgba(255,255,255,0.5)}.button-group>li:first-child button,.button-group>li:first-child .button{border-left:0}.button-group.stack>li{display:block;margin:0;float:none}.button-group.stack>li>button,.button-group.stack>li .button{border-left:1px solid;border-color:rgba(255,255,255,0.5)}.button-group.stack>li:first-child button,.button-group.stack>li:first-child .button{border-left:0}.button-group.stack>li>button,.button-group.stack>li .button{border-color:rgba(255,255,255,0.5);border-left-width:0;border-top:1px solid;display:block;margin:0}.button-group.stack>li>button{width:100%}.button-group.stack>li:first-child button,.button-group.stack>li:first-child .button{border-top:0}.button-group.stack-for-small>li{display:inline-block;margin:0 -2px}.button-group.stack-for-small>li>button,.button-group.stack-for-small>li .button{border-left:1px solid;border-color:rgba(255,255,255,0.5)}.button-group.stack-for-small>li:first-child button,.button-group.stack-for-small>li:first-child .button{border-left:0}@media only screen and (max-width: 40em){.button-group.stack-for-small>li{display:block;margin:0}.button-group.stack-for-small>li>button,.button-group.stack-for-small>li .button{border-left:1px solid;border-color:rgba(255,255,255,0.5)}.button-group.stack-for-small>li:first-child button,.button-group.stack-for-small>li:first-child .button{border-left:0}.button-group.stack-for-small>li>button,.button-group.stack-for-small>li .button{border-color:rgba(255,255,255,0.5);border-left-width:0;border-top:1px solid;display:block;margin:0}.button-group.stack-for-small>li>button{width:100%}.button-group.stack-for-small>li:first-child button,.button-group.stack-for-small>li:first-child .button{border-top:0}}.button-group.radius>*{display:inline-block;margin:0 -2px}.button-group.radius>*>button,.button-group.radius>* .button{border-left:1px solid;border-color:rgba(255,255,255,0.5)}.button-group.radius>*:first-child button,.button-group.radius>*:first-child .button{border-left:0}.button-group.radius>*,.button-group.radius>*>a,.button-group.radius>*>button,.button-group.radius>*>.button{border-radius:0}.button-group.radius>*:first-child,.button-group.radius>*:first-child>a,.button-group.radius>*:first-child>button,.button-group.radius>*:first-child>.button{-webkit-border-bottom-left-radius:3px;-webkit-border-top-left-radius:3px;border-bottom-left-radius:3px;border-top-left-radius:3px}.button-group.radius>*:last-child,.button-group.radius>*:last-child>a,.button-group.radius>*:last-child>button,.button-group.radius>*:last-child>.button{-webkit-border-bottom-right-radius:3px;-webkit-border-top-right-radius:3px;border-bottom-right-radius:3px;border-top-right-radius:3px}.button-group.radius.stack>*{display:block;margin:0}.button-group.radius.stack>*>button,.button-group.radius.stack>* .button{border-left:1px solid;border-color:rgba(255,255,255,0.5)}.button-group.radius.stack>*:first-child button,.button-group.radius.stack>*:first-child .button{border-left:0}.button-group.radius.stack>*>button,.button-group.radius.stack>* .button{border-color:rgba(255,255,255,0.5);border-left-width:0;border-top:1px solid;display:block;margin:0}.button-group.radius.stack>*>button{width:100%}.button-group.radius.stack>*:first-child button,.button-group.radius.stack>*:first-child .button{border-top:0}.button-group.radius.stack>*,.button-group.radius.stack>*>a,.button-group.radius.stack>*>button,.button-group.radius.stack>*>.button{border-radius:0}.button-group.radius.stack>*:first-child,.button-group.radius.stack>*:first-child>a,.button-group.radius.stack>*:first-child>button,.button-group.radius.stack>*:first-child>.button{-webkit-top-left-radius:3px;-webkit-top-right-radius:3px;border-top-left-radius:3px;border-top-right-radius:3px}.button-group.radius.stack>*:last-child,.button-group.radius.stack>*:last-child>a,.button-group.radius.stack>*:last-child>button,.button-group.radius.stack>*:last-child>.button{-webkit-bottom-left-radius:3px;-webkit-bottom-right-radius:3px;border-bottom-left-radius:3px;border-bottom-right-radius:3px}@media only screen and (min-width: 40.0625em){.button-group.radius.stack-for-small>*{display:inline-block;margin:0 -2px}.button-group.radius.stack-for-small>*>button,.button-group.radius.stack-for-small>* .button{border-left:1px solid;border-color:rgba(255,255,255,0.5)}.button-group.radius.stack-for-small>*:first-child button,.button-group.radius.stack-for-small>*:first-child .button{border-left:0}.button-group.radius.stack-for-small>*,.button-group.radius.stack-for-small>*>a,.button-group.radius.stack-for-small>*>button,.button-group.radius.stack-for-small>*>.button{border-radius:0}.button-group.radius.stack-for-small>*:first-child,.button-group.radius.stack-for-small>*:first-child>a,.button-group.radius.stack-for-small>*:first-child>button,.button-group.radius.stack-for-small>*:first-child>.button{-webkit-border-bottom-left-radius:3px;-webkit-border-top-left-radius:3px;border-bottom-left-radius:3px;border-top-left-radius:3px}.button-group.radius.stack-for-small>*:last-child,.button-group.radius.stack-for-small>*:last-child>a,.button-group.radius.stack-for-small>*:last-child>button,.button-group.radius.stack-for-small>*:last-child>.button{-webkit-border-bottom-right-radius:3px;-webkit-border-top-right-radius:3px;border-bottom-right-radius:3px;border-top-right-radius:3px}}@media only screen and (max-width: 40em){.button-group.radius.stack-for-small>*{display:block;margin:0}.button-group.radius.stack-for-small>*>button,.button-group.radius.stack-for-small>* .button{border-left:1px solid;border-color:rgba(255,255,255,0.5)}.button-group.radius.stack-for-small>*:first-child button,.button-group.radius.stack-for-small>*:first-child .button{border-left:0}.button-group.radius.stack-for-small>*>button,.button-group.radius.stack-for-small>* .button{border-color:rgba(255,255,255,0.5);border-left-width:0;border-top:1px solid;display:block;margin:0}.button-group.radius.stack-for-small>*>button{width:100%}.button-group.radius.stack-for-small>*:first-child button,.button-group.radius.stack-for-small>*:first-child .button{border-top:0}.button-group.radius.stack-for-small>*,.button-group.radius.stack-for-small>*>a,.button-group.radius.stack-for-small>*>button,.button-group.radius.stack-for-small>*>.button{border-radius:0}.button-group.radius.stack-for-small>*:first-child,.button-group.radius.stack-for-small>*:first-child>a,.button-group.radius.stack-for-small>*:first-child>button,.button-group.radius.stack-for-small>*:first-child>.button{-webkit-top-left-radius:3px;-webkit-top-right-radius:3px;border-top-left-radius:3px;border-top-right-radius:3px}.button-group.radius.stack-for-small>*:last-child,.button-group.radius.stack-for-small>*:last-child>a,.button-group.radius.stack-for-small>*:last-child>button,.button-group.radius.stack-for-small>*:last-child>.button{-webkit-bottom-left-radius:3px;-webkit-bottom-right-radius:3px;border-bottom-left-radius:3px;border-bottom-right-radius:3px}}.button-group.round>*{display:inline-block;margin:0 -2px}.button-group.round>*>button,.button-group.round>* .button{border-left:1px solid;border-color:rgba(255,255,255,0.5)}.button-group.round>*:first-child button,.button-group.round>*:first-child .button{border-left:0}.button-group.round>*,.button-group.round>*>a,.button-group.round>*>button,.button-group.round>*>.button{border-radius:0}.button-group.round>*:first-child,.button-group.round>*:first-child>a,.button-group.round>*:first-child>button,.button-group.round>*:first-child>.button{-webkit-border-bottom-left-radius:1000px;-webkit-border-top-left-radius:1000px;border-bottom-left-radius:1000px;border-top-left-radius:1000px}.button-group.round>*:last-child,.button-group.round>*:last-child>a,.button-group.round>*:last-child>button,.button-group.round>*:last-child>.button{-webkit-border-bottom-right-radius:1000px;-webkit-border-top-right-radius:1000px;border-bottom-right-radius:1000px;border-top-right-radius:1000px}.button-group.round.stack>*{display:block;margin:0}.button-group.round.stack>*>button,.button-group.round.stack>* .button{border-left:1px solid;border-color:rgba(255,255,255,0.5)}.button-group.round.stack>*:first-child button,.button-group.round.stack>*:first-child .button{border-left:0}.button-group.round.stack>*>button,.button-group.round.stack>* .button{border-color:rgba(255,255,255,0.5);border-left-width:0;border-top:1px solid;display:block;margin:0}.button-group.round.stack>*>button{width:100%}.button-group.round.stack>*:first-child button,.button-group.round.stack>*:first-child .button{border-top:0}.button-group.round.stack>*,.button-group.round.stack>*>a,.button-group.round.stack>*>button,.button-group.round.stack>*>.button{border-radius:0}.button-group.round.stack>*:first-child,.button-group.round.stack>*:first-child>a,.button-group.round.stack>*:first-child>button,.button-group.round.stack>*:first-child>.button{-webkit-top-left-radius:1rem;-webkit-top-right-radius:1rem;border-top-left-radius:1rem;border-top-right-radius:1rem}.button-group.round.stack>*:last-child,.button-group.round.stack>*:last-child>a,.button-group.round.stack>*:last-child>button,.button-group.round.stack>*:last-child>.button{-webkit-bottom-left-radius:1rem;-webkit-bottom-right-radius:1rem;border-bottom-left-radius:1rem;border-bottom-right-radius:1rem}@media only screen and (min-width: 40.0625em){.button-group.round.stack-for-small>*{display:inline-block;margin:0 -2px}.button-group.round.stack-for-small>*>button,.button-group.round.stack-for-small>* .button{border-left:1px solid;border-color:rgba(255,255,255,0.5)}.button-group.round.stack-for-small>*:first-child button,.button-group.round.stack-for-small>*:first-child .button{border-left:0}.button-group.round.stack-for-small>*,.button-group.round.stack-for-small>*>a,.button-group.round.stack-for-small>*>button,.button-group.round.stack-for-small>*>.button{border-radius:0}.button-group.round.stack-for-small>*:first-child,.button-group.round.stack-for-small>*:first-child>a,.button-group.round.stack-for-small>*:first-child>button,.button-group.round.stack-for-small>*:first-child>.button{-webkit-border-bottom-left-radius:1000px;-webkit-border-top-left-radius:1000px;border-bottom-left-radius:1000px;border-top-left-radius:1000px}.button-group.round.stack-for-small>*:last-child,.button-group.round.stack-for-small>*:last-child>a,.button-group.round.stack-for-small>*:last-child>button,.button-group.round.stack-for-small>*:last-child>.button{-webkit-border-bottom-right-radius:1000px;-webkit-border-top-right-radius:1000px;border-bottom-right-radius:1000px;border-top-right-radius:1000px}}@media only screen and (max-width: 40em){.button-group.round.stack-for-small>*{display:block;margin:0}.button-group.round.stack-for-small>*>button,.button-group.round.stack-for-small>* .button{border-left:1px solid;border-color:rgba(255,255,255,0.5)}.button-group.round.stack-for-small>*:first-child button,.button-group.round.stack-for-small>*:first-child .button{border-left:0}.button-group.round.stack-for-small>*>button,.button-group.round.stack-for-small>* .button{border-color:rgba(255,255,255,0.5);border-left-width:0;border-top:1px solid;display:block;margin:0}.button-group.round.stack-for-small>*>button{width:100%}.button-group.round.stack-for-small>*:first-child button,.button-group.round.stack-for-small>*:first-child .button{border-top:0}.button-group.round.stack-for-small>*,.button-group.round.stack-for-small>*>a,.button-group.round.stack-for-small>*>button,.button-group.round.stack-for-small>*>.button{border-radius:0}.button-group.round.stack-for-small>*:first-child,.button-group.round.stack-for-small>*:first-child>a,.button-group.round.stack-for-small>*:first-child>button,.button-group.round.stack-for-small>*:first-child>.button{-webkit-top-left-radius:1rem;-webkit-top-right-radius:1rem;border-top-left-radius:1rem;border-top-right-radius:1rem}.button-group.round.stack-for-small>*:last-child,.button-group.round.stack-for-small>*:last-child>a,.button-group.round.stack-for-small>*:last-child>button,.button-group.round.stack-for-small>*:last-child>.button{-webkit-bottom-left-radius:1rem;-webkit-bottom-right-radius:1rem;border-bottom-left-radius:1rem;border-bottom-right-radius:1rem}}.button-bar:before,.button-bar:after{content:" ";display:table}.button-bar:after{clear:both}.button-bar .button-group{float:left;margin-right:0.625rem}.button-bar .button-group div{overflow:hidden}.panel{border-style:solid;border-width:1px;border-color:#d8d8d8;margin-bottom:1.25rem;padding:1.25rem;background:#f2f2f2;color:#333}.panel>:first-child{margin-top:0}.panel>:last-child{margin-bottom:0}.panel h1,.panel h2,.panel h3,.panel h4,.panel h5,.panel h6,.panel p,.panel li,.panel dl{color:#333}.panel h1,.panel h2,.panel h3,.panel h4,.panel h5,.panel h6{line-height:1;margin-bottom:0.625rem}.panel h1.subheader,.panel h2.subheader,.panel h3.subheader,.panel h4.subheader,.panel h5.subheader,.panel h6.subheader{line-height:1.4}.panel.callout{border-style:solid;border-width:1px;border-color:#d8d8d8;margin-bottom:1.25rem;padding:1.25rem;background:#ecfaff;color:#333}.panel.callout>:first-child{margin-top:0}.panel.callout>:last-child{margin-bottom:0}.panel.callout h1,.panel.callout h2,.panel.callout h3,.panel.callout h4,.panel.callout h5,.panel.callout h6,.panel.callout p,.panel.callout li,.panel.callout dl{color:#333}.panel.callout h1,.panel.callout h2,.panel.callout h3,.panel.callout h4,.panel.callout h5,.panel.callout h6{line-height:1;margin-bottom:0.625rem}.panel.callout h1.subheader,.panel.callout h2.subheader,.panel.callout h3.subheader,.panel.callout h4.subheader,.panel.callout h5.subheader,.panel.callout h6.subheader{line-height:1.4}.panel.callout a:not(.button){color:#008CBA}.panel.callout a:not(.button):hover,.panel.callout a:not(.button):focus{color:#0078a0}.panel.radius{border-radius:3px}.dropdown.button,button.dropdown{position:relative;padding-right:3.5625rem}.dropdown.button::after,button.dropdown::after{border-color:#fff transparent transparent transparent;border-style:solid;content:"";display:block;height:0;position:absolute;top:50%;width:0}.dropdown.button::after,button.dropdown::after{border-width:0.375rem;right:1.40625rem;margin-top:-0.15625rem}.dropdown.button::after,button.dropdown::after{border-color:#fff transparent transparent transparent}.dropdown.button.tiny,button.dropdown.tiny{padding-right:2.625rem}.dropdown.button.tiny:after,button.dropdown.tiny:after{border-width:0.375rem;right:1.125rem;margin-top:-0.125rem}.dropdown.button.tiny::after,button.dropdown.tiny::after{border-color:#fff transparent transparent transparent}.dropdown.button.small,button.dropdown.small{padding-right:3.0625rem}.dropdown.button.small::after,button.dropdown.small::after{border-width:0.4375rem;right:1.3125rem;margin-top:-0.15625rem}.dropdown.button.small::after,button.dropdown.small::after{border-color:#fff transparent transparent transparent}.dropdown.button.large,button.dropdown.large{padding-right:3.625rem}.dropdown.button.large::after,button.dropdown.large::after{border-width:0.3125rem;right:1.71875rem;margin-top:-0.15625rem}.dropdown.button.large::after,button.dropdown.large::after{border-color:#fff transparent transparent transparent}.dropdown.button.secondary:after,button.dropdown.secondary:after{border-color:#333 transparent transparent transparent}.th{border:solid 4px #fff;box-shadow:0 0 0 1px rgba(0,0,0,0.2);display:inline-block;line-height:0;max-width:100%;transition:all 200ms ease-out}.th:hover,.th:focus{box-shadow:0 0 6px 1px rgba(0,140,186,0.5)}.th.radius{border-radius:3px}.pricing-table{border:solid 1px #ddd;margin-left:0;margin-bottom:1.25rem}.pricing-table *{list-style:none;line-height:1}.pricing-table .title{background-color:#333;color:#eee;font-family:"Helvetica Neue",Helvetica,Roboto,Arial,sans-serif;font-size:1rem;font-weight:normal;padding:0.9375rem 1.25rem;text-align:center}.pricing-table .price{background-color:#F6F6F6;color:#333;font-family:"Helvetica Neue",Helvetica,Roboto,Arial,sans-serif;font-size:2rem;font-weight:normal;padding:0.9375rem 1.25rem;text-align:center}.pricing-table .description{background-color:#fff;border-bottom:dotted 1px #ddd;color:#777;font-size:0.75rem;font-weight:normal;line-height:1.4;padding:0.9375rem;text-align:center}.pricing-table .bullet-item{background-color:#fff;border-bottom:dotted 1px #ddd;color:#333;font-size:0.875rem;font-weight:normal;padding:0.9375rem;text-align:center}.pricing-table .cta-button{background-color:#fff;padding:1.25rem 1.25rem 0;text-align:center}@-webkit-keyframes rotate{from{-webkit-transform:rotate(0deg);transform:rotate(0deg)}to{-webkit-transform:rotate(360deg);transform:rotate(360deg)}}@keyframes rotate{from{-webkit-transform:rotate(0deg);-moz-transform:rotate(0deg);-ms-transform:rotate(0deg);transform:rotate(0deg)}to{-webkit-transform:rotate(360deg);-moz-transform:rotate(360deg);-ms-transform:rotate(360deg);transform:rotate(360deg)}}.slideshow-wrapper{position:relative}.slideshow-wrapper ul{list-style-type:none;margin:0}.slideshow-wrapper ul li,.slideshow-wrapper ul li .orbit-caption{display:none}.slideshow-wrapper ul li:first-child{display:block}.slideshow-wrapper .orbit-container{background-color:transparent}.slideshow-wrapper .orbit-container li{display:block}.slideshow-wrapper .orbit-container li .orbit-caption{display:block}.slideshow-wrapper .orbit-container .orbit-bullets li{display:inline-block}.slideshow-wrapper .preloader{border-radius:1000px;animation-duration:1.5s;animation-iteration-count:infinite;animation-name:rotate;animation-timing-function:linear;border-color:#555 #fff;border:solid 3px;display:block;height:40px;left:50%;margin-left:-20px;margin-top:-20px;position:absolute;top:50%;width:40px}.orbit-container{background:none;overflow:hidden;position:relative;width:100%}.orbit-container .orbit-slides-container{list-style:none;margin:0;padding:0;position:relative;-webkit-transform:translateZ(0);-moz-transform:translateZ(0);-ms-transform:translateZ(0);-o-transform:translateZ(0);transform:translateZ(0)}.orbit-container .orbit-slides-container img{display:block;max-width:100%}.orbit-container .orbit-slides-container>*{position:absolute;top:0;width:100%;margin-left:100%}.orbit-container .orbit-slides-container>*:first-child{margin-left:0}.orbit-container .orbit-slides-container>* .orbit-caption{bottom:0;position:absolute;background-color:rgba(51,51,51,0.8);color:#fff;font-size:0.875rem;padding:0.625rem 0.875rem;width:100%}.orbit-container .orbit-slide-number{left:10px;background:transparent;color:#fff;font-size:12px;position:absolute;top:10px;z-index:10}.orbit-container .orbit-slide-number span{font-weight:700;padding:0.3125rem}.orbit-container .orbit-timer{position:absolute;top:12px;right:10px;height:6px;width:100px;z-index:10}.orbit-container .orbit-timer .orbit-progress{height:3px;background-color:rgba(255,255,255,0.3);display:block;width:0;position:relative;right:20px;top:5px}.orbit-container .orbit-timer>span{border:solid 4px #fff;border-bottom:none;border-top:none;display:none;height:14px;position:absolute;top:0;width:11px;right:0}.orbit-container .orbit-timer.paused>span{top:0;width:11px;height:14px;border:inset 8px;border-left-style:solid;border-color:transparent;border-left-color:#fff;right:-4px}.orbit-container .orbit-timer.paused>span.dark{border-left-color:#333}.orbit-container:hover .orbit-timer>span{display:block}.orbit-container .orbit-prev,.orbit-container .orbit-next{background-color:transparent;color:white;height:60px;line-height:50px;margin-top:-25px;position:absolute;text-indent:-9999px !important;top:45%;width:36px;z-index:10}.orbit-container .orbit-prev:hover,.orbit-container .orbit-next:hover{background-color:rgba(0,0,0,0.3)}.orbit-container .orbit-prev>span,.orbit-container .orbit-next>span{border:inset 10px;display:block;height:0;margin-top:-10px;position:absolute;top:50%;width:0}.orbit-container .orbit-prev{left:0}.orbit-container .orbit-prev>span{border-right-style:solid;border-color:transparent;border-right-color:#fff}.orbit-container .orbit-prev:hover>span{border-right-color:#fff}.orbit-container .orbit-next{right:0}.orbit-container .orbit-next>span{border-color:transparent;border-left-style:solid;border-left-color:#fff;left:50%;margin-left:-4px}.orbit-container .orbit-next:hover>span{border-left-color:#fff}.orbit-bullets-container{text-align:center}.orbit-bullets{display:block;float:none;margin:0 auto 30px auto;overflow:hidden;position:relative;text-align:center;top:10px}.orbit-bullets li{background:#ccc;cursor:pointer;display:inline-block;float:none;height:0.5625rem;margin-right:6px;width:0.5625rem;border-radius:1000px}.orbit-bullets li.active{background:#999}.orbit-bullets li:last-child{margin-right:0}.touch .orbit-container .orbit-prev,.touch .orbit-container .orbit-next{display:none}.touch .orbit-bullets{display:none}@media only screen and (min-width: 40.0625em){.touch .orbit-container .orbit-prev,.touch .orbit-container .orbit-next{display:inherit}.touch .orbit-bullets{display:block}}@media only screen and (max-width: 40em){.orbit-stack-on-small .orbit-slides-container{height:auto !important}.orbit-stack-on-small .orbit-slides-container>*{margin:0  !important;opacity:1 !important;position:relative}.orbit-stack-on-small .orbit-slide-number{display:none}.orbit-timer{display:none}.orbit-next,.orbit-prev{display:none}.orbit-bullets{display:none}}[data-magellan-expedition],[data-magellan-expedition-clone]{background:#fff;min-width:100%;padding:10px;z-index:50}[data-magellan-expedition] .sub-nav,[data-magellan-expedition-clone] .sub-nav{margin-bottom:0}[data-magellan-expedition] .sub-nav dd,[data-magellan-expedition-clone] .sub-nav dd{margin-bottom:0}[data-magellan-expedition] .sub-nav a,[data-magellan-expedition-clone] .sub-nav a{line-height:1.8em}.icon-bar{display:inline-block;font-size:0;width:100%;background:#333}.icon-bar>*{display:block;float:left;font-size:1rem;margin:0 auto;padding:1.25rem;text-align:center;width:25%}.icon-bar>* i,.icon-bar>* img{display:block;margin:0 auto}.icon-bar>* i+label,.icon-bar>* img+label{margin-top:.0625rem}.icon-bar>* i{font-size:1.875rem;vertical-align:middle}.icon-bar>* img{height:1.875rem;width:1.875rem}.icon-bar.label-right>* i,.icon-bar.label-right>* img{display:inline-block;margin:0 .0625rem 0 0}.icon-bar.label-right>* i+label,.icon-bar.label-right>* img+label{margin-top:0}.icon-bar.label-right>* label{display:inline-block}.icon-bar.vertical.label-right>*{text-align:left}.icon-bar.vertical,.icon-bar.small-vertical{height:100%;width:auto}.icon-bar.vertical .item,.icon-bar.small-vertical .item{float:none;margin:auto;width:auto}@media only screen and (min-width: 40.0625em){.icon-bar.medium-vertical{height:100%;width:auto}.icon-bar.medium-vertical .item{float:none;margin:auto;width:auto}}@media only screen and (min-width: 64.0625em){.icon-bar.large-vertical{height:100%;width:auto}.icon-bar.large-vertical .item{float:none;margin:auto;width:auto}}.icon-bar>*{font-size:1rem;padding:1.25rem}.icon-bar>* i+label,.icon-bar>* img+label{margin-top:.0625rem;font-size:1rem}.icon-bar>* i{font-size:1.875rem}.icon-bar>* img{height:1.875rem;width:1.875rem}.icon-bar>* label{color:#fff}.icon-bar>* i{color:#fff}.icon-bar>a:hover{background:#008CBA}.icon-bar>a:hover label{color:#fff}.icon-bar>a:hover i{color:#fff}.icon-bar>a.active{background:#008CBA}.icon-bar>a.active label{color:#fff}.icon-bar>a.active i{color:#fff}.icon-bar .item.disabled{cursor:not-allowed;opacity:0.7;pointer-events:none}.icon-bar .item.disabled>*{opacity:0.7;cursor:not-allowed}.icon-bar.two-up .item{width:50%}.icon-bar.two-up.vertical .item,.icon-bar.two-up.small-vertical .item{width:auto}@media only screen and (min-width: 40.0625em){.icon-bar.two-up.medium-vertical .item{width:auto}}@media only screen and (min-width: 64.0625em){.icon-bar.two-up.large-vertical .item{width:auto}}.icon-bar.three-up .item{width:33.3333%}.icon-bar.three-up.vertical .item,.icon-bar.three-up.small-vertical .item{width:auto}@media only screen and (min-width: 40.0625em){.icon-bar.three-up.medium-vertical .item{width:auto}}@media only screen and (min-width: 64.0625em){.icon-bar.three-up.large-vertical .item{width:auto}}.icon-bar.four-up .item{width:25%}.icon-bar.four-up.vertical .item,.icon-bar.four-up.small-vertical .item{width:auto}@media only screen and (min-width: 40.0625em){.icon-bar.four-up.medium-vertical .item{width:auto}}@media only screen and (min-width: 64.0625em){.icon-bar.four-up.large-vertical .item{width:auto}}.icon-bar.five-up .item{width:20%}.icon-bar.five-up.vertical .item,.icon-bar.five-up.small-vertical .item{width:auto}@media only screen and (min-width: 40.0625em){.icon-bar.five-up.medium-vertical .item{width:auto}}@media only screen and (min-width: 64.0625em){.icon-bar.five-up.large-vertical .item{width:auto}}.icon-bar.six-up .item{width:16.66667%}.icon-bar.six-up.vertical .item,.icon-bar.six-up.small-vertical .item{width:auto}@media only screen and (min-width: 40.0625em){.icon-bar.six-up.medium-vertical .item{width:auto}}@media only screen and (min-width: 64.0625em){.icon-bar.six-up.large-vertical .item{width:auto}}.icon-bar.seven-up .item{width:14.28571%}.icon-bar.seven-up.vertical .item,.icon-bar.seven-up.small-vertical .item{width:auto}@media only screen and (min-width: 40.0625em){.icon-bar.seven-up.medium-vertical .item{width:auto}}@media only screen and (min-width: 64.0625em){.icon-bar.seven-up.large-vertical .item{width:auto}}.icon-bar.eight-up .item{width:12.5%}.icon-bar.eight-up.vertical .item,.icon-bar.eight-up.small-vertical .item{width:auto}@media only screen and (min-width: 40.0625em){.icon-bar.eight-up.medium-vertical .item{width:auto}}@media only screen and (min-width: 64.0625em){.icon-bar.eight-up.large-vertical .item{width:auto}}.icon-bar.two-up .item{width:50%}.icon-bar.two-up.vertical .item,.icon-bar.two-up.small-vertical .item{width:auto}@media only screen and (min-width: 40.0625em){.icon-bar.two-up.medium-vertical .item{width:auto}}@media only screen and (min-width: 64.0625em){.icon-bar.two-up.large-vertical .item{width:auto}}.icon-bar.three-up .item{width:33.3333%}.icon-bar.three-up.vertical .item,.icon-bar.three-up.small-vertical .item{width:auto}@media only screen and (min-width: 40.0625em){.icon-bar.three-up.medium-vertical .item{width:auto}}@media only screen and (min-width: 64.0625em){.icon-bar.three-up.large-vertical .item{width:auto}}.icon-bar.four-up .item{width:25%}.icon-bar.four-up.vertical .item,.icon-bar.four-up.small-vertical .item{width:auto}@media only screen and (min-width: 40.0625em){.icon-bar.four-up.medium-vertical .item{width:auto}}@media only screen and (min-width: 64.0625em){.icon-bar.four-up.large-vertical .item{width:auto}}.icon-bar.five-up .item{width:20%}.icon-bar.five-up.vertical .item,.icon-bar.five-up.small-vertical .item{width:auto}@media only screen and (min-width: 40.0625em){.icon-bar.five-up.medium-vertical .item{width:auto}}@media only screen and (min-width: 64.0625em){.icon-bar.five-up.large-vertical .item{width:auto}}.icon-bar.six-up .item{width:16.66667%}.icon-bar.six-up.vertical .item,.icon-bar.six-up.small-vertical .item{width:auto}@media only screen and (min-width: 40.0625em){.icon-bar.six-up.medium-vertical .item{width:auto}}@media only screen and (min-width: 64.0625em){.icon-bar.six-up.large-vertical .item{width:auto}}.icon-bar.seven-up .item{width:14.28571%}.icon-bar.seven-up.vertical .item,.icon-bar.seven-up.small-vertical .item{width:auto}@media only screen and (min-width: 40.0625em){.icon-bar.seven-up.medium-vertical .item{width:auto}}@media only screen and (min-width: 64.0625em){.icon-bar.seven-up.large-vertical .item{width:auto}}.icon-bar.eight-up .item{width:12.5%}.icon-bar.eight-up.vertical .item,.icon-bar.eight-up.small-vertical .item{width:auto}@media only screen and (min-width: 40.0625em){.icon-bar.eight-up.medium-vertical .item{width:auto}}@media only screen and (min-width: 64.0625em){.icon-bar.eight-up.large-vertical .item{width:auto}}.tabs{margin-bottom:0 !important;margin-left:0}.tabs:before,.tabs:after{content:" ";display:table}.tabs:after{clear:both}.tabs dd,.tabs .tab-title{float:left;list-style:none;margin-bottom:0 !important;position:relative}.tabs dd>a,.tabs .tab-title>a{display:block;background-color:#EFEFEF;color:#222;font-family:"Helvetica Neue",Helvetica,Roboto,Arial,sans-serif;font-size:1rem;padding:1rem 2rem}.tabs dd>a:hover,.tabs .tab-title>a:hover{background-color:#e1e1e1}.tabs dd.active a,.tabs .tab-title.active a{background-color:#fff;color:#222}.tabs.radius dd:first-child a,.tabs.radius .tab:first-child a{-webkit-border-bottom-left-radius:3px;-webkit-border-top-left-radius:3px;border-bottom-left-radius:3px;border-top-left-radius:3px}.tabs.radius dd:last-child a,.tabs.radius .tab:last-child a{-webkit-border-bottom-right-radius:3px;-webkit-border-top-right-radius:3px;border-bottom-right-radius:3px;border-top-right-radius:3px}.tabs.vertical dd,.tabs.vertical .tab-title{position:inherit;float:none;display:block;top:auto}.tabs-content{margin-bottom:1.5rem;width:100%}.tabs-content:before,.tabs-content:after{content:" ";display:table}.tabs-content:after{clear:both}.tabs-content>.content{display:none;float:left;padding:0.9375rem 0;width:100%}.tabs-content>.content.active{display:block;float:none}.tabs-content>.content.contained{padding:0.9375rem}.tabs-content.vertical{display:block}.tabs-content.vertical>.content{padding:0 0.9375rem}@media only screen and (min-width: 40.0625em){.tabs.vertical{float:left;margin:0;margin-bottom:1.25rem !important;max-width:20%;width:20%}.tabs-content.vertical{float:left;margin-left:-1px;max-width:80%;padding-left:1rem;width:80%}}.no-js .tabs-content>.content{display:block;float:none}ul.pagination{display:block;margin-left:-0.3125rem;min-height:1.5rem}ul.pagination li{color:#222;font-size:0.875rem;height:1.5rem;margin-left:0.3125rem}ul.pagination li a,ul.pagination li button{border-radius:3px;transition:background-color 300ms ease-out;background:none;color:#999;display:block;font-size:1em;font-weight:normal;line-height:inherit;padding:0.0625rem 0.625rem 0.0625rem}ul.pagination li:hover a,ul.pagination li a:focus,ul.pagination li:hover button,ul.pagination li button:focus{background:#e6e6e6}ul.pagination li.unavailable a,ul.pagination li.unavailable button{cursor:default;color:#999}ul.pagination li.unavailable:hover a,ul.pagination li.unavailable a:focus,ul.pagination li.unavailable:hover button,ul.pagination li.unavailable button:focus{background:transparent}ul.pagination li.current a,ul.pagination li.current button{background:#008CBA;color:#fff;cursor:default;font-weight:bold}ul.pagination li.current a:hover,ul.pagination li.current a:focus,ul.pagination li.current button:hover,ul.pagination li.current button:focus{background:#008CBA}ul.pagination li{display:block;float:left}.pagination-centered{text-align:center}.pagination-centered ul.pagination li{display:inline-block;float:none}.side-nav{display:block;font-family:"Helvetica Neue",Helvetica,Roboto,Arial,sans-serif;list-style-position:outside;list-style-type:none;margin:0;padding:0.875rem 0}.side-nav li{font-size:0.875rem;font-weight:normal;margin:0 0 0.4375rem 0}.side-nav li a:not(.button){color:#008CBA;display:block;margin:0;padding:0.4375rem 0.875rem}.side-nav li a:not(.button):hover,.side-nav li a:not(.button):focus{background:rgba(0,0,0,0.025);color:#1cc7ff}.side-nav li a:not(.button):active{color:#1cc7ff}.side-nav li.active>a:first-child:not(.button){color:#1cc7ff;font-family:"Helvetica Neue",Helvetica,Roboto,Arial,sans-serif;font-weight:normal}.side-nav li.divider{border-top:1px solid;height:0;list-style:none;padding:0;border-top-color:#e6e6e6}.side-nav li.heading{color:#008CBA;font-size:0.875rem;font-weight:bold;text-transform:uppercase}.accordion{margin-bottom:0}.accordion:before,.accordion:after{content:" ";display:table}.accordion:after{clear:both}.accordion .accordion-navigation,.accordion dd{display:block;margin-bottom:0 !important}.accordion .accordion-navigation.active>a,.accordion dd.active>a{background:#e8e8e8}.accordion .accordion-navigation>a,.accordion dd>a{background:#EFEFEF;color:#222;display:block;font-family:"Helvetica Neue",Helvetica,Roboto,Arial,sans-serif;font-size:1rem;padding:1rem}.accordion .accordion-navigation>a:hover,.accordion dd>a:hover{background:#e3e3e3}.accordion .accordion-navigation>.content,.accordion dd>.content{display:none;padding:0.9375rem}.accordion .accordion-navigation>.content.active,.accordion dd>.content.active{background:#fff;display:block}.text-left{text-align:left !important}.text-right{text-align:right !important}.text-center{text-align:center !important}.text-justify{text-align:justify !important}@media only screen and (max-width: 40em){.small-only-text-left{text-align:left !important}.small-only-text-right{text-align:right !important}.small-only-text-center{text-align:center !important}.small-only-text-justify{text-align:justify !important}}@media only screen{.small-text-left{text-align:left !important}.small-text-right{text-align:right !important}.small-text-center{text-align:center !important}.small-text-justify{text-align:justify !important}}@media only screen and (min-width: 40.0625em) and (max-width: 64em){.medium-only-text-left{text-align:left !important}.medium-only-text-right{text-align:right !important}.medium-only-text-center{text-align:center !important}.medium-only-text-justify{text-align:justify !important}}@media only screen and (min-width: 40.0625em){.medium-text-left{text-align:left !important}.medium-text-right{text-align:right !important}.medium-text-center{text-align:center !important}.medium-text-justify{text-align:justify !important}}@media only screen and (min-width: 64.0625em) and (max-width: 90em){.large-only-text-left{text-align:left !important}.large-only-text-right{text-align:right !important}.large-only-text-center{text-align:center !important}.large-only-text-justify{text-align:justify !important}}@media only screen and (min-width: 64.0625em){.large-text-left{text-align:left !important}.large-text-right{text-align:right !important}.large-text-center{text-align:center !important}.large-text-justify{text-align:justify !important}}@media only screen and (min-width: 90.0625em) and (max-width: 120em){.xlarge-only-text-left{text-align:left !important}.xlarge-only-text-right{text-align:right !important}.xlarge-only-text-center{text-align:center !important}.xlarge-only-text-justify{text-align:justify !important}}@media only screen and (min-width: 90.0625em){.xlarge-text-left{text-align:left !important}.xlarge-text-right{text-align:right !important}.xlarge-text-center{text-align:center !important}.xlarge-text-justify{text-align:justify !important}}@media only screen and (min-width: 120.0625em) and (max-width: 6249999.9375em){.xxlarge-only-text-left{text-align:left !important}.xxlarge-only-text-right{text-align:right !important}.xxlarge-only-text-center{text-align:center !important}.xxlarge-only-text-justify{text-align:justify !important}}@media only screen and (min-width: 120.0625em){.xxlarge-text-left{text-align:left !important}.xxlarge-text-right{text-align:right !important}.xxlarge-text-center{text-align:center !important}.xxlarge-text-justify{text-align:justify !important}}div,dl,dt,dd,ul,ol,li,h1,h2,h3,h4,h5,h6,pre,form,p,blockquote,th,td{margin:0;padding:0}a{color:#008CBA;line-height:inherit;text-decoration:none}a:hover,a:focus{color:#0078a0}a img{border:none}p{font-family:inherit;font-size:1rem;font-weight:normal;line-height:1.6;margin-bottom:1.25rem;text-rendering:optimizeLegibility}p.lead{font-size:1.21875rem;line-height:1.6}p aside{font-size:0.875rem;font-style:italic;line-height:1.35}h1,h2,h3,h4,h5,h6{color:#222;font-family:"Helvetica Neue",Helvetica,Roboto,Arial,sans-serif;font-style:normal;font-weight:normal;line-height:1.4;margin-bottom:0.5rem;margin-top:0.2rem;text-rendering:optimizeLegibility}h1 small,h2 small,h3 small,h4 small,h5 small,h6 small{color:#6f6f6f;font-size:60%;line-height:0}h1{font-size:2.125rem}h2{font-size:1.6875rem}h3{font-size:1.375rem}h4{font-size:1.125rem}h5{font-size:1.125rem}h6{font-size:1rem}.subheader{line-height:1.4;color:#6f6f6f;font-weight:normal;margin-top:0.2rem;margin-bottom:0.5rem}hr{border:solid #ddd;border-width:1px 0 0;clear:both;height:0;margin:1.25rem 0 1.1875rem}em,i{font-style:italic;line-height:inherit}strong,b{font-weight:bold;line-height:inherit}small{font-size:60%;line-height:inherit}code{background-color:#f8f8f8;border-color:#dfdfdf;border-style:solid;border-width:1px;color:#333;font-family:Consolas,"Liberation Mono",Courier,monospace;font-weight:normal;padding:0.125rem 0.3125rem 0.0625rem}ul,ol,dl{font-family:inherit;font-size:1rem;line-height:1.6;list-style-position:outside;margin-bottom:1.25rem}ul{margin-left:1.1rem}ul.no-bullet{margin-left:0}ul.no-bullet li ul,ul.no-bullet li ol{margin-left:1.25rem;margin-bottom:0;list-style:none}ul li ul,ul li ol{margin-left:1.25rem;margin-bottom:0}ul.square li ul,ul.circle li ul,ul.disc li ul{list-style:inherit}ul.square{list-style-type:square;margin-left:1.1rem}ul.circle{list-style-type:circle;margin-left:1.1rem}ul.disc{list-style-type:disc;margin-left:1.1rem}ul.no-bullet{list-style:none}ol{margin-left:1.4rem}ol li ul,ol li ol{margin-left:1.25rem;margin-bottom:0}dl dt{margin-bottom:0.3rem;font-weight:bold}dl dd{margin-bottom:0.75rem}abbr,acronym{text-transform:uppercase;font-size:90%;color:#222;cursor:help}abbr{text-transform:none}abbr[title]{border-bottom:1px dotted #ddd}blockquote{margin:0 0 1.25rem;padding:0.5625rem 1.25rem 0 1.1875rem;border-left:1px solid #ddd}blockquote cite{display:block;font-size:0.8125rem;color:#555}blockquote cite:before{content:"\2014 \0020"}blockquote cite a,blockquote cite a:visited{color:#555}blockquote,blockquote p{line-height:1.6;color:#6f6f6f}.vcard{display:inline-block;margin:0 0 1.25rem 0;border:1px solid #ddd;padding:0.625rem 0.75rem}.vcard li{margin:0;display:block}.vcard .fn{font-weight:bold;font-size:0.9375rem}.vevent .summary{font-weight:bold}.vevent abbr{cursor:default;text-decoration:none;font-weight:bold;border:none;padding:0 0.0625rem}@media only screen and (min-width: 40.0625em){h1,h2,h3,h4,h5,h6{line-height:1.4}h1{font-size:2.75rem}h2{font-size:2.3125rem}h3{font-size:1.6875rem}h4{font-size:1.4375rem}h5{font-size:1.125rem}h6{font-size:1rem}}.split.button{position:relative;padding-right:5.0625rem}.split.button span{display:block;height:100%;position:absolute;right:0;top:0;border-left:solid 1px}.split.button span:after{position:absolute;content:"";width:0;height:0;display:block;border-style:inset;top:50%;left:50%}.split.button span:active{background-color:rgba(0,0,0,0.1)}.split.button span{border-left-color:rgba(255,255,255,0.5)}.split.button span{width:3.09375rem}.split.button span:after{border-top-style:solid;border-width:0.375rem;margin-left:-0.375rem;top:48%}.split.button span:after{border-color:#fff transparent transparent transparent}.split.button.secondary span{border-left-color:rgba(255,255,255,0.5)}.split.button.secondary span:after{border-color:#fff transparent transparent transparent}.split.button.alert span{border-left-color:rgba(255,255,255,0.5)}.split.button.success span{border-left-color:rgba(255,255,255,0.5)}.split.button.tiny{padding-right:3.75rem}.split.button.tiny span{width:2.25rem}.split.button.tiny span:after{border-top-style:solid;border-width:0.375rem;margin-left:-0.375rem;top:48%}.split.button.small{padding-right:4.375rem}.split.button.small span{width:2.625rem}.split.button.small span:after{border-top-style:solid;border-width:0.4375rem;margin-left:-0.375rem;top:48%}.split.button.large{padding-right:5.5rem}.split.button.large span{width:3.4375rem}.split.button.large span:after{border-top-style:solid;border-width:0.3125rem;margin-left:-0.375rem;top:48%}.split.button.expand{padding-left:2rem}.split.button.secondary span:after{border-color:#333 transparent transparent transparent}.split.button.radius span{-webkit-border-bottom-right-radius:3px;-webkit-border-top-right-radius:3px;border-bottom-right-radius:3px;border-top-right-radius:3px}.split.button.round span{-webkit-border-bottom-right-radius:1000px;-webkit-border-top-right-radius:1000px;border-bottom-right-radius:1000px;border-top-right-radius:1000px}.split.button.no-pip span:before{border-style:none}.split.button.no-pip span:after{border-style:none}.split.button.no-pip span>i{display:block;left:50%;margin-left:-0.28889em;margin-top:-0.48889em;position:absolute;top:50%}.reveal-modal-bg{background:#000;background:rgba(0,0,0,0.45);bottom:0;display:none;left:0;position:fixed;right:0;top:0;z-index:1004;left:0}.reveal-modal{border-radius:3px;display:none;position:absolute;top:0;visibility:hidden;width:100%;z-index:1005;left:0;background-color:#fff;padding:1.875rem;border:solid 1px #666;box-shadow:0 0 10px rgba(0,0,0,0.4)}@media only screen and (max-width: 40em){.reveal-modal{min-height:100vh}}.reveal-modal .column,.reveal-modal .columns{min-width:0}.reveal-modal>:first-child{margin-top:0}.reveal-modal>:last-child{margin-bottom:0}@media only screen and (min-width: 40.0625em){.reveal-modal{left:0;margin:0 auto;max-width:62.5rem;right:0;width:80%}}@media only screen and (min-width: 40.0625em){.reveal-modal{top:6.25rem}}.reveal-modal.radius{border-radius:3px}.reveal-modal.round{border-radius:1000px}.reveal-modal.collapse{padding:0}@media only screen and (min-width: 40.0625em){.reveal-modal.tiny{left:0;margin:0 auto;max-width:62.5rem;right:0;width:30%}}@media only screen and (min-width: 40.0625em){.reveal-modal.small{left:0;margin:0 auto;max-width:62.5rem;right:0;width:40%}}@media only screen and (min-width: 40.0625em){.reveal-modal.medium{left:0;margin:0 auto;max-width:62.5rem;right:0;width:60%}}@media only screen and (min-width: 40.0625em){.reveal-modal.large{left:0;margin:0 auto;max-width:62.5rem;right:0;width:70%}}@media only screen and (min-width: 40.0625em){.reveal-modal.xlarge{left:0;margin:0 auto;max-width:62.5rem;right:0;width:95%}}.reveal-modal.full{height:100vh;height:100%;left:0;margin-left:0 !important;max-width:none !important;min-height:100vh;top:0}@media only screen and (min-width: 40.0625em){.reveal-modal.full{left:0;margin:0 auto;max-width:62.5rem;right:0;width:100%}}.reveal-modal.toback{z-index:1003}.reveal-modal .close-reveal-modal{color:#aaa;cursor:pointer;font-size:2.5rem;font-weight:bold;line-height:1;position:absolute;top:0.625rem;right:1.375rem}.has-tip{border-bottom:dotted 1px #ccc;color:#333;cursor:help;font-weight:bold}.has-tip:hover,.has-tip:focus{border-bottom:dotted 1px #003f54;color:#008CBA}.has-tip.tip-left,.has-tip.tip-right{float:none !important}.tooltip{background:#333;color:#fff;display:none;font-size:0.875rem;font-weight:normal;line-height:1.3;max-width:300px;padding:0.75rem;position:absolute;width:100%;z-index:1006;left:50%}.tooltip>.nub{border-color:transparent transparent #333 transparent;border:solid 5px;display:block;height:0;pointer-events:none;position:absolute;top:-10px;width:0;left:5px}.tooltip>.nub.rtl{left:auto;right:5px}.tooltip.radius{border-radius:3px}.tooltip.round{border-radius:1000px}.tooltip.round>.nub{left:2rem}.tooltip.opened{border-bottom:dotted 1px #003f54 !important;color:#008CBA !important}.tap-to-close{color:#777;display:block;font-size:0.625rem;font-weight:normal}@media only screen and (min-width: 40.0625em){.tooltip>.nub{border-color:transparent transparent #333 transparent;top:-10px}.tooltip.tip-top>.nub{border-color:#333 transparent transparent transparent;bottom:-10px;top:auto}.tooltip.tip-left,.tooltip.tip-right{float:none !important}.tooltip.tip-left>.nub{border-color:transparent transparent transparent #333;left:auto;margin-top:-5px;right:-10px;top:50%}.tooltip.tip-right>.nub{border-color:transparent #333 transparent transparent;left:-10px;margin-top:-5px;right:auto;top:50%}}.clearing-thumbs,[data-clearing]{list-style:none;margin-left:0;margin-bottom:0}.clearing-thumbs:before,.clearing-thumbs:after,[data-clearing]:before,[data-clearing]:after{content:" ";display:table}.clearing-thumbs:after,[data-clearing]:after{clear:both}.clearing-thumbs li,[data-clearing] li{float:left;margin-right:10px}.clearing-thumbs[class*="block-grid-"] li,[data-clearing][class*="block-grid-"] li{margin-right:0}.clearing-blackout{background:#333;height:100%;position:fixed;top:0;width:100%;z-index:998;left:0}.clearing-blackout .clearing-close{display:block}.clearing-container{height:100%;margin:0;overflow:hidden;position:relative;z-index:998}.clearing-touch-label{color:#aaa;font-size:.6em;left:50%;position:absolute;top:50%}.visible-img{height:95%;position:relative}.visible-img img{position:absolute;left:50%;top:50%;-webkit-transform:translateY(-50%) translateX(-50%);-moz-transform:translateY(-50%) translateX(-50%);-ms-transform:translateY(-50%) translateX(-50%);-o-transform:translateY(-50%) translateX(-50%);transform:translateY(-50%) translateX(-50%);max-height:100%;max-width:100%}.clearing-caption{background:#333;bottom:0;color:#ccc;font-size:0.875em;line-height:1.3;margin-bottom:0;padding:10px 30px 20px;position:absolute;text-align:center;width:100%;left:0}.clearing-close{color:#ccc;display:none;font-size:30px;line-height:1;padding-left:20px;padding-top:10px;z-index:999}.clearing-close:hover,.clearing-close:focus{color:#ccc}.clearing-assembled .clearing-container{height:100%}.clearing-assembled .clearing-container .carousel>ul{display:none}.clearing-feature li{display:none}.clearing-feature li.clearing-featured-img{display:block}@media only screen and (min-width: 40.0625em){.clearing-main-prev,.clearing-main-next{height:100%;position:absolute;top:0;width:40px}.clearing-main-prev>span,.clearing-main-next>span{border:solid 12px;display:block;height:0;position:absolute;top:50%;width:0}.clearing-main-prev>span:hover,.clearing-main-next>span:hover{opacity:.8}.clearing-main-prev{left:0}.clearing-main-prev>span{left:5px;border-color:transparent;border-right-color:#ccc}.clearing-main-next{right:0}.clearing-main-next>span{border-color:transparent;border-left-color:#ccc}.clearing-main-prev.disabled,.clearing-main-next.disabled{opacity:.3}.clearing-assembled .clearing-container .carousel{background:rgba(51,51,51,0.8);height:120px;margin-top:10px;text-align:center}.clearing-assembled .clearing-container .carousel>ul{display:inline-block;z-index:999;height:100%;position:relative;float:none}.clearing-assembled .clearing-container .carousel>ul li{clear:none;cursor:pointer;display:block;float:left;margin-right:0;min-height:inherit;opacity:.4;overflow:hidden;padding:0;position:relative;width:120px}.clearing-assembled .clearing-container .carousel>ul li.fix-height img{height:100%;max-width:none}.clearing-assembled .clearing-container .carousel>ul li a.th{border:none;box-shadow:none;display:block}.clearing-assembled .clearing-container .carousel>ul li img{cursor:pointer !important;width:100% !important}.clearing-assembled .clearing-container .carousel>ul li.visible{opacity:1}.clearing-assembled .clearing-container .carousel>ul li:hover{opacity:.8}.clearing-assembled .clearing-container .visible-img{background:#333;height:85%;overflow:hidden}.clearing-close{padding-left:0;padding-top:0;position:absolute;top:10px;right:20px}}.progress{background-color:#F6F6F6;border:1px solid #fff;height:1.5625rem;margin-bottom:0.625rem;padding:0.125rem}.progress .meter{background:#008CBA;display:block;height:100%}.progress.secondary .meter{background:#e7e7e7;display:block;height:100%}.progress.success .meter{background:#43AC6A;display:block;height:100%}.progress.alert .meter{background:#f04124;display:block;height:100%}.progress.radius{border-radius:3px}.progress.radius .meter{border-radius:2px}.progress.round{border-radius:1000px}.progress.round .meter{border-radius:999px}.sub-nav{display:block;margin:-0.25rem 0 1.125rem;overflow:hidden;padding-top:0.25rem;width:auto}.sub-nav dt{text-transform:uppercase}.sub-nav dt,.sub-nav dd,.sub-nav li{color:#999;float:left;font-family:"Helvetica Neue",Helvetica,Roboto,Arial,sans-serif;font-size:0.875rem;font-weight:normal;margin-left:1rem;margin-bottom:0}.sub-nav dt a,.sub-nav dd a,.sub-nav li a{color:#999;padding:0.1875rem 1rem;text-decoration:none}.sub-nav dt a:hover,.sub-nav dd a:hover,.sub-nav li a:hover{color:#737373}.sub-nav dt.active a,.sub-nav dd.active a,.sub-nav li.active a{border-radius:3px;background:#008CBA;color:#fff;cursor:default;font-weight:normal;padding:0.1875rem 1rem}.sub-nav dt.active a:hover,.sub-nav dd.active a:hover,.sub-nav li.active a:hover{background:#0078a0}.joyride-list{display:none}.joyride-tip-guide{background:#333;color:#fff;display:none;font-family:inherit;font-weight:normal;position:absolute;top:0;width:95%;z-index:101;left:2.5%}.lt-ie9 .joyride-tip-guide{margin-left:-400px;max-width:800px;left:50%}.joyride-content-wrapper{padding:1.125rem 1.25rem 1.5rem;width:100%}.joyride-content-wrapper .button{margin-bottom:0 !important}.joyride-content-wrapper .joyride-prev-tip{margin-right:10px}.joyride-tip-guide .joyride-nub{border:10px solid #333;display:block;height:0;position:absolute;width:0;left:22px}.joyride-tip-guide .joyride-nub.top{border-color:#333;border-top-color:transparent !important;border-top-style:solid;border-left-color:transparent !important;border-right-color:transparent !important;top:-20px}.joyride-tip-guide .joyride-nub.bottom{border-color:#333 !important;border-bottom-color:transparent !important;border-bottom-style:solid;border-left-color:transparent !important;border-right-color:transparent !important;bottom:-20px}.joyride-tip-guide .joyride-nub.right{right:-20px}.joyride-tip-guide .joyride-nub.left{left:-20px}.joyride-tip-guide h1,.joyride-tip-guide h2,.joyride-tip-guide h3,.joyride-tip-guide h4,.joyride-tip-guide h5,.joyride-tip-guide h6{color:#fff;font-weight:bold;line-height:1.25;margin:0}.joyride-tip-guide p{font-size:0.875rem;line-height:1.3;margin:0 0 1.125rem 0}.joyride-timer-indicator-wrap{border:solid 1px #555;bottom:1rem;height:3px;position:absolute;width:50px;right:1.0625rem}.joyride-timer-indicator{background:#666;display:block;height:inherit;width:0}.joyride-close-tip{color:#777 !important;font-size:24px;font-weight:normal;line-height:.5 !important;position:absolute;text-decoration:none;top:10px;right:12px}.joyride-close-tip:hover,.joyride-close-tip:focus{color:#eee !important}.joyride-modal-bg{background:rgba(0,0,0,0.5);cursor:pointer;display:none;height:100%;position:fixed;top:0;width:100%;z-index:100;left:0}.joyride-expose-wrapper{background-color:#fff;border-radius:3px;box-shadow:0 0 15px #fff;position:absolute;z-index:102}.joyride-expose-cover{background:transparent;border-radius:3px;left:0;position:absolute;top:0;z-index:9999}@media only screen and (min-width: 40.0625em){.joyride-tip-guide{width:300px;left:inherit}.joyride-tip-guide .joyride-nub.bottom{border-color:#333 !important;border-bottom-color:transparent !important;border-left-color:transparent !important;border-right-color:transparent !important;bottom:-20px}.joyride-tip-guide .joyride-nub.right{border-color:#333 !important;border-right-color:transparent !important;border-bottom-color:transparent !important;border-top-color:transparent !important;left:auto;right:-20px;top:22px}.joyride-tip-guide .joyride-nub.left{border-color:#333 !important;border-bottom-color:transparent !important;border-left-color:transparent !important;border-top-color:transparent !important;left:-20px;right:auto;top:22px}}.label{display:inline-block;font-family:"Helvetica Neue",Helvetica,Roboto,Arial,sans-serif;font-weight:normal;line-height:1;margin-bottom:auto;position:relative;text-align:center;text-decoration:none;white-space:nowrap;padding:0.25rem 0.5rem 0.25rem;font-size:0.6875rem;background-color:#008CBA;color:#fff}.label.radius{border-radius:3px}.label.round{border-radius:1000px}.label.alert{background-color:#f04124;color:#fff}.label.warning{background-color:#f08a24;color:#fff}.label.success{background-color:#43AC6A;color:#fff}.label.secondary{background-color:#e7e7e7;color:#333}.label.info{background-color:#a0d3e8;color:#333}.off-canvas-wrap{-webkit-backface-visibility:hidden;position:relative;width:100%;overflow:hidden}.off-canvas-wrap.move-right,.off-canvas-wrap.move-left{min-height:100%;-webkit-overflow-scrolling:touch}.inner-wrap{position:relative;width:100%;-webkit-transition:-webkit-transform 500ms ease;-moz-transition:-moz-transform 500ms ease;-ms-transition:-ms-transform 500ms ease;-o-transition:-o-transform 500ms ease;transition:transform 500ms ease}.inner-wrap:before,.inner-wrap:after{content:" ";display:table}.inner-wrap:after{clear:both}.tab-bar{-webkit-backface-visibility:hidden;background:#333;color:#fff;height:2.8125rem;line-height:2.8125rem;position:relative}.tab-bar h1,.tab-bar h2,.tab-bar h3,.tab-bar h4,.tab-bar h5,.tab-bar h6{color:#fff;font-weight:bold;line-height:2.8125rem;margin:0}.tab-bar h1,.tab-bar h2,.tab-bar h3,.tab-bar h4{font-size:1.125rem}.left-small{height:2.8125rem;position:absolute;top:0;width:2.8125rem;border-right:solid 1px #1a1a1a;left:0}.right-small{height:2.8125rem;position:absolute;top:0;width:2.8125rem;border-left:solid 1px #1a1a1a;right:0}.tab-bar-section{height:2.8125rem;padding:0 0.625rem;position:absolute;text-align:center;top:0}.tab-bar-section.left{text-align:left}.tab-bar-section.right{text-align:right}.tab-bar-section.left{left:0;right:2.8125rem}.tab-bar-section.right{left:2.8125rem;right:0}.tab-bar-section.middle{left:2.8125rem;right:2.8125rem}.tab-bar .menu-icon{color:#fff;display:block;height:2.8125rem;padding:0;position:relative;text-indent:2.1875rem;transform:translate3d(0, 0, 0);width:2.8125rem}.tab-bar .menu-icon span::after{content:"";display:block;height:0;position:absolute;top:50%;margin-top:-0.5rem;left:0.90625rem;box-shadow:0 0 0 1px #fff,0 7px 0 1px #fff,0 14px 0 1px #fff;width:1rem}.tab-bar .menu-icon span:hover:after{box-shadow:0 0 0 1px #b3b3b3,0 7px 0 1px #b3b3b3,0 14px 0 1px #b3b3b3}.left-off-canvas-menu{-webkit-backface-visibility:hidden;background:#333;bottom:0;box-sizing:content-box;-webkit-overflow-scrolling:touch;-ms-overflow-style:-ms-autohiding-scrollbar;overflow-x:hidden;overflow-y:auto;position:absolute;top:0;transition:transform 500ms ease 0s;width:15.625rem;z-index:1001;-webkit-transform:translate3d(-100%, 0, 0);-moz-transform:translate3d(-100%, 0, 0);-ms-transform:translate(-100%, 0);-ms-transform:translate3d(-100%, 0, 0);-o-transform:translate3d(-100%, 0, 0);transform:translate3d(-100%, 0, 0);left:0}.left-off-canvas-menu *{-webkit-backface-visibility:hidden}.right-off-canvas-menu{-webkit-backface-visibility:hidden;background:#333;bottom:0;box-sizing:content-box;-webkit-overflow-scrolling:touch;-ms-overflow-style:-ms-autohiding-scrollbar;overflow-x:hidden;overflow-y:auto;position:absolute;top:0;transition:transform 500ms ease 0s;width:15.625rem;z-index:1001;-webkit-transform:translate3d(100%, 0, 0);-moz-transform:translate3d(100%, 0, 0);-ms-transform:translate(100%, 0);-ms-transform:translate3d(100%, 0, 0);-o-transform:translate3d(100%, 0, 0);transform:translate3d(100%, 0, 0);right:0}.right-off-canvas-menu *{-webkit-backface-visibility:hidden}ul.off-canvas-list{list-style-type:none;margin:0;padding:0}ul.off-canvas-list li label{background:#444;border-bottom:none;border-top:1px solid #5e5e5e;color:#999;display:block;font-size:0.75rem;font-weight:bold;margin:0;padding:0.3rem 0.9375rem;text-transform:uppercase}ul.off-canvas-list li a{border-bottom:1px solid #262626;color:rgba(255,255,255,0.7);display:block;padding:0.66667rem;transition:background 300ms ease}ul.off-canvas-list li a:hover{background:#242424}ul.off-canvas-list li a:active{background:#242424}.move-right>.inner-wrap{-webkit-transform:translate3d(15.625rem, 0, 0);-moz-transform:translate3d(15.625rem, 0, 0);-ms-transform:translate(15.625rem, 0);-ms-transform:translate3d(15.625rem, 0, 0);-o-transform:translate3d(15.625rem, 0, 0);transform:translate3d(15.625rem, 0, 0)}.move-right .exit-off-canvas{-webkit-backface-visibility:hidden;box-shadow:-4px 0 4px rgba(0,0,0,0.5),4px 0 4px rgba(0,0,0,0.5);cursor:pointer;transition:background 300ms ease;-webkit-tap-highlight-color:transparent;background:rgba(255,255,255,0.2);bottom:0;display:block;left:0;position:absolute;right:0;top:0;z-index:1002}@media only screen and (min-width: 40.0625em){.move-right .exit-off-canvas:hover{background:rgba(255,255,255,0.05)}}.move-left>.inner-wrap{-webkit-transform:translate3d(-15.625rem, 0, 0);-moz-transform:translate3d(-15.625rem, 0, 0);-ms-transform:translate(-15.625rem, 0);-ms-transform:translate3d(-15.625rem, 0, 0);-o-transform:translate3d(-15.625rem, 0, 0);transform:translate3d(-15.625rem, 0, 0)}.move-left .exit-off-canvas{-webkit-backface-visibility:hidden;box-shadow:-4px 0 4px rgba(0,0,0,0.5),4px 0 4px rgba(0,0,0,0.5);cursor:pointer;transition:background 300ms ease;-webkit-tap-highlight-color:transparent;background:rgba(255,255,255,0.2);bottom:0;display:block;left:0;position:absolute;right:0;top:0;z-index:1002}@media only screen and (min-width: 40.0625em){.move-left .exit-off-canvas:hover{background:rgba(255,255,255,0.05)}}.offcanvas-overlap .left-off-canvas-menu,.offcanvas-overlap .right-off-canvas-menu{-ms-transform:none;-webkit-transform:none;-moz-transform:none;-o-transform:none;transform:none;z-index:1003}.offcanvas-overlap .exit-off-canvas{-webkit-backface-visibility:hidden;box-shadow:-4px 0 4px rgba(0,0,0,0.5),4px 0 4px rgba(0,0,0,0.5);cursor:pointer;transition:background 300ms ease;-webkit-tap-highlight-color:transparent;background:rgba(255,255,255,0.2);bottom:0;display:block;left:0;position:absolute;right:0;top:0;z-index:1002}@media only screen and (min-width: 40.0625em){.offcanvas-overlap .exit-off-canvas:hover{background:rgba(255,255,255,0.05)}}.offcanvas-overlap-left .right-off-canvas-menu{-ms-transform:none;-webkit-transform:none;-moz-transform:none;-o-transform:none;transform:none;z-index:1003}.offcanvas-overlap-left .exit-off-canvas{-webkit-backface-visibility:hidden;box-shadow:-4px 0 4px rgba(0,0,0,0.5),4px 0 4px rgba(0,0,0,0.5);cursor:pointer;transition:background 300ms ease;-webkit-tap-highlight-color:transparent;background:rgba(255,255,255,0.2);bottom:0;display:block;left:0;position:absolute;right:0;top:0;z-index:1002}@media only screen and (min-width: 40.0625em){.offcanvas-overlap-left .exit-off-canvas:hover{background:rgba(255,255,255,0.05)}}.offcanvas-overlap-right .left-off-canvas-menu{-ms-transform:none;-webkit-transform:none;-moz-transform:none;-o-transform:none;transform:none;z-index:1003}.offcanvas-overlap-right .exit-off-canvas{-webkit-backface-visibility:hidden;box-shadow:-4px 0 4px rgba(0,0,0,0.5),4px 0 4px rgba(0,0,0,0.5);cursor:pointer;transition:background 300ms ease;-webkit-tap-highlight-color:transparent;background:rgba(255,255,255,0.2);bottom:0;display:block;left:0;position:absolute;right:0;top:0;z-index:1002}@media only screen and (min-width: 40.0625em){.offcanvas-overlap-right .exit-off-canvas:hover{background:rgba(255,255,255,0.05)}}.no-csstransforms .left-off-canvas-menu{left:-15.625rem}.no-csstransforms .right-off-canvas-menu{right:-15.625rem}.no-csstransforms .move-left>.inner-wrap{right:15.625rem}.no-csstransforms .move-right>.inner-wrap{left:15.625rem}.left-submenu{-webkit-backface-visibility:hidden;-webkit-overflow-scrolling:touch;background:#333;bottom:0;box-sizing:content-box;margin:0;overflow-x:hidden;overflow-y:auto;position:absolute;top:0;width:15.625rem;z-index:1002;-webkit-transform:translate3d(-100%, 0, 0);-moz-transform:translate3d(-100%, 0, 0);-ms-transform:translate(-100%, 0);-ms-transform:translate3d(-100%, 0, 0);-o-transform:translate3d(-100%, 0, 0);transform:translate3d(-100%, 0, 0);left:0;-webkit-transition:-webkit-transform 500ms ease;-moz-transition:-moz-transform 500ms ease;-ms-transition:-ms-transform 500ms ease;-o-transition:-o-transform 500ms ease;transition:transform 500ms ease}.left-submenu *{-webkit-backface-visibility:hidden}.left-submenu .back>a{background:#444;border-bottom:none;border-top:1px solid #5e5e5e;color:#999;font-weight:bold;padding:0.3rem 0.9375rem;text-transform:uppercase;margin:0}.left-submenu .back>a:hover{background:#303030;border-bottom:none;border-top:1px solid #5e5e5e}.left-submenu .back>a:before{content:"\AB";margin-right:.5rem;display:inline}.left-submenu.move-right,.left-submenu.offcanvas-overlap-right,.left-submenu.offcanvas-overlap{-webkit-transform:translate3d(0%, 0, 0);-moz-transform:translate3d(0%, 0, 0);-ms-transform:translate(0%, 0);-ms-transform:translate3d(0%, 0, 0);-o-transform:translate3d(0%, 0, 0);transform:translate3d(0%, 0, 0)}.right-submenu{-webkit-backface-visibility:hidden;-webkit-overflow-scrolling:touch;background:#333;bottom:0;box-sizing:content-box;margin:0;overflow-x:hidden;overflow-y:auto;position:absolute;top:0;width:15.625rem;z-index:1002;-webkit-transform:translate3d(100%, 0, 0);-moz-transform:translate3d(100%, 0, 0);-ms-transform:translate(100%, 0);-ms-transform:translate3d(100%, 0, 0);-o-transform:translate3d(100%, 0, 0);transform:translate3d(100%, 0, 0);right:0;-webkit-transition:-webkit-transform 500ms ease;-moz-transition:-moz-transform 500ms ease;-ms-transition:-ms-transform 500ms ease;-o-transition:-o-transform 500ms ease;transition:transform 500ms ease}.right-submenu *{-webkit-backface-visibility:hidden}.right-submenu .back>a{background:#444;border-bottom:none;border-top:1px solid #5e5e5e;color:#999;font-weight:bold;padding:0.3rem 0.9375rem;text-transform:uppercase;margin:0}.right-submenu .back>a:hover{background:#303030;border-bottom:none;border-top:1px solid #5e5e5e}.right-submenu .back>a:after{content:"\BB";margin-left:.5rem;display:inline}.right-submenu.move-left,.right-submenu.offcanvas-overlap-left,.right-submenu.offcanvas-overlap{-webkit-transform:translate3d(0%, 0, 0);-moz-transform:translate3d(0%, 0, 0);-ms-transform:translate(0%, 0);-ms-transform:translate3d(0%, 0, 0);-o-transform:translate3d(0%, 0, 0);transform:translate3d(0%, 0, 0)}.left-off-canvas-menu ul.off-canvas-list li.has-submenu>a:after{content:"\BB";margin-left:.5rem;display:inline}.right-off-canvas-menu ul.off-canvas-list li.has-submenu>a:before{content:"\AB";margin-right:.5rem;display:inline}.f-dropdown{display:none;left:-9999px;list-style:none;margin-left:0;position:absolute;background:#fff;border:solid 1px #ccc;font-size:0.875rem;height:auto;max-height:none;width:100%;z-index:89;margin-top:2px;max-width:200px}.f-dropdown.open{display:block}.f-dropdown>*:first-child{margin-top:0}.f-dropdown>*:last-child{margin-bottom:0}.f-dropdown:before{border:inset 6px;content:"";display:block;height:0;width:0;border-color:transparent transparent #fff transparent;border-bottom-style:solid;position:absolute;top:-12px;left:10px;z-index:89}.f-dropdown:after{border:inset 7px;content:"";display:block;height:0;width:0;border-color:transparent transparent #ccc transparent;border-bottom-style:solid;position:absolute;top:-14px;left:9px;z-index:88}.f-dropdown.right:before{left:auto;right:10px}.f-dropdown.right:after{left:auto;right:9px}.f-dropdown.drop-right{display:none;left:-9999px;list-style:none;margin-left:0;position:absolute;background:#fff;border:solid 1px #ccc;font-size:0.875rem;height:auto;max-height:none;width:100%;z-index:89;margin-top:0;margin-left:2px;max-width:200px}.f-dropdown.drop-right.open{display:block}.f-dropdown.drop-right>*:first-child{margin-top:0}.f-dropdown.drop-right>*:last-child{margin-bottom:0}.f-dropdown.drop-right:before{border:inset 6px;content:"";display:block;height:0;width:0;border-color:transparent #fff transparent transparent;border-right-style:solid;position:absolute;top:10px;left:-12px;z-index:89}.f-dropdown.drop-right:after{border:inset 7px;content:"";display:block;height:0;width:0;border-color:transparent #ccc transparent transparent;border-right-style:solid;position:absolute;top:9px;left:-14px;z-index:88}.f-dropdown.drop-left{display:none;left:-9999px;list-style:none;margin-left:0;position:absolute;background:#fff;border:solid 1px #ccc;font-size:0.875rem;height:auto;max-height:none;width:100%;z-index:89;margin-top:0;margin-left:-2px;max-width:200px}.f-dropdown.drop-left.open{display:block}.f-dropdown.drop-left>*:first-child{margin-top:0}.f-dropdown.drop-left>*:last-child{margin-bottom:0}.f-dropdown.drop-left:before{border:inset 6px;content:"";display:block;height:0;width:0;border-color:transparent transparent transparent #fff;border-left-style:solid;position:absolute;top:10px;right:-12px;left:auto;z-index:89}.f-dropdown.drop-left:after{border:inset 7px;content:"";display:block;height:0;width:0;border-color:transparent transparent transparent #ccc;border-left-style:solid;position:absolute;top:9px;right:-14px;left:auto;z-index:88}.f-dropdown.drop-top{display:none;left:-9999px;list-style:none;margin-left:0;position:absolute;background:#fff;border:solid 1px #ccc;font-size:0.875rem;height:auto;max-height:none;width:100%;z-index:89;margin-left:0;margin-top:-2px;max-width:200px}.f-dropdown.drop-top.open{display:block}.f-dropdown.drop-top>*:first-child{margin-top:0}.f-dropdown.drop-top>*:last-child{margin-bottom:0}.f-dropdown.drop-top:before{border:inset 6px;content:"";display:block;height:0;width:0;border-color:#fff transparent transparent transparent;border-top-style:solid;bottom:-12px;position:absolute;top:auto;left:10px;right:auto;z-index:89}.f-dropdown.drop-top:after{border:inset 7px;content:"";display:block;height:0;width:0;border-color:#ccc transparent transparent transparent;border-top-style:solid;bottom:-14px;position:absolute;top:auto;left:9px;right:auto;z-index:88}.f-dropdown li{cursor:pointer;font-size:0.875rem;line-height:1.125rem;margin:0}.f-dropdown li:hover,.f-dropdown li:focus{background:#eee}.f-dropdown li.radius{border-radius:3px}.f-dropdown li a{display:block;padding:0.5rem;color:#555}.f-dropdown.content{display:none;left:-9999px;list-style:none;margin-left:0;position:absolute;background:#fff;border:solid 1px #ccc;font-size:0.875rem;height:auto;max-height:none;padding:1.25rem;width:100%;z-index:89;max-width:200px}.f-dropdown.content.open{display:block}.f-dropdown.content>*:first-child{margin-top:0}.f-dropdown.content>*:last-child{margin-bottom:0}.f-dropdown.tiny{max-width:200px}.f-dropdown.small{max-width:300px}.f-dropdown.medium{max-width:500px}.f-dropdown.large{max-width:800px}.f-dropdown.mega{width:100% !important;max-width:100% !important}.f-dropdown.mega.open{left:0 !important}table{background:#fff;border:solid 1px #ddd;margin-bottom:1.25rem;table-layout:auto}table caption{background:transparent;color:#222;font-size:1rem;font-weight:bold}table thead{background:#F5F5F5}table thead tr th,table thead tr td{color:#222;font-size:0.875rem;font-weight:bold;padding:0.5rem 0.625rem 0.625rem}table tfoot{background:#F5F5F5}table tfoot tr th,table tfoot tr td{color:#222;font-size:0.875rem;font-weight:bold;padding:0.5rem 0.625rem 0.625rem}table tr th,table tr td{color:#222;font-size:0.875rem;padding:0.5625rem 0.625rem;text-align:left}table tr.even,table tr.alt,table tr:nth-of-type(even){background:#F9F9F9}table thead tr th,table tfoot tr th,table tfoot tr td,table tbody tr th,table tbody tr td,table tr td{display:table-cell;line-height:1.125rem}.range-slider{border:1px solid #ddd;margin:1.25rem 0;position:relative;-ms-touch-action:none;touch-action:none;display:block;height:1rem;width:100%;background:#FAFAFA}.range-slider.vertical-range{border:1px solid #ddd;margin:1.25rem 0;position:relative;-ms-touch-action:none;touch-action:none;display:inline-block;height:12.5rem;width:1rem}.range-slider.vertical-range .range-slider-handle{bottom:-10.5rem;margin-left:-0.5rem;margin-top:0;position:absolute}.range-slider.vertical-range .range-slider-active-segment{border-bottom-left-radius:inherit;border-bottom-right-radius:inherit;border-top-left-radius:initial;bottom:0;height:auto;width:0.875rem}.range-slider.radius{background:#FAFAFA;border-radius:3px}.range-slider.radius .range-slider-handle{background:#008CBA;border-radius:3px}.range-slider.radius .range-slider-handle:hover{background:#007ba4}.range-slider.round{background:#FAFAFA;border-radius:1000px}.range-slider.round .range-slider-handle{background:#008CBA;border-radius:1000px}.range-slider.round .range-slider-handle:hover{background:#007ba4}.range-slider.disabled,.range-slider[disabled]{background:#FAFAFA;cursor:not-allowed;opacity:0.7}.range-slider.disabled .range-slider-handle,.range-slider[disabled] .range-slider-handle{background:#008CBA;cursor:default;opacity:0.7}.range-slider.disabled .range-slider-handle:hover,.range-slider[disabled] .range-slider-handle:hover{background:#007ba4}.range-slider-active-segment{background:#e5e5e5;border-bottom-left-radius:inherit;border-top-left-radius:inherit;display:inline-block;height:0.875rem;position:absolute}.range-slider-handle{border:1px solid none;cursor:pointer;display:inline-block;height:1.375rem;position:absolute;top:-0.3125rem;width:2rem;z-index:1;-ms-touch-action:manipulation;touch-action:manipulation;background:#008CBA}.range-slider-handle:hover{background:#007ba4}[class*="block-grid-"]{display:block;padding:0;margin:0 -0.625rem}[class*="block-grid-"]:before,[class*="block-grid-"]:after{content:" ";display:table}[class*="block-grid-"]:after{clear:both}[class*="block-grid-"]>li{display:block;float:left;height:auto;padding:0 0.625rem 1.25rem}@media only screen{.small-block-grid-1>li{list-style:none;width:100%}.small-block-grid-1>li:nth-of-type(1n){clear:none}.small-block-grid-1>li:nth-of-type(1n+1){clear:both}.small-block-grid-2>li{list-style:none;width:50%}.small-block-grid-2>li:nth-of-type(1n){clear:none}.small-block-grid-2>li:nth-of-type(2n+1){clear:both}.small-block-grid-3>li{list-style:none;width:33.33333%}.small-block-grid-3>li:nth-of-type(1n){clear:none}.small-block-grid-3>li:nth-of-type(3n+1){clear:both}.small-block-grid-4>li{list-style:none;width:25%}.small-block-grid-4>li:nth-of-type(1n){clear:none}.small-block-grid-4>li:nth-of-type(4n+1){clear:both}.small-block-grid-5>li{list-style:none;width:20%}.small-block-grid-5>li:nth-of-type(1n){clear:none}.small-block-grid-5>li:nth-of-type(5n+1){clear:both}.small-block-grid-6>li{list-style:none;width:16.66667%}.small-block-grid-6>li:nth-of-type(1n){clear:none}.small-block-grid-6>li:nth-of-type(6n+1){clear:both}.small-block-grid-7>li{list-style:none;width:14.28571%}.small-block-grid-7>li:nth-of-type(1n){clear:none}.small-block-grid-7>li:nth-of-type(7n+1){clear:both}.small-block-grid-8>li{list-style:none;width:12.5%}.small-block-grid-8>li:nth-of-type(1n){clear:none}.small-block-grid-8>li:nth-of-type(8n+1){clear:both}.small-block-grid-9>li{list-style:none;width:11.11111%}.small-block-grid-9>li:nth-of-type(1n){clear:none}.small-block-grid-9>li:nth-of-type(9n+1){clear:both}.small-block-grid-10>li{list-style:none;width:10%}.small-block-grid-10>li:nth-of-type(1n){clear:none}.small-block-grid-10>li:nth-of-type(10n+1){clear:both}.small-block-grid-11>li{list-style:none;width:9.09091%}.small-block-grid-11>li:nth-of-type(1n){clear:none}.small-block-grid-11>li:nth-of-type(11n+1){clear:both}.small-block-grid-12>li{list-style:none;width:8.33333%}.small-block-grid-12>li:nth-of-type(1n){clear:none}.small-block-grid-12>li:nth-of-type(12n+1){clear:both}}@media only screen and (min-width: 40.0625em){.medium-block-grid-1>li{list-style:none;width:100%}.medium-block-grid-1>li:nth-of-type(1n){clear:none}.medium-block-grid-1>li:nth-of-type(1n+1){clear:both}.medium-block-grid-2>li{list-style:none;width:50%}.medium-block-grid-2>li:nth-of-type(1n){clear:none}.medium-block-grid-2>li:nth-of-type(2n+1){clear:both}.medium-block-grid-3>li{list-style:none;width:33.33333%}.medium-block-grid-3>li:nth-of-type(1n){clear:none}.medium-block-grid-3>li:nth-of-type(3n+1){clear:both}.medium-block-grid-4>li{list-style:none;width:25%}.medium-block-grid-4>li:nth-of-type(1n){clear:none}.medium-block-grid-4>li:nth-of-type(4n+1){clear:both}.medium-block-grid-5>li{list-style:none;width:20%}.medium-block-grid-5>li:nth-of-type(1n){clear:none}.medium-block-grid-5>li:nth-of-type(5n+1){clear:both}.medium-block-grid-6>li{list-style:none;width:16.66667%}.medium-block-grid-6>li:nth-of-type(1n){clear:none}.medium-block-grid-6>li:nth-of-type(6n+1){clear:both}.medium-block-grid-7>li{list-style:none;width:14.28571%}.medium-block-grid-7>li:nth-of-type(1n){clear:none}.medium-block-grid-7>li:nth-of-type(7n+1){clear:both}.medium-block-grid-8>li{list-style:none;width:12.5%}.medium-block-grid-8>li:nth-of-type(1n){clear:none}.medium-block-grid-8>li:nth-of-type(8n+1){clear:both}.medium-block-grid-9>li{list-style:none;width:11.11111%}.medium-block-grid-9>li:nth-of-type(1n){clear:none}.medium-block-grid-9>li:nth-of-type(9n+1){clear:both}.medium-block-grid-10>li{list-style:none;width:10%}.medium-block-grid-10>li:nth-of-type(1n){clear:none}.medium-block-grid-10>li:nth-of-type(10n+1){clear:both}.medium-block-grid-11>li{list-style:none;width:9.09091%}.medium-block-grid-11>li:nth-of-type(1n){clear:none}.medium-block-grid-11>li:nth-of-type(11n+1){clear:both}.medium-block-grid-12>li{list-style:none;width:8.33333%}.medium-block-grid-12>li:nth-of-type(1n){clear:none}.medium-block-grid-12>li:nth-of-type(12n+1){clear:both}}@media only screen and (min-width: 64.0625em){.large-block-grid-1>li{list-style:none;width:100%}.large-block-grid-1>li:nth-of-type(1n){clear:none}.large-block-grid-1>li:nth-of-type(1n+1){clear:both}.large-block-grid-2>li{list-style:none;width:50%}.large-block-grid-2>li:nth-of-type(1n){clear:none}.large-block-grid-2>li:nth-of-type(2n+1){clear:both}.large-block-grid-3>li{list-style:none;width:33.33333%}.large-block-grid-3>li:nth-of-type(1n){clear:none}.large-block-grid-3>li:nth-of-type(3n+1){clear:both}.large-block-grid-4>li{list-style:none;width:25%}.large-block-grid-4>li:nth-of-type(1n){clear:none}.large-block-grid-4>li:nth-of-type(4n+1){clear:both}.large-block-grid-5>li{list-style:none;width:20%}.large-block-grid-5>li:nth-of-type(1n){clear:none}.large-block-grid-5>li:nth-of-type(5n+1){clear:both}.large-block-grid-6>li{list-style:none;width:16.66667%}.large-block-grid-6>li:nth-of-type(1n){clear:none}.large-block-grid-6>li:nth-of-type(6n+1){clear:both}.large-block-grid-7>li{list-style:none;width:14.28571%}.large-block-grid-7>li:nth-of-type(1n){clear:none}.large-block-grid-7>li:nth-of-type(7n+1){clear:both}.large-block-grid-8>li{list-style:none;width:12.5%}.large-block-grid-8>li:nth-of-type(1n){clear:none}.large-block-grid-8>li:nth-of-type(8n+1){clear:both}.large-block-grid-9>li{list-style:none;width:11.11111%}.large-block-grid-9>li:nth-of-type(1n){clear:none}.large-block-grid-9>li:nth-of-type(9n+1){clear:both}.large-block-grid-10>li{list-style:none;width:10%}.large-block-grid-10>li:nth-of-type(1n){clear:none}.large-block-grid-10>li:nth-of-type(10n+1){clear:both}.large-block-grid-11>li{list-style:none;width:9.09091%}.large-block-grid-11>li:nth-of-type(1n){clear:none}.large-block-grid-11>li:nth-of-type(11n+1){clear:both}.large-block-grid-12>li{list-style:none;width:8.33333%}.large-block-grid-12>li:nth-of-type(1n){clear:none}.large-block-grid-12>li:nth-of-type(12n+1){clear:both}}.flex-video{height:0;margin-bottom:1rem;overflow:hidden;padding-bottom:67.5%;padding-top:1.5625rem;position:relative}.flex-video.widescreen{padding-bottom:56.34%}.flex-video.vimeo{padding-top:0}.flex-video iframe,.flex-video object,.flex-video embed,.flex-video video{height:100%;position:absolute;top:0;width:100%;left:0}.keystroke,kbd{background-color:#ededed;border-color:#ddd;color:#222;border-style:solid;border-width:1px;font-family:"Consolas","Menlo","Courier",monospace;font-size:inherit;margin:0;padding:0.125rem 0.25rem 0;border-radius:3px}.switch{border:none;margin-bottom:1.5rem;outline:0;padding:0;position:relative;-webkit-user-select:none;-moz-user-select:none;-ms-user-select:none;user-select:none}.switch label{background:#ddd;color:transparent;cursor:pointer;display:block;margin-bottom:1rem;position:relative;text-indent:100%;width:4rem;height:2rem;transition:left 0.15s ease-out}.switch input{left:10px;opacity:0;padding:0;position:absolute;top:9px}.switch input+label{margin-left:0;margin-right:0}.switch label:after{background:#fff;content:"";display:block;height:1.5rem;left:.25rem;position:absolute;top:.25rem;width:1.5rem;-webkit-transition:left 0.15s ease-out;-moz-transition:left 0.15s ease-out;-o-transition:translate3d(0, 0, 0);transition:left 0.15s ease-out;-webkit-transform:translate3d(0, 0, 0);-moz-transform:translate3d(0, 0, 0);-ms-transform:translate3d(0, 0, 0);-o-transform:translate3d(0, 0, 0);transform:translate3d(0, 0, 0)}.switch input:checked+label{background:#008CBA}.switch input:checked+label:after{left:2.25rem}.switch label{height:2rem;width:4rem}.switch label:after{height:1.5rem;width:1.5rem}.switch input:checked+label:after{left:2.25rem}.switch label{color:transparent;background:#ddd}.switch label:after{background:#fff}.switch input:checked+label{background:#008CBA}.switch.large label{height:2.5rem;width:5rem}.switch.large label:after{height:2rem;width:2rem}.switch.large input:checked+label:after{left:2.75rem}.switch.small label{height:1.75rem;width:3.5rem}.switch.small label:after{height:1.25rem;width:1.25rem}.switch.small input:checked+label:after{left:2rem}.switch.tiny label{height:1.5rem;width:3rem}.switch.tiny label:after{height:1rem;width:1rem}.switch.tiny input:checked+label:after{left:1.75rem}.switch.radius label{border-radius:4px}.switch.radius label:after{border-radius:3px}.switch.round{border-radius:1000px}.switch.round label{border-radius:2rem}.switch.round label:after{border-radius:2rem}@media only screen{.show-for-small-only,.show-for-small-up,.show-for-small,.show-for-small-down,.hide-for-medium-only,.hide-for-medium-up,.hide-for-medium,.show-for-medium-down,.hide-for-large-only,.hide-for-large-up,.hide-for-large,.show-for-large-down,.hide-for-xlarge-only,.hide-for-xlarge-up,.hide-for-xlarge,.show-for-xlarge-down,.hide-for-xxlarge-only,.hide-for-xxlarge-up,.hide-for-xxlarge,.show-for-xxlarge-down{display:inherit !important}.hide-for-small-only,.hide-for-small-up,.hide-for-small,.hide-for-small-down,.show-for-medium-only,.show-for-medium-up,.show-for-medium,.hide-for-medium-down,.show-for-large-only,.show-for-large-up,.show-for-large,.hide-for-large-down,.show-for-xlarge-only,.show-for-xlarge-up,.show-for-xlarge,.hide-for-xlarge-down,.show-for-xxlarge-only,.show-for-xxlarge-up,.show-for-xxlarge,.hide-for-xxlarge-down{display:none !important}.visible-for-small-only,.visible-for-small-up,.visible-for-small,.visible-for-small-down,.hidden-for-medium-only,.hidden-for-medium-up,.hidden-for-medium,.visible-for-medium-down,.hidden-for-large-only,.hidden-for-large-up,.hidden-for-large,.visible-for-large-down,.hidden-for-xlarge-only,.hidden-for-xlarge-up,.hidden-for-xlarge,.visible-for-xlarge-down,.hidden-for-xxlarge-only,.hidden-for-xxlarge-up,.hidden-for-xxlarge,.visible-for-xxlarge-down{position:static !important;height:auto;width:auto;overflow:visible;clip:auto}.hidden-for-small-only,.hidden-for-small-up,.hidden-for-small,.hidden-for-small-down,.visible-for-medium-only,.visible-for-medium-up,.visible-for-medium,.hidden-for-medium-down,.visible-for-large-only,.visible-for-large-up,.visible-for-large,.hidden-for-large-down,.visible-for-xlarge-only,.visible-for-xlarge-up,.visible-for-xlarge,.hidden-for-xlarge-down,.visible-for-xxlarge-only,.visible-for-xxlarge-up,.visible-for-xxlarge,.hidden-for-xxlarge-down{clip:rect(1px, 1px, 1px, 1px);height:1px;overflow:hidden;position:absolute !important;width:1px}table.show-for-small-only,table.show-for-small-up,table.show-for-small,table.show-for-small-down,table.hide-for-medium-only,table.hide-for-medium-up,table.hide-for-medium,table.show-for-medium-down,table.hide-for-large-only,table.hide-for-large-up,table.hide-for-large,table.show-for-large-down,table.hide-for-xlarge-only,table.hide-for-xlarge-up,table.hide-for-xlarge,table.show-for-xlarge-down,table.hide-for-xxlarge-only,table.hide-for-xxlarge-up,table.hide-for-xxlarge,table.show-for-xxlarge-down{display:table !important}thead.show-for-small-only,thead.show-for-small-up,thead.show-for-small,thead.show-for-small-down,thead.hide-for-medium-only,thead.hide-for-medium-up,thead.hide-for-medium,thead.show-for-medium-down,thead.hide-for-large-only,thead.hide-for-large-up,thead.hide-for-large,thead.show-for-large-down,thead.hide-for-xlarge-only,thead.hide-for-xlarge-up,thead.hide-for-xlarge,thead.show-for-xlarge-down,thead.hide-for-xxlarge-only,thead.hide-for-xxlarge-up,thead.hide-for-xxlarge,thead.show-for-xxlarge-down{display:table-header-group !important}tbody.show-for-small-only,tbody.show-for-small-up,tbody.show-for-small,tbody.show-for-small-down,tbody.hide-for-medium-only,tbody.hide-for-medium-up,tbody.hide-for-medium,tbody.show-for-medium-down,tbody.hide-for-large-only,tbody.hide-for-large-up,tbody.hide-for-large,tbody.show-for-large-down,tbody.hide-for-xlarge-only,tbody.hide-for-xlarge-up,tbody.hide-for-xlarge,tbody.show-for-xlarge-down,tbody.hide-for-xxlarge-only,tbody.hide-for-xxlarge-up,tbody.hide-for-xxlarge,tbody.show-for-xxlarge-down{display:table-row-group !important}tr.show-for-small-only,tr.show-for-small-up,tr.show-for-small,tr.show-for-small-down,tr.hide-for-medium-only,tr.hide-for-medium-up,tr.hide-for-medium,tr.show-for-medium-down,tr.hide-for-large-only,tr.hide-for-large-up,tr.hide-for-large,tr.show-for-large-down,tr.hide-for-xlarge-only,tr.hide-for-xlarge-up,tr.hide-for-xlarge,tr.show-for-xlarge-down,tr.hide-for-xxlarge-only,tr.hide-for-xxlarge-up,tr.hide-for-xxlarge,tr.show-for-xxlarge-down{display:table-row}th.show-for-small-only,td.show-for-small-only,th.show-for-small-up,td.show-for-small-up,th.show-for-small,td.show-for-small,th.show-for-small-down,td.show-for-small-down,th.hide-for-medium-only,td.hide-for-medium-only,th.hide-for-medium-up,td.hide-for-medium-up,th.hide-for-medium,td.hide-for-medium,th.show-for-medium-down,td.show-for-medium-down,th.hide-for-large-only,td.hide-for-large-only,th.hide-for-large-up,td.hide-for-large-up,th.hide-for-large,td.hide-for-large,th.show-for-large-down,td.show-for-large-down,th.hide-for-xlarge-only,td.hide-for-xlarge-only,th.hide-for-xlarge-up,td.hide-for-xlarge-up,th.hide-for-xlarge,td.hide-for-xlarge,th.show-for-xlarge-down,td.show-for-xlarge-down,th.hide-for-xxlarge-only,td.hide-for-xxlarge-only,th.hide-for-xxlarge-up,td.hide-for-xxlarge-up,th.hide-for-xxlarge,td.hide-for-xxlarge,th.show-for-xxlarge-down,td.show-for-xxlarge-down{display:table-cell !important}}@media only screen and (min-width: 40.0625em){.hide-for-small-only,.show-for-small-up,.hide-for-small,.hide-for-small-down,.show-for-medium-only,.show-for-medium-up,.show-for-medium,.show-for-medium-down,.hide-for-large-only,.hide-for-large-up,.hide-for-large,.show-for-large-down,.hide-for-xlarge-only,.hide-for-xlarge-up,.hide-for-xlarge,.show-for-xlarge-down,.hide-for-xxlarge-only,.hide-for-xxlarge-up,.hide-for-xxlarge,.show-for-xxlarge-down{display:inherit !important}.show-for-small-only,.hide-for-small-up,.show-for-small,.show-for-small-down,.hide-for-medium-only,.hide-for-medium-up,.hide-for-medium,.hide-for-medium-down,.show-for-large-only,.show-for-large-up,.show-for-large,.hide-for-large-down,.show-for-xlarge-only,.show-for-xlarge-up,.show-for-xlarge,.hide-for-xlarge-down,.show-for-xxlarge-only,.show-for-xxlarge-up,.show-for-xxlarge,.hide-for-xxlarge-down{display:none !important}.hidden-for-small-only,.visible-for-small-up,.hidden-for-small,.hidden-for-small-down,.visible-for-medium-only,.visible-for-medium-up,.visible-for-medium,.visible-for-medium-down,.hidden-for-large-only,.hidden-for-large-up,.hidden-for-large,.visible-for-large-down,.hidden-for-xlarge-only,.hidden-for-xlarge-up,.hidden-for-xlarge,.visible-for-xlarge-down,.hidden-for-xxlarge-only,.hidden-for-xxlarge-up,.hidden-for-xxlarge,.visible-for-xxlarge-down{position:static !important;height:auto;width:auto;overflow:visible;clip:auto}.visible-for-small-only,.hidden-for-small-up,.visible-for-small,.visible-for-small-down,.hidden-for-medium-only,.hidden-for-medium-up,.hidden-for-medium,.hidden-for-medium-down,.visible-for-large-only,.visible-for-large-up,.visible-for-large,.hidden-for-large-down,.visible-for-xlarge-only,.visible-for-xlarge-up,.visible-for-xlarge,.hidden-for-xlarge-down,.visible-for-xxlarge-only,.visible-for-xxlarge-up,.visible-for-xxlarge,.hidden-for-xxlarge-down{clip:rect(1px, 1px, 1px, 1px);height:1px;overflow:hidden;position:absolute !important;width:1px}table.hide-for-small-only,table.show-for-small-up,table.hide-for-small,table.hide-for-small-down,table.show-for-medium-only,table.show-for-medium-up,table.show-for-medium,table.show-for-medium-down,table.hide-for-large-only,table.hide-for-large-up,table.hide-for-large,table.show-for-large-down,table.hide-for-xlarge-only,table.hide-for-xlarge-up,table.hide-for-xlarge,table.show-for-xlarge-down,table.hide-for-xxlarge-only,table.hide-for-xxlarge-up,table.hide-for-xxlarge,table.show-for-xxlarge-down{display:table !important}thead.hide-for-small-only,thead.show-for-small-up,thead.hide-for-small,thead.hide-for-small-down,thead.show-for-medium-only,thead.show-for-medium-up,thead.show-for-medium,thead.show-for-medium-down,thead.hide-for-large-only,thead.hide-for-large-up,thead.hide-for-large,thead.show-for-large-down,thead.hide-for-xlarge-only,thead.hide-for-xlarge-up,thead.hide-for-xlarge,thead.show-for-xlarge-down,thead.hide-for-xxlarge-only,thead.hide-for-xxlarge-up,thead.hide-for-xxlarge,thead.show-for-xxlarge-down{display:table-header-group !important}tbody.hide-for-small-only,tbody.show-for-small-up,tbody.hide-for-small,tbody.hide-for-small-down,tbody.show-for-medium-only,tbody.show-for-medium-up,tbody.show-for-medium,tbody.show-for-medium-down,tbody.hide-for-large-only,tbody.hide-for-large-up,tbody.hide-for-large,tbody.show-for-large-down,tbody.hide-for-xlarge-only,tbody.hide-for-xlarge-up,tbody.hide-for-xlarge,tbody.show-for-xlarge-down,tbody.hide-for-xxlarge-only,tbody.hide-for-xxlarge-up,tbody.hide-for-xxlarge,tbody.show-for-xxlarge-down{display:table-row-group !important}tr.hide-for-small-only,tr.show-for-small-up,tr.hide-for-small,tr.hide-for-small-down,tr.show-for-medium-only,tr.show-for-medium-up,tr.show-for-medium,tr.show-for-medium-down,tr.hide-for-large-only,tr.hide-for-large-up,tr.hide-for-large,tr.show-for-large-down,tr.hide-for-xlarge-only,tr.hide-for-xlarge-up,tr.hide-for-xlarge,tr.show-for-xlarge-down,tr.hide-for-xxlarge-only,tr.hide-for-xxlarge-up,tr.hide-for-xxlarge,tr.show-for-xxlarge-down{display:table-row}th.hide-for-small-only,td.hide-for-small-only,th.show-for-small-up,td.show-for-small-up,th.hide-for-small,td.hide-for-small,th.hide-for-small-down,td.hide-for-small-down,th.show-for-medium-only,td.show-for-medium-only,th.show-for-medium-up,td.show-for-medium-up,th.show-for-medium,td.show-for-medium,th.show-for-medium-down,td.show-for-medium-down,th.hide-for-large-only,td.hide-for-large-only,th.hide-for-large-up,td.hide-for-large-up,th.hide-for-large,td.hide-for-large,th.show-for-large-down,td.show-for-large-down,th.hide-for-xlarge-only,td.hide-for-xlarge-only,th.hide-for-xlarge-up,td.hide-for-xlarge-up,th.hide-for-xlarge,td.hide-for-xlarge,th.show-for-xlarge-down,td.show-for-xlarge-down,th.hide-for-xxlarge-only,td.hide-for-xxlarge-only,th.hide-for-xxlarge-up,td.hide-for-xxlarge-up,th.hide-for-xxlarge,td.hide-for-xxlarge,th.show-for-xxlarge-down,td.show-for-xxlarge-down{display:table-cell !important}}@media only screen and (min-width: 64.0625em){.hide-for-small-only,.show-for-small-up,.hide-for-small,.hide-for-small-down,.hide-for-medium-only,.show-for-medium-up,.hide-for-medium,.hide-for-medium-down,.show-for-large-only,.show-for-large-up,.show-for-large,.show-for-large-down,.hide-for-xlarge-only,.hide-for-xlarge-up,.hide-for-xlarge,.show-for-xlarge-down,.hide-for-xxlarge-only,.hide-for-xxlarge-up,.hide-for-xxlarge,.show-for-xxlarge-down{display:inherit !important}.show-for-small-only,.hide-for-small-up,.show-for-small,.show-for-small-down,.show-for-medium-only,.hide-for-medium-up,.show-for-medium,.show-for-medium-down,.hide-for-large-only,.hide-for-large-up,.hide-for-large,.hide-for-large-down,.show-for-xlarge-only,.show-for-xlarge-up,.show-for-xlarge,.hide-for-xlarge-down,.show-for-xxlarge-only,.show-for-xxlarge-up,.show-for-xxlarge,.hide-for-xxlarge-down{display:none !important}.hidden-for-small-only,.visible-for-small-up,.hidden-for-small,.hidden-for-small-down,.hidden-for-medium-only,.visible-for-medium-up,.hidden-for-medium,.hidden-for-medium-down,.visible-for-large-only,.visible-for-large-up,.visible-for-large,.visible-for-large-down,.hidden-for-xlarge-only,.hidden-for-xlarge-up,.hidden-for-xlarge,.visible-for-xlarge-down,.hidden-for-xxlarge-only,.hidden-for-xxlarge-up,.hidden-for-xxlarge,.visible-for-xxlarge-down{position:static !important;height:auto;width:auto;overflow:visible;clip:auto}.visible-for-small-only,.hidden-for-small-up,.visible-for-small,.visible-for-small-down,.visible-for-medium-only,.hidden-for-medium-up,.visible-for-medium,.visible-for-medium-down,.hidden-for-large-only,.hidden-for-large-up,.hidden-for-large,.hidden-for-large-down,.visible-for-xlarge-only,.visible-for-xlarge-up,.visible-for-xlarge,.hidden-for-xlarge-down,.visible-for-xxlarge-only,.visible-for-xxlarge-up,.visible-for-xxlarge,.hidden-for-xxlarge-down{clip:rect(1px, 1px, 1px, 1px);height:1px;overflow:hidden;position:absolute !important;width:1px}table.hide-for-small-only,table.show-for-small-up,table.hide-for-small,table.hide-for-small-down,table.hide-for-medium-only,table.show-for-medium-up,table.hide-for-medium,table.hide-for-medium-down,table.show-for-large-only,table.show-for-large-up,table.show-for-large,table.show-for-large-down,table.hide-for-xlarge-only,table.hide-for-xlarge-up,table.hide-for-xlarge,table.show-for-xlarge-down,table.hide-for-xxlarge-only,table.hide-for-xxlarge-up,table.hide-for-xxlarge,table.show-for-xxlarge-down{display:table !important}thead.hide-for-small-only,thead.show-for-small-up,thead.hide-for-small,thead.hide-for-small-down,thead.hide-for-medium-only,thead.show-for-medium-up,thead.hide-for-medium,thead.hide-for-medium-down,thead.show-for-large-only,thead.show-for-large-up,thead.show-for-large,thead.show-for-large-down,thead.hide-for-xlarge-only,thead.hide-for-xlarge-up,thead.hide-for-xlarge,thead.show-for-xlarge-down,thead.hide-for-xxlarge-only,thead.hide-for-xxlarge-up,thead.hide-for-xxlarge,thead.show-for-xxlarge-down{display:table-header-group !important}tbody.hide-for-small-only,tbody.show-for-small-up,tbody.hide-for-small,tbody.hide-for-small-down,tbody.hide-for-medium-only,tbody.show-for-medium-up,tbody.hide-for-medium,tbody.hide-for-medium-down,tbody.show-for-large-only,tbody.show-for-large-up,tbody.show-for-large,tbody.show-for-large-down,tbody.hide-for-xlarge-only,tbody.hide-for-xlarge-up,tbody.hide-for-xlarge,tbody.show-for-xlarge-down,tbody.hide-for-xxlarge-only,tbody.hide-for-xxlarge-up,tbody.hide-for-xxlarge,tbody.show-for-xxlarge-down{display:table-row-group !important}tr.hide-for-small-only,tr.show-for-small-up,tr.hide-for-small,tr.hide-for-small-down,tr.hide-for-medium-only,tr.show-for-medium-up,tr.hide-for-medium,tr.hide-for-medium-down,tr.show-for-large-only,tr.show-for-large-up,tr.show-for-large,tr.show-for-large-down,tr.hide-for-xlarge-only,tr.hide-for-xlarge-up,tr.hide-for-xlarge,tr.show-for-xlarge-down,tr.hide-for-xxlarge-only,tr.hide-for-xxlarge-up,tr.hide-for-xxlarge,tr.show-for-xxlarge-down{display:table-row}th.hide-for-small-only,td.hide-for-small-only,th.show-for-small-up,td.show-for-small-up,th.hide-for-small,td.hide-for-small,th.hide-for-small-down,td.hide-for-small-down,th.hide-for-medium-only,td.hide-for-medium-only,th.show-for-medium-up,td.show-for-medium-up,th.hide-for-medium,td.hide-for-medium,th.hide-for-medium-down,td.hide-for-medium-down,th.show-for-large-only,td.show-for-large-only,th.show-for-large-up,td.show-for-large-up,th.show-for-large,td.show-for-large,th.show-for-large-down,td.show-for-large-down,th.hide-for-xlarge-only,td.hide-for-xlarge-only,th.hide-for-xlarge-up,td.hide-for-xlarge-up,th.hide-for-xlarge,td.hide-for-xlarge,th.show-for-xlarge-down,td.show-for-xlarge-down,th.hide-for-xxlarge-only,td.hide-for-xxlarge-only,th.hide-for-xxlarge-up,td.hide-for-xxlarge-up,th.hide-for-xxlarge,td.hide-for-xxlarge,th.show-for-xxlarge-down,td.show-for-xxlarge-down{display:table-cell !important}}@media only screen and (min-width: 90.0625em){.hide-for-small-only,.show-for-small-up,.hide-for-small,.hide-for-small-down,.hide-for-medium-only,.show-for-medium-up,.hide-for-medium,.hide-for-medium-down,.hide-for-large-only,.show-for-large-up,.hide-for-large,.hide-for-large-down,.show-for-xlarge-only,.show-for-xlarge-up,.show-for-xlarge,.show-for-xlarge-down,.hide-for-xxlarge-only,.hide-for-xxlarge-up,.hide-for-xxlarge,.show-for-xxlarge-down{display:inherit !important}.show-for-small-only,.hide-for-small-up,.show-for-small,.show-for-small-down,.show-for-medium-only,.hide-for-medium-up,.show-for-medium,.show-for-medium-down,.show-for-large-only,.hide-for-large-up,.show-for-large,.show-for-large-down,.hide-for-xlarge-only,.hide-for-xlarge-up,.hide-for-xlarge,.hide-for-xlarge-down,.show-for-xxlarge-only,.show-for-xxlarge-up,.show-for-xxlarge,.hide-for-xxlarge-down{display:none !important}.hidden-for-small-only,.visible-for-small-up,.hidden-for-small,.hidden-for-small-down,.hidden-for-medium-only,.visible-for-medium-up,.hidden-for-medium,.hidden-for-medium-down,.hidden-for-large-only,.visible-for-large-up,.hidden-for-large,.hidden-for-large-down,.visible-for-xlarge-only,.visible-for-xlarge-up,.visible-for-xlarge,.visible-for-xlarge-down,.hidden-for-xxlarge-only,.hidden-for-xxlarge-up,.hidden-for-xxlarge,.visible-for-xxlarge-down{position:static !important;height:auto;width:auto;overflow:visible;clip:auto}.visible-for-small-only,.hidden-for-small-up,.visible-for-small,.visible-for-small-down,.visible-for-medium-only,.hidden-for-medium-up,.visible-for-medium,.visible-for-medium-down,.visible-for-large-only,.hidden-for-large-up,.visible-for-large,.visible-for-large-down,.hidden-for-xlarge-only,.hidden-for-xlarge-up,.hidden-for-xlarge,.hidden-for-xlarge-down,.visible-for-xxlarge-only,.visible-for-xxlarge-up,.visible-for-xxlarge,.hidden-for-xxlarge-down{clip:rect(1px, 1px, 1px, 1px);height:1px;overflow:hidden;position:absolute !important;width:1px}table.hide-for-small-only,table.show-for-small-up,table.hide-for-small,table.hide-for-small-down,table.hide-for-medium-only,table.show-for-medium-up,table.hide-for-medium,table.hide-for-medium-down,table.hide-for-large-only,table.show-for-large-up,table.hide-for-large,table.hide-for-large-down,table.show-for-xlarge-only,table.show-for-xlarge-up,table.show-for-xlarge,table.show-for-xlarge-down,table.hide-for-xxlarge-only,table.hide-for-xxlarge-up,table.hide-for-xxlarge,table.show-for-xxlarge-down{display:table !important}thead.hide-for-small-only,thead.show-for-small-up,thead.hide-for-small,thead.hide-for-small-down,thead.hide-for-medium-only,thead.show-for-medium-up,thead.hide-for-medium,thead.hide-for-medium-down,thead.hide-for-large-only,thead.show-for-large-up,thead.hide-for-large,thead.hide-for-large-down,thead.show-for-xlarge-only,thead.show-for-xlarge-up,thead.show-for-xlarge,thead.show-for-xlarge-down,thead.hide-for-xxlarge-only,thead.hide-for-xxlarge-up,thead.hide-for-xxlarge,thead.show-for-xxlarge-down{display:table-header-group !important}tbody.hide-for-small-only,tbody.show-for-small-up,tbody.hide-for-small,tbody.hide-for-small-down,tbody.hide-for-medium-only,tbody.show-for-medium-up,tbody.hide-for-medium,tbody.hide-for-medium-down,tbody.hide-for-large-only,tbody.show-for-large-up,tbody.hide-for-large,tbody.hide-for-large-down,tbody.show-for-xlarge-only,tbody.show-for-xlarge-up,tbody.show-for-xlarge,tbody.show-for-xlarge-down,tbody.hide-for-xxlarge-only,tbody.hide-for-xxlarge-up,tbody.hide-for-xxlarge,tbody.show-for-xxlarge-down{display:table-row-group !important}tr.hide-for-small-only,tr.show-for-small-up,tr.hide-for-small,tr.hide-for-small-down,tr.hide-for-medium-only,tr.show-for-medium-up,tr.hide-for-medium,tr.hide-for-medium-down,tr.hide-for-large-only,tr.show-for-large-up,tr.hide-for-large,tr.hide-for-large-down,tr.show-for-xlarge-only,tr.show-for-xlarge-up,tr.show-for-xlarge,tr.show-for-xlarge-down,tr.hide-for-xxlarge-only,tr.hide-for-xxlarge-up,tr.hide-for-xxlarge,tr.show-for-xxlarge-down{display:table-row}th.hide-for-small-only,td.hide-for-small-only,th.show-for-small-up,td.show-for-small-up,th.hide-for-small,td.hide-for-small,th.hide-for-small-down,td.hide-for-small-down,th.hide-for-medium-only,td.hide-for-medium-only,th.show-for-medium-up,td.show-for-medium-up,th.hide-for-medium,td.hide-for-medium,th.hide-for-medium-down,td.hide-for-medium-down,th.hide-for-large-only,td.hide-for-large-only,th.show-for-large-up,td.show-for-large-up,th.hide-for-large,td.hide-for-large,th.hide-for-large-down,td.hide-for-large-down,th.show-for-xlarge-only,td.show-for-xlarge-only,th.show-for-xlarge-up,td.show-for-xlarge-up,th.show-for-xlarge,td.show-for-xlarge,th.show-for-xlarge-down,td.show-for-xlarge-down,th.hide-for-xxlarge-only,td.hide-for-xxlarge-only,th.hide-for-xxlarge-up,td.hide-for-xxlarge-up,th.hide-for-xxlarge,td.hide-for-xxlarge,th.show-for-xxlarge-down,td.show-for-xxlarge-down{display:table-cell !important}}@media only screen and (min-width: 120.0625em){.hide-for-small-only,.show-for-small-up,.hide-for-small,.hide-for-small-down,.hide-for-medium-only,.show-for-medium-up,.hide-for-medium,.hide-for-medium-down,.hide-for-large-only,.show-for-large-up,.hide-for-large,.hide-for-large-down,.hide-for-xlarge-only,.show-for-xlarge-up,.hide-for-xlarge,.hide-for-xlarge-down,.show-for-xxlarge-only,.show-for-xxlarge-up,.show-for-xxlarge,.show-for-xxlarge-down{display:inherit !important}.show-for-small-only,.hide-for-small-up,.show-for-small,.show-for-small-down,.show-for-medium-only,.hide-for-medium-up,.show-for-medium,.show-for-medium-down,.show-for-large-only,.hide-for-large-up,.show-for-large,.show-for-large-down,.show-for-xlarge-only,.hide-for-xlarge-up,.show-for-xlarge,.show-for-xlarge-down,.hide-for-xxlarge-only,.hide-for-xxlarge-up,.hide-for-xxlarge,.hide-for-xxlarge-down{display:none !important}.hidden-for-small-only,.visible-for-small-up,.hidden-for-small,.hidden-for-small-down,.hidden-for-medium-only,.visible-for-medium-up,.hidden-for-medium,.hidden-for-medium-down,.hidden-for-large-only,.visible-for-large-up,.hidden-for-large,.hidden-for-large-down,.hidden-for-xlarge-only,.visible-for-xlarge-up,.hidden-for-xlarge,.hidden-for-xlarge-down,.visible-for-xxlarge-only,.visible-for-xxlarge-up,.visible-for-xxlarge,.visible-for-xxlarge-down{position:static !important;height:auto;width:auto;overflow:visible;clip:auto}.visible-for-small-only,.hidden-for-small-up,.visible-for-small,.visible-for-small-down,.visible-for-medium-only,.hidden-for-medium-up,.visible-for-medium,.visible-for-medium-down,.visible-for-large-only,.hidden-for-large-up,.visible-for-large,.visible-for-large-down,.visible-for-xlarge-only,.hidden-for-xlarge-up,.visible-for-xlarge,.visible-for-xlarge-down,.hidden-for-xxlarge-only,.hidden-for-xxlarge-up,.hidden-for-xxlarge,.hidden-for-xxlarge-down{clip:rect(1px, 1px, 1px, 1px);height:1px;overflow:hidden;position:absolute !important;width:1px}table.hide-for-small-only,table.show-for-small-up,table.hide-for-small,table.hide-for-small-down,table.hide-for-medium-only,table.show-for-medium-up,table.hide-for-medium,table.hide-for-medium-down,table.hide-for-large-only,table.show-for-large-up,table.hide-for-large,table.hide-for-large-down,table.hide-for-xlarge-only,table.show-for-xlarge-up,table.hide-for-xlarge,table.hide-for-xlarge-down,table.show-for-xxlarge-only,table.show-for-xxlarge-up,table.show-for-xxlarge,table.show-for-xxlarge-down{display:table !important}thead.hide-for-small-only,thead.show-for-small-up,thead.hide-for-small,thead.hide-for-small-down,thead.hide-for-medium-only,thead.show-for-medium-up,thead.hide-for-medium,thead.hide-for-medium-down,thead.hide-for-large-only,thead.show-for-large-up,thead.hide-for-large,thead.hide-for-large-down,thead.hide-for-xlarge-only,thead.show-for-xlarge-up,thead.hide-for-xlarge,thead.hide-for-xlarge-down,thead.show-for-xxlarge-only,thead.show-for-xxlarge-up,thead.show-for-xxlarge,thead.show-for-xxlarge-down{display:table-header-group !important}tbody.hide-for-small-only,tbody.show-for-small-up,tbody.hide-for-small,tbody.hide-for-small-down,tbody.hide-for-medium-only,tbody.show-for-medium-up,tbody.hide-for-medium,tbody.hide-for-medium-down,tbody.hide-for-large-only,tbody.show-for-large-up,tbody.hide-for-large,tbody.hide-for-large-down,tbody.hide-for-xlarge-only,tbody.show-for-xlarge-up,tbody.hide-for-xlarge,tbody.hide-for-xlarge-down,tbody.show-for-xxlarge-only,tbody.show-for-xxlarge-up,tbody.show-for-xxlarge,tbody.show-for-xxlarge-down{display:table-row-group !important}tr.hide-for-small-only,tr.show-for-small-up,tr.hide-for-small,tr.hide-for-small-down,tr.hide-for-medium-only,tr.show-for-medium-up,tr.hide-for-medium,tr.hide-for-medium-down,tr.hide-for-large-only,tr.show-for-large-up,tr.hide-for-large,tr.hide-for-large-down,tr.hide-for-xlarge-only,tr.show-for-xlarge-up,tr.hide-for-xlarge,tr.hide-for-xlarge-down,tr.show-for-xxlarge-only,tr.show-for-xxlarge-up,tr.show-for-xxlarge,tr.show-for-xxlarge-down{display:table-row}th.hide-for-small-only,td.hide-for-small-only,th.show-for-small-up,td.show-for-small-up,th.hide-for-small,td.hide-for-small,th.hide-for-small-down,td.hide-for-small-down,th.hide-for-medium-only,td.hide-for-medium-only,th.show-for-medium-up,td.show-for-medium-up,th.hide-for-medium,td.hide-for-medium,th.hide-for-medium-down,td.hide-for-medium-down,th.hide-for-large-only,td.hide-for-large-only,th.show-for-large-up,td.show-for-large-up,th.hide-for-large,td.hide-for-large,th.hide-for-large-down,td.hide-for-large-down,th.hide-for-xlarge-only,td.hide-for-xlarge-only,th.show-for-xlarge-up,td.show-for-xlarge-up,th.hide-for-xlarge,td.hide-for-xlarge,th.hide-for-xlarge-down,td.hide-for-xlarge-down,th.show-for-xxlarge-only,td.show-for-xxlarge-only,th.show-for-xxlarge-up,td.show-for-xxlarge-up,th.show-for-xxlarge,td.show-for-xxlarge,th.show-for-xxlarge-down,td.show-for-xxlarge-down{display:table-cell !important}}.show-for-landscape,.hide-for-portrait{display:inherit !important}.hide-for-landscape,.show-for-portrait{display:none !important}table.hide-for-landscape,table.show-for-portrait{display:table !important}thead.hide-for-landscape,thead.show-for-portrait{display:table-header-group !important}tbody.hide-for-landscape,tbody.show-for-portrait{display:table-row-group !important}tr.hide-for-landscape,tr.show-for-portrait{display:table-row !important}td.hide-for-landscape,td.show-for-portrait,th.hide-for-landscape,th.show-for-portrait{display:table-cell !important}@media only screen and (orientation: landscape){.show-for-landscape,.hide-for-portrait{display:inherit !important}.hide-for-landscape,.show-for-portrait{display:none !important}table.show-for-landscape,table.hide-for-portrait{display:table !important}thead.show-for-landscape,thead.hide-for-portrait{display:table-header-group !important}tbody.show-for-landscape,tbody.hide-for-portrait{display:table-row-group !important}tr.show-for-landscape,tr.hide-for-portrait{display:table-row !important}td.show-for-landscape,td.hide-for-portrait,th.show-for-landscape,th.hide-for-portrait{display:table-cell !important}}@media only screen and (orientation: portrait){.show-for-portrait,.hide-for-landscape{display:inherit !important}.hide-for-portrait,.show-for-landscape{display:none !important}table.show-for-portrait,table.hide-for-landscape{display:table !important}thead.show-for-portrait,thead.hide-for-landscape{display:table-header-group !important}tbody.show-for-portrait,tbody.hide-for-landscape{display:table-row-group !important}tr.show-for-portrait,tr.hide-for-landscape{display:table-row !important}td.show-for-portrait,td.hide-for-landscape,th.show-for-portrait,th.hide-for-landscape{display:table-cell !important}}.show-for-touch{display:none !important}.hide-for-touch{display:inherit !important}.touch .show-for-touch{display:inherit !important}.touch .hide-for-touch{display:none !important}table.hide-for-touch{display:table !important}.touch table.show-for-touch{display:table !important}thead.hide-for-touch{display:table-header-group !important}.touch thead.show-for-touch{display:table-header-group !important}tbody.hide-for-touch{display:table-row-group !important}.touch tbody.show-for-touch{display:table-row-group !important}tr.hide-for-touch{display:table-row !important}.touch tr.show-for-touch{display:table-row !important}td.hide-for-touch{display:table-cell !important}.touch td.show-for-touch{display:table-cell !important}th.hide-for-touch{display:table-cell !important}.touch th.show-for-touch{display:table-cell !important}.show-for-sr{clip:rect(1px, 1px, 1px, 1px);height:1px;overflow:hidden;position:absolute !important;width:1px}.show-on-focus{clip:rect(1px, 1px, 1px, 1px);height:1px;overflow:hidden;position:absolute !important;width:1px}.show-on-focus:focus,.show-on-focus:active{position:static !important;height:auto;width:auto;overflow:visible;clip:auto}.print-only{display:none !important}@media print{*{background:transparent !important;box-shadow:none !important;color:#000 !important;text-shadow:none !important}.show-for-print{display:block}.hide-for-print{display:none}table.show-for-print{display:table !important}thead.show-for-print{display:table-header-group !important}tbody.show-for-print{display:table-row-group !important}tr.show-for-print{display:table-row !important}td.show-for-print{display:table-cell !important}th.show-for-print{display:table-cell !important}a,a:visited{text-decoration:underline}a[href]:after{content:" (" attr(href) ")"}abbr[title]:after{content:" (" attr(title) ")"}.ir a:after,a[href^="javascript:"]:after,a[href^="#"]:after{content:""}pre,blockquote{border:1px solid #999;page-break-inside:avoid}thead{display:table-header-group}tr,img{page-break-inside:avoid}img{max-width:100% !important}@page{margin:.5cm}p,h2,h3{orphans:3;widows:3}h2,h3{page-break-after:avoid}.hide-on-print{display:none !important}.print-only{display:block !important}.hide-for-print{display:none !important}.show-for-print{display:inherit !important}}@media print{.show-for-print{display:block}.hide-for-print{display:none}table.show-for-print{display:table !important}thead.show-for-print{display:table-header-group !important}tbody.show-for-print{display:table-row-group !important}tr.show-for-print{display:table-row !important}td.show-for-print{display:table-cell !important}th.show-for-print{display:table-cell !important}}@media not print{.show-for-print{display:none !important}}
';

        if ($withHtmlTag) {
            $result .= '
</style>
';
        }

        return $result;
    }

    /**
     * Returns the Foundation Javascript code.
     *
     * @param bool $withHtmlTag
     *
     * @return string The JavaScript code.
     */
    public static function getFoundationJs($withHtmlTag = false) {
        $result = '';

        if ($withHtmlTag) {
            $result .= '
<script type="text/javascript">
';
        }

        $result .= '/*
 * Foundation Responsive Library
 * http://foundation.zurb.com
 * Copyright 2014, ZURB
 * Free to use under the MIT license.
 * http://www.opensource.org/licenses/mit-license.php
*/
!function(t,e,i,s){"use strict";function n(t){return("string"==typeof t||t instanceof String)&&(t=t.replace(/^[\'\\/"]+|(;\s?})+|[\'\\/"]+$/g,"")),t}var a=function(e){for(var i=e.length,s=t("head");i--;)0===s.has("."+e[i]).length&&s.append(\'<meta class="\'+e[i]+\'" />\')};a(["foundation-mq-small","foundation-mq-small-only","foundation-mq-medium","foundation-mq-medium-only","foundation-mq-large","foundation-mq-large-only","foundation-mq-xlarge","foundation-mq-xlarge-only","foundation-mq-xxlarge","foundation-data-attribute-namespace"]),t(function(){"undefined"!=typeof FastClick&&"undefined"!=typeof i.body&&FastClick.attach(i.body)});var o=function(e,s){if("string"==typeof e){if(s){var n;if(s.jquery){if(n=s[0],!n)return s}else n=s;return t(n.querySelectorAll(e))}return t(i.querySelectorAll(e))}return t(e,s)},r=function(t){var e=[];return t||e.push("data"),this.namespace.length>0&&e.push(this.namespace),e.push(this.name),e.join("-")},l=function(t){for(var e=t.split("-"),i=e.length,s=[];i--;)0!==i?s.push(e[i]):this.namespace.length>0?s.push(this.namespace,e[i]):s.push(e[i]);return s.reverse().join("-")},d=function(e,i){var s=this,n=function(){var n=o(this),a=!n.data(s.attr_name(!0)+"-init");n.data(s.attr_name(!0)+"-init",t.extend({},s.settings,i||e,s.data_options(n))),a&&s.events(this)};return o(this.scope).is("["+this.attr_name()+"]")?n.call(this.scope):o("["+this.attr_name()+"]",this.scope).each(n),"string"==typeof e?this[e].call(this,i):void 0},c=function(t,e){function i(){e(t[0])}function s(){if(this.one("load",i),/MSIE (\d+\.\d+);/.test(navigator.userAgent)){var t=this.attr("src"),e=t.match(/\?/)?"&":"?";e+="random="+(new Date).getTime(),this.attr("src",t+e)}}return t.attr("src")?void(t[0].complete||4===t[0].readyState?i():s.call(t)):void i()};/*! matchMedia() polyfill - Test a CSS media type/query in JS. Authors & copyright (c) 2012: Scott Jehl, Paul Irish, Nicholas Zakas, David Knight. Dual MIT/BSD license */
e.matchMedia||(e.matchMedia=function(){var t=e.styleMedia||e.media;if(!t){var s=i.createElement("style"),n=i.getElementsByTagName("script")[0],a=null;s.type="text/css",s.id="matchmediajs-test",n.parentNode.insertBefore(s,n),a="getComputedStyle"in e&&e.getComputedStyle(s,null)||s.currentStyle,t={matchMedium:function(t){var e="@media "+t+"{ #matchmediajs-test { width: 1px; } }";return s.styleSheet?s.styleSheet.cssText=e:s.textContent=e,"1px"===a.width}}}return function(e){return{matches:t.matchMedium(e||"all"),media:e||"all"}}}()),/*
   * jquery.requestAnimationFrame
   * https://github.com/gnarf37/jquery-requestAnimationFrame
   * Requires jQuery 1.8+
   *
   * Copyright (c) 2012 Corey Frang
   * Licensed under the MIT license.
   */
function(t){function i(){s&&(o(i),l&&t.fx.tick())}for(var s,n=0,a=["webkit","moz"],o=e.requestAnimationFrame,r=e.cancelAnimationFrame,l="undefined"!=typeof t.fx;n<a.length&&!o;n++)o=e[a[n]+"RequestAnimationFrame"],r=r||e[a[n]+"CancelAnimationFrame"]||e[a[n]+"CancelRequestAnimationFrame"];o?(e.requestAnimationFrame=o,e.cancelAnimationFrame=r,l&&(t.fx.timer=function(e){e()&&t.timers.push(e)&&!s&&(s=!0,i())},t.fx.stop=function(){s=!1})):(e.requestAnimationFrame=function(t){var i=(new Date).getTime(),s=Math.max(0,16-(i-n)),a=e.setTimeout(function(){t(i+s)},s);return n=i+s,a},e.cancelAnimationFrame=function(t){clearTimeout(t)})}(t),e.Foundation={name:"Foundation",version:"5.5.2",media_queries:{small:o(".foundation-mq-small").css("font-family").replace(/^[\/\\\'"]+|(;\s?})+|[\/\\\'"]+$/g,""),"small-only":o(".foundation-mq-small-only").css("font-family").replace(/^[\/\\\'"]+|(;\s?})+|[\/\\\'"]+$/g,""),medium:o(".foundation-mq-medium").css("font-family").replace(/^[\/\\\'"]+|(;\s?})+|[\/\\\'"]+$/g,""),"medium-only":o(".foundation-mq-medium-only").css("font-family").replace(/^[\/\\\'"]+|(;\s?})+|[\/\\\'"]+$/g,""),large:o(".foundation-mq-large").css("font-family").replace(/^[\/\\\'"]+|(;\s?})+|[\/\\\'"]+$/g,""),"large-only":o(".foundation-mq-large-only").css("font-family").replace(/^[\/\\\'"]+|(;\s?})+|[\/\\\'"]+$/g,""),xlarge:o(".foundation-mq-xlarge").css("font-family").replace(/^[\/\\\'"]+|(;\s?})+|[\/\\\'"]+$/g,""),"xlarge-only":o(".foundation-mq-xlarge-only").css("font-family").replace(/^[\/\\\'"]+|(;\s?})+|[\/\\\'"]+$/g,""),xxlarge:o(".foundation-mq-xxlarge").css("font-family").replace(/^[\/\\\'"]+|(;\s?})+|[\/\\\'"]+$/g,"")},stylesheet:t("<style></style>").appendTo("head")[0].sheet,global:{namespace:s},init:function(t,i,s,n,a){var r=[t,s,n,a],l=[];if(this.rtl=/rtl/i.test(o("html").attr("dir")),this.scope=t||this.scope,this.set_namespace(),i&&"string"==typeof i&&!/reflow/i.test(i))this.libs.hasOwnProperty(i)&&l.push(this.init_lib(i,r));else for(var d in this.libs)l.push(this.init_lib(d,i));return o(e).load(function(){o(e).trigger("resize.fndtn.clearing").trigger("resize.fndtn.dropdown").trigger("resize.fndtn.equalizer").trigger("resize.fndtn.interchange").trigger("resize.fndtn.joyride").trigger("resize.fndtn.magellan").trigger("resize.fndtn.topbar").trigger("resize.fndtn.slider")}),t},init_lib:function(e,i){return this.libs.hasOwnProperty(e)?(this.patch(this.libs[e]),i&&i.hasOwnProperty(e)?("undefined"!=typeof this.libs[e].settings?t.extend(!0,this.libs[e].settings,i[e]):"undefined"!=typeof this.libs[e].defaults&&t.extend(!0,this.libs[e].defaults,i[e]),this.libs[e].init.apply(this.libs[e],[this.scope,i[e]])):(i=i instanceof Array?i:new Array(i),this.libs[e].init.apply(this.libs[e],i))):function(){}},patch:function(t){t.scope=this.scope,t.namespace=this.global.namespace,t.rtl=this.rtl,t.data_options=this.utils.data_options,t.attr_name=r,t.add_namespace=l,t.bindings=d,t.S=this.utils.S},inherit:function(t,e){for(var i=e.split(" "),s=i.length;s--;)this.utils.hasOwnProperty(i[s])&&(t[i[s]]=this.utils[i[s]])},set_namespace:function(){var e=this.global.namespace===s?t(".foundation-data-attribute-namespace").css("font-family"):this.global.namespace;this.global.namespace=e===s||/false/i.test(e)?"":e},libs:{},utils:{S:o,throttle:function(t,e){var i=null;return function(){var s=this,n=arguments;null==i&&(i=setTimeout(function(){t.apply(s,n),i=null},e))}},debounce:function(t,e,i){var s,n;return function(){var a=this,o=arguments,r=function(){s=null,i||(n=t.apply(a,o))},l=i&&!s;return clearTimeout(s),s=setTimeout(r,e),l&&(n=t.apply(a,o)),n}},data_options:function(e,i){function s(t){return!isNaN(t-0)&&null!==t&&""!==t&&t!==!1&&t!==!0}function n(e){return"string"==typeof e?t.trim(e):e}i=i||"options";var a,o,r,l={},d=function(t){var e=Foundation.global.namespace;return t.data(e.length>0?e+"-"+i:i)},c=d(e);if("object"==typeof c)return c;for(r=(c||":").split(";"),a=r.length;a--;)o=r[a].split(":"),o=[o[0],o.slice(1).join(":")],/true/i.test(o[1])&&(o[1]=!0),/false/i.test(o[1])&&(o[1]=!1),s(o[1])&&(o[1]=-1===o[1].indexOf(".")?parseInt(o[1],10):parseFloat(o[1])),2===o.length&&o[0].length>0&&(l[n(o[0])]=n(o[1]));return l},register_media:function(e,i){Foundation.media_queries[e]===s&&(t("head").append(\'<meta class="\'+i+\'"/>\'),Foundation.media_queries[e]=n(t("."+i).css("font-family")))},add_custom_rule:function(t,e){if(e===s&&Foundation.stylesheet)Foundation.stylesheet.insertRule(t,Foundation.stylesheet.cssRules.length);else{var i=Foundation.media_queries[e];i!==s&&Foundation.stylesheet.insertRule("@media "+Foundation.media_queries[e]+"{ "+t+" }",Foundation.stylesheet.cssRules.length)}},image_loaded:function(t,e){function i(t){for(var e=t.length,i=e-1;i>=0;i--)if(t.attr("height")===s)return!1;return!0}var n=this,a=t.length;(0===a||i(t))&&e(t),t.each(function(){c(n.S(this),function(){a-=1,0===a&&e(t)})})},random_str:function(){return this.fidx||(this.fidx=0),this.prefix=this.prefix||[this.name||"F",(+new Date).toString(36)].join("-"),this.prefix+(this.fidx++).toString(36)},match:function(t){return e.matchMedia(t).matches},is_small_up:function(){return this.match(Foundation.media_queries.small)},is_medium_up:function(){return this.match(Foundation.media_queries.medium)},is_large_up:function(){return this.match(Foundation.media_queries.large)},is_xlarge_up:function(){return this.match(Foundation.media_queries.xlarge)},is_xxlarge_up:function(){return this.match(Foundation.media_queries.xxlarge)},is_small_only:function(){return!(this.is_medium_up()||this.is_large_up()||this.is_xlarge_up()||this.is_xxlarge_up())},is_medium_only:function(){return this.is_medium_up()&&!this.is_large_up()&&!this.is_xlarge_up()&&!this.is_xxlarge_up()},is_large_only:function(){return this.is_medium_up()&&this.is_large_up()&&!this.is_xlarge_up()&&!this.is_xxlarge_up()},is_xlarge_only:function(){return this.is_medium_up()&&this.is_large_up()&&this.is_xlarge_up()&&!this.is_xxlarge_up()},is_xxlarge_only:function(){return this.is_medium_up()&&this.is_large_up()&&this.is_xlarge_up()&&this.is_xxlarge_up()}}},t.fn.foundation=function(){var t=Array.prototype.slice.call(arguments,0);return this.each(function(){return Foundation.init.apply(Foundation,[this].concat(t)),this})}}(jQuery,window,window.document),function(t,e){"use strict";Foundation.libs.slider={name:"slider",version:"5.5.2",settings:{start:0,end:100,step:1,precision:null,initial:null,display_selector:"",vertical:!1,trigger_input_change:!1,on_change:function(){}},cache:{},init:function(t,e,i){Foundation.inherit(this,"throttle"),this.bindings(e,i),this.reflow()},events:function(){var i=this;t(this.scope).off(".slider").on("mousedown.fndtn.slider touchstart.fndtn.slider pointerdown.fndtn.slider","["+i.attr_name()+"]:not(.disabled, [disabled]) .range-slider-handle",function(e){i.cache.active||(e.preventDefault(),i.set_active_slider(t(e.target)))}).on("mousemove.fndtn.slider touchmove.fndtn.slider pointermove.fndtn.slider",function(s){if(i.cache.active)if(s.preventDefault(),t.data(i.cache.active[0],"settings").vertical){var n=0;s.pageY||(n=e.scrollY),i.calculate_position(i.cache.active,i.get_cursor_position(s,"y")+n)}else i.calculate_position(i.cache.active,i.get_cursor_position(s,"x"))}).on("mouseup.fndtn.slider touchend.fndtn.slider pointerup.fndtn.slider",function(){i.remove_active_slider()}).on("change.fndtn.slider",function(){i.settings.on_change()}),i.S(e).on("resize.fndtn.slider",i.throttle(function(){i.reflow()},300)),this.S("["+this.attr_name()+"]").each(function(){var e=t(this),s=e.children(".range-slider-handle")[0],n=i.initialize_settings(s);""!=n.display_selector&&t(n.display_selector).each(function(){this.hasOwnProperty("value")&&t(this).change(function(){e.foundation("slider","set_value",t(this).val())})})})},get_cursor_position:function(t,e){var i,s="page"+e.toUpperCase(),n="client"+e.toUpperCase();return"undefined"!=typeof t[s]?i=t[s]:"undefined"!=typeof t.originalEvent[n]?i=t.originalEvent[n]:t.originalEvent.touches&&t.originalEvent.touches[0]&&"undefined"!=typeof t.originalEvent.touches[0][n]?i=t.originalEvent.touches[0][n]:t.currentPoint&&"undefined"!=typeof t.currentPoint[e]&&(i=t.currentPoint[e]),i},set_active_slider:function(t){this.cache.active=t},remove_active_slider:function(){this.cache.active=null},calculate_position:function(e,i){var s=this,n=t.data(e[0],"settings"),a=(t.data(e[0],"handle_l"),t.data(e[0],"handle_o"),t.data(e[0],"bar_l")),o=t.data(e[0],"bar_o");requestAnimationFrame(function(){var t;t=Foundation.rtl&&!n.vertical?s.limit_to((o+a-i)/a,0,1):s.limit_to((i-o)/a,0,1),t=n.vertical?1-t:t;var r=s.normalized_value(t,n.start,n.end,n.step,n.precision);s.set_ui(e,r)})},set_ui:function(e,i){var s=t.data(e[0],"settings"),n=t.data(e[0],"handle_l"),a=t.data(e[0],"bar_l"),o=this.normalized_percentage(i,s.start,s.end),r=o*(a-n)-1,l=100*o,d=e.parent(),c=e.parent().children("input[type=hidden]");Foundation.rtl&&!s.vertical&&(r=-r),r=s.vertical?-r+a-n+1:r,this.set_translate(e,r,s.vertical),s.vertical?e.siblings(".range-slider-active-segment").css("height",l+"%"):e.siblings(".range-slider-active-segment").css("width",l+"%"),d.attr(this.attr_name(),i).trigger("change.fndtn.slider"),c.val(i),s.trigger_input_change&&c.trigger("change.fndtn.slider"),e[0].hasAttribute("aria-valuemin")||e.attr({"aria-valuemin":s.start,"aria-valuemax":s.end}),e.attr("aria-valuenow",i),""!=s.display_selector&&t(s.display_selector).each(function(){this.hasAttribute("value")?t(this).val(i):t(this).text(i)})},normalized_percentage:function(t,e,i){return Math.min(1,(t-e)/(i-e))},normalized_value:function(t,e,i,s,n){var a=i-e,o=t*a,r=(o-o%s)/s,l=o%s,d=l>=.5*s?s:0;return(r*s+d+e).toFixed(n)},set_translate:function(e,i,s){s?t(e).css("-webkit-transform","translateY("+i+"px)").css("-moz-transform","translateY("+i+"px)").css("-ms-transform","translateY("+i+"px)").css("-o-transform","translateY("+i+"px)").css("transform","translateY("+i+"px)"):t(e).css("-webkit-transform","translateX("+i+"px)").css("-moz-transform","translateX("+i+"px)").css("-ms-transform","translateX("+i+"px)").css("-o-transform","translateX("+i+"px)").css("transform","translateX("+i+"px)")},limit_to:function(t,e,i){return Math.min(Math.max(t,e),i)},initialize_settings:function(e){var i,s=t.extend({},this.settings,this.data_options(t(e).parent()));return null===s.precision&&(i=(""+s.step).match(/\.([\d]*)/),s.precision=i&&i[1]?i[1].length:0),s.vertical?(t.data(e,"bar_o",t(e).parent().offset().top),t.data(e,"bar_l",t(e).parent().outerHeight()),t.data(e,"handle_o",t(e).offset().top),t.data(e,"handle_l",t(e).outerHeight())):(t.data(e,"bar_o",t(e).parent().offset().left),t.data(e,"bar_l",t(e).parent().outerWidth()),t.data(e,"handle_o",t(e).offset().left),t.data(e,"handle_l",t(e).outerWidth())),t.data(e,"bar",t(e).parent()),t.data(e,"settings",s)},set_initial_position:function(e){var i=t.data(e.children(".range-slider-handle")[0],"settings"),s="number"!=typeof i.initial||isNaN(i.initial)?Math.floor(.5*(i.end-i.start)/i.step)*i.step+i.start:i.initial,n=e.children(".range-slider-handle");this.set_ui(n,s)},set_value:function(e){var i=this;t("["+i.attr_name()+"]",this.scope).each(function(){t(this).attr(i.attr_name(),e)}),t(this.scope).attr(i.attr_name())&&t(this.scope).attr(i.attr_name(),e),i.reflow()},reflow:function(){var e=this;e.S("["+this.attr_name()+"]").each(function(){var i=t(this).children(".range-slider-handle")[0],s=t(this).attr(e.attr_name());e.initialize_settings(i),s?e.set_ui(t(i),parseFloat(s)):e.set_initial_position(t(this))})}}}(jQuery,window,window.document),function(t,e,i,s){"use strict";Foundation.libs.joyride={name:"joyride",version:"5.5.2",defaults:{expose:!1,modal:!0,keyboard:!0,tip_location:"bottom",nub_position:"auto",scroll_speed:1500,scroll_animation:"linear",timer:0,start_timer_on_click:!0,start_offset:0,next_button:!0,prev_button:!0,tip_animation:"fade",pause_after:[],exposed:[],tip_animation_fade_speed:300,cookie_monster:!1,cookie_name:"joyride",cookie_domain:!1,cookie_expires:365,tip_container:"body",abort_on_close:!0,tip_location_patterns:{top:["bottom"],bottom:[],left:["right","top","bottom"],right:["left","top","bottom"]},post_ride_callback:function(){},post_step_callback:function(){},pre_step_callback:function(){},pre_ride_callback:function(){},post_expose_callback:function(){},template:{link:\'<a href="#close" class="joyride-close-tip">&times;</a>\',timer:\'<div class="joyride-timer-indicator-wrap"><span class="joyride-timer-indicator"></span></div>\',tip:\'<div class="joyride-tip-guide"><span class="joyride-nub"></span></div>\',wrapper:\'<div class="joyride-content-wrapper"></div>\',button:\'<a href="#" class="small button joyride-next-tip"></a>\',prev_button:\'<a href="#" class="small button joyride-prev-tip"></a>\',modal:\'<div class="joyride-modal-bg"></div>\',expose:\'<div class="joyride-expose-wrapper"></div>\',expose_cover:\'<div class="joyride-expose-cover"></div>\'},expose_add_class:""},init:function(e,i,s){Foundation.inherit(this,"throttle random_str"),this.settings=this.settings||t.extend({},this.defaults,s||i),this.bindings(i,s)},go_next:function(){this.settings.$li.next().length<1?this.end():this.settings.timer>0?(clearTimeout(this.settings.automate),this.hide(),this.show(),this.startTimer()):(this.hide(),this.show())},go_prev:function(){this.settings.$li.prev().length<1||(this.settings.timer>0?(clearTimeout(this.settings.automate),this.hide(),this.show(null,!0),this.startTimer()):(this.hide(),this.show(null,!0)))},events:function(){var i=this;t(this.scope).off(".joyride").on("click.fndtn.joyride",".joyride-next-tip, .joyride-modal-bg",function(t){t.preventDefault(),this.go_next()}.bind(this)).on("click.fndtn.joyride",".joyride-prev-tip",function(t){t.preventDefault(),this.go_prev()}.bind(this)).on("click.fndtn.joyride",".joyride-close-tip",function(t){t.preventDefault(),this.end(this.settings.abort_on_close)}.bind(this)).on("keyup.fndtn.joyride",function(t){if(this.settings.keyboard&&this.settings.riding)switch(t.which){case 39:t.preventDefault(),this.go_next();break;case 37:t.preventDefault(),this.go_prev();break;case 27:t.preventDefault(),this.end(this.settings.abort_on_close)}}.bind(this)),t(e).off(".joyride").on("resize.fndtn.joyride",i.throttle(function(){if(t("["+i.attr_name()+"]").length>0&&i.settings.$next_tip&&i.settings.riding){if(i.settings.exposed.length>0){var e=t(i.settings.exposed);e.each(function(){var e=t(this);i.un_expose(e),i.expose(e)})}i.is_phone()?i.pos_phone():i.pos_default(!1)}},100))},start:function(){var e=this,i=t("["+this.attr_name()+"]",this.scope),s=["timer","scrollSpeed","startOffset","tipAnimationFadeSpeed","cookieExpires"],n=s.length;!i.length>0||(this.settings.init||this.events(),this.settings=i.data(this.attr_name(!0)+"-init"),this.settings.$content_el=i,this.settings.$body=t(this.settings.tip_container),this.settings.body_offset=t(this.settings.tip_container).position(),this.settings.$tip_content=this.settings.$content_el.find("> li"),this.settings.paused=!1,this.settings.attempts=0,this.settings.riding=!0,"function"!=typeof t.cookie&&(this.settings.cookie_monster=!1),(!this.settings.cookie_monster||this.settings.cookie_monster&&!t.cookie(this.settings.cookie_name))&&(this.settings.$tip_content.each(function(i){var a=t(this);this.settings=t.extend({},e.defaults,e.data_options(a));for(var o=n;o--;)e.settings[s[o]]=parseInt(e.settings[s[o]],10);e.create({$li:a,index:i})}),!this.settings.start_timer_on_click&&this.settings.timer>0?(this.show("init"),this.startTimer()):this.show("init")))},resume:function(){this.set_li(),this.show()},tip_template:function(e){var i,s;return e.tip_class=e.tip_class||"",i=t(this.settings.template.tip).addClass(e.tip_class),s=t.trim(t(e.li).html())+this.prev_button_text(e.prev_button_text,e.index)+this.button_text(e.button_text)+this.settings.template.link+this.timer_instance(e.index),i.append(t(this.settings.template.wrapper)),i.first().attr(this.add_namespace("data-index"),e.index),t(".joyride-content-wrapper",i).append(s),i[0]},timer_instance:function(e){var i;return i=0===e&&this.settings.start_timer_on_click&&this.settings.timer>0||0===this.settings.timer?"":t(this.settings.template.timer)[0].outerHTML},button_text:function(e){return this.settings.tip_settings.next_button?(e=t.trim(e)||"Next",e=t(this.settings.template.button).append(e)[0].outerHTML):e="",e},prev_button_text:function(e,i){return this.settings.tip_settings.prev_button?(e=t.trim(e)||"Previous",e=0==i?t(this.settings.template.prev_button).append(e).addClass("disabled")[0].outerHTML:t(this.settings.template.prev_button).append(e)[0].outerHTML):e="",e},create:function(e){this.settings.tip_settings=t.extend({},this.settings,this.data_options(e.$li));var i=e.$li.attr(this.add_namespace("data-button"))||e.$li.attr(this.add_namespace("data-text")),s=e.$li.attr(this.add_namespace("data-button-prev"))||e.$li.attr(this.add_namespace("data-prev-text")),n=e.$li.attr("class"),a=t(this.tip_template({tip_class:n,index:e.index,button_text:i,prev_button_text:s,li:e.$li}));t(this.settings.tip_container).append(a)},show:function(e,i){var n=null;if(this.settings.$li===s||-1===t.inArray(this.settings.$li.index(),this.settings.pause_after))if(this.settings.paused?this.settings.paused=!1:this.set_li(e,i),this.settings.attempts=0,this.settings.$li.length&&this.settings.$target.length>0){if(e&&(this.settings.pre_ride_callback(this.settings.$li.index(),this.settings.$next_tip),this.settings.modal&&this.show_modal()),this.settings.pre_step_callback(this.settings.$li.index(),this.settings.$next_tip),this.settings.modal&&this.settings.expose&&this.expose(),this.settings.tip_settings=t.extend({},this.settings,this.data_options(this.settings.$li)),this.settings.timer=parseInt(this.settings.timer,10),this.settings.tip_settings.tip_location_pattern=this.settings.tip_location_patterns[this.settings.tip_settings.tip_location],!/body/i.test(this.settings.$target.selector)){var a=t(".joyride-modal-bg");/pop/i.test(this.settings.tipAnimation)?a.hide():a.fadeOut(this.settings.tipAnimationFadeSpeed),this.scroll_to()}this.is_phone()?this.pos_phone(!0):this.pos_default(!0),n=this.settings.$next_tip.find(".joyride-timer-indicator"),/pop/i.test(this.settings.tip_animation)?(n.width(0),this.settings.timer>0?(this.settings.$next_tip.show(),setTimeout(function(){n.animate({width:n.parent().width()},this.settings.timer,"linear")}.bind(this),this.settings.tip_animation_fade_speed)):this.settings.$next_tip.show()):/fade/i.test(this.settings.tip_animation)&&(n.width(0),this.settings.timer>0?(this.settings.$next_tip.fadeIn(this.settings.tip_animation_fade_speed).show(),setTimeout(function(){n.animate({width:n.parent().width()},this.settings.timer,"linear")}.bind(this),this.settings.tip_animation_fade_speed)):this.settings.$next_tip.fadeIn(this.settings.tip_animation_fade_speed)),this.settings.$current_tip=this.settings.$next_tip}else this.settings.$li&&this.settings.$target.length<1?this.show(e,i):this.end();else this.settings.paused=!0},is_phone:function(){return matchMedia(Foundation.media_queries.small).matches&&!matchMedia(Foundation.media_queries.medium).matches},hide:function(){this.settings.modal&&this.settings.expose&&this.un_expose(),this.settings.modal||t(".joyride-modal-bg").hide(),this.settings.$current_tip.css("visibility","hidden"),setTimeout(t.proxy(function(){this.hide(),this.css("visibility","visible")},this.settings.$current_tip),0),this.settings.post_step_callback(this.settings.$li.index(),this.settings.$current_tip)},set_li:function(t,e){t?(this.settings.$li=this.settings.$tip_content.eq(this.settings.start_offset),this.set_next_tip(),this.settings.$current_tip=this.settings.$next_tip):(this.settings.$li=e?this.settings.$li.prev():this.settings.$li.next(),this.set_next_tip()),this.set_target()},set_next_tip:function(){this.settings.$next_tip=t(".joyride-tip-guide").eq(this.settings.$li.index()),this.settings.$next_tip.data("closed","")},set_target:function(){var e=this.settings.$li.attr(this.add_namespace("data-class")),s=this.settings.$li.attr(this.add_namespace("data-id")),n=function(){return s?t(i.getElementById(s)):e?t("."+e).first():t("body")};this.settings.$target=n()},scroll_to:function(){var i,s;i=t(e).height()/2,s=Math.ceil(this.settings.$target.offset().top-i+this.settings.$next_tip.outerHeight()),0!=s&&t("html, body").stop().animate({scrollTop:s},this.settings.scroll_speed,"swing")},paused:function(){return-1===t.inArray(this.settings.$li.index()+1,this.settings.pause_after)},restart:function(){this.hide(),this.settings.$li=s,this.show("init")},pos_default:function(t){var e=this.settings.$next_tip.find(".joyride-nub"),i=Math.ceil(e.outerWidth()/2),s=Math.ceil(e.outerHeight()/2),n=t||!1;if(n&&(this.settings.$next_tip.css("visibility","hidden"),this.settings.$next_tip.show()),/body/i.test(this.settings.$target.selector))this.settings.$li.length&&this.pos_modal(e);else{var a=this.settings.tip_settings.tipAdjustmentY?parseInt(this.settings.tip_settings.tipAdjustmentY):0,o=this.settings.tip_settings.tipAdjustmentX?parseInt(this.settings.tip_settings.tipAdjustmentX):0;this.bottom()?(this.settings.$next_tip.css(this.rtl?{top:this.settings.$target.offset().top+s+this.settings.$target.outerHeight()+a,left:this.settings.$target.offset().left+this.settings.$target.outerWidth()-this.settings.$next_tip.outerWidth()+o}:{top:this.settings.$target.offset().top+s+this.settings.$target.outerHeight()+a,left:this.settings.$target.offset().left+o}),this.nub_position(e,this.settings.tip_settings.nub_position,"top")):this.top()?(this.settings.$next_tip.css(this.rtl?{top:this.settings.$target.offset().top-this.settings.$next_tip.outerHeight()-s+a,left:this.settings.$target.offset().left+this.settings.$target.outerWidth()-this.settings.$next_tip.outerWidth()}:{top:this.settings.$target.offset().top-this.settings.$next_tip.outerHeight()-s+a,left:this.settings.$target.offset().left+o}),this.nub_position(e,this.settings.tip_settings.nub_position,"bottom")):this.right()?(this.settings.$next_tip.css({top:this.settings.$target.offset().top+a,left:this.settings.$target.outerWidth()+this.settings.$target.offset().left+i+o}),this.nub_position(e,this.settings.tip_settings.nub_position,"left")):this.left()&&(this.settings.$next_tip.css({top:this.settings.$target.offset().top+a,left:this.settings.$target.offset().left-this.settings.$next_tip.outerWidth()-i+o}),this.nub_position(e,this.settings.tip_settings.nub_position,"right")),!this.visible(this.corners(this.settings.$next_tip))&&this.settings.attempts<this.settings.tip_settings.tip_location_pattern.length&&(e.removeClass("bottom").removeClass("top").removeClass("right").removeClass("left"),this.settings.tip_settings.tip_location=this.settings.tip_settings.tip_location_pattern[this.settings.attempts],this.settings.attempts++,this.pos_default())}n&&(this.settings.$next_tip.hide(),this.settings.$next_tip.css("visibility","visible"))},pos_phone:function(e){var i=this.settings.$next_tip.outerHeight(),s=(this.settings.$next_tip.offset(),this.settings.$target.outerHeight()),n=t(".joyride-nub",this.settings.$next_tip),a=Math.ceil(n.outerHeight()/2),o=e||!1;n.removeClass("bottom").removeClass("top").removeClass("right").removeClass("left"),o&&(this.settings.$next_tip.css("visibility","hidden"),this.settings.$next_tip.show()),/body/i.test(this.settings.$target.selector)?this.settings.$li.length&&this.pos_modal(n):this.top()?(this.settings.$next_tip.offset({top:this.settings.$target.offset().top-i-a}),n.addClass("bottom")):(this.settings.$next_tip.offset({top:this.settings.$target.offset().top+s+a}),n.addClass("top")),o&&(this.settings.$next_tip.hide(),this.settings.$next_tip.css("visibility","visible"))},pos_modal:function(t){this.center(),t.hide(),this.show_modal()},show_modal:function(){if(!this.settings.$next_tip.data("closed")){var e=t(".joyride-modal-bg");if(e.length<1){var e=t(this.settings.template.modal);e.appendTo("body")}/pop/i.test(this.settings.tip_animation)?e.show():e.fadeIn(this.settings.tip_animation_fade_speed)}},expose:function(){var i,s,n,a,o,r="expose-"+this.random_str(6);if(arguments.length>0&&arguments[0]instanceof t)n=arguments[0];else{if(!this.settings.$target||/body/i.test(this.settings.$target.selector))return!1;n=this.settings.$target}return n.length<1?(e.console&&console.error("element not valid",n),!1):(i=t(this.settings.template.expose),this.settings.$body.append(i),i.css({top:n.offset().top,left:n.offset().left,width:n.outerWidth(!0),height:n.outerHeight(!0)}),s=t(this.settings.template.expose_cover),a={zIndex:n.css("z-index"),position:n.css("position")},o=null==n.attr("class")?"":n.attr("class"),n.css("z-index",parseInt(i.css("z-index"))+1),"static"==a.position&&n.css("position","relative"),n.data("expose-css",a),n.data("orig-class",o),n.attr("class",o+" "+this.settings.expose_add_class),s.css({top:n.offset().top,left:n.offset().left,width:n.outerWidth(!0),height:n.outerHeight(!0)}),this.settings.modal&&this.show_modal(),this.settings.$body.append(s),i.addClass(r),s.addClass(r),n.data("expose",r),this.settings.post_expose_callback(this.settings.$li.index(),this.settings.$next_tip,n),void this.add_exposed(n))},un_expose:function(){var i,s,n,a,o,r=!1;if(arguments.length>0&&arguments[0]instanceof t)s=arguments[0];else{if(!this.settings.$target||/body/i.test(this.settings.$target.selector))return!1;s=this.settings.$target}return s.length<1?(e.console&&console.error("element not valid",s),!1):(i=s.data("expose"),n=t("."+i),arguments.length>1&&(r=arguments[1]),r===!0?t(".joyride-expose-wrapper,.joyride-expose-cover").remove():n.remove(),a=s.data("expose-css"),"auto"==a.zIndex?s.css("z-index",""):s.css("z-index",a.zIndex),a.position!=s.css("position")&&("static"==a.position?s.css("position",""):s.css("position",a.position)),o=s.data("orig-class"),s.attr("class",o),s.removeData("orig-classes"),s.removeData("expose"),s.removeData("expose-z-index"),void this.remove_exposed(s))},add_exposed:function(e){this.settings.exposed=this.settings.exposed||[],e instanceof t||"object"==typeof e?this.settings.exposed.push(e[0]):"string"==typeof e&&this.settings.exposed.push(e)},remove_exposed:function(e){var i,s;for(e instanceof t?i=e[0]:"string"==typeof e&&(i=e),this.settings.exposed=this.settings.exposed||[],s=this.settings.exposed.length;s--;)if(this.settings.exposed[s]==i)return void this.settings.exposed.splice(s,1)},center:function(){var i=t(e);return this.settings.$next_tip.css({top:(i.height()-this.settings.$next_tip.outerHeight())/2+i.scrollTop(),left:(i.width()-this.settings.$next_tip.outerWidth())/2+i.scrollLeft()}),!0},bottom:function(){return/bottom/i.test(this.settings.tip_settings.tip_location)},top:function(){return/top/i.test(this.settings.tip_settings.tip_location)},right:function(){return/right/i.test(this.settings.tip_settings.tip_location)},left:function(){return/left/i.test(this.settings.tip_settings.tip_location)},corners:function(i){var s=t(e),n=s.height()/2,a=Math.ceil(this.settings.$target.offset().top-n+this.settings.$next_tip.outerHeight()),o=s.width()+s.scrollLeft(),r=s.height()+a,l=s.height()+s.scrollTop(),d=s.scrollTop();return d>a&&(d=0>a?0:a),r>l&&(l=r),[i.offset().top<d,o<i.offset().left+i.outerWidth(),l<i.offset().top+i.outerHeight(),s.scrollLeft()>i.offset().left]},visible:function(t){for(var e=t.length;e--;)if(t[e])return!1;return!0},nub_position:function(t,e,i){t.addClass("auto"===e?i:e)},startTimer:function(){this.settings.$li.length?this.settings.automate=setTimeout(function(){this.hide(),this.show(),this.startTimer()}.bind(this),this.settings.timer):clearTimeout(this.settings.automate)},end:function(e){this.settings.cookie_monster&&t.cookie(this.settings.cookie_name,"ridden",{expires:this.settings.cookie_expires,domain:this.settings.cookie_domain}),this.settings.timer>0&&clearTimeout(this.settings.automate),this.settings.modal&&this.settings.expose&&this.un_expose(),t(this.scope).off("keyup.joyride"),this.settings.$next_tip.data("closed",!0),this.settings.riding=!1,t(".joyride-modal-bg").hide(),this.settings.$current_tip.hide(),("undefined"==typeof e||e===!1)&&(this.settings.post_step_callback(this.settings.$li.index(),this.settings.$current_tip),this.settings.post_ride_callback(this.settings.$li.index(),this.settings.$current_tip)),t(".joyride-tip-guide").remove()},off:function(){t(this.scope).off(".joyride"),t(e).off(".joyride"),t(".joyride-close-tip, .joyride-next-tip, .joyride-modal-bg").off(".joyride"),t(".joyride-tip-guide, .joyride-modal-bg").remove(),clearTimeout(this.settings.automate),this.settings={}},reflow:function(){}}}(jQuery,window,window.document),function(t,e){"use strict";Foundation.libs.equalizer={name:"equalizer",version:"5.5.2",settings:{use_tallest:!0,before_height_change:t.noop,after_height_change:t.noop,equalize_on_stack:!1,act_on_hidden_el:!1},init:function(t,e,i){Foundation.inherit(this,"image_loaded"),this.bindings(e,i),this.reflow()},events:function(){this.S(e).off(".equalizer").on("resize.fndtn.equalizer",function(){this.reflow()}.bind(this))},equalize:function(e){var i,s,n=!1,a=e.data("equalizer"),o=e.data(this.attr_name(!0)+"-init")||this.settings;if(i=e.find(o.act_on_hidden_el?a?"["+this.attr_name()+\'-watch="\'+a+\'"]\':"["+this.attr_name()+"-watch]":a?"["+this.attr_name()+\'-watch="\'+a+\'"]:visible\':"["+this.attr_name()+"-watch]:visible"),0!==i.length&&(o.before_height_change(),e.trigger("before-height-change.fndth.equalizer"),i.height("inherit"),o.equalize_on_stack!==!1||(s=i.first().offset().top,i.each(function(){return t(this).offset().top!==s?(n=!0,!1):void 0}),!n))){var r=i.map(function(){return t(this).outerHeight(!1)}).get();if(o.use_tallest){var l=Math.max.apply(null,r);i.css("height",l)}else{var d=Math.min.apply(null,r);i.css("height",d)}o.after_height_change(),e.trigger("after-height-change.fndtn.equalizer")}},reflow:function(){var e=this;this.S("["+this.attr_name()+"]",this.scope).each(function(){var i=t(this),s=i.data("equalizer-mq"),n=!0;s&&(s="is_"+s.replace(/-/g,"_"),Foundation.utils.hasOwnProperty(s)&&(n=!1)),e.image_loaded(e.S("img",this),function(){if(n||Foundation.utils[s]())e.equalize(i);else{var t=i.find("["+e.attr_name()+"-watch]:visible");t.css("height","auto")}})})}}}(jQuery,window,window.document),function(t,e,i){"use strict";Foundation.libs.dropdown={name:"dropdown",version:"5.5.2",settings:{active_class:"open",disabled_class:"disabled",mega_class:"mega",align:"bottom",is_hover:!1,hover_timeout:150,opened:function(){},closed:function(){}},init:function(e,i,s){Foundation.inherit(this,"throttle"),t.extend(!0,this.settings,i,s),this.bindings(i,s)},events:function(){var s=this,n=s.S;n(this.scope).off(".dropdown").on("click.fndtn.dropdown","["+this.attr_name()+"]",function(e){var i=n(this).data(s.attr_name(!0)+"-init")||s.settings;(!i.is_hover||Modernizr.touch)&&(e.preventDefault(),n(this).parent("[data-reveal-id]").length&&e.stopPropagation(),s.toggle(t(this)))}).on("mouseenter.fndtn.dropdown","["+this.attr_name()+"], ["+this.attr_name()+"-content]",function(t){var e,i,a=n(this);clearTimeout(s.timeout),a.data(s.data_attr())?(e=n("#"+a.data(s.data_attr())),i=a):(e=a,i=n("["+s.attr_name()+\'="\'+e.attr("id")+\'"]\'));var o=i.data(s.attr_name(!0)+"-init")||s.settings;n(t.currentTarget).data(s.data_attr())&&o.is_hover&&s.closeall.call(s),o.is_hover&&s.open.apply(s,[e,i])}).on("mouseleave.fndtn.dropdown","["+this.attr_name()+"], ["+this.attr_name()+"-content]",function(){var t,e=n(this);if(e.data(s.data_attr()))t=e.data(s.data_attr(!0)+"-init")||s.settings;else var i=n("["+s.attr_name()+\'="\'+n(this).attr("id")+\'"]\'),t=i.data(s.attr_name(!0)+"-init")||s.settings;s.timeout=setTimeout(function(){e.data(s.data_attr())?t.is_hover&&s.close.call(s,n("#"+e.data(s.data_attr()))):t.is_hover&&s.close.call(s,e)}.bind(this),t.hover_timeout)}).on("click.fndtn.dropdown",function(e){var a=n(e.target).closest("["+s.attr_name()+"-content]"),o=a.find("a");return o.length>0&&"false"!==a.attr("aria-autoclose")&&s.close.call(s,n("["+s.attr_name()+"-content]")),e.target!==i&&!t.contains(i.documentElement,e.target)||n(e.target).closest("["+s.attr_name()+"]").length>0?void 0:!n(e.target).data("revealId")&&a.length>0&&(n(e.target).is("["+s.attr_name()+"-content]")||t.contains(a.first()[0],e.target))?void e.stopPropagation():void s.close.call(s,n("["+s.attr_name()+"-content]"))}).on("opened.fndtn.dropdown","["+s.attr_name()+"-content]",function(){s.settings.opened.call(this)}).on("closed.fndtn.dropdown","["+s.attr_name()+"-content]",function(){s.settings.closed.call(this)}),n(e).off(".dropdown").on("resize.fndtn.dropdown",s.throttle(function(){s.resize.call(s)},50)),this.resize()},close:function(e){var i=this;e.each(function(s){var n=t("["+i.attr_name()+"="+e[s].id+"]")||t("aria-controls="+e[s].id+"]");n.attr("aria-expanded","false"),i.S(this).hasClass(i.settings.active_class)&&(i.S(this).css(Foundation.rtl?"right":"left","-99999px").attr("aria-hidden","true").removeClass(i.settings.active_class).prev("["+i.attr_name()+"]").removeClass(i.settings.active_class).removeData("target"),i.S(this).trigger("closed.fndtn.dropdown",[e]))
}),e.removeClass("f-open-"+this.attr_name(!0))},closeall:function(){var e=this;t.each(e.S(".f-open-"+this.attr_name(!0)),function(){e.close.call(e,e.S(this))})},open:function(t,e){this.css(t.addClass(this.settings.active_class),e),t.prev("["+this.attr_name()+"]").addClass(this.settings.active_class),t.data("target",e.get(0)).trigger("opened.fndtn.dropdown",[t,e]),t.attr("aria-hidden","false"),e.attr("aria-expanded","true"),t.focus(),t.addClass("f-open-"+this.attr_name(!0))},data_attr:function(){return this.namespace.length>0?this.namespace+"-"+this.name:this.name},toggle:function(t){if(!t.hasClass(this.settings.disabled_class)){var e=this.S("#"+t.data(this.data_attr()));0!==e.length&&(this.close.call(this,this.S("["+this.attr_name()+"-content]").not(e)),e.hasClass(this.settings.active_class)?(this.close.call(this,e),e.data("target")!==t.get(0)&&this.open.call(this,e,t)):this.open.call(this,e,t))}},resize:function(){var e=this.S("["+this.attr_name()+"-content].open"),i=t(e.data("target"));e.length&&i.length&&this.css(e,i)},css:function(t,e){var i=Math.max((e.width()-t.width())/2,8),s=e.data(this.attr_name(!0)+"-init")||this.settings,n=t.parent().css("overflow-y")||t.parent().css("overflow");if(this.clear_idx(),this.small()){var a=this.dirs.bottom.call(t,e,s);t.attr("style","").removeClass("drop-left drop-right drop-top").css({position:"absolute",width:"95%","max-width":"none",top:a.top}),t.css(Foundation.rtl?"right":"left",i)}else if("visible"!==n){var o=e[0].offsetTop+e[0].offsetHeight;t.attr("style","").css({position:"absolute",top:o}),t.css(Foundation.rtl?"right":"left",i)}else this.style(t,e,s);return t},style:function(e,i,s){var n=t.extend({position:"absolute"},this.dirs[s.align].call(e,i,s));e.attr("style","").css(n)},dirs:{_base:function(t){var s=this.offsetParent(),n=s.offset(),a=t.offset();a.top-=n.top,a.left-=n.left,a.missRight=!1,a.missTop=!1,a.missLeft=!1,a.leftRightFlag=!1;var o;o=i.getElementsByClassName("row")[0]?i.getElementsByClassName("row")[0].clientWidth:e.innerWidth;var r=(e.innerWidth-o)/2,l=o;return this.hasClass("mega")||(t.offset().top<=this.outerHeight()&&(a.missTop=!0,l=e.innerWidth-r,a.leftRightFlag=!0),t.offset().left+this.outerWidth()>t.offset().left+r&&t.offset().left-r>this.outerWidth()&&(a.missRight=!0,a.missLeft=!1),t.offset().left-this.outerWidth()<=0&&(a.missLeft=!0,a.missRight=!1)),a},top:function(t,e){var i=Foundation.libs.dropdown,s=i.dirs._base.call(this,t);return this.addClass("drop-top"),1==s.missTop&&(s.top=s.top+t.outerHeight()+this.outerHeight(),this.removeClass("drop-top")),1==s.missRight&&(s.left=s.left-this.outerWidth()+t.outerWidth()),(t.outerWidth()<this.outerWidth()||i.small()||this.hasClass(e.mega_menu))&&i.adjust_pip(this,t,e,s),Foundation.rtl?{left:s.left-this.outerWidth()+t.outerWidth(),top:s.top-this.outerHeight()}:{left:s.left,top:s.top-this.outerHeight()}},bottom:function(t,e){var i=Foundation.libs.dropdown,s=i.dirs._base.call(this,t);return 1==s.missRight&&(s.left=s.left-this.outerWidth()+t.outerWidth()),(t.outerWidth()<this.outerWidth()||i.small()||this.hasClass(e.mega_menu))&&i.adjust_pip(this,t,e,s),i.rtl?{left:s.left-this.outerWidth()+t.outerWidth(),top:s.top+t.outerHeight()}:{left:s.left,top:s.top+t.outerHeight()}},left:function(t){var e=Foundation.libs.dropdown.dirs._base.call(this,t);return this.addClass("drop-left"),1==e.missLeft&&(e.left=e.left+this.outerWidth(),e.top=e.top+t.outerHeight(),this.removeClass("drop-left")),{left:e.left-this.outerWidth(),top:e.top}},right:function(t,e){var i=Foundation.libs.dropdown.dirs._base.call(this,t);this.addClass("drop-right"),1==i.missRight?(i.left=i.left-this.outerWidth(),i.top=i.top+t.outerHeight(),this.removeClass("drop-right")):i.triggeredRight=!0;var s=Foundation.libs.dropdown;return(t.outerWidth()<this.outerWidth()||s.small()||this.hasClass(e.mega_menu))&&s.adjust_pip(this,t,e,i),{left:i.left+t.outerWidth(),top:i.top}}},adjust_pip:function(t,e,i,s){var n=Foundation.stylesheet,a=8;t.hasClass(i.mega_class)?a=s.left+e.outerWidth()/2-8:this.small()&&(a+=s.left-8),this.rule_idx=n.cssRules.length;var o=".f-dropdown.open:before",r=".f-dropdown.open:after",l="left: "+a+"px;",d="left: "+(a-1)+"px;";1==s.missRight&&(a=t.outerWidth()-23,o=".f-dropdown.open:before",r=".f-dropdown.open:after",l="left: "+a+"px;",d="left: "+(a-1)+"px;"),1==s.triggeredRight&&(o=".f-dropdown.open:before",r=".f-dropdown.open:after",l="left:-12px;",d="left:-14px;"),n.insertRule?(n.insertRule([o,"{",l,"}"].join(" "),this.rule_idx),n.insertRule([r,"{",d,"}"].join(" "),this.rule_idx+1)):(n.addRule(o,l,this.rule_idx),n.addRule(r,d,this.rule_idx+1))},clear_idx:function(){var t=Foundation.stylesheet;"undefined"!=typeof this.rule_idx&&(t.deleteRule(this.rule_idx),t.deleteRule(this.rule_idx),delete this.rule_idx)},small:function(){return matchMedia(Foundation.media_queries.small).matches&&!matchMedia(Foundation.media_queries.medium).matches},off:function(){this.S(this.scope).off(".fndtn.dropdown"),this.S("html, body").off(".fndtn.dropdown"),this.S(e).off(".fndtn.dropdown"),this.S("[data-dropdown-content]").off(".fndtn.dropdown")},reflow:function(){}}}(jQuery,window,window.document),function(t,e,i,s){"use strict";Foundation.libs.clearing={name:"clearing",version:"5.5.2",settings:{templates:{viewing:\'<a href="#" class="clearing-close">&times;</a><div class="visible-img" style="display: none"><div class="clearing-touch-label"></div><img src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs%3D" alt="" /><p class="clearing-caption"></p><a href="#" class="clearing-main-prev"><span></span></a><a href="#" class="clearing-main-next"><span></span></a></div><img class="clearing-preload-next" style="display: none" src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs%3D" alt="" /><img class="clearing-preload-prev" style="display: none" src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs%3D" alt="" />\'},close_selectors:".clearing-close, div.clearing-blackout",open_selectors:"",skip_selector:"",touch_label:"",init:!1,locked:!1},init:function(t,e,i){var s=this;Foundation.inherit(this,"throttle image_loaded"),this.bindings(e,i),s.S(this.scope).is("["+this.attr_name()+"]")?this.assemble(s.S("li",this.scope)):s.S("["+this.attr_name()+"]",this.scope).each(function(){s.assemble(s.S("li",this))})},events:function(s){var n=this,a=n.S,o=t(".scroll-container");o.length>0&&(this.scope=o),a(this.scope).off(".clearing").on("click.fndtn.clearing","ul["+this.attr_name()+"] li "+this.settings.open_selectors,function(t,e,i){var e=e||a(this),i=i||e,s=e.next("li"),o=e.closest("["+n.attr_name()+"]").data(n.attr_name(!0)+"-init"),r=a(t.target);t.preventDefault(),o||(n.init(),o=e.closest("["+n.attr_name()+"]").data(n.attr_name(!0)+"-init")),i.hasClass("visible")&&e[0]===i[0]&&s.length>0&&n.is_open(e)&&(i=s,r=a("img",i)),n.open(r,e,i),n.update_paddles(i)}).on("click.fndtn.clearing",".clearing-main-next",function(t){n.nav(t,"next")}).on("click.fndtn.clearing",".clearing-main-prev",function(t){n.nav(t,"prev")}).on("click.fndtn.clearing",this.settings.close_selectors,function(t){Foundation.libs.clearing.close(t,this)}),t(i).on("keydown.fndtn.clearing",function(t){n.keydown(t)}),a(e).off(".clearing").on("resize.fndtn.clearing",function(){n.resize()}),this.swipe_events(s)},swipe_events:function(){var t=this,e=t.S;e(this.scope).on("touchstart.fndtn.clearing",".visible-img",function(t){t.touches||(t=t.originalEvent);var i={start_page_x:t.touches[0].pageX,start_page_y:t.touches[0].pageY,start_time:(new Date).getTime(),delta_x:0,is_scrolling:s};e(this).data("swipe-transition",i),t.stopPropagation()}).on("touchmove.fndtn.clearing",".visible-img",function(i){if(i.touches||(i=i.originalEvent),!(i.touches.length>1||i.scale&&1!==i.scale)){var s=e(this).data("swipe-transition");if("undefined"==typeof s&&(s={}),s.delta_x=i.touches[0].pageX-s.start_page_x,Foundation.rtl&&(s.delta_x=-s.delta_x),"undefined"==typeof s.is_scrolling&&(s.is_scrolling=!!(s.is_scrolling||Math.abs(s.delta_x)<Math.abs(i.touches[0].pageY-s.start_page_y))),!s.is_scrolling&&!s.active){i.preventDefault();var n=s.delta_x<0?"next":"prev";s.active=!0,t.nav(i,n)}}}).on("touchend.fndtn.clearing",".visible-img",function(t){e(this).data("swipe-transition",{}),t.stopPropagation()})},assemble:function(e){var i=e.parent();if(!i.parent().hasClass("carousel")){i.after(\'<div id="foundationClearingHolder"></div>\');var s=i.detach(),n="";if(null!=s[0]){n=s[0].outerHTML;var a=this.S("#foundationClearingHolder"),o=i.data(this.attr_name(!0)+"-init"),r={grid:\'<div class="carousel">\'+n+"</div>",viewing:o.templates.viewing},l=\'<div class="clearing-assembled"><div>\'+r.viewing+r.grid+"</div></div>",d=this.settings.touch_label;Modernizr.touch&&(l=t(l).find(".clearing-touch-label").html(d).end()),a.after(l).remove()}}},open:function(e,s,n){function a(){setTimeout(function(){this.image_loaded(u,function(){1!==u.outerWidth()||p?o.call(this,u):a.call(this)}.bind(this))}.bind(this),100)}function o(e){var i=t(e);i.css("visibility","visible"),i.trigger("imageVisible"),l.css("overflow","hidden"),d.addClass("clearing-blackout"),c.addClass("clearing-container"),h.show(),this.fix_height(n).caption(r.S(".clearing-caption",h),r.S("img",n)).center_and_label(e,f).shift(s,n,function(){n.closest("li").siblings().removeClass("visible"),n.closest("li").addClass("visible")}),h.trigger("opened.fndtn.clearing")}var r=this,l=t(i.body),d=n.closest(".clearing-assembled"),c=r.S("div",d).first(),h=r.S(".visible-img",c),u=r.S("img",h).not(e),f=r.S(".clearing-touch-label",c),p=!1,g={};t("body").on("touchmove",function(t){t.preventDefault()}),u.error(function(){p=!0}),this.locked()||(h.trigger("open.fndtn.clearing"),g=this.load(e),g.interchange?u.attr("data-interchange",g.interchange).foundation("interchange","reflow"):u.attr("src",g.src).attr("data-interchange",""),u.css("visibility","hidden"),a.call(this))},close:function(e,s){e.preventDefault();var n,a,o=function(t){return/blackout/.test(t.selector)?t:t.closest(".clearing-blackout")}(t(s)),r=t(i.body);return s===e.target&&o&&(r.css("overflow",""),n=t("div",o).first(),a=t(".visible-img",n),a.trigger("close.fndtn.clearing"),this.settings.prev_index=0,t("ul["+this.attr_name()+"]",o).attr("style","").closest(".clearing-blackout").removeClass("clearing-blackout"),n.removeClass("clearing-container"),a.hide(),a.trigger("closed.fndtn.clearing")),t("body").off("touchmove"),!1},is_open:function(t){return t.parent().prop("style").length>0},keydown:function(e){var i=t(".clearing-blackout ul["+this.attr_name()+"]"),s=this.rtl?37:39,n=this.rtl?39:37,a=27;e.which===s&&this.go(i,"next"),e.which===n&&this.go(i,"prev"),e.which===a&&this.S("a.clearing-close").trigger("click.fndtn.clearing")},nav:function(e,i){var s=t("ul["+this.attr_name()+"]",".clearing-blackout");e.preventDefault(),this.go(s,i)},resize:function(){var e=t("img",".clearing-blackout .visible-img"),i=t(".clearing-touch-label",".clearing-blackout");e.length&&(this.center_and_label(e,i),e.trigger("resized.fndtn.clearing"))},fix_height:function(t){var e=t.parent().children(),i=this;return e.each(function(){var t=i.S(this),e=t.find("img");t.height()>e.outerHeight()&&t.addClass("fix-height")}).closest("ul").width(100*e.length+"%"),this},update_paddles:function(t){t=t.closest("li");var e=t.closest(".carousel").siblings(".visible-img");t.next().length>0?this.S(".clearing-main-next",e).removeClass("disabled"):this.S(".clearing-main-next",e).addClass("disabled"),t.prev().length>0?this.S(".clearing-main-prev",e).removeClass("disabled"):this.S(".clearing-main-prev",e).addClass("disabled")},center_and_label:function(t,e){return e.css(!this.rtl&&e.length>0?{marginLeft:-(e.outerWidth()/2),marginTop:-(t.outerHeight()/2)-e.outerHeight()-10}:{marginRight:-(e.outerWidth()/2),marginTop:-(t.outerHeight()/2)-e.outerHeight()-10,left:"auto",right:"50%"}),this},load:function(t){var e,i,s;return"A"===t[0].nodeName?(e=t.attr("href"),i=t.data("clearing-interchange")):(s=t.closest("a"),e=s.attr("href"),i=s.data("clearing-interchange")),this.preload(t),{src:e?e:t.attr("src"),interchange:e?i:t.data("clearing-interchange")}},preload:function(t){this.img(t.closest("li").next(),"next").img(t.closest("li").prev(),"prev")},img:function(e,i){if(e.length){var s,n,a,o=t(".clearing-preload-"+i),r=this.S("a",e);r.length?(s=r.attr("href"),n=r.data("clearing-interchange")):(a=this.S("img",e),s=a.attr("src"),n=a.data("clearing-interchange")),n?o.attr("data-interchange",n):(o.attr("src",s),o.attr("data-interchange",""))}return this},caption:function(t,e){var i=e.attr("data-caption");return i?t.html(i).show():t.text("").hide(),this},go:function(t,e){var i=this.S(".visible",t),s=i[e]();this.settings.skip_selector&&0!=s.find(this.settings.skip_selector).length&&(s=s[e]()),s.length&&this.S("img",s).trigger("click.fndtn.clearing",[i,s]).trigger("change.fndtn.clearing")},shift:function(t,e,i){var s,n=e.parent(),a=this.settings.prev_index||e.index(),o=this.direction(n,t,e),r=this.rtl?"right":"left",l=parseInt(n.css("left"),10),d=e.outerWidth(),c={};e.index()===a||/skip/.test(o)?/skip/.test(o)&&(s=e.index()-this.settings.up_count,this.lock(),s>0?(c[r]=-(s*d),n.animate(c,300,this.unlock())):(c[r]=0,n.animate(c,300,this.unlock()))):/left/.test(o)?(this.lock(),c[r]=l+d,n.animate(c,300,this.unlock())):/right/.test(o)&&(this.lock(),c[r]=l-d,n.animate(c,300,this.unlock())),i()},direction:function(t,e,i){var s,n=this.S("li",t),a=n.outerWidth()+n.outerWidth()/4,o=Math.floor(this.S(".clearing-container").outerWidth()/a)-1,r=n.index(i);return this.settings.up_count=o,s=this.adjacent(this.settings.prev_index,r)?r>o&&r>this.settings.prev_index?"right":r>o-1&&r<=this.settings.prev_index?"left":!1:"skip",this.settings.prev_index=r,s},adjacent:function(t,e){for(var i=e+1;i>=e-1;i--)if(i===t)return!0;return!1},lock:function(){this.settings.locked=!0},unlock:function(){this.settings.locked=!1},locked:function(){return this.settings.locked},off:function(){this.S(this.scope).off(".fndtn.clearing"),this.S(e).off(".fndtn.clearing")},reflow:function(){this.init()}}}(jQuery,window,window.document),function(t,e,i,s){"use strict";var n=function(){},a=function(n,a){if(n.hasClass(a.slides_container_class))return this;var d,c,h,u,f,p,g=this,_=n,m=0,v=!1;g.slides=function(){return _.children(a.slide_selector)},g.slides().first().addClass(a.active_slide_class),g.update_slide_number=function(e){a.slide_number&&(c.find("span:first").text(parseInt(e)+1),c.find("span:last").text(g.slides().length)),a.bullets&&(h.children().removeClass(a.bullets_active_class),t(h.children().get(e)).addClass(a.bullets_active_class))},g.update_active_link=function(e){var i=t(\'[data-orbit-link="\'+g.slides().eq(e).attr("data-orbit-slide")+\'"]\');i.siblings().removeClass(a.bullets_active_class),i.addClass(a.bullets_active_class)},g.build_markup=function(){_.wrap(\'<div class="\'+a.container_class+\'"></div>\'),d=_.parent(),_.addClass(a.slides_container_class),a.stack_on_small&&d.addClass(a.stack_on_small_class),a.navigation_arrows&&(d.append(t(\'<a href="#"><span></span></a>\').addClass(a.prev_class)),d.append(t(\'<a href="#"><span></span></a>\').addClass(a.next_class))),a.timer&&(u=t("<div>").addClass(a.timer_container_class),u.append("<span>"),u.append(t("<div>").addClass(a.timer_progress_class)),u.addClass(a.timer_paused_class),d.append(u)),a.slide_number&&(c=t("<div>").addClass(a.slide_number_class),c.append("<span></span> "+a.slide_number_text+" <span></span>"),d.append(c)),a.bullets&&(h=t("<ol>").addClass(a.bullets_container_class),d.append(h),h.wrap(\'<div class="orbit-bullets-container"></div>\'),g.slides().each(function(e){var i=t("<li>").attr("data-orbit-slide",e).on("click",g.link_bullet);h.append(i)}))},g._goto=function(e,i){if(e===m)return!1;"object"==typeof p&&p.restart();var s=g.slides(),n="next";if(v=!0,m>e&&(n="prev"),e>=s.length){if(!a.circular)return!1;e=0}else if(0>e){if(!a.circular)return!1;e=s.length-1}var o=t(s.get(m)),r=t(s.get(e));o.css("zIndex",2),o.removeClass(a.active_slide_class),r.css("zIndex",4).addClass(a.active_slide_class),_.trigger("before-slide-change.fndtn.orbit"),a.before_slide_change(),g.update_active_link(e);var l=function(){var t=function(){m=e,v=!1,i===!0&&(p=g.create_timer(),p.start()),g.update_slide_number(m),_.trigger("after-slide-change.fndtn.orbit",[{slide_number:m,total_slides:s.length}]),a.after_slide_change(m,s.length)};_.outerHeight()!=r.outerHeight()&&a.variable_height?_.animate({height:r.outerHeight()},250,"linear",t):t()};if(1===s.length)return l(),!1;var d=function(){"next"===n&&f.next(o,r,l),"prev"===n&&f.prev(o,r,l)};r.outerHeight()>_.outerHeight()&&a.variable_height?_.animate({height:r.outerHeight()},250,"linear",d):d()},g.next=function(t){t.stopImmediatePropagation(),t.preventDefault(),g._goto(m+1)},g.prev=function(t){t.stopImmediatePropagation(),t.preventDefault(),g._goto(m-1)},g.link_custom=function(e){e.preventDefault();var i=t(this).attr("data-orbit-link");if("string"==typeof i&&""!=(i=t.trim(i))){var s=d.find("[data-orbit-slide="+i+"]");-1!=s.index()&&g._goto(s.index())}},g.link_bullet=function(){var e=t(this).attr("data-orbit-slide");if("string"==typeof e&&""!=(e=t.trim(e)))if(isNaN(parseInt(e))){var i=d.find("[data-orbit-slide="+e+"]");-1!=i.index()&&g._goto(i.index()+1)}else g._goto(parseInt(e))},g.timer_callback=function(){g._goto(m+1,!0)},g.compute_dimensions=function(){var e=t(g.slides().get(m)),i=e.outerHeight();a.variable_height||g.slides().each(function(){t(this).outerHeight()>i&&(i=t(this).outerHeight())}),_.height(i)},g.create_timer=function(){var t=new o(d.find("."+a.timer_container_class),a,g.timer_callback);return t},g.stop_timer=function(){"object"==typeof p&&p.stop()},g.toggle_timer=function(){var t=d.find("."+a.timer_container_class);t.hasClass(a.timer_paused_class)?("undefined"==typeof p&&(p=g.create_timer()),p.start()):"object"==typeof p&&p.stop()},g.init=function(){g.build_markup(),a.timer&&(p=g.create_timer(),Foundation.utils.image_loaded(this.slides().children("img"),p.start)),f=new l(a,_),"slide"===a.animation&&(f=new r(a,_)),d.on("click","."+a.next_class,g.next),d.on("click","."+a.prev_class,g.prev),a.next_on_click&&d.on("click","."+a.slides_container_class+" [data-orbit-slide]",g.link_bullet),d.on("click",g.toggle_timer),a.swipe&&d.on("touchstart.fndtn.orbit",function(t){t.touches||(t=t.originalEvent);var e={start_page_x:t.touches[0].pageX,start_page_y:t.touches[0].pageY,start_time:(new Date).getTime(),delta_x:0,is_scrolling:s};d.data("swipe-transition",e),t.stopPropagation()}).on("touchmove.fndtn.orbit",function(t){if(t.touches||(t=t.originalEvent),!(t.touches.length>1||t.scale&&1!==t.scale)){var e=d.data("swipe-transition");if("undefined"==typeof e&&(e={}),e.delta_x=t.touches[0].pageX-e.start_page_x,"undefined"==typeof e.is_scrolling&&(e.is_scrolling=!!(e.is_scrolling||Math.abs(e.delta_x)<Math.abs(t.touches[0].pageY-e.start_page_y))),!e.is_scrolling&&!e.active){t.preventDefault();var i=e.delta_x<0?m+1:m-1;e.active=!0,g._goto(i)}}}).on("touchend.fndtn.orbit",function(t){d.data("swipe-transition",{}),t.stopPropagation()}),d.on("mouseenter.fndtn.orbit",function(){a.timer&&a.pause_on_hover&&g.stop_timer()}).on("mouseleave.fndtn.orbit",function(){a.timer&&a.resume_on_mouseout&&p.start()}),t(i).on("click","[data-orbit-link]",g.link_custom),t(e).on("load resize",g.compute_dimensions),Foundation.utils.image_loaded(this.slides().children("img"),g.compute_dimensions),Foundation.utils.image_loaded(this.slides().children("img"),function(){d.prev("."+a.preloader_class).css("display","none"),g.update_slide_number(0),g.update_active_link(0),_.trigger("ready.fndtn.orbit")})},g.init()},o=function(t,e,i){var s,n,a=this,o=e.timer_speed,r=t.find("."+e.timer_progress_class),l=-1;this.update_progress=function(t){var e=r.clone();e.attr("style",""),e.css("width",t+"%"),r.replaceWith(e),r=e},this.restart=function(){clearTimeout(n),t.addClass(e.timer_paused_class),l=-1,a.update_progress(0)},this.start=function(){return t.hasClass(e.timer_paused_class)?(l=-1===l?o:l,t.removeClass(e.timer_paused_class),s=(new Date).getTime(),r.animate({width:"100%"},l,"linear"),n=setTimeout(function(){a.restart(),i()},l),void t.trigger("timer-started.fndtn.orbit")):!0},this.stop=function(){if(t.hasClass(e.timer_paused_class))return!0;clearTimeout(n),t.addClass(e.timer_paused_class);var i=(new Date).getTime();l-=i-s;var r=100-l/o*100;a.update_progress(r),t.trigger("timer-stopped.fndtn.orbit")}},r=function(e){var i=e.animation_speed,s=1===t("html[dir=rtl]").length,n=s?"marginRight":"marginLeft",a={};a[n]="0%",this.next=function(t,e,s){t.animate({marginLeft:"-100%"},i),e.animate(a,i,function(){t.css(n,"100%"),s()})},this.prev=function(t,e,s){t.animate({marginLeft:"100%"},i),e.css(n,"-100%"),e.animate(a,i,function(){t.css(n,"100%"),s()})}},l=function(e){{var i=e.animation_speed;1===t("html[dir=rtl]").length}this.next=function(t,e,s){e.css({margin:"0%",opacity:"0.01"}),e.animate({opacity:"1"},i,"linear",function(){t.css("margin","100%"),s()})},this.prev=function(t,e,s){e.css({margin:"0%",opacity:"0.01"}),e.animate({opacity:"1"},i,"linear",function(){t.css("margin","100%"),s()})}};Foundation.libs=Foundation.libs||{},Foundation.libs.orbit={name:"orbit",version:"5.5.2",settings:{animation:"slide",timer_speed:1e4,pause_on_hover:!0,resume_on_mouseout:!1,next_on_click:!0,animation_speed:500,stack_on_small:!1,navigation_arrows:!0,slide_number:!0,slide_number_text:"of",container_class:"orbit-container",stack_on_small_class:"orbit-stack-on-small",next_class:"orbit-next",prev_class:"orbit-prev",timer_container_class:"orbit-timer",timer_paused_class:"paused",timer_progress_class:"orbit-progress",slides_container_class:"orbit-slides-container",preloader_class:"preloader",slide_selector:"*",bullets_container_class:"orbit-bullets",bullets_active_class:"active",slide_number_class:"orbit-slide-number",caption_class:"orbit-caption",active_slide_class:"active",orbit_transition_class:"orbit-transitioning",bullets:!0,circular:!0,timer:!0,variable_height:!1,swipe:!0,before_slide_change:n,after_slide_change:n},init:function(t,e,i){this.bindings(e,i)},events:function(t){var e=new a(this.S(t),this.S(t).data("orbit-init"));this.S(t).data(this.name+"-instance",e)},reflow:function(){var t=this;if(t.S(t.scope).is("[data-orbit]")){var e=t.S(t.scope),i=e.data(t.name+"-instance");i.compute_dimensions()}else t.S("[data-orbit]",t.scope).each(function(e,i){var s=t.S(i),n=(t.data_options(s),s.data(t.name+"-instance"));n.compute_dimensions()})}}}(jQuery,window,window.document),function(t){"use strict";Foundation.libs.offcanvas={name:"offcanvas",version:"5.5.2",settings:{open_method:"move",close_on_click:!1},init:function(t,e,i){this.bindings(e,i)},events:function(){var e=this,i=e.S,s="",n="",a="";"move"===this.settings.open_method?(s="move-",n="right",a="left"):"overlap_single"===this.settings.open_method?(s="offcanvas-overlap-",n="right",a="left"):"overlap"===this.settings.open_method&&(s="offcanvas-overlap"),i(this.scope).off(".offcanvas").on("click.fndtn.offcanvas",".left-off-canvas-toggle",function(a){e.click_toggle_class(a,s+n),"overlap"!==e.settings.open_method&&i(".left-submenu").removeClass(s+n),t(".left-off-canvas-toggle").attr("aria-expanded","true")}).on("click.fndtn.offcanvas",".left-off-canvas-menu a",function(a){var o=e.get_settings(a),r=i(this).parent();!o.close_on_click||r.hasClass("has-submenu")||r.hasClass("back")?i(this).parent().hasClass("has-submenu")?(a.preventDefault(),i(this).siblings(".left-submenu").toggleClass(s+n)):r.hasClass("back")&&(a.preventDefault(),r.parent().removeClass(s+n)):(e.hide.call(e,s+n,e.get_wrapper(a)),r.parent().removeClass(s+n)),t(".left-off-canvas-toggle").attr("aria-expanded","true")}).on("click.fndtn.offcanvas",".right-off-canvas-toggle",function(n){e.click_toggle_class(n,s+a),"overlap"!==e.settings.open_method&&i(".right-submenu").removeClass(s+a),t(".right-off-canvas-toggle").attr("aria-expanded","true")}).on("click.fndtn.offcanvas",".right-off-canvas-menu a",function(n){var o=e.get_settings(n),r=i(this).parent();!o.close_on_click||r.hasClass("has-submenu")||r.hasClass("back")?i(this).parent().hasClass("has-submenu")?(n.preventDefault(),i(this).siblings(".right-submenu").toggleClass(s+a)):r.hasClass("back")&&(n.preventDefault(),r.parent().removeClass(s+a)):(e.hide.call(e,s+a,e.get_wrapper(n)),r.parent().removeClass(s+a)),t(".right-off-canvas-toggle").attr("aria-expanded","true")}).on("click.fndtn.offcanvas",".exit-off-canvas",function(o){e.click_remove_class(o,s+a),i(".right-submenu").removeClass(s+a),n&&(e.click_remove_class(o,s+n),i(".left-submenu").removeClass(s+a)),t(".right-off-canvas-toggle").attr("aria-expanded","true")}).on("click.fndtn.offcanvas",".exit-off-canvas",function(i){e.click_remove_class(i,s+a),t(".left-off-canvas-toggle").attr("aria-expanded","false"),n&&(e.click_remove_class(i,s+n),t(".right-off-canvas-toggle").attr("aria-expanded","false"))})},toggle:function(t,e){e=e||this.get_wrapper(),e.is("."+t)?this.hide(t,e):this.show(t,e)},show:function(t,e){e=e||this.get_wrapper(),e.trigger("open.fndtn.offcanvas"),e.addClass(t)},hide:function(t,e){e=e||this.get_wrapper(),e.trigger("close.fndtn.offcanvas"),e.removeClass(t)},click_toggle_class:function(t,e){t.preventDefault();var i=this.get_wrapper(t);this.toggle(e,i)},click_remove_class:function(t,e){t.preventDefault();var i=this.get_wrapper(t);this.hide(e,i)},get_settings:function(t){var e=this.S(t.target).closest("["+this.attr_name()+"]");return e.data(this.attr_name(!0)+"-init")||this.settings},get_wrapper:function(t){var e=this.S(t?t.target:this.scope).closest(".off-canvas-wrap");return 0===e.length&&(e=this.S(".off-canvas-wrap")),e},reflow:function(){}}}(jQuery,window,window.document),function(t){"use strict";Foundation.libs.alert={name:"alert",version:"5.5.2",settings:{callback:function(){}},init:function(t,e,i){this.bindings(e,i)},events:function(){var e=this,i=this.S;t(this.scope).off(".alert").on("click.fndtn.alert","["+this.attr_name()+"] .close",function(t){var s=i(this).closest("["+e.attr_name()+"]"),n=s.data(e.attr_name(!0)+"-init")||e.settings;t.preventDefault(),Modernizr.csstransitions?(s.addClass("alert-close"),s.on("transitionend webkitTransitionEnd oTransitionEnd",function(){i(this).trigger("close.fndtn.alert").remove(),n.callback()})):s.fadeOut(300,function(){i(this).trigger("close.fndtn.alert").remove(),n.callback()})})},reflow:function(){}}}(jQuery,window,window.document),function(t,e,i,s){"use strict";function n(t){var e=/fade/i.test(t),i=/pop/i.test(t);return{animate:e||i,pop:i,fade:e}}Foundation.libs.reveal={name:"reveal",version:"5.5.2",locked:!1,settings:{animation:"fadeAndPop",animation_speed:250,close_on_background_click:!0,close_on_esc:!0,dismiss_modal_class:"close-reveal-modal",multiple_opened:!1,bg_class:"reveal-modal-bg",root_element:"body",open:function(){},opened:function(){},close:function(){},closed:function(){},on_ajax_error:t.noop,bg:t(".reveal-modal-bg"),css:{open:{opacity:0,visibility:"visible",display:"block"},close:{opacity:1,visibility:"hidden",display:"none"}}},init:function(e,i,s){t.extend(!0,this.settings,i,s),this.bindings(i,s)},events:function(){var t=this,e=t.S;return e(this.scope).off(".reveal").on("click.fndtn.reveal","["+this.add_namespace("data-reveal-id")+"]:not([disabled])",function(i){if(i.preventDefault(),!t.locked){var s=e(this),n=s.data(t.data_attr("reveal-ajax")),a=s.data(t.data_attr("reveal-replace-content"));if(t.locked=!0,"undefined"==typeof n)t.open.call(t,s);else{var o=n===!0?s.attr("href"):n;t.open.call(t,s,{url:o},{replaceContentSel:a})}}}),e(i).on("click.fndtn.reveal",this.close_targets(),function(i){if(i.preventDefault(),!t.locked){var s=e("["+t.attr_name()+"].open").data(t.attr_name(!0)+"-init")||t.settings,n=e(i.target)[0]===e("."+s.bg_class)[0];if(n){if(!s.close_on_background_click)return;i.stopPropagation()}t.locked=!0,t.close.call(t,n?e("["+t.attr_name()+"].open:not(.toback)"):e(this).closest("["+t.attr_name()+"]"))}}),e("["+t.attr_name()+"]",this.scope).length>0?e(this.scope).on("open.fndtn.reveal",this.settings.open).on("opened.fndtn.reveal",this.settings.opened).on("opened.fndtn.reveal",this.open_video).on("close.fndtn.reveal",this.settings.close).on("closed.fndtn.reveal",this.settings.closed).on("closed.fndtn.reveal",this.close_video):e(this.scope).on("open.fndtn.reveal","["+t.attr_name()+"]",this.settings.open).on("opened.fndtn.reveal","["+t.attr_name()+"]",this.settings.opened).on("opened.fndtn.reveal","["+t.attr_name()+"]",this.open_video).on("close.fndtn.reveal","["+t.attr_name()+"]",this.settings.close).on("closed.fndtn.reveal","["+t.attr_name()+"]",this.settings.closed).on("closed.fndtn.reveal","["+t.attr_name()+"]",this.close_video),!0},key_up_on:function(){var t=this;return t.S("body").off("keyup.fndtn.reveal").on("keyup.fndtn.reveal",function(e){var i=t.S("["+t.attr_name()+"].open"),s=i.data(t.attr_name(!0)+"-init")||t.settings;s&&27===e.which&&s.close_on_esc&&!t.locked&&t.close.call(t,i)}),!0},key_up_off:function(){return this.S("body").off("keyup.fndtn.reveal"),!0},open:function(i,s){var n,a=this;i?"undefined"!=typeof i.selector?n=a.S("#"+i.data(a.data_attr("reveal-id"))).first():(n=a.S(this.scope),s=i):n=a.S(this.scope);var o=n.data(a.attr_name(!0)+"-init");if(o=o||this.settings,n.hasClass("open")&&i.attr("data-reveal-id")==n.attr("id"))return a.close(n);if(!n.hasClass("open")){var r=a.S("["+a.attr_name()+"].open");if("undefined"==typeof n.data("css-top")&&n.data("css-top",parseInt(n.css("top"),10)).data("offset",this.cache_offset(n)),n.attr("tabindex","0").attr("aria-hidden","false"),this.key_up_on(n),n.on("open.fndtn.reveal",function(t){"fndtn.reveal"!==t.namespace}),n.on("open.fndtn.reveal").trigger("open.fndtn.reveal"),r.length<1&&this.toggle_bg(n,!0),"string"==typeof s&&(s={url:s}),"undefined"!=typeof s&&s.url){var l="undefined"!=typeof s.success?s.success:null;t.extend(s,{success:function(e,i,s){if(t.isFunction(l)){var d=l(e,i,s);"string"==typeof d&&(e=d)}"undefined"!=typeof options&&"undefined"!=typeof options.replaceContentSel?n.find(options.replaceContentSel).html(e):n.html(e),a.S(n).foundation("section","reflow"),a.S(n).children().foundation(),r.length>0&&(o.multiple_opened?a.to_back(r):a.hide(r,o.css.close)),a.show(n,o.css.open)}}),o.on_ajax_error!==t.noop&&t.extend(s,{error:o.on_ajax_error}),t.ajax(s)}else r.length>0&&(o.multiple_opened?a.to_back(r):a.hide(r,o.css.close)),this.show(n,o.css.open)}a.S(e).trigger("resize")},close:function(e){var e=e&&e.length?e:this.S(this.scope),i=this.S("["+this.attr_name()+"].open"),s=e.data(this.attr_name(!0)+"-init")||this.settings,n=this;i.length>0&&(e.removeAttr("tabindex","0").attr("aria-hidden","true"),this.locked=!0,this.key_up_off(e),e.trigger("close.fndtn.reveal"),(s.multiple_opened&&1===i.length||!s.multiple_opened||e.length>1)&&(n.toggle_bg(e,!1),n.to_front(e)),s.multiple_opened?(n.hide(e,s.css.close,s),n.to_front(t(t.makeArray(i).reverse()[1]))):n.hide(i,s.css.close,s))},close_targets:function(){var t="."+this.settings.dismiss_modal_class;return this.settings.close_on_background_click?t+", ."+this.settings.bg_class:t},toggle_bg:function(e,i){0===this.S("."+this.settings.bg_class).length&&(this.settings.bg=t("<div />",{"class":this.settings.bg_class}).appendTo("body").hide());var n=this.settings.bg.filter(":visible").length>0;i!=n&&((i==s?n:!i)?this.hide(this.settings.bg):this.show(this.settings.bg))},show:function(i,s){if(s){var a=i.data(this.attr_name(!0)+"-init")||this.settings,o=a.root_element,r=this;if(0===i.parent(o).length){var l=i.wrap(\'<div style="display: none;" />\').parent();i.on("closed.fndtn.reveal.wrapped",function(){i.detach().appendTo(l),i.unwrap().unbind("closed.fndtn.reveal.wrapped")}),i.detach().appendTo(o)}var d=n(a.animation);if(d.animate||(this.locked=!1),d.pop){s.top=t(e).scrollTop()-i.data("offset")+"px";var c={top:t(e).scrollTop()+i.data("css-top")+"px",opacity:1};return setTimeout(function(){return i.css(s).animate(c,a.animation_speed,"linear",function(){r.locked=!1,i.trigger("opened.fndtn.reveal")}).addClass("open")},a.animation_speed/2)}if(d.fade){s.top=t(e).scrollTop()+i.data("css-top")+"px";var c={opacity:1};return setTimeout(function(){return i.css(s).animate(c,a.animation_speed,"linear",function(){r.locked=!1,i.trigger("opened.fndtn.reveal")}).addClass("open")},a.animation_speed/2)}return i.css(s).show().css({opacity:1}).addClass("open").trigger("opened.fndtn.reveal")}var a=this.settings;return n(a.animation).fade?i.fadeIn(a.animation_speed/2):(this.locked=!1,i.show())},to_back:function(t){t.addClass("toback")},to_front:function(t){t.removeClass("toback")},hide:function(i,s){if(s){var a=i.data(this.attr_name(!0)+"-init"),o=this;a=a||this.settings;var r=n(a.animation);if(r.animate||(this.locked=!1),r.pop){var l={top:-t(e).scrollTop()-i.data("offset")+"px",opacity:0};return setTimeout(function(){return i.animate(l,a.animation_speed,"linear",function(){o.locked=!1,i.css(s).trigger("closed.fndtn.reveal")
}).removeClass("open")},a.animation_speed/2)}if(r.fade){var l={opacity:0};return setTimeout(function(){return i.animate(l,a.animation_speed,"linear",function(){o.locked=!1,i.css(s).trigger("closed.fndtn.reveal")}).removeClass("open")},a.animation_speed/2)}return i.hide().css(s).removeClass("open").trigger("closed.fndtn.reveal")}var a=this.settings;return n(a.animation).fade?i.fadeOut(a.animation_speed/2):i.hide()},close_video:function(e){var i=t(".flex-video",e.target),s=t("iframe",i);s.length>0&&(s.attr("data-src",s[0].src),s.attr("src",s.attr("src")),i.hide())},open_video:function(e){var i=t(".flex-video",e.target),n=i.find("iframe");if(n.length>0){var a=n.attr("data-src");if("string"==typeof a)n[0].src=n.attr("data-src");else{var o=n[0].src;n[0].src=s,n[0].src=o}i.show()}},data_attr:function(t){return this.namespace.length>0?this.namespace+"-"+t:t},cache_offset:function(t){var e=t.show().height()+parseInt(t.css("top"),10)+t.scrollY;return t.hide(),e},off:function(){t(this.scope).off(".fndtn.reveal")},reflow:function(){}}}(jQuery,window,window.document),function(t,e){"use strict";Foundation.libs.interchange={name:"interchange",version:"5.5.2",cache:{},images_loaded:!1,nodes_loaded:!1,settings:{load_attr:"interchange",named_queries:{"default":"only screen",small:Foundation.media_queries.small,"small-only":Foundation.media_queries["small-only"],medium:Foundation.media_queries.medium,"medium-only":Foundation.media_queries["medium-only"],large:Foundation.media_queries.large,"large-only":Foundation.media_queries["large-only"],xlarge:Foundation.media_queries.xlarge,"xlarge-only":Foundation.media_queries["xlarge-only"],xxlarge:Foundation.media_queries.xxlarge,landscape:"only screen and (orientation: landscape)",portrait:"only screen and (orientation: portrait)",retina:"only screen and (-webkit-min-device-pixel-ratio: 2),only screen and (min--moz-device-pixel-ratio: 2),only screen and (-o-min-device-pixel-ratio: 2/1),only screen and (min-device-pixel-ratio: 2),only screen and (min-resolution: 192dpi),only screen and (min-resolution: 2dppx)"},directives:{replace:function(e,i,s){if(null!==e&&/IMG/.test(e[0].nodeName)){var n=e[0].src;if(new RegExp(i,"i").test(n))return;return e.attr("src",i),s(e[0].src)}var a=e.data(this.data_attr+"-last-path"),o=this;if(a!=i)return/\.(gif|jpg|jpeg|tiff|png)([?#].*)?/i.test(i)?(t(e).css("background-image","url("+i+")"),e.data("interchange-last-path",i),s(i)):t.get(i,function(t){e.html(t),e.data(o.data_attr+"-last-path",i),s()})}}},init:function(e,i,s){Foundation.inherit(this,"throttle random_str"),this.data_attr=this.set_data_attr(),t.extend(!0,this.settings,i,s),this.bindings(i,s),this.reflow()},get_media_hash:function(){var t="";for(var e in this.settings.named_queries)t+=matchMedia(this.settings.named_queries[e]).matches.toString();return t},events:function(){var i,s=this;return t(e).off(".interchange").on("resize.fndtn.interchange",s.throttle(function(){var t=s.get_media_hash();t!==i&&s.resize(),i=t},50)),this},resize:function(){var e=this.cache;if(!this.images_loaded||!this.nodes_loaded)return void setTimeout(t.proxy(this.resize,this),50);for(var i in e)if(e.hasOwnProperty(i)){var s=this.results(i,e[i]);s&&this.settings.directives[s.scenario[1]].call(this,s.el,s.scenario[0],function(t){if(arguments[0]instanceof Array)var e=arguments[0];else var e=Array.prototype.slice.call(arguments,0);return function(){t.el.trigger(t.scenario[1],e)}}(s))}},results:function(t,e){var i=e.length;if(i>0)for(var s=this.S("["+this.add_namespace("data-uuid")+\'="\'+t+\'"]\');i--;){var n,a=e[i][2];if(n=matchMedia(this.settings.named_queries.hasOwnProperty(a)?this.settings.named_queries[a]:a),n.matches)return{el:s,scenario:e[i]}}return!1},load:function(t,e){return("undefined"==typeof this["cached_"+t]||e)&&this["update_"+t](),this["cached_"+t]},update_images:function(){var t=this.S("img["+this.data_attr+"]"),e=t.length,i=e,s=0,n=this.data_attr;for(this.cache={},this.cached_images=[],this.images_loaded=0===e;i--;){if(s++,t[i]){var a=t[i].getAttribute(n)||"";a.length>0&&this.cached_images.push(t[i])}s===e&&(this.images_loaded=!0,this.enhance("images"))}return this},update_nodes:function(){var t=this.S("["+this.data_attr+"]").not("img"),e=t.length,i=e,s=0,n=this.data_attr;for(this.cached_nodes=[],this.nodes_loaded=0===e;i--;){s++;var a=t[i].getAttribute(n)||"";a.length>0&&this.cached_nodes.push(t[i]),s===e&&(this.nodes_loaded=!0,this.enhance("nodes"))}return this},enhance:function(i){for(var s=this["cached_"+i].length;s--;)this.object(t(this["cached_"+i][s]));return t(e).trigger("resize.fndtn.interchange")},convert_directive:function(t){var e=this.trim(t);return e.length>0?e:"replace"},parse_scenario:function(t){var e=t[0].match(/(.+),\s*(\w+)\s*$/),i=t[1].match(/(.*)\)/);if(e)var s=e[1],n=e[2];else var a=t[0].split(/,\s*$/),s=a[0],n="";return[this.trim(s),this.convert_directive(n),this.trim(i[1])]},object:function(t){var e=this.parse_data_attr(t),i=[],s=e.length;if(s>0)for(;s--;){var n=e[s].split(/,\s?\(/);if(n.length>1){var a=this.parse_scenario(n);i.push(a)}}return this.store(t,i)},store:function(t,e){var i=this.random_str(),s=t.data(this.add_namespace("uuid",!0));return this.cache[s]?this.cache[s]:(t.attr(this.add_namespace("data-uuid"),i),this.cache[i]=e)},trim:function(e){return"string"==typeof e?t.trim(e):e},set_data_attr:function(t){return t?this.namespace.length>0?this.namespace+"-"+this.settings.load_attr:this.settings.load_attr:this.namespace.length>0?"data-"+this.namespace+"-"+this.settings.load_attr:"data-"+this.settings.load_attr},parse_data_attr:function(t){for(var e=t.attr(this.attr_name()).split(/\[(.*?)\]/),i=e.length,s=[];i--;)e[i].replace(/[\W\d]+/,"").length>4&&s.push(e[i]);return s},reflow:function(){this.load("images",!0),this.load("nodes",!0)}}}(jQuery,window,window.document),function(t,e){"use strict";Foundation.libs["magellan-expedition"]={name:"magellan-expedition",version:"5.5.2",settings:{active_class:"active",threshold:0,destination_threshold:20,throttle_delay:30,fixed_top:0,offset_by_height:!0,duration:700,easing:"swing"},init:function(t,e,i){Foundation.inherit(this,"throttle"),this.bindings(e,i)},events:function(){var e=this,i=e.S,s=e.settings;e.set_expedition_position(),i(e.scope).off(".magellan").on("click.fndtn.magellan","["+e.add_namespace("data-magellan-arrival")+"] a[href*=#]",function(i){var s=this.hostname===location.hostname||!this.hostname,n=e.filterPathname(location.pathname)===e.filterPathname(this.pathname),a=this.hash.replace(/(:|\.|\/)/g,"\\$1"),o=this;if(s&&n&&a){i.preventDefault();var r=t(this).closest("["+e.attr_name()+"]"),l=r.data("magellan-expedition-init"),d=this.hash.split("#").join(""),c=t(\'a[name="\'+d+\'"]\');0===c.length&&(c=t("#"+d));var h=c.offset().top-l.destination_threshold+1;l.offset_by_height&&(h-=r.outerHeight()),t("html, body").stop().animate({scrollTop:h},l.duration,l.easing,function(){history.pushState?history.pushState(null,null,o.pathname+"#"+d):location.hash=o.pathname+"#"+d})}}).on("scroll.fndtn.magellan",e.throttle(this.check_for_arrivals.bind(this),s.throttle_delay))},check_for_arrivals:function(){var t=this;t.update_arrivals(),t.update_expedition_positions()},set_expedition_position:function(){var e=this;t("["+this.attr_name()+"=fixed]",e.scope).each(function(){var i,s,n=t(this),a=n.data("magellan-expedition-init"),o=n.attr("styles");n.attr("style",""),i=n.offset().top+a.threshold,s=parseInt(n.data("magellan-fixed-top")),isNaN(s)||(e.settings.fixed_top=s),n.data(e.data_attr("magellan-top-offset"),i),n.attr("style",o)})},update_expedition_positions:function(){var i=this,s=t(e).scrollTop();t("["+this.attr_name()+"=fixed]",i.scope).each(function(){var e=t(this),n=e.data("magellan-expedition-init"),a=e.attr("style"),o=e.data("magellan-top-offset");if(s+i.settings.fixed_top>=o){var r=e.prev("["+i.add_namespace("data-magellan-expedition-clone")+"]");0===r.length&&(r=e.clone(),r.removeAttr(i.attr_name()),r.attr(i.add_namespace("data-magellan-expedition-clone"),""),e.before(r)),e.css({position:"fixed",top:n.fixed_top}).addClass("fixed")}else e.prev("["+i.add_namespace("data-magellan-expedition-clone")+"]").remove(),e.attr("style",a).css("position","").css("top","").removeClass("fixed")})},update_arrivals:function(){var i=this,s=t(e).scrollTop();t("["+this.attr_name()+"]",i.scope).each(function(){var e=t(this),n=e.data(i.attr_name(!0)+"-init"),a=i.offsets(e,s),o=e.find("["+i.add_namespace("data-magellan-arrival")+"]"),r=!1;a.each(function(t,s){if(s.viewport_offset>=s.top_offset){var a=e.find("["+i.add_namespace("data-magellan-arrival")+"]");return a.not(s.arrival).removeClass(n.active_class),s.arrival.addClass(n.active_class),r=!0,!0}}),r||o.removeClass(n.active_class)})},offsets:function(e,i){var s=this,n=e.data(s.attr_name(!0)+"-init"),a=i;return e.find("["+s.add_namespace("data-magellan-arrival")+"]").map(function(){var i=t(this).data(s.data_attr("magellan-arrival")),o=t("["+s.add_namespace("data-magellan-destination")+"="+i+"]");if(o.length>0){var r=o.offset().top-n.destination_threshold;return n.offset_by_height&&(r-=e.outerHeight()),r=Math.floor(r),{destination:o,arrival:t(this),top_offset:r,viewport_offset:a}}}).sort(function(t,e){return t.top_offset<e.top_offset?-1:t.top_offset>e.top_offset?1:0})},data_attr:function(t){return this.namespace.length>0?this.namespace+"-"+t:t},off:function(){this.S(this.scope).off(".magellan"),this.S(e).off(".magellan")},filterPathname:function(t){return t=t||"",t.replace(/^\//,"").replace(/(?:index|default).[a-zA-Z]{3,4}$/,"").replace(/\/$/,"")},reflow:function(){var e=this;t("["+e.add_namespace("data-magellan-expedition-clone")+"]",e.scope).remove()}}}(jQuery,window,window.document),function(t){"use strict";Foundation.libs.accordion={name:"accordion",version:"5.5.2",settings:{content_class:"content",active_class:"active",multi_expand:!1,toggleable:!0,callback:function(){}},init:function(t,e,i){this.bindings(e,i)},events:function(e){var i=this,s=this.S;i.create(this.S(e)),s(this.scope).off(".fndtn.accordion").on("click.fndtn.accordion","["+this.attr_name()+"] > dd > a, ["+this.attr_name()+"] > li > a",function(e){var n=s(this).closest("["+i.attr_name()+"]"),a=i.attr_name()+"="+n.attr(i.attr_name()),o=n.data(i.attr_name(!0)+"-init")||i.settings,r=s("#"+this.href.split("#")[1]),l=t("> dd, > li",n),d=l.children("."+o.content_class),c=d.filter("."+o.active_class);return e.preventDefault(),n.attr(i.attr_name())&&(d=d.add("["+a+"] dd > ."+o.content_class+", ["+a+"] li > ."+o.content_class),l=l.add("["+a+"] dd, ["+a+"] li")),o.toggleable&&r.is(c)?(r.parent("dd, li").toggleClass(o.active_class,!1),r.toggleClass(o.active_class,!1),s(this).attr("aria-expanded",function(t,e){return"true"===e?"false":"true"}),o.callback(r),r.triggerHandler("toggled",[n]),void n.triggerHandler("toggled",[r])):(o.multi_expand||(d.removeClass(o.active_class),l.removeClass(o.active_class),l.children("a").attr("aria-expanded","false")),r.addClass(o.active_class).parent().addClass(o.active_class),o.callback(r),r.triggerHandler("toggled",[n]),n.triggerHandler("toggled",[r]),void s(this).attr("aria-expanded","true"))})},create:function(e){var i=this,s=e,n=t("> .accordion-navigation",s),a=s.data(i.attr_name(!0)+"-init")||i.settings;n.children("a").attr("aria-expanded","false"),n.has("."+a.content_class+"."+a.active_class).children("a").attr("aria-expanded","true"),a.multi_expand&&e.attr("aria-multiselectable","true")},off:function(){},reflow:function(){}}}(jQuery,window,window.document),function(t,e,i){"use strict";Foundation.libs.topbar={name:"topbar",version:"5.5.2",settings:{index:0,start_offset:0,sticky_class:"sticky",custom_back_text:!0,back_text:"Back",mobile_show_parent_link:!0,is_hover:!0,scrolltop:!0,sticky_on:"all",dropdown_autoclose:!0},init:function(e,i,s){Foundation.inherit(this,"add_custom_rule register_media throttle");var n=this;n.register_media("topbar","foundation-mq-topbar"),this.bindings(i,s),n.S("["+this.attr_name()+"]",this.scope).each(function(){{var e=t(this),i=e.data(n.attr_name(!0)+"-init");n.S("section, .top-bar-section",this)}e.data("index",0);var s=e.parent();s.hasClass("fixed")||n.is_sticky(e,s,i)?(n.settings.sticky_class=i.sticky_class,n.settings.sticky_topbar=e,e.data("height",s.outerHeight()),e.data("stickyoffset",s.offset().top)):e.data("height",e.outerHeight()),i.assembled||n.assemble(e),i.is_hover?n.S(".has-dropdown",e).addClass("not-click"):n.S(".has-dropdown",e).removeClass("not-click"),n.add_custom_rule(".f-topbar-fixed { padding-top: "+e.data("height")+"px }"),s.hasClass("fixed")&&n.S("body").addClass("f-topbar-fixed")})},is_sticky:function(t,e,i){var s=e.hasClass(i.sticky_class),n=matchMedia(Foundation.media_queries.small).matches,a=matchMedia(Foundation.media_queries.medium).matches,o=matchMedia(Foundation.media_queries.large).matches;return s&&"all"===i.sticky_on?!0:s&&this.small()&&-1!==i.sticky_on.indexOf("small")&&n&&!a&&!o?!0:s&&this.medium()&&-1!==i.sticky_on.indexOf("medium")&&n&&a&&!o?!0:s&&this.large()&&-1!==i.sticky_on.indexOf("large")&&n&&a&&o?!0:!1},toggle:function(i){var s,n=this;s=i?n.S(i).closest("["+this.attr_name()+"]"):n.S("["+this.attr_name()+"]");var a=s.data(this.attr_name(!0)+"-init"),o=n.S("section, .top-bar-section",s);n.breakpoint()&&(n.rtl?(o.css({right:"0%"}),t(">.name",o).css({right:"100%"})):(o.css({left:"0%"}),t(">.name",o).css({left:"100%"})),n.S("li.moved",o).removeClass("moved"),s.data("index",0),s.toggleClass("expanded").css("height","")),a.scrolltop?s.hasClass("expanded")?s.parent().hasClass("fixed")&&(a.scrolltop?(s.parent().removeClass("fixed"),s.addClass("fixed"),n.S("body").removeClass("f-topbar-fixed"),e.scrollTo(0,0)):s.parent().removeClass("expanded")):s.hasClass("fixed")&&(s.parent().addClass("fixed"),s.removeClass("fixed"),n.S("body").addClass("f-topbar-fixed")):(n.is_sticky(s,s.parent(),a)&&s.parent().addClass("fixed"),s.parent().hasClass("fixed")&&(s.hasClass("expanded")?(s.addClass("fixed"),s.parent().addClass("expanded"),n.S("body").addClass("f-topbar-fixed")):(s.removeClass("fixed"),s.parent().removeClass("expanded"),n.update_sticky_positioning())))},timer:null,events:function(){var i=this,s=this.S;s(this.scope).off(".topbar").on("click.fndtn.topbar","["+this.attr_name()+"] .toggle-topbar",function(t){t.preventDefault(),i.toggle(this)}).on("click.fndtn.topbar contextmenu.fndtn.topbar",\'.top-bar .top-bar-section li a[href^="#"],[\'+this.attr_name()+\'] .top-bar-section li a[href^="#"]\',function(){var e=t(this).closest("li"),s=e.closest("["+i.attr_name()+"]"),n=s.data(i.attr_name(!0)+"-init");if(n.dropdown_autoclose&&n.is_hover){var a=t(this).closest(".hover");a.removeClass("hover")}!i.breakpoint()||e.hasClass("back")||e.hasClass("has-dropdown")||i.toggle()}).on("click.fndtn.topbar","["+this.attr_name()+"] li.has-dropdown",function(e){var n=s(this),a=s(e.target),o=n.closest("["+i.attr_name()+"]"),r=o.data(i.attr_name(!0)+"-init");return a.data("revealId")?void i.toggle():void(i.breakpoint()||(!r.is_hover||Modernizr.touch)&&(e.stopImmediatePropagation(),n.hasClass("hover")?(n.removeClass("hover").find("li").removeClass("hover"),n.parents("li.hover").removeClass("hover")):(n.addClass("hover"),t(n).siblings().removeClass("hover"),"A"===a[0].nodeName&&a.parent().hasClass("has-dropdown")&&e.preventDefault())))}).on("click.fndtn.topbar","["+this.attr_name()+"] .has-dropdown>a",function(t){if(i.breakpoint()){t.preventDefault();var e=s(this),n=e.closest("["+i.attr_name()+"]"),a=n.find("section, .top-bar-section"),o=(e.next(".dropdown").outerHeight(),e.closest("li"));n.data("index",n.data("index")+1),o.addClass("moved"),i.rtl?(a.css({right:-(100*n.data("index"))+"%"}),a.find(">.name").css({right:100*n.data("index")+"%"})):(a.css({left:-(100*n.data("index"))+"%"}),a.find(">.name").css({left:100*n.data("index")+"%"})),n.css("height",e.siblings("ul").outerHeight(!0)+n.data("height"))}}),s(e).off(".topbar").on("resize.fndtn.topbar",i.throttle(function(){i.resize.call(i)},50)).trigger("resize.fndtn.topbar").load(function(){s(this).trigger("resize.fndtn.topbar")}),s("body").off(".topbar").on("click.fndtn.topbar",function(t){var e=s(t.target).closest("li").closest("li.hover");e.length>0||s("["+i.attr_name()+"] li.hover").removeClass("hover")}),s(this.scope).on("click.fndtn.topbar","["+this.attr_name()+"] .has-dropdown .back",function(t){t.preventDefault();var e=s(this),n=e.closest("["+i.attr_name()+"]"),a=n.find("section, .top-bar-section"),o=(n.data(i.attr_name(!0)+"-init"),e.closest("li.moved")),r=o.parent();n.data("index",n.data("index")-1),i.rtl?(a.css({right:-(100*n.data("index"))+"%"}),a.find(">.name").css({right:100*n.data("index")+"%"})):(a.css({left:-(100*n.data("index"))+"%"}),a.find(">.name").css({left:100*n.data("index")+"%"})),0===n.data("index")?n.css("height",""):n.css("height",r.outerHeight(!0)+n.data("height")),setTimeout(function(){o.removeClass("moved")},300)}),s(this.scope).find(".dropdown a").focus(function(){t(this).parents(".has-dropdown").addClass("hover")}).blur(function(){t(this).parents(".has-dropdown").removeClass("hover")})},resize:function(){var t=this;t.S("["+this.attr_name()+"]").each(function(){var e,s=t.S(this),n=s.data(t.attr_name(!0)+"-init"),a=s.parent("."+t.settings.sticky_class);if(!t.breakpoint()){var o=s.hasClass("expanded");s.css("height","").removeClass("expanded").find("li").removeClass("hover"),o&&t.toggle(s)}t.is_sticky(s,a,n)&&(a.hasClass("fixed")?(a.removeClass("fixed"),e=a.offset().top,t.S(i.body).hasClass("f-topbar-fixed")&&(e-=s.data("height")),s.data("stickyoffset",e),a.addClass("fixed")):(e=a.offset().top,s.data("stickyoffset",e)))})},breakpoint:function(){return!matchMedia(Foundation.media_queries.topbar).matches},small:function(){return matchMedia(Foundation.media_queries.small).matches},medium:function(){return matchMedia(Foundation.media_queries.medium).matches},large:function(){return matchMedia(Foundation.media_queries.large).matches},assemble:function(e){var i=this,s=e.data(this.attr_name(!0)+"-init"),n=i.S("section, .top-bar-section",e);n.detach(),i.S(".has-dropdown>a",n).each(function(){var e,n=i.S(this),a=n.siblings(".dropdown"),o=n.attr("href");a.find(".title.back").length||(e=t(1==s.mobile_show_parent_link&&o?\'<li class="title back js-generated"><h5><a href="javascript:void(0)"></a></h5></li><li class="parent-link hide-for-medium-up"><a class="parent-link js-generated" href="\'+o+\'">\'+n.html()+"</a></li>":\'<li class="title back js-generated"><h5><a href="javascript:void(0)"></a></h5>\'),t("h5>a",e).html(1==s.custom_back_text?s.back_text:"&laquo; "+n.html()),a.prepend(e))}),n.appendTo(e),this.sticky(),this.assembled(e)},assembled:function(e){e.data(this.attr_name(!0),t.extend({},e.data(this.attr_name(!0)),{assembled:!0}))},height:function(e){var i=0,s=this;return t("> li",e).each(function(){i+=s.S(this).outerHeight(!0)}),i},sticky:function(){var t=this;this.S(e).on("scroll",function(){t.update_sticky_positioning()})},update_sticky_positioning:function(){var t="."+this.settings.sticky_class,i=this.S(e),s=this;if(s.settings.sticky_topbar&&s.is_sticky(this.settings.sticky_topbar,this.settings.sticky_topbar.parent(),this.settings)){var n=this.settings.sticky_topbar.data("stickyoffset")+this.settings.start_offset;s.S(t).hasClass("expanded")||(i.scrollTop()>n?s.S(t).hasClass("fixed")||(s.S(t).addClass("fixed"),s.S("body").addClass("f-topbar-fixed")):i.scrollTop()<=n&&s.S(t).hasClass("fixed")&&(s.S(t).removeClass("fixed"),s.S("body").removeClass("f-topbar-fixed")))}},off:function(){this.S(this.scope).off(".fndtn.topbar"),this.S(e).off(".fndtn.topbar")},reflow:function(){}}}(jQuery,window,window.document),function(t,e,i,s){"use strict";Foundation.libs.tab={name:"tab",version:"5.5.2",settings:{active_class:"active",callback:function(){},deep_linking:!1,scroll_to_content:!0,is_hover:!1},default_tab_hashes:[],init:function(t,i,s){var n=this,a=this.S;a("["+this.attr_name()+"] > .active > a",this.scope).each(function(){n.default_tab_hashes.push(this.hash)}),n.entry_location=e.location.href,this.bindings(i,s),this.handle_location_hash_change()},events:function(){var t=this,i=this.S,s=function(e,s){var n=i(s).closest("["+t.attr_name()+"]").data(t.attr_name(!0)+"-init");(!n.is_hover||Modernizr.touch)&&(e.preventDefault(),e.stopPropagation(),t.toggle_active_tab(i(s).parent()))};i(this.scope).off(".tab").on("keydown.fndtn.tab","["+this.attr_name()+"] > * > a",function(t){var e=this,i=t.keyCode||t.which;9==i&&(t.preventDefault(),s(t,e))}).on("click.fndtn.tab","["+this.attr_name()+"] > * > a",function(t){var e=this;s(t,e)}).on("mouseenter.fndtn.tab","["+this.attr_name()+"] > * > a",function(){var e=i(this).closest("["+t.attr_name()+"]").data(t.attr_name(!0)+"-init");e.is_hover&&t.toggle_active_tab(i(this).parent())}),i(e).on("hashchange.fndtn.tab",function(e){e.preventDefault(),t.handle_location_hash_change()})},handle_location_hash_change:function(){var e=this,i=this.S;i("["+this.attr_name()+"]",this.scope).each(function(){var n=i(this).data(e.attr_name(!0)+"-init");if(n.deep_linking){var a;if(a=n.scroll_to_content?e.scope.location.hash:e.scope.location.hash.replace("fndtn-",""),""!=a){var o=i(a);if(o.hasClass("content")&&o.parent().hasClass("tabs-content"))e.toggle_active_tab(t("["+e.attr_name()+"] > * > a[href="+a+"]").parent());else{var r=o.closest(".content").attr("id");r!=s&&e.toggle_active_tab(t("["+e.attr_name()+"] > * > a[href=#"+r+"]").parent(),a)}}else for(var l=0;l<e.default_tab_hashes.length;l++)e.toggle_active_tab(t("["+e.attr_name()+"] > * > a[href="+e.default_tab_hashes[l]+"]").parent())}})},toggle_active_tab:function(n,a){var o=this,r=o.S,l=n.closest("["+this.attr_name()+"]"),d=n.find("a"),c=n.children("a").first(),h="#"+c.attr("href").split("#")[1],u=r(h),f=n.siblings(),p=l.data(this.attr_name(!0)+"-init"),g=function(e){var s,n=t(this),a=t(this).parents("li").prev().children(\'[role="tab"]\'),o=t(this).parents("li").next().children(\'[role="tab"]\');switch(e.keyCode){case 37:s=a;break;case 39:s=o;break;default:s=!1}s.length&&(n.attr({tabindex:"-1","aria-selected":null}),s.attr({tabindex:"0","aria-selected":!0}).focus()),t(\'[role="tabpanel"]\').attr("aria-hidden","true"),t("#"+t(i.activeElement).attr("href").substring(1)).attr("aria-hidden",null)},_=function(t){var i=e.location.href===o.entry_location,s=p.scroll_to_content?o.default_tab_hashes[0]:i?e.location.hash:"fndtn-"+o.default_tab_hashes[0].replace("#","");i&&t===s||(e.location.hash=t)};c.data("tab-content")&&(h="#"+c.data("tab-content").split("#")[1],u=r(h)),p.deep_linking&&(p.scroll_to_content?(_(a||h),a==s||a==h?n.parent()[0].scrollIntoView():r(h)[0].scrollIntoView()):_(a!=s?"fndtn-"+a.replace("#",""):"fndtn-"+h.replace("#",""))),n.addClass(p.active_class).triggerHandler("opened"),d.attr({"aria-selected":"true",tabindex:0}),f.removeClass(p.active_class),f.find("a").attr({"aria-selected":"false",tabindex:-1}),u.siblings().removeClass(p.active_class).attr({"aria-hidden":"true",tabindex:-1}),u.addClass(p.active_class).attr("aria-hidden","false").removeAttr("tabindex"),p.callback(n),u.triggerHandler("toggled",[u]),l.triggerHandler("toggled",[n]),d.off("keydown").on("keydown",g)},data_attr:function(t){return this.namespace.length>0?this.namespace+"-"+t:t},off:function(){},reflow:function(){}}}(jQuery,window,window.document),function(t,e,i){"use strict";Foundation.libs.abide={name:"abide",version:"5.5.2",settings:{live_validate:!0,validate_on_blur:!0,focus_on_invalid:!0,error_labels:!0,error_class:"error",timeout:1e3,patterns:{alpha:/^[a-zA-Z]+$/,alpha_numeric:/^[a-zA-Z0-9]+$/,integer:/^[-+]?\d+$/,number:/^[-+]?\d*(?:[\.\,]\d+)?$/,card:/^(?:4[0-9]{12}(?:[0-9]{3})?|5[1-5][0-9]{14}|6(?:011|5[0-9][0-9])[0-9]{12}|3[47][0-9]{13}|3(?:0[0-5]|[68][0-9])[0-9]{11}|(?:2131|1800|35\d{3})\d{11})$/,cvv:/^([0-9]){3,4}$/,email:/^[a-zA-Z0-9.!#$%&\'*+\/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)+$/,url:/^(https?|ftp|file|ssh):\/\/([-;:&=\+\$,\w]+@{1})?([-A-Za-z0-9\.]+)+:?(\d+)?((\/[-\+~%\/\.\w]+)?\??([-\+=&;%@\.\w]+)?#?([\w]+)?)?/,domain:/^([a-zA-Z0-9]([a-zA-Z0-9\-]{0,61}[a-zA-Z0-9])?\.)+[a-zA-Z]{2,8}$/,datetime:/^([0-2][0-9]{3})\-([0-1][0-9])\-([0-3][0-9])T([0-5][0-9])\:([0-5][0-9])\:([0-5][0-9])(Z|([\-\+]([0-1][0-9])\:00))$/,date:/(?:19|20)[0-9]{2}-(?:(?:0[1-9]|1[0-2])-(?:0[1-9]|1[0-9]|2[0-9])|(?:(?!02)(?:0[1-9]|1[0-2])-(?:30))|(?:(?:0[13578]|1[02])-31))$/,time:/^(0[0-9]|1[0-9]|2[0-3])(:[0-5][0-9]){2}$/,dateISO:/^\d{4}[\/\-]\d{1,2}[\/\-]\d{1,2}$/,month_day_year:/^(0[1-9]|1[012])[- \/.](0[1-9]|[12][0-9]|3[01])[- \/.]\d{4}$/,day_month_year:/^(0[1-9]|[12][0-9]|3[01])[- \/.](0[1-9]|1[012])[- \/.]\d{4}$/,color:/^#?([a-fA-F0-9]{6}|[a-fA-F0-9]{3})$/},validators:{equalTo:function(t){var e=i.getElementById(t.getAttribute(this.add_namespace("data-equalto"))).value,s=t.value,n=e===s;return n}}},timer:null,init:function(t,e,i){this.bindings(e,i)},events:function(e){function i(t,e){clearTimeout(s.timer),s.timer=setTimeout(function(){s.validate([t],e)}.bind(t),a.timeout)}var s=this,n=s.S(e).attr("novalidate","novalidate"),a=n.data(this.attr_name(!0)+"-init")||{};this.invalid_attr=this.add_namespace("data-invalid"),n.off(".abide").on("submit.fndtn.abide",function(t){var e=/ajax/i.test(s.S(this).attr(s.attr_name()));return s.validate(s.S(this).find("input, textarea, select").not(":hidden, [data-abide-ignore]").get(),t,e)}).on("validate.fndtn.abide",function(t){"manual"===a.validate_on&&s.validate([t.target],t)}).on("reset",function(e){return s.reset(t(this),e)}).find("input, textarea, select").not(":hidden, [data-abide-ignore]").off(".abide").on("blur.fndtn.abide change.fndtn.abide",function(t){a.validate_on_blur&&a.validate_on_blur===!0&&i(this,t),"change"===a.validate_on&&i(this,t)}).on("keydown.fndtn.abide",function(t){a.live_validate&&a.live_validate===!0&&9!=t.which&&i(this,t),"tab"===a.validate_on&&9===t.which?i(this,t):"change"===a.validate_on&&i(this,t)}).on("focus",function(e){navigator.userAgent.match(/iPad|iPhone|Android|BlackBerry|Windows Phone|webOS/i)&&t("html, body").animate({scrollTop:t(e.target).offset().top},100)})},reset:function(e){var i=this;e.removeAttr(i.invalid_attr),t("["+i.invalid_attr+"]",e).removeAttr(i.invalid_attr),t("."+i.settings.error_class,e).not("small").removeClass(i.settings.error_class),t(":input",e).not(":button, :submit, :reset, :hidden, [data-abide-ignore]").val("").removeAttr(i.invalid_attr)},validate:function(t,e,i){for(var s=this.parse_patterns(t),n=s.length,a=this.S(t[0]).closest("form"),o=/submit/.test(e.type),r=0;n>r;r++)if(!s[r]&&(o||i))return this.settings.focus_on_invalid&&t[r].focus(),a.trigger("invalid.fndtn.abide"),this.S(t[r]).closest("form").attr(this.invalid_attr,""),!1;return(o||i)&&a.trigger("valid.fndtn.abide"),a.removeAttr(this.invalid_attr),i?!1:!0},parse_patterns:function(t){for(var e=t.length,i=[];e--;)i.push(this.pattern(t[e]));return this.check_validation_and_apply_styles(i)},pattern:function(t){var e=t.getAttribute("type"),i="string"==typeof t.getAttribute("required"),s=t.getAttribute("pattern")||"";return this.settings.patterns.hasOwnProperty(s)&&s.length>0?[t,this.settings.patterns[s],i]:s.length>0?[t,new RegExp(s),i]:this.settings.patterns.hasOwnProperty(e)?[t,this.settings.patterns[e],i]:(s=/.*/,[t,s,i])},check_validation_and_apply_styles:function(e){var i=e.length,s=[],n=this.S(e[0][0]).closest("[data-"+this.attr_name(!0)+"]");for(n.data(this.attr_name(!0)+"-init")||{};i--;){var a,o,r=e[i][0],l=e[i][2],d=r.value.trim(),c=this.S(r).parent(),h=r.getAttribute(this.add_namespace("data-abide-validator")),u="radio"===r.type,f="checkbox"===r.type,p=this.S(\'label[for="\'+r.getAttribute("id")+\'"]\'),g=l?r.value.length>0:!0,_=[];if(r.getAttribute(this.add_namespace("data-equalto"))&&(h="equalTo"),a=c.is("label")?c.parent():c,u&&l)_.push(this.valid_radio(r,l));else if(f&&l)_.push(this.valid_checkbox(r,l));else if(h){for(var m=h.split(" "),v=!0,b=!0,x=0;x<m.length;x++)o=this.settings.validators[m[x]].apply(this,[r,l,a]),_.push(o),b=o&&v,v=o;b?(this.S(r).removeAttr(this.invalid_attr),a.removeClass("error"),p.length>0&&this.settings.error_labels&&p.removeClass(this.settings.error_class).removeAttr("role"),t(r).triggerHandler("valid")):(this.S(r).attr(this.invalid_attr,""),a.addClass("error"),p.length>0&&this.settings.error_labels&&p.addClass(this.settings.error_class).attr("role","alert"),t(r).triggerHandler("invalid"))}else if(_.push(e[i][1].test(d)&&g||!l&&r.value.length<1||t(r).attr("disabled")?!0:!1),_=[_.every(function(t){return t})],_[0])this.S(r).removeAttr(this.invalid_attr),r.setAttribute("aria-invalid","false"),r.removeAttribute("aria-describedby"),a.removeClass(this.settings.error_class),p.length>0&&this.settings.error_labels&&p.removeClass(this.settings.error_class).removeAttr("role"),t(r).triggerHandler("valid");else{this.S(r).attr(this.invalid_attr,""),r.setAttribute("aria-invalid","true");var y=a.find("small."+this.settings.error_class,"span."+this.settings.error_class),w=y.length>0?y[0].id:"";w.length>0&&r.setAttribute("aria-describedby",w),a.addClass(this.settings.error_class),p.length>0&&this.settings.error_labels&&p.addClass(this.settings.error_class).attr("role","alert"),t(r).triggerHandler("invalid")}s=s.concat(_)}return s},valid_checkbox:function(e,i){var e=this.S(e),s=e.is(":checked")||!i||e.get(0).getAttribute("disabled");return s?(e.removeAttr(this.invalid_attr).parent().removeClass(this.settings.error_class),t(e).triggerHandler("valid")):(e.attr(this.invalid_attr,"").parent().addClass(this.settings.error_class),t(e).triggerHandler("invalid")),s},valid_radio:function(e){for(var i=e.getAttribute("name"),s=this.S(e).closest("[data-"+this.attr_name(!0)+"]").find("[name=\'"+i+"\']"),n=s.length,a=!1,o=!1,r=0;n>r;r++)s[r].getAttribute("disabled")?(o=!0,a=!0):s[r].checked?a=!0:o&&(a=!1);for(var r=0;n>r;r++)a?(this.S(s[r]).removeAttr(this.invalid_attr).parent().removeClass(this.settings.error_class),t(s[r]).triggerHandler("valid")):(this.S(s[r]).attr(this.invalid_attr,"").parent().addClass(this.settings.error_class),t(s[r]).triggerHandler("invalid"));return a},valid_equal:function(t,e,s){var n=i.getElementById(t.getAttribute(this.add_namespace("data-equalto"))).value,a=t.value,o=n===a;return o?(this.S(t).removeAttr(this.invalid_attr),s.removeClass(this.settings.error_class),label.length>0&&settings.error_labels&&label.removeClass(this.settings.error_class)):(this.S(t).attr(this.invalid_attr,""),s.addClass(this.settings.error_class),label.length>0&&settings.error_labels&&label.addClass(this.settings.error_class)),o},valid_oneof:function(t,e,i,s){var t=this.S(t),n=this.S("["+this.add_namespace("data-oneof")+"]"),a=n.filter(":checked").length>0;if(a?t.removeAttr(this.invalid_attr).parent().removeClass(this.settings.error_class):t.attr(this.invalid_attr,"").parent().addClass(this.settings.error_class),!s){var o=this;n.each(function(){o.valid_oneof.call(o,this,null,null,!0)})}return a},reflow:function(){var t=this,e=t.S("["+this.attr_name()+"]").attr("novalidate","novalidate");t.S(e).each(function(e,i){t.events(i)})}}}(jQuery,window,window.document),function(t,e){"use strict";Foundation.libs.tooltip={name:"tooltip",version:"5.5.2",settings:{additional_inheritable_classes:[],tooltip_class:".tooltip",append_to:"body",touch_close_text:"Tap To Close",disable_for_touch:!1,hover_delay:200,show_on:"all",tip_template:function(t,e){return\'<span data-selector="\'+t+\'" id="\'+t+\'" class="\'+Foundation.libs.tooltip.settings.tooltip_class.substring(1)+\'" role="tooltip">\'+e+\'<span class="nub"></span></span>\'}},cache:{},init:function(t,e,i){Foundation.inherit(this,"random_str"),this.bindings(e,i)},should_show:function(e){var i=t.extend({},this.settings,this.data_options(e));return"all"===i.show_on?!0:this.small()&&"small"===i.show_on?!0:this.medium()&&"medium"===i.show_on?!0:this.large()&&"large"===i.show_on?!0:!1},medium:function(){return matchMedia(Foundation.media_queries.medium).matches},large:function(){return matchMedia(Foundation.media_queries.large).matches},events:function(e){function i(t,e,i){t.timer||(i?(t.timer=null,n.showTip(e)):t.timer=setTimeout(function(){t.timer=null,n.showTip(e)}.bind(t),n.settings.hover_delay))}function s(t,e){t.timer&&(clearTimeout(t.timer),t.timer=null),n.hide(e)}var n=this,a=n.S;n.create(this.S(e)),t(this.scope).off(".tooltip").on("mouseenter.fndtn.tooltip mouseleave.fndtn.tooltip touchstart.fndtn.tooltip MSPointerDown.fndtn.tooltip","["+this.attr_name()+"]",function(e){var o=a(this),r=t.extend({},n.settings,n.data_options(o)),l=!1;if(Modernizr.touch&&/touchstart|MSPointerDown/i.test(e.type)&&a(e.target).is("a"))return!1;if(/mouse/i.test(e.type)&&n.ie_touch(e))return!1;if(o.hasClass("open"))Modernizr.touch&&/touchstart|MSPointerDown/i.test(e.type)&&e.preventDefault(),n.hide(o);else{if(r.disable_for_touch&&Modernizr.touch&&/touchstart|MSPointerDown/i.test(e.type))return;if(!r.disable_for_touch&&Modernizr.touch&&/touchstart|MSPointerDown/i.test(e.type)&&(e.preventDefault(),a(r.tooltip_class+".open").hide(),l=!0,t(".open["+n.attr_name()+"]").length>0)){var d=a(t(".open["+n.attr_name()+"]")[0]);
n.hide(d)}/enter|over/i.test(e.type)?i(this,o):"mouseout"===e.type||"mouseleave"===e.type?s(this,o):i(this,o,!0)}}).on("mouseleave.fndtn.tooltip touchstart.fndtn.tooltip MSPointerDown.fndtn.tooltip","["+this.attr_name()+"].open",function(e){return/mouse/i.test(e.type)&&n.ie_touch(e)?!1:void(("touch"!=t(this).data("tooltip-open-event-type")||"mouseleave"!=e.type)&&("mouse"==t(this).data("tooltip-open-event-type")&&/MSPointerDown|touchstart/i.test(e.type)?n.convert_to_touch(t(this)):s(this,t(this))))}).on("DOMNodeRemoved DOMAttrModified","["+this.attr_name()+"]:not(a)",function(){s(this,a(this))})},ie_touch:function(){return!1},showTip:function(t){var e=this.getTip(t);return this.should_show(t,e)?this.show(t):void 0},getTip:function(e){var i=this.selector(e),s=t.extend({},this.settings,this.data_options(e)),n=null;return i&&(n=this.S(\'span[data-selector="\'+i+\'"]\'+s.tooltip_class)),"object"==typeof n?n:!1},selector:function(t){var e=t.attr(this.attr_name())||t.attr("data-selector");return"string"!=typeof e&&(e=this.random_str(6),t.attr("data-selector",e).attr("aria-describedby",e)),e},create:function(i){var s=this,n=t.extend({},this.settings,this.data_options(i)),a=this.settings.tip_template;"string"==typeof n.tip_template&&e.hasOwnProperty(n.tip_template)&&(a=e[n.tip_template]);var o=t(a(this.selector(i),t("<div></div>").html(i.attr("title")).html())),r=this.inheritable_classes(i);o.addClass(r).appendTo(n.append_to),Modernizr.touch&&(o.append(\'<span class="tap-to-close">\'+n.touch_close_text+"</span>"),o.on("touchstart.fndtn.tooltip MSPointerDown.fndtn.tooltip",function(){s.hide(i)})),i.removeAttr("title").attr("title","")},reposition:function(e,i,s){var n,a,o,r,l;if(i.css("visibility","hidden").show(),n=e.data("width"),a=i.children(".nub"),o=a.outerHeight(),r=a.outerHeight(),i.css(this.small()?{width:"100%"}:{width:n?n:"auto"}),l=function(t,e,i,s,n){return t.css({top:e?e:"auto",bottom:s?s:"auto",left:n?n:"auto",right:i?i:"auto"}).end()},l(i,e.offset().top+e.outerHeight()+10,"auto","auto",e.offset().left),this.small())l(i,e.offset().top+e.outerHeight()+10,"auto","auto",12.5,t(this.scope).width()),i.addClass("tip-override"),l(a,-o,"auto","auto",e.offset().left);else{var d=e.offset().left;Foundation.rtl&&(a.addClass("rtl"),d=e.offset().left+e.outerWidth()-i.outerWidth()),l(i,e.offset().top+e.outerHeight()+10,"auto","auto",d),a.attr("style")&&a.removeAttr("style"),i.removeClass("tip-override"),s&&s.indexOf("tip-top")>-1?(Foundation.rtl&&a.addClass("rtl"),l(i,e.offset().top-i.outerHeight(),"auto","auto",d).removeClass("tip-override")):s&&s.indexOf("tip-left")>-1?(l(i,e.offset().top+e.outerHeight()/2-i.outerHeight()/2,"auto","auto",e.offset().left-i.outerWidth()-o).removeClass("tip-override"),a.removeClass("rtl")):s&&s.indexOf("tip-right")>-1&&(l(i,e.offset().top+e.outerHeight()/2-i.outerHeight()/2,"auto","auto",e.offset().left+e.outerWidth()+o).removeClass("tip-override"),a.removeClass("rtl"))}i.css("visibility","visible").hide()},small:function(){return matchMedia(Foundation.media_queries.small).matches&&!matchMedia(Foundation.media_queries.medium).matches},inheritable_classes:function(e){var i=t.extend({},this.settings,this.data_options(e)),s=["tip-top","tip-left","tip-bottom","tip-right","radius","round"].concat(i.additional_inheritable_classes),n=e.attr("class"),a=n?t.map(n.split(" "),function(e){return-1!==t.inArray(e,s)?e:void 0}).join(" "):"";return t.trim(a)},convert_to_touch:function(e){var i=this,s=i.getTip(e),n=t.extend({},i.settings,i.data_options(e));0===s.find(".tap-to-close").length&&(s.append(\'<span class="tap-to-close">\'+n.touch_close_text+"</span>"),s.on("click.fndtn.tooltip.tapclose touchstart.fndtn.tooltip.tapclose MSPointerDown.fndtn.tooltip.tapclose",function(){i.hide(e)})),e.data("tooltip-open-event-type","touch")},show:function(t){var e=this.getTip(t);"touch"==t.data("tooltip-open-event-type")&&this.convert_to_touch(t),this.reposition(t,e,t.attr("class")),t.addClass("open"),e.fadeIn(150)},hide:function(t){var e=this.getTip(t);e.fadeOut(150,function(){e.find(".tap-to-close").remove(),e.off("click.fndtn.tooltip.tapclose MSPointerDown.fndtn.tapclose"),t.removeClass("open")})},off:function(){var e=this;this.S(this.scope).off(".fndtn.tooltip"),this.S(this.settings.tooltip_class).each(function(i){t("["+e.attr_name()+"]").eq(i).attr("title",t(this).text())}).remove()},reflow:function(){}}}(jQuery,window,window.document);';

        if ($withHtmlTag) {
            $result .= '
</script>
';
        }

        return $result;
    }

    /**
     * Returns an instance with a specific name.
     *
     * @param string $name The optional name of the instance.
     *
     * @return phpDeeBuk
     */
    public static function getInstance($name = null) {
        $name = str_ireplace("\t", '    ', $name);
        $name = str_ireplace(' ' , '_'   , $name);

        $name = trim(strtoupper($name));

        if (!isset(self::$_instances[$name])) {
            $newInstance        = new self();
            $newInstance->_name = $name;

            self::$_instances[$name] = $newInstance;
        }

        return self::$_instances[$name];
    }

    /**
     * Gets the source code of jQuery.
     *
     * @param bool $withHtmlTag With HTML tags or not.
     *
     * @return string The jQuery code.
     */
    public static function getJQuery($withHtmlTag = false) {
        $result = '';

        if ($withHtmlTag) {
            $result .= '
<script type="text/javascript">
';
        }

        $result .= '/*! jQuery v2.1.3 | (c) 2005, 2014 jQuery Foundation, Inc. | jquery.org/license */
!function(a,b){"object"==typeof module&&"object"==typeof module.exports?module.exports=a.document?b(a,!0):function(a){if(!a.document)throw new Error("jQuery requires a window with a document");return b(a)}:b(a)}("undefined"!=typeof window?window:this,function(a,b){var c=[],d=c.slice,e=c.concat,f=c.push,g=c.indexOf,h={},i=h.toString,j=h.hasOwnProperty,k={},l=a.document,m="2.1.3",n=function(a,b){return new n.fn.init(a,b)},o=/^[\\s\\uFEFF\\xA0]+|[\\s\\uFEFF\\xA0]+$/g,p=/^-ms-/,q=/-([\\da-z])/gi,r=function(a,b){return b.toUpperCase()};n.fn=n.prototype={jquery:m,constructor:n,selector:"",length:0,toArray:function(){return d.call(this)},get:function(a){return null!=a?0>a?this[a+this.length]:this[a]:d.call(this)},pushStack:function(a){var b=n.merge(this.constructor(),a);return b.prevObject=this,b.context=this.context,b},each:function(a,b){return n.each(this,a,b)},map:function(a){return this.pushStack(n.map(this,function(b,c){return a.call(b,c,b)}))},slice:function(){return this.pushStack(d.apply(this,arguments))},first:function(){return this.eq(0)},last:function(){return this.eq(-1)},eq:function(a){var b=this.length,c=+a+(0>a?b:0);return this.pushStack(c>=0&&b>c?[this[c]]:[])},end:function(){return this.prevObject||this.constructor(null)},push:f,sort:c.sort,splice:c.splice},n.extend=n.fn.extend=function(){var a,b,c,d,e,f,g=arguments[0]||{},h=1,i=arguments.length,j=!1;for("boolean"==typeof g&&(j=g,g=arguments[h]||{},h++),"object"==typeof g||n.isFunction(g)||(g={}),h===i&&(g=this,h--);i>h;h++)if(null!=(a=arguments[h]))for(b in a)c=g[b],d=a[b],g!==d&&(j&&d&&(n.isPlainObject(d)||(e=n.isArray(d)))?(e?(e=!1,f=c&&n.isArray(c)?c:[]):f=c&&n.isPlainObject(c)?c:{},g[b]=n.extend(j,f,d)):void 0!==d&&(g[b]=d));return g},n.extend({expando:"jQuery"+(m+Math.random()).replace(/\\D/g,""),isReady:!0,error:function(a){throw new Error(a)},noop:function(){},isFunction:function(a){return"function"===n.type(a)},isArray:Array.isArray,isWindow:function(a){return null!=a&&a===a.window},isNumeric:function(a){return!n.isArray(a)&&a-parseFloat(a)+1>=0},isPlainObject:function(a){return"object"!==n.type(a)||a.nodeType||n.isWindow(a)?!1:a.constructor&&!j.call(a.constructor.prototype,"isPrototypeOf")?!1:!0},isEmptyObject:function(a){var b;for(b in a)return!1;return!0},type:function(a){return null==a?a+"":"object"==typeof a||"function"==typeof a?h[i.call(a)]||"object":typeof a},globalEval:function(a){var b,c=eval;a=n.trim(a),a&&(1===a.indexOf("use strict")?(b=l.createElement("script"),b.text=a,l.head.appendChild(b).parentNode.removeChild(b)):c(a))},camelCase:function(a){return a.replace(p,"ms-").replace(q,r)},nodeName:function(a,b){return a.nodeName&&a.nodeName.toLowerCase()===b.toLowerCase()},each:function(a,b,c){var d,e=0,f=a.length,g=s(a);if(c){if(g){for(;f>e;e++)if(d=b.apply(a[e],c),d===!1)break}else for(e in a)if(d=b.apply(a[e],c),d===!1)break}else if(g){for(;f>e;e++)if(d=b.call(a[e],e,a[e]),d===!1)break}else for(e in a)if(d=b.call(a[e],e,a[e]),d===!1)break;return a},trim:function(a){return null==a?"":(a+"").replace(o,"")},makeArray:function(a,b){var c=b||[];return null!=a&&(s(Object(a))?n.merge(c,"string"==typeof a?[a]:a):f.call(c,a)),c},inArray:function(a,b,c){return null==b?-1:g.call(b,a,c)},merge:function(a,b){for(var c=+b.length,d=0,e=a.length;c>d;d++)a[e++]=b[d];return a.length=e,a},grep:function(a,b,c){for(var d,e=[],f=0,g=a.length,h=!c;g>f;f++)d=!b(a[f],f),d!==h&&e.push(a[f]);return e},map:function(a,b,c){var d,f=0,g=a.length,h=s(a),i=[];if(h)for(;g>f;f++)d=b(a[f],f,c),null!=d&&i.push(d);else for(f in a)d=b(a[f],f,c),null!=d&&i.push(d);return e.apply([],i)},guid:1,proxy:function(a,b){var c,e,f;return"string"==typeof b&&(c=a[b],b=a,a=c),n.isFunction(a)?(e=d.call(arguments,2),f=function(){return a.apply(b||this,e.concat(d.call(arguments)))},f.guid=a.guid=a.guid||n.guid++,f):void 0},now:Date.now,support:k}),n.each("Boolean Number String Function Array Date RegExp Object Error".split(" "),function(a,b){h["[object "+b+"]"]=b.toLowerCase()});function s(a){var b=a.length,c=n.type(a);return"function"===c||n.isWindow(a)?!1:1===a.nodeType&&b?!0:"array"===c||0===b||"number"==typeof b&&b>0&&b-1 in a}var t=function(a){var b,c,d,e,f,g,h,i,j,k,l,m,n,o,p,q,r,s,t,u="sizzle"+1*new Date,v=a.document,w=0,x=0,y=hb(),z=hb(),A=hb(),B=function(a,b){return a===b&&(l=!0),0},C=1<<31,D={}.hasOwnProperty,E=[],F=E.pop,G=E.push,H=E.push,I=E.slice,J=function(a,b){for(var c=0,d=a.length;d>c;c++)if(a[c]===b)return c;return-1},K="checked|selected|async|autofocus|autoplay|controls|defer|disabled|hidden|ismap|loop|multiple|open|readonly|required|scoped",L="[\\\\x20\\\\t\\\\r\\\\n\\\\f]",M="(?:\\\\\\\\.|[\\\\w-]|[^\\\\x00-\\\\xa0])+",N=M.replace("w","w#"),O="\\\\["+L+"*("+M+")(?:"+L+"*([*^$|!~]?=)"+L+"*(?:\'((?:\\\\\\\\.|[^\\\\\\\\\'])*)\'|\\"((?:\\\\\\\\.|[^\\\\\\\\\\"])*)\\"|("+N+"))|)"+L+"*\\\\]",P=":("+M+")(?:\\\\(((\'((?:\\\\\\\\.|[^\\\\\\\\\'])*)\'|\\"((?:\\\\\\\\.|[^\\\\\\\\\\"])*)\\")|((?:\\\\\\\\.|[^\\\\\\\\()[\\\\]]|"+O+")*)|.*)\\\\)|)",Q=new RegExp(L+"+","g"),R=new RegExp("^"+L+"+|((?:^|[^\\\\\\\\])(?:\\\\\\\\.)*)"+L+"+$","g"),S=new RegExp("^"+L+"*,"+L+"*"),T=new RegExp("^"+L+"*([>+~]|"+L+")"+L+"*"),U=new RegExp("="+L+"*([^\\\\]\'\\"]*?)"+L+"*\\\\]","g"),V=new RegExp(P),W=new RegExp("^"+N+"$"),X={ID:new RegExp("^#("+M+")"),CLASS:new RegExp("^\\\\.("+M+")"),TAG:new RegExp("^("+M.replace("w","w*")+")"),ATTR:new RegExp("^"+O),PSEUDO:new RegExp("^"+P),CHILD:new RegExp("^:(only|first|last|nth|nth-last)-(child|of-type)(?:\\\\("+L+"*(even|odd|(([+-]|)(\\\\d*)n|)"+L+"*(?:([+-]|)"+L+"*(\\\\d+)|))"+L+"*\\\\)|)","i"),bool:new RegExp("^(?:"+K+")$","i"),needsContext:new RegExp("^"+L+"*[>+~]|:(even|odd|eq|gt|lt|nth|first|last)(?:\\\\("+L+"*((?:-\\\\d)?\\\\d*)"+L+"*\\\\)|)(?=[^-]|$)","i")},Y=/^(?:input|select|textarea|button)$/i,Z=/^h\\d$/i,$=/^[^{]+\\{\\s*\\[native \\w/,_=/^(?:#([\\w-]+)|(\\w+)|\\.([\\w-]+))$/,ab=/[+~]/,bb=/\'|\\\\/g,cb=new RegExp("\\\\\\\\([\\\\da-f]{1,6}"+L+"?|("+L+")|.)","ig"),db=function(a,b,c){var d="0x"+b-65536;return d!==d||c?b:0>d?String.fromCharCode(d+65536):String.fromCharCode(d>>10|55296,1023&d|56320)},eb=function(){m()};try{H.apply(E=I.call(v.childNodes),v.childNodes),E[v.childNodes.length].nodeType}catch(fb){H={apply:E.length?function(a,b){G.apply(a,I.call(b))}:function(a,b){var c=a.length,d=0;while(a[c++]=b[d++]);a.length=c-1}}}function gb(a,b,d,e){var f,h,j,k,l,o,r,s,w,x;if((b?b.ownerDocument||b:v)!==n&&m(b),b=b||n,d=d||[],k=b.nodeType,"string"!=typeof a||!a||1!==k&&9!==k&&11!==k)return d;if(!e&&p){if(11!==k&&(f=_.exec(a)))if(j=f[1]){if(9===k){if(h=b.getElementById(j),!h||!h.parentNode)return d;if(h.id===j)return d.push(h),d}else if(b.ownerDocument&&(h=b.ownerDocument.getElementById(j))&&t(b,h)&&h.id===j)return d.push(h),d}else{if(f[2])return H.apply(d,b.getElementsByTagName(a)),d;if((j=f[3])&&c.getElementsByClassName)return H.apply(d,b.getElementsByClassName(j)),d}if(c.qsa&&(!q||!q.test(a))){if(s=r=u,w=b,x=1!==k&&a,1===k&&"object"!==b.nodeName.toLowerCase()){o=g(a),(r=b.getAttribute("id"))?s=r.replace(bb,"\\\\$&"):b.setAttribute("id",s),s="[id=\'"+s+"\'] ",l=o.length;while(l--)o[l]=s+rb(o[l]);w=ab.test(a)&&pb(b.parentNode)||b,x=o.join(",")}if(x)try{return H.apply(d,w.querySelectorAll(x)),d}catch(y){}finally{r||b.removeAttribute("id")}}}return i(a.replace(R,"$1"),b,d,e)}function hb(){var a=[];function b(c,e){return a.push(c+" ")>d.cacheLength&&delete b[a.shift()],b[c+" "]=e}return b}function ib(a){return a[u]=!0,a}function jb(a){var b=n.createElement("div");try{return!!a(b)}catch(c){return!1}finally{b.parentNode&&b.parentNode.removeChild(b),b=null}}function kb(a,b){var c=a.split("|"),e=a.length;while(e--)d.attrHandle[c[e]]=b}function lb(a,b){var c=b&&a,d=c&&1===a.nodeType&&1===b.nodeType&&(~b.sourceIndex||C)-(~a.sourceIndex||C);if(d)return d;if(c)while(c=c.nextSibling)if(c===b)return-1;return a?1:-1}function mb(a){return function(b){var c=b.nodeName.toLowerCase();return"input"===c&&b.type===a}}function nb(a){return function(b){var c=b.nodeName.toLowerCase();return("input"===c||"button"===c)&&b.type===a}}function ob(a){return ib(function(b){return b=+b,ib(function(c,d){var e,f=a([],c.length,b),g=f.length;while(g--)c[e=f[g]]&&(c[e]=!(d[e]=c[e]))})})}function pb(a){return a&&"undefined"!=typeof a.getElementsByTagName&&a}c=gb.support={},f=gb.isXML=function(a){var b=a&&(a.ownerDocument||a).documentElement;return b?"HTML"!==b.nodeName:!1},m=gb.setDocument=function(a){var b,e,g=a?a.ownerDocument||a:v;return g!==n&&9===g.nodeType&&g.documentElement?(n=g,o=g.documentElement,e=g.defaultView,e&&e!==e.top&&(e.addEventListener?e.addEventListener("unload",eb,!1):e.attachEvent&&e.attachEvent("onunload",eb)),p=!f(g),c.attributes=jb(function(a){return a.className="i",!a.getAttribute("className")}),c.getElementsByTagName=jb(function(a){return a.appendChild(g.createComment("")),!a.getElementsByTagName("*").length}),c.getElementsByClassName=$.test(g.getElementsByClassName),c.getById=jb(function(a){return o.appendChild(a).id=u,!g.getElementsByName||!g.getElementsByName(u).length}),c.getById?(d.find.ID=function(a,b){if("undefined"!=typeof b.getElementById&&p){var c=b.getElementById(a);return c&&c.parentNode?[c]:[]}},d.filter.ID=function(a){var b=a.replace(cb,db);return function(a){return a.getAttribute("id")===b}}):(delete d.find.ID,d.filter.ID=function(a){var b=a.replace(cb,db);return function(a){var c="undefined"!=typeof a.getAttributeNode&&a.getAttributeNode("id");return c&&c.value===b}}),d.find.TAG=c.getElementsByTagName?function(a,b){return"undefined"!=typeof b.getElementsByTagName?b.getElementsByTagName(a):c.qsa?b.querySelectorAll(a):void 0}:function(a,b){var c,d=[],e=0,f=b.getElementsByTagName(a);if("*"===a){while(c=f[e++])1===c.nodeType&&d.push(c);return d}return f},d.find.CLASS=c.getElementsByClassName&&function(a,b){return p?b.getElementsByClassName(a):void 0},r=[],q=[],(c.qsa=$.test(g.querySelectorAll))&&(jb(function(a){o.appendChild(a).innerHTML="<a id=\'"+u+"\'></a><select id=\'"+u+"-\\f]\' msallowcapture=\'\'><option selected=\'\'></option></select>",a.querySelectorAll("[msallowcapture^=\'\']").length&&q.push("[*^$]="+L+"*(?:\'\'|\\"\\")"),a.querySelectorAll("[selected]").length||q.push("\\\\["+L+"*(?:value|"+K+")"),a.querySelectorAll("[id~="+u+"-]").length||q.push("~="),a.querySelectorAll(":checked").length||q.push(":checked"),a.querySelectorAll("a#"+u+"+*").length||q.push(".#.+[+~]")}),jb(function(a){var b=g.createElement("input");b.setAttribute("type","hidden"),a.appendChild(b).setAttribute("name","D"),a.querySelectorAll("[name=d]").length&&q.push("name"+L+"*[*^$|!~]?="),a.querySelectorAll(":enabled").length||q.push(":enabled",":disabled"),a.querySelectorAll("*,:x"),q.push(",.*:")})),(c.matchesSelector=$.test(s=o.matches||o.webkitMatchesSelector||o.mozMatchesSelector||o.oMatchesSelector||o.msMatchesSelector))&&jb(function(a){c.disconnectedMatch=s.call(a,"div"),s.call(a,"[s!=\'\']:x"),r.push("!=",P)}),q=q.length&&new RegExp(q.join("|")),r=r.length&&new RegExp(r.join("|")),b=$.test(o.compareDocumentPosition),t=b||$.test(o.contains)?function(a,b){var c=9===a.nodeType?a.documentElement:a,d=b&&b.parentNode;return a===d||!(!d||1!==d.nodeType||!(c.contains?c.contains(d):a.compareDocumentPosition&&16&a.compareDocumentPosition(d)))}:function(a,b){if(b)while(b=b.parentNode)if(b===a)return!0;return!1},B=b?function(a,b){if(a===b)return l=!0,0;var d=!a.compareDocumentPosition-!b.compareDocumentPosition;return d?d:(d=(a.ownerDocument||a)===(b.ownerDocument||b)?a.compareDocumentPosition(b):1,1&d||!c.sortDetached&&b.compareDocumentPosition(a)===d?a===g||a.ownerDocument===v&&t(v,a)?-1:b===g||b.ownerDocument===v&&t(v,b)?1:k?J(k,a)-J(k,b):0:4&d?-1:1)}:function(a,b){if(a===b)return l=!0,0;var c,d=0,e=a.parentNode,f=b.parentNode,h=[a],i=[b];if(!e||!f)return a===g?-1:b===g?1:e?-1:f?1:k?J(k,a)-J(k,b):0;if(e===f)return lb(a,b);c=a;while(c=c.parentNode)h.unshift(c);c=b;while(c=c.parentNode)i.unshift(c);while(h[d]===i[d])d++;return d?lb(h[d],i[d]):h[d]===v?-1:i[d]===v?1:0},g):n},gb.matches=function(a,b){return gb(a,null,null,b)},gb.matchesSelector=function(a,b){if((a.ownerDocument||a)!==n&&m(a),b=b.replace(U,"=\'$1\']"),!(!c.matchesSelector||!p||r&&r.test(b)||q&&q.test(b)))try{var d=s.call(a,b);if(d||c.disconnectedMatch||a.document&&11!==a.document.nodeType)return d}catch(e){}return gb(b,n,null,[a]).length>0},gb.contains=function(a,b){return(a.ownerDocument||a)!==n&&m(a),t(a,b)},gb.attr=function(a,b){(a.ownerDocument||a)!==n&&m(a);var e=d.attrHandle[b.toLowerCase()],f=e&&D.call(d.attrHandle,b.toLowerCase())?e(a,b,!p):void 0;return void 0!==f?f:c.attributes||!p?a.getAttribute(b):(f=a.getAttributeNode(b))&&f.specified?f.value:null},gb.error=function(a){throw new Error("Syntax error, unrecognized expression: "+a)},gb.uniqueSort=function(a){var b,d=[],e=0,f=0;if(l=!c.detectDuplicates,k=!c.sortStable&&a.slice(0),a.sort(B),l){while(b=a[f++])b===a[f]&&(e=d.push(f));while(e--)a.splice(d[e],1)}return k=null,a},e=gb.getText=function(a){var b,c="",d=0,f=a.nodeType;if(f){if(1===f||9===f||11===f){if("string"==typeof a.textContent)return a.textContent;for(a=a.firstChild;a;a=a.nextSibling)c+=e(a)}else if(3===f||4===f)return a.nodeValue}else while(b=a[d++])c+=e(b);return c},d=gb.selectors={cacheLength:50,createPseudo:ib,match:X,attrHandle:{},find:{},relative:{">":{dir:"parentNode",first:!0}," ":{dir:"parentNode"},"+":{dir:"previousSibling",first:!0},"~":{dir:"previousSibling"}},preFilter:{ATTR:function(a){return a[1]=a[1].replace(cb,db),a[3]=(a[3]||a[4]||a[5]||"").replace(cb,db),"~="===a[2]&&(a[3]=" "+a[3]+" "),a.slice(0,4)},CHILD:function(a){return a[1]=a[1].toLowerCase(),"nth"===a[1].slice(0,3)?(a[3]||gb.error(a[0]),a[4]=+(a[4]?a[5]+(a[6]||1):2*("even"===a[3]||"odd"===a[3])),a[5]=+(a[7]+a[8]||"odd"===a[3])):a[3]&&gb.error(a[0]),a},PSEUDO:function(a){var b,c=!a[6]&&a[2];return X.CHILD.test(a[0])?null:(a[3]?a[2]=a[4]||a[5]||"":c&&V.test(c)&&(b=g(c,!0))&&(b=c.indexOf(")",c.length-b)-c.length)&&(a[0]=a[0].slice(0,b),a[2]=c.slice(0,b)),a.slice(0,3))}},filter:{TAG:function(a){var b=a.replace(cb,db).toLowerCase();return"*"===a?function(){return!0}:function(a){return a.nodeName&&a.nodeName.toLowerCase()===b}},CLASS:function(a){var b=y[a+" "];return b||(b=new RegExp("(^|"+L+")"+a+"("+L+"|$)"))&&y(a,function(a){return b.test("string"==typeof a.className&&a.className||"undefined"!=typeof a.getAttribute&&a.getAttribute("class")||"")})},ATTR:function(a,b,c){return function(d){var e=gb.attr(d,a);return null==e?"!="===b:b?(e+="","="===b?e===c:"!="===b?e!==c:"^="===b?c&&0===e.indexOf(c):"*="===b?c&&e.indexOf(c)>-1:"$="===b?c&&e.slice(-c.length)===c:"~="===b?(" "+e.replace(Q," ")+" ").indexOf(c)>-1:"|="===b?e===c||e.slice(0,c.length+1)===c+"-":!1):!0}},CHILD:function(a,b,c,d,e){var f="nth"!==a.slice(0,3),g="last"!==a.slice(-4),h="of-type"===b;return 1===d&&0===e?function(a){return!!a.parentNode}:function(b,c,i){var j,k,l,m,n,o,p=f!==g?"nextSibling":"previousSibling",q=b.parentNode,r=h&&b.nodeName.toLowerCase(),s=!i&&!h;if(q){if(f){while(p){l=b;while(l=l[p])if(h?l.nodeName.toLowerCase()===r:1===l.nodeType)return!1;o=p="only"===a&&!o&&"nextSibling"}return!0}if(o=[g?q.firstChild:q.lastChild],g&&s){k=q[u]||(q[u]={}),j=k[a]||[],n=j[0]===w&&j[1],m=j[0]===w&&j[2],l=n&&q.childNodes[n];while(l=++n&&l&&l[p]||(m=n=0)||o.pop())if(1===l.nodeType&&++m&&l===b){k[a]=[w,n,m];break}}else if(s&&(j=(b[u]||(b[u]={}))[a])&&j[0]===w)m=j[1];else while(l=++n&&l&&l[p]||(m=n=0)||o.pop())if((h?l.nodeName.toLowerCase()===r:1===l.nodeType)&&++m&&(s&&((l[u]||(l[u]={}))[a]=[w,m]),l===b))break;return m-=e,m===d||m%d===0&&m/d>=0}}},PSEUDO:function(a,b){var c,e=d.pseudos[a]||d.setFilters[a.toLowerCase()]||gb.error("unsupported pseudo: "+a);return e[u]?e(b):e.length>1?(c=[a,a,"",b],d.setFilters.hasOwnProperty(a.toLowerCase())?ib(function(a,c){var d,f=e(a,b),g=f.length;while(g--)d=J(a,f[g]),a[d]=!(c[d]=f[g])}):function(a){return e(a,0,c)}):e}},pseudos:{not:ib(function(a){var b=[],c=[],d=h(a.replace(R,"$1"));return d[u]?ib(function(a,b,c,e){var f,g=d(a,null,e,[]),h=a.length;while(h--)(f=g[h])&&(a[h]=!(b[h]=f))}):function(a,e,f){return b[0]=a,d(b,null,f,c),b[0]=null,!c.pop()}}),has:ib(function(a){return function(b){return gb(a,b).length>0}}),contains:ib(function(a){return a=a.replace(cb,db),function(b){return(b.textContent||b.innerText||e(b)).indexOf(a)>-1}}),lang:ib(function(a){return W.test(a||"")||gb.error("unsupported lang: "+a),a=a.replace(cb,db).toLowerCase(),function(b){var c;do if(c=p?b.lang:b.getAttribute("xml:lang")||b.getAttribute("lang"))return c=c.toLowerCase(),c===a||0===c.indexOf(a+"-");while((b=b.parentNode)&&1===b.nodeType);return!1}}),target:function(b){var c=a.location&&a.location.hash;return c&&c.slice(1)===b.id},root:function(a){return a===o},focus:function(a){return a===n.activeElement&&(!n.hasFocus||n.hasFocus())&&!!(a.type||a.href||~a.tabIndex)},enabled:function(a){return a.disabled===!1},disabled:function(a){return a.disabled===!0},checked:function(a){var b=a.nodeName.toLowerCase();return"input"===b&&!!a.checked||"option"===b&&!!a.selected},selected:function(a){return a.parentNode&&a.parentNode.selectedIndex,a.selected===!0},empty:function(a){for(a=a.firstChild;a;a=a.nextSibling)if(a.nodeType<6)return!1;return!0},parent:function(a){return!d.pseudos.empty(a)},header:function(a){return Z.test(a.nodeName)},input:function(a){return Y.test(a.nodeName)},button:function(a){var b=a.nodeName.toLowerCase();return"input"===b&&"button"===a.type||"button"===b},text:function(a){var b;return"input"===a.nodeName.toLowerCase()&&"text"===a.type&&(null==(b=a.getAttribute("type"))||"text"===b.toLowerCase())},first:ob(function(){return[0]}),last:ob(function(a,b){return[b-1]}),eq:ob(function(a,b,c){return[0>c?c+b:c]}),even:ob(function(a,b){for(var c=0;b>c;c+=2)a.push(c);return a}),odd:ob(function(a,b){for(var c=1;b>c;c+=2)a.push(c);return a}),lt:ob(function(a,b,c){for(var d=0>c?c+b:c;--d>=0;)a.push(d);return a}),gt:ob(function(a,b,c){for(var d=0>c?c+b:c;++d<b;)a.push(d);return a})}},d.pseudos.nth=d.pseudos.eq;for(b in{radio:!0,checkbox:!0,file:!0,password:!0,image:!0})d.pseudos[b]=mb(b);for(b in{submit:!0,reset:!0})d.pseudos[b]=nb(b);function qb(){}qb.prototype=d.filters=d.pseudos,d.setFilters=new qb,g=gb.tokenize=function(a,b){var c,e,f,g,h,i,j,k=z[a+" "];if(k)return b?0:k.slice(0);h=a,i=[],j=d.preFilter;while(h){(!c||(e=S.exec(h)))&&(e&&(h=h.slice(e[0].length)||h),i.push(f=[])),c=!1,(e=T.exec(h))&&(c=e.shift(),f.push({value:c,type:e[0].replace(R," ")}),h=h.slice(c.length));for(g in d.filter)!(e=X[g].exec(h))||j[g]&&!(e=j[g](e))||(c=e.shift(),f.push({value:c,type:g,matches:e}),h=h.slice(c.length));if(!c)break}return b?h.length:h?gb.error(a):z(a,i).slice(0)};function rb(a){for(var b=0,c=a.length,d="";c>b;b++)d+=a[b].value;return d}function sb(a,b,c){var d=b.dir,e=c&&"parentNode"===d,f=x++;return b.first?function(b,c,f){while(b=b[d])if(1===b.nodeType||e)return a(b,c,f)}:function(b,c,g){var h,i,j=[w,f];if(g){while(b=b[d])if((1===b.nodeType||e)&&a(b,c,g))return!0}else while(b=b[d])if(1===b.nodeType||e){if(i=b[u]||(b[u]={}),(h=i[d])&&h[0]===w&&h[1]===f)return j[2]=h[2];if(i[d]=j,j[2]=a(b,c,g))return!0}}}function tb(a){return a.length>1?function(b,c,d){var e=a.length;while(e--)if(!a[e](b,c,d))return!1;return!0}:a[0]}function ub(a,b,c){for(var d=0,e=b.length;e>d;d++)gb(a,b[d],c);return c}function vb(a,b,c,d,e){for(var f,g=[],h=0,i=a.length,j=null!=b;i>h;h++)(f=a[h])&&(!c||c(f,d,e))&&(g.push(f),j&&b.push(h));return g}function wb(a,b,c,d,e,f){return d&&!d[u]&&(d=wb(d)),e&&!e[u]&&(e=wb(e,f)),ib(function(f,g,h,i){var j,k,l,m=[],n=[],o=g.length,p=f||ub(b||"*",h.nodeType?[h]:h,[]),q=!a||!f&&b?p:vb(p,m,a,h,i),r=c?e||(f?a:o||d)?[]:g:q;if(c&&c(q,r,h,i),d){j=vb(r,n),d(j,[],h,i),k=j.length;while(k--)(l=j[k])&&(r[n[k]]=!(q[n[k]]=l))}if(f){if(e||a){if(e){j=[],k=r.length;while(k--)(l=r[k])&&j.push(q[k]=l);e(null,r=[],j,i)}k=r.length;while(k--)(l=r[k])&&(j=e?J(f,l):m[k])>-1&&(f[j]=!(g[j]=l))}}else r=vb(r===g?r.splice(o,r.length):r),e?e(null,g,r,i):H.apply(g,r)})}function xb(a){for(var b,c,e,f=a.length,g=d.relative[a[0].type],h=g||d.relative[" "],i=g?1:0,k=sb(function(a){return a===b},h,!0),l=sb(function(a){return J(b,a)>-1},h,!0),m=[function(a,c,d){var e=!g&&(d||c!==j)||((b=c).nodeType?k(a,c,d):l(a,c,d));return b=null,e}];f>i;i++)if(c=d.relative[a[i].type])m=[sb(tb(m),c)];else{if(c=d.filter[a[i].type].apply(null,a[i].matches),c[u]){for(e=++i;f>e;e++)if(d.relative[a[e].type])break;return wb(i>1&&tb(m),i>1&&rb(a.slice(0,i-1).concat({value:" "===a[i-2].type?"*":""})).replace(R,"$1"),c,e>i&&xb(a.slice(i,e)),f>e&&xb(a=a.slice(e)),f>e&&rb(a))}m.push(c)}return tb(m)}function yb(a,b){var c=b.length>0,e=a.length>0,f=function(f,g,h,i,k){var l,m,o,p=0,q="0",r=f&&[],s=[],t=j,u=f||e&&d.find.TAG("*",k),v=w+=null==t?1:Math.random()||.1,x=u.length;for(k&&(j=g!==n&&g);q!==x&&null!=(l=u[q]);q++){if(e&&l){m=0;while(o=a[m++])if(o(l,g,h)){i.push(l);break}k&&(w=v)}c&&((l=!o&&l)&&p--,f&&r.push(l))}if(p+=q,c&&q!==p){m=0;while(o=b[m++])o(r,s,g,h);if(f){if(p>0)while(q--)r[q]||s[q]||(s[q]=F.call(i));s=vb(s)}H.apply(i,s),k&&!f&&s.length>0&&p+b.length>1&&gb.uniqueSort(i)}return k&&(w=v,j=t),r};return c?ib(f):f}return h=gb.compile=function(a,b){var c,d=[],e=[],f=A[a+" "];if(!f){b||(b=g(a)),c=b.length;while(c--)f=xb(b[c]),f[u]?d.push(f):e.push(f);f=A(a,yb(e,d)),f.selector=a}return f},i=gb.select=function(a,b,e,f){var i,j,k,l,m,n="function"==typeof a&&a,o=!f&&g(a=n.selector||a);if(e=e||[],1===o.length){if(j=o[0]=o[0].slice(0),j.length>2&&"ID"===(k=j[0]).type&&c.getById&&9===b.nodeType&&p&&d.relative[j[1].type]){if(b=(d.find.ID(k.matches[0].replace(cb,db),b)||[])[0],!b)return e;n&&(b=b.parentNode),a=a.slice(j.shift().value.length)}i=X.needsContext.test(a)?0:j.length;while(i--){if(k=j[i],d.relative[l=k.type])break;if((m=d.find[l])&&(f=m(k.matches[0].replace(cb,db),ab.test(j[0].type)&&pb(b.parentNode)||b))){if(j.splice(i,1),a=f.length&&rb(j),!a)return H.apply(e,f),e;break}}}return(n||h(a,o))(f,b,!p,e,ab.test(a)&&pb(b.parentNode)||b),e},c.sortStable=u.split("").sort(B).join("")===u,c.detectDuplicates=!!l,m(),c.sortDetached=jb(function(a){return 1&a.compareDocumentPosition(n.createElement("div"))}),jb(function(a){return a.innerHTML="<a href=\'#\'></a>","#"===a.firstChild.getAttribute("href")})||kb("type|href|height|width",function(a,b,c){return c?void 0:a.getAttribute(b,"type"===b.toLowerCase()?1:2)}),c.attributes&&jb(function(a){return a.innerHTML="<input/>",a.firstChild.setAttribute("value",""),""===a.firstChild.getAttribute("value")})||kb("value",function(a,b,c){return c||"input"!==a.nodeName.toLowerCase()?void 0:a.defaultValue}),jb(function(a){return null==a.getAttribute("disabled")})||kb(K,function(a,b,c){var d;return c?void 0:a[b]===!0?b.toLowerCase():(d=a.getAttributeNode(b))&&d.specified?d.value:null}),gb}(a);n.find=t,n.expr=t.selectors,n.expr[":"]=n.expr.pseudos,n.unique=t.uniqueSort,n.text=t.getText,n.isXMLDoc=t.isXML,n.contains=t.contains;var u=n.expr.match.needsContext,v=/^<(\\w+)\\s*\\/?>(?:<\\/\\1>|)$/,w=/^.[^:#\\[\\.,]*$/;function x(a,b,c){if(n.isFunction(b))return n.grep(a,function(a,d){return!!b.call(a,d,a)!==c});if(b.nodeType)return n.grep(a,function(a){return a===b!==c});if("string"==typeof b){if(w.test(b))return n.filter(b,a,c);b=n.filter(b,a)}return n.grep(a,function(a){return g.call(b,a)>=0!==c})}n.filter=function(a,b,c){var d=b[0];return c&&(a=":not("+a+")"),1===b.length&&1===d.nodeType?n.find.matchesSelector(d,a)?[d]:[]:n.find.matches(a,n.grep(b,function(a){return 1===a.nodeType}))},n.fn.extend({find:function(a){var b,c=this.length,d=[],e=this;if("string"!=typeof a)return this.pushStack(n(a).filter(function(){for(b=0;c>b;b++)if(n.contains(e[b],this))return!0}));for(b=0;c>b;b++)n.find(a,e[b],d);return d=this.pushStack(c>1?n.unique(d):d),d.selector=this.selector?this.selector+" "+a:a,d},filter:function(a){return this.pushStack(x(this,a||[],!1))},not:function(a){return this.pushStack(x(this,a||[],!0))},is:function(a){return!!x(this,"string"==typeof a&&u.test(a)?n(a):a||[],!1).length}});var y,z=/^(?:\\s*(<[\\w\\W]+>)[^>]*|#([\\w-]*))$/,A=n.fn.init=function(a,b){var c,d;if(!a)return this;if("string"==typeof a){if(c="<"===a[0]&&">"===a[a.length-1]&&a.length>=3?[null,a,null]:z.exec(a),!c||!c[1]&&b)return!b||b.jquery?(b||y).find(a):this.constructor(b).find(a);if(c[1]){if(b=b instanceof n?b[0]:b,n.merge(this,n.parseHTML(c[1],b&&b.nodeType?b.ownerDocument||b:l,!0)),v.test(c[1])&&n.isPlainObject(b))for(c in b)n.isFunction(this[c])?this[c](b[c]):this.attr(c,b[c]);return this}return d=l.getElementById(c[2]),d&&d.parentNode&&(this.length=1,this[0]=d),this.context=l,this.selector=a,this}return a.nodeType?(this.context=this[0]=a,this.length=1,this):n.isFunction(a)?"undefined"!=typeof y.ready?y.ready(a):a(n):(void 0!==a.selector&&(this.selector=a.selector,this.context=a.context),n.makeArray(a,this))};A.prototype=n.fn,y=n(l);var B=/^(?:parents|prev(?:Until|All))/,C={children:!0,contents:!0,next:!0,prev:!0};n.extend({dir:function(a,b,c){var d=[],e=void 0!==c;while((a=a[b])&&9!==a.nodeType)if(1===a.nodeType){if(e&&n(a).is(c))break;d.push(a)}return d},sibling:function(a,b){for(var c=[];a;a=a.nextSibling)1===a.nodeType&&a!==b&&c.push(a);return c}}),n.fn.extend({has:function(a){var b=n(a,this),c=b.length;return this.filter(function(){for(var a=0;c>a;a++)if(n.contains(this,b[a]))return!0})},closest:function(a,b){for(var c,d=0,e=this.length,f=[],g=u.test(a)||"string"!=typeof a?n(a,b||this.context):0;e>d;d++)for(c=this[d];c&&c!==b;c=c.parentNode)if(c.nodeType<11&&(g?g.index(c)>-1:1===c.nodeType&&n.find.matchesSelector(c,a))){f.push(c);break}return this.pushStack(f.length>1?n.unique(f):f)},index:function(a){return a?"string"==typeof a?g.call(n(a),this[0]):g.call(this,a.jquery?a[0]:a):this[0]&&this[0].parentNode?this.first().prevAll().length:-1},add:function(a,b){return this.pushStack(n.unique(n.merge(this.get(),n(a,b))))},addBack:function(a){return this.add(null==a?this.prevObject:this.prevObject.filter(a))}});function D(a,b){while((a=a[b])&&1!==a.nodeType);return a}n.each({parent:function(a){var b=a.parentNode;return b&&11!==b.nodeType?b:null},parents:function(a){return n.dir(a,"parentNode")},parentsUntil:function(a,b,c){return n.dir(a,"parentNode",c)},next:function(a){return D(a,"nextSibling")},prev:function(a){return D(a,"previousSibling")},nextAll:function(a){return n.dir(a,"nextSibling")},prevAll:function(a){return n.dir(a,"previousSibling")},nextUntil:function(a,b,c){return n.dir(a,"nextSibling",c)},prevUntil:function(a,b,c){return n.dir(a,"previousSibling",c)},siblings:function(a){return n.sibling((a.parentNode||{}).firstChild,a)},children:function(a){return n.sibling(a.firstChild)},contents:function(a){return a.contentDocument||n.merge([],a.childNodes)}},function(a,b){n.fn[a]=function(c,d){var e=n.map(this,b,c);return"Until"!==a.slice(-5)&&(d=c),d&&"string"==typeof d&&(e=n.filter(d,e)),this.length>1&&(C[a]||n.unique(e),B.test(a)&&e.reverse()),this.pushStack(e)}});var E=/\\S+/g,F={};function G(a){var b=F[a]={};return n.each(a.match(E)||[],function(a,c){b[c]=!0}),b}n.Callbacks=function(a){a="string"==typeof a?F[a]||G(a):n.extend({},a);var b,c,d,e,f,g,h=[],i=!a.once&&[],j=function(l){for(b=a.memory&&l,c=!0,g=e||0,e=0,f=h.length,d=!0;h&&f>g;g++)if(h[g].apply(l[0],l[1])===!1&&a.stopOnFalse){b=!1;break}d=!1,h&&(i?i.length&&j(i.shift()):b?h=[]:k.disable())},k={add:function(){if(h){var c=h.length;!function g(b){n.each(b,function(b,c){var d=n.type(c);"function"===d?a.unique&&k.has(c)||h.push(c):c&&c.length&&"string"!==d&&g(c)})}(arguments),d?f=h.length:b&&(e=c,j(b))}return this},remove:function(){return h&&n.each(arguments,function(a,b){var c;while((c=n.inArray(b,h,c))>-1)h.splice(c,1),d&&(f>=c&&f--,g>=c&&g--)}),this},has:function(a){return a?n.inArray(a,h)>-1:!(!h||!h.length)},empty:function(){return h=[],f=0,this},disable:function(){return h=i=b=void 0,this},disabled:function(){return!h},lock:function(){return i=void 0,b||k.disable(),this},locked:function(){return!i},fireWith:function(a,b){return!h||c&&!i||(b=b||[],b=[a,b.slice?b.slice():b],d?i.push(b):j(b)),this},fire:function(){return k.fireWith(this,arguments),this},fired:function(){return!!c}};return k},n.extend({Deferred:function(a){var b=[["resolve","done",n.Callbacks("once memory"),"resolved"],["reject","fail",n.Callbacks("once memory"),"rejected"],["notify","progress",n.Callbacks("memory")]],c="pending",d={state:function(){return c},always:function(){return e.done(arguments).fail(arguments),this},then:function(){var a=arguments;return n.Deferred(function(c){n.each(b,function(b,f){var g=n.isFunction(a[b])&&a[b];e[f[1]](function(){var a=g&&g.apply(this,arguments);a&&n.isFunction(a.promise)?a.promise().done(c.resolve).fail(c.reject).progress(c.notify):c[f[0]+"With"](this===d?c.promise():this,g?[a]:arguments)})}),a=null}).promise()},promise:function(a){return null!=a?n.extend(a,d):d}},e={};return d.pipe=d.then,n.each(b,function(a,f){var g=f[2],h=f[3];d[f[1]]=g.add,h&&g.add(function(){c=h},b[1^a][2].disable,b[2][2].lock),e[f[0]]=function(){return e[f[0]+"With"](this===e?d:this,arguments),this},e[f[0]+"With"]=g.fireWith}),d.promise(e),a&&a.call(e,e),e},when:function(a){var b=0,c=d.call(arguments),e=c.length,f=1!==e||a&&n.isFunction(a.promise)?e:0,g=1===f?a:n.Deferred(),h=function(a,b,c){return function(e){b[a]=this,c[a]=arguments.length>1?d.call(arguments):e,c===i?g.notifyWith(b,c):--f||g.resolveWith(b,c)}},i,j,k;if(e>1)for(i=new Array(e),j=new Array(e),k=new Array(e);e>b;b++)c[b]&&n.isFunction(c[b].promise)?c[b].promise().done(h(b,k,c)).fail(g.reject).progress(h(b,j,i)):--f;return f||g.resolveWith(k,c),g.promise()}});var H;n.fn.ready=function(a){return n.ready.promise().done(a),this},n.extend({isReady:!1,readyWait:1,holdReady:function(a){a?n.readyWait++:n.ready(!0)},ready:function(a){(a===!0?--n.readyWait:n.isReady)||(n.isReady=!0,a!==!0&&--n.readyWait>0||(H.resolveWith(l,[n]),n.fn.triggerHandler&&(n(l).triggerHandler("ready"),n(l).off("ready"))))}});function I(){l.removeEventListener("DOMContentLoaded",I,!1),a.removeEventListener("load",I,!1),n.ready()}n.ready.promise=function(b){return H||(H=n.Deferred(),"complete"===l.readyState?setTimeout(n.ready):(l.addEventListener("DOMContentLoaded",I,!1),a.addEventListener("load",I,!1))),H.promise(b)},n.ready.promise();var J=n.access=function(a,b,c,d,e,f,g){var h=0,i=a.length,j=null==c;if("object"===n.type(c)){e=!0;for(h in c)n.access(a,b,h,c[h],!0,f,g)}else if(void 0!==d&&(e=!0,n.isFunction(d)||(g=!0),j&&(g?(b.call(a,d),b=null):(j=b,b=function(a,b,c){return j.call(n(a),c)})),b))for(;i>h;h++)b(a[h],c,g?d:d.call(a[h],h,b(a[h],c)));return e?a:j?b.call(a):i?b(a[0],c):f};n.acceptData=function(a){return 1===a.nodeType||9===a.nodeType||!+a.nodeType};function K(){Object.defineProperty(this.cache={},0,{get:function(){return{}}}),this.expando=n.expando+K.uid++}K.uid=1,K.accepts=n.acceptData,K.prototype={key:function(a){if(!K.accepts(a))return 0;var b={},c=a[this.expando];if(!c){c=K.uid++;try{b[this.expando]={value:c},Object.defineProperties(a,b)}catch(d){b[this.expando]=c,n.extend(a,b)}}return this.cache[c]||(this.cache[c]={}),c},set:function(a,b,c){var d,e=this.key(a),f=this.cache[e];if("string"==typeof b)f[b]=c;else if(n.isEmptyObject(f))n.extend(this.cache[e],b);else for(d in b)f[d]=b[d];return f},get:function(a,b){var c=this.cache[this.key(a)];return void 0===b?c:c[b]},access:function(a,b,c){var d;return void 0===b||b&&"string"==typeof b&&void 0===c?(d=this.get(a,b),void 0!==d?d:this.get(a,n.camelCase(b))):(this.set(a,b,c),void 0!==c?c:b)},remove:function(a,b){var c,d,e,f=this.key(a),g=this.cache[f];if(void 0===b)this.cache[f]={};else{n.isArray(b)?d=b.concat(b.map(n.camelCase)):(e=n.camelCase(b),b in g?d=[b,e]:(d=e,d=d in g?[d]:d.match(E)||[])),c=d.length;while(c--)delete g[d[c]]}},hasData:function(a){return!n.isEmptyObject(this.cache[a[this.expando]]||{})},discard:function(a){a[this.expando]&&delete this.cache[a[this.expando]]}};var L=new K,M=new K,N=/^(?:\\{[\\w\\W]*\\}|\\[[\\w\\W]*\\])$/,O=/([A-Z])/g;function P(a,b,c){var d;if(void 0===c&&1===a.nodeType)if(d="data-"+b.replace(O,"-$1").toLowerCase(),c=a.getAttribute(d),"string"==typeof c){try{c="true"===c?!0:"false"===c?!1:"null"===c?null:+c+""===c?+c:N.test(c)?n.parseJSON(c):c}catch(e){}M.set(a,b,c)}else c=void 0;return c}n.extend({hasData:function(a){return M.hasData(a)||L.hasData(a)},data:function(a,b,c){return M.access(a,b,c)
},removeData:function(a,b){M.remove(a,b)},_data:function(a,b,c){return L.access(a,b,c)},_removeData:function(a,b){L.remove(a,b)}}),n.fn.extend({data:function(a,b){var c,d,e,f=this[0],g=f&&f.attributes;if(void 0===a){if(this.length&&(e=M.get(f),1===f.nodeType&&!L.get(f,"hasDataAttrs"))){c=g.length;while(c--)g[c]&&(d=g[c].name,0===d.indexOf("data-")&&(d=n.camelCase(d.slice(5)),P(f,d,e[d])));L.set(f,"hasDataAttrs",!0)}return e}return"object"==typeof a?this.each(function(){M.set(this,a)}):J(this,function(b){var c,d=n.camelCase(a);if(f&&void 0===b){if(c=M.get(f,a),void 0!==c)return c;if(c=M.get(f,d),void 0!==c)return c;if(c=P(f,d,void 0),void 0!==c)return c}else this.each(function(){var c=M.get(this,d);M.set(this,d,b),-1!==a.indexOf("-")&&void 0!==c&&M.set(this,a,b)})},null,b,arguments.length>1,null,!0)},removeData:function(a){return this.each(function(){M.remove(this,a)})}}),n.extend({queue:function(a,b,c){var d;return a?(b=(b||"fx")+"queue",d=L.get(a,b),c&&(!d||n.isArray(c)?d=L.access(a,b,n.makeArray(c)):d.push(c)),d||[]):void 0},dequeue:function(a,b){b=b||"fx";var c=n.queue(a,b),d=c.length,e=c.shift(),f=n._queueHooks(a,b),g=function(){n.dequeue(a,b)};"inprogress"===e&&(e=c.shift(),d--),e&&("fx"===b&&c.unshift("inprogress"),delete f.stop,e.call(a,g,f)),!d&&f&&f.empty.fire()},_queueHooks:function(a,b){var c=b+"queueHooks";return L.get(a,c)||L.access(a,c,{empty:n.Callbacks("once memory").add(function(){L.remove(a,[b+"queue",c])})})}}),n.fn.extend({queue:function(a,b){var c=2;return"string"!=typeof a&&(b=a,a="fx",c--),arguments.length<c?n.queue(this[0],a):void 0===b?this:this.each(function(){var c=n.queue(this,a,b);n._queueHooks(this,a),"fx"===a&&"inprogress"!==c[0]&&n.dequeue(this,a)})},dequeue:function(a){return this.each(function(){n.dequeue(this,a)})},clearQueue:function(a){return this.queue(a||"fx",[])},promise:function(a,b){var c,d=1,e=n.Deferred(),f=this,g=this.length,h=function(){--d||e.resolveWith(f,[f])};"string"!=typeof a&&(b=a,a=void 0),a=a||"fx";while(g--)c=L.get(f[g],a+"queueHooks"),c&&c.empty&&(d++,c.empty.add(h));return h(),e.promise(b)}});var Q=/[+-]?(?:\\d*\\.|)\\d+(?:[eE][+-]?\\d+|)/.source,R=["Top","Right","Bottom","Left"],S=function(a,b){return a=b||a,"none"===n.css(a,"display")||!n.contains(a.ownerDocument,a)},T=/^(?:checkbox|radio)$/i;!function(){var a=l.createDocumentFragment(),b=a.appendChild(l.createElement("div")),c=l.createElement("input");c.setAttribute("type","radio"),c.setAttribute("checked","checked"),c.setAttribute("name","t"),b.appendChild(c),k.checkClone=b.cloneNode(!0).cloneNode(!0).lastChild.checked,b.innerHTML="<textarea>x</textarea>",k.noCloneChecked=!!b.cloneNode(!0).lastChild.defaultValue}();var U="undefined";k.focusinBubbles="onfocusin"in a;var V=/^key/,W=/^(?:mouse|pointer|contextmenu)|click/,X=/^(?:focusinfocus|focusoutblur)$/,Y=/^([^.]*)(?:\\.(.+)|)$/;function Z(){return!0}function $(){return!1}function _(){try{return l.activeElement}catch(a){}}n.event={global:{},add:function(a,b,c,d,e){var f,g,h,i,j,k,l,m,o,p,q,r=L.get(a);if(r){c.handler&&(f=c,c=f.handler,e=f.selector),c.guid||(c.guid=n.guid++),(i=r.events)||(i=r.events={}),(g=r.handle)||(g=r.handle=function(b){return typeof n!==U&&n.event.triggered!==b.type?n.event.dispatch.apply(a,arguments):void 0}),b=(b||"").match(E)||[""],j=b.length;while(j--)h=Y.exec(b[j])||[],o=q=h[1],p=(h[2]||"").split(".").sort(),o&&(l=n.event.special[o]||{},o=(e?l.delegateType:l.bindType)||o,l=n.event.special[o]||{},k=n.extend({type:o,origType:q,data:d,handler:c,guid:c.guid,selector:e,needsContext:e&&n.expr.match.needsContext.test(e),namespace:p.join(".")},f),(m=i[o])||(m=i[o]=[],m.delegateCount=0,l.setup&&l.setup.call(a,d,p,g)!==!1||a.addEventListener&&a.addEventListener(o,g,!1)),l.add&&(l.add.call(a,k),k.handler.guid||(k.handler.guid=c.guid)),e?m.splice(m.delegateCount++,0,k):m.push(k),n.event.global[o]=!0)}},remove:function(a,b,c,d,e){var f,g,h,i,j,k,l,m,o,p,q,r=L.hasData(a)&&L.get(a);if(r&&(i=r.events)){b=(b||"").match(E)||[""],j=b.length;while(j--)if(h=Y.exec(b[j])||[],o=q=h[1],p=(h[2]||"").split(".").sort(),o){l=n.event.special[o]||{},o=(d?l.delegateType:l.bindType)||o,m=i[o]||[],h=h[2]&&new RegExp("(^|\\\\.)"+p.join("\\\\.(?:.*\\\\.|)")+"(\\\\.|$)"),g=f=m.length;while(f--)k=m[f],!e&&q!==k.origType||c&&c.guid!==k.guid||h&&!h.test(k.namespace)||d&&d!==k.selector&&("**"!==d||!k.selector)||(m.splice(f,1),k.selector&&m.delegateCount--,l.remove&&l.remove.call(a,k));g&&!m.length&&(l.teardown&&l.teardown.call(a,p,r.handle)!==!1||n.removeEvent(a,o,r.handle),delete i[o])}else for(o in i)n.event.remove(a,o+b[j],c,d,!0);n.isEmptyObject(i)&&(delete r.handle,L.remove(a,"events"))}},trigger:function(b,c,d,e){var f,g,h,i,k,m,o,p=[d||l],q=j.call(b,"type")?b.type:b,r=j.call(b,"namespace")?b.namespace.split("."):[];if(g=h=d=d||l,3!==d.nodeType&&8!==d.nodeType&&!X.test(q+n.event.triggered)&&(q.indexOf(".")>=0&&(r=q.split("."),q=r.shift(),r.sort()),k=q.indexOf(":")<0&&"on"+q,b=b[n.expando]?b:new n.Event(q,"object"==typeof b&&b),b.isTrigger=e?2:3,b.namespace=r.join("."),b.namespace_re=b.namespace?new RegExp("(^|\\\\.)"+r.join("\\\\.(?:.*\\\\.|)")+"(\\\\.|$)"):null,b.result=void 0,b.target||(b.target=d),c=null==c?[b]:n.makeArray(c,[b]),o=n.event.special[q]||{},e||!o.trigger||o.trigger.apply(d,c)!==!1)){if(!e&&!o.noBubble&&!n.isWindow(d)){for(i=o.delegateType||q,X.test(i+q)||(g=g.parentNode);g;g=g.parentNode)p.push(g),h=g;h===(d.ownerDocument||l)&&p.push(h.defaultView||h.parentWindow||a)}f=0;while((g=p[f++])&&!b.isPropagationStopped())b.type=f>1?i:o.bindType||q,m=(L.get(g,"events")||{})[b.type]&&L.get(g,"handle"),m&&m.apply(g,c),m=k&&g[k],m&&m.apply&&n.acceptData(g)&&(b.result=m.apply(g,c),b.result===!1&&b.preventDefault());return b.type=q,e||b.isDefaultPrevented()||o._default&&o._default.apply(p.pop(),c)!==!1||!n.acceptData(d)||k&&n.isFunction(d[q])&&!n.isWindow(d)&&(h=d[k],h&&(d[k]=null),n.event.triggered=q,d[q](),n.event.triggered=void 0,h&&(d[k]=h)),b.result}},dispatch:function(a){a=n.event.fix(a);var b,c,e,f,g,h=[],i=d.call(arguments),j=(L.get(this,"events")||{})[a.type]||[],k=n.event.special[a.type]||{};if(i[0]=a,a.delegateTarget=this,!k.preDispatch||k.preDispatch.call(this,a)!==!1){h=n.event.handlers.call(this,a,j),b=0;while((f=h[b++])&&!a.isPropagationStopped()){a.currentTarget=f.elem,c=0;while((g=f.handlers[c++])&&!a.isImmediatePropagationStopped())(!a.namespace_re||a.namespace_re.test(g.namespace))&&(a.handleObj=g,a.data=g.data,e=((n.event.special[g.origType]||{}).handle||g.handler).apply(f.elem,i),void 0!==e&&(a.result=e)===!1&&(a.preventDefault(),a.stopPropagation()))}return k.postDispatch&&k.postDispatch.call(this,a),a.result}},handlers:function(a,b){var c,d,e,f,g=[],h=b.delegateCount,i=a.target;if(h&&i.nodeType&&(!a.button||"click"!==a.type))for(;i!==this;i=i.parentNode||this)if(i.disabled!==!0||"click"!==a.type){for(d=[],c=0;h>c;c++)f=b[c],e=f.selector+" ",void 0===d[e]&&(d[e]=f.needsContext?n(e,this).index(i)>=0:n.find(e,this,null,[i]).length),d[e]&&d.push(f);d.length&&g.push({elem:i,handlers:d})}return h<b.length&&g.push({elem:this,handlers:b.slice(h)}),g},props:"altKey bubbles cancelable ctrlKey currentTarget eventPhase metaKey relatedTarget shiftKey target timeStamp view which".split(" "),fixHooks:{},keyHooks:{props:"char charCode key keyCode".split(" "),filter:function(a,b){return null==a.which&&(a.which=null!=b.charCode?b.charCode:b.keyCode),a}},mouseHooks:{props:"button buttons clientX clientY offsetX offsetY pageX pageY screenX screenY toElement".split(" "),filter:function(a,b){var c,d,e,f=b.button;return null==a.pageX&&null!=b.clientX&&(c=a.target.ownerDocument||l,d=c.documentElement,e=c.body,a.pageX=b.clientX+(d&&d.scrollLeft||e&&e.scrollLeft||0)-(d&&d.clientLeft||e&&e.clientLeft||0),a.pageY=b.clientY+(d&&d.scrollTop||e&&e.scrollTop||0)-(d&&d.clientTop||e&&e.clientTop||0)),a.which||void 0===f||(a.which=1&f?1:2&f?3:4&f?2:0),a}},fix:function(a){if(a[n.expando])return a;var b,c,d,e=a.type,f=a,g=this.fixHooks[e];g||(this.fixHooks[e]=g=W.test(e)?this.mouseHooks:V.test(e)?this.keyHooks:{}),d=g.props?this.props.concat(g.props):this.props,a=new n.Event(f),b=d.length;while(b--)c=d[b],a[c]=f[c];return a.target||(a.target=l),3===a.target.nodeType&&(a.target=a.target.parentNode),g.filter?g.filter(a,f):a},special:{load:{noBubble:!0},focus:{trigger:function(){return this!==_()&&this.focus?(this.focus(),!1):void 0},delegateType:"focusin"},blur:{trigger:function(){return this===_()&&this.blur?(this.blur(),!1):void 0},delegateType:"focusout"},click:{trigger:function(){return"checkbox"===this.type&&this.click&&n.nodeName(this,"input")?(this.click(),!1):void 0},_default:function(a){return n.nodeName(a.target,"a")}},beforeunload:{postDispatch:function(a){void 0!==a.result&&a.originalEvent&&(a.originalEvent.returnValue=a.result)}}},simulate:function(a,b,c,d){var e=n.extend(new n.Event,c,{type:a,isSimulated:!0,originalEvent:{}});d?n.event.trigger(e,null,b):n.event.dispatch.call(b,e),e.isDefaultPrevented()&&c.preventDefault()}},n.removeEvent=function(a,b,c){a.removeEventListener&&a.removeEventListener(b,c,!1)},n.Event=function(a,b){return this instanceof n.Event?(a&&a.type?(this.originalEvent=a,this.type=a.type,this.isDefaultPrevented=a.defaultPrevented||void 0===a.defaultPrevented&&a.returnValue===!1?Z:$):this.type=a,b&&n.extend(this,b),this.timeStamp=a&&a.timeStamp||n.now(),void(this[n.expando]=!0)):new n.Event(a,b)},n.Event.prototype={isDefaultPrevented:$,isPropagationStopped:$,isImmediatePropagationStopped:$,preventDefault:function(){var a=this.originalEvent;this.isDefaultPrevented=Z,a&&a.preventDefault&&a.preventDefault()},stopPropagation:function(){var a=this.originalEvent;this.isPropagationStopped=Z,a&&a.stopPropagation&&a.stopPropagation()},stopImmediatePropagation:function(){var a=this.originalEvent;this.isImmediatePropagationStopped=Z,a&&a.stopImmediatePropagation&&a.stopImmediatePropagation(),this.stopPropagation()}},n.each({mouseenter:"mouseover",mouseleave:"mouseout",pointerenter:"pointerover",pointerleave:"pointerout"},function(a,b){n.event.special[a]={delegateType:b,bindType:b,handle:function(a){var c,d=this,e=a.relatedTarget,f=a.handleObj;return(!e||e!==d&&!n.contains(d,e))&&(a.type=f.origType,c=f.handler.apply(this,arguments),a.type=b),c}}}),k.focusinBubbles||n.each({focus:"focusin",blur:"focusout"},function(a,b){var c=function(a){n.event.simulate(b,a.target,n.event.fix(a),!0)};n.event.special[b]={setup:function(){var d=this.ownerDocument||this,e=L.access(d,b);e||d.addEventListener(a,c,!0),L.access(d,b,(e||0)+1)},teardown:function(){var d=this.ownerDocument||this,e=L.access(d,b)-1;e?L.access(d,b,e):(d.removeEventListener(a,c,!0),L.remove(d,b))}}}),n.fn.extend({on:function(a,b,c,d,e){var f,g;if("object"==typeof a){"string"!=typeof b&&(c=c||b,b=void 0);for(g in a)this.on(g,b,c,a[g],e);return this}if(null==c&&null==d?(d=b,c=b=void 0):null==d&&("string"==typeof b?(d=c,c=void 0):(d=c,c=b,b=void 0)),d===!1)d=$;else if(!d)return this;return 1===e&&(f=d,d=function(a){return n().off(a),f.apply(this,arguments)},d.guid=f.guid||(f.guid=n.guid++)),this.each(function(){n.event.add(this,a,d,c,b)})},one:function(a,b,c,d){return this.on(a,b,c,d,1)},off:function(a,b,c){var d,e;if(a&&a.preventDefault&&a.handleObj)return d=a.handleObj,n(a.delegateTarget).off(d.namespace?d.origType+"."+d.namespace:d.origType,d.selector,d.handler),this;if("object"==typeof a){for(e in a)this.off(e,b,a[e]);return this}return(b===!1||"function"==typeof b)&&(c=b,b=void 0),c===!1&&(c=$),this.each(function(){n.event.remove(this,a,c,b)})},trigger:function(a,b){return this.each(function(){n.event.trigger(a,b,this)})},triggerHandler:function(a,b){var c=this[0];return c?n.event.trigger(a,b,c,!0):void 0}});var ab=/<(?!area|br|col|embed|hr|img|input|link|meta|param)(([\\w:]+)[^>]*)\\/>/gi,bb=/<([\\w:]+)/,cb=/<|&#?\\w+;/,db=/<(?:script|style|link)/i,eb=/checked\\s*(?:[^=]|=\\s*.checked.)/i,fb=/^$|\\/(?:java|ecma)script/i,gb=/^true\\/(.*)/,hb=/^\\s*<!(?:\\[CDATA\\[|--)|(?:\\]\\]|--)>\\s*$/g,ib={option:[1,"<select multiple=\'multiple\'>","</select>"],thead:[1,"<table>","</table>"],col:[2,"<table><colgroup>","</colgroup></table>"],tr:[2,"<table><tbody>","</tbody></table>"],td:[3,"<table><tbody><tr>","</tr></tbody></table>"],_default:[0,"",""]};ib.optgroup=ib.option,ib.tbody=ib.tfoot=ib.colgroup=ib.caption=ib.thead,ib.th=ib.td;function jb(a,b){return n.nodeName(a,"table")&&n.nodeName(11!==b.nodeType?b:b.firstChild,"tr")?a.getElementsByTagName("tbody")[0]||a.appendChild(a.ownerDocument.createElement("tbody")):a}function kb(a){return a.type=(null!==a.getAttribute("type"))+"/"+a.type,a}function lb(a){var b=gb.exec(a.type);return b?a.type=b[1]:a.removeAttribute("type"),a}function mb(a,b){for(var c=0,d=a.length;d>c;c++)L.set(a[c],"globalEval",!b||L.get(b[c],"globalEval"))}function nb(a,b){var c,d,e,f,g,h,i,j;if(1===b.nodeType){if(L.hasData(a)&&(f=L.access(a),g=L.set(b,f),j=f.events)){delete g.handle,g.events={};for(e in j)for(c=0,d=j[e].length;d>c;c++)n.event.add(b,e,j[e][c])}M.hasData(a)&&(h=M.access(a),i=n.extend({},h),M.set(b,i))}}function ob(a,b){var c=a.getElementsByTagName?a.getElementsByTagName(b||"*"):a.querySelectorAll?a.querySelectorAll(b||"*"):[];return void 0===b||b&&n.nodeName(a,b)?n.merge([a],c):c}function pb(a,b){var c=b.nodeName.toLowerCase();"input"===c&&T.test(a.type)?b.checked=a.checked:("input"===c||"textarea"===c)&&(b.defaultValue=a.defaultValue)}n.extend({clone:function(a,b,c){var d,e,f,g,h=a.cloneNode(!0),i=n.contains(a.ownerDocument,a);if(!(k.noCloneChecked||1!==a.nodeType&&11!==a.nodeType||n.isXMLDoc(a)))for(g=ob(h),f=ob(a),d=0,e=f.length;e>d;d++)pb(f[d],g[d]);if(b)if(c)for(f=f||ob(a),g=g||ob(h),d=0,e=f.length;e>d;d++)nb(f[d],g[d]);else nb(a,h);return g=ob(h,"script"),g.length>0&&mb(g,!i&&ob(a,"script")),h},buildFragment:function(a,b,c,d){for(var e,f,g,h,i,j,k=b.createDocumentFragment(),l=[],m=0,o=a.length;o>m;m++)if(e=a[m],e||0===e)if("object"===n.type(e))n.merge(l,e.nodeType?[e]:e);else if(cb.test(e)){f=f||k.appendChild(b.createElement("div")),g=(bb.exec(e)||["",""])[1].toLowerCase(),h=ib[g]||ib._default,f.innerHTML=h[1]+e.replace(ab,"<$1></$2>")+h[2],j=h[0];while(j--)f=f.lastChild;n.merge(l,f.childNodes),f=k.firstChild,f.textContent=""}else l.push(b.createTextNode(e));k.textContent="",m=0;while(e=l[m++])if((!d||-1===n.inArray(e,d))&&(i=n.contains(e.ownerDocument,e),f=ob(k.appendChild(e),"script"),i&&mb(f),c)){j=0;while(e=f[j++])fb.test(e.type||"")&&c.push(e)}return k},cleanData:function(a){for(var b,c,d,e,f=n.event.special,g=0;void 0!==(c=a[g]);g++){if(n.acceptData(c)&&(e=c[L.expando],e&&(b=L.cache[e]))){if(b.events)for(d in b.events)f[d]?n.event.remove(c,d):n.removeEvent(c,d,b.handle);L.cache[e]&&delete L.cache[e]}delete M.cache[c[M.expando]]}}}),n.fn.extend({text:function(a){return J(this,function(a){return void 0===a?n.text(this):this.empty().each(function(){(1===this.nodeType||11===this.nodeType||9===this.nodeType)&&(this.textContent=a)})},null,a,arguments.length)},append:function(){return this.domManip(arguments,function(a){if(1===this.nodeType||11===this.nodeType||9===this.nodeType){var b=jb(this,a);b.appendChild(a)}})},prepend:function(){return this.domManip(arguments,function(a){if(1===this.nodeType||11===this.nodeType||9===this.nodeType){var b=jb(this,a);b.insertBefore(a,b.firstChild)}})},before:function(){return this.domManip(arguments,function(a){this.parentNode&&this.parentNode.insertBefore(a,this)})},after:function(){return this.domManip(arguments,function(a){this.parentNode&&this.parentNode.insertBefore(a,this.nextSibling)})},remove:function(a,b){for(var c,d=a?n.filter(a,this):this,e=0;null!=(c=d[e]);e++)b||1!==c.nodeType||n.cleanData(ob(c)),c.parentNode&&(b&&n.contains(c.ownerDocument,c)&&mb(ob(c,"script")),c.parentNode.removeChild(c));return this},empty:function(){for(var a,b=0;null!=(a=this[b]);b++)1===a.nodeType&&(n.cleanData(ob(a,!1)),a.textContent="");return this},clone:function(a,b){return a=null==a?!1:a,b=null==b?a:b,this.map(function(){return n.clone(this,a,b)})},html:function(a){return J(this,function(a){var b=this[0]||{},c=0,d=this.length;if(void 0===a&&1===b.nodeType)return b.innerHTML;if("string"==typeof a&&!db.test(a)&&!ib[(bb.exec(a)||["",""])[1].toLowerCase()]){a=a.replace(ab,"<$1></$2>");try{for(;d>c;c++)b=this[c]||{},1===b.nodeType&&(n.cleanData(ob(b,!1)),b.innerHTML=a);b=0}catch(e){}}b&&this.empty().append(a)},null,a,arguments.length)},replaceWith:function(){var a=arguments[0];return this.domManip(arguments,function(b){a=this.parentNode,n.cleanData(ob(this)),a&&a.replaceChild(b,this)}),a&&(a.length||a.nodeType)?this:this.remove()},detach:function(a){return this.remove(a,!0)},domManip:function(a,b){a=e.apply([],a);var c,d,f,g,h,i,j=0,l=this.length,m=this,o=l-1,p=a[0],q=n.isFunction(p);if(q||l>1&&"string"==typeof p&&!k.checkClone&&eb.test(p))return this.each(function(c){var d=m.eq(c);q&&(a[0]=p.call(this,c,d.html())),d.domManip(a,b)});if(l&&(c=n.buildFragment(a,this[0].ownerDocument,!1,this),d=c.firstChild,1===c.childNodes.length&&(c=d),d)){for(f=n.map(ob(c,"script"),kb),g=f.length;l>j;j++)h=c,j!==o&&(h=n.clone(h,!0,!0),g&&n.merge(f,ob(h,"script"))),b.call(this[j],h,j);if(g)for(i=f[f.length-1].ownerDocument,n.map(f,lb),j=0;g>j;j++)h=f[j],fb.test(h.type||"")&&!L.access(h,"globalEval")&&n.contains(i,h)&&(h.src?n._evalUrl&&n._evalUrl(h.src):n.globalEval(h.textContent.replace(hb,"")))}return this}}),n.each({appendTo:"append",prependTo:"prepend",insertBefore:"before",insertAfter:"after",replaceAll:"replaceWith"},function(a,b){n.fn[a]=function(a){for(var c,d=[],e=n(a),g=e.length-1,h=0;g>=h;h++)c=h===g?this:this.clone(!0),n(e[h])[b](c),f.apply(d,c.get());return this.pushStack(d)}});var qb,rb={};function sb(b,c){var d,e=n(c.createElement(b)).appendTo(c.body),f=a.getDefaultComputedStyle&&(d=a.getDefaultComputedStyle(e[0]))?d.display:n.css(e[0],"display");return e.detach(),f}function tb(a){var b=l,c=rb[a];return c||(c=sb(a,b),"none"!==c&&c||(qb=(qb||n("<iframe frameborder=\'0\' width=\'0\' height=\'0\'/>")).appendTo(b.documentElement),b=qb[0].contentDocument,b.write(),b.close(),c=sb(a,b),qb.detach()),rb[a]=c),c}var ub=/^margin/,vb=new RegExp("^("+Q+")(?!px)[a-z%]+$","i"),wb=function(b){return b.ownerDocument.defaultView.opener?b.ownerDocument.defaultView.getComputedStyle(b,null):a.getComputedStyle(b,null)};function xb(a,b,c){var d,e,f,g,h=a.style;return c=c||wb(a),c&&(g=c.getPropertyValue(b)||c[b]),c&&(""!==g||n.contains(a.ownerDocument,a)||(g=n.style(a,b)),vb.test(g)&&ub.test(b)&&(d=h.width,e=h.minWidth,f=h.maxWidth,h.minWidth=h.maxWidth=h.width=g,g=c.width,h.width=d,h.minWidth=e,h.maxWidth=f)),void 0!==g?g+"":g}function yb(a,b){return{get:function(){return a()?void delete this.get:(this.get=b).apply(this,arguments)}}}!function(){var b,c,d=l.documentElement,e=l.createElement("div"),f=l.createElement("div");if(f.style){f.style.backgroundClip="content-box",f.cloneNode(!0).style.backgroundClip="",k.clearCloneStyle="content-box"===f.style.backgroundClip,e.style.cssText="border:0;width:0;height:0;top:0;left:-9999px;margin-top:1px;position:absolute",e.appendChild(f);function g(){f.style.cssText="-webkit-box-sizing:border-box;-moz-box-sizing:border-box;box-sizing:border-box;display:block;margin-top:1%;top:1%;border:1px;padding:1px;width:4px;position:absolute",f.innerHTML="",d.appendChild(e);var g=a.getComputedStyle(f,null);b="1%"!==g.top,c="4px"===g.width,d.removeChild(e)}a.getComputedStyle&&n.extend(k,{pixelPosition:function(){return g(),b},boxSizingReliable:function(){return null==c&&g(),c},reliableMarginRight:function(){var b,c=f.appendChild(l.createElement("div"));return c.style.cssText=f.style.cssText="-webkit-box-sizing:content-box;-moz-box-sizing:content-box;box-sizing:content-box;display:block;margin:0;border:0;padding:0",c.style.marginRight=c.style.width="0",f.style.width="1px",d.appendChild(e),b=!parseFloat(a.getComputedStyle(c,null).marginRight),d.removeChild(e),f.removeChild(c),b}})}}(),n.swap=function(a,b,c,d){var e,f,g={};for(f in b)g[f]=a.style[f],a.style[f]=b[f];e=c.apply(a,d||[]);for(f in b)a.style[f]=g[f];return e};var zb=/^(none|table(?!-c[ea]).+)/,Ab=new RegExp("^("+Q+")(.*)$","i"),Bb=new RegExp("^([+-])=("+Q+")","i"),Cb={position:"absolute",visibility:"hidden",display:"block"},Db={letterSpacing:"0",fontWeight:"400"},Eb=["Webkit","O","Moz","ms"];function Fb(a,b){if(b in a)return b;var c=b[0].toUpperCase()+b.slice(1),d=b,e=Eb.length;while(e--)if(b=Eb[e]+c,b in a)return b;return d}function Gb(a,b,c){var d=Ab.exec(b);return d?Math.max(0,d[1]-(c||0))+(d[2]||"px"):b}function Hb(a,b,c,d,e){for(var f=c===(d?"border":"content")?4:"width"===b?1:0,g=0;4>f;f+=2)"margin"===c&&(g+=n.css(a,c+R[f],!0,e)),d?("content"===c&&(g-=n.css(a,"padding"+R[f],!0,e)),"margin"!==c&&(g-=n.css(a,"border"+R[f]+"Width",!0,e))):(g+=n.css(a,"padding"+R[f],!0,e),"padding"!==c&&(g+=n.css(a,"border"+R[f]+"Width",!0,e)));return g}function Ib(a,b,c){var d=!0,e="width"===b?a.offsetWidth:a.offsetHeight,f=wb(a),g="border-box"===n.css(a,"boxSizing",!1,f);if(0>=e||null==e){if(e=xb(a,b,f),(0>e||null==e)&&(e=a.style[b]),vb.test(e))return e;d=g&&(k.boxSizingReliable()||e===a.style[b]),e=parseFloat(e)||0}return e+Hb(a,b,c||(g?"border":"content"),d,f)+"px"}function Jb(a,b){for(var c,d,e,f=[],g=0,h=a.length;h>g;g++)d=a[g],d.style&&(f[g]=L.get(d,"olddisplay"),c=d.style.display,b?(f[g]||"none"!==c||(d.style.display=""),""===d.style.display&&S(d)&&(f[g]=L.access(d,"olddisplay",tb(d.nodeName)))):(e=S(d),"none"===c&&e||L.set(d,"olddisplay",e?c:n.css(d,"display"))));for(g=0;h>g;g++)d=a[g],d.style&&(b&&"none"!==d.style.display&&""!==d.style.display||(d.style.display=b?f[g]||"":"none"));return a}n.extend({cssHooks:{opacity:{get:function(a,b){if(b){var c=xb(a,"opacity");return""===c?"1":c}}}},cssNumber:{columnCount:!0,fillOpacity:!0,flexGrow:!0,flexShrink:!0,fontWeight:!0,lineHeight:!0,opacity:!0,order:!0,orphans:!0,widows:!0,zIndex:!0,zoom:!0},cssProps:{"float":"cssFloat"},style:function(a,b,c,d){if(a&&3!==a.nodeType&&8!==a.nodeType&&a.style){var e,f,g,h=n.camelCase(b),i=a.style;return b=n.cssProps[h]||(n.cssProps[h]=Fb(i,h)),g=n.cssHooks[b]||n.cssHooks[h],void 0===c?g&&"get"in g&&void 0!==(e=g.get(a,!1,d))?e:i[b]:(f=typeof c,"string"===f&&(e=Bb.exec(c))&&(c=(e[1]+1)*e[2]+parseFloat(n.css(a,b)),f="number"),null!=c&&c===c&&("number"!==f||n.cssNumber[h]||(c+="px"),k.clearCloneStyle||""!==c||0!==b.indexOf("background")||(i[b]="inherit"),g&&"set"in g&&void 0===(c=g.set(a,c,d))||(i[b]=c)),void 0)}},css:function(a,b,c,d){var e,f,g,h=n.camelCase(b);return b=n.cssProps[h]||(n.cssProps[h]=Fb(a.style,h)),g=n.cssHooks[b]||n.cssHooks[h],g&&"get"in g&&(e=g.get(a,!0,c)),void 0===e&&(e=xb(a,b,d)),"normal"===e&&b in Db&&(e=Db[b]),""===c||c?(f=parseFloat(e),c===!0||n.isNumeric(f)?f||0:e):e}}),n.each(["height","width"],function(a,b){n.cssHooks[b]={get:function(a,c,d){return c?zb.test(n.css(a,"display"))&&0===a.offsetWidth?n.swap(a,Cb,function(){return Ib(a,b,d)}):Ib(a,b,d):void 0},set:function(a,c,d){var e=d&&wb(a);return Gb(a,c,d?Hb(a,b,d,"border-box"===n.css(a,"boxSizing",!1,e),e):0)}}}),n.cssHooks.marginRight=yb(k.reliableMarginRight,function(a,b){return b?n.swap(a,{display:"inline-block"},xb,[a,"marginRight"]):void 0}),n.each({margin:"",padding:"",border:"Width"},function(a,b){n.cssHooks[a+b]={expand:function(c){for(var d=0,e={},f="string"==typeof c?c.split(" "):[c];4>d;d++)e[a+R[d]+b]=f[d]||f[d-2]||f[0];return e}},ub.test(a)||(n.cssHooks[a+b].set=Gb)}),n.fn.extend({css:function(a,b){return J(this,function(a,b,c){var d,e,f={},g=0;if(n.isArray(b)){for(d=wb(a),e=b.length;e>g;g++)f[b[g]]=n.css(a,b[g],!1,d);return f}return void 0!==c?n.style(a,b,c):n.css(a,b)},a,b,arguments.length>1)},show:function(){return Jb(this,!0)},hide:function(){return Jb(this)},toggle:function(a){return"boolean"==typeof a?a?this.show():this.hide():this.each(function(){S(this)?n(this).show():n(this).hide()})}});function Kb(a,b,c,d,e){return new Kb.prototype.init(a,b,c,d,e)}n.Tween=Kb,Kb.prototype={constructor:Kb,init:function(a,b,c,d,e,f){this.elem=a,this.prop=c,this.easing=e||"swing",this.options=b,this.start=this.now=this.cur(),this.end=d,this.unit=f||(n.cssNumber[c]?"":"px")},cur:function(){var a=Kb.propHooks[this.prop];return a&&a.get?a.get(this):Kb.propHooks._default.get(this)},run:function(a){var b,c=Kb.propHooks[this.prop];return this.pos=b=this.options.duration?n.easing[this.easing](a,this.options.duration*a,0,1,this.options.duration):a,this.now=(this.end-this.start)*b+this.start,this.options.step&&this.options.step.call(this.elem,this.now,this),c&&c.set?c.set(this):Kb.propHooks._default.set(this),this}},Kb.prototype.init.prototype=Kb.prototype,Kb.propHooks={_default:{get:function(a){var b;return null==a.elem[a.prop]||a.elem.style&&null!=a.elem.style[a.prop]?(b=n.css(a.elem,a.prop,""),b&&"auto"!==b?b:0):a.elem[a.prop]},set:function(a){n.fx.step[a.prop]?n.fx.step[a.prop](a):a.elem.style&&(null!=a.elem.style[n.cssProps[a.prop]]||n.cssHooks[a.prop])?n.style(a.elem,a.prop,a.now+a.unit):a.elem[a.prop]=a.now}}},Kb.propHooks.scrollTop=Kb.propHooks.scrollLeft={set:function(a){a.elem.nodeType&&a.elem.parentNode&&(a.elem[a.prop]=a.now)}},n.easing={linear:function(a){return a},swing:function(a){return.5-Math.cos(a*Math.PI)/2}},n.fx=Kb.prototype.init,n.fx.step={};var Lb,Mb,Nb=/^(?:toggle|show|hide)$/,Ob=new RegExp("^(?:([+-])=|)("+Q+")([a-z%]*)$","i"),Pb=/queueHooks$/,Qb=[Vb],Rb={"*":[function(a,b){var c=this.createTween(a,b),d=c.cur(),e=Ob.exec(b),f=e&&e[3]||(n.cssNumber[a]?"":"px"),g=(n.cssNumber[a]||"px"!==f&&+d)&&Ob.exec(n.css(c.elem,a)),h=1,i=20;if(g&&g[3]!==f){f=f||g[3],e=e||[],g=+d||1;do h=h||".5",g/=h,n.style(c.elem,a,g+f);while(h!==(h=c.cur()/d)&&1!==h&&--i)}return e&&(g=c.start=+g||+d||0,c.unit=f,c.end=e[1]?g+(e[1]+1)*e[2]:+e[2]),c}]};function Sb(){return setTimeout(function(){Lb=void 0}),Lb=n.now()}function Tb(a,b){var c,d=0,e={height:a};for(b=b?1:0;4>d;d+=2-b)c=R[d],e["margin"+c]=e["padding"+c]=a;return b&&(e.opacity=e.width=a),e}function Ub(a,b,c){for(var d,e=(Rb[b]||[]).concat(Rb["*"]),f=0,g=e.length;g>f;f++)if(d=e[f].call(c,b,a))return d}function Vb(a,b,c){var d,e,f,g,h,i,j,k,l=this,m={},o=a.style,p=a.nodeType&&S(a),q=L.get(a,"fxshow");c.queue||(h=n._queueHooks(a,"fx"),null==h.unqueued&&(h.unqueued=0,i=h.empty.fire,h.empty.fire=function(){h.unqueued||i()}),h.unqueued++,l.always(function(){l.always(function(){h.unqueued--,n.queue(a,"fx").length||h.empty.fire()})})),1===a.nodeType&&("height"in b||"width"in b)&&(c.overflow=[o.overflow,o.overflowX,o.overflowY],j=n.css(a,"display"),k="none"===j?L.get(a,"olddisplay")||tb(a.nodeName):j,"inline"===k&&"none"===n.css(a,"float")&&(o.display="inline-block")),c.overflow&&(o.overflow="hidden",l.always(function(){o.overflow=c.overflow[0],o.overflowX=c.overflow[1],o.overflowY=c.overflow[2]}));for(d in b)if(e=b[d],Nb.exec(e)){if(delete b[d],f=f||"toggle"===e,e===(p?"hide":"show")){if("show"!==e||!q||void 0===q[d])continue;p=!0}m[d]=q&&q[d]||n.style(a,d)}else j=void 0;if(n.isEmptyObject(m))"inline"===("none"===j?tb(a.nodeName):j)&&(o.display=j);else{q?"hidden"in q&&(p=q.hidden):q=L.access(a,"fxshow",{}),f&&(q.hidden=!p),p?n(a).show():l.done(function(){n(a).hide()}),l.done(function(){var b;L.remove(a,"fxshow");for(b in m)n.style(a,b,m[b])});for(d in m)g=Ub(p?q[d]:0,d,l),d in q||(q[d]=g.start,p&&(g.end=g.start,g.start="width"===d||"height"===d?1:0))}}function Wb(a,b){var c,d,e,f,g;for(c in a)if(d=n.camelCase(c),e=b[d],f=a[c],n.isArray(f)&&(e=f[1],f=a[c]=f[0]),c!==d&&(a[d]=f,delete a[c]),g=n.cssHooks[d],g&&"expand"in g){f=g.expand(f),delete a[d];for(c in f)c in a||(a[c]=f[c],b[c]=e)}else b[d]=e}function Xb(a,b,c){var d,e,f=0,g=Qb.length,h=n.Deferred().always(function(){delete i.elem}),i=function(){if(e)return!1;for(var b=Lb||Sb(),c=Math.max(0,j.startTime+j.duration-b),d=c/j.duration||0,f=1-d,g=0,i=j.tweens.length;i>g;g++)j.tweens[g].run(f);return h.notifyWith(a,[j,f,c]),1>f&&i?c:(h.resolveWith(a,[j]),!1)},j=h.promise({elem:a,props:n.extend({},b),opts:n.extend(!0,{specialEasing:{}},c),originalProperties:b,originalOptions:c,startTime:Lb||Sb(),duration:c.duration,tweens:[],createTween:function(b,c){var d=n.Tween(a,j.opts,b,c,j.opts.specialEasing[b]||j.opts.easing);return j.tweens.push(d),d},stop:function(b){var c=0,d=b?j.tweens.length:0;if(e)return this;for(e=!0;d>c;c++)j.tweens[c].run(1);return b?h.resolveWith(a,[j,b]):h.rejectWith(a,[j,b]),this}}),k=j.props;for(Wb(k,j.opts.specialEasing);g>f;f++)if(d=Qb[f].call(j,a,k,j.opts))return d;return n.map(k,Ub,j),n.isFunction(j.opts.start)&&j.opts.start.call(a,j),n.fx.timer(n.extend(i,{elem:a,anim:j,queue:j.opts.queue})),j.progress(j.opts.progress).done(j.opts.done,j.opts.complete).fail(j.opts.fail).always(j.opts.always)}n.Animation=n.extend(Xb,{tweener:function(a,b){n.isFunction(a)?(b=a,a=["*"]):a=a.split(" ");for(var c,d=0,e=a.length;e>d;d++)c=a[d],Rb[c]=Rb[c]||[],Rb[c].unshift(b)},prefilter:function(a,b){b?Qb.unshift(a):Qb.push(a)}}),n.speed=function(a,b,c){var d=a&&"object"==typeof a?n.extend({},a):{complete:c||!c&&b||n.isFunction(a)&&a,duration:a,easing:c&&b||b&&!n.isFunction(b)&&b};return d.duration=n.fx.off?0:"number"==typeof d.duration?d.duration:d.duration in n.fx.speeds?n.fx.speeds[d.duration]:n.fx.speeds._default,(null==d.queue||d.queue===!0)&&(d.queue="fx"),d.old=d.complete,d.complete=function(){n.isFunction(d.old)&&d.old.call(this),d.queue&&n.dequeue(this,d.queue)},d},n.fn.extend({fadeTo:function(a,b,c,d){return this.filter(S).css("opacity",0).show().end().animate({opacity:b},a,c,d)},animate:function(a,b,c,d){var e=n.isEmptyObject(a),f=n.speed(b,c,d),g=function(){var b=Xb(this,n.extend({},a),f);(e||L.get(this,"finish"))&&b.stop(!0)};return g.finish=g,e||f.queue===!1?this.each(g):this.queue(f.queue,g)},stop:function(a,b,c){var d=function(a){var b=a.stop;delete a.stop,b(c)};return"string"!=typeof a&&(c=b,b=a,a=void 0),b&&a!==!1&&this.queue(a||"fx",[]),this.each(function(){var b=!0,e=null!=a&&a+"queueHooks",f=n.timers,g=L.get(this);if(e)g[e]&&g[e].stop&&d(g[e]);else for(e in g)g[e]&&g[e].stop&&Pb.test(e)&&d(g[e]);for(e=f.length;e--;)f[e].elem!==this||null!=a&&f[e].queue!==a||(f[e].anim.stop(c),b=!1,f.splice(e,1));(b||!c)&&n.dequeue(this,a)})},finish:function(a){return a!==!1&&(a=a||"fx"),this.each(function(){var b,c=L.get(this),d=c[a+"queue"],e=c[a+"queueHooks"],f=n.timers,g=d?d.length:0;for(c.finish=!0,n.queue(this,a,[]),e&&e.stop&&e.stop.call(this,!0),b=f.length;b--;)f[b].elem===this&&f[b].queue===a&&(f[b].anim.stop(!0),f.splice(b,1));for(b=0;g>b;b++)d[b]&&d[b].finish&&d[b].finish.call(this);delete c.finish})}}),n.each(["toggle","show","hide"],function(a,b){var c=n.fn[b];n.fn[b]=function(a,d,e){return null==a||"boolean"==typeof a?c.apply(this,arguments):this.animate(Tb(b,!0),a,d,e)}}),n.each({slideDown:Tb("show"),slideUp:Tb("hide"),slideToggle:Tb("toggle"),fadeIn:{opacity:"show"},fadeOut:{opacity:"hide"},fadeToggle:{opacity:"toggle"}},function(a,b){n.fn[a]=function(a,c,d){return this.animate(b,a,c,d)}}),n.timers=[],n.fx.tick=function(){var a,b=0,c=n.timers;for(Lb=n.now();b<c.length;b++)a=c[b],a()||c[b]!==a||c.splice(b--,1);c.length||n.fx.stop(),Lb=void 0},n.fx.timer=function(a){n.timers.push(a),a()?n.fx.start():n.timers.pop()},n.fx.interval=13,n.fx.start=function(){Mb||(Mb=setInterval(n.fx.tick,n.fx.interval))},n.fx.stop=function(){clearInterval(Mb),Mb=null},n.fx.speeds={slow:600,fast:200,_default:400},n.fn.delay=function(a,b){return a=n.fx?n.fx.speeds[a]||a:a,b=b||"fx",this.queue(b,function(b,c){var d=setTimeout(b,a);c.stop=function(){clearTimeout(d)}})},function(){var a=l.createElement("input"),b=l.createElement("select"),c=b.appendChild(l.createElement("option"));a.type="checkbox",k.checkOn=""!==a.value,k.optSelected=c.selected,b.disabled=!0,k.optDisabled=!c.disabled,a=l.createElement("input"),a.value="t",a.type="radio",k.radioValue="t"===a.value}();var Yb,Zb,$b=n.expr.attrHandle;n.fn.extend({attr:function(a,b){return J(this,n.attr,a,b,arguments.length>1)},removeAttr:function(a){return this.each(function(){n.removeAttr(this,a)})}}),n.extend({attr:function(a,b,c){var d,e,f=a.nodeType;if(a&&3!==f&&8!==f&&2!==f)return typeof a.getAttribute===U?n.prop(a,b,c):(1===f&&n.isXMLDoc(a)||(b=b.toLowerCase(),d=n.attrHooks[b]||(n.expr.match.bool.test(b)?Zb:Yb)),void 0===c?d&&"get"in d&&null!==(e=d.get(a,b))?e:(e=n.find.attr(a,b),null==e?void 0:e):null!==c?d&&"set"in d&&void 0!==(e=d.set(a,c,b))?e:(a.setAttribute(b,c+""),c):void n.removeAttr(a,b))
},removeAttr:function(a,b){var c,d,e=0,f=b&&b.match(E);if(f&&1===a.nodeType)while(c=f[e++])d=n.propFix[c]||c,n.expr.match.bool.test(c)&&(a[d]=!1),a.removeAttribute(c)},attrHooks:{type:{set:function(a,b){if(!k.radioValue&&"radio"===b&&n.nodeName(a,"input")){var c=a.value;return a.setAttribute("type",b),c&&(a.value=c),b}}}}}),Zb={set:function(a,b,c){return b===!1?n.removeAttr(a,c):a.setAttribute(c,c),c}},n.each(n.expr.match.bool.source.match(/\\w+/g),function(a,b){var c=$b[b]||n.find.attr;$b[b]=function(a,b,d){var e,f;return d||(f=$b[b],$b[b]=e,e=null!=c(a,b,d)?b.toLowerCase():null,$b[b]=f),e}});var _b=/^(?:input|select|textarea|button)$/i;n.fn.extend({prop:function(a,b){return J(this,n.prop,a,b,arguments.length>1)},removeProp:function(a){return this.each(function(){delete this[n.propFix[a]||a]})}}),n.extend({propFix:{"for":"htmlFor","class":"className"},prop:function(a,b,c){var d,e,f,g=a.nodeType;if(a&&3!==g&&8!==g&&2!==g)return f=1!==g||!n.isXMLDoc(a),f&&(b=n.propFix[b]||b,e=n.propHooks[b]),void 0!==c?e&&"set"in e&&void 0!==(d=e.set(a,c,b))?d:a[b]=c:e&&"get"in e&&null!==(d=e.get(a,b))?d:a[b]},propHooks:{tabIndex:{get:function(a){return a.hasAttribute("tabindex")||_b.test(a.nodeName)||a.href?a.tabIndex:-1}}}}),k.optSelected||(n.propHooks.selected={get:function(a){var b=a.parentNode;return b&&b.parentNode&&b.parentNode.selectedIndex,null}}),n.each(["tabIndex","readOnly","maxLength","cellSpacing","cellPadding","rowSpan","colSpan","useMap","frameBorder","contentEditable"],function(){n.propFix[this.toLowerCase()]=this});var ac=/[\\t\\r\\n\\f]/g;n.fn.extend({addClass:function(a){var b,c,d,e,f,g,h="string"==typeof a&&a,i=0,j=this.length;if(n.isFunction(a))return this.each(function(b){n(this).addClass(a.call(this,b,this.className))});if(h)for(b=(a||"").match(E)||[];j>i;i++)if(c=this[i],d=1===c.nodeType&&(c.className?(" "+c.className+" ").replace(ac," "):" ")){f=0;while(e=b[f++])d.indexOf(" "+e+" ")<0&&(d+=e+" ");g=n.trim(d),c.className!==g&&(c.className=g)}return this},removeClass:function(a){var b,c,d,e,f,g,h=0===arguments.length||"string"==typeof a&&a,i=0,j=this.length;if(n.isFunction(a))return this.each(function(b){n(this).removeClass(a.call(this,b,this.className))});if(h)for(b=(a||"").match(E)||[];j>i;i++)if(c=this[i],d=1===c.nodeType&&(c.className?(" "+c.className+" ").replace(ac," "):"")){f=0;while(e=b[f++])while(d.indexOf(" "+e+" ")>=0)d=d.replace(" "+e+" "," ");g=a?n.trim(d):"",c.className!==g&&(c.className=g)}return this},toggleClass:function(a,b){var c=typeof a;return"boolean"==typeof b&&"string"===c?b?this.addClass(a):this.removeClass(a):this.each(n.isFunction(a)?function(c){n(this).toggleClass(a.call(this,c,this.className,b),b)}:function(){if("string"===c){var b,d=0,e=n(this),f=a.match(E)||[];while(b=f[d++])e.hasClass(b)?e.removeClass(b):e.addClass(b)}else(c===U||"boolean"===c)&&(this.className&&L.set(this,"__className__",this.className),this.className=this.className||a===!1?"":L.get(this,"__className__")||"")})},hasClass:function(a){for(var b=" "+a+" ",c=0,d=this.length;d>c;c++)if(1===this[c].nodeType&&(" "+this[c].className+" ").replace(ac," ").indexOf(b)>=0)return!0;return!1}});var bc=/\\r/g;n.fn.extend({val:function(a){var b,c,d,e=this[0];{if(arguments.length)return d=n.isFunction(a),this.each(function(c){var e;1===this.nodeType&&(e=d?a.call(this,c,n(this).val()):a,null==e?e="":"number"==typeof e?e+="":n.isArray(e)&&(e=n.map(e,function(a){return null==a?"":a+""})),b=n.valHooks[this.type]||n.valHooks[this.nodeName.toLowerCase()],b&&"set"in b&&void 0!==b.set(this,e,"value")||(this.value=e))});if(e)return b=n.valHooks[e.type]||n.valHooks[e.nodeName.toLowerCase()],b&&"get"in b&&void 0!==(c=b.get(e,"value"))?c:(c=e.value,"string"==typeof c?c.replace(bc,""):null==c?"":c)}}}),n.extend({valHooks:{option:{get:function(a){var b=n.find.attr(a,"value");return null!=b?b:n.trim(n.text(a))}},select:{get:function(a){for(var b,c,d=a.options,e=a.selectedIndex,f="select-one"===a.type||0>e,g=f?null:[],h=f?e+1:d.length,i=0>e?h:f?e:0;h>i;i++)if(c=d[i],!(!c.selected&&i!==e||(k.optDisabled?c.disabled:null!==c.getAttribute("disabled"))||c.parentNode.disabled&&n.nodeName(c.parentNode,"optgroup"))){if(b=n(c).val(),f)return b;g.push(b)}return g},set:function(a,b){var c,d,e=a.options,f=n.makeArray(b),g=e.length;while(g--)d=e[g],(d.selected=n.inArray(d.value,f)>=0)&&(c=!0);return c||(a.selectedIndex=-1),f}}}}),n.each(["radio","checkbox"],function(){n.valHooks[this]={set:function(a,b){return n.isArray(b)?a.checked=n.inArray(n(a).val(),b)>=0:void 0}},k.checkOn||(n.valHooks[this].get=function(a){return null===a.getAttribute("value")?"on":a.value})}),n.each("blur focus focusin focusout load resize scroll unload click dblclick mousedown mouseup mousemove mouseover mouseout mouseenter mouseleave change select submit keydown keypress keyup error contextmenu".split(" "),function(a,b){n.fn[b]=function(a,c){return arguments.length>0?this.on(b,null,a,c):this.trigger(b)}}),n.fn.extend({hover:function(a,b){return this.mouseenter(a).mouseleave(b||a)},bind:function(a,b,c){return this.on(a,null,b,c)},unbind:function(a,b){return this.off(a,null,b)},delegate:function(a,b,c,d){return this.on(b,a,c,d)},undelegate:function(a,b,c){return 1===arguments.length?this.off(a,"**"):this.off(b,a||"**",c)}});var cc=n.now(),dc=/\\?/;n.parseJSON=function(a){return JSON.parse(a+"")},n.parseXML=function(a){var b,c;if(!a||"string"!=typeof a)return null;try{c=new DOMParser,b=c.parseFromString(a,"text/xml")}catch(d){b=void 0}return(!b||b.getElementsByTagName("parsererror").length)&&n.error("Invalid XML: "+a),b};var ec=/#.*$/,fc=/([?&])_=[^&]*/,gc=/^(.*?):[ \\t]*([^\\r\\n]*)$/gm,hc=/^(?:about|app|app-storage|.+-extension|file|res|widget):$/,ic=/^(?:GET|HEAD)$/,jc=/^\\/\\//,kc=/^([\\w.+-]+:)(?:\\/\\/(?:[^\\/?#]*@|)([^\\/?#:]*)(?::(\\d+)|)|)/,lc={},mc={},nc="*/".concat("*"),oc=a.location.href,pc=kc.exec(oc.toLowerCase())||[];function qc(a){return function(b,c){"string"!=typeof b&&(c=b,b="*");var d,e=0,f=b.toLowerCase().match(E)||[];if(n.isFunction(c))while(d=f[e++])"+"===d[0]?(d=d.slice(1)||"*",(a[d]=a[d]||[]).unshift(c)):(a[d]=a[d]||[]).push(c)}}function rc(a,b,c,d){var e={},f=a===mc;function g(h){var i;return e[h]=!0,n.each(a[h]||[],function(a,h){var j=h(b,c,d);return"string"!=typeof j||f||e[j]?f?!(i=j):void 0:(b.dataTypes.unshift(j),g(j),!1)}),i}return g(b.dataTypes[0])||!e["*"]&&g("*")}function sc(a,b){var c,d,e=n.ajaxSettings.flatOptions||{};for(c in b)void 0!==b[c]&&((e[c]?a:d||(d={}))[c]=b[c]);return d&&n.extend(!0,a,d),a}function tc(a,b,c){var d,e,f,g,h=a.contents,i=a.dataTypes;while("*"===i[0])i.shift(),void 0===d&&(d=a.mimeType||b.getResponseHeader("Content-Type"));if(d)for(e in h)if(h[e]&&h[e].test(d)){i.unshift(e);break}if(i[0]in c)f=i[0];else{for(e in c){if(!i[0]||a.converters[e+" "+i[0]]){f=e;break}g||(g=e)}f=f||g}return f?(f!==i[0]&&i.unshift(f),c[f]):void 0}function uc(a,b,c,d){var e,f,g,h,i,j={},k=a.dataTypes.slice();if(k[1])for(g in a.converters)j[g.toLowerCase()]=a.converters[g];f=k.shift();while(f)if(a.responseFields[f]&&(c[a.responseFields[f]]=b),!i&&d&&a.dataFilter&&(b=a.dataFilter(b,a.dataType)),i=f,f=k.shift())if("*"===f)f=i;else if("*"!==i&&i!==f){if(g=j[i+" "+f]||j["* "+f],!g)for(e in j)if(h=e.split(" "),h[1]===f&&(g=j[i+" "+h[0]]||j["* "+h[0]])){g===!0?g=j[e]:j[e]!==!0&&(f=h[0],k.unshift(h[1]));break}if(g!==!0)if(g&&a["throws"])b=g(b);else try{b=g(b)}catch(l){return{state:"parsererror",error:g?l:"No conversion from "+i+" to "+f}}}return{state:"success",data:b}}n.extend({active:0,lastModified:{},etag:{},ajaxSettings:{url:oc,type:"GET",isLocal:hc.test(pc[1]),global:!0,processData:!0,async:!0,contentType:"application/x-www-form-urlencoded; charset=UTF-8",accepts:{"*":nc,text:"text/plain",html:"text/html",xml:"application/xml, text/xml",json:"application/json, text/javascript"},contents:{xml:/xml/,html:/html/,json:/json/},responseFields:{xml:"responseXML",text:"responseText",json:"responseJSON"},converters:{"* text":String,"text html":!0,"text json":n.parseJSON,"text xml":n.parseXML},flatOptions:{url:!0,context:!0}},ajaxSetup:function(a,b){return b?sc(sc(a,n.ajaxSettings),b):sc(n.ajaxSettings,a)},ajaxPrefilter:qc(lc),ajaxTransport:qc(mc),ajax:function(a,b){"object"==typeof a&&(b=a,a=void 0),b=b||{};var c,d,e,f,g,h,i,j,k=n.ajaxSetup({},b),l=k.context||k,m=k.context&&(l.nodeType||l.jquery)?n(l):n.event,o=n.Deferred(),p=n.Callbacks("once memory"),q=k.statusCode||{},r={},s={},t=0,u="canceled",v={readyState:0,getResponseHeader:function(a){var b;if(2===t){if(!f){f={};while(b=gc.exec(e))f[b[1].toLowerCase()]=b[2]}b=f[a.toLowerCase()]}return null==b?null:b},getAllResponseHeaders:function(){return 2===t?e:null},setRequestHeader:function(a,b){var c=a.toLowerCase();return t||(a=s[c]=s[c]||a,r[a]=b),this},overrideMimeType:function(a){return t||(k.mimeType=a),this},statusCode:function(a){var b;if(a)if(2>t)for(b in a)q[b]=[q[b],a[b]];else v.always(a[v.status]);return this},abort:function(a){var b=a||u;return c&&c.abort(b),x(0,b),this}};if(o.promise(v).complete=p.add,v.success=v.done,v.error=v.fail,k.url=((a||k.url||oc)+"").replace(ec,"").replace(jc,pc[1]+"//"),k.type=b.method||b.type||k.method||k.type,k.dataTypes=n.trim(k.dataType||"*").toLowerCase().match(E)||[""],null==k.crossDomain&&(h=kc.exec(k.url.toLowerCase()),k.crossDomain=!(!h||h[1]===pc[1]&&h[2]===pc[2]&&(h[3]||("http:"===h[1]?"80":"443"))===(pc[3]||("http:"===pc[1]?"80":"443")))),k.data&&k.processData&&"string"!=typeof k.data&&(k.data=n.param(k.data,k.traditional)),rc(lc,k,b,v),2===t)return v;i=n.event&&k.global,i&&0===n.active++&&n.event.trigger("ajaxStart"),k.type=k.type.toUpperCase(),k.hasContent=!ic.test(k.type),d=k.url,k.hasContent||(k.data&&(d=k.url+=(dc.test(d)?"&":"?")+k.data,delete k.data),k.cache===!1&&(k.url=fc.test(d)?d.replace(fc,"$1_="+cc++):d+(dc.test(d)?"&":"?")+"_="+cc++)),k.ifModified&&(n.lastModified[d]&&v.setRequestHeader("If-Modified-Since",n.lastModified[d]),n.etag[d]&&v.setRequestHeader("If-None-Match",n.etag[d])),(k.data&&k.hasContent&&k.contentType!==!1||b.contentType)&&v.setRequestHeader("Content-Type",k.contentType),v.setRequestHeader("Accept",k.dataTypes[0]&&k.accepts[k.dataTypes[0]]?k.accepts[k.dataTypes[0]]+("*"!==k.dataTypes[0]?", "+nc+"; q=0.01":""):k.accepts["*"]);for(j in k.headers)v.setRequestHeader(j,k.headers[j]);if(k.beforeSend&&(k.beforeSend.call(l,v,k)===!1||2===t))return v.abort();u="abort";for(j in{success:1,error:1,complete:1})v[j](k[j]);if(c=rc(mc,k,b,v)){v.readyState=1,i&&m.trigger("ajaxSend",[v,k]),k.async&&k.timeout>0&&(g=setTimeout(function(){v.abort("timeout")},k.timeout));try{t=1,c.send(r,x)}catch(w){if(!(2>t))throw w;x(-1,w)}}else x(-1,"No Transport");function x(a,b,f,h){var j,r,s,u,w,x=b;2!==t&&(t=2,g&&clearTimeout(g),c=void 0,e=h||"",v.readyState=a>0?4:0,j=a>=200&&300>a||304===a,f&&(u=tc(k,v,f)),u=uc(k,u,v,j),j?(k.ifModified&&(w=v.getResponseHeader("Last-Modified"),w&&(n.lastModified[d]=w),w=v.getResponseHeader("etag"),w&&(n.etag[d]=w)),204===a||"HEAD"===k.type?x="nocontent":304===a?x="notmodified":(x=u.state,r=u.data,s=u.error,j=!s)):(s=x,(a||!x)&&(x="error",0>a&&(a=0))),v.status=a,v.statusText=(b||x)+"",j?o.resolveWith(l,[r,x,v]):o.rejectWith(l,[v,x,s]),v.statusCode(q),q=void 0,i&&m.trigger(j?"ajaxSuccess":"ajaxError",[v,k,j?r:s]),p.fireWith(l,[v,x]),i&&(m.trigger("ajaxComplete",[v,k]),--n.active||n.event.trigger("ajaxStop")))}return v},getJSON:function(a,b,c){return n.get(a,b,c,"json")},getScript:function(a,b){return n.get(a,void 0,b,"script")}}),n.each(["get","post"],function(a,b){n[b]=function(a,c,d,e){return n.isFunction(c)&&(e=e||d,d=c,c=void 0),n.ajax({url:a,type:b,dataType:e,data:c,success:d})}}),n._evalUrl=function(a){return n.ajax({url:a,type:"GET",dataType:"script",async:!1,global:!1,"throws":!0})},n.fn.extend({wrapAll:function(a){var b;return n.isFunction(a)?this.each(function(b){n(this).wrapAll(a.call(this,b))}):(this[0]&&(b=n(a,this[0].ownerDocument).eq(0).clone(!0),this[0].parentNode&&b.insertBefore(this[0]),b.map(function(){var a=this;while(a.firstElementChild)a=a.firstElementChild;return a}).append(this)),this)},wrapInner:function(a){return this.each(n.isFunction(a)?function(b){n(this).wrapInner(a.call(this,b))}:function(){var b=n(this),c=b.contents();c.length?c.wrapAll(a):b.append(a)})},wrap:function(a){var b=n.isFunction(a);return this.each(function(c){n(this).wrapAll(b?a.call(this,c):a)})},unwrap:function(){return this.parent().each(function(){n.nodeName(this,"body")||n(this).replaceWith(this.childNodes)}).end()}}),n.expr.filters.hidden=function(a){return a.offsetWidth<=0&&a.offsetHeight<=0},n.expr.filters.visible=function(a){return!n.expr.filters.hidden(a)};var vc=/%20/g,wc=/\\[\\]$/,xc=/\\r?\\n/g,yc=/^(?:submit|button|image|reset|file)$/i,zc=/^(?:input|select|textarea|keygen)/i;function Ac(a,b,c,d){var e;if(n.isArray(b))n.each(b,function(b,e){c||wc.test(a)?d(a,e):Ac(a+"["+("object"==typeof e?b:"")+"]",e,c,d)});else if(c||"object"!==n.type(b))d(a,b);else for(e in b)Ac(a+"["+e+"]",b[e],c,d)}n.param=function(a,b){var c,d=[],e=function(a,b){b=n.isFunction(b)?b():null==b?"":b,d[d.length]=encodeURIComponent(a)+"="+encodeURIComponent(b)};if(void 0===b&&(b=n.ajaxSettings&&n.ajaxSettings.traditional),n.isArray(a)||a.jquery&&!n.isPlainObject(a))n.each(a,function(){e(this.name,this.value)});else for(c in a)Ac(c,a[c],b,e);return d.join("&").replace(vc,"+")},n.fn.extend({serialize:function(){return n.param(this.serializeArray())},serializeArray:function(){return this.map(function(){var a=n.prop(this,"elements");return a?n.makeArray(a):this}).filter(function(){var a=this.type;return this.name&&!n(this).is(":disabled")&&zc.test(this.nodeName)&&!yc.test(a)&&(this.checked||!T.test(a))}).map(function(a,b){var c=n(this).val();return null==c?null:n.isArray(c)?n.map(c,function(a){return{name:b.name,value:a.replace(xc,"\\r\\n")}}):{name:b.name,value:c.replace(xc,"\\r\\n")}}).get()}}),n.ajaxSettings.xhr=function(){try{return new XMLHttpRequest}catch(a){}};var Bc=0,Cc={},Dc={0:200,1223:204},Ec=n.ajaxSettings.xhr();a.attachEvent&&a.attachEvent("onunload",function(){for(var a in Cc)Cc[a]()}),k.cors=!!Ec&&"withCredentials"in Ec,k.ajax=Ec=!!Ec,n.ajaxTransport(function(a){var b;return k.cors||Ec&&!a.crossDomain?{send:function(c,d){var e,f=a.xhr(),g=++Bc;if(f.open(a.type,a.url,a.async,a.username,a.password),a.xhrFields)for(e in a.xhrFields)f[e]=a.xhrFields[e];a.mimeType&&f.overrideMimeType&&f.overrideMimeType(a.mimeType),a.crossDomain||c["X-Requested-With"]||(c["X-Requested-With"]="XMLHttpRequest");for(e in c)f.setRequestHeader(e,c[e]);b=function(a){return function(){b&&(delete Cc[g],b=f.onload=f.onerror=null,"abort"===a?f.abort():"error"===a?d(f.status,f.statusText):d(Dc[f.status]||f.status,f.statusText,"string"==typeof f.responseText?{text:f.responseText}:void 0,f.getAllResponseHeaders()))}},f.onload=b(),f.onerror=b("error"),b=Cc[g]=b("abort");try{f.send(a.hasContent&&a.data||null)}catch(h){if(b)throw h}},abort:function(){b&&b()}}:void 0}),n.ajaxSetup({accepts:{script:"text/javascript, application/javascript, application/ecmascript, application/x-ecmascript"},contents:{script:/(?:java|ecma)script/},converters:{"text script":function(a){return n.globalEval(a),a}}}),n.ajaxPrefilter("script",function(a){void 0===a.cache&&(a.cache=!1),a.crossDomain&&(a.type="GET")}),n.ajaxTransport("script",function(a){if(a.crossDomain){var b,c;return{send:function(d,e){b=n("<script>").prop({async:!0,charset:a.scriptCharset,src:a.url}).on("load error",c=function(a){b.remove(),c=null,a&&e("error"===a.type?404:200,a.type)}),l.head.appendChild(b[0])},abort:function(){c&&c()}}}});var Fc=[],Gc=/(=)\\?(?=&|$)|\\?\\?/;n.ajaxSetup({jsonp:"callback",jsonpCallback:function(){var a=Fc.pop()||n.expando+"_"+cc++;return this[a]=!0,a}}),n.ajaxPrefilter("json jsonp",function(b,c,d){var e,f,g,h=b.jsonp!==!1&&(Gc.test(b.url)?"url":"string"==typeof b.data&&!(b.contentType||"").indexOf("application/x-www-form-urlencoded")&&Gc.test(b.data)&&"data");return h||"jsonp"===b.dataTypes[0]?(e=b.jsonpCallback=n.isFunction(b.jsonpCallback)?b.jsonpCallback():b.jsonpCallback,h?b[h]=b[h].replace(Gc,"$1"+e):b.jsonp!==!1&&(b.url+=(dc.test(b.url)?"&":"?")+b.jsonp+"="+e),b.converters["script json"]=function(){return g||n.error(e+" was not called"),g[0]},b.dataTypes[0]="json",f=a[e],a[e]=function(){g=arguments},d.always(function(){a[e]=f,b[e]&&(b.jsonpCallback=c.jsonpCallback,Fc.push(e)),g&&n.isFunction(f)&&f(g[0]),g=f=void 0}),"script"):void 0}),n.parseHTML=function(a,b,c){if(!a||"string"!=typeof a)return null;"boolean"==typeof b&&(c=b,b=!1),b=b||l;var d=v.exec(a),e=!c&&[];return d?[b.createElement(d[1])]:(d=n.buildFragment([a],b,e),e&&e.length&&n(e).remove(),n.merge([],d.childNodes))};var Hc=n.fn.load;n.fn.load=function(a,b,c){if("string"!=typeof a&&Hc)return Hc.apply(this,arguments);var d,e,f,g=this,h=a.indexOf(" ");return h>=0&&(d=n.trim(a.slice(h)),a=a.slice(0,h)),n.isFunction(b)?(c=b,b=void 0):b&&"object"==typeof b&&(e="POST"),g.length>0&&n.ajax({url:a,type:e,dataType:"html",data:b}).done(function(a){f=arguments,g.html(d?n("<div>").append(n.parseHTML(a)).find(d):a)}).complete(c&&function(a,b){g.each(c,f||[a.responseText,b,a])}),this},n.each(["ajaxStart","ajaxStop","ajaxComplete","ajaxError","ajaxSuccess","ajaxSend"],function(a,b){n.fn[b]=function(a){return this.on(b,a)}}),n.expr.filters.animated=function(a){return n.grep(n.timers,function(b){return a===b.elem}).length};var Ic=a.document.documentElement;function Jc(a){return n.isWindow(a)?a:9===a.nodeType&&a.defaultView}n.offset={setOffset:function(a,b,c){var d,e,f,g,h,i,j,k=n.css(a,"position"),l=n(a),m={};"static"===k&&(a.style.position="relative"),h=l.offset(),f=n.css(a,"top"),i=n.css(a,"left"),j=("absolute"===k||"fixed"===k)&&(f+i).indexOf("auto")>-1,j?(d=l.position(),g=d.top,e=d.left):(g=parseFloat(f)||0,e=parseFloat(i)||0),n.isFunction(b)&&(b=b.call(a,c,h)),null!=b.top&&(m.top=b.top-h.top+g),null!=b.left&&(m.left=b.left-h.left+e),"using"in b?b.using.call(a,m):l.css(m)}},n.fn.extend({offset:function(a){if(arguments.length)return void 0===a?this:this.each(function(b){n.offset.setOffset(this,a,b)});var b,c,d=this[0],e={top:0,left:0},f=d&&d.ownerDocument;if(f)return b=f.documentElement,n.contains(b,d)?(typeof d.getBoundingClientRect!==U&&(e=d.getBoundingClientRect()),c=Jc(f),{top:e.top+c.pageYOffset-b.clientTop,left:e.left+c.pageXOffset-b.clientLeft}):e},position:function(){if(this[0]){var a,b,c=this[0],d={top:0,left:0};return"fixed"===n.css(c,"position")?b=c.getBoundingClientRect():(a=this.offsetParent(),b=this.offset(),n.nodeName(a[0],"html")||(d=a.offset()),d.top+=n.css(a[0],"borderTopWidth",!0),d.left+=n.css(a[0],"borderLeftWidth",!0)),{top:b.top-d.top-n.css(c,"marginTop",!0),left:b.left-d.left-n.css(c,"marginLeft",!0)}}},offsetParent:function(){return this.map(function(){var a=this.offsetParent||Ic;while(a&&!n.nodeName(a,"html")&&"static"===n.css(a,"position"))a=a.offsetParent;return a||Ic})}}),n.each({scrollLeft:"pageXOffset",scrollTop:"pageYOffset"},function(b,c){var d="pageYOffset"===c;n.fn[b]=function(e){return J(this,function(b,e,f){var g=Jc(b);return void 0===f?g?g[c]:b[e]:void(g?g.scrollTo(d?a.pageXOffset:f,d?f:a.pageYOffset):b[e]=f)},b,e,arguments.length,null)}}),n.each(["top","left"],function(a,b){n.cssHooks[b]=yb(k.pixelPosition,function(a,c){return c?(c=xb(a,b),vb.test(c)?n(a).position()[b]+"px":c):void 0})}),n.each({Height:"height",Width:"width"},function(a,b){n.each({padding:"inner"+a,content:b,"":"outer"+a},function(c,d){n.fn[d]=function(d,e){var f=arguments.length&&(c||"boolean"!=typeof d),g=c||(d===!0||e===!0?"margin":"border");return J(this,function(b,c,d){var e;return n.isWindow(b)?b.document.documentElement["client"+a]:9===b.nodeType?(e=b.documentElement,Math.max(b.body["scroll"+a],e["scroll"+a],b.body["offset"+a],e["offset"+a],e["client"+a])):void 0===d?n.css(b,c,g):n.style(b,c,d,g)},b,f?d:void 0,f,null)}})}),n.fn.size=function(){return this.length},n.fn.andSelf=n.fn.addBack,"function"==typeof define&&define.amd&&define("jquery",[],function(){return n});var Kc=a.jQuery,Lc=a.$;return n.noConflict=function(b){return a.$===n&&(a.$=Lc),b&&a.jQuery===n&&(a.jQuery=Kc),n},typeof b===U&&(a.jQuery=a.$=n),n});';

        if ($withHtmlTag) {
            $result .= '
</script>
';
        }

        return $result;
    }

    /**
     * Returns the JavaScript code of Modernizr.
     *
     * @param bool $withHtmlTag With HTML tags or not.
     *
     * @return string The source code.
     */
    public static function getModernizerJs($withHtmlTag = false) {
        $result = '';
        
        if ($withHtmlTag) {
            $result .= '
<script type="text/javascript">
';
        }

        $result .= '/*!
 * Modernizr v2.8.3
 * www.modernizr.com
 *
 * Copyright (c) Faruk Ates, Paul Irish, Alex Sexton
 * Available under the BSD and MIT licenses: www.modernizr.com/license/
 */
window.Modernizr=function(a,b,c){function d(a){t.cssText=a}function e(a,b){return d(x.join(a+";")+(b||""))}function f(a,b){return typeof a===b}function g(a,b){return!!~(""+a).indexOf(b)}function h(a,b){for(var d in a){var e=a[d];if(!g(e,"-")&&t[e]!==c)return"pfx"==b?e:!0}return!1}function i(a,b,d){for(var e in a){var g=b[a[e]];if(g!==c)return d===!1?a[e]:f(g,"function")?g.bind(d||b):g}return!1}function j(a,b,c){var d=a.charAt(0).toUpperCase()+a.slice(1),e=(a+" "+z.join(d+" ")+d).split(" ");return f(b,"string")||f(b,"undefined")?h(e,b):(e=(a+" "+A.join(d+" ")+d).split(" "),i(e,b,c))}function k(){o.input=function(c){for(var d=0,e=c.length;e>d;d++)E[c[d]]=!!(c[d]in u);return E.list&&(E.list=!(!b.createElement("datalist")||!a.HTMLDataListElement)),E}("autocomplete autofocus list placeholder max min multiple pattern required step".split(" ")),o.inputtypes=function(a){for(var d,e,f,g=0,h=a.length;h>g;g++)u.setAttribute("type",e=a[g]),d="text"!==u.type,d&&(u.value=v,u.style.cssText="position:absolute;visibility:hidden;",/^range$/.test(e)&&u.style.WebkitAppearance!==c?(q.appendChild(u),f=b.defaultView,d=f.getComputedStyle&&"textfield"!==f.getComputedStyle(u,null).WebkitAppearance&&0!==u.offsetHeight,q.removeChild(u)):/^(search|tel)$/.test(e)||(d=/^(url|email)$/.test(e)?u.checkValidity&&u.checkValidity()===!1:u.value!=v)),D[a[g]]=!!d;return D}("search tel url email datetime date month week time datetime-local number range color".split(" "))}var l,m,n="2.8.3",o={},p=!0,q=b.documentElement,r="modernizr",s=b.createElement(r),t=s.style,u=b.createElement("input"),v=":)",w={}.toString,x=" -webkit- -moz- -o- -ms- ".split(" "),y="Webkit Moz O ms",z=y.split(" "),A=y.toLowerCase().split(" "),B={svg:"http://www.w3.org/2000/svg"},C={},D={},E={},F=[],G=F.slice,H=function(a,c,d,e){var f,g,h,i,j=b.createElement("div"),k=b.body,l=k||b.createElement("body");if(parseInt(d,10))for(;d--;)h=b.createElement("div"),h.id=e?e[d]:r+(d+1),j.appendChild(h);return f=["&#173;",\'<style id="s\',r,\'">\',a,"</style>"].join(""),j.id=r,(k?j:l).innerHTML+=f,l.appendChild(j),k||(l.style.background="",l.style.overflow="hidden",i=q.style.overflow,q.style.overflow="hidden",q.appendChild(l)),g=c(j,a),k?j.parentNode.removeChild(j):(l.parentNode.removeChild(l),q.style.overflow=i),!!g},I=function(b){var c=a.matchMedia||a.msMatchMedia;if(c)return c(b)&&c(b).matches||!1;var d;return H("@media "+b+" { #"+r+" { position: absolute; } }",function(b){d="absolute"==(a.getComputedStyle?getComputedStyle(b,null):b.currentStyle).position}),d},J=function(){function a(a,e){e=e||b.createElement(d[a]||"div"),a="on"+a;var g=a in e;return g||(e.setAttribute||(e=b.createElement("div")),e.setAttribute&&e.removeAttribute&&(e.setAttribute(a,""),g=f(e[a],"function"),f(e[a],"undefined")||(e[a]=c),e.removeAttribute(a))),e=null,g}var d={select:"input",change:"input",submit:"form",reset:"form",error:"img",load:"img",abort:"img"};return a}(),K={}.hasOwnProperty;m=f(K,"undefined")||f(K.call,"undefined")?function(a,b){return b in a&&f(a.constructor.prototype[b],"undefined")}:function(a,b){return K.call(a,b)},Function.prototype.bind||(Function.prototype.bind=function(a){var b=this;if("function"!=typeof b)throw new TypeError;var c=G.call(arguments,1),d=function(){if(this instanceof d){var e=function(){};e.prototype=b.prototype;var f=new e,g=b.apply(f,c.concat(G.call(arguments)));return Object(g)===g?g:f}return b.apply(a,c.concat(G.call(arguments)))};return d}),C.flexbox=function(){return j("flexWrap")},C.flexboxlegacy=function(){return j("boxDirection")},C.canvas=function(){var a=b.createElement("canvas");return!(!a.getContext||!a.getContext("2d"))},C.canvastext=function(){return!(!o.canvas||!f(b.createElement("canvas").getContext("2d").fillText,"function"))},C.webgl=function(){return!!a.WebGLRenderingContext},C.touch=function(){var c;return"ontouchstart"in a||a.DocumentTouch&&b instanceof DocumentTouch?c=!0:H(["@media (",x.join("touch-enabled),("),r,")","{#modernizr{top:9px;position:absolute}}"].join(""),function(a){c=9===a.offsetTop}),c},C.geolocation=function(){return"geolocation"in navigator},C.postmessage=function(){return!!a.postMessage},C.websqldatabase=function(){return!!a.openDatabase},C.indexedDB=function(){return!!j("indexedDB",a)},C.hashchange=function(){return J("hashchange",a)&&(b.documentMode===c||b.documentMode>7)},C.history=function(){return!(!a.history||!history.pushState)},C.draganddrop=function(){var a=b.createElement("div");return"draggable"in a||"ondragstart"in a&&"ondrop"in a},C.websockets=function(){return"WebSocket"in a||"MozWebSocket"in a},C.rgba=function(){return d("background-color:rgba(150,255,150,.5)"),g(t.backgroundColor,"rgba")},C.hsla=function(){return d("background-color:hsla(120,40%,100%,.5)"),g(t.backgroundColor,"rgba")||g(t.backgroundColor,"hsla")},C.multiplebgs=function(){return d("background:url(https://),url(https://),red url(https://)"),/(url\\s*\\(.*?){3}/.test(t.background)},C.backgroundsize=function(){return j("backgroundSize")},C.borderimage=function(){return j("borderImage")},C.borderradius=function(){return j("borderRadius")},C.boxshadow=function(){return j("boxShadow")},C.textshadow=function(){return""===b.createElement("div").style.textShadow},C.opacity=function(){return e("opacity:.55"),/^0.55$/.test(t.opacity)},C.cssanimations=function(){return j("animationName")},C.csscolumns=function(){return j("columnCount")},C.cssgradients=function(){var a="background-image:",b="gradient(linear,left top,right bottom,from(#9f9),to(white));",c="linear-gradient(left top,#9f9, white);";return d((a+"-webkit- ".split(" ").join(b+a)+x.join(c+a)).slice(0,-a.length)),g(t.backgroundImage,"gradient")},C.cssreflections=function(){return j("boxReflect")},C.csstransforms=function(){return!!j("transform")},C.csstransforms3d=function(){var a=!!j("perspective");return a&&"webkitPerspective"in q.style&&H("@media (transform-3d),(-webkit-transform-3d){#modernizr{left:9px;position:absolute;height:3px;}}",function(b){a=9===b.offsetLeft&&3===b.offsetHeight}),a},C.csstransitions=function(){return j("transition")},C.fontface=function(){var a;return H(\'@font-face {font-family:"font";src:url("https://")}\',function(c,d){var e=b.getElementById("smodernizr"),f=e.sheet||e.styleSheet,g=f?f.cssRules&&f.cssRules[0]?f.cssRules[0].cssText:f.cssText||"":"";a=/src/i.test(g)&&0===g.indexOf(d.split(" ")[0])}),a},C.generatedcontent=function(){var a;return H(["#",r,"{font:0/0 a}#",r,\':after{content:"\',v,\'";visibility:hidden;font:3px/1 a}\'].join(""),function(b){a=b.offsetHeight>=3}),a},C.video=function(){var a=b.createElement("video"),c=!1;try{(c=!!a.canPlayType)&&(c=new Boolean(c),c.ogg=a.canPlayType(\'video/ogg; codecs="theora"\').replace(/^no$/,""),c.h264=a.canPlayType(\'video/mp4; codecs="avc1.42E01E"\').replace(/^no$/,""),c.webm=a.canPlayType(\'video/webm; codecs="vp8, vorbis"\').replace(/^no$/,""))}catch(d){}return c},C.audio=function(){var a=b.createElement("audio"),c=!1;try{(c=!!a.canPlayType)&&(c=new Boolean(c),c.ogg=a.canPlayType(\'audio/ogg; codecs="vorbis"\').replace(/^no$/,""),c.mp3=a.canPlayType("audio/mpeg;").replace(/^no$/,""),c.wav=a.canPlayType(\'audio/wav; codecs="1"\').replace(/^no$/,""),c.m4a=(a.canPlayType("audio/x-m4a;")||a.canPlayType("audio/aac;")).replace(/^no$/,""))}catch(d){}return c},C.localstorage=function(){try{return localStorage.setItem(r,r),localStorage.removeItem(r),!0}catch(a){return!1}},C.sessionstorage=function(){try{return sessionStorage.setItem(r,r),sessionStorage.removeItem(r),!0}catch(a){return!1}},C.webworkers=function(){return!!a.Worker},C.applicationcache=function(){return!!a.applicationCache},C.svg=function(){return!!b.createElementNS&&!!b.createElementNS(B.svg,"svg").createSVGRect},C.inlinesvg=function(){var a=b.createElement("div");return a.innerHTML="<svg/>",(a.firstChild&&a.firstChild.namespaceURI)==B.svg},C.smil=function(){return!!b.createElementNS&&/SVGAnimate/.test(w.call(b.createElementNS(B.svg,"animate")))},C.svgclippaths=function(){return!!b.createElementNS&&/SVGClipPath/.test(w.call(b.createElementNS(B.svg,"clipPath")))};for(var L in C)m(C,L)&&(l=L.toLowerCase(),o[l]=C[L](),F.push((o[l]?"":"no-")+l));return o.input||k(),o.addTest=function(a,b){if("object"==typeof a)for(var d in a)m(a,d)&&o.addTest(d,a[d]);else{if(a=a.toLowerCase(),o[a]!==c)return o;b="function"==typeof b?b():b,"undefined"!=typeof p&&p&&(q.className+=" "+(b?"":"no-")+a),o[a]=b}return o},d(""),s=u=null,function(a,b){function c(a,b){var c=a.createElement("p"),d=a.getElementsByTagName("head")[0]||a.documentElement;return c.innerHTML="x<style>"+b+"</style>",d.insertBefore(c.lastChild,d.firstChild)}function d(){var a=s.elements;return"string"==typeof a?a.split(" "):a}function e(a){var b=r[a[p]];return b||(b={},q++,a[p]=q,r[q]=b),b}function f(a,c,d){if(c||(c=b),k)return c.createElement(a);d||(d=e(c));var f;return f=d.cache[a]?d.cache[a].cloneNode():o.test(a)?(d.cache[a]=d.createElem(a)).cloneNode():d.createElem(a),!f.canHaveChildren||n.test(a)||f.tagUrn?f:d.frag.appendChild(f)}function g(a,c){if(a||(a=b),k)return a.createDocumentFragment();c=c||e(a);for(var f=c.frag.cloneNode(),g=0,h=d(),i=h.length;i>g;g++)f.createElement(h[g]);return f}function h(a,b){b.cache||(b.cache={},b.createElem=a.createElement,b.createFrag=a.createDocumentFragment,b.frag=b.createFrag()),a.createElement=function(c){return s.shivMethods?f(c,a,b):b.createElem(c)},a.createDocumentFragment=Function("h,f","return function(){var n=f.cloneNode(),c=n.createElement;h.shivMethods&&("+d().join().replace(/[\\w\\-]+/g,function(a){return b.createElem(a),b.frag.createElement(a),\'c("\'+a+\'")\'})+");return n}")(s,b.frag)}function i(a){a||(a=b);var d=e(a);return!s.shivCSS||j||d.hasCSS||(d.hasCSS=!!c(a,"article,aside,dialog,figcaption,figure,footer,header,hgroup,main,nav,section{display:block}mark{background:#FF0;color:#000}template{display:none}")),k||h(a,d),a}var j,k,l="3.7.0",m=a.html5||{},n=/^<|^(?:button|map|select|textarea|object|iframe|option|optgroup)$/i,o=/^(?:a|b|code|div|fieldset|h1|h2|h3|h4|h5|h6|i|label|li|ol|p|q|span|strong|style|table|tbody|td|th|tr|ul)$/i,p="_html5shiv",q=0,r={};!function(){try{var a=b.createElement("a");a.innerHTML="<xyz></xyz>",j="hidden"in a,k=1==a.childNodes.length||function(){b.createElement("a");var a=b.createDocumentFragment();return"undefined"==typeof a.cloneNode||"undefined"==typeof a.createDocumentFragment||"undefined"==typeof a.createElement}()}catch(c){j=!0,k=!0}}();var s={elements:m.elements||"abbr article aside audio bdi canvas data datalist details dialog figcaption figure footer header hgroup main mark meter nav output progress section summary template time video",version:l,shivCSS:m.shivCSS!==!1,supportsUnknownElements:k,shivMethods:m.shivMethods!==!1,type:"default",shivDocument:i,createElement:f,createDocumentFragment:g};a.html5=s,i(b)}(this,b),o._version=n,o._prefixes=x,o._domPrefixes=A,o._cssomPrefixes=z,o.mq=I,o.hasEvent=J,o.testProp=function(a){return h([a])},o.testAllProps=j,o.testStyles=H,o.prefixed=function(a,b,c){return b?j(a,b,c):j(a,"pfx")},q.className=q.className.replace(/(^|\\s)no-js(\\s|$)/,"$1$2")+(p?" js "+F.join(" "):""),o}(this,this.document);';

        if ($withHtmlTag) {
            $result .= '
</script>
';
        }
        
        return $result;
    }

    /**
     * Gets the name of that instance.
     *
     * @return string The name of that instance.
     */
    public function getName() {
        return $this->_name;
    }

    /**
     * Returns the code of normalize.css.
     *
     * @param bool $withHtmlTag With HTML tags or not.
     *
     * @return string The CSS code.
     */
    public static function getNormalizeCss($withHtmlTag = false) {
        $result = '';

        if ($withHtmlTag) {
            $result .= '
<style type="text/css">
';
        }
        
        $result .= '/*! normalize.css v3.0.3 | MIT License | github.com/necolas/normalize.css */

/**
 * 1. Set default font family to sans-serif.
 * 2. Prevent iOS and IE text size adjust after device orientation change,
 *    without disabling user zoom.
 */

html {
  font-family: sans-serif; /* 1 */
  -ms-text-size-adjust: 100%; /* 2 */
  -webkit-text-size-adjust: 100%; /* 2 */
}

/**
 * Remove default margin.
 */

body {
  margin: 0;
}

/* HTML5 display definitions
   ========================================================================== */

/**
 * Correct `block` display not defined for any HTML5 element in IE 8/9.
 * Correct `block` display not defined for `details` or `summary` in IE 10/11
 * and Firefox.
 * Correct `block` display not defined for `main` in IE 11.
 */

article,
aside,
details,
figcaption,
figure,
footer,
header,
hgroup,
main,
menu,
nav,
section,
summary {
  display: block;
}

/**
 * 1. Correct `inline-block` display not defined in IE 8/9.
 * 2. Normalize vertical alignment of `progress` in Chrome, Firefox, and Opera.
 */

audio,
canvas,
progress,
video {
  display: inline-block; /* 1 */
  vertical-align: baseline; /* 2 */
}

/**
 * Prevent modern browsers from displaying `audio` without controls.
 * Remove excess height in iOS 5 devices.
 */

audio:not([controls]) {
  display: none;
  height: 0;
}

/**
 * Address `[hidden]` styling not present in IE 8/9/10.
 * Hide the `template` element in IE 8/9/10/11, Safari, and Firefox < 22.
 */

[hidden],
template {
  display: none;
}

/* Links
   ========================================================================== */

/**
 * Remove the gray background color from active links in IE 10.
 */

a {
  background-color: transparent;
}

/**
 * Improve readability of focused elements when they are also in an
 * active/hover state.
 */

a:active,
a:hover {
  outline: 0;
}

/* Text-level semantics
   ========================================================================== */

/**
 * Address styling not present in IE 8/9/10/11, Safari, and Chrome.
 */

abbr[title] {
  border-bottom: 1px dotted;
}

/**
 * Address style set to `bolder` in Firefox 4+, Safari, and Chrome.
 */

b,
strong {
  font-weight: bold;
}

/**
 * Address styling not present in Safari and Chrome.
 */

dfn {
  font-style: italic;
}

/**
 * Address variable `h1` font-size and margin within `section` and `article`
 * contexts in Firefox 4+, Safari, and Chrome.
 */

h1 {
  font-size: 2em;
  margin: 0.67em 0;
}

/**
 * Address styling not present in IE 8/9.
 */

mark {
  background: #ff0;
  color: #000;
}

/**
 * Address inconsistent and variable font size in all browsers.
 */

small {
  font-size: 80%;
}

/**
 * Prevent `sub` and `sup` affecting `line-height` in all browsers.
 */

sub,
sup {
  font-size: 75%;
  line-height: 0;
  position: relative;
  vertical-align: baseline;
}

sup {
  top: -0.5em;
}

sub {
  bottom: -0.25em;
}

/* Embedded content
   ========================================================================== */

/**
 * Remove border when inside `a` element in IE 8/9/10.
 */

img {
  border: 0;
}

/**
 * Correct overflow not hidden in IE 9/10/11.
 */

svg:not(:root) {
  overflow: hidden;
}

/* Grouping content
   ========================================================================== */

/**
 * Address margin not present in IE 8/9 and Safari.
 */

figure {
  margin: 1em 40px;
}

/**
 * Address differences between Firefox and other browsers.
 */

hr {
  box-sizing: content-box;
  height: 0;
}

/**
 * Contain overflow in all browsers.
 */

pre {
  overflow: auto;
}

/**
 * Address odd `em`-unit font size rendering in all browsers.
 */

code,
kbd,
pre,
samp {
  font-family: monospace, monospace;
  font-size: 1em;
}

/* Forms
   ========================================================================== */

/**
 * Known limitation: by default, Chrome and Safari on OS X allow very limited
 * styling of `select`, unless a `border` property is set.
 */

/**
 * 1. Correct color not being inherited.
 *    Known issue: affects color of disabled elements.
 * 2. Correct font properties not being inherited.
 * 3. Address margins set differently in Firefox 4+, Safari, and Chrome.
 */

button,
input,
optgroup,
select,
textarea {
  color: inherit; /* 1 */
  font: inherit; /* 2 */
  margin: 0; /* 3 */
}

/**
 * Address `overflow` set to `hidden` in IE 8/9/10/11.
 */

button {
  overflow: visible;
}

/**
 * Address inconsistent `text-transform` inheritance for `button` and `select`.
 * All other form control elements do not inherit `text-transform` values.
 * Correct `button` style inheritance in Firefox, IE 8/9/10/11, and Opera.
 * Correct `select` style inheritance in Firefox.
 */

button,
select {
  text-transform: none;
}

/**
 * 1. Avoid the WebKit bug in Android 4.0.* where (2) destroys native `audio`
 *    and `video` controls.
 * 2. Correct inability to style clickable `input` types in iOS.
 * 3. Improve usability and consistency of cursor style between image-type
 *    `input` and others.
 */

button,
html input[type="button"], /* 1 */
input[type="reset"],
input[type="submit"] {
  -webkit-appearance: button; /* 2 */
  cursor: pointer; /* 3 */
}

/**
 * Re-set default cursor for disabled elements.
 */

button[disabled],
html input[disabled] {
  cursor: default;
}

/**
 * Remove inner padding and border in Firefox 4+.
 */

button::-moz-focus-inner,
input::-moz-focus-inner {
  border: 0;
  padding: 0;
}

/**
 * Address Firefox 4+ setting `line-height` on `input` using `!important` in
 * the UA stylesheet.
 */

input {
  line-height: normal;
}

/**
 * It\'s recommended that you don\'t attempt to style these elements.
 * Firefox\'s implementation doesn\'t respect box-sizing, padding, or width.
 *
 * 1. Address box sizing set to `content-box` in IE 8/9/10.
 * 2. Remove excess padding in IE 8/9/10.
 */

input[type="checkbox"],
input[type="radio"] {
  box-sizing: border-box; /* 1 */
  padding: 0; /* 2 */
}

/**
 * Fix the cursor style for Chrome\'s increment/decrement buttons. For certain
 * `font-size` values of the `input`, it causes the cursor style of the
 * decrement button to change from `default` to `text`.
 */

input[type="number"]::-webkit-inner-spin-button,
input[type="number"]::-webkit-outer-spin-button {
  height: auto;
}

/**
 * 1. Address `appearance` set to `searchfield` in Safari and Chrome.
 * 2. Address `box-sizing` set to `border-box` in Safari and Chrome.
 */

input[type="search"] {
  -webkit-appearance: textfield; /* 1 */
  box-sizing: content-box; /* 2 */
}

/**
 * Remove inner padding and search cancel button in Safari and Chrome on OS X.
 * Safari (but not Chrome) clips the cancel button when the search input has
 * padding (and `textfield` appearance).
 */

input[type="search"]::-webkit-search-cancel-button,
input[type="search"]::-webkit-search-decoration {
  -webkit-appearance: none;
}

/**
 * Define consistent border, margin, and padding.
 */

fieldset {
  border: 1px solid #c0c0c0;
  margin: 0 2px;
  padding: 0.35em 0.625em 0.75em;
}

/**
 * 1. Correct `color` not being inherited in IE 8/9/10/11.
 * 2. Remove padding so people aren\'t caught out if they zero out fieldsets.
 */

legend {
  border: 0; /* 1 */
  padding: 0; /* 2 */
}

/**
 * Remove default vertical scrollbar in IE 8/9/10/11.
 */

textarea {
  overflow: auto;
}

/**
 * Don\'t inherit the `font-weight` (applied by a rule above).
 * NOTE: the default cannot safely be changed in Chrome and Safari on OS X.
 */

optgroup {
  font-weight: bold;
}

/* Tables
   ========================================================================== */

/**
 * Remove most spacing between table cells.
 */

table {
  border-collapse: collapse;
  border-spacing: 0;
}

td,
th {
  padding: 0;
}';

        if ($withHtmlTag) {
            $result .= '
</style>
';
        }

        return $result;
    }

    /**
     * Returns the value of a variable.
     *
     * @param string $name The name of the variable.
     * @param mixed $defValue The default value.
     *
     * @return mixed The value of the variable.
     */
    public function getVar($name, $defValue = null) {
        $item = $this->getVarEntryByName($name);
        if (is_object($item)) {
            return $item->value;
        }

        return $defValue;
    }

    /**
     * Returns all variables as array.
     *
     * @param string|\Traversable|array ... List of variables to remove.
     *                                      If an argument is a string, it is used as regular expression pattern.
     *                                      If an argument is an array or traversable it is checked by variable name.
     *                                      If argument list is EMPTY, all variables are returned.
     *
     * @return array The variables.
     */
    public function getVarArray() {
        $result = array();

        foreach ($this->_vars as $v) {
            $add = false;

            if (func_num_args() < 1) {
                // add all
                $add = true;
            }
            else {
                foreach (func_get_args() as $arg) {
                    $predicate = self::getCheckVarNamePredicate($arg);

                    if (false !== $predicate) {
                        if (call_user_func($predicate, $v->name)) {
                            $add = true;
                            break;
                        }
                    }
                }
            }

            if ($add) {
                $result[$v->name] = $v->value;
            }
        }

        // sort keys
        uksort($result, function($x, $y) {
            return strcmp($x, $y);
        });

        return $result;
    }

    private function getVarEntryByName($name) {
        $name = self::getVarName($name);

        foreach ($this->_vars as $v) {
            if ($v->name == $name) {
                return $v;
            }
        }

        return null;
    }

    /**
     * Normalizes a value that should be used as variable name.
     *
     * @param mixed $name The input value.
     *
     * @return string The output value.
     */
    public static function getVarName($name) {
        $result = trim(self::valueToString($name));

        $result = str_ireplace(' ', '_', $result);

        return strtoupper($result);
    }

    /**
     * Checks if a variable is set or not.
     *
     * @param string $name The name to check.
     *
     * @return bool Is set or not.
     */
    public function issetVar($name) {
        return is_object($this->getVarEntryByName($name));
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Whether a offset exists
     * @link http://php.net/manual/en/arrayaccess.offsetexists.php
     * @param mixed $offset <p>
     * An offset to check for.
     * </p>
     * @return boolean true on success or false on failure.
     * </p>
     * <p>
     * The return value will be casted to boolean if non-boolean was returned.
     */
    public function offsetExists($offset)
    {
        return $this->issetVar($offset);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to retrieve
     * @link http://php.net/manual/en/arrayaccess.offsetget.php
     * @param mixed $offset <p>
     * The offset to retrieve.
     * </p>
     * @return mixed Can return all value types.
     */
    public function offsetGet($offset)
    {
        return $this->getVar($offset, null);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to set
     * @link http://php.net/manual/en/arrayaccess.offsetset.php
     * @param mixed $offset <p>
     * The offset to assign the value to.
     * </p>
     * @param mixed $value <p>
     * The value to set.
     * </p>
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        $this->setVar($offset, $value);
    }

    /**
     * (PHP 5 &gt;= 5.0.0)<br/>
     * Offset to unset
     * @link http://php.net/manual/en/arrayaccess.offsetunset.php
     * @param mixed $offset <p>
     * The offset to unset.
     * </p>
     * @return void
     */
    public function offsetUnset($offset)
    {
        $this->unsetVar($offset);
    }

    private static function outputToStream($data, $outputLogic = null) {
        if (is_null($outputLogic)) {
            $outputLogic = function($data) {
                echo $data;
            };
        }

        if (is_resource($outputLogic)) {
            $stream = $outputLogic;

            $outputLogic = function($data) use ($stream) {
                fwrite($stream, $data);
            };
        }

        $outputLogic($data);
        return true;
    }

    private static function parseForHtmlOutput($str) {
        $result = htmlentities(self::valueToString($str));

        $result = str_ireplace("\r", ''      , $result);
        $result = str_ireplace("\t", '    '  , $result);
        $result = str_ireplace(' ' , '&nbsp;', $result);
        $result = str_ireplace("\n", '<br />', $result);

        return $result;
    }

    /**
     * Prints the calling file to console.
     *
     * @return $this
     */
    public function printFile() {
        $e = self::getBacktraceEntryOfCallingLine();

        return $this->write($e['file']);
    }

    /**
     * Prints the calling line to console.
     *
     * @return $this
     */
    public function printLine() {
        $e = self::getBacktraceEntryOfCallingLine();

        return $this->write($e['line']);
    }

    /**
     * Renders and outputs the Javascript code based on the current instance.
     * The code is surrounded by a HTML script tag.
     *
     * @param resource $stream The optional, custom stream to write the data to.
     *
     * @return bool Operation was successful or not.
     */
    public function renderAndOutput($stream = null) {
        self::outputToStream('<script type="text/javascript">', $stream);

        $result = $this->renderAndOutputJavascript($stream);

        self::outputToStream('</script>', $stream);

        return $result;
    }

    /**
     * Renders and outputs the Javascript code based on the current instance
     * if a condition meets.
     * The code is surrounded by a HTML script tag.
     *
     * @param mixed $condition The condition (a callable or value that returns / represents (true)).
     * @param resource $stream The optional, custom stream to write the data to.
     *
     * @return bool Operation was successful or not.
     */
    public function renderAndOutputIf($condition, $stream = null) {
        if (!is_callable($condition)) {
            $value = $condition;

            $condition = function() use ($value) {
                return $value;
            };
        }

        if (call_user_func($condition)) {
            return $this->renderAndOutput($stream);
        }

        return null;
    }

    /**
     * Renders and outputs the Javascript code based on the current instance.
     *
     * @param resource $stream The optional, custom stream to write the data to.
     *
     * @return bool Operation was successful or not.
     */
    public function renderAndOutputJavascript($stream = null) {
        return self::outputToStream($this->renderJavascript(),
                                    $stream);
    }

    /**
     * Renders and outputs the Javascript code based on the current instance
     * if a condition meets.
     *
     * @param mixed $condition The condition (a callable or value that returns / represents (true)).
     * @param resource $stream The optional, custom stream to write the data to.
     *
     * @return bool Operation was successful or not.
     */
    public function renderAndOutputJavascriptIf($condition, $stream = null) {
        if (!is_callable($condition)) {
            $value = $condition;

            $condition = function() use ($value) {
                return $value;
            };
        }

        if (call_user_func($condition)) {
            return $this->renderAndOutputJavascript($stream);
        }

        return null;
    }

    /**
     * Renders and returns the JavaScript code based on the current instance.
     *
     * @return string The generated code.
     */
    public function renderJavascript() {
        $result = '';

        $result .= "(function() {\n";
        $result .= "\n";

        $result .= "var e;\n";
        $result .= "var e1, e2, e3;\n";
        $result .= "var a;\n";
        $result .= "var tn;\n";

        $result .= "var jq = " . json_encode(self::getJQuery()) . ";\n";
        $result .= "var fdJs = " . json_encode(self::getFoundationJs()) . ";\n";
        $result .= "var mJs = " . json_encode(self::getModernizerJs()) . ";\n";
        $result .= "\n";

        $result .= "var nCss = " . json_encode(self::getNormalizeCss()) . ";\n";
        $result .= "var fdCss = " . json_encode(self::getFoundationCss()) . ";\n";
        $result .= "var css = " . json_encode(self::getCss()) . ";\n";
        $result .= "\n";

        $result .= "var p = window.open('', 'phpDeeBuk{$this->getName()}', 'width=800,height=600,resizable=yes,scrollbars=yes');\n";
        $result .= "\n";

        $result .= "p.document.open();\n";
        $result .= "p.document.write('<html class=\"no-js\"><head></head><body></body></html>');\n";
        $result .= "p.document.close();\n";

        $result .= "var headTag = p.document.getElementsByTagName(\"head\")[0];\n";
        $result .= "var bodyTag = p.document.getElementsByTagName(\"body\")[0];\n";

        // <head></head>
        {
            // normalize.css
            $result .= "e = p.document.createElement('style');\n";
            {
                // type="text/css"
                $result .= "a = p.document.createAttribute('type');\n";
                $result .= "a.nodeValue = 'text/css';\n";
                $result .= "e.setAttributeNode(a);\n";

                // CSS code
                $result .= "tn = p.document.createTextNode(nCss);\n";
                $result .= "e.appendChild(tn);\n";
            }
            $result .= "headTag.appendChild(e);\n";

            // foundation.css
            $result .= "e = p.document.createElement('style');\n";
            {
                // type="text/css"
                $result .= "a = p.document.createAttribute('type');\n";
                $result .= "a.nodeValue = 'text/css';\n";
                $result .= "e.setAttributeNode(a);\n";

                // CSS code
                $result .= "tn = p.document.createTextNode(fdCss);\n";
                $result .= "e.appendChild(tn);\n";
            }
            $result .= "headTag.appendChild(e);\n";

            // modanizr.js
            $result .= "e = p.document.createElement('script');\n";
            {
                // type="text/javascript"
                $result .= "a = p.document.createAttribute('type');\n";
                $result .= "a.nodeValue = 'text/javascript';\n";
                $result .= "e.setAttributeNode(a);\n";

                // JS code
                $result .= "tn = p.document.createTextNode(mJs);\n";
                $result .= "e.appendChild(tn);\n";
            }
            $result .= "bodyTag.appendChild(e);\n";
        }

        // <body></body>
        {
            $tabs = array();

            // console
            if (!empty($this->_console)) {
                $tabs[] = $this->createTabEntry('Console', 'getConsoleHtml', true);
            }

            // tests
            if (!empty($this->_asserts)) {
                $tabs[] = $this->createTabEntry('Tests', 'getAssertHtml');
            }

            // add the rest
            foreach ($this->_tabs as $t) {
                $tabs[] = $t;
            }

            // tabs
            {
                // <ul>
                $result .= "e1 = p.document.createElement('ul');\n";
                {
                    // class="tabs"
                    $result .= "a = p.document.createAttribute('class');\n";
                    $result .= "a.nodeValue = 'tabs';\n";
                    $result .= "e1.setAttributeNode(a);\n";

                    // data-tab
                    $result .= "a = p.document.createAttribute('data-tab');\n";
                    $result .= "a.nodeValue = '';\n";
                    $result .= "e1.setAttributeNode(a);\n";
                }
                $result .= "bodyTag.appendChild(e1);\n";

                foreach ($tabs as $i => $tab) {
                    $isActive  = $i < 1;
                    $elementId = sprintf('phpDeeBukPanel-%s', $i + 1);

                    // <li>
                    $result .= "e2 = p.document.createElement('li');\n";
                    {
                        // class="tab-title active"
                        $result .= "a = p.document.createAttribute('class');\n";
                        $result .= "a.nodeValue = 'tab-title" . (!$isActive ? '' : ' active') . "';\n";
                        $result .= "e2.setAttributeNode(a);\n";

                        // role="presentational"
                        $result .= "a = p.document.createAttribute('role');\n";
                        $result .= "a.nodeValue = 'presentational';\n";
                        $result .= "e2.setAttributeNode(a);\n";
                    }
                    $result .= "e1.appendChild(e2);\n";

                    // <li><a></a></li>
                    $result .= "e3 = p.document.createElement('a');\n";
                    {
                        // href="#panel2-1"
                        $result .= "a = p.document.createAttribute('href');\n";
                        $result .= "a.nodeValue = '#{$elementId}';\n";
                        $result .= "e3.setAttributeNode(a);\n";

                        // role="tab"
                        $result .= "a = p.document.createAttribute('role');\n";
                        $result .= "a.nodeValue = 'tab';\n";
                        $result .= "e3.setAttributeNode(a);\n";

                        // tabindex="0"
                        $result .= "a = p.document.createAttribute('tabindex');\n";
                        $result .= "a.nodeValue = '0';\n";
                        $result .= "e3.setAttributeNode(a);\n";

                        // aria-selected="true"
                        $result .= "a = p.document.createAttribute('aria-selected');\n";
                        $result .= "a.nodeValue = '" . (!$isActive ? 'false' : 'true') . "';\n";
                        $result .= "e3.setAttributeNode(a);\n";

                        // controls="panel2-1"
                        $result .= "a = p.document.createAttribute('controls');\n";
                        $result .= "a.nodeValue = '{$elementId}';\n";
                        $result .= "e3.setAttributeNode(a);\n";

                        $result .= "tn = p.document.createTextNode(" . json_encode($tab->title) . ");\n";
                        $result .= "e3.appendChild(tn);\n";
                    }
                    $result .= "e2.appendChild(e3);\n";
                }
            }

            // tabs
            {
                // <div>
                $result .= "e1 = p.document.createElement('div');\n";
                {
                    // class="tabs-content"
                    $result .= "a = p.document.createAttribute('class');\n";
                    $result .= "a.nodeValue = 'tabs-content';\n";
                    $result .= "e1.setAttributeNode(a);\n";
                }
                $result .= "bodyTag.appendChild(e1);\n";

                foreach ($tabs as $i => $tab)
                {
                    $isActive  = $i < 1;
                    $elementId = sprintf('phpDeeBukPanel-%s', $i + 1);

                    // <section>
                    $result .= "e2 = p.document.createElement('section');\n";
                    {
                        // role="tabpanel"
                        $result .= "a = p.document.createAttribute('role');\n";
                        $result .= "a.nodeValue = 'tabpanel';\n";
                        $result .= "e2.setAttributeNode(a);\n";

                        // aria-hidden="false"
                        $result .= "a = p.document.createAttribute('aria-hidden');\n";
                        $result .= "a.nodeValue = '" . (!$isActive ? 'true' : 'false') . "';\n";
                        $result .= "e2.setAttributeNode(a);\n";

                        // class
                        $result .= "a = p.document.createAttribute('class');\n";
                        $result .= "a.nodeValue = 'content" . (!$isActive ? '' : ' active') . "';\n";
                        $result .= "e2.setAttributeNode(a);\n";

                        // id
                        $result .= "a = p.document.createAttribute('id');\n";
                        $result .= "a.nodeValue = '{$elementId}';\n";
                        $result .= "e2.setAttributeNode(a);\n";
                    }
                    $result .= "e1.appendChild(e2);\n";

                    // content
                    {
                        $jsonHtml = json_encode(call_user_func_array($tab->contentProvider,
                                                                     $tab->contentProviderArgs));

                        $result .= "e2.innerHTML = {$jsonHtml};\n";
                    }
                }
            }

            // jquery.js
            $result .= "e = p.document.createElement('script');\n";
            {
                // type="text/javascript"
                $result .= "a = p.document.createAttribute('type');\n";
                $result .= "a.nodeValue = 'text/javascript';\n";
                $result .= "e.setAttributeNode(a);\n";

                // JS code
                $result .= "tn = p.document.createTextNode(jq);\n";
                $result .= "e.appendChild(tn);\n";
            }
            $result .= "bodyTag.appendChild(e);\n";

            // foundation.js
            $result .= "e = p.document.createElement('script');\n";
            {
                // type="text/javascript"
                $result .= "a = p.document.createAttribute('type');\n";
                $result .= "a.nodeValue = 'text/javascript';\n";
                $result .= "e.setAttributeNode(a);\n";

                // JS code
                $result .= "tn = p.document.createTextNode(fdJs);\n";
                $result .= "e.appendChild(tn);\n";
            }
            $result .= "bodyTag.appendChild(e);\n";

            // init Foundation
            $result .= "e = p.document.createElement('script');\n";
            {
                // type="text/javascript"
                $result .= "a = p.document.createAttribute('type');\n";
                $result .= "a.nodeValue = 'text/javascript';\n";
                $result .= "e.setAttributeNode(a);\n";

                // JS code
                $result .= "tn = p.document.createTextNode('$(document).foundation();');\n";
                $result .= "e.appendChild(tn);\n";
            }
            $result .= "bodyTag.appendChild(e);\n";

            // custom CSS
            $result .= "e = p.document.createElement('style');\n";
            {
                // type="text/css"
                $result .= "a = p.document.createAttribute('type');\n";
                $result .= "a.nodeValue = 'text/css';\n";
                $result .= "e.setAttributeNode(a);\n";

                // CSS code
                $result .= "tn = p.document.createTextNode(" . json_encode($this->getCss()) . ");\n";
                $result .= "e.appendChild(tn);\n";
            }
            $result .= "bodyTag.appendChild(e);\n";
        }

        // free memory
        $result .= "\n";
        $result .= "jq = null;\n";
        $result .= "fdJs = null;\n";
        $result .= "mJs = null;\n";
        $result .= "nCss = null;\n";
        $result .= "fdCss = null;\n";
        $result .= "css = null;\n";

        $result .= "\n";

        $result .= "p.focus();\n";

        $result .= "\n";
        $result .= "})();\n";

        return $result;
    }

    /**
     * Resets the state of that instance.
     *
     * @return $this
     */
    public function reset() {
        $this->clr();

        $this->_analyzeObjCounter = 0;
        $this->_analyzeValCounter = 0;
        $this->_assertCounter     = 0;
        $this->_asserts           = array();
        $this->_backtraceCounter  = 0;
        $this->_dumpCounter       = 0;
        $this->_tabs              = array();

        $this->resetColors();
        $this->resetStyles();

        $this->clearVars();

        return $this;
    }

    /**
     * Resets the console colors to default.
     *
     * @return $this
     */
    public function resetColors() {
        $this->_bgColor = null;
        $this->_fgColor = null;

        return $this;
    }

    /**
     * Resets all text styles of the console.
     *
     * @return $this
     */
    public function resetStyles() {
        $this->_isBold      = false;
        $this->_isUnderline = false;

        return $this;
    }

    /**
     * Sets the background color for the console.
     *
     * @param string $cssColor The CSS color.
     *
     * @return $this
     */
    public function setBackgroundColor($cssColor) {
        $cssColor = self::getCssColor($cssColor);
        if (false !== $cssColor) {
            $this->_bgColor = $cssColor;
        }

        return $this;
    }

    /**
     * Defines if console should output text bold or not.
     *
     * @param bool $isBold Output bold or not.
     *
     * @return $this
     */
    public function setBold($isBold = true) {
        $this->_isBold = $isBold ? true : false;

        return $this;
    }

    /**
     * Sets the colors for the console.
     *
     * @param string $foreColor The CSS text color.
     * @param string $backColor The CSS background color.
     *
     * @return $this
     */
    public function setColors($foreColor, $backColor) {
        $this->setForegroundColor($foreColor)
             ->setBackgroundColor($backColor);

        return $this;
    }

    /**
     * Sets the text color for the console.
     *
     * @param string $cssColor The CSS color.
     *
     * @return $this
     */
    public function setForegroundColor($cssColor) {
        $cssColor = self::getCssColor($cssColor);
        if (false !== $cssColor) {
            $this->_fgColor = $cssColor;
        }

        return $this;
    }

    /**
     * Defines if console should output text underline or not.
     *
     * @param bool $isUnderline Output underline or not.
     *
     * @return $this
     */
    public function setUnderline($isUnderline = true) {
        $this->_isUnderline = $isUnderline ? true : false;

        return $this;
    }

    /**
     * Sets a variable.
     *
     * @param string $name The name of the variable.
     * @param mixed $value The value to set.
     *
     * @return $this
     */
    public function setVar($name, $value) {
        $item = $this->getVarEntryByName($name);
        if (!is_object($item)) {
            $item       = new \stdClass();
            $item->name = self::getVarName($name);

            $this->_vars[] = $item;
        }

        $item->value = $value;
        return $this;
    }

    /**
     * Sets a range of variables.
     *
     * @param \Traversable|array $values The values to set. The keys are the names of the variables.
     *
     * @return $this
     */
    public function setVarRange($values) {
        foreach ($values as $n => $v) {
            $this->setVar($n, $v);
        }

        return $this;
    }

    /**
     * Unsets a variable.
     *
     * @param string $name The name of the variable.
     *
     * @return $this
     */
    public function unsetVar($name) {
        $name = self::getVarName($name);
        foreach ($this->_vars as $i => $v) {
            if ($v->name == $name) {
                unset($this->_vars[$i]);
            }
        }

        return $this;
    }

    /**
     * Unsets a range of variables.
     *
     * @param string|\Traversable|array ... List of variables to remove.
     *                                      If an argument is a string, it is used as regular expression pattern.
     *                                      If an argument is an array or traversable it is checked by variable name.
     *
     * @return $this
     */
    public function unsetVarRange() {
        for ($i = 0; $i < func_num_args(); $i++) {
            $nameOrPattern = func_get_arg($i);

            $predicate = self::getCheckVarNamePredicate($nameOrPattern);
            if (false !== $predicate) {
                foreach ($this->_vars as $i => $v) {
                    if (call_user_func($predicate, $v->name)) {
                        // matches => unset
                        unset($this->_vars[$i]);
                    }
                }
            }
        }

        return $this;
    }

    /**
     * Converts a value to a string.
     *
     * @param mixed $value The value to convert.
     * @param bool $noVarExport If true and value cannot be converted to a string, the type name is returned
     *                          instead of doing a var_export() call.
     *
     * @return string The converted value.
     */
    public static function valueToString($value, $noVarExport = false) {
        if (is_string($value)) {
            return $value;
        }

        if (is_null($value)) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'TRUE' : 'FALSE';
        }

        if (!is_array($value)) {
            if (!is_object($value)) {
                $valueToConvert = $value;

                if (settype($valueToConvert, 'string')) {
                    return $valueToConvert;
                }
            }
            else {
                if (method_exists($value, '__toString')) {
                    return $value->__toString();
                }
            }
        }

        if ($noVarExport) {
            if (is_object($value)) {
                return get_class($value);
            }

            return gettype($value);
        }

        return var_export($value, true);
    }

    /**
     * Writes data to the console.
     *
     * @param mixed $str The data to write.
     *
     * @return $this
     */
    public function write($str) {
        $newEntry              = new \stdClass();
        $newEntry->bgColor     = $this->_bgColor;
        $newEntry->fgColor     = $this->_fgColor;
        $newEntry->isBold      = $this->_isBold;
        $newEntry->isUnderline = $this->_isUnderline;
        $newEntry->value       = $str;

        $this->_console[] = $newEntry;
        return $this;
    }

    /**
     * Writes a formatted string.
     *
     * @param string $format The format string.
     *
     * @return $this
     */
    public function writeFormat($format) {
        $args = array();
        foreach(func_get_args() as $a) {
            $args[] = self::valueToString($a);
        }

        return $this->write(call_user_func_array('sprintf',
                                                 $args));
    }

    /**
     * Writes data to the console and appends a new line.
     *
     * @param mixed $str The data to write.
     *
     * @return $this
     */
    public function writeLine($str = null) {
        return $this->write($str)
                    ->write("\n");
    }
}
