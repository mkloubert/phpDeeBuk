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
final class phpDeeBuk {
    /**
     * @var int
     */
    private $_analyzeDumpCounter;
    /**
     * @var int
     */
    private $_analyzeObjCounter;
    /**
     * @var int
     */
    private $_analyzeValCounter;
    /**
     * @var string
     */
    private $_bgColor;
    /**
     * @var string
     */
    private $_console;
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
        if (empty($caption)) {
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
        if (empty($caption)) {
            $caption = sprintf('Dump #%s', ++$this->_analyzeDumpCounter);
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

table.memberTable, table.aboutTable {
    width: 100%;
}

table.memberTable th.memberName,
table.aboutTable td.colLeft {
    width: 15em;
}

pre.debuggerConsole {
    background-color: #000;
    color: #fff;
    height: 80%;
    padding: 0.5em;
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
        if (empty($result)) {
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

        $result .= 'meta.foundation-version{font-family:"/5.5.1/"}meta.foundation-mq-small{font-family:"/only screen/";width:0}meta.foundation-mq-small-only{font-family:"/only screen and (max-width: 40em)/";width:0}meta.foundation-mq-medium{font-family:"/only screen and (min-width:40.063em)/";width:40.063em}meta.foundation-mq-medium-only{font-family:"/only screen and (min-width:40.063em) and (max-width:64em)/";width:40.063em}meta.foundation-mq-large{font-family:"/only screen and (min-width:64.063em)/";width:64.063em}meta.foundation-mq-large-only{font-family:"/only screen and (min-width:64.063em) and (max-width:90em)/";width:64.063em}meta.foundation-mq-xlarge{font-family:"/only screen and (min-width:90.063em)/";width:90.063em}meta.foundation-mq-xlarge-only{font-family:"/only screen and (min-width:90.063em) and (max-width:120em)/";width:90.063em}meta.foundation-mq-xxlarge{font-family:"/only screen and (min-width:120.063em)/";width:120.063em}meta.foundation-data-attribute-namespace{font-family:false}html,body{height:100%}*,*:before,*:after{-webkit-box-sizing:border-box;-moz-box-sizing:border-box;box-sizing:border-box}html,body{font-size:100%}body{background:#fff;color:#222;padding:0;margin:0;font-family:"Helvetica Neue",Helvetica,Roboto,Arial,sans-serif;font-weight:normal;font-style:normal;line-height:1.5;position:relative;cursor:auto}a:hover{cursor:pointer}img{max-width:100%;height:auto}img{-ms-interpolation-mode:bicubic}#map_canvas img,#map_canvas embed,#map_canvas object,.map_canvas img,.map_canvas embed,.map_canvas object{max-width:none !important}.left{float:left !important}.right{float:right !important}.clearfix:before,.clearfix:after{content:" ";display:table}.clearfix:after{clear:both}.hide{display:none}.invisible{visibility:hidden}.antialiased{-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}img{display:inline-block;vertical-align:middle}textarea{height:auto;min-height:50px}select{width:100%}.row{width:100%;margin-left:auto;margin-right:auto;margin-top:0;margin-bottom:0;max-width:62.5rem}.row:before,.row:after{content:" ";display:table}.row:after{clear:both}.row.collapse>.column,.row.collapse>.columns{padding-left:0;padding-right:0}.row.collapse .row{margin-left:0;margin-right:0}.row .row{width:auto;margin-left:-0.9375rem;margin-right:-0.9375rem;margin-top:0;margin-bottom:0;max-width:none}.row .row:before,.row .row:after{content:" ";display:table}.row .row:after{clear:both}.row .row.collapse{width:auto;margin:0;max-width:none}.row .row.collapse:before,.row .row.collapse:after{content:" ";display:table}.row .row.collapse:after{clear:both}.column,.columns{padding-left:0.9375rem;padding-right:0.9375rem;width:100%;float:left}[class*="column"]+[class*="column"]:last-child{float:right}[class*="column"]+[class*="column"].end{float:left}@media only screen{.small-push-0{position:relative;left:0%;right:auto}.small-pull-0{position:relative;right:0%;left:auto}.small-push-1{position:relative;left:8.33333%;right:auto}.small-pull-1{position:relative;right:8.33333%;left:auto}.small-push-2{position:relative;left:16.66667%;right:auto}.small-pull-2{position:relative;right:16.66667%;left:auto}.small-push-3{position:relative;left:25%;right:auto}.small-pull-3{position:relative;right:25%;left:auto}.small-push-4{position:relative;left:33.33333%;right:auto}.small-pull-4{position:relative;right:33.33333%;left:auto}.small-push-5{position:relative;left:41.66667%;right:auto}.small-pull-5{position:relative;right:41.66667%;left:auto}.small-push-6{position:relative;left:50%;right:auto}.small-pull-6{position:relative;right:50%;left:auto}.small-push-7{position:relative;left:58.33333%;right:auto}.small-pull-7{position:relative;right:58.33333%;left:auto}.small-push-8{position:relative;left:66.66667%;right:auto}.small-pull-8{position:relative;right:66.66667%;left:auto}.small-push-9{position:relative;left:75%;right:auto}.small-pull-9{position:relative;right:75%;left:auto}.small-push-10{position:relative;left:83.33333%;right:auto}.small-pull-10{position:relative;right:83.33333%;left:auto}.small-push-11{position:relative;left:91.66667%;right:auto}.small-pull-11{position:relative;right:91.66667%;left:auto}.column,.columns{position:relative;padding-left:0.9375rem;padding-right:0.9375rem;float:left}.small-1{width:8.33333%}.small-2{width:16.66667%}.small-3{width:25%}.small-4{width:33.33333%}.small-5{width:41.66667%}.small-6{width:50%}.small-7{width:58.33333%}.small-8{width:66.66667%}.small-9{width:75%}.small-10{width:83.33333%}.small-11{width:91.66667%}.small-12{width:100%}.small-offset-0{margin-left:0% !important}.small-offset-1{margin-left:8.33333% !important}.small-offset-2{margin-left:16.66667% !important}.small-offset-3{margin-left:25% !important}.small-offset-4{margin-left:33.33333% !important}.small-offset-5{margin-left:41.66667% !important}.small-offset-6{margin-left:50% !important}.small-offset-7{margin-left:58.33333% !important}.small-offset-8{margin-left:66.66667% !important}.small-offset-9{margin-left:75% !important}.small-offset-10{margin-left:83.33333% !important}.small-offset-11{margin-left:91.66667% !important}.small-reset-order{margin-left:0;margin-right:0;left:auto;right:auto;float:left}.column.small-centered,.columns.small-centered{margin-left:auto;margin-right:auto;float:none}.column.small-uncentered,.columns.small-uncentered{margin-left:0;margin-right:0;float:left}.column.small-centered:last-child,.columns.small-centered:last-child{float:none}.column.small-uncentered:last-child,.columns.small-uncentered:last-child{float:left}.column.small-uncentered.opposite,.columns.small-uncentered.opposite{float:right}.row.small-collapse>.column,.row.small-collapse>.columns{padding-left:0;padding-right:0}.row.small-collapse .row{margin-left:0;margin-right:0}.row.small-uncollapse>.column,.row.small-uncollapse>.columns{padding-left:0.9375rem;padding-right:0.9375rem;float:left}}@media only screen and (min-width: 40.063em){.medium-push-0{position:relative;left:0%;right:auto}.medium-pull-0{position:relative;right:0%;left:auto}.medium-push-1{position:relative;left:8.33333%;right:auto}.medium-pull-1{position:relative;right:8.33333%;left:auto}.medium-push-2{position:relative;left:16.66667%;right:auto}.medium-pull-2{position:relative;right:16.66667%;left:auto}.medium-push-3{position:relative;left:25%;right:auto}.medium-pull-3{position:relative;right:25%;left:auto}.medium-push-4{position:relative;left:33.33333%;right:auto}.medium-pull-4{position:relative;right:33.33333%;left:auto}.medium-push-5{position:relative;left:41.66667%;right:auto}.medium-pull-5{position:relative;right:41.66667%;left:auto}.medium-push-6{position:relative;left:50%;right:auto}.medium-pull-6{position:relative;right:50%;left:auto}.medium-push-7{position:relative;left:58.33333%;right:auto}.medium-pull-7{position:relative;right:58.33333%;left:auto}.medium-push-8{position:relative;left:66.66667%;right:auto}.medium-pull-8{position:relative;right:66.66667%;left:auto}.medium-push-9{position:relative;left:75%;right:auto}.medium-pull-9{position:relative;right:75%;left:auto}.medium-push-10{position:relative;left:83.33333%;right:auto}.medium-pull-10{position:relative;right:83.33333%;left:auto}.medium-push-11{position:relative;left:91.66667%;right:auto}.medium-pull-11{position:relative;right:91.66667%;left:auto}.column,.columns{position:relative;padding-left:0.9375rem;padding-right:0.9375rem;float:left}.medium-1{width:8.33333%}.medium-2{width:16.66667%}.medium-3{width:25%}.medium-4{width:33.33333%}.medium-5{width:41.66667%}.medium-6{width:50%}.medium-7{width:58.33333%}.medium-8{width:66.66667%}.medium-9{width:75%}.medium-10{width:83.33333%}.medium-11{width:91.66667%}.medium-12{width:100%}.medium-offset-0{margin-left:0% !important}.medium-offset-1{margin-left:8.33333% !important}.medium-offset-2{margin-left:16.66667% !important}.medium-offset-3{margin-left:25% !important}.medium-offset-4{margin-left:33.33333% !important}.medium-offset-5{margin-left:41.66667% !important}.medium-offset-6{margin-left:50% !important}.medium-offset-7{margin-left:58.33333% !important}.medium-offset-8{margin-left:66.66667% !important}.medium-offset-9{margin-left:75% !important}.medium-offset-10{margin-left:83.33333% !important}.medium-offset-11{margin-left:91.66667% !important}.medium-reset-order{margin-left:0;margin-right:0;left:auto;right:auto;float:left}.column.medium-centered,.columns.medium-centered{margin-left:auto;margin-right:auto;float:none}.column.medium-uncentered,.columns.medium-uncentered{margin-left:0;margin-right:0;float:left}.column.medium-centered:last-child,.columns.medium-centered:last-child{float:none}.column.medium-uncentered:last-child,.columns.medium-uncentered:last-child{float:left}.column.medium-uncentered.opposite,.columns.medium-uncentered.opposite{float:right}.row.medium-collapse>.column,.row.medium-collapse>.columns{padding-left:0;padding-right:0}.row.medium-collapse .row{margin-left:0;margin-right:0}.row.medium-uncollapse>.column,.row.medium-uncollapse>.columns{padding-left:0.9375rem;padding-right:0.9375rem;float:left}.push-0{position:relative;left:0%;right:auto}.pull-0{position:relative;right:0%;left:auto}.push-1{position:relative;left:8.33333%;right:auto}.pull-1{position:relative;right:8.33333%;left:auto}.push-2{position:relative;left:16.66667%;right:auto}.pull-2{position:relative;right:16.66667%;left:auto}.push-3{position:relative;left:25%;right:auto}.pull-3{position:relative;right:25%;left:auto}.push-4{position:relative;left:33.33333%;right:auto}.pull-4{position:relative;right:33.33333%;left:auto}.push-5{position:relative;left:41.66667%;right:auto}.pull-5{position:relative;right:41.66667%;left:auto}.push-6{position:relative;left:50%;right:auto}.pull-6{position:relative;right:50%;left:auto}.push-7{position:relative;left:58.33333%;right:auto}.pull-7{position:relative;right:58.33333%;left:auto}.push-8{position:relative;left:66.66667%;right:auto}.pull-8{position:relative;right:66.66667%;left:auto}.push-9{position:relative;left:75%;right:auto}.pull-9{position:relative;right:75%;left:auto}.push-10{position:relative;left:83.33333%;right:auto}.pull-10{position:relative;right:83.33333%;left:auto}.push-11{position:relative;left:91.66667%;right:auto}.pull-11{position:relative;right:91.66667%;left:auto}}@media only screen and (min-width: 64.063em){.large-push-0{position:relative;left:0%;right:auto}.large-pull-0{position:relative;right:0%;left:auto}.large-push-1{position:relative;left:8.33333%;right:auto}.large-pull-1{position:relative;right:8.33333%;left:auto}.large-push-2{position:relative;left:16.66667%;right:auto}.large-pull-2{position:relative;right:16.66667%;left:auto}.large-push-3{position:relative;left:25%;right:auto}.large-pull-3{position:relative;right:25%;left:auto}.large-push-4{position:relative;left:33.33333%;right:auto}.large-pull-4{position:relative;right:33.33333%;left:auto}.large-push-5{position:relative;left:41.66667%;right:auto}.large-pull-5{position:relative;right:41.66667%;left:auto}.large-push-6{position:relative;left:50%;right:auto}.large-pull-6{position:relative;right:50%;left:auto}.large-push-7{position:relative;left:58.33333%;right:auto}.large-pull-7{position:relative;right:58.33333%;left:auto}.large-push-8{position:relative;left:66.66667%;right:auto}.large-pull-8{position:relative;right:66.66667%;left:auto}.large-push-9{position:relative;left:75%;right:auto}.large-pull-9{position:relative;right:75%;left:auto}.large-push-10{position:relative;left:83.33333%;right:auto}.large-pull-10{position:relative;right:83.33333%;left:auto}.large-push-11{position:relative;left:91.66667%;right:auto}.large-pull-11{position:relative;right:91.66667%;left:auto}.column,.columns{position:relative;padding-left:0.9375rem;padding-right:0.9375rem;float:left}.large-1{width:8.33333%}.large-2{width:16.66667%}.large-3{width:25%}.large-4{width:33.33333%}.large-5{width:41.66667%}.large-6{width:50%}.large-7{width:58.33333%}.large-8{width:66.66667%}.large-9{width:75%}.large-10{width:83.33333%}.large-11{width:91.66667%}.large-12{width:100%}.large-offset-0{margin-left:0% !important}.large-offset-1{margin-left:8.33333% !important}.large-offset-2{margin-left:16.66667% !important}.large-offset-3{margin-left:25% !important}.large-offset-4{margin-left:33.33333% !important}.large-offset-5{margin-left:41.66667% !important}.large-offset-6{margin-left:50% !important}.large-offset-7{margin-left:58.33333% !important}.large-offset-8{margin-left:66.66667% !important}.large-offset-9{margin-left:75% !important}.large-offset-10{margin-left:83.33333% !important}.large-offset-11{margin-left:91.66667% !important}.large-reset-order{margin-left:0;margin-right:0;left:auto;right:auto;float:left}.column.large-centered,.columns.large-centered{margin-left:auto;margin-right:auto;float:none}.column.large-uncentered,.columns.large-uncentered{margin-left:0;margin-right:0;float:left}.column.large-centered:last-child,.columns.large-centered:last-child{float:none}.column.large-uncentered:last-child,.columns.large-uncentered:last-child{float:left}.column.large-uncentered.opposite,.columns.large-uncentered.opposite{float:right}.row.large-collapse>.column,.row.large-collapse>.columns{padding-left:0;padding-right:0}.row.large-collapse .row{margin-left:0;margin-right:0}.row.large-uncollapse>.column,.row.large-uncollapse>.columns{padding-left:0.9375rem;padding-right:0.9375rem;float:left}.push-0{position:relative;left:0%;right:auto}.pull-0{position:relative;right:0%;left:auto}.push-1{position:relative;left:8.33333%;right:auto}.pull-1{position:relative;right:8.33333%;left:auto}.push-2{position:relative;left:16.66667%;right:auto}.pull-2{position:relative;right:16.66667%;left:auto}.push-3{position:relative;left:25%;right:auto}.pull-3{position:relative;right:25%;left:auto}.push-4{position:relative;left:33.33333%;right:auto}.pull-4{position:relative;right:33.33333%;left:auto}.push-5{position:relative;left:41.66667%;right:auto}.pull-5{position:relative;right:41.66667%;left:auto}.push-6{position:relative;left:50%;right:auto}.pull-6{position:relative;right:50%;left:auto}.push-7{position:relative;left:58.33333%;right:auto}.pull-7{position:relative;right:58.33333%;left:auto}.push-8{position:relative;left:66.66667%;right:auto}.pull-8{position:relative;right:66.66667%;left:auto}.push-9{position:relative;left:75%;right:auto}.pull-9{position:relative;right:75%;left:auto}.push-10{position:relative;left:83.33333%;right:auto}.pull-10{position:relative;right:83.33333%;left:auto}.push-11{position:relative;left:91.66667%;right:auto}.pull-11{position:relative;right:91.66667%;left:auto}}button,.button{border-style:solid;border-width:0;cursor:pointer;font-family:"Helvetica Neue",Helvetica,Roboto,Arial,sans-serif;font-weight:normal;line-height:normal;margin:0 0 1.25rem;position:relative;text-decoration:none;text-align:center;-webkit-appearance:none;-moz-appearance:none;border-radius:0;display:inline-block;padding-top:1rem;padding-right:2rem;padding-bottom:1.0625rem;padding-left:2rem;font-size:1rem;background-color:#008CBA;border-color:#007095;color:#fff;transition:background-color 300ms ease-out}button:hover,button:focus,.button:hover,.button:focus{background-color:#007095}button:hover,button:focus,.button:hover,.button:focus{color:#fff}button.secondary,.button.secondary{background-color:#e7e7e7;border-color:#b9b9b9;color:#333}button.secondary:hover,button.secondary:focus,.button.secondary:hover,.button.secondary:focus{background-color:#b9b9b9}button.secondary:hover,button.secondary:focus,.button.secondary:hover,.button.secondary:focus{color:#333}button.success,.button.success{background-color:#43AC6A;border-color:#368a55;color:#fff}button.success:hover,button.success:focus,.button.success:hover,.button.success:focus{background-color:#368a55}button.success:hover,button.success:focus,.button.success:hover,.button.success:focus{color:#fff}button.alert,.button.alert{background-color:#f04124;border-color:#cf2a0e;color:#fff}button.alert:hover,button.alert:focus,.button.alert:hover,.button.alert:focus{background-color:#cf2a0e}button.alert:hover,button.alert:focus,.button.alert:hover,.button.alert:focus{color:#fff}button.warning,.button.warning{background-color:#f08a24;border-color:#cf6e0e;color:#fff}button.warning:hover,button.warning:focus,.button.warning:hover,.button.warning:focus{background-color:#cf6e0e}button.warning:hover,button.warning:focus,.button.warning:hover,.button.warning:focus{color:#fff}button.info,.button.info{background-color:#a0d3e8;border-color:#61b6d9;color:#333}button.info:hover,button.info:focus,.button.info:hover,.button.info:focus{background-color:#61b6d9}button.info:hover,button.info:focus,.button.info:hover,.button.info:focus{color:#fff}button.large,.button.large{padding-top:1.125rem;padding-right:2.25rem;padding-bottom:1.1875rem;padding-left:2.25rem;font-size:1.25rem}button.small,.button.small{padding-top:0.875rem;padding-right:1.75rem;padding-bottom:0.9375rem;padding-left:1.75rem;font-size:0.8125rem}button.tiny,.button.tiny{padding-top:0.625rem;padding-right:1.25rem;padding-bottom:0.6875rem;padding-left:1.25rem;font-size:0.6875rem}button.expand,.button.expand{padding-right:0;padding-left:0;width:100%}button.left-align,.button.left-align{text-align:left;text-indent:0.75rem}button.right-align,.button.right-align{text-align:right;padding-right:0.75rem}button.radius,.button.radius{border-radius:3px}button.round,.button.round{border-radius:1000px}button.disabled,button[disabled],.button.disabled,.button[disabled]{background-color:#008CBA;border-color:#007095;color:#fff;cursor:default;opacity:0.7;box-shadow:none}button.disabled:hover,button.disabled:focus,button[disabled]:hover,button[disabled]:focus,.button.disabled:hover,.button.disabled:focus,.button[disabled]:hover,.button[disabled]:focus{background-color:#007095}button.disabled:hover,button.disabled:focus,button[disabled]:hover,button[disabled]:focus,.button.disabled:hover,.button.disabled:focus,.button[disabled]:hover,.button[disabled]:focus{color:#fff}button.disabled:hover,button.disabled:focus,button[disabled]:hover,button[disabled]:focus,.button.disabled:hover,.button.disabled:focus,.button[disabled]:hover,.button[disabled]:focus{background-color:#008CBA}button.disabled.secondary,button[disabled].secondary,.button.disabled.secondary,.button[disabled].secondary{background-color:#e7e7e7;border-color:#b9b9b9;color:#333;cursor:default;opacity:0.7;box-shadow:none}button.disabled.secondary:hover,button.disabled.secondary:focus,button[disabled].secondary:hover,button[disabled].secondary:focus,.button.disabled.secondary:hover,.button.disabled.secondary:focus,.button[disabled].secondary:hover,.button[disabled].secondary:focus{background-color:#b9b9b9}button.disabled.secondary:hover,button.disabled.secondary:focus,button[disabled].secondary:hover,button[disabled].secondary:focus,.button.disabled.secondary:hover,.button.disabled.secondary:focus,.button[disabled].secondary:hover,.button[disabled].secondary:focus{color:#333}button.disabled.secondary:hover,button.disabled.secondary:focus,button[disabled].secondary:hover,button[disabled].secondary:focus,.button.disabled.secondary:hover,.button.disabled.secondary:focus,.button[disabled].secondary:hover,.button[disabled].secondary:focus{background-color:#e7e7e7}button.disabled.success,button[disabled].success,.button.disabled.success,.button[disabled].success{background-color:#43AC6A;border-color:#368a55;color:#fff;cursor:default;opacity:0.7;box-shadow:none}button.disabled.success:hover,button.disabled.success:focus,button[disabled].success:hover,button[disabled].success:focus,.button.disabled.success:hover,.button.disabled.success:focus,.button[disabled].success:hover,.button[disabled].success:focus{background-color:#368a55}button.disabled.success:hover,button.disabled.success:focus,button[disabled].success:hover,button[disabled].success:focus,.button.disabled.success:hover,.button.disabled.success:focus,.button[disabled].success:hover,.button[disabled].success:focus{color:#fff}button.disabled.success:hover,button.disabled.success:focus,button[disabled].success:hover,button[disabled].success:focus,.button.disabled.success:hover,.button.disabled.success:focus,.button[disabled].success:hover,.button[disabled].success:focus{background-color:#43AC6A}button.disabled.alert,button[disabled].alert,.button.disabled.alert,.button[disabled].alert{background-color:#f04124;border-color:#cf2a0e;color:#fff;cursor:default;opacity:0.7;box-shadow:none}button.disabled.alert:hover,button.disabled.alert:focus,button[disabled].alert:hover,button[disabled].alert:focus,.button.disabled.alert:hover,.button.disabled.alert:focus,.button[disabled].alert:hover,.button[disabled].alert:focus{background-color:#cf2a0e}button.disabled.alert:hover,button.disabled.alert:focus,button[disabled].alert:hover,button[disabled].alert:focus,.button.disabled.alert:hover,.button.disabled.alert:focus,.button[disabled].alert:hover,.button[disabled].alert:focus{color:#fff}button.disabled.alert:hover,button.disabled.alert:focus,button[disabled].alert:hover,button[disabled].alert:focus,.button.disabled.alert:hover,.button.disabled.alert:focus,.button[disabled].alert:hover,.button[disabled].alert:focus{background-color:#f04124}button.disabled.warning,button[disabled].warning,.button.disabled.warning,.button[disabled].warning{background-color:#f08a24;border-color:#cf6e0e;color:#fff;cursor:default;opacity:0.7;box-shadow:none}button.disabled.warning:hover,button.disabled.warning:focus,button[disabled].warning:hover,button[disabled].warning:focus,.button.disabled.warning:hover,.button.disabled.warning:focus,.button[disabled].warning:hover,.button[disabled].warning:focus{background-color:#cf6e0e}button.disabled.warning:hover,button.disabled.warning:focus,button[disabled].warning:hover,button[disabled].warning:focus,.button.disabled.warning:hover,.button.disabled.warning:focus,.button[disabled].warning:hover,.button[disabled].warning:focus{color:#fff}button.disabled.warning:hover,button.disabled.warning:focus,button[disabled].warning:hover,button[disabled].warning:focus,.button.disabled.warning:hover,.button.disabled.warning:focus,.button[disabled].warning:hover,.button[disabled].warning:focus{background-color:#f08a24}button.disabled.info,button[disabled].info,.button.disabled.info,.button[disabled].info{background-color:#a0d3e8;border-color:#61b6d9;color:#333;cursor:default;opacity:0.7;box-shadow:none}button.disabled.info:hover,button.disabled.info:focus,button[disabled].info:hover,button[disabled].info:focus,.button.disabled.info:hover,.button.disabled.info:focus,.button[disabled].info:hover,.button[disabled].info:focus{background-color:#61b6d9}button.disabled.info:hover,button.disabled.info:focus,button[disabled].info:hover,button[disabled].info:focus,.button.disabled.info:hover,.button.disabled.info:focus,.button[disabled].info:hover,.button[disabled].info:focus{color:#fff}button.disabled.info:hover,button.disabled.info:focus,button[disabled].info:hover,button[disabled].info:focus,.button.disabled.info:hover,.button.disabled.info:focus,.button[disabled].info:hover,.button[disabled].info:focus{background-color:#a0d3e8}button::-moz-focus-inner{border:0;padding:0}@media only screen and (min-width: 40.063em){button,.button{display:inline-block}}form{margin:0 0 1rem}form .row .row{margin:0 -0.5rem}form .row .row .column,form .row .row .columns{padding:0 0.5rem}form .row .row.collapse{margin:0}form .row .row.collapse .column,form .row .row.collapse .columns{padding:0}form .row .row.collapse input{-webkit-border-bottom-right-radius:0;-webkit-border-top-right-radius:0;border-bottom-right-radius:0;border-top-right-radius:0}form .row input.column,form .row input.columns,form .row textarea.column,form .row textarea.columns{padding-left:0.5rem}label{font-size:0.875rem;color:#4d4d4d;cursor:pointer;display:block;font-weight:normal;line-height:1.5;margin-bottom:0}label.right{float:none !important;text-align:right}label.inline{margin:0 0 1rem 0;padding:0.5625rem 0}label small{text-transform:capitalize;color:#676767}.prefix,.postfix{display:block;position:relative;z-index:2;text-align:center;width:100%;padding-top:0;padding-bottom:0;border-style:solid;border-width:1px;overflow:visible;font-size:0.875rem;height:2.3125rem;line-height:2.3125rem}.postfix.button{padding-left:0;padding-right:0;padding-top:0;padding-bottom:0;text-align:center;border:none}.prefix.button{padding-left:0;padding-right:0;padding-top:0;padding-bottom:0;text-align:center;border:none}.prefix.button.radius{border-radius:0;-webkit-border-bottom-left-radius:3px;-webkit-border-top-left-radius:3px;border-bottom-left-radius:3px;border-top-left-radius:3px}.postfix.button.radius{border-radius:0;-webkit-border-bottom-right-radius:3px;-webkit-border-top-right-radius:3px;border-bottom-right-radius:3px;border-top-right-radius:3px}.prefix.button.round{border-radius:0;-webkit-border-bottom-left-radius:1000px;-webkit-border-top-left-radius:1000px;border-bottom-left-radius:1000px;border-top-left-radius:1000px}.postfix.button.round{border-radius:0;-webkit-border-bottom-right-radius:1000px;-webkit-border-top-right-radius:1000px;border-bottom-right-radius:1000px;border-top-right-radius:1000px}span.prefix,label.prefix{background:#f2f2f2;border-right:none;color:#333;border-color:#ccc}span.postfix,label.postfix{background:#f2f2f2;border-left:none;color:#333;border-color:#ccc}input[type="text"],input[type="password"],input[type="date"],input[type="datetime"],input[type="datetime-local"],input[type="month"],input[type="week"],input[type="email"],input[type="number"],input[type="search"],input[type="tel"],input[type="time"],input[type="url"],input[type="color"],textarea{-webkit-appearance:none;border-radius:0;background-color:#fff;font-family:inherit;border-style:solid;border-width:1px;border-color:#ccc;box-shadow:inset 0 1px 2px rgba(0,0,0,0.1);color:rgba(0,0,0,0.75);display:block;font-size:0.875rem;margin:0 0 1rem 0;padding:0.5rem;height:2.3125rem;width:100%;-webkit-box-sizing:border-box;-moz-box-sizing:border-box;box-sizing:border-box;transition:all 0.15s linear}input[type="text"]:focus,input[type="password"]:focus,input[type="date"]:focus,input[type="datetime"]:focus,input[type="datetime-local"]:focus,input[type="month"]:focus,input[type="week"]:focus,input[type="email"]:focus,input[type="number"]:focus,input[type="search"]:focus,input[type="tel"]:focus,input[type="time"]:focus,input[type="url"]:focus,input[type="color"]:focus,textarea:focus{background:#fafafa;border-color:#999;outline:none}input[type="text"]:disabled,input[type="password"]:disabled,input[type="date"]:disabled,input[type="datetime"]:disabled,input[type="datetime-local"]:disabled,input[type="month"]:disabled,input[type="week"]:disabled,input[type="email"]:disabled,input[type="number"]:disabled,input[type="search"]:disabled,input[type="tel"]:disabled,input[type="time"]:disabled,input[type="url"]:disabled,input[type="color"]:disabled,textarea:disabled{background-color:#ddd;cursor:default}input[type="text"][disabled],input[type="text"][readonly],fieldset[disabled] input[type="text"],input[type="password"][disabled],input[type="password"][readonly],fieldset[disabled] input[type="password"],input[type="date"][disabled],input[type="date"][readonly],fieldset[disabled] input[type="date"],input[type="datetime"][disabled],input[type="datetime"][readonly],fieldset[disabled] input[type="datetime"],input[type="datetime-local"][disabled],input[type="datetime-local"][readonly],fieldset[disabled] input[type="datetime-local"],input[type="month"][disabled],input[type="month"][readonly],fieldset[disabled] input[type="month"],input[type="week"][disabled],input[type="week"][readonly],fieldset[disabled] input[type="week"],input[type="email"][disabled],input[type="email"][readonly],fieldset[disabled] input[type="email"],input[type="number"][disabled],input[type="number"][readonly],fieldset[disabled] input[type="number"],input[type="search"][disabled],input[type="search"][readonly],fieldset[disabled] input[type="search"],input[type="tel"][disabled],input[type="tel"][readonly],fieldset[disabled] input[type="tel"],input[type="time"][disabled],input[type="time"][readonly],fieldset[disabled] input[type="time"],input[type="url"][disabled],input[type="url"][readonly],fieldset[disabled] input[type="url"],input[type="color"][disabled],input[type="color"][readonly],fieldset[disabled] input[type="color"],textarea[disabled],textarea[readonly],fieldset[disabled] textarea{background-color:#ddd;cursor:default}input[type="text"].radius,input[type="password"].radius,input[type="date"].radius,input[type="datetime"].radius,input[type="datetime-local"].radius,input[type="month"].radius,input[type="week"].radius,input[type="email"].radius,input[type="number"].radius,input[type="search"].radius,input[type="tel"].radius,input[type="time"].radius,input[type="url"].radius,input[type="color"].radius,textarea.radius{border-radius:3px}form .row .prefix-radius.row.collapse input,form .row .prefix-radius.row.collapse textarea,form .row .prefix-radius.row.collapse select,form .row .prefix-radius.row.collapse button{border-radius:0;-webkit-border-bottom-right-radius:3px;-webkit-border-top-right-radius:3px;border-bottom-right-radius:3px;border-top-right-radius:3px}form .row .prefix-radius.row.collapse .prefix{border-radius:0;-webkit-border-bottom-left-radius:3px;-webkit-border-top-left-radius:3px;border-bottom-left-radius:3px;border-top-left-radius:3px}form .row .postfix-radius.row.collapse input,form .row .postfix-radius.row.collapse textarea,form .row .postfix-radius.row.collapse select,form .row .postfix-radius.row.collapse button{border-radius:0;-webkit-border-bottom-left-radius:3px;-webkit-border-top-left-radius:3px;border-bottom-left-radius:3px;border-top-left-radius:3px}form .row .postfix-radius.row.collapse .postfix{border-radius:0;-webkit-border-bottom-right-radius:3px;-webkit-border-top-right-radius:3px;border-bottom-right-radius:3px;border-top-right-radius:3px}form .row .prefix-round.row.collapse input,form .row .prefix-round.row.collapse textarea,form .row .prefix-round.row.collapse select,form .row .prefix-round.row.collapse button{border-radius:0;-webkit-border-bottom-right-radius:1000px;-webkit-border-top-right-radius:1000px;border-bottom-right-radius:1000px;border-top-right-radius:1000px}form .row .prefix-round.row.collapse .prefix{border-radius:0;-webkit-border-bottom-left-radius:1000px;-webkit-border-top-left-radius:1000px;border-bottom-left-radius:1000px;border-top-left-radius:1000px}form .row .postfix-round.row.collapse input,form .row .postfix-round.row.collapse textarea,form .row .postfix-round.row.collapse select,form .row .postfix-round.row.collapse button{border-radius:0;-webkit-border-bottom-left-radius:1000px;-webkit-border-top-left-radius:1000px;border-bottom-left-radius:1000px;border-top-left-radius:1000px}form .row .postfix-round.row.collapse .postfix{border-radius:0;-webkit-border-bottom-right-radius:1000px;-webkit-border-top-right-radius:1000px;border-bottom-right-radius:1000px;border-top-right-radius:1000px}input[type="submit"]{-webkit-appearance:none;border-radius:0}textarea[rows]{height:auto}textarea{max-width:100%}select{-webkit-appearance:none !important;border-radius:0;background-color:#FAFAFA;background-image:url(data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZlcnNpb249IjEuMSIgeD0iMTJweCIgeT0iMHB4IiB3aWR0aD0iMjRweCIgaGVpZ2h0PSIzcHgiIHZpZXdCb3g9IjAgMCA2IDMiIGVuYWJsZS1iYWNrZ3JvdW5kPSJuZXcgMCAwIDYgMyIgeG1sOnNwYWNlPSJwcmVzZXJ2ZSI+PHBvbHlnb24gcG9pbnRzPSI1Ljk5MiwwIDIuOTkyLDMgLTAuMDA4LDAgIi8+PC9zdmc+);background-position:100% center;background-repeat:no-repeat;border-style:solid;border-width:1px;border-color:#ccc;padding:0.5rem;font-size:0.875rem;font-family:"Helvetica Neue",Helvetica,Roboto,Arial,sans-serif;color:rgba(0,0,0,0.75);line-height:normal;border-radius:0;height:2.3125rem}select::-ms-expand{display:none}select.radius{border-radius:3px}select:hover{background-color:#f3f3f3;border-color:#999}select:disabled{background-color:#ddd;cursor:default}select[multiple]{height:auto}input[type="file"],input[type="checkbox"],input[type="radio"],select{margin:0 0 1rem 0}input[type="checkbox"]+label,input[type="radio"]+label{display:inline-block;margin-left:0.5rem;margin-right:1rem;margin-bottom:0;vertical-align:baseline}input[type="file"]{width:100%}fieldset{border:1px solid #ddd;padding:1.25rem;margin:1.125rem 0}fieldset legend{font-weight:bold;background:#fff;padding:0 0.1875rem;margin:0;margin-left:-0.1875rem}[data-abide] .error small.error,[data-abide] .error span.error,[data-abide] span.error,[data-abide] small.error{display:block;padding:0.375rem 0.5625rem 0.5625rem;margin-top:-1px;margin-bottom:1rem;font-size:0.75rem;font-weight:normal;font-style:italic;background:#f04124;color:#fff}[data-abide] span.error,[data-abide] small.error{display:none}span.error,small.error{display:block;padding:0.375rem 0.5625rem 0.5625rem;margin-top:-1px;margin-bottom:1rem;font-size:0.75rem;font-weight:normal;font-style:italic;background:#f04124;color:#fff}.error input,.error textarea,.error select{margin-bottom:0}.error input[type="checkbox"],.error input[type="radio"]{margin-bottom:1rem}.error label,.error label.error{color:#f04124}.error small.error{display:block;padding:0.375rem 0.5625rem 0.5625rem;margin-top:-1px;margin-bottom:1rem;font-size:0.75rem;font-weight:normal;font-style:italic;background:#f04124;color:#fff}.error>label>small{color:#676767;background:transparent;padding:0;text-transform:capitalize;font-style:normal;font-size:60%;margin:0;display:inline}.error span.error-message{display:block}input.error,textarea.error,select.error{margin-bottom:0}label.error{color:#f04124}meta.foundation-mq-topbar{font-family:"/only screen and (min-width:40.063em)/";width:40.063em}.contain-to-grid{width:100%;background:#333}.contain-to-grid .top-bar{margin-bottom:0}.fixed{width:100%;left:0;position:fixed;top:0;z-index:99}.fixed.expanded:not(.top-bar){overflow-y:auto;height:auto;width:100%;max-height:100%}.fixed.expanded:not(.top-bar) .title-area{position:fixed;width:100%;z-index:99}.fixed.expanded:not(.top-bar) .top-bar-section{z-index:98;margin-top:2.8125rem}.top-bar{overflow:hidden;height:2.8125rem;line-height:2.8125rem;position:relative;background:#333;margin-bottom:0}.top-bar ul{margin-bottom:0;list-style:none}.top-bar .row{max-width:none}.top-bar form,.top-bar input{margin-bottom:0}.top-bar input{height:1.75rem;padding-top:.35rem;padding-bottom:.35rem;font-size:0.75rem}.top-bar .button,.top-bar button{padding-top:0.4125rem;padding-bottom:0.4125rem;margin-bottom:0;font-size:0.75rem}@media only screen and (max-width: 40em){.top-bar .button,.top-bar button{position:relative;top:-1px}}.top-bar .title-area{position:relative;margin:0}.top-bar .name{height:2.8125rem;margin:0;font-size:16px}.top-bar .name h1,.top-bar .name h2,.top-bar .name h3,.top-bar .name h4,.top-bar .name p,.top-bar .name span{line-height:2.8125rem;font-size:1.0625rem;margin:0}.top-bar .name h1 a,.top-bar .name h2 a,.top-bar .name h3 a,.top-bar .name h4 a,.top-bar .name p a,.top-bar .name span a{font-weight:normal;color:#fff;width:75%;display:block;padding:0 0.9375rem}.top-bar .toggle-topbar{position:absolute;right:0;top:0}.top-bar .toggle-topbar a{color:#fff;text-transform:uppercase;font-size:0.8125rem;font-weight:bold;position:relative;display:block;padding:0 0.9375rem;height:2.8125rem;line-height:2.8125rem}.top-bar .toggle-topbar.menu-icon{top:50%;margin-top:-16px}.top-bar .toggle-topbar.menu-icon a{height:34px;line-height:33px;padding:0 2.5rem 0 0.9375rem;color:#fff;position:relative}.top-bar .toggle-topbar.menu-icon a span::after{content:"";position:absolute;display:block;height:0;top:50%;margin-top:-8px;right:0.9375rem;box-shadow:0 0 0 1px #fff,0 7px 0 1px #fff,0 14px 0 1px #fff;width:16px}.top-bar .toggle-topbar.menu-icon a span:hover:after{box-shadow:0 0 0 1px "",0 7px 0 1px "",0 14px 0 1px ""}.top-bar.expanded{height:auto;background:transparent}.top-bar.expanded .title-area{background:#333}.top-bar.expanded .toggle-topbar a{color:#888}.top-bar.expanded .toggle-topbar a span::after{box-shadow:0 0 0 1px #888,0 7px 0 1px #888,0 14px 0 1px #888}.top-bar-section{left:0;position:relative;width:auto;transition:left 300ms ease-out}.top-bar-section ul{padding:0;width:100%;height:auto;display:block;font-size:16px;margin:0}.top-bar-section .divider,.top-bar-section [role="separator"]{border-top:solid 1px #1a1a1a;clear:both;height:1px;width:100%}.top-bar-section ul li{background:#333}.top-bar-section ul li>a{display:block;width:100%;color:#fff;padding:12px 0 12px 0;padding-left:0.9375rem;font-family:"Helvetica Neue",Helvetica,Roboto,Arial,sans-serif;font-size:0.8125rem;font-weight:normal;text-transform:none}.top-bar-section ul li>a.button{font-size:0.8125rem;padding-right:0.9375rem;padding-left:0.9375rem;background-color:#008CBA;border-color:#007095;color:#fff}.top-bar-section ul li>a.button:hover,.top-bar-section ul li>a.button:focus{background-color:#007095}.top-bar-section ul li>a.button:hover,.top-bar-section ul li>a.button:focus{color:#fff}.top-bar-section ul li>a.button.secondary{background-color:#e7e7e7;border-color:#b9b9b9;color:#333}.top-bar-section ul li>a.button.secondary:hover,.top-bar-section ul li>a.button.secondary:focus{background-color:#b9b9b9}.top-bar-section ul li>a.button.secondary:hover,.top-bar-section ul li>a.button.secondary:focus{color:#333}.top-bar-section ul li>a.button.success{background-color:#43AC6A;border-color:#368a55;color:#fff}.top-bar-section ul li>a.button.success:hover,.top-bar-section ul li>a.button.success:focus{background-color:#368a55}.top-bar-section ul li>a.button.success:hover,.top-bar-section ul li>a.button.success:focus{color:#fff}.top-bar-section ul li>a.button.alert{background-color:#f04124;border-color:#cf2a0e;color:#fff}.top-bar-section ul li>a.button.alert:hover,.top-bar-section ul li>a.button.alert:focus{background-color:#cf2a0e}.top-bar-section ul li>a.button.alert:hover,.top-bar-section ul li>a.button.alert:focus{color:#fff}.top-bar-section ul li>a.button.warning{background-color:#f08a24;border-color:#cf6e0e;color:#fff}.top-bar-section ul li>a.button.warning:hover,.top-bar-section ul li>a.button.warning:focus{background-color:#cf6e0e}.top-bar-section ul li>a.button.warning:hover,.top-bar-section ul li>a.button.warning:focus{color:#fff}.top-bar-section ul li>button{font-size:0.8125rem;padding-right:0.9375rem;padding-left:0.9375rem;background-color:#008CBA;border-color:#007095;color:#fff}.top-bar-section ul li>button:hover,.top-bar-section ul li>button:focus{background-color:#007095}.top-bar-section ul li>button:hover,.top-bar-section ul li>button:focus{color:#fff}.top-bar-section ul li>button.secondary{background-color:#e7e7e7;border-color:#b9b9b9;color:#333}.top-bar-section ul li>button.secondary:hover,.top-bar-section ul li>button.secondary:focus{background-color:#b9b9b9}.top-bar-section ul li>button.secondary:hover,.top-bar-section ul li>button.secondary:focus{color:#333}.top-bar-section ul li>button.success{background-color:#43AC6A;border-color:#368a55;color:#fff}.top-bar-section ul li>button.success:hover,.top-bar-section ul li>button.success:focus{background-color:#368a55}.top-bar-section ul li>button.success:hover,.top-bar-section ul li>button.success:focus{color:#fff}.top-bar-section ul li>button.alert{background-color:#f04124;border-color:#cf2a0e;color:#fff}.top-bar-section ul li>button.alert:hover,.top-bar-section ul li>button.alert:focus{background-color:#cf2a0e}.top-bar-section ul li>button.alert:hover,.top-bar-section ul li>button.alert:focus{color:#fff}.top-bar-section ul li>button.warning{background-color:#f08a24;border-color:#cf6e0e;color:#fff}.top-bar-section ul li>button.warning:hover,.top-bar-section ul li>button.warning:focus{background-color:#cf6e0e}.top-bar-section ul li>button.warning:hover,.top-bar-section ul li>button.warning:focus{color:#fff}.top-bar-section ul li:hover:not(.has-form)>a{background-color:#555;background:#333;color:#fff}.top-bar-section ul li.active>a{background:#008CBA;color:#fff}.top-bar-section ul li.active>a:hover{background:#0078a0;color:#fff}.top-bar-section .has-form{padding:0.9375rem}.top-bar-section .has-dropdown{position:relative}.top-bar-section .has-dropdown>a:after{content:"";display:block;width:0;height:0;border:inset 5px;border-color:transparent transparent transparent rgba(255,255,255,0.4);border-left-style:solid;margin-right:0.9375rem;margin-top:-4.5px;position:absolute;top:50%;right:0}.top-bar-section .has-dropdown.moved{position:static}.top-bar-section .has-dropdown.moved>.dropdown{display:block;position:static !important;height:auto;width:auto;overflow:visible;clip:auto;position:absolute !important;width:100%}.top-bar-section .has-dropdown.moved>a:after{display:none}.top-bar-section .dropdown{padding:0;position:absolute;left:100%;top:0;z-index:99;display:block;position:absolute !important;height:1px;width:1px;overflow:hidden;clip:rect(1px, 1px, 1px, 1px)}.top-bar-section .dropdown li{width:100%;height:auto}.top-bar-section .dropdown li a{font-weight:normal;padding:8px 0.9375rem}.top-bar-section .dropdown li a.parent-link{font-weight:normal}.top-bar-section .dropdown li.title h5,.top-bar-section .dropdown li.parent-link{margin-bottom:0;margin-top:0;font-size:1.125rem}.top-bar-section .dropdown li.title h5 a,.top-bar-section .dropdown li.parent-link a{color:#fff;display:block}.top-bar-section .dropdown li.title h5 a:hover,.top-bar-section .dropdown li.parent-link a:hover{background:none}.top-bar-section .dropdown li.has-form{padding:8px 0.9375rem}.top-bar-section .dropdown li .button,.top-bar-section .dropdown li button{top:auto}.top-bar-section .dropdown label{padding:8px 0.9375rem 2px;margin-bottom:0;text-transform:uppercase;color:#777;font-weight:bold;font-size:0.625rem}.js-generated{display:block}@media only screen and (min-width: 40.063em){.top-bar{background:#333;overflow:visible}.top-bar:before,.top-bar:after{content:" ";display:table}.top-bar:after{clear:both}.top-bar .toggle-topbar{display:none}.top-bar .title-area{float:left}.top-bar .name h1 a,.top-bar .name h2 a,.top-bar .name h3 a,.top-bar .name h4 a,.top-bar .name h5 a,.top-bar .name h6 a{width:auto}.top-bar input,.top-bar .button,.top-bar button{font-size:0.875rem;position:relative;height:1.75rem;top:0.53125rem}.top-bar.expanded{background:#333}.contain-to-grid .top-bar{max-width:62.5rem;margin:0 auto;margin-bottom:0}.top-bar-section{transition:none 0 0;left:0 !important}.top-bar-section ul{width:auto;height:auto !important;display:inline}.top-bar-section ul li{float:left}.top-bar-section ul li .js-generated{display:none}.top-bar-section li.hover>a:not(.button){background-color:#555;background:#333;color:#fff}.top-bar-section li:not(.has-form) a:not(.button){padding:0 0.9375rem;line-height:2.8125rem;background:#333}.top-bar-section li:not(.has-form) a:not(.button):hover{background-color:#555;background:#333}.top-bar-section li.active:not(.has-form) a:not(.button){padding:0 0.9375rem;line-height:2.8125rem;color:#fff;background:#008CBA}.top-bar-section li.active:not(.has-form) a:not(.button):hover{background:#0078a0;color:#fff}.top-bar-section .has-dropdown>a{padding-right:2.1875rem !important}.top-bar-section .has-dropdown>a:after{content:"";display:block;width:0;height:0;border:inset 5px;border-color:rgba(255,255,255,0.4) transparent transparent transparent;border-top-style:solid;margin-top:-2.5px;top:1.40625rem}.top-bar-section .has-dropdown.moved{position:relative}.top-bar-section .has-dropdown.moved>.dropdown{display:block;position:absolute !important;height:1px;width:1px;overflow:hidden;clip:rect(1px, 1px, 1px, 1px)}.top-bar-section .has-dropdown.hover>.dropdown,.top-bar-section .has-dropdown.not-click:hover>.dropdown{display:block;position:static !important;height:auto;width:auto;overflow:visible;clip:auto;position:absolute !important}.top-bar-section .has-dropdown>a:focus+.dropdown{display:block;position:static !important;height:auto;width:auto;overflow:visible;clip:auto;position:absolute !important}.top-bar-section .has-dropdown .dropdown li.has-dropdown>a:after{border:none;content:"\\00bb";top:1rem;margin-top:-1px;right:5px;line-height:1.2}.top-bar-section .dropdown{left:0;top:auto;background:transparent;min-width:100%}.top-bar-section .dropdown li a{color:#fff;line-height:2.8125rem;white-space:nowrap;padding:12px 0.9375rem;background:#333}.top-bar-section .dropdown li:not(.has-form):not(.active)>a:not(.button){color:#fff;background:#333}.top-bar-section .dropdown li:not(.has-form):not(.active):hover>a:not(.button){color:#fff;background-color:#555;background:#333}.top-bar-section .dropdown li label{white-space:nowrap;background:#333}.top-bar-section .dropdown li .dropdown{left:100%;top:0}.top-bar-section>ul>.divider,.top-bar-section>ul>[role="separator"]{border-bottom:none;border-top:none;border-right:solid 1px #4e4e4e;clear:none;height:2.8125rem;width:0}.top-bar-section .has-form{background:#333;padding:0 0.9375rem;height:2.8125rem}.top-bar-section .right li .dropdown{left:auto;right:0}.top-bar-section .right li .dropdown li .dropdown{right:100%}.top-bar-section .left li .dropdown{right:auto;left:0}.top-bar-section .left li .dropdown li .dropdown{left:100%}.no-js .top-bar-section ul li:hover>a{background-color:#555;background:#333;color:#fff}.no-js .top-bar-section ul li:active>a{background:#008CBA;color:#fff}.no-js .top-bar-section .has-dropdown:hover>.dropdown{display:block;position:static !important;height:auto;width:auto;overflow:visible;clip:auto;position:absolute !important}.no-js .top-bar-section .has-dropdown>a:focus+.dropdown{display:block;position:static !important;height:auto;width:auto;overflow:visible;clip:auto;position:absolute !important}}.breadcrumbs{display:block;padding:0.5625rem 0.875rem 0.5625rem;overflow:hidden;margin-left:0;list-style:none;border-style:solid;border-width:1px;background-color:#f4f4f4;border-color:#dcdcdc;border-radius:3px}.breadcrumbs>*{margin:0;float:left;font-size:0.6875rem;line-height:0.6875rem;text-transform:uppercase;color:#008CBA}.breadcrumbs>*:hover a,.breadcrumbs>*:focus a{text-decoration:underline}.breadcrumbs>* a{color:#008CBA}.breadcrumbs>*.current{cursor:default;color:#333}.breadcrumbs>*.current a{cursor:default;color:#333}.breadcrumbs>*.current:hover,.breadcrumbs>*.current:hover a,.breadcrumbs>*.current:focus,.breadcrumbs>*.current:focus a{text-decoration:none}.breadcrumbs>*.unavailable{color:#999}.breadcrumbs>*.unavailable a{color:#999}.breadcrumbs>*.unavailable:hover,.breadcrumbs>*.unavailable:hover a,.breadcrumbs>*.unavailable:focus,.breadcrumbs>*.unavailable a:focus{text-decoration:none;color:#999;cursor:not-allowed}.breadcrumbs>*:before{content:"/";color:#aaa;margin:0 0.75rem;position:relative;top:1px}.breadcrumbs>*:first-child:before{content:" ";margin:0}[aria-label="breadcrumbs"] [aria-hidden="true"]:after{content:"/"}.alert-box{border-style:solid;border-width:1px;display:block;font-weight:normal;margin-bottom:1.25rem;position:relative;padding:0.875rem 1.5rem 0.875rem 0.875rem;font-size:0.8125rem;transition:opacity 300ms ease-out;background-color:#008CBA;border-color:#0078a0;color:#fff}.alert-box .close{font-size:1.375rem;padding:0 6px 4px;line-height:.9;position:absolute;top:50%;margin-top:-0.6875rem;right:0.25rem;color:#333;opacity:0.3;background:inherit}.alert-box .close:hover,.alert-box .close:focus{opacity:0.5}.alert-box.radius{border-radius:3px}.alert-box.round{border-radius:1000px}.alert-box.success{background-color:#43AC6A;border-color:#3a945b;color:#fff}.alert-box.alert{background-color:#f04124;border-color:#de2d0f;color:#fff}.alert-box.secondary{background-color:#e7e7e7;border-color:#c7c7c7;color:#4f4f4f}.alert-box.warning{background-color:#f08a24;border-color:#de770f;color:#fff}.alert-box.info{background-color:#a0d3e8;border-color:#74bfdd;color:#4f4f4f}.alert-box.alert-close{opacity:0}.inline-list{margin:0 auto 1.0625rem auto;margin-left:-1.375rem;margin-right:0;padding:0;list-style:none;overflow:hidden}.inline-list>li{list-style:none;float:left;margin-left:1.375rem;display:block}.inline-list>li>*{display:block}.button-group{list-style:none;margin:0;left:0}.button-group:before,.button-group:after{content:" ";display:table}.button-group:after{clear:both}.button-group.even-2 li{margin:0 -2px;display:inline-block;width:50%}.button-group.even-2 li>button,.button-group.even-2 li .button{border-left:1px solid;border-color:rgba(255,255,255,0.5)}.button-group.even-2 li:first-child button,.button-group.even-2 li:first-child .button{border-left:0}.button-group.even-2 li button,.button-group.even-2 li .button{width:100%}.button-group.even-3 li{margin:0 -2px;display:inline-block;width:33.33333%}.button-group.even-3 li>button,.button-group.even-3 li .button{border-left:1px solid;border-color:rgba(255,255,255,0.5)}.button-group.even-3 li:first-child button,.button-group.even-3 li:first-child .button{border-left:0}.button-group.even-3 li button,.button-group.even-3 li .button{width:100%}.button-group.even-4 li{margin:0 -2px;display:inline-block;width:25%}.button-group.even-4 li>button,.button-group.even-4 li .button{border-left:1px solid;border-color:rgba(255,255,255,0.5)}.button-group.even-4 li:first-child button,.button-group.even-4 li:first-child .button{border-left:0}.button-group.even-4 li button,.button-group.even-4 li .button{width:100%}.button-group.even-5 li{margin:0 -2px;display:inline-block;width:20%}.button-group.even-5 li>button,.button-group.even-5 li .button{border-left:1px solid;border-color:rgba(255,255,255,0.5)}.button-group.even-5 li:first-child button,.button-group.even-5 li:first-child .button{border-left:0}.button-group.even-5 li button,.button-group.even-5 li .button{width:100%}.button-group.even-6 li{margin:0 -2px;display:inline-block;width:16.66667%}.button-group.even-6 li>button,.button-group.even-6 li .button{border-left:1px solid;border-color:rgba(255,255,255,0.5)}.button-group.even-6 li:first-child button,.button-group.even-6 li:first-child .button{border-left:0}.button-group.even-6 li button,.button-group.even-6 li .button{width:100%}.button-group.even-7 li{margin:0 -2px;display:inline-block;width:14.28571%}.button-group.even-7 li>button,.button-group.even-7 li .button{border-left:1px solid;border-color:rgba(255,255,255,0.5)}.button-group.even-7 li:first-child button,.button-group.even-7 li:first-child .button{border-left:0}.button-group.even-7 li button,.button-group.even-7 li .button{width:100%}.button-group.even-8 li{margin:0 -2px;display:inline-block;width:12.5%}.button-group.even-8 li>button,.button-group.even-8 li .button{border-left:1px solid;border-color:rgba(255,255,255,0.5)}.button-group.even-8 li:first-child button,.button-group.even-8 li:first-child .button{border-left:0}.button-group.even-8 li button,.button-group.even-8 li .button{width:100%}.button-group>li{margin:0 -2px;display:inline-block}.button-group>li>button,.button-group>li .button{border-left:1px solid;border-color:rgba(255,255,255,0.5)}.button-group>li:first-child button,.button-group>li:first-child .button{border-left:0}.button-group.stack>li{margin:0 -2px;display:inline-block;display:block;margin:0;float:none}.button-group.stack>li>button,.button-group.stack>li .button{border-left:1px solid;border-color:rgba(255,255,255,0.5)}.button-group.stack>li:first-child button,.button-group.stack>li:first-child .button{border-left:0}.button-group.stack>li>button,.button-group.stack>li .button{border-top:1px solid;border-color:rgba(255,255,255,0.5);border-left-width:0;margin:0;display:block}.button-group.stack>li>button{width:100%}.button-group.stack>li:first-child button,.button-group.stack>li:first-child .button{border-top:0}.button-group.stack-for-small>li{margin:0 -2px;display:inline-block}.button-group.stack-for-small>li>button,.button-group.stack-for-small>li .button{border-left:1px solid;border-color:rgba(255,255,255,0.5)}.button-group.stack-for-small>li:first-child button,.button-group.stack-for-small>li:first-child .button{border-left:0}@media only screen and (max-width: 40em){.button-group.stack-for-small>li{margin:0 -2px;display:inline-block;display:block;margin:0}.button-group.stack-for-small>li>button,.button-group.stack-for-small>li .button{border-left:1px solid;border-color:rgba(255,255,255,0.5)}.button-group.stack-for-small>li:first-child button,.button-group.stack-for-small>li:first-child .button{border-left:0}.button-group.stack-for-small>li>button,.button-group.stack-for-small>li .button{border-top:1px solid;border-color:rgba(255,255,255,0.5);border-left-width:0;margin:0;display:block}.button-group.stack-for-small>li>button{width:100%}.button-group.stack-for-small>li:first-child button,.button-group.stack-for-small>li:first-child .button{border-top:0}}.button-group.radius>*{margin:0 -2px;display:inline-block}.button-group.radius>*>button,.button-group.radius>* .button{border-left:1px solid;border-color:rgba(255,255,255,0.5)}.button-group.radius>*:first-child button,.button-group.radius>*:first-child .button{border-left:0}.button-group.radius>*,.button-group.radius>*>a,.button-group.radius>*>button,.button-group.radius>*>.button{border-radius:0}.button-group.radius>*:first-child,.button-group.radius>*:first-child>a,.button-group.radius>*:first-child>button,.button-group.radius>*:first-child>.button{-webkit-border-bottom-left-radius:3px;-webkit-border-top-left-radius:3px;border-bottom-left-radius:3px;border-top-left-radius:3px}.button-group.radius>*:last-child,.button-group.radius>*:last-child>a,.button-group.radius>*:last-child>button,.button-group.radius>*:last-child>.button{-webkit-border-bottom-right-radius:3px;-webkit-border-top-right-radius:3px;border-bottom-right-radius:3px;border-top-right-radius:3px}.button-group.radius.stack>*{margin:0 -2px;display:inline-block;display:block;margin:0}.button-group.radius.stack>*>button,.button-group.radius.stack>* .button{border-left:1px solid;border-color:rgba(255,255,255,0.5)}.button-group.radius.stack>*:first-child button,.button-group.radius.stack>*:first-child .button{border-left:0}.button-group.radius.stack>*>button,.button-group.radius.stack>* .button{border-top:1px solid;border-color:rgba(255,255,255,0.5);border-left-width:0;margin:0;display:block}.button-group.radius.stack>*>button{width:100%}.button-group.radius.stack>*:first-child button,.button-group.radius.stack>*:first-child .button{border-top:0}.button-group.radius.stack>*,.button-group.radius.stack>*>a,.button-group.radius.stack>*>button,.button-group.radius.stack>*>.button{border-radius:0}.button-group.radius.stack>*:first-child,.button-group.radius.stack>*:first-child>a,.button-group.radius.stack>*:first-child>button,.button-group.radius.stack>*:first-child>.button{-webkit-top-left-radius:3px;-webkit-top-right-radius:3px;border-top-left-radius:3px;border-top-right-radius:3px}.button-group.radius.stack>*:last-child,.button-group.radius.stack>*:last-child>a,.button-group.radius.stack>*:last-child>button,.button-group.radius.stack>*:last-child>.button{-webkit-bottom-left-radius:3px;-webkit-bottom-right-radius:3px;border-bottom-left-radius:3px;border-bottom-right-radius:3px}@media only screen and (min-width: 40.063em){.button-group.radius.stack-for-small>*{margin:0 -2px;display:inline-block}.button-group.radius.stack-for-small>*>button,.button-group.radius.stack-for-small>* .button{border-left:1px solid;border-color:rgba(255,255,255,0.5)}.button-group.radius.stack-for-small>*:first-child button,.button-group.radius.stack-for-small>*:first-child .button{border-left:0}.button-group.radius.stack-for-small>*,.button-group.radius.stack-for-small>*>a,.button-group.radius.stack-for-small>*>button,.button-group.radius.stack-for-small>*>.button{border-radius:0}.button-group.radius.stack-for-small>*:first-child,.button-group.radius.stack-for-small>*:first-child>a,.button-group.radius.stack-for-small>*:first-child>button,.button-group.radius.stack-for-small>*:first-child>.button{-webkit-border-bottom-left-radius:3px;-webkit-border-top-left-radius:3px;border-bottom-left-radius:3px;border-top-left-radius:3px}.button-group.radius.stack-for-small>*:last-child,.button-group.radius.stack-for-small>*:last-child>a,.button-group.radius.stack-for-small>*:last-child>button,.button-group.radius.stack-for-small>*:last-child>.button{-webkit-border-bottom-right-radius:3px;-webkit-border-top-right-radius:3px;border-bottom-right-radius:3px;border-top-right-radius:3px}}@media only screen and (max-width: 40em){.button-group.radius.stack-for-small>*{margin:0 -2px;display:inline-block;display:block;margin:0}.button-group.radius.stack-for-small>*>button,.button-group.radius.stack-for-small>* .button{border-left:1px solid;border-color:rgba(255,255,255,0.5)}.button-group.radius.stack-for-small>*:first-child button,.button-group.radius.stack-for-small>*:first-child .button{border-left:0}.button-group.radius.stack-for-small>*>button,.button-group.radius.stack-for-small>* .button{border-top:1px solid;border-color:rgba(255,255,255,0.5);border-left-width:0;margin:0;display:block}.button-group.radius.stack-for-small>*>button{width:100%}.button-group.radius.stack-for-small>*:first-child button,.button-group.radius.stack-for-small>*:first-child .button{border-top:0}.button-group.radius.stack-for-small>*,.button-group.radius.stack-for-small>*>a,.button-group.radius.stack-for-small>*>button,.button-group.radius.stack-for-small>*>.button{border-radius:0}.button-group.radius.stack-for-small>*:first-child,.button-group.radius.stack-for-small>*:first-child>a,.button-group.radius.stack-for-small>*:first-child>button,.button-group.radius.stack-for-small>*:first-child>.button{-webkit-top-left-radius:3px;-webkit-top-right-radius:3px;border-top-left-radius:3px;border-top-right-radius:3px}.button-group.radius.stack-for-small>*:last-child,.button-group.radius.stack-for-small>*:last-child>a,.button-group.radius.stack-for-small>*:last-child>button,.button-group.radius.stack-for-small>*:last-child>.button{-webkit-bottom-left-radius:3px;-webkit-bottom-right-radius:3px;border-bottom-left-radius:3px;border-bottom-right-radius:3px}}.button-group.round>*{margin:0 -2px;display:inline-block}.button-group.round>*>button,.button-group.round>* .button{border-left:1px solid;border-color:rgba(255,255,255,0.5)}.button-group.round>*:first-child button,.button-group.round>*:first-child .button{border-left:0}.button-group.round>*,.button-group.round>*>a,.button-group.round>*>button,.button-group.round>*>.button{border-radius:0}.button-group.round>*:first-child,.button-group.round>*:first-child>a,.button-group.round>*:first-child>button,.button-group.round>*:first-child>.button{-webkit-border-bottom-left-radius:1000px;-webkit-border-top-left-radius:1000px;border-bottom-left-radius:1000px;border-top-left-radius:1000px}.button-group.round>*:last-child,.button-group.round>*:last-child>a,.button-group.round>*:last-child>button,.button-group.round>*:last-child>.button{-webkit-border-bottom-right-radius:1000px;-webkit-border-top-right-radius:1000px;border-bottom-right-radius:1000px;border-top-right-radius:1000px}.button-group.round.stack>*{margin:0 -2px;display:inline-block;display:block;margin:0}.button-group.round.stack>*>button,.button-group.round.stack>* .button{border-left:1px solid;border-color:rgba(255,255,255,0.5)}.button-group.round.stack>*:first-child button,.button-group.round.stack>*:first-child .button{border-left:0}.button-group.round.stack>*>button,.button-group.round.stack>* .button{border-top:1px solid;border-color:rgba(255,255,255,0.5);border-left-width:0;margin:0;display:block}.button-group.round.stack>*>button{width:100%}.button-group.round.stack>*:first-child button,.button-group.round.stack>*:first-child .button{border-top:0}.button-group.round.stack>*,.button-group.round.stack>*>a,.button-group.round.stack>*>button,.button-group.round.stack>*>.button{border-radius:0}.button-group.round.stack>*:first-child,.button-group.round.stack>*:first-child>a,.button-group.round.stack>*:first-child>button,.button-group.round.stack>*:first-child>.button{-webkit-top-left-radius:1rem;-webkit-top-right-radius:1rem;border-top-left-radius:1rem;border-top-right-radius:1rem}.button-group.round.stack>*:last-child,.button-group.round.stack>*:last-child>a,.button-group.round.stack>*:last-child>button,.button-group.round.stack>*:last-child>.button{-webkit-bottom-left-radius:1rem;-webkit-bottom-right-radius:1rem;border-bottom-left-radius:1rem;border-bottom-right-radius:1rem}@media only screen and (min-width: 40.063em){.button-group.round.stack-for-small>*{margin:0 -2px;display:inline-block}.button-group.round.stack-for-small>*>button,.button-group.round.stack-for-small>* .button{border-left:1px solid;border-color:rgba(255,255,255,0.5)}.button-group.round.stack-for-small>*:first-child button,.button-group.round.stack-for-small>*:first-child .button{border-left:0}.button-group.round.stack-for-small>*,.button-group.round.stack-for-small>*>a,.button-group.round.stack-for-small>*>button,.button-group.round.stack-for-small>*>.button{border-radius:0}.button-group.round.stack-for-small>*:first-child,.button-group.round.stack-for-small>*:first-child>a,.button-group.round.stack-for-small>*:first-child>button,.button-group.round.stack-for-small>*:first-child>.button{-webkit-border-bottom-left-radius:1000px;-webkit-border-top-left-radius:1000px;border-bottom-left-radius:1000px;border-top-left-radius:1000px}.button-group.round.stack-for-small>*:last-child,.button-group.round.stack-for-small>*:last-child>a,.button-group.round.stack-for-small>*:last-child>button,.button-group.round.stack-for-small>*:last-child>.button{-webkit-border-bottom-right-radius:1000px;-webkit-border-top-right-radius:1000px;border-bottom-right-radius:1000px;border-top-right-radius:1000px}}@media only screen and (max-width: 40em){.button-group.round.stack-for-small>*{margin:0 -2px;display:inline-block;display:block;margin:0}.button-group.round.stack-for-small>*>button,.button-group.round.stack-for-small>* .button{border-left:1px solid;border-color:rgba(255,255,255,0.5)}.button-group.round.stack-for-small>*:first-child button,.button-group.round.stack-for-small>*:first-child .button{border-left:0}.button-group.round.stack-for-small>*>button,.button-group.round.stack-for-small>* .button{border-top:1px solid;border-color:rgba(255,255,255,0.5);border-left-width:0;margin:0;display:block}.button-group.round.stack-for-small>*>button{width:100%}.button-group.round.stack-for-small>*:first-child button,.button-group.round.stack-for-small>*:first-child .button{border-top:0}.button-group.round.stack-for-small>*,.button-group.round.stack-for-small>*>a,.button-group.round.stack-for-small>*>button,.button-group.round.stack-for-small>*>.button{border-radius:0}.button-group.round.stack-for-small>*:first-child,.button-group.round.stack-for-small>*:first-child>a,.button-group.round.stack-for-small>*:first-child>button,.button-group.round.stack-for-small>*:first-child>.button{-webkit-top-left-radius:1rem;-webkit-top-right-radius:1rem;border-top-left-radius:1rem;border-top-right-radius:1rem}.button-group.round.stack-for-small>*:last-child,.button-group.round.stack-for-small>*:last-child>a,.button-group.round.stack-for-small>*:last-child>button,.button-group.round.stack-for-small>*:last-child>.button{-webkit-bottom-left-radius:1rem;-webkit-bottom-right-radius:1rem;border-bottom-left-radius:1rem;border-bottom-right-radius:1rem}}.button-bar:before,.button-bar:after{content:" ";display:table}.button-bar:after{clear:both}.button-bar .button-group{float:left;margin-right:0.625rem}.button-bar .button-group div{overflow:hidden}.panel{border-style:solid;border-width:1px;border-color:#d8d8d8;margin-bottom:1.25rem;padding:1.25rem;background:#f2f2f2;color:#333}.panel>:first-child{margin-top:0}.panel>:last-child{margin-bottom:0}.panel h1,.panel h2,.panel h3,.panel h4,.panel h5,.panel h6,.panel p,.panel li,.panel dl{color:#333}.panel h1,.panel h2,.panel h3,.panel h4,.panel h5,.panel h6{line-height:1;margin-bottom:0.625rem}.panel h1.subheader,.panel h2.subheader,.panel h3.subheader,.panel h4.subheader,.panel h5.subheader,.panel h6.subheader{line-height:1.4}.panel.callout{border-style:solid;border-width:1px;border-color:#b6edff;margin-bottom:1.25rem;padding:1.25rem;background:#ecfaff;color:#333}.panel.callout>:first-child{margin-top:0}.panel.callout>:last-child{margin-bottom:0}.panel.callout h1,.panel.callout h2,.panel.callout h3,.panel.callout h4,.panel.callout h5,.panel.callout h6,.panel.callout p,.panel.callout li,.panel.callout dl{color:#333}.panel.callout h1,.panel.callout h2,.panel.callout h3,.panel.callout h4,.panel.callout h5,.panel.callout h6{line-height:1;margin-bottom:0.625rem}.panel.callout h1.subheader,.panel.callout h2.subheader,.panel.callout h3.subheader,.panel.callout h4.subheader,.panel.callout h5.subheader,.panel.callout h6.subheader{line-height:1.4}.panel.callout a:not(.button){color:#008CBA}.panel.callout a:not(.button):hover,.panel.callout a:not(.button):focus{color:#0078a0}.panel.radius{border-radius:3px}.dropdown.button,button.dropdown{position:relative;outline:none;padding-right:3.5625rem}.dropdown.button::after,button.dropdown::after{position:absolute;content:"";width:0;height:0;display:block;border-style:solid;border-color:#fff transparent transparent transparent;top:50%}.dropdown.button::after,button.dropdown::after{border-width:0.375rem;right:1.40625rem;margin-top:-0.15625rem}.dropdown.button::after,button.dropdown::after{border-color:#fff transparent transparent transparent}.dropdown.button.tiny,button.dropdown.tiny{padding-right:2.625rem}.dropdown.button.tiny:after,button.dropdown.tiny:after{border-width:0.375rem;right:1.125rem;margin-top:-0.125rem}.dropdown.button.tiny::after,button.dropdown.tiny::after{border-color:#fff transparent transparent transparent}.dropdown.button.small,button.dropdown.small{padding-right:3.0625rem}.dropdown.button.small::after,button.dropdown.small::after{border-width:0.4375rem;right:1.3125rem;margin-top:-0.15625rem}.dropdown.button.small::after,button.dropdown.small::after{border-color:#fff transparent transparent transparent}.dropdown.button.large,button.dropdown.large{padding-right:3.625rem}.dropdown.button.large::after,button.dropdown.large::after{border-width:0.3125rem;right:1.71875rem;margin-top:-0.15625rem}.dropdown.button.large::after,button.dropdown.large::after{border-color:#fff transparent transparent transparent}.dropdown.button.secondary:after,button.dropdown.secondary:after{border-color:#333 transparent transparent transparent}.th{line-height:0;display:inline-block;border:solid 4px #fff;max-width:100%;box-shadow:0 0 0 1px rgba(0,0,0,0.2);transition:all 200ms ease-out}.th:hover,.th:focus{box-shadow:0 0 6px 1px rgba(0,140,186,0.5)}.th.radius{border-radius:3px}.toolbar{background:#333;width:100%;font-size:0;display:inline-block}.toolbar.label-bottom .tab .tab-content i,.toolbar.label-bottom .tab .tab-content img{margin-bottom:10px}.toolbar.label-right .tab .tab-content i,.toolbar.label-right .tab .tab-content img{margin-right:10px;display:inline-block}.toolbar.label-right .tab .tab-content label{display:inline-block}.toolbar.vertical.label-right .tab .tab-content{text-align:left}.toolbar.vertical{height:100%;width:auto}.toolbar.vertical .tab{width:auto;margin:auto;float:none}.toolbar .tab{text-align:center;width:25%;margin:0 auto;display:block;padding:20px;float:left}.toolbar .tab:hover{background:rgba(255,255,255,0.1)}.toolbar .tab-content{font-size:16px;text-align:center}.toolbar .tab-content label{color:#ccc}.toolbar .tab-content i{font-size:30px;display:block;margin:0 auto;color:#ccc;vertical-align:middle}.toolbar .tab-content img{width:30px;height:30px;display:block;margin:0 auto}.pricing-table{border:solid 1px #ddd;margin-left:0;margin-bottom:1.25rem}.pricing-table *{list-style:none;line-height:1}.pricing-table .title{background-color:#333;padding:0.9375rem 1.25rem;text-align:center;color:#eee;font-weight:normal;font-size:1rem;font-family:"Helvetica Neue",Helvetica,Roboto,Arial,sans-serif}.pricing-table .price{background-color:#F6F6F6;padding:0.9375rem 1.25rem;text-align:center;color:#333;font-weight:normal;font-size:2rem;font-family:"Helvetica Neue",Helvetica,Roboto,Arial,sans-serif}.pricing-table .description{background-color:#fff;padding:0.9375rem;text-align:center;color:#777;font-size:0.75rem;font-weight:normal;line-height:1.4;border-bottom:dotted 1px #ddd}.pricing-table .bullet-item{background-color:#fff;padding:0.9375rem;text-align:center;color:#333;font-size:0.875rem;font-weight:normal;border-bottom:dotted 1px #ddd}.pricing-table .cta-button{background-color:#fff;text-align:center;padding:1.25rem 1.25rem 0}@-webkit-keyframes rotate{from{-webkit-transform:rotate(0deg)}to{-webkit-transform:rotate(360deg)}}@-moz-keyframes rotate{from{-moz-transform:rotate(0deg)}to{-moz-transform:rotate(360deg)}}@-o-keyframes rotate{from{-o-transform:rotate(0deg)}to{-o-transform:rotate(360deg)}}@keyframes rotate{from{transform:rotate(0deg)}to{transform:rotate(360deg)}}.slideshow-wrapper{position:relative}.slideshow-wrapper ul{list-style-type:none;margin:0}.slideshow-wrapper ul li,.slideshow-wrapper ul li .orbit-caption{display:none}.slideshow-wrapper ul li:first-child{display:block}.slideshow-wrapper .orbit-container{background-color:transparent}.slideshow-wrapper .orbit-container li{display:block}.slideshow-wrapper .orbit-container li .orbit-caption{display:block}.slideshow-wrapper .orbit-container .orbit-bullets li{display:inline-block}.slideshow-wrapper .preloader{display:block;width:40px;height:40px;position:absolute;top:50%;left:50%;margin-top:-20px;margin-left:-20px;border:solid 3px;border-color:#555 #fff;border-radius:1000px;animation-name:rotate;animation-duration:1.5s;animation-iteration-count:infinite;animation-timing-function:linear}.orbit-container{overflow:hidden;width:100%;position:relative;background:none}.orbit-container .orbit-slides-container{list-style:none;margin:0;padding:0;position:relative;-webkit-transform:translateZ(0)}.orbit-container .orbit-slides-container img{display:block;max-width:100%}.orbit-container .orbit-slides-container>*{position:absolute;top:0;width:100%;margin-left:100%}.orbit-container .orbit-slides-container>*:first-child{margin-left:0}.orbit-container .orbit-slides-container>* .orbit-caption{position:absolute;bottom:0;background-color:rgba(51,51,51,0.8);color:#fff;width:100%;padding:0.625rem 0.875rem;font-size:0.875rem}.orbit-container .orbit-slide-number{position:absolute;top:10px;left:10px;font-size:12px;color:#fff;background:transparent;z-index:10}.orbit-container .orbit-slide-number span{font-weight:700;padding:0.3125rem}.orbit-container .orbit-timer{position:absolute;top:12px;right:10px;height:6px;width:100px;z-index:10}.orbit-container .orbit-timer .orbit-progress{height:3px;background-color:rgba(255,255,255,0.3);display:block;width:0;position:relative;right:20px;top:5px}.orbit-container .orbit-timer>span{display:none;position:absolute;top:0;right:0;width:11px;height:14px;border:solid 4px #fff;border-top:none;border-bottom:none}.orbit-container .orbit-timer.paused>span{right:-4px;top:0;width:11px;height:14px;border:inset 8px;border-left-style:solid;border-color:transparent;border-left-color:#fff}.orbit-container .orbit-timer.paused>span.dark{border-left-color:#333}.orbit-container:hover .orbit-timer>span{display:block}.orbit-container .orbit-prev,.orbit-container .orbit-next{position:absolute;top:45%;margin-top:-25px;width:36px;height:60px;line-height:50px;color:white;background-color:transparent;text-indent:-9999px !important;z-index:10}.orbit-container .orbit-prev:hover,.orbit-container .orbit-next:hover{background-color:rgba(0,0,0,0.3)}.orbit-container .orbit-prev>span,.orbit-container .orbit-next>span{position:absolute;top:50%;margin-top:-10px;display:block;width:0;height:0;border:inset 10px}.orbit-container .orbit-prev{left:0}.orbit-container .orbit-prev>span{border-right-style:solid;border-color:transparent;border-right-color:#fff}.orbit-container .orbit-prev:hover>span{border-right-color:#fff}.orbit-container .orbit-next{right:0}.orbit-container .orbit-next>span{border-color:transparent;border-left-style:solid;border-left-color:#fff;left:50%;margin-left:-4px}.orbit-container .orbit-next:hover>span{border-left-color:#fff}.orbit-bullets-container{text-align:center}.orbit-bullets{margin:0 auto 30px auto;overflow:hidden;position:relative;top:10px;float:none;text-align:center;display:block}.orbit-bullets li{cursor:pointer;display:inline-block;width:0.5625rem;height:0.5625rem;background:#ccc;float:none;margin-right:6px;border-radius:1000px}.orbit-bullets li.active{background:#999}.orbit-bullets li:last-child{margin-right:0}.touch .orbit-container .orbit-prev,.touch .orbit-container .orbit-next{display:none}.touch .orbit-bullets{display:none}@media only screen and (min-width: 40.063em){.touch .orbit-container .orbit-prev,.touch .orbit-container .orbit-next{display:inherit}.touch .orbit-bullets{display:block}}@media only screen and (max-width: 40em){.orbit-stack-on-small .orbit-slides-container{height:auto !important}.orbit-stack-on-small .orbit-slides-container>*{position:relative;margin:0 !important;opacity:1 !important}.orbit-stack-on-small .orbit-slide-number{display:none}.orbit-timer{display:none}.orbit-next,.orbit-prev{display:none}.orbit-bullets{display:none}}[data-magellan-expedition],[data-magellan-expedition-clone]{background:#fff;z-index:50;min-width:100%;padding:10px}[data-magellan-expedition] .sub-nav,[data-magellan-expedition-clone] .sub-nav{margin-bottom:0}[data-magellan-expedition] .sub-nav dd,[data-magellan-expedition-clone] .sub-nav dd{margin-bottom:0}[data-magellan-expedition] .sub-nav a,[data-magellan-expedition-clone] .sub-nav a{line-height:1.8em}.icon-bar{width:100%;font-size:0;display:inline-block;background:#333}.icon-bar>*{text-align:center;font-size:1rem;width:25%;margin:0 auto;display:block;padding:1.25rem;float:left}.icon-bar>* i,.icon-bar>* img{display:block;margin:0 auto}.icon-bar>* i+label,.icon-bar>* img+label{margin-top:.0625rem}.icon-bar>* i{font-size:1.875rem;vertical-align:middle}.icon-bar>* img{width:1.875rem;height:1.875rem}.icon-bar.label-right>* i,.icon-bar.label-right>* img{margin:0 .0625rem 0 0;display:inline-block}.icon-bar.label-right>* i+label,.icon-bar.label-right>* img+label{margin-top:0}.icon-bar.label-right>* label{display:inline-block}.icon-bar.vertical.label-right>*{text-align:left}.icon-bar.vertical,.icon-bar.small-vertical{height:100%;width:auto}.icon-bar.vertical .item,.icon-bar.small-vertical .item{width:auto;margin:auto;float:none}@media only screen and (min-width: 40.063em){.icon-bar.medium-vertical{height:100%;width:auto}.icon-bar.medium-vertical .item{width:auto;margin:auto;float:none}}@media only screen and (min-width: 64.063em){.icon-bar.large-vertical{height:100%;width:auto}.icon-bar.large-vertical .item{width:auto;margin:auto;float:none}}.icon-bar>*{font-size:1rem;padding:1.25rem}.icon-bar>* i+label,.icon-bar>* img+label{margin-top:.0625rem}.icon-bar>* i{font-size:1.875rem}.icon-bar>* img{width:1.875rem;height:1.875rem}.icon-bar>* label{color:#fff}.icon-bar>* i{color:#fff}.icon-bar>a:hover{background:#008CBA}.icon-bar>a:hover label{color:#fff}.icon-bar>a:hover i{color:#fff}.icon-bar>a.active{background:#008CBA}.icon-bar>a.active label{color:#fff}.icon-bar>a.active i{color:#fff}.icon-bar .item.disabled{opacity:0.7;cursor:not-allowed;pointer-events:none}.icon-bar .item.disabled>*{opacity:0.7;cursor:not-allowed}.icon-bar.two-up .item{width:50%}.icon-bar.two-up.vertical .item,.icon-bar.two-up.small-vertical .item{width:auto}@media only screen and (min-width: 40.063em){.icon-bar.two-up.medium-vertical .item{width:auto}}@media only screen and (min-width: 64.063em){.icon-bar.two-up.large-vertical .item{width:auto}}.icon-bar.three-up .item{width:33.3333%}.icon-bar.three-up.vertical .item,.icon-bar.three-up.small-vertical .item{width:auto}@media only screen and (min-width: 40.063em){.icon-bar.three-up.medium-vertical .item{width:auto}}@media only screen and (min-width: 64.063em){.icon-bar.three-up.large-vertical .item{width:auto}}.icon-bar.four-up .item{width:25%}.icon-bar.four-up.vertical .item,.icon-bar.four-up.small-vertical .item{width:auto}@media only screen and (min-width: 40.063em){.icon-bar.four-up.medium-vertical .item{width:auto}}@media only screen and (min-width: 64.063em){.icon-bar.four-up.large-vertical .item{width:auto}}.icon-bar.five-up .item{width:20%}.icon-bar.five-up.vertical .item,.icon-bar.five-up.small-vertical .item{width:auto}@media only screen and (min-width: 40.063em){.icon-bar.five-up.medium-vertical .item{width:auto}}@media only screen and (min-width: 64.063em){.icon-bar.five-up.large-vertical .item{width:auto}}.icon-bar.six-up .item{width:16.66667%}.icon-bar.six-up.vertical .item,.icon-bar.six-up.small-vertical .item{width:auto}@media only screen and (min-width: 40.063em){.icon-bar.six-up.medium-vertical .item{width:auto}}@media only screen and (min-width: 64.063em){.icon-bar.six-up.large-vertical .item{width:auto}}.icon-bar.seven-up .item{width:14.28571%}.icon-bar.seven-up.vertical .item,.icon-bar.seven-up.small-vertical .item{width:auto}@media only screen and (min-width: 40.063em){.icon-bar.seven-up.medium-vertical .item{width:auto}}@media only screen and (min-width: 64.063em){.icon-bar.seven-up.large-vertical .item{width:auto}}.icon-bar.eight-up .item{width:12.5%}.icon-bar.eight-up.vertical .item,.icon-bar.eight-up.small-vertical .item{width:auto}@media only screen and (min-width: 40.063em){.icon-bar.eight-up.medium-vertical .item{width:auto}}@media only screen and (min-width: 64.063em){.icon-bar.eight-up.large-vertical .item{width:auto}}.tabs{margin-bottom:0 !important;margin-left:0}.tabs:before,.tabs:after{content:" ";display:table}.tabs:after{clear:both}.tabs dd,.tabs .tab-title{position:relative;margin-bottom:0 !important;list-style:none;float:left}.tabs dd>a,.tabs .tab-title>a{display:block;background-color:#EFEFEF;color:#222;padding:1rem 2rem;font-family:"Helvetica Neue",Helvetica,Roboto,Arial,sans-serif;font-size:1rem}.tabs dd>a:hover,.tabs .tab-title>a:hover{background-color:#e1e1e1}.tabs dd>a:focus,.tabs .tab-title>a:focus{outline:none}.tabs dd.active a,.tabs .tab-title.active a{background-color:#fff;color:#222}.tabs.radius dd:first-child a,.tabs.radius .tab:first-child a{-webkit-border-bottom-left-radius:3px;-webkit-border-top-left-radius:3px;border-bottom-left-radius:3px;border-top-left-radius:3px}.tabs.radius dd:last-child a,.tabs.radius .tab:last-child a{-webkit-border-bottom-right-radius:3px;-webkit-border-top-right-radius:3px;border-bottom-right-radius:3px;border-top-right-radius:3px}.tabs.vertical dd,.tabs.vertical .tab-title{position:inherit;float:none;display:block;top:auto}.tabs-content{margin-bottom:1.5rem;width:100%}.tabs-content:before,.tabs-content:after{content:" ";display:table}.tabs-content:after{clear:both}.tabs-content>.content{display:none;float:left;padding:0.9375rem 0;width:100%}.tabs-content>.content.active{display:block;float:none}.tabs-content>.content.contained{padding:0.9375rem}.tabs-content.vertical{display:block}.tabs-content.vertical>.content{padding:0 0.9375rem}@media only screen and (min-width: 40.063em){.tabs.vertical{width:20%;max-width:20%;float:left;margin:0 0 1.25rem}.tabs-content.vertical{width:80%;max-width:80%;float:left;margin-left:-1px;padding-left:1rem}}.no-js .tabs-content>.content{display:block;float:none}ul.pagination{display:block;min-height:1.5rem;margin-left:-0.3125rem}ul.pagination li{height:1.5rem;color:#222;font-size:0.875rem;margin-left:0.3125rem}ul.pagination li a,ul.pagination li button{display:block;padding:0.0625rem 0.625rem 0.0625rem;color:#999;background:none;border-radius:3px;font-weight:normal;font-size:1em;line-height:inherit;transition:background-color 300ms ease-out}ul.pagination li:hover a,ul.pagination li a:focus,ul.pagination li:hover button,ul.pagination li button:focus{background:#e6e6e6}ul.pagination li.unavailable a,ul.pagination li.unavailable button{cursor:default;color:#999}ul.pagination li.unavailable:hover a,ul.pagination li.unavailable a:focus,ul.pagination li.unavailable:hover button,ul.pagination li.unavailable button:focus{background:transparent}ul.pagination li.current a,ul.pagination li.current button{background:#008CBA;color:#fff;font-weight:bold;cursor:default}ul.pagination li.current a:hover,ul.pagination li.current a:focus,ul.pagination li.current button:hover,ul.pagination li.current button:focus{background:#008CBA}ul.pagination li{float:left;display:block}.pagination-centered{text-align:center}.pagination-centered ul.pagination li{float:none;display:inline-block}.side-nav{display:block;margin:0;padding:0.875rem 0;list-style-type:none;list-style-position:outside;font-family:"Helvetica Neue",Helvetica,Roboto,Arial,sans-serif}.side-nav li{margin:0 0 0.4375rem 0;font-size:0.875rem;font-weight:normal}.side-nav li a:not(.button){display:block;color:#008CBA;margin:0;padding:0.4375rem 0.875rem}.side-nav li a:not(.button):hover,.side-nav li a:not(.button):focus{background:rgba(0,0,0,0.025);color:#1cc7ff}.side-nav li.active>a:first-child:not(.button){color:#1cc7ff;font-weight:normal;font-family:"Helvetica Neue",Helvetica,Roboto,Arial,sans-serif}.side-nav li.divider{border-top:1px solid;height:0;padding:0;list-style:none;border-top-color:#fff}.side-nav li.heading{color:#008CBA;font-size:0.875rem;font-weight:bold;text-transform:uppercase}.accordion{margin-bottom:0}.accordion:before,.accordion:after{content:" ";display:table}.accordion:after{clear:both}.accordion .accordion-navigation,.accordion dd{display:block;margin-bottom:0 !important}.accordion .accordion-navigation.active>a,.accordion dd.active>a{background:#e8e8e8}.accordion .accordion-navigation>a,.accordion dd>a{background:#EFEFEF;color:#222;padding:1rem;display:block;font-family:"Helvetica Neue",Helvetica,Roboto,Arial,sans-serif;font-size:1rem}.accordion .accordion-navigation>a:hover,.accordion dd>a:hover{background:#e3e3e3}.accordion .accordion-navigation>.content,.accordion dd>.content{display:none;padding:0.9375rem}.accordion .accordion-navigation>.content.active,.accordion dd>.content.active{display:block;background:#fff}.text-left{text-align:left !important}.text-right{text-align:right !important}.text-center{text-align:center !important}.text-justify{text-align:justify !important}@media only screen and (max-width: 40em){.small-only-text-left{text-align:left !important}.small-only-text-right{text-align:right !important}.small-only-text-center{text-align:center !important}.small-only-text-justify{text-align:justify !important}}@media only screen{.small-text-left{text-align:left !important}.small-text-right{text-align:right !important}.small-text-center{text-align:center !important}.small-text-justify{text-align:justify !important}}@media only screen and (min-width: 40.063em) and (max-width: 64em){.medium-only-text-left{text-align:left !important}.medium-only-text-right{text-align:right !important}.medium-only-text-center{text-align:center !important}.medium-only-text-justify{text-align:justify !important}}@media only screen and (min-width: 40.063em){.medium-text-left{text-align:left !important}.medium-text-right{text-align:right !important}.medium-text-center{text-align:center !important}.medium-text-justify{text-align:justify !important}}@media only screen and (min-width: 64.063em) and (max-width: 90em){.large-only-text-left{text-align:left !important}.large-only-text-right{text-align:right !important}.large-only-text-center{text-align:center !important}.large-only-text-justify{text-align:justify !important}}@media only screen and (min-width: 64.063em){.large-text-left{text-align:left !important}.large-text-right{text-align:right !important}.large-text-center{text-align:center !important}.large-text-justify{text-align:justify !important}}@media only screen and (min-width: 90.063em) and (max-width: 120em){.xlarge-only-text-left{text-align:left !important}.xlarge-only-text-right{text-align:right !important}.xlarge-only-text-center{text-align:center !important}.xlarge-only-text-justify{text-align:justify !important}}@media only screen and (min-width: 90.063em){.xlarge-text-left{text-align:left !important}.xlarge-text-right{text-align:right !important}.xlarge-text-center{text-align:center !important}.xlarge-text-justify{text-align:justify !important}}@media only screen and (min-width: 120.063em) and (max-width: 99999999em){.xxlarge-only-text-left{text-align:left !important}.xxlarge-only-text-right{text-align:right !important}.xxlarge-only-text-center{text-align:center !important}.xxlarge-only-text-justify{text-align:justify !important}}@media only screen and (min-width: 120.063em){.xxlarge-text-left{text-align:left !important}.xxlarge-text-right{text-align:right !important}.xxlarge-text-center{text-align:center !important}.xxlarge-text-justify{text-align:justify !important}}div,dl,dt,dd,ul,ol,li,h1,h2,h3,h4,h5,h6,pre,form,p,blockquote,th,td{margin:0;padding:0}a{color:#008CBA;text-decoration:none;line-height:inherit}a:hover,a:focus{color:#0078a0}a img{border:none}p{font-family:inherit;font-weight:normal;font-size:1rem;line-height:1.6;margin-bottom:1.25rem;text-rendering:optimizeLegibility}p.lead{font-size:1.21875rem;line-height:1.6}p aside{font-size:0.875rem;line-height:1.35;font-style:italic}h1,h2,h3,h4,h5,h6{font-family:"Helvetica Neue",Helvetica,Roboto,Arial,sans-serif;font-weight:normal;font-style:normal;color:#222;text-rendering:optimizeLegibility;margin-top:0.2rem;margin-bottom:0.5rem;line-height:1.4}h1 small,h2 small,h3 small,h4 small,h5 small,h6 small{font-size:60%;color:#6f6f6f;line-height:0}h1{font-size:2.125rem}h2{font-size:1.6875rem}h3{font-size:1.375rem}h4{font-size:1.125rem}h5{font-size:1.125rem}h6{font-size:1rem}.subheader{line-height:1.4;color:#6f6f6f;font-weight:normal;margin-top:0.2rem;margin-bottom:0.5rem}hr{border:solid #ddd;border-width:1px 0 0;clear:both;margin:1.25rem 0 1.1875rem;height:0}em,i{font-style:italic;line-height:inherit}strong,b{font-weight:bold;line-height:inherit}small{font-size:60%;line-height:inherit}code{font-family:Consolas,"Liberation Mono",Courier,monospace;font-weight:normal;color:#333;background-color:#f8f8f8;border-width:1px;border-style:solid;border-color:#dfdfdf;padding:0.125rem 0.3125rem 0.0625rem}ul,ol,dl{font-size:1rem;line-height:1.6;margin-bottom:1.25rem;list-style-position:outside;font-family:inherit}ul{margin-left:1.1rem}ul.no-bullet{margin-left:0}ul.no-bullet li ul,ul.no-bullet li ol{margin-left:1.25rem;margin-bottom:0;list-style:none}ul li ul,ul li ol{margin-left:1.25rem;margin-bottom:0}ul.square li ul,ul.circle li ul,ul.disc li ul{list-style:inherit}ul.square{list-style-type:square;margin-left:1.1rem}ul.circle{list-style-type:circle;margin-left:1.1rem}ul.disc{list-style-type:disc;margin-left:1.1rem}ul.no-bullet{list-style:none}ol{margin-left:1.4rem}ol li ul,ol li ol{margin-left:1.25rem;margin-bottom:0}dl dt{margin-bottom:0.3rem;font-weight:bold}dl dd{margin-bottom:0.75rem}abbr,acronym{text-transform:uppercase;font-size:90%;color:#222;cursor:help}abbr{text-transform:none}abbr[title]{border-bottom:1px dotted #ddd}blockquote{margin:0 0 1.25rem;padding:0.5625rem 1.25rem 0 1.1875rem;border-left:1px solid #ddd}blockquote cite{display:block;font-size:0.8125rem;color:#555}blockquote cite:before{content:"\\2014 \\0020"}blockquote cite a,blockquote cite a:visited{color:#555}blockquote,blockquote p{line-height:1.6;color:#6f6f6f}.vcard{display:inline-block;margin:0 0 1.25rem 0;border:1px solid #ddd;padding:0.625rem 0.75rem}.vcard li{margin:0;display:block}.vcard .fn{font-weight:bold;font-size:0.9375rem}.vevent .summary{font-weight:bold}.vevent abbr{cursor:default;text-decoration:none;font-weight:bold;border:none;padding:0 0.0625rem}@media only screen and (min-width: 40.063em){h1,h2,h3,h4,h5,h6{line-height:1.4}h1{font-size:2.75rem}h2{font-size:2.3125rem}h3{font-size:1.6875rem}h4{font-size:1.4375rem}h5{font-size:1.125rem}h6{font-size:1rem}}.split.button{position:relative;padding-right:5.0625rem}.split.button span{display:block;height:100%;position:absolute;right:0;top:0;border-left:solid 1px}.split.button span:after{position:absolute;content:"";width:0;height:0;display:block;border-style:inset;top:50%;left:50%}.split.button span:active{background-color:rgba(0,0,0,0.1)}.split.button span{border-left-color:rgba(255,255,255,0.5)}.split.button span{width:3.09375rem}.split.button span:after{border-top-style:solid;border-width:0.375rem;top:48%;margin-left:-0.375rem}.split.button span:after{border-color:#fff transparent transparent transparent}.split.button.secondary span{border-left-color:rgba(255,255,255,0.5)}.split.button.secondary span:after{border-color:#fff transparent transparent transparent}.split.button.alert span{border-left-color:rgba(255,255,255,0.5)}.split.button.success span{border-left-color:rgba(255,255,255,0.5)}.split.button.tiny{padding-right:3.75rem}.split.button.tiny span{width:2.25rem}.split.button.tiny span:after{border-top-style:solid;border-width:0.375rem;top:48%;margin-left:-0.375rem}.split.button.small{padding-right:4.375rem}.split.button.small span{width:2.625rem}.split.button.small span:after{border-top-style:solid;border-width:0.4375rem;top:48%;margin-left:-0.375rem}.split.button.large{padding-right:5.5rem}.split.button.large span{width:3.4375rem}.split.button.large span:after{border-top-style:solid;border-width:0.3125rem;top:48%;margin-left:-0.375rem}.split.button.expand{padding-left:2rem}.split.button.secondary span:after{border-color:#333 transparent transparent transparent}.split.button.radius span{-webkit-border-bottom-right-radius:3px;-webkit-border-top-right-radius:3px;border-bottom-right-radius:3px;border-top-right-radius:3px}.split.button.round span{-webkit-border-bottom-right-radius:1000px;-webkit-border-top-right-radius:1000px;border-bottom-right-radius:1000px;border-top-right-radius:1000px}.split.button.no-pip span:before{border-style:none}.split.button.no-pip span:after{border-style:none}.split.button.no-pip span>i{top:50%;display:block;position:absolute;left:50%;margin-left:-0.28889em;margin-top:-0.48889em}.reveal-modal-bg{position:fixed;top:0;bottom:0;left:0;right:0;background:#000;background:rgba(0,0,0,0.45);z-index:1004;display:none;left:0}.reveal-modal{visibility:hidden;display:none;position:absolute;z-index:1005;width:100%;top:0;border-radius:3px;left:0;background-color:#fff;padding:1.875rem;border:solid 1px #666;box-shadow:0 0 10px rgba(0,0,0,0.4)}@media only screen and (max-width: 40em){.reveal-modal{min-height:100vh}}.reveal-modal .column,.reveal-modal .columns{min-width:0}.reveal-modal>:first-child{margin-top:0}.reveal-modal>:last-child{margin-bottom:0}@media only screen and (min-width: 40.063em){.reveal-modal{width:80%;max-width:62.5rem;left:0;right:0;margin:0 auto}}@media only screen and (min-width: 40.063em){.reveal-modal{top:6.25rem}}.reveal-modal.radius{border-radius:3px}.reveal-modal.round{border-radius:1000px}.reveal-modal.collapse{padding:0}@media only screen and (min-width: 40.063em){.reveal-modal.tiny{width:30%;max-width:62.5rem;left:0;right:0;margin:0 auto}}@media only screen and (min-width: 40.063em){.reveal-modal.small{width:40%;max-width:62.5rem;left:0;right:0;margin:0 auto}}@media only screen and (min-width: 40.063em){.reveal-modal.medium{width:60%;max-width:62.5rem;left:0;right:0;margin:0 auto}}@media only screen and (min-width: 40.063em){.reveal-modal.large{width:70%;max-width:62.5rem;left:0;right:0;margin:0 auto}}@media only screen and (min-width: 40.063em){.reveal-modal.xlarge{width:95%;max-width:62.5rem;left:0;right:0;margin:0 auto}}.reveal-modal.full{top:0;left:0;height:100%;height:100vh;min-height:100vh;max-width:none !important;margin-left:0 !important}@media only screen and (min-width: 40.063em){.reveal-modal.full{width:100%;max-width:62.5rem;left:0;right:0;margin:0 auto}}.reveal-modal.toback{z-index:1003}.reveal-modal .close-reveal-modal{font-size:2.5rem;line-height:1;position:absolute;top:0.625rem;right:1.375rem;color:#aaa;font-weight:bold;cursor:pointer}.has-tip{border-bottom:dotted 1px #ccc;cursor:help;font-weight:bold;color:#333}.has-tip:hover,.has-tip:focus{border-bottom:dotted 1px #003f54;color:#008CBA}.has-tip.tip-left,.has-tip.tip-right{float:none !important}.tooltip{display:none;position:absolute;z-index:1006;font-weight:normal;font-size:0.875rem;line-height:1.3;padding:0.75rem;max-width:300px;left:50%;width:100%;color:#fff;background:#333}.tooltip>.nub{display:block;left:5px;position:absolute;width:0;height:0;border:solid 5px;border-color:transparent transparent #333 transparent;top:-10px;pointer-events:none}.tooltip>.nub.rtl{left:auto;right:5px}.tooltip.radius{border-radius:3px}.tooltip.round{border-radius:1000px}.tooltip.round>.nub{left:2rem}.tooltip.opened{color:#008CBA !important;border-bottom:dotted 1px #003f54 !important}.tap-to-close{display:block;font-size:0.625rem;color:#777;font-weight:normal}@media only screen and (min-width: 40.063em){.tooltip>.nub{border-color:transparent transparent #333 transparent;top:-10px}.tooltip.tip-top>.nub{border-color:#333 transparent transparent transparent;top:auto;bottom:-10px}.tooltip.tip-left,.tooltip.tip-right{float:none !important}.tooltip.tip-left>.nub{border-color:transparent transparent transparent #333;right:-10px;left:auto;top:50%;margin-top:-5px}.tooltip.tip-right>.nub{border-color:transparent #333 transparent transparent;right:auto;left:-10px;top:50%;margin-top:-5px}}.clearing-thumbs,[data-clearing]{margin-bottom:0;margin-left:0;list-style:none}.clearing-thumbs:before,.clearing-thumbs:after,[data-clearing]:before,[data-clearing]:after{content:" ";display:table}.clearing-thumbs:after,[data-clearing]:after{clear:both}.clearing-thumbs li,[data-clearing] li{float:left;margin-right:10px}.clearing-thumbs[class*="block-grid-"] li,[data-clearing][class*="block-grid-"] li{margin-right:0}.clearing-blackout{background:#333;position:fixed;width:100%;height:100%;top:0;left:0;z-index:998}.clearing-blackout .clearing-close{display:block}.clearing-container{position:relative;z-index:998;height:100%;overflow:hidden;margin:0}.clearing-touch-label{position:absolute;top:50%;left:50%;color:#aaa;font-size:0.6em}.visible-img{height:95%;position:relative}.visible-img img{position:absolute;left:50%;top:50%;transform:translateY(-50%) translateX(-50%);-webkit-transform:translateY(-50%) translateX(-50%);-ms-transform:translateY(-50%) translateX(-50%);max-height:100%;max-width:100%}.clearing-caption{color:#ccc;font-size:0.875em;line-height:1.3;margin-bottom:0;text-align:center;bottom:0;background:#333;width:100%;padding:10px 30px 20px;position:absolute;left:0}.clearing-close{z-index:999;padding-left:20px;padding-top:10px;font-size:30px;line-height:1;color:#ccc;display:none}.clearing-close:hover,.clearing-close:focus{color:#ccc}.clearing-assembled .clearing-container{height:100%}.clearing-assembled .clearing-container .carousel>ul{display:none}.clearing-feature li{display:none}.clearing-feature li.clearing-featured-img{display:block}@media only screen and (min-width: 40.063em){.clearing-main-prev,.clearing-main-next{position:absolute;height:100%;width:40px;top:0}.clearing-main-prev>span,.clearing-main-next>span{position:absolute;top:50%;display:block;width:0;height:0;border:solid 12px}.clearing-main-prev>span:hover,.clearing-main-next>span:hover{opacity:0.8}.clearing-main-prev{left:0}.clearing-main-prev>span{left:5px;border-color:transparent;border-right-color:#ccc}.clearing-main-next{right:0}.clearing-main-next>span{border-color:transparent;border-left-color:#ccc}.clearing-main-prev.disabled,.clearing-main-next.disabled{opacity:0.3}.clearing-assembled .clearing-container .carousel{background:rgba(51,51,51,0.8);height:120px;margin-top:10px;text-align:center}.clearing-assembled .clearing-container .carousel>ul{display:inline-block;z-index:999;height:100%;position:relative;float:none}.clearing-assembled .clearing-container .carousel>ul li{display:block;width:120px;min-height:inherit;float:left;overflow:hidden;margin-right:0;padding:0;position:relative;cursor:pointer;opacity:0.4;clear:none}.clearing-assembled .clearing-container .carousel>ul li.fix-height img{height:100%;max-width:none}.clearing-assembled .clearing-container .carousel>ul li a.th{border:none;box-shadow:none;display:block}.clearing-assembled .clearing-container .carousel>ul li img{cursor:pointer !important;width:100% !important}.clearing-assembled .clearing-container .carousel>ul li.visible{opacity:1}.clearing-assembled .clearing-container .carousel>ul li:hover{opacity:0.8}.clearing-assembled .clearing-container .visible-img{background:#333;overflow:hidden;height:85%}.clearing-close{position:absolute;top:10px;right:20px;padding-left:0;padding-top:0}}.progress{background-color:#F6F6F6;height:1.5625rem;border:1px solid #fff;padding:0.125rem;margin-bottom:0.625rem}.progress .meter{background:#008CBA;height:100%;display:block}.progress.secondary .meter{background:#e7e7e7;height:100%;display:block}.progress.success .meter{background:#43AC6A;height:100%;display:block}.progress.alert .meter{background:#f04124;height:100%;display:block}.progress.radius{border-radius:3px}.progress.radius .meter{border-radius:2px}.progress.round{border-radius:1000px}.progress.round .meter{border-radius:999px}.sub-nav{display:block;width:auto;overflow:hidden;margin-bottom:-0.25rem 0 1.125rem;padding-top:0.25rem}.sub-nav dt{text-transform:uppercase}.sub-nav dt,.sub-nav dd,.sub-nav li{float:left;margin-left:1rem;margin-bottom:0;font-family:"Helvetica Neue",Helvetica,Roboto,Arial,sans-serif;font-weight:normal;font-size:0.875rem;color:#999}.sub-nav dt a,.sub-nav dd a,.sub-nav li a{text-decoration:none;color:#999;padding:0.1875rem 1rem}.sub-nav dt a:hover,.sub-nav dd a:hover,.sub-nav li a:hover{color:#737373}.sub-nav dt.active a,.sub-nav dd.active a,.sub-nav li.active a{border-radius:3px;font-weight:normal;background:#008CBA;padding:0.1875rem 1rem;cursor:default;color:#fff}.sub-nav dt.active a:hover,.sub-nav dd.active a:hover,.sub-nav li.active a:hover{background:#0078a0}.joyride-list{display:none}.joyride-tip-guide{display:none;position:absolute;background:#333;color:#fff;z-index:101;top:0;left:2.5%;font-family:inherit;font-weight:normal;width:95%}.lt-ie9 .joyride-tip-guide{max-width:800px;left:50%;margin-left:-400px}.joyride-content-wrapper{width:100%;padding:1.125rem 1.25rem 1.5rem}.joyride-content-wrapper .button{margin-bottom:0 !important}.joyride-content-wrapper .joyride-prev-tip{margin-right:10px}.joyride-tip-guide .joyride-nub{display:block;position:absolute;left:22px;width:0;height:0;border:10px solid #333}.joyride-tip-guide .joyride-nub.top{border-top-style:solid;border-color:#333;border-top-color:transparent !important;border-left-color:transparent !important;border-right-color:transparent !important;top:-20px}.joyride-tip-guide .joyride-nub.bottom{border-bottom-style:solid;border-color:#333 !important;border-bottom-color:transparent !important;border-left-color:transparent !important;border-right-color:transparent !important;bottom:-20px}.joyride-tip-guide .joyride-nub.right{right:-20px}.joyride-tip-guide .joyride-nub.left{left:-20px}.joyride-tip-guide h1,.joyride-tip-guide h2,.joyride-tip-guide h3,.joyride-tip-guide h4,.joyride-tip-guide h5,.joyride-tip-guide h6{line-height:1.25;margin:0;font-weight:bold;color:#fff}.joyride-tip-guide p{margin:0 0 1.125rem 0;font-size:0.875rem;line-height:1.3}.joyride-timer-indicator-wrap{width:50px;height:3px;border:solid 1px #555;position:absolute;right:1.0625rem;bottom:1rem}.joyride-timer-indicator{display:block;width:0;height:inherit;background:#666}.joyride-close-tip{position:absolute;right:12px;top:10px;color:#777 !important;text-decoration:none;font-size:24px;font-weight:normal;line-height:.5 !important}.joyride-close-tip:hover,.joyride-close-tip:focus{color:#eee !important}.joyride-modal-bg{position:fixed;height:100%;width:100%;background:transparent;background:rgba(0,0,0,0.5);z-index:100;display:none;top:0;left:0;cursor:pointer}.joyride-expose-wrapper{background-color:#fff;position:absolute;border-radius:3px;z-index:102;box-shadow:0 0 15px #fff}.joyride-expose-cover{background:transparent;border-radius:3px;position:absolute;z-index:9999;top:0;left:0}@media only screen and (min-width: 40.063em){.joyride-tip-guide{width:300px;left:inherit}.joyride-tip-guide .joyride-nub.bottom{border-color:#333 !important;border-bottom-color:transparent !important;border-left-color:transparent !important;border-right-color:transparent !important;bottom:-20px}.joyride-tip-guide .joyride-nub.right{border-color:#333 !important;border-top-color:transparent !important;border-right-color:transparent !important;border-bottom-color:transparent !important;top:22px;left:auto;right:-20px}.joyride-tip-guide .joyride-nub.left{border-color:#333 !important;border-top-color:transparent !important;border-left-color:transparent !important;border-bottom-color:transparent !important;top:22px;left:-20px;right:auto}}.label{font-weight:normal;font-family:"Helvetica Neue",Helvetica,Roboto,Arial,sans-serif;text-align:center;text-decoration:none;line-height:1;white-space:nowrap;display:inline-block;position:relative;margin-bottom:auto;padding:0.25rem 0.5rem 0.25rem;font-size:0.6875rem;background-color:#008CBA;color:#fff}.label.radius{border-radius:3px}.label.round{border-radius:1000px}.label.alert{background-color:#f04124;color:#fff}.label.warning{background-color:#f08a24;color:#fff}.label.success{background-color:#43AC6A;color:#fff}.label.secondary{background-color:#e7e7e7;color:#333}.label.info{background-color:#a0d3e8;color:#333}.off-canvas-wrap{-webkit-backface-visibility:hidden;position:relative;width:100%;overflow:hidden}.off-canvas-wrap.move-right,.off-canvas-wrap.move-left{min-height:100%;-webkit-overflow-scrolling:touch}.inner-wrap{position:relative;width:100%;-webkit-transition:-webkit-transform 500ms ease;-moz-transition:-moz-transform 500ms ease;-ms-transition:-ms-transform 500ms ease;-o-transition:-o-transform 500ms ease;transition:transform 500ms ease}.inner-wrap:before,.inner-wrap:after{content:" ";display:table}.inner-wrap:after{clear:both}.tab-bar{-webkit-backface-visibility:hidden;background:#333;color:#fff;height:2.8125rem;line-height:2.8125rem;position:relative}.tab-bar h1,.tab-bar h2,.tab-bar h3,.tab-bar h4,.tab-bar h5,.tab-bar h6{color:#fff;font-weight:bold;line-height:2.8125rem;margin:0}.tab-bar h1,.tab-bar h2,.tab-bar h3,.tab-bar h4{font-size:1.125rem}.left-small{width:2.8125rem;height:2.8125rem;position:absolute;top:0;border-right:solid 1px #1a1a1a;left:0}.right-small{width:2.8125rem;height:2.8125rem;position:absolute;top:0;border-left:solid 1px #1a1a1a;right:0}.tab-bar-section{padding:0 0.625rem;position:absolute;text-align:center;height:2.8125rem;top:0}@media only screen and (min-width: 40.063em){.tab-bar-section.left{text-align:left}.tab-bar-section.right{text-align:right}}.tab-bar-section.left{left:0;right:2.8125rem}.tab-bar-section.right{left:2.8125rem;right:0}.tab-bar-section.middle{left:2.8125rem;right:2.8125rem}.tab-bar .menu-icon{text-indent:2.1875rem;width:2.8125rem;height:2.8125rem;display:block;padding:0;color:#fff;position:relative;transform:translate3d(0, 0, 0)}.tab-bar .menu-icon span::after{content:"";position:absolute;display:block;height:0;top:50%;margin-top:-0.5rem;left:0.90625rem;box-shadow:0 0 0 1px #fff,0 7px 0 1px #fff,0 14px 0 1px #fff;width:1rem}.tab-bar .menu-icon span:hover:after{box-shadow:0 0 0 1px #b3b3b3,0 7px 0 1px #b3b3b3,0 14px 0 1px #b3b3b3}.left-off-canvas-menu{-webkit-backface-visibility:hidden;width:15.625rem;top:0;bottom:0;position:absolute;overflow-x:hidden;overflow-y:auto;background:#333;z-index:1001;box-sizing:content-box;transition:transform 500ms ease 0s;-webkit-overflow-scrolling:touch;-ms-overflow-style:-ms-autohiding-scrollbar;-ms-transform:translate(-100%, 0);-webkit-transform:translate3d(-100%, 0, 0);-moz-transform:translate3d(-100%, 0, 0);-ms-transform:translate3d(-100%, 0, 0);-o-transform:translate3d(-100%, 0, 0);transform:translate3d(-100%, 0, 0);left:0}.left-off-canvas-menu *{-webkit-backface-visibility:hidden}.right-off-canvas-menu{-webkit-backface-visibility:hidden;width:15.625rem;top:0;bottom:0;position:absolute;overflow-x:hidden;overflow-y:auto;background:#333;z-index:1001;box-sizing:content-box;transition:transform 500ms ease 0s;-webkit-overflow-scrolling:touch;-ms-overflow-style:-ms-autohiding-scrollbar;-ms-transform:translate(100%, 0);-webkit-transform:translate3d(100%, 0, 0);-moz-transform:translate3d(100%, 0, 0);-ms-transform:translate3d(100%, 0, 0);-o-transform:translate3d(100%, 0, 0);transform:translate3d(100%, 0, 0);right:0}.right-off-canvas-menu *{-webkit-backface-visibility:hidden}ul.off-canvas-list{list-style-type:none;padding:0;margin:0}ul.off-canvas-list li label{display:block;padding:0.3rem 0.9375rem;color:#999;text-transform:uppercase;font-size:0.75rem;font-weight:bold;background:#444;border-top:1px solid #5e5e5e;border-bottom:none;margin:0}ul.off-canvas-list li a{display:block;padding:0.66667rem;color:rgba(255,255,255,0.7);border-bottom:1px solid #262626;transition:background 300ms ease}ul.off-canvas-list li a:hover{background:#242424}.move-right>.inner-wrap{-ms-transform:translate(15.625rem, 0);-webkit-transform:translate3d(15.625rem, 0, 0);-moz-transform:translate3d(15.625rem, 0, 0);-ms-transform:translate3d(15.625rem, 0, 0);-o-transform:translate3d(15.625rem, 0, 0);transform:translate3d(15.625rem, 0, 0)}.move-right .exit-off-canvas{-webkit-backface-visibility:hidden;transition:background 300ms ease;cursor:pointer;box-shadow:-4px 0 4px rgba(0,0,0,0.5),4px 0 4px rgba(0,0,0,0.5);display:block;position:absolute;background:rgba(255,255,255,0.2);top:0;bottom:0;left:0;right:0;z-index:1002;-webkit-tap-highlight-color:transparent}@media only screen and (min-width: 40.063em){.move-right .exit-off-canvas:hover{background:rgba(255,255,255,0.05)}}.move-left>.inner-wrap{-ms-transform:translate(-15.625rem, 0);-webkit-transform:translate3d(-15.625rem, 0, 0);-moz-transform:translate3d(-15.625rem, 0, 0);-ms-transform:translate3d(-15.625rem, 0, 0);-o-transform:translate3d(-15.625rem, 0, 0);transform:translate3d(-15.625rem, 0, 0)}.move-left .exit-off-canvas{-webkit-backface-visibility:hidden;transition:background 300ms ease;cursor:pointer;box-shadow:-4px 0 4px rgba(0,0,0,0.5),4px 0 4px rgba(0,0,0,0.5);display:block;position:absolute;background:rgba(255,255,255,0.2);top:0;bottom:0;left:0;right:0;z-index:1002;-webkit-tap-highlight-color:transparent}@media only screen and (min-width: 40.063em){.move-left .exit-off-canvas:hover{background:rgba(255,255,255,0.05)}}.offcanvas-overlap .left-off-canvas-menu,.offcanvas-overlap .right-off-canvas-menu{-ms-transform:none;-webkit-transform:none;-moz-transform:none;-o-transform:none;transform:none;z-index:1003}.offcanvas-overlap .exit-off-canvas{-webkit-backface-visibility:hidden;transition:background 300ms ease;cursor:pointer;box-shadow:-4px 0 4px rgba(0,0,0,0.5),4px 0 4px rgba(0,0,0,0.5);display:block;position:absolute;background:rgba(255,255,255,0.2);top:0;bottom:0;left:0;right:0;z-index:1002;-webkit-tap-highlight-color:transparent}@media only screen and (min-width: 40.063em){.offcanvas-overlap .exit-off-canvas:hover{background:rgba(255,255,255,0.05)}}.offcanvas-overlap-left .right-off-canvas-menu{-ms-transform:none;-webkit-transform:none;-moz-transform:none;-o-transform:none;transform:none;z-index:1003}.offcanvas-overlap-left .exit-off-canvas{-webkit-backface-visibility:hidden;transition:background 300ms ease;cursor:pointer;box-shadow:-4px 0 4px rgba(0,0,0,0.5),4px 0 4px rgba(0,0,0,0.5);display:block;position:absolute;background:rgba(255,255,255,0.2);top:0;bottom:0;left:0;right:0;z-index:1002;-webkit-tap-highlight-color:transparent}@media only screen and (min-width: 40.063em){.offcanvas-overlap-left .exit-off-canvas:hover{background:rgba(255,255,255,0.05)}}.offcanvas-overlap-right .left-off-canvas-menu{-ms-transform:none;-webkit-transform:none;-moz-transform:none;-o-transform:none;transform:none;z-index:1003}.offcanvas-overlap-right .exit-off-canvas{-webkit-backface-visibility:hidden;transition:background 300ms ease;cursor:pointer;box-shadow:-4px 0 4px rgba(0,0,0,0.5),4px 0 4px rgba(0,0,0,0.5);display:block;position:absolute;background:rgba(255,255,255,0.2);top:0;bottom:0;left:0;right:0;z-index:1002;-webkit-tap-highlight-color:transparent}@media only screen and (min-width: 40.063em){.offcanvas-overlap-right .exit-off-canvas:hover{background:rgba(255,255,255,0.05)}}.no-csstransforms .left-off-canvas-menu{left:-15.625rem}.no-csstransforms .right-off-canvas-menu{right:-15.625rem}.no-csstransforms .move-left>.inner-wrap{right:15.625rem}.no-csstransforms .move-right>.inner-wrap{left:15.625rem}.left-submenu{-webkit-backface-visibility:hidden;width:15.625rem;top:0;bottom:0;position:absolute;margin:0;overflow-x:hidden;overflow-y:auto;background:#333;z-index:1002;box-sizing:content-box;-webkit-overflow-scrolling:touch;-ms-transform:translate(-100%, 0);-webkit-transform:translate3d(-100%, 0, 0);-moz-transform:translate3d(-100%, 0, 0);-ms-transform:translate3d(-100%, 0, 0);-o-transform:translate3d(-100%, 0, 0);transform:translate3d(-100%, 0, 0);left:0;-webkit-transition:-webkit-transform 500ms ease;-moz-transition:-moz-transform 500ms ease;-ms-transition:-ms-transform 500ms ease;-o-transition:-o-transform 500ms ease;transition:transform 500ms ease}.left-submenu *{-webkit-backface-visibility:hidden}.left-submenu .back>a{padding:0.3rem 0.9375rem;color:#999;text-transform:uppercase;font-weight:bold;background:#444;border-top:1px solid #5e5e5e;border-bottom:none;margin:0}.left-submenu .back>a:hover{background:#303030;border-top:1px solid #5e5e5e;border-bottom:none}.left-submenu .back>a:before{content:"\\AB";margin-right:0.5rem;display:inline}.left-submenu.move-right,.left-submenu.offcanvas-overlap-right,.left-submenu.offcanvas-overlap{-ms-transform:translate(0%, 0);-webkit-transform:translate3d(0%, 0, 0);-moz-transform:translate3d(0%, 0, 0);-ms-transform:translate3d(0%, 0, 0);-o-transform:translate3d(0%, 0, 0);transform:translate3d(0%, 0, 0)}.right-submenu{-webkit-backface-visibility:hidden;width:15.625rem;top:0;bottom:0;position:absolute;margin:0;overflow-x:hidden;overflow-y:auto;background:#333;z-index:1002;box-sizing:content-box;-webkit-overflow-scrolling:touch;-ms-transform:translate(100%, 0);-webkit-transform:translate3d(100%, 0, 0);-moz-transform:translate3d(100%, 0, 0);-ms-transform:translate3d(100%, 0, 0);-o-transform:translate3d(100%, 0, 0);transform:translate3d(100%, 0, 0);right:0;-webkit-transition:-webkit-transform 500ms ease;-moz-transition:-moz-transform 500ms ease;-ms-transition:-ms-transform 500ms ease;-o-transition:-o-transform 500ms ease;transition:transform 500ms ease}.right-submenu *{-webkit-backface-visibility:hidden}.right-submenu .back>a{padding:0.3rem 0.9375rem;color:#999;text-transform:uppercase;font-weight:bold;background:#444;border-top:1px solid #5e5e5e;border-bottom:none;margin:0}.right-submenu .back>a:hover{background:#303030;border-top:1px solid #5e5e5e;border-bottom:none}.right-submenu .back>a:after{content:"\\BB";margin-left:0.5rem;display:inline}.right-submenu.move-left,.right-submenu.offcanvas-overlap-left,.right-submenu.offcanvas-overlap{-ms-transform:translate(0%, 0);-webkit-transform:translate3d(0%, 0, 0);-moz-transform:translate3d(0%, 0, 0);-ms-transform:translate3d(0%, 0, 0);-o-transform:translate3d(0%, 0, 0);transform:translate3d(0%, 0, 0)}.left-off-canvas-menu ul.off-canvas-list li.has-submenu>a:after{content:"\\BB";margin-left:0.5rem;display:inline}.right-off-canvas-menu ul.off-canvas-list li.has-submenu>a:before{content:"\\AB";margin-right:0.5rem;display:inline}.f-dropdown{position:absolute;left:-9999px;list-style:none;margin-left:0;outline:none;width:100%;max-height:none;height:auto;background:#fff;border:solid 1px #ccc;font-size:0.875rem;z-index:89;margin-top:2px;max-width:200px}.f-dropdown>*:first-child{margin-top:0}.f-dropdown>*:last-child{margin-bottom:0}.f-dropdown:before{content:"";display:block;width:0;height:0;border:inset 6px;border-color:transparent transparent #fff transparent;border-bottom-style:solid;position:absolute;top:-12px;left:10px;z-index:89}.f-dropdown:after{content:"";display:block;width:0;height:0;border:inset 7px;border-color:transparent transparent #ccc transparent;border-bottom-style:solid;position:absolute;top:-14px;left:9px;z-index:88}.f-dropdown.right:before{left:auto;right:10px}.f-dropdown.right:after{left:auto;right:9px}.f-dropdown.drop-right{position:absolute;left:-9999px;list-style:none;margin-left:0;outline:none;width:100%;max-height:none;height:auto;background:#fff;border:solid 1px #ccc;font-size:0.875rem;z-index:89;margin-top:0;margin-left:2px;max-width:200px}.f-dropdown.drop-right>*:first-child{margin-top:0}.f-dropdown.drop-right>*:last-child{margin-bottom:0}.f-dropdown.drop-right:before{content:"";display:block;width:0;height:0;border:inset 6px;border-color:transparent #fff transparent transparent;border-right-style:solid;position:absolute;top:10px;left:-12px;z-index:89}.f-dropdown.drop-right:after{content:"";display:block;width:0;height:0;border:inset 7px;border-color:transparent #ccc transparent transparent;border-right-style:solid;position:absolute;top:9px;left:-14px;z-index:88}.f-dropdown.drop-left{position:absolute;left:-9999px;list-style:none;margin-left:0;outline:none;width:100%;max-height:none;height:auto;background:#fff;border:solid 1px #ccc;font-size:0.875rem;z-index:89;margin-top:0;margin-left:-2px;max-width:200px}.f-dropdown.drop-left>*:first-child{margin-top:0}.f-dropdown.drop-left>*:last-child{margin-bottom:0}.f-dropdown.drop-left:before{content:"";display:block;width:0;height:0;border:inset 6px;border-color:transparent transparent transparent #fff;border-left-style:solid;position:absolute;top:10px;right:-12px;left:auto;z-index:89}.f-dropdown.drop-left:after{content:"";display:block;width:0;height:0;border:inset 7px;border-color:transparent transparent transparent #ccc;border-left-style:solid;position:absolute;top:9px;right:-14px;left:auto;z-index:88}.f-dropdown.drop-top{position:absolute;left:-9999px;list-style:none;margin-left:0;outline:none;width:100%;max-height:none;height:auto;background:#fff;border:solid 1px #ccc;font-size:0.875rem;z-index:89;margin-top:-2px;margin-left:0;max-width:200px}.f-dropdown.drop-top>*:first-child{margin-top:0}.f-dropdown.drop-top>*:last-child{margin-bottom:0}.f-dropdown.drop-top:before{content:"";display:block;width:0;height:0;border:inset 6px;border-color:#fff transparent transparent transparent;border-top-style:solid;position:absolute;top:auto;bottom:-12px;left:10px;right:auto;z-index:89}.f-dropdown.drop-top:after{content:"";display:block;width:0;height:0;border:inset 7px;border-color:#ccc transparent transparent transparent;border-top-style:solid;position:absolute;top:auto;bottom:-14px;left:9px;right:auto;z-index:88}.f-dropdown li{font-size:0.875rem;cursor:pointer;line-height:1.125rem;margin:0}.f-dropdown li:hover,.f-dropdown li:focus{background:#eee}.f-dropdown li.radius{border-radius:3px}.f-dropdown li a{display:block;padding:0.5rem;color:#555}.f-dropdown.content{position:absolute;left:-9999px;list-style:none;margin-left:0;outline:none;padding:1.25rem;width:100%;height:auto;max-height:none;background:#fff;border:solid 1px #ccc;font-size:0.875rem;z-index:89;max-width:200px}.f-dropdown.content>*:first-child{margin-top:0}.f-dropdown.content>*:last-child{margin-bottom:0}.f-dropdown.tiny{max-width:200px}.f-dropdown.small{max-width:300px}.f-dropdown.medium{max-width:500px}.f-dropdown.large{max-width:800px}.f-dropdown.mega{width:100% !important;max-width:100% !important}.f-dropdown.mega.open{left:0 !important}table{background:#fff;margin-bottom:1.25rem;border:solid 1px #ddd;table-layout:auto}table caption{background:transparent;color:#222;font-size:1rem;font-weight:bold}table thead{background:#F5F5F5}table thead tr th,table thead tr td{padding:0.5rem 0.625rem 0.625rem;font-size:0.875rem;font-weight:bold;color:#222}table tfoot{background:#F5F5F5}table tfoot tr th,table tfoot tr td{padding:0.5rem 0.625rem 0.625rem;font-size:0.875rem;font-weight:bold;color:#222}table tr th,table tr td{padding:0.5625rem 0.625rem;font-size:0.875rem;color:#222;text-align:left}table tr.even,table tr.alt,table tr:nth-of-type(even){background:#F9F9F9}table thead tr th,table tfoot tr th,table tfoot tr td,table tbody tr th,table tbody tr td,table tr td{display:table-cell;line-height:1.125rem}.range-slider{position:relative;border:1px solid #ddd;margin:1.25rem 0;-ms-touch-action:none;touch-action:none;display:block;width:100%;height:1rem;background:#FAFAFA}.range-slider.vertical-range{position:relative;border:1px solid #ddd;margin:1.25rem 0;-ms-touch-action:none;touch-action:none;display:inline-block;width:1rem;height:12.5rem}.range-slider.vertical-range .range-slider-handle{margin-top:0;margin-left:-0.5rem;position:absolute;bottom:-10.5rem}.range-slider.vertical-range .range-slider-active-segment{width:0.875rem;height:auto;bottom:0}.range-slider.radius{background:#FAFAFA;border-radius:3px}.range-slider.radius .range-slider-handle{background:#008CBA;border-radius:3px}.range-slider.radius .range-slider-handle:hover{background:#007ba4}.range-slider.round{background:#FAFAFA;border-radius:1000px}.range-slider.round .range-slider-handle{background:#008CBA;border-radius:1000px}.range-slider.round .range-slider-handle:hover{background:#007ba4}.range-slider.disabled,.range-slider[disabled]{background:#FAFAFA;cursor:not-allowed;opacity:0.7}.range-slider.disabled .range-slider-handle,.range-slider[disabled] .range-slider-handle{background:#008CBA;cursor:default;opacity:0.7}.range-slider.disabled .range-slider-handle:hover,.range-slider[disabled] .range-slider-handle:hover{background:#007ba4}.range-slider-active-segment{display:inline-block;position:absolute;height:0.875rem;background:#e5e5e5}.range-slider-handle{display:inline-block;position:absolute;z-index:1;top:-0.3125rem;width:2rem;height:1.375rem;border:1px solid none;cursor:pointer;-ms-touch-action:manipulation;touch-action:manipulation;background:#008CBA}.range-slider-handle:hover{background:#007ba4}[class*="block-grid-"]{display:block;padding:0;margin:0 -0.625rem}[class*="block-grid-"]:before,[class*="block-grid-"]:after{content:" ";display:table}[class*="block-grid-"]:after{clear:both}[class*="block-grid-"]>li{display:block;height:auto;float:left;padding:0 0.625rem 1.25rem}@media only screen{.small-block-grid-1>li{width:100%;list-style:none}.small-block-grid-1>li:nth-of-type(1n){clear:none}.small-block-grid-1>li:nth-of-type(1n+1){clear:both}.small-block-grid-2>li{width:50%;list-style:none}.small-block-grid-2>li:nth-of-type(1n){clear:none}.small-block-grid-2>li:nth-of-type(2n+1){clear:both}.small-block-grid-3>li{width:33.33333%;list-style:none}.small-block-grid-3>li:nth-of-type(1n){clear:none}.small-block-grid-3>li:nth-of-type(3n+1){clear:both}.small-block-grid-4>li{width:25%;list-style:none}.small-block-grid-4>li:nth-of-type(1n){clear:none}.small-block-grid-4>li:nth-of-type(4n+1){clear:both}.small-block-grid-5>li{width:20%;list-style:none}.small-block-grid-5>li:nth-of-type(1n){clear:none}.small-block-grid-5>li:nth-of-type(5n+1){clear:both}.small-block-grid-6>li{width:16.66667%;list-style:none}.small-block-grid-6>li:nth-of-type(1n){clear:none}.small-block-grid-6>li:nth-of-type(6n+1){clear:both}.small-block-grid-7>li{width:14.28571%;list-style:none}.small-block-grid-7>li:nth-of-type(1n){clear:none}.small-block-grid-7>li:nth-of-type(7n+1){clear:both}.small-block-grid-8>li{width:12.5%;list-style:none}.small-block-grid-8>li:nth-of-type(1n){clear:none}.small-block-grid-8>li:nth-of-type(8n+1){clear:both}.small-block-grid-9>li{width:11.11111%;list-style:none}.small-block-grid-9>li:nth-of-type(1n){clear:none}.small-block-grid-9>li:nth-of-type(9n+1){clear:both}.small-block-grid-10>li{width:10%;list-style:none}.small-block-grid-10>li:nth-of-type(1n){clear:none}.small-block-grid-10>li:nth-of-type(10n+1){clear:both}.small-block-grid-11>li{width:9.09091%;list-style:none}.small-block-grid-11>li:nth-of-type(1n){clear:none}.small-block-grid-11>li:nth-of-type(11n+1){clear:both}.small-block-grid-12>li{width:8.33333%;list-style:none}.small-block-grid-12>li:nth-of-type(1n){clear:none}.small-block-grid-12>li:nth-of-type(12n+1){clear:both}}@media only screen and (min-width: 40.063em){.medium-block-grid-1>li{width:100%;list-style:none}.medium-block-grid-1>li:nth-of-type(1n){clear:none}.medium-block-grid-1>li:nth-of-type(1n+1){clear:both}.medium-block-grid-2>li{width:50%;list-style:none}.medium-block-grid-2>li:nth-of-type(1n){clear:none}.medium-block-grid-2>li:nth-of-type(2n+1){clear:both}.medium-block-grid-3>li{width:33.33333%;list-style:none}.medium-block-grid-3>li:nth-of-type(1n){clear:none}.medium-block-grid-3>li:nth-of-type(3n+1){clear:both}.medium-block-grid-4>li{width:25%;list-style:none}.medium-block-grid-4>li:nth-of-type(1n){clear:none}.medium-block-grid-4>li:nth-of-type(4n+1){clear:both}.medium-block-grid-5>li{width:20%;list-style:none}.medium-block-grid-5>li:nth-of-type(1n){clear:none}.medium-block-grid-5>li:nth-of-type(5n+1){clear:both}.medium-block-grid-6>li{width:16.66667%;list-style:none}.medium-block-grid-6>li:nth-of-type(1n){clear:none}.medium-block-grid-6>li:nth-of-type(6n+1){clear:both}.medium-block-grid-7>li{width:14.28571%;list-style:none}.medium-block-grid-7>li:nth-of-type(1n){clear:none}.medium-block-grid-7>li:nth-of-type(7n+1){clear:both}.medium-block-grid-8>li{width:12.5%;list-style:none}.medium-block-grid-8>li:nth-of-type(1n){clear:none}.medium-block-grid-8>li:nth-of-type(8n+1){clear:both}.medium-block-grid-9>li{width:11.11111%;list-style:none}.medium-block-grid-9>li:nth-of-type(1n){clear:none}.medium-block-grid-9>li:nth-of-type(9n+1){clear:both}.medium-block-grid-10>li{width:10%;list-style:none}.medium-block-grid-10>li:nth-of-type(1n){clear:none}.medium-block-grid-10>li:nth-of-type(10n+1){clear:both}.medium-block-grid-11>li{width:9.09091%;list-style:none}.medium-block-grid-11>li:nth-of-type(1n){clear:none}.medium-block-grid-11>li:nth-of-type(11n+1){clear:both}.medium-block-grid-12>li{width:8.33333%;list-style:none}.medium-block-grid-12>li:nth-of-type(1n){clear:none}.medium-block-grid-12>li:nth-of-type(12n+1){clear:both}}@media only screen and (min-width: 64.063em){.large-block-grid-1>li{width:100%;list-style:none}.large-block-grid-1>li:nth-of-type(1n){clear:none}.large-block-grid-1>li:nth-of-type(1n+1){clear:both}.large-block-grid-2>li{width:50%;list-style:none}.large-block-grid-2>li:nth-of-type(1n){clear:none}.large-block-grid-2>li:nth-of-type(2n+1){clear:both}.large-block-grid-3>li{width:33.33333%;list-style:none}.large-block-grid-3>li:nth-of-type(1n){clear:none}.large-block-grid-3>li:nth-of-type(3n+1){clear:both}.large-block-grid-4>li{width:25%;list-style:none}.large-block-grid-4>li:nth-of-type(1n){clear:none}.large-block-grid-4>li:nth-of-type(4n+1){clear:both}.large-block-grid-5>li{width:20%;list-style:none}.large-block-grid-5>li:nth-of-type(1n){clear:none}.large-block-grid-5>li:nth-of-type(5n+1){clear:both}.large-block-grid-6>li{width:16.66667%;list-style:none}.large-block-grid-6>li:nth-of-type(1n){clear:none}.large-block-grid-6>li:nth-of-type(6n+1){clear:both}.large-block-grid-7>li{width:14.28571%;list-style:none}.large-block-grid-7>li:nth-of-type(1n){clear:none}.large-block-grid-7>li:nth-of-type(7n+1){clear:both}.large-block-grid-8>li{width:12.5%;list-style:none}.large-block-grid-8>li:nth-of-type(1n){clear:none}.large-block-grid-8>li:nth-of-type(8n+1){clear:both}.large-block-grid-9>li{width:11.11111%;list-style:none}.large-block-grid-9>li:nth-of-type(1n){clear:none}.large-block-grid-9>li:nth-of-type(9n+1){clear:both}.large-block-grid-10>li{width:10%;list-style:none}.large-block-grid-10>li:nth-of-type(1n){clear:none}.large-block-grid-10>li:nth-of-type(10n+1){clear:both}.large-block-grid-11>li{width:9.09091%;list-style:none}.large-block-grid-11>li:nth-of-type(1n){clear:none}.large-block-grid-11>li:nth-of-type(11n+1){clear:both}.large-block-grid-12>li{width:8.33333%;list-style:none}.large-block-grid-12>li:nth-of-type(1n){clear:none}.large-block-grid-12>li:nth-of-type(12n+1){clear:both}}.flex-video{position:relative;padding-top:1.5625rem;padding-bottom:67.5%;height:0;margin-bottom:1rem;overflow:hidden}.flex-video.widescreen{padding-bottom:56.34%}.flex-video.vimeo{padding-top:0}.flex-video iframe,.flex-video object,.flex-video embed,.flex-video video{position:absolute;top:0;left:0;width:100%;height:100%}.keystroke,kbd{background-color:#ededed;border-color:#ddd;color:#222;border-style:solid;border-width:1px;margin:0;font-family:"Consolas","Menlo","Courier",monospace;font-size:inherit;padding:0.125rem 0.25rem 0;border-radius:3px}.switch{padding:0;border:none;position:relative;outline:0;-webkit-user-select:none;-moz-user-select:none;user-select:none}.switch label{display:block;margin-bottom:1rem;position:relative;color:transparent;background:#ddd;text-indent:100%;width:4rem;height:2rem;cursor:pointer;transition:left 0.15s ease-out}.switch input{opacity:0;position:absolute;top:9px;left:10px;padding:0}.switch input+label{margin-left:0;margin-right:0}.switch label:after{content:"";display:block;background:#fff;position:absolute;top:.25rem;left:.25rem;width:1.5rem;height:1.5rem;-webkit-transition:left 0.15s ease-out;-moz-transition:left 0.15s ease-out;-o-transition:translate3d(0, 0, 0);transition:left 0.15s ease-out;-webkit-transform:translate3d(0, 0, 0);-moz-transform:translate3d(0, 0, 0);-o-transform:translate3d(0, 0, 0);transform:translate3d(0, 0, 0)}.switch input:checked+label{background:#008CBA}.switch input:checked+label:after{left:2.25rem}.switch label{width:4rem;height:2rem}.switch label:after{width:1.5rem;height:1.5rem}.switch input:checked+label:after{left:2.25rem}.switch label{color:transparent;background:#ddd}.switch label:after{background:#fff}.switch input:checked+label{background:#008CBA}.switch.large label{width:5rem;height:2.5rem}.switch.large label:after{width:2rem;height:2rem}.switch.large input:checked+label:after{left:2.75rem}.switch.small label{width:3.5rem;height:1.75rem}.switch.small label:after{width:1.25rem;height:1.25rem}.switch.small input:checked+label:after{left:2rem}.switch.tiny label{width:3rem;height:1.5rem}.switch.tiny label:after{width:1rem;height:1rem}.switch.tiny input:checked+label:after{left:1.75rem}.switch.radius label{border-radius:4px}.switch.radius label:after{border-radius:3px}.switch.round{border-radius:1000px}.switch.round label{border-radius:2rem}.switch.round label:after{border-radius:2rem}@media only screen{.show-for-small-only,.show-for-small-up,.show-for-small,.show-for-small-down,.hide-for-medium-only,.hide-for-medium-up,.hide-for-medium,.show-for-medium-down,.hide-for-large-only,.hide-for-large-up,.hide-for-large,.show-for-large-down,.hide-for-xlarge-only,.hide-for-xlarge-up,.hide-for-xlarge,.show-for-xlarge-down,.hide-for-xxlarge-only,.hide-for-xxlarge-up,.hide-for-xxlarge,.show-for-xxlarge-down{display:inherit !important}.hide-for-small-only,.hide-for-small-up,.hide-for-small,.hide-for-small-down,.show-for-medium-only,.show-for-medium-up,.show-for-medium,.hide-for-medium-down,.show-for-large-only,.show-for-large-up,.show-for-large,.hide-for-large-down,.show-for-xlarge-only,.show-for-xlarge-up,.show-for-xlarge,.hide-for-xlarge-down,.show-for-xxlarge-only,.show-for-xxlarge-up,.show-for-xxlarge,.hide-for-xxlarge-down{display:none !important}.visible-for-small-only,.visible-for-small-up,.visible-for-small,.visible-for-small-down,.hidden-for-medium-only,.hidden-for-medium-up,.hidden-for-medium,.visible-for-medium-down,.hidden-for-large-only,.hidden-for-large-up,.hidden-for-large,.visible-for-large-down,.hidden-for-xlarge-only,.hidden-for-xlarge-up,.hidden-for-xlarge,.visible-for-xlarge-down,.hidden-for-xxlarge-only,.hidden-for-xxlarge-up,.hidden-for-xxlarge,.visible-for-xxlarge-down{position:static !important;height:auto;width:auto;overflow:visible;clip:auto}.hidden-for-small-only,.hidden-for-small-up,.hidden-for-small,.hidden-for-small-down,.visible-for-medium-only,.visible-for-medium-up,.visible-for-medium,.hidden-for-medium-down,.visible-for-large-only,.visible-for-large-up,.visible-for-large,.hidden-for-large-down,.visible-for-xlarge-only,.visible-for-xlarge-up,.visible-for-xlarge,.hidden-for-xlarge-down,.visible-for-xxlarge-only,.visible-for-xxlarge-up,.visible-for-xxlarge,.hidden-for-xxlarge-down{position:absolute !important;height:1px;width:1px;overflow:hidden;clip:rect(1px, 1px, 1px, 1px)}table.show-for-small-only,table.show-for-small-up,table.show-for-small,table.show-for-small-down,table.hide-for-medium-only,table.hide-for-medium-up,table.hide-for-medium,table.show-for-medium-down,table.hide-for-large-only,table.hide-for-large-up,table.hide-for-large,table.show-for-large-down,table.hide-for-xlarge-only,table.hide-for-xlarge-up,table.hide-for-xlarge,table.show-for-xlarge-down,table.hide-for-xxlarge-only,table.hide-for-xxlarge-up,table.hide-for-xxlarge,table.show-for-xxlarge-down{display:table !important}thead.show-for-small-only,thead.show-for-small-up,thead.show-for-small,thead.show-for-small-down,thead.hide-for-medium-only,thead.hide-for-medium-up,thead.hide-for-medium,thead.show-for-medium-down,thead.hide-for-large-only,thead.hide-for-large-up,thead.hide-for-large,thead.show-for-large-down,thead.hide-for-xlarge-only,thead.hide-for-xlarge-up,thead.hide-for-xlarge,thead.show-for-xlarge-down,thead.hide-for-xxlarge-only,thead.hide-for-xxlarge-up,thead.hide-for-xxlarge,thead.show-for-xxlarge-down{display:table-header-group !important}tbody.show-for-small-only,tbody.show-for-small-up,tbody.show-for-small,tbody.show-for-small-down,tbody.hide-for-medium-only,tbody.hide-for-medium-up,tbody.hide-for-medium,tbody.show-for-medium-down,tbody.hide-for-large-only,tbody.hide-for-large-up,tbody.hide-for-large,tbody.show-for-large-down,tbody.hide-for-xlarge-only,tbody.hide-for-xlarge-up,tbody.hide-for-xlarge,tbody.show-for-xlarge-down,tbody.hide-for-xxlarge-only,tbody.hide-for-xxlarge-up,tbody.hide-for-xxlarge,tbody.show-for-xxlarge-down{display:table-row-group !important}tr.show-for-small-only,tr.show-for-small-up,tr.show-for-small,tr.show-for-small-down,tr.hide-for-medium-only,tr.hide-for-medium-up,tr.hide-for-medium,tr.show-for-medium-down,tr.hide-for-large-only,tr.hide-for-large-up,tr.hide-for-large,tr.show-for-large-down,tr.hide-for-xlarge-only,tr.hide-for-xlarge-up,tr.hide-for-xlarge,tr.show-for-xlarge-down,tr.hide-for-xxlarge-only,tr.hide-for-xxlarge-up,tr.hide-for-xxlarge,tr.show-for-xxlarge-down{display:table-row}th.show-for-small-only,td.show-for-small-only,th.show-for-small-up,td.show-for-small-up,th.show-for-small,td.show-for-small,th.show-for-small-down,td.show-for-small-down,th.hide-for-medium-only,td.hide-for-medium-only,th.hide-for-medium-up,td.hide-for-medium-up,th.hide-for-medium,td.hide-for-medium,th.show-for-medium-down,td.show-for-medium-down,th.hide-for-large-only,td.hide-for-large-only,th.hide-for-large-up,td.hide-for-large-up,th.hide-for-large,td.hide-for-large,th.show-for-large-down,td.show-for-large-down,th.hide-for-xlarge-only,td.hide-for-xlarge-only,th.hide-for-xlarge-up,td.hide-for-xlarge-up,th.hide-for-xlarge,td.hide-for-xlarge,th.show-for-xlarge-down,td.show-for-xlarge-down,th.hide-for-xxlarge-only,td.hide-for-xxlarge-only,th.hide-for-xxlarge-up,td.hide-for-xxlarge-up,th.hide-for-xxlarge,td.hide-for-xxlarge,th.show-for-xxlarge-down,td.show-for-xxlarge-down{display:table-cell !important}}@media only screen and (min-width: 40.063em){.hide-for-small-only,.show-for-small-up,.hide-for-small,.hide-for-small-down,.show-for-medium-only,.show-for-medium-up,.show-for-medium,.show-for-medium-down,.hide-for-large-only,.hide-for-large-up,.hide-for-large,.show-for-large-down,.hide-for-xlarge-only,.hide-for-xlarge-up,.hide-for-xlarge,.show-for-xlarge-down,.hide-for-xxlarge-only,.hide-for-xxlarge-up,.hide-for-xxlarge,.show-for-xxlarge-down{display:inherit !important}.show-for-small-only,.hide-for-small-up,.show-for-small,.show-for-small-down,.hide-for-medium-only,.hide-for-medium-up,.hide-for-medium,.hide-for-medium-down,.show-for-large-only,.show-for-large-up,.show-for-large,.hide-for-large-down,.show-for-xlarge-only,.show-for-xlarge-up,.show-for-xlarge,.hide-for-xlarge-down,.show-for-xxlarge-only,.show-for-xxlarge-up,.show-for-xxlarge,.hide-for-xxlarge-down{display:none !important}.hidden-for-small-only,.visible-for-small-up,.hidden-for-small,.hidden-for-small-down,.visible-for-medium-only,.visible-for-medium-up,.visible-for-medium,.visible-for-medium-down,.hidden-for-large-only,.hidden-for-large-up,.hidden-for-large,.visible-for-large-down,.hidden-for-xlarge-only,.hidden-for-xlarge-up,.hidden-for-xlarge,.visible-for-xlarge-down,.hidden-for-xxlarge-only,.hidden-for-xxlarge-up,.hidden-for-xxlarge,.visible-for-xxlarge-down{position:static !important;height:auto;width:auto;overflow:visible;clip:auto}.visible-for-small-only,.hidden-for-small-up,.visible-for-small,.visible-for-small-down,.hidden-for-medium-only,.hidden-for-medium-up,.hidden-for-medium,.hidden-for-medium-down,.visible-for-large-only,.visible-for-large-up,.visible-for-large,.hidden-for-large-down,.visible-for-xlarge-only,.visible-for-xlarge-up,.visible-for-xlarge,.hidden-for-xlarge-down,.visible-for-xxlarge-only,.visible-for-xxlarge-up,.visible-for-xxlarge,.hidden-for-xxlarge-down{position:absolute !important;height:1px;width:1px;overflow:hidden;clip:rect(1px, 1px, 1px, 1px)}table.hide-for-small-only,table.show-for-small-up,table.hide-for-small,table.hide-for-small-down,table.show-for-medium-only,table.show-for-medium-up,table.show-for-medium,table.show-for-medium-down,table.hide-for-large-only,table.hide-for-large-up,table.hide-for-large,table.show-for-large-down,table.hide-for-xlarge-only,table.hide-for-xlarge-up,table.hide-for-xlarge,table.show-for-xlarge-down,table.hide-for-xxlarge-only,table.hide-for-xxlarge-up,table.hide-for-xxlarge,table.show-for-xxlarge-down{display:table !important}thead.hide-for-small-only,thead.show-for-small-up,thead.hide-for-small,thead.hide-for-small-down,thead.show-for-medium-only,thead.show-for-medium-up,thead.show-for-medium,thead.show-for-medium-down,thead.hide-for-large-only,thead.hide-for-large-up,thead.hide-for-large,thead.show-for-large-down,thead.hide-for-xlarge-only,thead.hide-for-xlarge-up,thead.hide-for-xlarge,thead.show-for-xlarge-down,thead.hide-for-xxlarge-only,thead.hide-for-xxlarge-up,thead.hide-for-xxlarge,thead.show-for-xxlarge-down{display:table-header-group !important}tbody.hide-for-small-only,tbody.show-for-small-up,tbody.hide-for-small,tbody.hide-for-small-down,tbody.show-for-medium-only,tbody.show-for-medium-up,tbody.show-for-medium,tbody.show-for-medium-down,tbody.hide-for-large-only,tbody.hide-for-large-up,tbody.hide-for-large,tbody.show-for-large-down,tbody.hide-for-xlarge-only,tbody.hide-for-xlarge-up,tbody.hide-for-xlarge,tbody.show-for-xlarge-down,tbody.hide-for-xxlarge-only,tbody.hide-for-xxlarge-up,tbody.hide-for-xxlarge,tbody.show-for-xxlarge-down{display:table-row-group !important}tr.hide-for-small-only,tr.show-for-small-up,tr.hide-for-small,tr.hide-for-small-down,tr.show-for-medium-only,tr.show-for-medium-up,tr.show-for-medium,tr.show-for-medium-down,tr.hide-for-large-only,tr.hide-for-large-up,tr.hide-for-large,tr.show-for-large-down,tr.hide-for-xlarge-only,tr.hide-for-xlarge-up,tr.hide-for-xlarge,tr.show-for-xlarge-down,tr.hide-for-xxlarge-only,tr.hide-for-xxlarge-up,tr.hide-for-xxlarge,tr.show-for-xxlarge-down{display:table-row}th.hide-for-small-only,td.hide-for-small-only,th.show-for-small-up,td.show-for-small-up,th.hide-for-small,td.hide-for-small,th.hide-for-small-down,td.hide-for-small-down,th.show-for-medium-only,td.show-for-medium-only,th.show-for-medium-up,td.show-for-medium-up,th.show-for-medium,td.show-for-medium,th.show-for-medium-down,td.show-for-medium-down,th.hide-for-large-only,td.hide-for-large-only,th.hide-for-large-up,td.hide-for-large-up,th.hide-for-large,td.hide-for-large,th.show-for-large-down,td.show-for-large-down,th.hide-for-xlarge-only,td.hide-for-xlarge-only,th.hide-for-xlarge-up,td.hide-for-xlarge-up,th.hide-for-xlarge,td.hide-for-xlarge,th.show-for-xlarge-down,td.show-for-xlarge-down,th.hide-for-xxlarge-only,td.hide-for-xxlarge-only,th.hide-for-xxlarge-up,td.hide-for-xxlarge-up,th.hide-for-xxlarge,td.hide-for-xxlarge,th.show-for-xxlarge-down,td.show-for-xxlarge-down{display:table-cell !important}}@media only screen and (min-width: 64.063em){.hide-for-small-only,.show-for-small-up,.hide-for-small,.hide-for-small-down,.hide-for-medium-only,.show-for-medium-up,.hide-for-medium,.hide-for-medium-down,.show-for-large-only,.show-for-large-up,.show-for-large,.show-for-large-down,.hide-for-xlarge-only,.hide-for-xlarge-up,.hide-for-xlarge,.show-for-xlarge-down,.hide-for-xxlarge-only,.hide-for-xxlarge-up,.hide-for-xxlarge,.show-for-xxlarge-down{display:inherit !important}.show-for-small-only,.hide-for-small-up,.show-for-small,.show-for-small-down,.show-for-medium-only,.hide-for-medium-up,.show-for-medium,.show-for-medium-down,.hide-for-large-only,.hide-for-large-up,.hide-for-large,.hide-for-large-down,.show-for-xlarge-only,.show-for-xlarge-up,.show-for-xlarge,.hide-for-xlarge-down,.show-for-xxlarge-only,.show-for-xxlarge-up,.show-for-xxlarge,.hide-for-xxlarge-down{display:none !important}.hidden-for-small-only,.visible-for-small-up,.hidden-for-small,.hidden-for-small-down,.hidden-for-medium-only,.visible-for-medium-up,.hidden-for-medium,.hidden-for-medium-down,.visible-for-large-only,.visible-for-large-up,.visible-for-large,.visible-for-large-down,.hidden-for-xlarge-only,.hidden-for-xlarge-up,.hidden-for-xlarge,.visible-for-xlarge-down,.hidden-for-xxlarge-only,.hidden-for-xxlarge-up,.hidden-for-xxlarge,.visible-for-xxlarge-down{position:static !important;height:auto;width:auto;overflow:visible;clip:auto}.visible-for-small-only,.hidden-for-small-up,.visible-for-small,.visible-for-small-down,.visible-for-medium-only,.hidden-for-medium-up,.visible-for-medium,.visible-for-medium-down,.hidden-for-large-only,.hidden-for-large-up,.hidden-for-large,.hidden-for-large-down,.visible-for-xlarge-only,.visible-for-xlarge-up,.visible-for-xlarge,.hidden-for-xlarge-down,.visible-for-xxlarge-only,.visible-for-xxlarge-up,.visible-for-xxlarge,.hidden-for-xxlarge-down{position:absolute !important;height:1px;width:1px;overflow:hidden;clip:rect(1px, 1px, 1px, 1px)}table.hide-for-small-only,table.show-for-small-up,table.hide-for-small,table.hide-for-small-down,table.hide-for-medium-only,table.show-for-medium-up,table.hide-for-medium,table.hide-for-medium-down,table.show-for-large-only,table.show-for-large-up,table.show-for-large,table.show-for-large-down,table.hide-for-xlarge-only,table.hide-for-xlarge-up,table.hide-for-xlarge,table.show-for-xlarge-down,table.hide-for-xxlarge-only,table.hide-for-xxlarge-up,table.hide-for-xxlarge,table.show-for-xxlarge-down{display:table !important}thead.hide-for-small-only,thead.show-for-small-up,thead.hide-for-small,thead.hide-for-small-down,thead.hide-for-medium-only,thead.show-for-medium-up,thead.hide-for-medium,thead.hide-for-medium-down,thead.show-for-large-only,thead.show-for-large-up,thead.show-for-large,thead.show-for-large-down,thead.hide-for-xlarge-only,thead.hide-for-xlarge-up,thead.hide-for-xlarge,thead.show-for-xlarge-down,thead.hide-for-xxlarge-only,thead.hide-for-xxlarge-up,thead.hide-for-xxlarge,thead.show-for-xxlarge-down{display:table-header-group !important}tbody.hide-for-small-only,tbody.show-for-small-up,tbody.hide-for-small,tbody.hide-for-small-down,tbody.hide-for-medium-only,tbody.show-for-medium-up,tbody.hide-for-medium,tbody.hide-for-medium-down,tbody.show-for-large-only,tbody.show-for-large-up,tbody.show-for-large,tbody.show-for-large-down,tbody.hide-for-xlarge-only,tbody.hide-for-xlarge-up,tbody.hide-for-xlarge,tbody.show-for-xlarge-down,tbody.hide-for-xxlarge-only,tbody.hide-for-xxlarge-up,tbody.hide-for-xxlarge,tbody.show-for-xxlarge-down{display:table-row-group !important}tr.hide-for-small-only,tr.show-for-small-up,tr.hide-for-small,tr.hide-for-small-down,tr.hide-for-medium-only,tr.show-for-medium-up,tr.hide-for-medium,tr.hide-for-medium-down,tr.show-for-large-only,tr.show-for-large-up,tr.show-for-large,tr.show-for-large-down,tr.hide-for-xlarge-only,tr.hide-for-xlarge-up,tr.hide-for-xlarge,tr.show-for-xlarge-down,tr.hide-for-xxlarge-only,tr.hide-for-xxlarge-up,tr.hide-for-xxlarge,tr.show-for-xxlarge-down{display:table-row}th.hide-for-small-only,td.hide-for-small-only,th.show-for-small-up,td.show-for-small-up,th.hide-for-small,td.hide-for-small,th.hide-for-small-down,td.hide-for-small-down,th.hide-for-medium-only,td.hide-for-medium-only,th.show-for-medium-up,td.show-for-medium-up,th.hide-for-medium,td.hide-for-medium,th.hide-for-medium-down,td.hide-for-medium-down,th.show-for-large-only,td.show-for-large-only,th.show-for-large-up,td.show-for-large-up,th.show-for-large,td.show-for-large,th.show-for-large-down,td.show-for-large-down,th.hide-for-xlarge-only,td.hide-for-xlarge-only,th.hide-for-xlarge-up,td.hide-for-xlarge-up,th.hide-for-xlarge,td.hide-for-xlarge,th.show-for-xlarge-down,td.show-for-xlarge-down,th.hide-for-xxlarge-only,td.hide-for-xxlarge-only,th.hide-for-xxlarge-up,td.hide-for-xxlarge-up,th.hide-for-xxlarge,td.hide-for-xxlarge,th.show-for-xxlarge-down,td.show-for-xxlarge-down{display:table-cell !important}}@media only screen and (min-width: 90.063em){.hide-for-small-only,.show-for-small-up,.hide-for-small,.hide-for-small-down,.hide-for-medium-only,.show-for-medium-up,.hide-for-medium,.hide-for-medium-down,.hide-for-large-only,.show-for-large-up,.hide-for-large,.hide-for-large-down,.show-for-xlarge-only,.show-for-xlarge-up,.show-for-xlarge,.show-for-xlarge-down,.hide-for-xxlarge-only,.hide-for-xxlarge-up,.hide-for-xxlarge,.show-for-xxlarge-down{display:inherit !important}.show-for-small-only,.hide-for-small-up,.show-for-small,.show-for-small-down,.show-for-medium-only,.hide-for-medium-up,.show-for-medium,.show-for-medium-down,.show-for-large-only,.hide-for-large-up,.show-for-large,.show-for-large-down,.hide-for-xlarge-only,.hide-for-xlarge-up,.hide-for-xlarge,.hide-for-xlarge-down,.show-for-xxlarge-only,.show-for-xxlarge-up,.show-for-xxlarge,.hide-for-xxlarge-down{display:none !important}.hidden-for-small-only,.visible-for-small-up,.hidden-for-small,.hidden-for-small-down,.hidden-for-medium-only,.visible-for-medium-up,.hidden-for-medium,.hidden-for-medium-down,.hidden-for-large-only,.visible-for-large-up,.hidden-for-large,.hidden-for-large-down,.visible-for-xlarge-only,.visible-for-xlarge-up,.visible-for-xlarge,.visible-for-xlarge-down,.hidden-for-xxlarge-only,.hidden-for-xxlarge-up,.hidden-for-xxlarge,.visible-for-xxlarge-down{position:static !important;height:auto;width:auto;overflow:visible;clip:auto}.visible-for-small-only,.hidden-for-small-up,.visible-for-small,.visible-for-small-down,.visible-for-medium-only,.hidden-for-medium-up,.visible-for-medium,.visible-for-medium-down,.visible-for-large-only,.hidden-for-large-up,.visible-for-large,.visible-for-large-down,.hidden-for-xlarge-only,.hidden-for-xlarge-up,.hidden-for-xlarge,.hidden-for-xlarge-down,.visible-for-xxlarge-only,.visible-for-xxlarge-up,.visible-for-xxlarge,.hidden-for-xxlarge-down{position:absolute !important;height:1px;width:1px;overflow:hidden;clip:rect(1px, 1px, 1px, 1px)}table.hide-for-small-only,table.show-for-small-up,table.hide-for-small,table.hide-for-small-down,table.hide-for-medium-only,table.show-for-medium-up,table.hide-for-medium,table.hide-for-medium-down,table.hide-for-large-only,table.show-for-large-up,table.hide-for-large,table.hide-for-large-down,table.show-for-xlarge-only,table.show-for-xlarge-up,table.show-for-xlarge,table.show-for-xlarge-down,table.hide-for-xxlarge-only,table.hide-for-xxlarge-up,table.hide-for-xxlarge,table.show-for-xxlarge-down{display:table !important}thead.hide-for-small-only,thead.show-for-small-up,thead.hide-for-small,thead.hide-for-small-down,thead.hide-for-medium-only,thead.show-for-medium-up,thead.hide-for-medium,thead.hide-for-medium-down,thead.hide-for-large-only,thead.show-for-large-up,thead.hide-for-large,thead.hide-for-large-down,thead.show-for-xlarge-only,thead.show-for-xlarge-up,thead.show-for-xlarge,thead.show-for-xlarge-down,thead.hide-for-xxlarge-only,thead.hide-for-xxlarge-up,thead.hide-for-xxlarge,thead.show-for-xxlarge-down{display:table-header-group !important}tbody.hide-for-small-only,tbody.show-for-small-up,tbody.hide-for-small,tbody.hide-for-small-down,tbody.hide-for-medium-only,tbody.show-for-medium-up,tbody.hide-for-medium,tbody.hide-for-medium-down,tbody.hide-for-large-only,tbody.show-for-large-up,tbody.hide-for-large,tbody.hide-for-large-down,tbody.show-for-xlarge-only,tbody.show-for-xlarge-up,tbody.show-for-xlarge,tbody.show-for-xlarge-down,tbody.hide-for-xxlarge-only,tbody.hide-for-xxlarge-up,tbody.hide-for-xxlarge,tbody.show-for-xxlarge-down{display:table-row-group !important}tr.hide-for-small-only,tr.show-for-small-up,tr.hide-for-small,tr.hide-for-small-down,tr.hide-for-medium-only,tr.show-for-medium-up,tr.hide-for-medium,tr.hide-for-medium-down,tr.hide-for-large-only,tr.show-for-large-up,tr.hide-for-large,tr.hide-for-large-down,tr.show-for-xlarge-only,tr.show-for-xlarge-up,tr.show-for-xlarge,tr.show-for-xlarge-down,tr.hide-for-xxlarge-only,tr.hide-for-xxlarge-up,tr.hide-for-xxlarge,tr.show-for-xxlarge-down{display:table-row}th.hide-for-small-only,td.hide-for-small-only,th.show-for-small-up,td.show-for-small-up,th.hide-for-small,td.hide-for-small,th.hide-for-small-down,td.hide-for-small-down,th.hide-for-medium-only,td.hide-for-medium-only,th.show-for-medium-up,td.show-for-medium-up,th.hide-for-medium,td.hide-for-medium,th.hide-for-medium-down,td.hide-for-medium-down,th.hide-for-large-only,td.hide-for-large-only,th.show-for-large-up,td.show-for-large-up,th.hide-for-large,td.hide-for-large,th.hide-for-large-down,td.hide-for-large-down,th.show-for-xlarge-only,td.show-for-xlarge-only,th.show-for-xlarge-up,td.show-for-xlarge-up,th.show-for-xlarge,td.show-for-xlarge,th.show-for-xlarge-down,td.show-for-xlarge-down,th.hide-for-xxlarge-only,td.hide-for-xxlarge-only,th.hide-for-xxlarge-up,td.hide-for-xxlarge-up,th.hide-for-xxlarge,td.hide-for-xxlarge,th.show-for-xxlarge-down,td.show-for-xxlarge-down{display:table-cell !important}}@media only screen and (min-width: 120.063em){.hide-for-small-only,.show-for-small-up,.hide-for-small,.hide-for-small-down,.hide-for-medium-only,.show-for-medium-up,.hide-for-medium,.hide-for-medium-down,.hide-for-large-only,.show-for-large-up,.hide-for-large,.hide-for-large-down,.hide-for-xlarge-only,.show-for-xlarge-up,.hide-for-xlarge,.hide-for-xlarge-down,.show-for-xxlarge-only,.show-for-xxlarge-up,.show-for-xxlarge,.show-for-xxlarge-down{display:inherit !important}.show-for-small-only,.hide-for-small-up,.show-for-small,.show-for-small-down,.show-for-medium-only,.hide-for-medium-up,.show-for-medium,.show-for-medium-down,.show-for-large-only,.hide-for-large-up,.show-for-large,.show-for-large-down,.show-for-xlarge-only,.hide-for-xlarge-up,.show-for-xlarge,.show-for-xlarge-down,.hide-for-xxlarge-only,.hide-for-xxlarge-up,.hide-for-xxlarge,.hide-for-xxlarge-down{display:none !important}.hidden-for-small-only,.visible-for-small-up,.hidden-for-small,.hidden-for-small-down,.hidden-for-medium-only,.visible-for-medium-up,.hidden-for-medium,.hidden-for-medium-down,.hidden-for-large-only,.visible-for-large-up,.hidden-for-large,.hidden-for-large-down,.hidden-for-xlarge-only,.visible-for-xlarge-up,.hidden-for-xlarge,.hidden-for-xlarge-down,.visible-for-xxlarge-only,.visible-for-xxlarge-up,.visible-for-xxlarge,.visible-for-xxlarge-down{position:static !important;height:auto;width:auto;overflow:visible;clip:auto}.visible-for-small-only,.hidden-for-small-up,.visible-for-small,.visible-for-small-down,.visible-for-medium-only,.hidden-for-medium-up,.visible-for-medium,.visible-for-medium-down,.visible-for-large-only,.hidden-for-large-up,.visible-for-large,.visible-for-large-down,.visible-for-xlarge-only,.hidden-for-xlarge-up,.visible-for-xlarge,.visible-for-xlarge-down,.hidden-for-xxlarge-only,.hidden-for-xxlarge-up,.hidden-for-xxlarge,.hidden-for-xxlarge-down{position:absolute !important;height:1px;width:1px;overflow:hidden;clip:rect(1px, 1px, 1px, 1px)}table.hide-for-small-only,table.show-for-small-up,table.hide-for-small,table.hide-for-small-down,table.hide-for-medium-only,table.show-for-medium-up,table.hide-for-medium,table.hide-for-medium-down,table.hide-for-large-only,table.show-for-large-up,table.hide-for-large,table.hide-for-large-down,table.hide-for-xlarge-only,table.show-for-xlarge-up,table.hide-for-xlarge,table.hide-for-xlarge-down,table.show-for-xxlarge-only,table.show-for-xxlarge-up,table.show-for-xxlarge,table.show-for-xxlarge-down{display:table !important}thead.hide-for-small-only,thead.show-for-small-up,thead.hide-for-small,thead.hide-for-small-down,thead.hide-for-medium-only,thead.show-for-medium-up,thead.hide-for-medium,thead.hide-for-medium-down,thead.hide-for-large-only,thead.show-for-large-up,thead.hide-for-large,thead.hide-for-large-down,thead.hide-for-xlarge-only,thead.show-for-xlarge-up,thead.hide-for-xlarge,thead.hide-for-xlarge-down,thead.show-for-xxlarge-only,thead.show-for-xxlarge-up,thead.show-for-xxlarge,thead.show-for-xxlarge-down{display:table-header-group !important}tbody.hide-for-small-only,tbody.show-for-small-up,tbody.hide-for-small,tbody.hide-for-small-down,tbody.hide-for-medium-only,tbody.show-for-medium-up,tbody.hide-for-medium,tbody.hide-for-medium-down,tbody.hide-for-large-only,tbody.show-for-large-up,tbody.hide-for-large,tbody.hide-for-large-down,tbody.hide-for-xlarge-only,tbody.show-for-xlarge-up,tbody.hide-for-xlarge,tbody.hide-for-xlarge-down,tbody.show-for-xxlarge-only,tbody.show-for-xxlarge-up,tbody.show-for-xxlarge,tbody.show-for-xxlarge-down{display:table-row-group !important}tr.hide-for-small-only,tr.show-for-small-up,tr.hide-for-small,tr.hide-for-small-down,tr.hide-for-medium-only,tr.show-for-medium-up,tr.hide-for-medium,tr.hide-for-medium-down,tr.hide-for-large-only,tr.show-for-large-up,tr.hide-for-large,tr.hide-for-large-down,tr.hide-for-xlarge-only,tr.show-for-xlarge-up,tr.hide-for-xlarge,tr.hide-for-xlarge-down,tr.show-for-xxlarge-only,tr.show-for-xxlarge-up,tr.show-for-xxlarge,tr.show-for-xxlarge-down{display:table-row}th.hide-for-small-only,td.hide-for-small-only,th.show-for-small-up,td.show-for-small-up,th.hide-for-small,td.hide-for-small,th.hide-for-small-down,td.hide-for-small-down,th.hide-for-medium-only,td.hide-for-medium-only,th.show-for-medium-up,td.show-for-medium-up,th.hide-for-medium,td.hide-for-medium,th.hide-for-medium-down,td.hide-for-medium-down,th.hide-for-large-only,td.hide-for-large-only,th.show-for-large-up,td.show-for-large-up,th.hide-for-large,td.hide-for-large,th.hide-for-large-down,td.hide-for-large-down,th.hide-for-xlarge-only,td.hide-for-xlarge-only,th.show-for-xlarge-up,td.show-for-xlarge-up,th.hide-for-xlarge,td.hide-for-xlarge,th.hide-for-xlarge-down,td.hide-for-xlarge-down,th.show-for-xxlarge-only,td.show-for-xxlarge-only,th.show-for-xxlarge-up,td.show-for-xxlarge-up,th.show-for-xxlarge,td.show-for-xxlarge,th.show-for-xxlarge-down,td.show-for-xxlarge-down{display:table-cell !important}}.show-for-landscape,.hide-for-portrait{display:inherit !important}.hide-for-landscape,.show-for-portrait{display:none !important}table.hide-for-landscape,table.show-for-portrait{display:table !important}thead.hide-for-landscape,thead.show-for-portrait{display:table-header-group !important}tbody.hide-for-landscape,tbody.show-for-portrait{display:table-row-group !important}tr.hide-for-landscape,tr.show-for-portrait{display:table-row !important}td.hide-for-landscape,td.show-for-portrait,th.hide-for-landscape,th.show-for-portrait{display:table-cell !important}@media only screen and (orientation: landscape){.show-for-landscape,.hide-for-portrait{display:inherit !important}.hide-for-landscape,.show-for-portrait{display:none !important}table.show-for-landscape,table.hide-for-portrait{display:table !important}thead.show-for-landscape,thead.hide-for-portrait{display:table-header-group !important}tbody.show-for-landscape,tbody.hide-for-portrait{display:table-row-group !important}tr.show-for-landscape,tr.hide-for-portrait{display:table-row !important}td.show-for-landscape,td.hide-for-portrait,th.show-for-landscape,th.hide-for-portrait{display:table-cell !important}}@media only screen and (orientation: portrait){.show-for-portrait,.hide-for-landscape{display:inherit !important}.hide-for-portrait,.show-for-landscape{display:none !important}table.show-for-portrait,table.hide-for-landscape{display:table !important}thead.show-for-portrait,thead.hide-for-landscape{display:table-header-group !important}tbody.show-for-portrait,tbody.hide-for-landscape{display:table-row-group !important}tr.show-for-portrait,tr.hide-for-landscape{display:table-row !important}td.show-for-portrait,td.hide-for-landscape,th.show-for-portrait,th.hide-for-landscape{display:table-cell !important}}.show-for-touch{display:none !important}.hide-for-touch{display:inherit !important}.touch .show-for-touch{display:inherit !important}.touch .hide-for-touch{display:none !important}table.hide-for-touch{display:table !important}.touch table.show-for-touch{display:table !important}thead.hide-for-touch{display:table-header-group !important}.touch thead.show-for-touch{display:table-header-group !important}tbody.hide-for-touch{display:table-row-group !important}.touch tbody.show-for-touch{display:table-row-group !important}tr.hide-for-touch{display:table-row !important}.touch tr.show-for-touch{display:table-row !important}td.hide-for-touch{display:table-cell !important}.touch td.show-for-touch{display:table-cell !important}th.hide-for-touch{display:table-cell !important}.touch th.show-for-touch{display:table-cell !important}.print-only{display:none !important}@media print{*{background:transparent !important;color:#000 !important;box-shadow:none !important;text-shadow:none !important}.show-for-print{display:block}.hide-for-print{display:none}table.show-for-print{display:table !important}thead.show-for-print{display:table-header-group !important}tbody.show-for-print{display:table-row-group !important}tr.show-for-print{display:table-row !important}td.show-for-print{display:table-cell !important}th.show-for-print{display:table-cell !important}a,a:visited{text-decoration:underline}a[href]:after{content:" (" attr(href) ")"}abbr[title]:after{content:" (" attr(title) ")"}.ir a:after,a[href^="javascript:"]:after,a[href^="#"]:after{content:""}pre,blockquote{border:1px solid #999;page-break-inside:avoid}thead{display:table-header-group}tr,img{page-break-inside:avoid}img{max-width:100% !important}@page{margin:0.5cm}p,h2,h3{orphans:3;widows:3}h2,h3{page-break-after:avoid}.hide-on-print{display:none !important}.print-only{display:block !important}.hide-for-print{display:none !important}.show-for-print{display:inherit !important}}@media print{.show-for-print{display:block}.hide-for-print{display:none}table.show-for-print{display:table !important}thead.show-for-print{display:table-header-group !important}tbody.show-for-print{display:table-row-group !important}tr.show-for-print{display:table-row !important}td.show-for-print{display:table-cell !important}th.show-for-print{display:table-cell !important}}';

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

(function ($, window, document, undefined) {
  \'use strict\';

  var header_helpers = function (class_array) {
    var i = class_array.length;
    var head = $(\'head\');

    while (i--) {
      if (head.has(\'.\' + class_array[i]).length === 0) {
        head.append(\'<meta class="\' + class_array[i] + \'" />\');
      }
    }
  };

  header_helpers([
    \'foundation-mq-small\',
    \'foundation-mq-small-only\',
    \'foundation-mq-medium\',
    \'foundation-mq-medium-only\',
    \'foundation-mq-large\',
    \'foundation-mq-large-only\',
    \'foundation-mq-xlarge\',
    \'foundation-mq-xlarge-only\',
    \'foundation-mq-xxlarge\',
    \'foundation-data-attribute-namespace\']);

  // Enable FastClick if present

  $(function () {
    if (typeof FastClick !== \'undefined\') {
      // Don\'t attach to body if undefined
      if (typeof document.body !== \'undefined\') {
        FastClick.attach(document.body);
      }
    }
  });

  // private Fast Selector wrapper,
  // returns jQuery object. Only use where
  // getElementById is not available.
  var S = function (selector, context) {
    if (typeof selector === \'string\') {
      if (context) {
        var cont;
        if (context.jquery) {
          cont = context[0];
          if (!cont) {
            return context;
          }
        } else {
          cont = context;
        }
        return $(cont.querySelectorAll(selector));
      }

      return $(document.querySelectorAll(selector));
    }

    return $(selector, context);
  };

  // Namespace functions.

  var attr_name = function (init) {
    var arr = [];
    if (!init) {
      arr.push(\'data\');
    }
    if (this.namespace.length > 0) {
      arr.push(this.namespace);
    }
    arr.push(this.name);

    return arr.join(\'-\');
  };

  var add_namespace = function (str) {
    var parts = str.split(\'-\'),
        i = parts.length,
        arr = [];

    while (i--) {
      if (i !== 0) {
        arr.push(parts[i]);
      } else {
        if (this.namespace.length > 0) {
          arr.push(this.namespace, parts[i]);
        } else {
          arr.push(parts[i]);
        }
      }
    }

    return arr.reverse().join(\'-\');
  };

  // Event binding and data-options updating.

  var bindings = function (method, options) {
    var self = this,
        bind = function(){
          var $this = S(this),
              should_bind_events = !$this.data(self.attr_name(true) + \'-init\');
          $this.data(self.attr_name(true) + \'-init\', $.extend({}, self.settings, (options || method), self.data_options($this)));

          if (should_bind_events) {
            self.events(this);
          }
        };

    if (S(this.scope).is(\'[\' + this.attr_name() +\']\')) {
      bind.call(this.scope);
    } else {
      S(\'[\' + this.attr_name() +\']\', this.scope).each(bind);
    }
    // # Patch to fix #5043 to move this *after* the if/else clause in order for Backbone and similar frameworks to have improved control over event binding and data-options updating.
    if (typeof method === \'string\') {
      return this[method].call(this, options);
    }

  };

  var single_image_loaded = function (image, callback) {
    function loaded () {
      callback(image[0]);
    }

    function bindLoad () {
      this.one(\'load\', loaded);

      if (/MSIE (\\d+\\.\\d+);/.test(navigator.userAgent)) {
        var src = this.attr( \'src\' ),
            param = src.match( /\\?/ ) ? \'&\' : \'?\';

        param += \'random=\' + (new Date()).getTime();
        this.attr(\'src\', src + param);
      }
    }

    if (!image.attr(\'src\')) {
      loaded();
      return;
    }

    if (image[0].complete || image[0].readyState === 4) {
      loaded();
    } else {
      bindLoad.call(image);
    }
  };

  /*
    https://github.com/paulirish/matchMedia.js
  */

  window.matchMedia = window.matchMedia || (function ( doc ) {

    \'use strict\';

    var bool,
        docElem = doc.documentElement,
        refNode = docElem.firstElementChild || docElem.firstChild,
        // fakeBody required for <FF4 when executed in <head>
        fakeBody = doc.createElement( \'body\' ),
        div = doc.createElement( \'div\' );

    div.id = \'mq-test-1\';
    div.style.cssText = \'position:absolute;top:-100em\';
    fakeBody.style.background = \'none\';
    fakeBody.appendChild(div);

    return function (q) {

      div.innerHTML = \'&shy;<style media="\' + q + \'"> #mq-test-1 { width: 42px; }</style>\';

      docElem.insertBefore( fakeBody, refNode );
      bool = div.offsetWidth === 42;
      docElem.removeChild( fakeBody );

      return {
        matches : bool,
        media : q
      };

    };

  }( document ));

  /*
   * jquery.requestAnimationFrame
   * https://github.com/gnarf37/jquery-requestAnimationFrame
   * Requires jQuery 1.8+
   *
   * Copyright (c) 2012 Corey Frang
   * Licensed under the MIT license.
   */

  (function(jQuery) {


  // requestAnimationFrame polyfill adapted from Erik Möller
  // fixes from Paul Irish and Tino Zijdel
  // http://paulirish.com/2011/requestanimationframe-for-smart-animating/
  // http://my.opera.com/emoller/blog/2011/12/20/requestanimationframe-for-smart-er-animating

  var animating,
      lastTime = 0,
      vendors = [\'webkit\', \'moz\'],
      requestAnimationFrame = window.requestAnimationFrame,
      cancelAnimationFrame = window.cancelAnimationFrame,
      jqueryFxAvailable = \'undefined\' !== typeof jQuery.fx;

  for (; lastTime < vendors.length && !requestAnimationFrame; lastTime++) {
    requestAnimationFrame = window[ vendors[lastTime] + \'RequestAnimationFrame\' ];
    cancelAnimationFrame = cancelAnimationFrame ||
      window[ vendors[lastTime] + \'CancelAnimationFrame\' ] ||
      window[ vendors[lastTime] + \'CancelRequestAnimationFrame\' ];
  }

  function raf() {
    if (animating) {
      requestAnimationFrame(raf);

      if (jqueryFxAvailable) {
        jQuery.fx.tick();
      }
    }
  }

  if (requestAnimationFrame) {
    // use rAF
    window.requestAnimationFrame = requestAnimationFrame;
    window.cancelAnimationFrame = cancelAnimationFrame;

    if (jqueryFxAvailable) {
      jQuery.fx.timer = function (timer) {
        if (timer() && jQuery.timers.push(timer) && !animating) {
          animating = true;
          raf();
        }
      };

      jQuery.fx.stop = function () {
        animating = false;
      };
    }
  } else {
    // polyfill
    window.requestAnimationFrame = function (callback) {
      var currTime = new Date().getTime(),
        timeToCall = Math.max(0, 16 - (currTime - lastTime)),
        id = window.setTimeout(function () {
          callback(currTime + timeToCall);
        }, timeToCall);
      lastTime = currTime + timeToCall;
      return id;
    };

    window.cancelAnimationFrame = function (id) {
      clearTimeout(id);
    };

  }

  }( $ ));

  function removeQuotes (string) {
    if (typeof string === \'string\' || string instanceof String) {
      string = string.replace(/^[\'\\\\/"]+|(;\\s?})+|[\'\\\\/"]+$/g, \'\');
    }

    return string;
  }

  window.Foundation = {
    name : \'Foundation\',

    version : \'5.5.1\',

    media_queries : {
      \'small\'       : S(\'.foundation-mq-small\').css(\'font-family\').replace(/^[\\/\\\\\'"]+|(;\\s?})+|[\\/\\\\\'"]+$/g, \'\'),
      \'small-only\'  : S(\'.foundation-mq-small-only\').css(\'font-family\').replace(/^[\\/\\\\\'"]+|(;\\s?})+|[\\/\\\\\'"]+$/g, \'\'),
      \'medium\'      : S(\'.foundation-mq-medium\').css(\'font-family\').replace(/^[\\/\\\\\'"]+|(;\\s?})+|[\\/\\\\\'"]+$/g, \'\'),
      \'medium-only\' : S(\'.foundation-mq-medium-only\').css(\'font-family\').replace(/^[\\/\\\\\'"]+|(;\\s?})+|[\\/\\\\\'"]+$/g, \'\'),
      \'large\'       : S(\'.foundation-mq-large\').css(\'font-family\').replace(/^[\\/\\\\\'"]+|(;\\s?})+|[\\/\\\\\'"]+$/g, \'\'),
      \'large-only\'  : S(\'.foundation-mq-large-only\').css(\'font-family\').replace(/^[\\/\\\\\'"]+|(;\\s?})+|[\\/\\\\\'"]+$/g, \'\'),
      \'xlarge\'      : S(\'.foundation-mq-xlarge\').css(\'font-family\').replace(/^[\\/\\\\\'"]+|(;\\s?})+|[\\/\\\\\'"]+$/g, \'\'),
      \'xlarge-only\' : S(\'.foundation-mq-xlarge-only\').css(\'font-family\').replace(/^[\\/\\\\\'"]+|(;\\s?})+|[\\/\\\\\'"]+$/g, \'\'),
      \'xxlarge\'     : S(\'.foundation-mq-xxlarge\').css(\'font-family\').replace(/^[\\/\\\\\'"]+|(;\\s?})+|[\\/\\\\\'"]+$/g, \'\')
    },

    stylesheet : $(\'<style></style>\').appendTo(\'head\')[0].sheet,

    global : {
      namespace : undefined
    },

    init : function (scope, libraries, method, options, response) {
      var args = [scope, method, options, response],
          responses = [];

      // check RTL
      this.rtl = /rtl/i.test(S(\'html\').attr(\'dir\'));

      // set foundation global scope
      this.scope = scope || this.scope;

      this.set_namespace();

      if (libraries && typeof libraries === \'string\' && !/reflow/i.test(libraries)) {
        if (this.libs.hasOwnProperty(libraries)) {
          responses.push(this.init_lib(libraries, args));
        }
      } else {
        for (var lib in this.libs) {
          responses.push(this.init_lib(lib, libraries));
        }
      }

      S(window).load(function () {
        S(window)
          .trigger(\'resize.fndtn.clearing\')
          .trigger(\'resize.fndtn.dropdown\')
          .trigger(\'resize.fndtn.equalizer\')
          .trigger(\'resize.fndtn.interchange\')
          .trigger(\'resize.fndtn.joyride\')
          .trigger(\'resize.fndtn.magellan\')
          .trigger(\'resize.fndtn.topbar\')
          .trigger(\'resize.fndtn.slider\');
      });

      return scope;
    },

    init_lib : function (lib, args) {
      if (this.libs.hasOwnProperty(lib)) {
        this.patch(this.libs[lib]);

        if (args && args.hasOwnProperty(lib)) {
            if (typeof this.libs[lib].settings !== \'undefined\') {
              $.extend(true, this.libs[lib].settings, args[lib]);
            } else if (typeof this.libs[lib].defaults !== \'undefined\') {
              $.extend(true, this.libs[lib].defaults, args[lib]);
            }
          return this.libs[lib].init.apply(this.libs[lib], [this.scope, args[lib]]);
        }

        args = args instanceof Array ? args : new Array(args);
        return this.libs[lib].init.apply(this.libs[lib], args);
      }

      return function () {};
    },

    patch : function (lib) {
      lib.scope = this.scope;
      lib.namespace = this.global.namespace;
      lib.rtl = this.rtl;
      lib[\'data_options\'] = this.utils.data_options;
      lib[\'attr_name\'] = attr_name;
      lib[\'add_namespace\'] = add_namespace;
      lib[\'bindings\'] = bindings;
      lib[\'S\'] = this.utils.S;
    },

    inherit : function (scope, methods) {
      var methods_arr = methods.split(\' \'),
          i = methods_arr.length;

      while (i--) {
        if (this.utils.hasOwnProperty(methods_arr[i])) {
          scope[methods_arr[i]] = this.utils[methods_arr[i]];
        }
      }
    },

    set_namespace : function () {

      // Description:
      //    Don\'t bother reading the namespace out of the meta tag
      //    if the namespace has been set globally in javascript
      //
      // Example:
      //    Foundation.global.namespace = \'my-namespace\';
      // or make it an empty string:
      //    Foundation.global.namespace = \'\';
      //
      //

      // If the namespace has not been set (is undefined), try to read it out of the meta element.
      // Otherwise use the globally defined namespace, even if it\'s empty (\'\')
      var namespace = ( this.global.namespace === undefined ) ? $(\'.foundation-data-attribute-namespace\').css(\'font-family\') : this.global.namespace;

      // Finally, if the namsepace is either undefined or false, set it to an empty string.
      // Otherwise use the namespace value.
      this.global.namespace = ( namespace === undefined || /false/i.test(namespace) ) ? \'\' : namespace;
    },

    libs : {},

    // methods that can be inherited in libraries
    utils : {

      // Description:
      //    Fast Selector wrapper returns jQuery object. Only use where getElementById
      //    is not available.
      //
      // Arguments:
      //    Selector (String): CSS selector describing the element(s) to be
      //    returned as a jQuery object.
      //
      //    Scope (String): CSS selector describing the area to be searched. Default
      //    is document.
      //
      // Returns:
      //    Element (jQuery Object): jQuery object containing elements matching the
      //    selector within the scope.
      S : S,

      // Description:
      //    Executes a function a max of once every n milliseconds
      //
      // Arguments:
      //    Func (Function): Function to be throttled.
      //
      //    Delay (Integer): Function execution threshold in milliseconds.
      //
      // Returns:
      //    Lazy_function (Function): Function with throttling applied.
      throttle : function (func, delay) {
        var timer = null;

        return function () {
          var context = this, args = arguments;

          if (timer == null) {
            timer = setTimeout(function () {
              func.apply(context, args);
              timer = null;
            }, delay);
          }
        };
      },

      // Description:
      //    Executes a function when it stops being invoked for n seconds
      //    Modified version of _.debounce() http://underscorejs.org
      //
      // Arguments:
      //    Func (Function): Function to be debounced.
      //
      //    Delay (Integer): Function execution threshold in milliseconds.
      //
      //    Immediate (Bool): Whether the function should be called at the beginning
      //    of the delay instead of the end. Default is false.
      //
      // Returns:
      //    Lazy_function (Function): Function with debouncing applied.
      debounce : function (func, delay, immediate) {
        var timeout, result;
        return function () {
          var context = this, args = arguments;
          var later = function () {
            timeout = null;
            if (!immediate) {
              result = func.apply(context, args);
            }
          };
          var callNow = immediate && !timeout;
          clearTimeout(timeout);
          timeout = setTimeout(later, delay);
          if (callNow) {
            result = func.apply(context, args);
          }
          return result;
        };
      },

      // Description:
      //    Parses data-options attribute
      //
      // Arguments:
      //    El (jQuery Object): Element to be parsed.
      //
      // Returns:
      //    Options (Javascript Object): Contents of the element\'s data-options
      //    attribute.
      data_options : function (el, data_attr_name) {
        data_attr_name = data_attr_name || \'options\';
        var opts = {}, ii, p, opts_arr,
            data_options = function (el) {
              var namespace = Foundation.global.namespace;

              if (namespace.length > 0) {
                return el.data(namespace + \'-\' + data_attr_name);
              }

              return el.data(data_attr_name);
            };

        var cached_options = data_options(el);

        if (typeof cached_options === \'object\') {
          return cached_options;
        }

        opts_arr = (cached_options || \':\').split(\';\');
        ii = opts_arr.length;

        function isNumber (o) {
          return !isNaN (o - 0) && o !== null && o !== \'\' && o !== false && o !== true;
        }

        function trim (str) {
          if (typeof str === \'string\') {
            return $.trim(str);
          }
          return str;
        }

        while (ii--) {
          p = opts_arr[ii].split(\':\');
          p = [p[0], p.slice(1).join(\':\')];

          if (/true/i.test(p[1])) {
            p[1] = true;
          }
          if (/false/i.test(p[1])) {
            p[1] = false;
          }
          if (isNumber(p[1])) {
            if (p[1].indexOf(\'.\') === -1) {
              p[1] = parseInt(p[1], 10);
            } else {
              p[1] = parseFloat(p[1]);
            }
          }

          if (p.length === 2 && p[0].length > 0) {
            opts[trim(p[0])] = trim(p[1]);
          }
        }

        return opts;
      },

      // Description:
      //    Adds JS-recognizable media queries
      //
      // Arguments:
      //    Media (String): Key string for the media query to be stored as in
      //    Foundation.media_queries
      //
      //    Class (String): Class name for the generated <meta> tag
      register_media : function (media, media_class) {
        if (Foundation.media_queries[media] === undefined) {
          $(\'head\').append(\'<meta class="\' + media_class + \'"/>\');
          Foundation.media_queries[media] = removeQuotes($(\'.\' + media_class).css(\'font-family\'));
        }
      },

      // Description:
      //    Add custom CSS within a JS-defined media query
      //
      // Arguments:
      //    Rule (String): CSS rule to be appended to the document.
      //
      //    Media (String): Optional media query string for the CSS rule to be
      //    nested under.
      add_custom_rule : function (rule, media) {
        if (media === undefined && Foundation.stylesheet) {
          Foundation.stylesheet.insertRule(rule, Foundation.stylesheet.cssRules.length);
        } else {
          var query = Foundation.media_queries[media];

          if (query !== undefined) {
            Foundation.stylesheet.insertRule(\'@media \' +
              Foundation.media_queries[media] + \'{ \' + rule + \' }\');
          }
        }
      },

      // Description:
      //    Performs a callback function when an image is fully loaded
      //
      // Arguments:
      //    Image (jQuery Object): Image(s) to check if loaded.
      //
      //    Callback (Function): Function to execute when image is fully loaded.
      image_loaded : function (images, callback) {
        var self = this,
            unloaded = images.length;

        if (unloaded === 0) {
          callback(images);
        }

        images.each(function () {
          single_image_loaded(self.S(this), function () {
            unloaded -= 1;
            if (unloaded === 0) {
              callback(images);
            }
          });
        });
      },

      // Description:
      //    Returns a random, alphanumeric string
      //
      // Arguments:
      //    Length (Integer): Length of string to be generated. Defaults to random
      //    integer.
      //
      // Returns:
      //    Rand (String): Pseudo-random, alphanumeric string.
      random_str : function () {
        if (!this.fidx) {
          this.fidx = 0;
        }
        this.prefix = this.prefix || [(this.name || \'F\'), (+new Date).toString(36)].join(\'-\');

        return this.prefix + (this.fidx++).toString(36);
      },

      // Description:
      //    Helper for window.matchMedia
      //
      // Arguments:
      //    mq (String): Media query
      //
      // Returns:
      //    (Boolean): Whether the media query passes or not
      match : function (mq) {
        return window.matchMedia(mq).matches;
      },

      // Description:
      //    Helpers for checking Foundation default media queries with JS
      //
      // Returns:
      //    (Boolean): Whether the media query passes or not

      is_small_up : function () {
        return this.match(Foundation.media_queries.small);
      },

      is_medium_up : function () {
        return this.match(Foundation.media_queries.medium);
      },

      is_large_up : function () {
        return this.match(Foundation.media_queries.large);
      },

      is_xlarge_up : function () {
        return this.match(Foundation.media_queries.xlarge);
      },

      is_xxlarge_up : function () {
        return this.match(Foundation.media_queries.xxlarge);
      },

      is_small_only : function () {
        return !this.is_medium_up() && !this.is_large_up() && !this.is_xlarge_up() && !this.is_xxlarge_up();
      },

      is_medium_only : function () {
        return this.is_medium_up() && !this.is_large_up() && !this.is_xlarge_up() && !this.is_xxlarge_up();
      },

      is_large_only : function () {
        return this.is_medium_up() && this.is_large_up() && !this.is_xlarge_up() && !this.is_xxlarge_up();
      },

      is_xlarge_only : function () {
        return this.is_medium_up() && this.is_large_up() && this.is_xlarge_up() && !this.is_xxlarge_up();
      },

      is_xxlarge_only : function () {
        return this.is_medium_up() && this.is_large_up() && this.is_xlarge_up() && this.is_xxlarge_up();
      }
    }
  };

  $.fn.foundation = function () {
    var args = Array.prototype.slice.call(arguments, 0);

    return this.each(function () {
      Foundation.init.apply(Foundation, [this].concat(args));
      return this;
    });
  };

}(jQuery, window, window.document));
;(function ($, window, document, undefined) {
  \'use strict\';

  Foundation.libs.slider = {
    name : \'slider\',

    version : \'5.5.1\',

    settings : {
      start : 0,
      end : 100,
      step : 1,
      precision : null,
      initial : null,
      display_selector : \'\',
      vertical : false,
      trigger_input_change : false,
      on_change : function () {}
    },

    cache : {},

    init : function (scope, method, options) {
      Foundation.inherit(this, \'throttle\');
      this.bindings(method, options);
      this.reflow();
    },

    events : function () {
      var self = this;

      $(this.scope)
        .off(\'.slider\')
        .on(\'mousedown.fndtn.slider touchstart.fndtn.slider pointerdown.fndtn.slider\',
        \'[\' + self.attr_name() + \']:not(.disabled, [disabled]) .range-slider-handle\', function (e) {
          if (!self.cache.active) {
            e.preventDefault();
            self.set_active_slider($(e.target));
          }
        })
        .on(\'mousemove.fndtn.slider touchmove.fndtn.slider pointermove.fndtn.slider\', function (e) {
          if (!!self.cache.active) {
            e.preventDefault();
            if ($.data(self.cache.active[0], \'settings\').vertical) {
              var scroll_offset = 0;
              if (!e.pageY) {
                scroll_offset = window.scrollY;
              }
              self.calculate_position(self.cache.active, self.get_cursor_position(e, \'y\') + scroll_offset);
            } else {
              self.calculate_position(self.cache.active, self.get_cursor_position(e, \'x\'));
            }
          }
        })
        .on(\'mouseup.fndtn.slider touchend.fndtn.slider pointerup.fndtn.slider\', function (e) {
          self.remove_active_slider();
        })
        .on(\'change.fndtn.slider\', function (e) {
          self.settings.on_change();
        });

      self.S(window)
        .on(\'resize.fndtn.slider\', self.throttle(function (e) {
          self.reflow();
        }, 300));
    },

    get_cursor_position : function (e, xy) {
      var pageXY = \'page\' + xy.toUpperCase(),
          clientXY = \'client\' + xy.toUpperCase(),
          position;

      if (typeof e[pageXY] !== \'undefined\') {
        position = e[pageXY];
      } else if (typeof e.originalEvent[clientXY] !== \'undefined\') {
        position = e.originalEvent[clientXY];
      } else if (e.originalEvent.touches && e.originalEvent.touches[0] && typeof e.originalEvent.touches[0][clientXY] !== \'undefined\') {
        position = e.originalEvent.touches[0][clientXY];
      } else if (e.currentPoint && typeof e.currentPoint[xy] !== \'undefined\') {
        position = e.currentPoint[xy];
      }

      return position;
    },

    set_active_slider : function ($handle) {
      this.cache.active = $handle;
    },

    remove_active_slider : function () {
      this.cache.active = null;
    },

    calculate_position : function ($handle, cursor_x) {
      var self = this,
          settings = $.data($handle[0], \'settings\'),
          handle_l = $.data($handle[0], \'handle_l\'),
          handle_o = $.data($handle[0], \'handle_o\'),
          bar_l = $.data($handle[0], \'bar_l\'),
          bar_o = $.data($handle[0], \'bar_o\');

      requestAnimationFrame(function () {
        var pct;

        if (Foundation.rtl && !settings.vertical) {
          pct = self.limit_to(((bar_o + bar_l - cursor_x) / bar_l), 0, 1);
        } else {
          pct = self.limit_to(((cursor_x - bar_o) / bar_l), 0, 1);
        }

        pct = settings.vertical ? 1 - pct : pct;

        var norm = self.normalized_value(pct, settings.start, settings.end, settings.step, settings.precision);

        self.set_ui($handle, norm);
      });
    },

    set_ui : function ($handle, value) {
      var settings = $.data($handle[0], \'settings\'),
          handle_l = $.data($handle[0], \'handle_l\'),
          bar_l = $.data($handle[0], \'bar_l\'),
          norm_pct = this.normalized_percentage(value, settings.start, settings.end),
          handle_offset = norm_pct * (bar_l - handle_l) - 1,
          progress_bar_length = norm_pct * 100,
          $handle_parent = $handle.parent(),
          $hidden_inputs = $handle.parent().children(\'input[type=hidden]\');

      if (Foundation.rtl && !settings.vertical) {
        handle_offset = -handle_offset;
      }

      handle_offset = settings.vertical ? -handle_offset + bar_l - handle_l + 1 : handle_offset;
      this.set_translate($handle, handle_offset, settings.vertical);

      if (settings.vertical) {
        $handle.siblings(\'.range-slider-active-segment\').css(\'height\', progress_bar_length + \'%\');
      } else {
        $handle.siblings(\'.range-slider-active-segment\').css(\'width\', progress_bar_length + \'%\');
      }

      $handle_parent.attr(this.attr_name(), value).trigger(\'change\').trigger(\'change.fndtn.slider\');

      $hidden_inputs.val(value);
      if (settings.trigger_input_change) {
          $hidden_inputs.trigger(\'change\');
      }

      if (!$handle[0].hasAttribute(\'aria-valuemin\')) {
        $handle.attr({
          \'aria-valuemin\' : settings.start,
          \'aria-valuemax\' : settings.end
        });
      }
      $handle.attr(\'aria-valuenow\', value);

      if (settings.display_selector != \'\') {
        $(settings.display_selector).each(function () {
          if (this.hasOwnProperty(\'value\')) {
            $(this).val(value);
          } else {
            $(this).text(value);
          }
        });
      }

    },

    normalized_percentage : function (val, start, end) {
      return Math.min(1, (val - start) / (end - start));
    },

    normalized_value : function (val, start, end, step, precision) {
      var range = end - start,
          point = val * range,
          mod = (point - (point % step)) / step,
          rem = point % step,
          round = ( rem >= step * 0.5 ? step : 0);
      return ((mod * step + round) + start).toFixed(precision);
    },

    set_translate : function (ele, offset, vertical) {
      if (vertical) {
        $(ele)
          .css(\'-webkit-transform\', \'translateY(\' + offset + \'px)\')
          .css(\'-moz-transform\', \'translateY(\' + offset + \'px)\')
          .css(\'-ms-transform\', \'translateY(\' + offset + \'px)\')
          .css(\'-o-transform\', \'translateY(\' + offset + \'px)\')
          .css(\'transform\', \'translateY(\' + offset + \'px)\');
      } else {
        $(ele)
          .css(\'-webkit-transform\', \'translateX(\' + offset + \'px)\')
          .css(\'-moz-transform\', \'translateX(\' + offset + \'px)\')
          .css(\'-ms-transform\', \'translateX(\' + offset + \'px)\')
          .css(\'-o-transform\', \'translateX(\' + offset + \'px)\')
          .css(\'transform\', \'translateX(\' + offset + \'px)\');
      }
    },

    limit_to : function (val, min, max) {
      return Math.min(Math.max(val, min), max);
    },

    initialize_settings : function (handle) {
      var settings = $.extend({}, this.settings, this.data_options($(handle).parent())),
          decimal_places_match_result;

      if (settings.precision === null) {
        decimal_places_match_result = (\'\' + settings.step).match(/\\.([\\d]*)/);
        settings.precision = decimal_places_match_result && decimal_places_match_result[1] ? decimal_places_match_result[1].length : 0;
      }

      if (settings.vertical) {
        $.data(handle, \'bar_o\', $(handle).parent().offset().top);
        $.data(handle, \'bar_l\', $(handle).parent().outerHeight());
        $.data(handle, \'handle_o\', $(handle).offset().top);
        $.data(handle, \'handle_l\', $(handle).outerHeight());
      } else {
        $.data(handle, \'bar_o\', $(handle).parent().offset().left);
        $.data(handle, \'bar_l\', $(handle).parent().outerWidth());
        $.data(handle, \'handle_o\', $(handle).offset().left);
        $.data(handle, \'handle_l\', $(handle).outerWidth());
      }

      $.data(handle, \'bar\', $(handle).parent());
      $.data(handle, \'settings\', settings);
    },

    set_initial_position : function ($ele) {
      var settings = $.data($ele.children(\'.range-slider-handle\')[0], \'settings\'),
          initial = ((typeof settings.initial == \'number\' && !isNaN(settings.initial)) ? settings.initial : Math.floor((settings.end - settings.start) * 0.5 / settings.step) * settings.step + settings.start),
          $handle = $ele.children(\'.range-slider-handle\');
      this.set_ui($handle, initial);
    },

    set_value : function (value) {
      var self = this;
      $(\'[\' + self.attr_name() + \']\', this.scope).each(function () {
        $(this).attr(self.attr_name(), value);
      });
      if (!!$(this.scope).attr(self.attr_name())) {
        $(this.scope).attr(self.attr_name(), value);
      }
      self.reflow();
    },

    reflow : function () {
      var self = this;
      self.S(\'[\' + this.attr_name() + \']\').each(function () {
        var handle = $(this).children(\'.range-slider-handle\')[0],
            val = $(this).attr(self.attr_name());
        self.initialize_settings(handle);

        if (val) {
          self.set_ui($(handle), parseFloat(val));
        } else {
          self.set_initial_position($(this));
        }
      });
    }
  };

}(jQuery, window, window.document));
;(function ($, window, document, undefined) {
  \'use strict\';

  var Modernizr = Modernizr || false;

  Foundation.libs.joyride = {
    name : \'joyride\',

    version : \'5.5.1\',

    defaults : {
      expose                   : false,     // turn on or off the expose feature
      modal                    : true,      // Whether to cover page with modal during the tour
      keyboard                 : true,      // enable left, right and esc keystrokes
      tip_location             : \'bottom\',  // \'top\' or \'bottom\' in relation to parent
      nub_position             : \'auto\',    // override on a per tooltip bases
      scroll_speed             : 1500,      // Page scrolling speed in milliseconds, 0 = no scroll animation
      scroll_animation         : \'linear\',  // supports \'swing\' and \'linear\', extend with jQuery UI.
      timer                    : 0,         // 0 = no timer , all other numbers = timer in milliseconds
      start_timer_on_click     : true,      // true or false - true requires clicking the first button start the timer
      start_offset             : 0,         // the index of the tooltip you want to start on (index of the li)
      next_button              : true,      // true or false to control whether a next button is used
      prev_button              : true,      // true or false to control whether a prev button is used
      tip_animation            : \'fade\',    // \'pop\' or \'fade\' in each tip
      pause_after              : [],        // array of indexes where to pause the tour after
      exposed                  : [],        // array of expose elements
      tip_animation_fade_speed : 300,       // when tipAnimation = \'fade\' this is speed in milliseconds for the transition
      cookie_monster           : false,     // true or false to control whether cookies are used
      cookie_name              : \'joyride\', // Name the cookie you\'ll use
      cookie_domain            : false,     // Will this cookie be attached to a domain, ie. \'.notableapp.com\'
      cookie_expires           : 365,       // set when you would like the cookie to expire.
      tip_container            : \'body\',    // Where will the tip be attached
      abort_on_close           : true,      // When true, the close event will not fire any callback
      tip_location_patterns    : {
        top : [\'bottom\'],
        bottom : [], // bottom should not need to be repositioned
        left : [\'right\', \'top\', \'bottom\'],
        right : [\'left\', \'top\', \'bottom\']
      },
      post_ride_callback     : function () {},    // A method to call once the tour closes (canceled or complete)
      post_step_callback     : function () {},    // A method to call after each step
      pre_step_callback      : function () {},    // A method to call before each step
      pre_ride_callback      : function () {},    // A method to call before the tour starts (passed index, tip, and cloned exposed element)
      post_expose_callback   : function () {},    // A method to call after an element has been exposed
      template : { // HTML segments for tip layout
        link          : \'<a href="#close" class="joyride-close-tip">&times;</a>\',
        timer         : \'<div class="joyride-timer-indicator-wrap"><span class="joyride-timer-indicator"></span></div>\',
        tip           : \'<div class="joyride-tip-guide"><span class="joyride-nub"></span></div>\',
        wrapper       : \'<div class="joyride-content-wrapper"></div>\',
        button        : \'<a href="#" class="small button joyride-next-tip"></a>\',
        prev_button   : \'<a href="#" class="small button joyride-prev-tip"></a>\',
        modal         : \'<div class="joyride-modal-bg"></div>\',
        expose        : \'<div class="joyride-expose-wrapper"></div>\',
        expose_cover  : \'<div class="joyride-expose-cover"></div>\'
      },
      expose_add_class : \'\' // One or more space-separated class names to be added to exposed element
    },

    init : function (scope, method, options) {
      Foundation.inherit(this, \'throttle random_str\');

      this.settings = this.settings || $.extend({}, this.defaults, (options || method));

      this.bindings(method, options)
    },

    go_next : function () {
      if (this.settings.$li.next().length < 1) {
        this.end();
      } else if (this.settings.timer > 0) {
        clearTimeout(this.settings.automate);
        this.hide();
        this.show();
        this.startTimer();
      } else {
        this.hide();
        this.show();
      }
    },

    go_prev : function () {
      if (this.settings.$li.prev().length < 1) {
        // Do nothing if there are no prev element
      } else if (this.settings.timer > 0) {
        clearTimeout(this.settings.automate);
        this.hide();
        this.show(null, true);
        this.startTimer();
      } else {
        this.hide();
        this.show(null, true);
      }
    },

    events : function () {
      var self = this;

      $(this.scope)
        .off(\'.joyride\')
        .on(\'click.fndtn.joyride\', \'.joyride-next-tip, .joyride-modal-bg\', function (e) {
          e.preventDefault();
          this.go_next()
        }.bind(this))
        .on(\'click.fndtn.joyride\', \'.joyride-prev-tip\', function (e) {
          e.preventDefault();
          this.go_prev();
        }.bind(this))

        .on(\'click.fndtn.joyride\', \'.joyride-close-tip\', function (e) {
          e.preventDefault();
          this.end(this.settings.abort_on_close);
        }.bind(this))

        .on(\'keyup.fndtn.joyride\', function (e) {
          // Don\'t do anything if keystrokes are disabled
          // or if the joyride is not being shown
          if (!this.settings.keyboard || !this.settings.riding) {
            return;
          }

          switch (e.which) {
            case 39: // right arrow
              e.preventDefault();
              this.go_next();
              break;
            case 37: // left arrow
              e.preventDefault();
              this.go_prev();
              break;
            case 27: // escape
              e.preventDefault();
              this.end(this.settings.abort_on_close);
          }
        }.bind(this));

      $(window)
        .off(\'.joyride\')
        .on(\'resize.fndtn.joyride\', self.throttle(function () {
          if ($(\'[\' + self.attr_name() + \']\').length > 0 && self.settings.$next_tip && self.settings.riding) {
            if (self.settings.exposed.length > 0) {
              var $els = $(self.settings.exposed);

              $els.each(function () {
                var $this = $(this);
                self.un_expose($this);
                self.expose($this);
              });
            }

            if (self.is_phone()) {
              self.pos_phone();
            } else {
              self.pos_default(false);
            }
          }
        }, 100));
    },

    start : function () {
      var self = this,
          $this = $(\'[\' + this.attr_name() + \']\', this.scope),
          integer_settings = [\'timer\', \'scrollSpeed\', \'startOffset\', \'tipAnimationFadeSpeed\', \'cookieExpires\'],
          int_settings_count = integer_settings.length;

      if (!$this.length > 0) {
        return;
      }

      if (!this.settings.init) {
        this.events();
      }

      this.settings = $this.data(this.attr_name(true) + \'-init\');

      // non configureable settings
      this.settings.$content_el = $this;
      this.settings.$body = $(this.settings.tip_container);
      this.settings.body_offset = $(this.settings.tip_container).position();
      this.settings.$tip_content = this.settings.$content_el.find(\'> li\');
      this.settings.paused = false;
      this.settings.attempts = 0;
      this.settings.riding = true;

      // can we create cookies?
      if (typeof $.cookie !== \'function\') {
        this.settings.cookie_monster = false;
      }

      // generate the tips and insert into dom.
      if (!this.settings.cookie_monster || this.settings.cookie_monster && !$.cookie(this.settings.cookie_name)) {
        this.settings.$tip_content.each(function (index) {
          var $this = $(this);
          this.settings = $.extend({}, self.defaults, self.data_options($this));

          // Make sure that settings parsed from data_options are integers where necessary
          var i = int_settings_count;
          while (i--) {
            self.settings[integer_settings[i]] = parseInt(self.settings[integer_settings[i]], 10);
          }
          self.create({$li : $this, index : index});
        });

        // show first tip
        if (!this.settings.start_timer_on_click && this.settings.timer > 0) {
          this.show(\'init\');
          this.startTimer();
        } else {
          this.show(\'init\');
        }

      }
    },

    resume : function () {
      this.set_li();
      this.show();
    },

    tip_template : function (opts) {
      var $blank, content;

      opts.tip_class = opts.tip_class || \'\';

      $blank = $(this.settings.template.tip).addClass(opts.tip_class);
      content = $.trim($(opts.li).html()) +
        this.prev_button_text(opts.prev_button_text, opts.index) +
        this.button_text(opts.button_text) +
        this.settings.template.link +
        this.timer_instance(opts.index);

      $blank.append($(this.settings.template.wrapper));
      $blank.first().attr(this.add_namespace(\'data-index\'), opts.index);
      $(\'.joyride-content-wrapper\', $blank).append(content);

      return $blank[0];
    },

    timer_instance : function (index) {
      var txt;

      if ((index === 0 && this.settings.start_timer_on_click && this.settings.timer > 0) || this.settings.timer === 0) {
        txt = \'\';
      } else {
        txt = $(this.settings.template.timer)[0].outerHTML;
      }
      return txt;
    },

    button_text : function (txt) {
      if (this.settings.tip_settings.next_button) {
        txt = $.trim(txt) || \'Next\';
        txt = $(this.settings.template.button).append(txt)[0].outerHTML;
      } else {
        txt = \'\';
      }
      return txt;
    },

    prev_button_text : function (txt, idx) {
      if (this.settings.tip_settings.prev_button) {
        txt = $.trim(txt) || \'Previous\';

        // Add the disabled class to the button if it\'s the first element
        if (idx == 0) {
          txt = $(this.settings.template.prev_button).append(txt).addClass(\'disabled\')[0].outerHTML;
        } else {
          txt = $(this.settings.template.prev_button).append(txt)[0].outerHTML;
        }
      } else {
        txt = \'\';
      }
      return txt;
    },

    create : function (opts) {
      this.settings.tip_settings = $.extend({}, this.settings, this.data_options(opts.$li));
      var buttonText = opts.$li.attr(this.add_namespace(\'data-button\')) || opts.$li.attr(this.add_namespace(\'data-text\')),
          prevButtonText = opts.$li.attr(this.add_namespace(\'data-button-prev\')) || opts.$li.attr(this.add_namespace(\'data-prev-text\')),
        tipClass = opts.$li.attr(\'class\'),
        $tip_content = $(this.tip_template({
          tip_class : tipClass,
          index : opts.index,
          button_text : buttonText,
          prev_button_text : prevButtonText,
          li : opts.$li
        }));

      $(this.settings.tip_container).append($tip_content);
    },

    show : function (init, is_prev) {
      var $timer = null;

      // are we paused?
      if (this.settings.$li === undefined || ($.inArray(this.settings.$li.index(), this.settings.pause_after) === -1)) {

        // don\'t go to the next li if the tour was paused
        if (this.settings.paused) {
          this.settings.paused = false;
        } else {
          this.set_li(init, is_prev);
        }

        this.settings.attempts = 0;

        if (this.settings.$li.length && this.settings.$target.length > 0) {
          if (init) { //run when we first start
            this.settings.pre_ride_callback(this.settings.$li.index(), this.settings.$next_tip);
            if (this.settings.modal) {
              this.show_modal();
            }
          }

          this.settings.pre_step_callback(this.settings.$li.index(), this.settings.$next_tip);

          if (this.settings.modal && this.settings.expose) {
            this.expose();
          }

          this.settings.tip_settings = $.extend({}, this.settings, this.data_options(this.settings.$li));

          this.settings.timer = parseInt(this.settings.timer, 10);

          this.settings.tip_settings.tip_location_pattern = this.settings.tip_location_patterns[this.settings.tip_settings.tip_location];

          // scroll and hide bg if not modal
          if (!/body/i.test(this.settings.$target.selector)) {
            var joyridemodalbg = $(\'.joyride-modal-bg\');
            if (/pop/i.test(this.settings.tipAnimation)) {
                joyridemodalbg.hide();
            } else {
                joyridemodalbg.fadeOut(this.settings.tipAnimationFadeSpeed);
            }
            this.scroll_to();
          }

          if (this.is_phone()) {
            this.pos_phone(true);
          } else {
            this.pos_default(true);
          }

          $timer = this.settings.$next_tip.find(\'.joyride-timer-indicator\');

          if (/pop/i.test(this.settings.tip_animation)) {

            $timer.width(0);

            if (this.settings.timer > 0) {

              this.settings.$next_tip.show();

              setTimeout(function () {
                $timer.animate({
                  width : $timer.parent().width()
                }, this.settings.timer, \'linear\');
              }.bind(this), this.settings.tip_animation_fade_speed);

            } else {
              this.settings.$next_tip.show();

            }

          } else if (/fade/i.test(this.settings.tip_animation)) {

            $timer.width(0);

            if (this.settings.timer > 0) {

              this.settings.$next_tip
                .fadeIn(this.settings.tip_animation_fade_speed)
                .show();

              setTimeout(function () {
                $timer.animate({
                  width : $timer.parent().width()
                }, this.settings.timer, \'linear\');
              }.bind(this), this.settings.tip_animation_fade_speed);

            } else {
              this.settings.$next_tip.fadeIn(this.settings.tip_animation_fade_speed);
            }
          }

          this.settings.$current_tip = this.settings.$next_tip;

        // skip non-existant targets
        } else if (this.settings.$li && this.settings.$target.length < 1) {

          this.show(init, is_prev);

        } else {

          this.end();

        }
      } else {

        this.settings.paused = true;

      }

    },

    is_phone : function () {
      return matchMedia(Foundation.media_queries.small).matches &&
        !matchMedia(Foundation.media_queries.medium).matches;
    },

    hide : function () {
      if (this.settings.modal && this.settings.expose) {
        this.un_expose();
      }

      if (!this.settings.modal) {
        $(\'.joyride-modal-bg\').hide();
      }

      // Prevent scroll bouncing...wait to remove from layout
      this.settings.$current_tip.css(\'visibility\', \'hidden\');
      setTimeout($.proxy(function () {
        this.hide();
        this.css(\'visibility\', \'visible\');
      }, this.settings.$current_tip), 0);
      this.settings.post_step_callback(this.settings.$li.index(),
        this.settings.$current_tip);
    },

    set_li : function (init, is_prev) {
      if (init) {
        this.settings.$li = this.settings.$tip_content.eq(this.settings.start_offset);
        this.set_next_tip();
        this.settings.$current_tip = this.settings.$next_tip;
      } else {
        if (is_prev) {
          this.settings.$li = this.settings.$li.prev();
        } else {
          this.settings.$li = this.settings.$li.next();
        }
        this.set_next_tip();
      }

      this.set_target();
    },

    set_next_tip : function () {
      this.settings.$next_tip = $(\'.joyride-tip-guide\').eq(this.settings.$li.index());
      this.settings.$next_tip.data(\'closed\', \'\');
    },

    set_target : function () {
      var cl = this.settings.$li.attr(this.add_namespace(\'data-class\')),
          id = this.settings.$li.attr(this.add_namespace(\'data-id\')),
          $sel = function () {
            if (id) {
              return $(document.getElementById(id));
            } else if (cl) {
              return $(\'.\' + cl).first();
            } else {
              return $(\'body\');
            }
          };

      this.settings.$target = $sel();
    },

    scroll_to : function () {
      var window_half, tipOffset;

      window_half = $(window).height() / 2;
      tipOffset = Math.ceil(this.settings.$target.offset().top - window_half + this.settings.$next_tip.outerHeight());

      if (tipOffset != 0) {
        $(\'html, body\').stop().animate({
          scrollTop : tipOffset
        }, this.settings.scroll_speed, \'swing\');
      }
    },

    paused : function () {
      return ($.inArray((this.settings.$li.index() + 1), this.settings.pause_after) === -1);
    },

    restart : function () {
      this.hide();
      this.settings.$li = undefined;
      this.show(\'init\');
    },

    pos_default : function (init) {
      var $nub = this.settings.$next_tip.find(\'.joyride-nub\'),
          nub_width = Math.ceil($nub.outerWidth() / 2),
          nub_height = Math.ceil($nub.outerHeight() / 2),
          toggle = init || false;

      // tip must not be "display: none" to calculate position
      if (toggle) {
        this.settings.$next_tip.css(\'visibility\', \'hidden\');
        this.settings.$next_tip.show();
      }

      if (!/body/i.test(this.settings.$target.selector)) {
          var topAdjustment = this.settings.tip_settings.tipAdjustmentY ? parseInt(this.settings.tip_settings.tipAdjustmentY) : 0,
              leftAdjustment = this.settings.tip_settings.tipAdjustmentX ? parseInt(this.settings.tip_settings.tipAdjustmentX) : 0;

          if (this.bottom()) {
            if (this.rtl) {
              this.settings.$next_tip.css({
                top : (this.settings.$target.offset().top + nub_height + this.settings.$target.outerHeight() + topAdjustment),
                left : this.settings.$target.offset().left + this.settings.$target.outerWidth() - this.settings.$next_tip.outerWidth() + leftAdjustment});
            } else {
              this.settings.$next_tip.css({
                top : (this.settings.$target.offset().top + nub_height + this.settings.$target.outerHeight() + topAdjustment),
                left : this.settings.$target.offset().left + leftAdjustment});
            }

            this.nub_position($nub, this.settings.tip_settings.nub_position, \'top\');

          } else if (this.top()) {
            if (this.rtl) {
              this.settings.$next_tip.css({
                top : (this.settings.$target.offset().top - this.settings.$next_tip.outerHeight() - nub_height + topAdjustment),
                left : this.settings.$target.offset().left + this.settings.$target.outerWidth() - this.settings.$next_tip.outerWidth()});
            } else {
              this.settings.$next_tip.css({
                top : (this.settings.$target.offset().top - this.settings.$next_tip.outerHeight() - nub_height + topAdjustment),
                left : this.settings.$target.offset().left + leftAdjustment});
            }

            this.nub_position($nub, this.settings.tip_settings.nub_position, \'bottom\');

          } else if (this.right()) {

            this.settings.$next_tip.css({
              top : this.settings.$target.offset().top + topAdjustment,
              left : (this.settings.$target.outerWidth() + this.settings.$target.offset().left + nub_width + leftAdjustment)});

            this.nub_position($nub, this.settings.tip_settings.nub_position, \'left\');

          } else if (this.left()) {

            this.settings.$next_tip.css({
              top : this.settings.$target.offset().top + topAdjustment,
              left : (this.settings.$target.offset().left - this.settings.$next_tip.outerWidth() - nub_width + leftAdjustment)});

            this.nub_position($nub, this.settings.tip_settings.nub_position, \'right\');

          }

          if (!this.visible(this.corners(this.settings.$next_tip)) && this.settings.attempts < this.settings.tip_settings.tip_location_pattern.length) {

            $nub.removeClass(\'bottom\')
              .removeClass(\'top\')
              .removeClass(\'right\')
              .removeClass(\'left\');

            this.settings.tip_settings.tip_location = this.settings.tip_settings.tip_location_pattern[this.settings.attempts];

            this.settings.attempts++;

            this.pos_default();

          }

      } else if (this.settings.$li.length) {

        this.pos_modal($nub);

      }

      if (toggle) {
        this.settings.$next_tip.hide();
        this.settings.$next_tip.css(\'visibility\', \'visible\');
      }

    },

    pos_phone : function (init) {
      var tip_height = this.settings.$next_tip.outerHeight(),
          tip_offset = this.settings.$next_tip.offset(),
          target_height = this.settings.$target.outerHeight(),
          $nub = $(\'.joyride-nub\', this.settings.$next_tip),
          nub_height = Math.ceil($nub.outerHeight() / 2),
          toggle = init || false;

      $nub.removeClass(\'bottom\')
        .removeClass(\'top\')
        .removeClass(\'right\')
        .removeClass(\'left\');

      if (toggle) {
        this.settings.$next_tip.css(\'visibility\', \'hidden\');
        this.settings.$next_tip.show();
      }

      if (!/body/i.test(this.settings.$target.selector)) {

        if (this.top()) {

            this.settings.$next_tip.offset({top : this.settings.$target.offset().top - tip_height - nub_height});
            $nub.addClass(\'bottom\');

        } else {

          this.settings.$next_tip.offset({top : this.settings.$target.offset().top + target_height + nub_height});
          $nub.addClass(\'top\');

        }

      } else if (this.settings.$li.length) {
        this.pos_modal($nub);
      }

      if (toggle) {
        this.settings.$next_tip.hide();
        this.settings.$next_tip.css(\'visibility\', \'visible\');
      }
    },

    pos_modal : function ($nub) {
      this.center();
      $nub.hide();

      this.show_modal();
    },

    show_modal : function () {
      if (!this.settings.$next_tip.data(\'closed\')) {
        var joyridemodalbg =  $(\'.joyride-modal-bg\');
        if (joyridemodalbg.length < 1) {
          var joyridemodalbg = $(this.settings.template.modal);
          joyridemodalbg.appendTo(\'body\');
        }

        if (/pop/i.test(this.settings.tip_animation)) {
            joyridemodalbg.show();
        } else {
            joyridemodalbg.fadeIn(this.settings.tip_animation_fade_speed);
        }
      }
    },

    expose : function () {
      var expose,
          exposeCover,
          el,
          origCSS,
          origClasses,
          randId = \'expose-\' + this.random_str(6);

      if (arguments.length > 0 && arguments[0] instanceof $) {
        el = arguments[0];
      } else if (this.settings.$target && !/body/i.test(this.settings.$target.selector)) {
        el = this.settings.$target;
      } else {
        return false;
      }

      if (el.length < 1) {
        if (window.console) {
          console.error(\'element not valid\', el);
        }
        return false;
      }

      expose = $(this.settings.template.expose);
      this.settings.$body.append(expose);
      expose.css({
        top : el.offset().top,
        left : el.offset().left,
        width : el.outerWidth(true),
        height : el.outerHeight(true)
      });

      exposeCover = $(this.settings.template.expose_cover);

      origCSS = {
        zIndex : el.css(\'z-index\'),
        position : el.css(\'position\')
      };

      origClasses = el.attr(\'class\') == null ? \'\' : el.attr(\'class\');

      el.css(\'z-index\', parseInt(expose.css(\'z-index\')) + 1);

      if (origCSS.position == \'static\') {
        el.css(\'position\', \'relative\');
      }

      el.data(\'expose-css\', origCSS);
      el.data(\'orig-class\', origClasses);
      el.attr(\'class\', origClasses + \' \' + this.settings.expose_add_class);

      exposeCover.css({
        top : el.offset().top,
        left : el.offset().left,
        width : el.outerWidth(true),
        height : el.outerHeight(true)
      });

      if (this.settings.modal) {
        this.show_modal();
      }

      this.settings.$body.append(exposeCover);
      expose.addClass(randId);
      exposeCover.addClass(randId);
      el.data(\'expose\', randId);
      this.settings.post_expose_callback(this.settings.$li.index(), this.settings.$next_tip, el);
      this.add_exposed(el);
    },

    un_expose : function () {
      var exposeId,
          el,
          expose,
          origCSS,
          origClasses,
          clearAll = false;

      if (arguments.length > 0 && arguments[0] instanceof $) {
        el = arguments[0];
      } else if (this.settings.$target && !/body/i.test(this.settings.$target.selector)) {
        el = this.settings.$target;
      } else {
        return false;
      }

      if (el.length < 1) {
        if (window.console) {
          console.error(\'element not valid\', el);
        }
        return false;
      }

      exposeId = el.data(\'expose\');
      expose = $(\'.\' + exposeId);

      if (arguments.length > 1) {
        clearAll = arguments[1];
      }

      if (clearAll === true) {
        $(\'.joyride-expose-wrapper,.joyride-expose-cover\').remove();
      } else {
        expose.remove();
      }

      origCSS = el.data(\'expose-css\');

      if (origCSS.zIndex == \'auto\') {
        el.css(\'z-index\', \'\');
      } else {
        el.css(\'z-index\', origCSS.zIndex);
      }

      if (origCSS.position != el.css(\'position\')) {
        if (origCSS.position == \'static\') {// this is default, no need to set it.
          el.css(\'position\', \'\');
        } else {
          el.css(\'position\', origCSS.position);
        }
      }

      origClasses = el.data(\'orig-class\');
      el.attr(\'class\', origClasses);
      el.removeData(\'orig-classes\');

      el.removeData(\'expose\');
      el.removeData(\'expose-z-index\');
      this.remove_exposed(el);
    },

    add_exposed : function (el) {
      this.settings.exposed = this.settings.exposed || [];
      if (el instanceof $ || typeof el === \'object\') {
        this.settings.exposed.push(el[0]);
      } else if (typeof el == \'string\') {
        this.settings.exposed.push(el);
      }
    },

    remove_exposed : function (el) {
      var search, i;
      if (el instanceof $) {
        search = el[0]
      } else if (typeof el == \'string\') {
        search = el;
      }

      this.settings.exposed = this.settings.exposed || [];
      i = this.settings.exposed.length;

      while (i--) {
        if (this.settings.exposed[i] == search) {
          this.settings.exposed.splice(i, 1);
          return;
        }
      }
    },

    center : function () {
      var $w = $(window);

      this.settings.$next_tip.css({
        top : ((($w.height() - this.settings.$next_tip.outerHeight()) / 2) + $w.scrollTop()),
        left : ((($w.width() - this.settings.$next_tip.outerWidth()) / 2) + $w.scrollLeft())
      });

      return true;
    },

    bottom : function () {
      return /bottom/i.test(this.settings.tip_settings.tip_location);
    },

    top : function () {
      return /top/i.test(this.settings.tip_settings.tip_location);
    },

    right : function () {
      return /right/i.test(this.settings.tip_settings.tip_location);
    },

    left : function () {
      return /left/i.test(this.settings.tip_settings.tip_location);
    },

    corners : function (el) {
      var w = $(window),
          window_half = w.height() / 2,
          //using this to calculate since scroll may not have finished yet.
          tipOffset = Math.ceil(this.settings.$target.offset().top - window_half + this.settings.$next_tip.outerHeight()),
          right = w.width() + w.scrollLeft(),
          offsetBottom =  w.height() + tipOffset,
          bottom = w.height() + w.scrollTop(),
          top = w.scrollTop();

      if (tipOffset < top) {
        if (tipOffset < 0) {
          top = 0;
        } else {
          top = tipOffset;
        }
      }

      if (offsetBottom > bottom) {
        bottom = offsetBottom;
      }

      return [
        el.offset().top < top,
        right < el.offset().left + el.outerWidth(),
        bottom < el.offset().top + el.outerHeight(),
        w.scrollLeft() > el.offset().left
      ];
    },

    visible : function (hidden_corners) {
      var i = hidden_corners.length;

      while (i--) {
        if (hidden_corners[i]) {
          return false;
        }
      }

      return true;
    },

    nub_position : function (nub, pos, def) {
      if (pos === \'auto\') {
        nub.addClass(def);
      } else {
        nub.addClass(pos);
      }
    },

    startTimer : function () {
      if (this.settings.$li.length) {
        this.settings.automate = setTimeout(function () {
          this.hide();
          this.show();
          this.startTimer();
        }.bind(this), this.settings.timer);
      } else {
        clearTimeout(this.settings.automate);
      }
    },

    end : function (abort) {
      if (this.settings.cookie_monster) {
        $.cookie(this.settings.cookie_name, \'ridden\', {expires : this.settings.cookie_expires, domain : this.settings.cookie_domain});
      }

      if (this.settings.timer > 0) {
        clearTimeout(this.settings.automate);
      }

      if (this.settings.modal && this.settings.expose) {
        this.un_expose();
      }

      // Unplug keystrokes listener
      $(this.scope).off(\'keyup.joyride\')

      this.settings.$next_tip.data(\'closed\', true);
      this.settings.riding = false;

      $(\'.joyride-modal-bg\').hide();
      this.settings.$current_tip.hide();

      if (typeof abort === \'undefined\' || abort === false) {
        this.settings.post_step_callback(this.settings.$li.index(), this.settings.$current_tip);
        this.settings.post_ride_callback(this.settings.$li.index(), this.settings.$current_tip);
      }

      $(\'.joyride-tip-guide\').remove();
    },

    off : function () {
      $(this.scope).off(\'.joyride\');
      $(window).off(\'.joyride\');
      $(\'.joyride-close-tip, .joyride-next-tip, .joyride-modal-bg\').off(\'.joyride\');
      $(\'.joyride-tip-guide, .joyride-modal-bg\').remove();
      clearTimeout(this.settings.automate);
      this.settings = {};
    },

    reflow : function () {}
  };
}(jQuery, window, window.document));
;(function ($, window, document, undefined) {
  \'use strict\';

  Foundation.libs.equalizer = {
    name : \'equalizer\',

    version : \'5.5.1\',

    settings : {
      use_tallest : true,
      before_height_change : $.noop,
      after_height_change : $.noop,
      equalize_on_stack : false
    },

    init : function (scope, method, options) {
      Foundation.inherit(this, \'image_loaded\');
      this.bindings(method, options);
      this.reflow();
    },

    events : function () {
      this.S(window).off(\'.equalizer\').on(\'resize.fndtn.equalizer\', function (e) {
        this.reflow();
      }.bind(this));
    },

    equalize : function (equalizer) {
      var isStacked = false,
          vals = equalizer.find(\'[\' + this.attr_name() + \'-watch]:visible\'),
          settings = equalizer.data(this.attr_name(true) + \'-init\');

      if (vals.length === 0) {
        return;
      }
      var firstTopOffset = vals.first().offset().top;
      settings.before_height_change();
      equalizer.trigger(\'before-height-change\').trigger(\'before-height-change.fndth.equalizer\');
      vals.height(\'inherit\');
      vals.each(function () {
        var el = $(this);
        if (el.offset().top !== firstTopOffset) {
          isStacked = true;
        }
      });

      if (settings.equalize_on_stack === false) {
        if (isStacked) {
          return;
        }
      };

      var heights = vals.map(function () { return $(this).outerHeight(false) }).get();

      if (settings.use_tallest) {
        var max = Math.max.apply(null, heights);
        vals.css(\'height\', max);
      } else {
        var min = Math.min.apply(null, heights);
        vals.css(\'height\', min);
      }
      settings.after_height_change();
      equalizer.trigger(\'after-height-change\').trigger(\'after-height-change.fndtn.equalizer\');
    },

    reflow : function () {
      var self = this;

      this.S(\'[\' + this.attr_name() + \']\', this.scope).each(function () {
        var $eq_target = $(this);
        self.image_loaded(self.S(\'img\', this), function () {
          self.equalize($eq_target)
        });
      });
    }
  };
})(jQuery, window, window.document);
;(function ($, window, document, undefined) {
  \'use strict\';

  Foundation.libs.dropdown = {
    name : \'dropdown\',

    version : \'5.5.1\',

    settings : {
      active_class : \'open\',
      disabled_class : \'disabled\',
      mega_class : \'mega\',
      align : \'bottom\',
      is_hover : false,
      hover_timeout : 150,
      opened : function () {},
      closed : function () {}
    },

    init : function (scope, method, options) {
      Foundation.inherit(this, \'throttle\');

      $.extend(true, this.settings, method, options);
      this.bindings(method, options);
    },

    events : function (scope) {
      var self = this,
          S = self.S;

      S(this.scope)
        .off(\'.dropdown\')
        .on(\'click.fndtn.dropdown\', \'[\' + this.attr_name() + \']\', function (e) {
          var settings = S(this).data(self.attr_name(true) + \'-init\') || self.settings;
          if (!settings.is_hover || Modernizr.touch) {
            e.preventDefault();
            if (S(this).parent(\'[data-reveal-id]\')) {
              e.stopPropagation();
            }
            self.toggle($(this));
          }
        })
        .on(\'mouseenter.fndtn.dropdown\', \'[\' + this.attr_name() + \'], [\' + this.attr_name() + \'-content]\', function (e) {
          var $this = S(this),
              dropdown,
              target;

          clearTimeout(self.timeout);

          if ($this.data(self.data_attr())) {
            dropdown = S(\'#\' + $this.data(self.data_attr()));
            target = $this;
          } else {
            dropdown = $this;
            target = S(\'[\' + self.attr_name() + \'="\' + dropdown.attr(\'id\') + \'"]\');
          }

          var settings = target.data(self.attr_name(true) + \'-init\') || self.settings;

          if (S(e.currentTarget).data(self.data_attr()) && settings.is_hover) {
            self.closeall.call(self);
          }

          if (settings.is_hover) {
            self.open.apply(self, [dropdown, target]);
          }
        })
        .on(\'mouseleave.fndtn.dropdown\', \'[\' + this.attr_name() + \'], [\' + this.attr_name() + \'-content]\', function (e) {
          var $this = S(this);
          var settings;

          if ($this.data(self.data_attr())) {
              settings = $this.data(self.data_attr(true) + \'-init\') || self.settings;
          } else {
              var target   = S(\'[\' + self.attr_name() + \'="\' + S(this).attr(\'id\') + \'"]\'),
                  settings = target.data(self.attr_name(true) + \'-init\') || self.settings;
          }

          self.timeout = setTimeout(function () {
            if ($this.data(self.data_attr())) {
              if (settings.is_hover) {
                self.close.call(self, S(\'#\' + $this.data(self.data_attr())));
              }
            } else {
              if (settings.is_hover) {
                self.close.call(self, $this);
              }
            }
          }.bind(this), settings.hover_timeout);
        })
        .on(\'click.fndtn.dropdown\', function (e) {
          var parent = S(e.target).closest(\'[\' + self.attr_name() + \'-content]\');
          var links  = parent.find(\'a\');

          if (links.length > 0 && parent.attr(\'aria-autoclose\') !== \'false\') {
              self.close.call(self, S(\'[\' + self.attr_name() + \'-content]\'));
          }

          if (e.target !== document && !$.contains(document.documentElement, e.target)) {
            return;
          }

          if (S(e.target).closest(\'[\' + self.attr_name() + \']\').length > 0) {
            return;
          }

          if (!(S(e.target).data(\'revealId\')) &&
            (parent.length > 0 && (S(e.target).is(\'[\' + self.attr_name() + \'-content]\') ||
              $.contains(parent.first()[0], e.target)))) {
            e.stopPropagation();
            return;
          }

          self.close.call(self, S(\'[\' + self.attr_name() + \'-content]\'));
        })
        .on(\'opened.fndtn.dropdown\', \'[\' + self.attr_name() + \'-content]\', function () {
          self.settings.opened.call(this);
        })
        .on(\'closed.fndtn.dropdown\', \'[\' + self.attr_name() + \'-content]\', function () {
          self.settings.closed.call(this);
        });

      S(window)
        .off(\'.dropdown\')
        .on(\'resize.fndtn.dropdown\', self.throttle(function () {
          self.resize.call(self);
        }, 50));

      this.resize();
    },

    close : function (dropdown) {
      var self = this;
      dropdown.each(function () {
        var original_target = $(\'[\' + self.attr_name() + \'=\' + dropdown[0].id + \']\') || $(\'aria-controls=\' + dropdown[0].id + \']\');
        original_target.attr(\'aria-expanded\', \'false\');
        if (self.S(this).hasClass(self.settings.active_class)) {
          self.S(this)
            .css(Foundation.rtl ? \'right\' : \'left\', \'-99999px\')
            .attr(\'aria-hidden\', \'true\')
            .removeClass(self.settings.active_class)
            .prev(\'[\' + self.attr_name() + \']\')
            .removeClass(self.settings.active_class)
            .removeData(\'target\');

          self.S(this).trigger(\'closed\').trigger(\'closed.fndtn.dropdown\', [dropdown]);
        }
      });
      dropdown.removeClass(\'f-open-\' + this.attr_name(true));
    },

    closeall : function () {
      var self = this;
      $.each(self.S(\'.f-open-\' + this.attr_name(true)), function () {
        self.close.call(self, self.S(this));
      });
    },

    open : function (dropdown, target) {
      this
        .css(dropdown
        .addClass(this.settings.active_class), target);
      dropdown.prev(\'[\' + this.attr_name() + \']\').addClass(this.settings.active_class);
      dropdown.data(\'target\', target.get(0)).trigger(\'opened\').trigger(\'opened.fndtn.dropdown\', [dropdown, target]);
      dropdown.attr(\'aria-hidden\', \'false\');
      target.attr(\'aria-expanded\', \'true\');
      dropdown.focus();
      dropdown.addClass(\'f-open-\' + this.attr_name(true));
    },

    data_attr : function () {
      if (this.namespace.length > 0) {
        return this.namespace + \'-\' + this.name;
      }

      return this.name;
    },

    toggle : function (target) {
      if (target.hasClass(this.settings.disabled_class)) {
        return;
      }
      var dropdown = this.S(\'#\' + target.data(this.data_attr()));
      if (dropdown.length === 0) {
        // No dropdown found, not continuing
        return;
      }

      this.close.call(this, this.S(\'[\' + this.attr_name() + \'-content]\').not(dropdown));

      if (dropdown.hasClass(this.settings.active_class)) {
        this.close.call(this, dropdown);
        if (dropdown.data(\'target\') !== target.get(0)) {
          this.open.call(this, dropdown, target);
        }
      } else {
        this.open.call(this, dropdown, target);
      }
    },

    resize : function () {
      var dropdown = this.S(\'[\' + this.attr_name() + \'-content].open\');
      var target = $(dropdown.data("target"));

      if (dropdown.length && target.length) {
        this.css(dropdown, target);
      }
    },

    css : function (dropdown, target) {
      var left_offset = Math.max((target.width() - dropdown.width()) / 2, 8),
          settings = target.data(this.attr_name(true) + \'-init\') || this.settings;

      this.clear_idx();

      if (this.small()) {
        var p = this.dirs.bottom.call(dropdown, target, settings);

        dropdown.attr(\'style\', \'\').removeClass(\'drop-left drop-right drop-top\').css({
          position : \'absolute\',
          width : \'95%\',
          \'max-width\' : \'none\',
          top : p.top
        });

        dropdown.css(Foundation.rtl ? \'right\' : \'left\', left_offset);
      } else {

        this.style(dropdown, target, settings);
      }

      return dropdown;
    },

    style : function (dropdown, target, settings) {
      var css = $.extend({position : \'absolute\'},
        this.dirs[settings.align].call(dropdown, target, settings));

      dropdown.attr(\'style\', \'\').css(css);
    },

    // return CSS property object
    // `this` is the dropdown
    dirs : {
      // Calculate target offset
      _base : function (t) {
        var o_p = this.offsetParent(),
            o = o_p.offset(),
            p = t.offset();

        p.top -= o.top;
        p.left -= o.left;

        //set some flags on the p object to pass along
        p.missRight = false;
        p.missTop = false;
        p.missLeft = false;
        p.leftRightFlag = false;

        //lets see if the panel will be off the screen
        //get the actual width of the page and store it
        var actualBodyWidth;
        if (document.getElementsByClassName(\'row\')[0]) {
          actualBodyWidth = document.getElementsByClassName(\'row\')[0].clientWidth;
        } else {
          actualBodyWidth = window.outerWidth;
        }

        var actualMarginWidth = (window.outerWidth - actualBodyWidth) / 2;
        var actualBoundary = actualBodyWidth;

        if (!this.hasClass(\'mega\')) {
          //miss top
          if (t.offset().top <= this.outerHeight()) {
            p.missTop = true;
            actualBoundary = window.outerWidth - actualMarginWidth;
            p.leftRightFlag = true;
          }

          //miss right
          if (t.offset().left + this.outerWidth() > t.offset().left + actualMarginWidth && t.offset().left - actualMarginWidth > this.outerWidth()) {
            p.missRight = true;
            p.missLeft = false;
          }

          //miss left
          if (t.offset().left - this.outerWidth() <= 0) {
            p.missLeft = true;
            p.missRight = false;
          }
        }

        return p;
      },

      top : function (t, s) {
        var self = Foundation.libs.dropdown,
            p = self.dirs._base.call(this, t);

        this.addClass(\'drop-top\');

        if (p.missTop == true) {
          p.top = p.top + t.outerHeight() + this.outerHeight();
          this.removeClass(\'drop-top\');
        }

        if (p.missRight == true) {
          p.left = p.left - this.outerWidth() + t.outerWidth();
        }

        if (t.outerWidth() < this.outerWidth() || self.small() || this.hasClass(s.mega_menu)) {
          self.adjust_pip(this, t, s, p);
        }

        if (Foundation.rtl) {
          return {left : p.left - this.outerWidth() + t.outerWidth(),
            top : p.top - this.outerHeight()};
        }

        return {left : p.left, top : p.top - this.outerHeight()};
      },

      bottom : function (t, s) {
        var self = Foundation.libs.dropdown,
            p = self.dirs._base.call(this, t);

        if (p.missRight == true) {
          p.left = p.left - this.outerWidth() + t.outerWidth();
        }

        if (t.outerWidth() < this.outerWidth() || self.small() || this.hasClass(s.mega_menu)) {
          self.adjust_pip(this, t, s, p);
        }

        if (self.rtl) {
          return {left : p.left - this.outerWidth() + t.outerWidth(), top : p.top + t.outerHeight()};
        }

        return {left : p.left, top : p.top + t.outerHeight()};
      },

      left : function (t, s) {
        var p = Foundation.libs.dropdown.dirs._base.call(this, t);

        this.addClass(\'drop-left\');

        if (p.missLeft == true) {
          p.left =  p.left + this.outerWidth();
          p.top = p.top + t.outerHeight();
          this.removeClass(\'drop-left\');
        }

        return {left : p.left - this.outerWidth(), top : p.top};
      },

      right : function (t, s) {
        var p = Foundation.libs.dropdown.dirs._base.call(this, t);

        this.addClass(\'drop-right\');

        if (p.missRight == true) {
          p.left = p.left - this.outerWidth();
          p.top = p.top + t.outerHeight();
          this.removeClass(\'drop-right\');
        } else {
          p.triggeredRight = true;
        }

        var self = Foundation.libs.dropdown;

        if (t.outerWidth() < this.outerWidth() || self.small() || this.hasClass(s.mega_menu)) {
          self.adjust_pip(this, t, s, p);
        }

        return {left : p.left + t.outerWidth(), top : p.top};
      }
    },

    // Insert rule to style psuedo elements
    adjust_pip : function (dropdown, target, settings, position) {
      var sheet = Foundation.stylesheet,
          pip_offset_base = 8;

      if (dropdown.hasClass(settings.mega_class)) {
        pip_offset_base = position.left + (target.outerWidth() / 2) - 8;
      } else if (this.small()) {
        pip_offset_base += position.left - 8;
      }

      this.rule_idx = sheet.cssRules.length;

      //default
      var sel_before = \'.f-dropdown.open:before\',
          sel_after  = \'.f-dropdown.open:after\',
          css_before = \'left: \' + pip_offset_base + \'px;\',
          css_after  = \'left: \' + (pip_offset_base - 1) + \'px;\';

      if (position.missRight == true) {
        pip_offset_base = dropdown.outerWidth() - 23;
        sel_before = \'.f-dropdown.open:before\',
        sel_after  = \'.f-dropdown.open:after\',
        css_before = \'left: \' + pip_offset_base + \'px;\',
        css_after  = \'left: \' + (pip_offset_base - 1) + \'px;\';
      }

      //just a case where right is fired, but its not missing right
      if (position.triggeredRight == true) {
        sel_before = \'.f-dropdown.open:before\',
        sel_after  = \'.f-dropdown.open:after\',
        css_before = \'left:-12px;\',
        css_after  = \'left:-14px;\';
      }

      if (sheet.insertRule) {
        sheet.insertRule([sel_before, \'{\', css_before, \'}\'].join(\' \'), this.rule_idx);
        sheet.insertRule([sel_after, \'{\', css_after, \'}\'].join(\' \'), this.rule_idx + 1);
      } else {
        sheet.addRule(sel_before, css_before, this.rule_idx);
        sheet.addRule(sel_after, css_after, this.rule_idx + 1);
      }
    },

    // Remove old dropdown rule index
    clear_idx : function () {
      var sheet = Foundation.stylesheet;

      if (typeof this.rule_idx !== \'undefined\') {
        sheet.deleteRule(this.rule_idx);
        sheet.deleteRule(this.rule_idx);
        delete this.rule_idx;
      }
    },

    small : function () {
      return matchMedia(Foundation.media_queries.small).matches &&
        !matchMedia(Foundation.media_queries.medium).matches;
    },

    off : function () {
      this.S(this.scope).off(\'.fndtn.dropdown\');
      this.S(\'html, body\').off(\'.fndtn.dropdown\');
      this.S(window).off(\'.fndtn.dropdown\');
      this.S(\'[data-dropdown-content]\').off(\'.fndtn.dropdown\');
    },

    reflow : function () {}
  };
}(jQuery, window, window.document));
;(function ($, window, document, undefined) {
  \'use strict\';

  Foundation.libs.clearing = {
    name : \'clearing\',

    version : \'5.5.1\',

    settings : {
      templates : {
        viewing : \'<a href="#" class="clearing-close">&times;</a>\' +
          \'<div class="visible-img" style="display: none"><div class="clearing-touch-label"></div><img src="data:image/gif;base64,R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs%3D" alt="" />\' +
          \'<p class="clearing-caption"></p><a href="#" class="clearing-main-prev"><span></span></a>\' +
          \'<a href="#" class="clearing-main-next"><span></span></a></div>\'
      },

      // comma delimited list of selectors that, on click, will close clearing,
      // add \'div.clearing-blackout, div.visible-img\' to close on background click
      close_selectors : \'.clearing-close, div.clearing-blackout\',

      // Default to the entire li element.
      open_selectors : \'\',

      // Image will be skipped in carousel.
      skip_selector : \'\',

      touch_label : \'\',

      // event initializers and locks
      init : false,
      locked : false
    },

    init : function (scope, method, options) {
      var self = this;
      Foundation.inherit(this, \'throttle image_loaded\');

      this.bindings(method, options);

      if (self.S(this.scope).is(\'[\' + this.attr_name() + \']\')) {
        this.assemble(self.S(\'li\', this.scope));
      } else {
        self.S(\'[\' + this.attr_name() + \']\', this.scope).each(function () {
          self.assemble(self.S(\'li\', this));
        });
      }
    },

    events : function (scope) {
      var self = this,
          S = self.S,
          $scroll_container = $(\'.scroll-container\');

      if ($scroll_container.length > 0) {
        this.scope = $scroll_container;
      }

      S(this.scope)
        .off(\'.clearing\')
        .on(\'click.fndtn.clearing\', \'ul[\' + this.attr_name() + \'] li \' + this.settings.open_selectors,
          function (e, current, target) {
            var current = current || S(this),
                target = target || current,
                next = current.next(\'li\'),
                settings = current.closest(\'[\' + self.attr_name() + \']\').data(self.attr_name(true) + \'-init\'),
                image = S(e.target);

            e.preventDefault();

            if (!settings) {
              self.init();
              settings = current.closest(\'[\' + self.attr_name() + \']\').data(self.attr_name(true) + \'-init\');
            }

            // if clearing is open and the current image is
            // clicked, go to the next image in sequence
            if (target.hasClass(\'visible\') &&
              current[0] === target[0] &&
              next.length > 0 && self.is_open(current)) {
              target = next;
              image = S(\'img\', target);
            }

            // set current and target to the clicked li if not otherwise defined.
            self.open(image, current, target);
            self.update_paddles(target);
          })

        .on(\'click.fndtn.clearing\', \'.clearing-main-next\',
          function (e) { self.nav(e, \'next\') })
        .on(\'click.fndtn.clearing\', \'.clearing-main-prev\',
          function (e) { self.nav(e, \'prev\') })
        .on(\'click.fndtn.clearing\', this.settings.close_selectors,
          function (e) { Foundation.libs.clearing.close(e, this) });

      $(document).on(\'keydown.fndtn.clearing\',
          function (e) { self.keydown(e) });

      S(window).off(\'.clearing\').on(\'resize.fndtn.clearing\',
        function () { self.resize() });

      this.swipe_events(scope);
    },

    swipe_events : function (scope) {
      var self = this,
      S = self.S;

      S(this.scope)
        .on(\'touchstart.fndtn.clearing\', \'.visible-img\', function (e) {
          if (!e.touches) { e = e.originalEvent; }
          var data = {
                start_page_x : e.touches[0].pageX,
                start_page_y : e.touches[0].pageY,
                start_time : (new Date()).getTime(),
                delta_x : 0,
                is_scrolling : undefined
              };

          S(this).data(\'swipe-transition\', data);
          e.stopPropagation();
        })
        .on(\'touchmove.fndtn.clearing\', \'.visible-img\', function (e) {
          if (!e.touches) {
            e = e.originalEvent;
          }
          // Ignore pinch/zoom events
          if (e.touches.length > 1 || e.scale && e.scale !== 1) {
            return;
          }

          var data = S(this).data(\'swipe-transition\');

          if (typeof data === \'undefined\') {
            data = {};
          }

          data.delta_x = e.touches[0].pageX - data.start_page_x;

          if (Foundation.rtl) {
            data.delta_x = -data.delta_x;
          }

          if (typeof data.is_scrolling === \'undefined\') {
            data.is_scrolling = !!( data.is_scrolling || Math.abs(data.delta_x) < Math.abs(e.touches[0].pageY - data.start_page_y) );
          }

          if (!data.is_scrolling && !data.active) {
            e.preventDefault();
            var direction = (data.delta_x < 0) ? \'next\' : \'prev\';
            data.active = true;
            self.nav(e, direction);
          }
        })
        .on(\'touchend.fndtn.clearing\', \'.visible-img\', function (e) {
          S(this).data(\'swipe-transition\', {});
          e.stopPropagation();
        });
    },

    assemble : function ($li) {
      var $el = $li.parent();

      if ($el.parent().hasClass(\'carousel\')) {
        return;
      }

      $el.after(\'<div id="foundationClearingHolder"></div>\');

      var grid = $el.detach(),
          grid_outerHTML = \'\';

      if (grid[0] == null) {
        return;
      } else {
        grid_outerHTML = grid[0].outerHTML;
      }

      var holder = this.S(\'#foundationClearingHolder\'),
          settings = $el.data(this.attr_name(true) + \'-init\'),
          data = {
            grid : \'<div class="carousel">\' + grid_outerHTML + \'</div>\',
            viewing : settings.templates.viewing
          },
          wrapper = \'<div class="clearing-assembled"><div>\' + data.viewing +
            data.grid + \'</div></div>\',
          touch_label = this.settings.touch_label;

      if (Modernizr.touch) {
        wrapper = $(wrapper).find(\'.clearing-touch-label\').html(touch_label).end();
      }

      holder.after(wrapper).remove();
    },

    open : function ($image, current, target) {
      var self = this,
          body = $(document.body),
          root = target.closest(\'.clearing-assembled\'),
          container = self.S(\'div\', root).first(),
          visible_image = self.S(\'.visible-img\', container),
          image = self.S(\'img\', visible_image).not($image),
          label = self.S(\'.clearing-touch-label\', container),
          error = false;

      // Event to disable scrolling on touch devices when Clearing is activated
      $(\'body\').on(\'touchmove\', function (e) {
        e.preventDefault();
      });

      image.error(function () {
        error = true;
      });

      function startLoad() {
        setTimeout(function () {
          this.image_loaded(image, function () {
            if (image.outerWidth() === 1 && !error) {
              startLoad.call(this);
            } else {
              cb.call(this, image);
            }
          }.bind(this));
        }.bind(this), 100);
      }

      function cb (image) {
        var $image = $(image);
        $image.css(\'visibility\', \'visible\');
        // toggle the gallery
        body.css(\'overflow\', \'hidden\');
        root.addClass(\'clearing-blackout\');
        container.addClass(\'clearing-container\');
        visible_image.show();
        this.fix_height(target)
          .caption(self.S(\'.clearing-caption\', visible_image), self.S(\'img\', target))
          .center_and_label(image, label)
          .shift(current, target, function () {
            target.closest(\'li\').siblings().removeClass(\'visible\');
            target.closest(\'li\').addClass(\'visible\');
          });
        visible_image.trigger(\'opened.fndtn.clearing\')
      }

      if (!this.locked()) {
        visible_image.trigger(\'open.fndtn.clearing\');
        // set the image to the selected thumbnail
        image
          .attr(\'src\', this.load($image))
          .css(\'visibility\', \'hidden\');

        startLoad.call(this);
      }
    },

    close : function (e, el) {
      e.preventDefault();

      var root = (function (target) {
            if (/blackout/.test(target.selector)) {
              return target;
            } else {
              return target.closest(\'.clearing-blackout\');
            }
          }($(el))),
          body = $(document.body), container, visible_image;

      if (el === e.target && root) {
        body.css(\'overflow\', \'\');
        container = $(\'div\', root).first();
        visible_image = $(\'.visible-img\', container);
        visible_image.trigger(\'close.fndtn.clearing\');
        this.settings.prev_index = 0;
        $(\'ul[\' + this.attr_name() + \']\', root)
          .attr(\'style\', \'\').closest(\'.clearing-blackout\')
          .removeClass(\'clearing-blackout\');
        container.removeClass(\'clearing-container\');
        visible_image.hide();
        visible_image.trigger(\'closed.fndtn.clearing\');
      }

      // Event to re-enable scrolling on touch devices
      $(\'body\').off(\'touchmove\');

      return false;
    },

    is_open : function (current) {
      return current.parent().prop(\'style\').length > 0;
    },

    keydown : function (e) {
      var clearing = $(\'.clearing-blackout ul[\' + this.attr_name() + \']\'),
          NEXT_KEY = this.rtl ? 37 : 39,
          PREV_KEY = this.rtl ? 39 : 37,
          ESC_KEY = 27;

      if (e.which === NEXT_KEY) {
        this.go(clearing, \'next\');
      }
      if (e.which === PREV_KEY) {
        this.go(clearing, \'prev\');
      }
      if (e.which === ESC_KEY) {
        this.S(\'a.clearing-close\').trigger(\'click\').trigger(\'click.fndtn.clearing\');
      }
    },

    nav : function (e, direction) {
      var clearing = $(\'ul[\' + this.attr_name() + \']\', \'.clearing-blackout\');

      e.preventDefault();
      this.go(clearing, direction);
    },

    resize : function () {
      var image = $(\'img\', \'.clearing-blackout .visible-img\'),
          label = $(\'.clearing-touch-label\', \'.clearing-blackout\');

      if (image.length) {
        this.center_and_label(image, label);
        image.trigger(\'resized.fndtn.clearing\')
      }
    },

    // visual adjustments
    fix_height : function (target) {
      var lis = target.parent().children(),
          self = this;

      lis.each(function () {
        var li = self.S(this),
            image = li.find(\'img\');

        if (li.height() > image.outerHeight()) {
          li.addClass(\'fix-height\');
        }
      })
      .closest(\'ul\')
      .width(lis.length * 100 + \'%\');

      return this;
    },

    update_paddles : function (target) {
      target = target.closest(\'li\');
      var visible_image = target
        .closest(\'.carousel\')
        .siblings(\'.visible-img\');

      if (target.next().length > 0) {
        this.S(\'.clearing-main-next\', visible_image).removeClass(\'disabled\');
      } else {
        this.S(\'.clearing-main-next\', visible_image).addClass(\'disabled\');
      }

      if (target.prev().length > 0) {
        this.S(\'.clearing-main-prev\', visible_image).removeClass(\'disabled\');
      } else {
        this.S(\'.clearing-main-prev\', visible_image).addClass(\'disabled\');
      }
    },

    center_and_label : function (target, label) {
      if (!this.rtl && label.length > 0) {
        label.css({
          marginLeft : -(label.outerWidth() / 2),
          marginTop : -(target.outerHeight() / 2)-label.outerHeight()-10
        });
      } else {
        label.css({
          marginRight : -(label.outerWidth() / 2),
          marginTop : -(target.outerHeight() / 2)-label.outerHeight()-10,
          left: \'auto\',
          right: \'50%\'
        });
      }
      return this;
    },

    // image loading and preloading

    load : function ($image) {
      var href;

      if ($image[0].nodeName === \'A\') {
        href = $image.attr(\'href\');
      } else {
        href = $image.closest(\'a\').attr(\'href\');
      }

      this.preload($image);

      if (href) {
        return href;
      }
      return $image.attr(\'src\');
    },

    preload : function ($image) {
      this
        .img($image.closest(\'li\').next())
        .img($image.closest(\'li\').prev());
    },

    img : function (img) {
      if (img.length) {
        var new_img = new Image(),
            new_a = this.S(\'a\', img);

        if (new_a.length) {
          new_img.src = new_a.attr(\'href\');
        } else {
          new_img.src = this.S(\'img\', img).attr(\'src\');
        }
      }
      return this;
    },

    // image caption

    caption : function (container, $image) {
      var caption = $image.attr(\'data-caption\');

      if (caption) {
        container
          .html(caption)
          .show();
      } else {
        container
          .text(\'\')
          .hide();
      }
      return this;
    },

    // directional methods

    go : function ($ul, direction) {
      var current = this.S(\'.visible\', $ul),
          target = current[direction]();

      // Check for skip selector.
      if (this.settings.skip_selector && target.find(this.settings.skip_selector).length != 0) {
        target = target[direction]();
      }

      if (target.length) {
        this.S(\'img\', target)
          .trigger(\'click\', [current, target]).trigger(\'click.fndtn.clearing\', [current, target])
          .trigger(\'change.fndtn.clearing\');
      }
    },

    shift : function (current, target, callback) {
      var clearing = target.parent(),
          old_index = this.settings.prev_index || target.index(),
          direction = this.direction(clearing, current, target),
          dir = this.rtl ? \'right\' : \'left\',
          left = parseInt(clearing.css(\'left\'), 10),
          width = target.outerWidth(),
          skip_shift;

      var dir_obj = {};

      // we use jQuery animate instead of CSS transitions because we
      // need a callback to unlock the next animation
      // needs support for RTL **
      if (target.index() !== old_index && !/skip/.test(direction)) {
        if (/left/.test(direction)) {
          this.lock();
          dir_obj[dir] = left + width;
          clearing.animate(dir_obj, 300, this.unlock());
        } else if (/right/.test(direction)) {
          this.lock();
          dir_obj[dir] = left - width;
          clearing.animate(dir_obj, 300, this.unlock());
        }
      } else if (/skip/.test(direction)) {
        // the target image is not adjacent to the current image, so
        // do we scroll right or not
        skip_shift = target.index() - this.settings.up_count;
        this.lock();

        if (skip_shift > 0) {
          dir_obj[dir] = -(skip_shift * width);
          clearing.animate(dir_obj, 300, this.unlock());
        } else {
          dir_obj[dir] = 0;
          clearing.animate(dir_obj, 300, this.unlock());
        }
      }

      callback();
    },

    direction : function ($el, current, target) {
      var lis = this.S(\'li\', $el),
          li_width = lis.outerWidth() + (lis.outerWidth() / 4),
          up_count = Math.floor(this.S(\'.clearing-container\').outerWidth() / li_width) - 1,
          target_index = lis.index(target),
          response;

      this.settings.up_count = up_count;

      if (this.adjacent(this.settings.prev_index, target_index)) {
        if ((target_index > up_count) && target_index > this.settings.prev_index) {
          response = \'right\';
        } else if ((target_index > up_count - 1) && target_index <= this.settings.prev_index) {
          response = \'left\';
        } else {
          response = false;
        }
      } else {
        response = \'skip\';
      }

      this.settings.prev_index = target_index;

      return response;
    },

    adjacent : function (current_index, target_index) {
      for (var i = target_index + 1; i >= target_index - 1; i--) {
        if (i === current_index) {
          return true;
        }
      }
      return false;
    },

    // lock management

    lock : function () {
      this.settings.locked = true;
    },

    unlock : function () {
      this.settings.locked = false;
    },

    locked : function () {
      return this.settings.locked;
    },

    off : function () {
      this.S(this.scope).off(\'.fndtn.clearing\');
      this.S(window).off(\'.fndtn.clearing\');
    },

    reflow : function () {
      this.init();
    }
  };

}(jQuery, window, window.document));
;(function ($, window, document, undefined) {
  \'use strict\';

  var noop = function () {};

  var Orbit = function (el, settings) {
    // Don\'t reinitialize plugin
    if (el.hasClass(settings.slides_container_class)) {
      return this;
    }

    var self = this,
        container,
        slides_container = el,
        number_container,
        bullets_container,
        timer_container,
        idx = 0,
        animate,
        timer,
        locked = false,
        adjust_height_after = false;

    self.slides = function () {
      return slides_container.children(settings.slide_selector);
    };

    self.slides().first().addClass(settings.active_slide_class);

    self.update_slide_number = function (index) {
      if (settings.slide_number) {
        number_container.find(\'span:first\').text(parseInt(index) + 1);
        number_container.find(\'span:last\').text(self.slides().length);
      }
      if (settings.bullets) {
        bullets_container.children().removeClass(settings.bullets_active_class);
        $(bullets_container.children().get(index)).addClass(settings.bullets_active_class);
      }
    };

    self.update_active_link = function (index) {
      var link = $(\'[data-orbit-link="\' + self.slides().eq(index).attr(\'data-orbit-slide\') + \'"]\');
      link.siblings().removeClass(settings.bullets_active_class);
      link.addClass(settings.bullets_active_class);
    };

    self.build_markup = function () {
      slides_container.wrap(\'<div class="\' + settings.container_class + \'"></div>\');
      container = slides_container.parent();
      slides_container.addClass(settings.slides_container_class);

      if (settings.stack_on_small) {
        container.addClass(settings.stack_on_small_class);
      }

      if (settings.navigation_arrows) {
        container.append($(\'<a href="#"><span></span></a>\').addClass(settings.prev_class));
        container.append($(\'<a href="#"><span></span></a>\').addClass(settings.next_class));
      }

      if (settings.timer) {
        timer_container = $(\'<div>\').addClass(settings.timer_container_class);
        timer_container.append(\'<span>\');
        timer_container.append($(\'<div>\').addClass(settings.timer_progress_class));
        timer_container.addClass(settings.timer_paused_class);
        container.append(timer_container);
      }

      if (settings.slide_number) {
        number_container = $(\'<div>\').addClass(settings.slide_number_class);
        number_container.append(\'<span></span> \' + settings.slide_number_text + \' <span></span>\');
        container.append(number_container);
      }

      if (settings.bullets) {
        bullets_container = $(\'<ol>\').addClass(settings.bullets_container_class);
        container.append(bullets_container);
        bullets_container.wrap(\'<div class="orbit-bullets-container"></div>\');
        self.slides().each(function (idx, el) {
          var bullet = $(\'<li>\').attr(\'data-orbit-slide\', idx).on(\'click\', self.link_bullet);;
          bullets_container.append(bullet);
        });
      }

    };

    self._goto = function (next_idx, start_timer) {
      // if (locked) {return false;}
      if (next_idx === idx) {return false;}
      if (typeof timer === \'object\') {timer.restart();}
      var slides = self.slides();

      var dir = \'next\';
      locked = true;
      if (next_idx < idx) {dir = \'prev\';}
      if (next_idx >= slides.length) {
        if (!settings.circular) {
          return false;
        }
        next_idx = 0;
      } else if (next_idx < 0) {
        if (!settings.circular) {
          return false;
        }
        next_idx = slides.length - 1;
      }

      var current = $(slides.get(idx));
      var next = $(slides.get(next_idx));

      current.css(\'zIndex\', 2);
      current.removeClass(settings.active_slide_class);
      next.css(\'zIndex\', 4).addClass(settings.active_slide_class);

      slides_container.trigger(\'before-slide-change.fndtn.orbit\');
      settings.before_slide_change();
      self.update_active_link(next_idx);

      var callback = function () {
        var unlock = function () {
          idx = next_idx;
          locked = false;
          if (start_timer === true) {timer = self.create_timer(); timer.start();}
          self.update_slide_number(idx);
          slides_container.trigger(\'after-slide-change.fndtn.orbit\', [{slide_number : idx, total_slides : slides.length}]);
          settings.after_slide_change(idx, slides.length);
        };
        if (slides_container.outerHeight() != next.outerHeight() && settings.variable_height) {
          slides_container.animate({\'height\': next.outerHeight()}, 250, \'linear\', unlock);
        } else {
          unlock();
        }
      };

      if (slides.length === 1) {callback(); return false;}

      var start_animation = function () {
        if (dir === \'next\') {animate.next(current, next, callback);}
        if (dir === \'prev\') {animate.prev(current, next, callback);}
      };

      if (next.outerHeight() > slides_container.outerHeight() && settings.variable_height) {
        slides_container.animate({\'height\': next.outerHeight()}, 250, \'linear\', start_animation);
      } else {
        start_animation();
      }
    };

    self.next = function (e) {
      e.stopImmediatePropagation();
      e.preventDefault();
      self._goto(idx + 1);
    };

    self.prev = function (e) {
      e.stopImmediatePropagation();
      e.preventDefault();
      self._goto(idx - 1);
    };

    self.link_custom = function (e) {
      e.preventDefault();
      var link = $(this).attr(\'data-orbit-link\');
      if ((typeof link === \'string\') && (link = $.trim(link)) != \'\') {
        var slide = container.find(\'[data-orbit-slide=\' + link + \']\');
        if (slide.index() != -1) {self._goto(slide.index());}
      }
    };

    self.link_bullet = function (e) {
      var index = $(this).attr(\'data-orbit-slide\');
      if ((typeof index === \'string\') && (index = $.trim(index)) != \'\') {
        if (isNaN(parseInt(index))) {
          var slide = container.find(\'[data-orbit-slide=\' + index + \']\');
          if (slide.index() != -1) {self._goto(slide.index() + 1);}
        } else {
          self._goto(parseInt(index));
        }
      }

    }

    self.timer_callback = function () {
      self._goto(idx + 1, true);
    }

    self.compute_dimensions = function () {
      var current = $(self.slides().get(idx));
      var h = current.outerHeight();
      if (!settings.variable_height) {
        self.slides().each(function(){
          if ($(this).outerHeight() > h) { h = $(this).outerHeight(); }
        });
      }
      slides_container.height(h);
    };

    self.create_timer = function () {
      var t = new Timer(
        container.find(\'.\' + settings.timer_container_class),
        settings,
        self.timer_callback
      );
      return t;
    };

    self.stop_timer = function () {
      if (typeof timer === \'object\') {
        timer.stop();
      }
    };

    self.toggle_timer = function () {
      var t = container.find(\'.\' + settings.timer_container_class);
      if (t.hasClass(settings.timer_paused_class)) {
        if (typeof timer === \'undefined\') {timer = self.create_timer();}
        timer.start();
      } else {
        if (typeof timer === \'object\') {timer.stop();}
      }
    };

    self.init = function () {
      self.build_markup();
      if (settings.timer) {
        timer = self.create_timer();
        Foundation.utils.image_loaded(this.slides().children(\'img\'), timer.start);
      }
      animate = new FadeAnimation(settings, slides_container);
      if (settings.animation === \'slide\') {
        animate = new SlideAnimation(settings, slides_container);
      }

      container.on(\'click\', \'.\' + settings.next_class, self.next);
      container.on(\'click\', \'.\' + settings.prev_class, self.prev);

      if (settings.next_on_click) {
        container.on(\'click\', \'.\' + settings.slides_container_class + \' [data-orbit-slide]\', self.link_bullet);
      }

      container.on(\'click\', self.toggle_timer);
      if (settings.swipe) {
        container.on(\'touchstart.fndtn.orbit\', function (e) {
          if (!e.touches) {e = e.originalEvent;}
          var data = {
            start_page_x : e.touches[0].pageX,
            start_page_y : e.touches[0].pageY,
            start_time : (new Date()).getTime(),
            delta_x : 0,
            is_scrolling : undefined
          };
          container.data(\'swipe-transition\', data);
          e.stopPropagation();
        })
        .on(\'touchmove.fndtn.orbit\', function (e) {
          if (!e.touches) {
            e = e.originalEvent;
          }
          // Ignore pinch/zoom events
          if (e.touches.length > 1 || e.scale && e.scale !== 1) {
            return;
          }

          var data = container.data(\'swipe-transition\');
          if (typeof data === \'undefined\') {data = {};}

          data.delta_x = e.touches[0].pageX - data.start_page_x;

          if ( typeof data.is_scrolling === \'undefined\') {
            data.is_scrolling = !!( data.is_scrolling || Math.abs(data.delta_x) < Math.abs(e.touches[0].pageY - data.start_page_y) );
          }

          if (!data.is_scrolling && !data.active) {
            e.preventDefault();
            var direction = (data.delta_x < 0) ? (idx + 1) : (idx - 1);
            data.active = true;
            self._goto(direction);
          }
        })
        .on(\'touchend.fndtn.orbit\', function (e) {
          container.data(\'swipe-transition\', {});
          e.stopPropagation();
        })
      }
      container.on(\'mouseenter.fndtn.orbit\', function (e) {
        if (settings.timer && settings.pause_on_hover) {
          self.stop_timer();
        }
      })
      .on(\'mouseleave.fndtn.orbit\', function (e) {
        if (settings.timer && settings.resume_on_mouseout) {
          timer.start();
        }
      });

      $(document).on(\'click\', \'[data-orbit-link]\', self.link_custom);
      $(window).on(\'load resize\', self.compute_dimensions);
      Foundation.utils.image_loaded(this.slides().children(\'img\'), self.compute_dimensions);
      Foundation.utils.image_loaded(this.slides().children(\'img\'), function () {
        container.prev(\'.\' + settings.preloader_class).css(\'display\', \'none\');
        self.update_slide_number(0);
        self.update_active_link(0);
        slides_container.trigger(\'ready.fndtn.orbit\');
      });
    };

    self.init();
  };

  var Timer = function (el, settings, callback) {
    var self = this,
        duration = settings.timer_speed,
        progress = el.find(\'.\' + settings.timer_progress_class),
        start,
        timeout,
        left = -1;

    this.update_progress = function (w) {
      var new_progress = progress.clone();
      new_progress.attr(\'style\', \'\');
      new_progress.css(\'width\', w + \'%\');
      progress.replaceWith(new_progress);
      progress = new_progress;
    };

    this.restart = function () {
      clearTimeout(timeout);
      el.addClass(settings.timer_paused_class);
      left = -1;
      self.update_progress(0);
    };

    this.start = function () {
      if (!el.hasClass(settings.timer_paused_class)) {return true;}
      left = (left === -1) ? duration : left;
      el.removeClass(settings.timer_paused_class);
      start = new Date().getTime();
      progress.animate({\'width\' : \'100%\'}, left, \'linear\');
      timeout = setTimeout(function () {
        self.restart();
        callback();
      }, left);
      el.trigger(\'timer-started.fndtn.orbit\')
    };

    this.stop = function () {
      if (el.hasClass(settings.timer_paused_class)) {return true;}
      clearTimeout(timeout);
      el.addClass(settings.timer_paused_class);
      var end = new Date().getTime();
      left = left - (end - start);
      var w = 100 - ((left / duration) * 100);
      self.update_progress(w);
      el.trigger(\'timer-stopped.fndtn.orbit\');
    };
  };

  var SlideAnimation = function (settings, container) {
    var duration = settings.animation_speed;
    var is_rtl = ($(\'html[dir=rtl]\').length === 1);
    var margin = is_rtl ? \'marginRight\' : \'marginLeft\';
    var animMargin = {};
    animMargin[margin] = \'0%\';

    this.next = function (current, next, callback) {
      current.animate({marginLeft : \'-100%\'}, duration);
      next.animate(animMargin, duration, function () {
        current.css(margin, \'100%\');
        callback();
      });
    };

    this.prev = function (current, prev, callback) {
      current.animate({marginLeft : \'100%\'}, duration);
      prev.css(margin, \'-100%\');
      prev.animate(animMargin, duration, function () {
        current.css(margin, \'100%\');
        callback();
      });
    };
  };

  var FadeAnimation = function (settings, container) {
    var duration = settings.animation_speed;
    var is_rtl = ($(\'html[dir=rtl]\').length === 1);
    var margin = is_rtl ? \'marginRight\' : \'marginLeft\';

    this.next = function (current, next, callback) {
      next.css({\'margin\' : \'0%\', \'opacity\' : \'0.01\'});
      next.animate({\'opacity\' :\'1\'}, duration, \'linear\', function () {
        current.css(\'margin\', \'100%\');
        callback();
      });
    };

    this.prev = function (current, prev, callback) {
      prev.css({\'margin\' : \'0%\', \'opacity\' : \'0.01\'});
      prev.animate({\'opacity\' : \'1\'}, duration, \'linear\', function () {
        current.css(\'margin\', \'100%\');
        callback();
      });
    };
  };

  Foundation.libs = Foundation.libs || {};

  Foundation.libs.orbit = {
    name : \'orbit\',

    version : \'5.5.1\',

    settings : {
      animation : \'slide\',
      timer_speed : 10000,
      pause_on_hover : true,
      resume_on_mouseout : false,
      next_on_click : true,
      animation_speed : 500,
      stack_on_small : false,
      navigation_arrows : true,
      slide_number : true,
      slide_number_text : \'of\',
      container_class : \'orbit-container\',
      stack_on_small_class : \'orbit-stack-on-small\',
      next_class : \'orbit-next\',
      prev_class : \'orbit-prev\',
      timer_container_class : \'orbit-timer\',
      timer_paused_class : \'paused\',
      timer_progress_class : \'orbit-progress\',
      slides_container_class : \'orbit-slides-container\',
      preloader_class : \'preloader\',
      slide_selector : \'*\',
      bullets_container_class : \'orbit-bullets\',
      bullets_active_class : \'active\',
      slide_number_class : \'orbit-slide-number\',
      caption_class : \'orbit-caption\',
      active_slide_class : \'active\',
      orbit_transition_class : \'orbit-transitioning\',
      bullets : true,
      circular : true,
      timer : true,
      variable_height : false,
      swipe : true,
      before_slide_change : noop,
      after_slide_change : noop
    },

    init : function (scope, method, options) {
      var self = this;
      this.bindings(method, options);
    },

    events : function (instance) {
      var orbit_instance = new Orbit(this.S(instance), this.S(instance).data(\'orbit-init\'));
      this.S(instance).data(this.name + \'-instance\', orbit_instance);
    },

    reflow : function () {
      var self = this;

      if (self.S(self.scope).is(\'[data-orbit]\')) {
        var $el = self.S(self.scope);
        var instance = $el.data(self.name + \'-instance\');
        instance.compute_dimensions();
      } else {
        self.S(\'[data-orbit]\', self.scope).each(function (idx, el) {
          var $el = self.S(el);
          var opts = self.data_options($el);
          var instance = $el.data(self.name + \'-instance\');
          instance.compute_dimensions();
        });
      }
    }
  };

}(jQuery, window, window.document));
;(function ($, window, document, undefined) {
  \'use strict\';

  Foundation.libs.offcanvas = {
    name : \'offcanvas\',

    version : \'5.5.1\',

    settings : {
      open_method : \'move\',
      close_on_click : false
    },

    init : function (scope, method, options) {
      this.bindings(method, options);
    },

    events : function () {
      var self = this,
          S = self.S,
          move_class = \'\',
          right_postfix = \'\',
          left_postfix = \'\';

      if (this.settings.open_method === \'move\') {
        move_class = \'move-\';
        right_postfix = \'right\';
        left_postfix = \'left\';
      } else if (this.settings.open_method === \'overlap_single\') {
        move_class = \'offcanvas-overlap-\';
        right_postfix = \'right\';
        left_postfix = \'left\';
      } else if (this.settings.open_method === \'overlap\') {
        move_class = \'offcanvas-overlap\';
      }

      S(this.scope).off(\'.offcanvas\')
        .on(\'click.fndtn.offcanvas\', \'.left-off-canvas-toggle\', function (e) {
          self.click_toggle_class(e, move_class + right_postfix);
          if (self.settings.open_method !== \'overlap\') {
            S(\'.left-submenu\').removeClass(move_class + right_postfix);
          }
          $(\'.left-off-canvas-toggle\').attr(\'aria-expanded\', \'true\');
        })
        .on(\'click.fndtn.offcanvas\', \'.left-off-canvas-menu a\', function (e) {
          var settings = self.get_settings(e);
          var parent = S(this).parent();

          if (settings.close_on_click && !parent.hasClass(\'has-submenu\') && !parent.hasClass(\'back\')) {
            self.hide.call(self, move_class + right_postfix, self.get_wrapper(e));
            parent.parent().removeClass(move_class + right_postfix);
          } else if (S(this).parent().hasClass(\'has-submenu\')) {
            e.preventDefault();
            S(this).siblings(\'.left-submenu\').toggleClass(move_class + right_postfix);
          } else if (parent.hasClass(\'back\')) {
            e.preventDefault();
            parent.parent().removeClass(move_class + right_postfix);
          }
          $(\'.left-off-canvas-toggle\').attr(\'aria-expanded\', \'true\');
        })
        .on(\'click.fndtn.offcanvas\', \'.right-off-canvas-toggle\', function (e) {
          self.click_toggle_class(e, move_class + left_postfix);
          if (self.settings.open_method !== \'overlap\') {
            S(\'.right-submenu\').removeClass(move_class + left_postfix);
          }
          $(\'.right-off-canvas-toggle\').attr(\'aria-expanded\', \'true\');
        })
        .on(\'click.fndtn.offcanvas\', \'.right-off-canvas-menu a\', function (e) {
          var settings = self.get_settings(e);
          var parent = S(this).parent();

          if (settings.close_on_click && !parent.hasClass(\'has-submenu\') && !parent.hasClass(\'back\')) {
            self.hide.call(self, move_class + left_postfix, self.get_wrapper(e));
            parent.parent().removeClass(move_class + left_postfix);
          } else if (S(this).parent().hasClass(\'has-submenu\')) {
            e.preventDefault();
            S(this).siblings(\'.right-submenu\').toggleClass(move_class + left_postfix);
          } else if (parent.hasClass(\'back\')) {
            e.preventDefault();
            parent.parent().removeClass(move_class + left_postfix);
          }
          $(\'.right-off-canvas-toggle\').attr(\'aria-expanded\', \'true\');
        })
        .on(\'click.fndtn.offcanvas\', \'.exit-off-canvas\', function (e) {
          self.click_remove_class(e, move_class + left_postfix);
          S(\'.right-submenu\').removeClass(move_class + left_postfix);
          if (right_postfix) {
            self.click_remove_class(e, move_class + right_postfix);
            S(\'.left-submenu\').removeClass(move_class + left_postfix);
          }
          $(\'.right-off-canvas-toggle\').attr(\'aria-expanded\', \'true\');
        })
        .on(\'click.fndtn.offcanvas\', \'.exit-off-canvas\', function (e) {
          self.click_remove_class(e, move_class + left_postfix);
          $(\'.left-off-canvas-toggle\').attr(\'aria-expanded\', \'false\');
          if (right_postfix) {
            self.click_remove_class(e, move_class + right_postfix);
            $(\'.right-off-canvas-toggle\').attr(\'aria-expanded\', \'false\');
          }
        });
    },

    toggle : function (class_name, $off_canvas) {
      $off_canvas = $off_canvas || this.get_wrapper();
      if ($off_canvas.is(\'.\' + class_name)) {
        this.hide(class_name, $off_canvas);
      } else {
        this.show(class_name, $off_canvas);
      }
    },

    show : function (class_name, $off_canvas) {
      $off_canvas = $off_canvas || this.get_wrapper();
      $off_canvas.trigger(\'open\').trigger(\'open.fndtn.offcanvas\');
      $off_canvas.addClass(class_name);
    },

    hide : function (class_name, $off_canvas) {
      $off_canvas = $off_canvas || this.get_wrapper();
      $off_canvas.trigger(\'close\').trigger(\'close.fndtn.offcanvas\');
      $off_canvas.removeClass(class_name);
    },

    click_toggle_class : function (e, class_name) {
      e.preventDefault();
      var $off_canvas = this.get_wrapper(e);
      this.toggle(class_name, $off_canvas);
    },

    click_remove_class : function (e, class_name) {
      e.preventDefault();
      var $off_canvas = this.get_wrapper(e);
      this.hide(class_name, $off_canvas);
    },

    get_settings : function (e) {
      var offcanvas  = this.S(e.target).closest(\'[\' + this.attr_name() + \']\');
      return offcanvas.data(this.attr_name(true) + \'-init\') || this.settings;
    },

    get_wrapper : function (e) {
      var $off_canvas = this.S(e ? e.target : this.scope).closest(\'.off-canvas-wrap\');

      if ($off_canvas.length === 0) {
        $off_canvas = this.S(\'.off-canvas-wrap\');
      }
      return $off_canvas;
    },

    reflow : function () {}
  };
}(jQuery, window, window.document));
;(function ($, window, document, undefined) {
  \'use strict\';

  Foundation.libs.alert = {
    name : \'alert\',

    version : \'5.5.1\',

    settings : {
      callback : function () {}
    },

    init : function (scope, method, options) {
      this.bindings(method, options);
    },

    events : function () {
      var self = this,
          S = this.S;

      $(this.scope).off(\'.alert\').on(\'click.fndtn.alert\', \'[\' + this.attr_name() + \'] .close\', function (e) {
        var alertBox = S(this).closest(\'[\' + self.attr_name() + \']\'),
            settings = alertBox.data(self.attr_name(true) + \'-init\') || self.settings;

        e.preventDefault();
        if (Modernizr.csstransitions) {
          alertBox.addClass(\'alert-close\');
          alertBox.on(\'transitionend webkitTransitionEnd oTransitionEnd\', function (e) {
            S(this).trigger(\'close\').trigger(\'close.fndtn.alert\').remove();
            settings.callback();
          });
        } else {
          alertBox.fadeOut(300, function () {
            S(this).trigger(\'close\').trigger(\'close.fndtn.alert\').remove();
            settings.callback();
          });
        }
      });
    },

    reflow : function () {}
  };
}(jQuery, window, window.document));
;(function ($, window, document, undefined) {
  \'use strict\';

  Foundation.libs.reveal = {
    name : \'reveal\',

    version : \'5.5.1\',

    locked : false,

    settings : {
      animation : \'fadeAndPop\',
      animation_speed : 250,
      close_on_background_click : true,
      close_on_esc : true,
      dismiss_modal_class : \'close-reveal-modal\',
      multiple_opened : false,
      bg_class : \'reveal-modal-bg\',
      root_element : \'body\',
      open : function(){},
      opened : function(){},
      close : function(){},
      closed : function(){},
      bg : $(\'.reveal-modal-bg\'),
      css : {
        open : {
          \'opacity\' : 0,
          \'visibility\' : \'visible\',
          \'display\' : \'block\'
        },
        close : {
          \'opacity\' : 1,
          \'visibility\' : \'hidden\',
          \'display\' : \'none\'
        }
      }
    },

    init : function (scope, method, options) {
      $.extend(true, this.settings, method, options);
      this.bindings(method, options);
    },

    events : function (scope) {
      var self = this,
          S = self.S;

      S(this.scope)
        .off(\'.reveal\')
        .on(\'click.fndtn.reveal\', \'[\' + this.add_namespace(\'data-reveal-id\') + \']:not([disabled])\', function (e) {
          e.preventDefault();

          if (!self.locked) {
            var element = S(this),
                ajax = element.data(self.data_attr(\'reveal-ajax\'));

            self.locked = true;

            if (typeof ajax === \'undefined\') {
              self.open.call(self, element);
            } else {
              var url = ajax === true ? element.attr(\'href\') : ajax;

              self.open.call(self, element, {url : url});
            }
          }
        });

      S(document)
        .on(\'click.fndtn.reveal\', this.close_targets(), function (e) {
          e.preventDefault();
          if (!self.locked) {
            var settings = S(\'[\' + self.attr_name() + \'].open\').data(self.attr_name(true) + \'-init\') || self.settings,
                bg_clicked = S(e.target)[0] === S(\'.\' + settings.bg_class)[0];

            if (bg_clicked) {
              if (settings.close_on_background_click) {
                e.stopPropagation();
              } else {
                return;
              }
            }

            self.locked = true;
            self.close.call(self, bg_clicked ? S(\'[\' + self.attr_name() + \'].open\') : S(this).closest(\'[\' + self.attr_name() + \']\'));
          }
        });

      if (S(\'[\' + self.attr_name() + \']\', this.scope).length > 0) {
        S(this.scope)
          // .off(\'.reveal\')
          .on(\'open.fndtn.reveal\', this.settings.open)
          .on(\'opened.fndtn.reveal\', this.settings.opened)
          .on(\'opened.fndtn.reveal\', this.open_video)
          .on(\'close.fndtn.reveal\', this.settings.close)
          .on(\'closed.fndtn.reveal\', this.settings.closed)
          .on(\'closed.fndtn.reveal\', this.close_video);
      } else {
        S(this.scope)
          // .off(\'.reveal\')
          .on(\'open.fndtn.reveal\', \'[\' + self.attr_name() + \']\', this.settings.open)
          .on(\'opened.fndtn.reveal\', \'[\' + self.attr_name() + \']\', this.settings.opened)
          .on(\'opened.fndtn.reveal\', \'[\' + self.attr_name() + \']\', this.open_video)
          .on(\'close.fndtn.reveal\', \'[\' + self.attr_name() + \']\', this.settings.close)
          .on(\'closed.fndtn.reveal\', \'[\' + self.attr_name() + \']\', this.settings.closed)
          .on(\'closed.fndtn.reveal\', \'[\' + self.attr_name() + \']\', this.close_video);
      }

      return true;
    },

    // PATCH #3: turning on key up capture only when a reveal window is open
    key_up_on : function (scope) {
      var self = this;

      // PATCH #1: fixing multiple keyup event trigger from single key press
      self.S(\'body\').off(\'keyup.fndtn.reveal\').on(\'keyup.fndtn.reveal\', function ( event ) {
        var open_modal = self.S(\'[\' + self.attr_name() + \'].open\'),
            settings = open_modal.data(self.attr_name(true) + \'-init\') || self.settings ;
        // PATCH #2: making sure that the close event can be called only while unlocked,
        //           so that multiple keyup.fndtn.reveal events don\'t prevent clean closing of the reveal window.
        if ( settings && event.which === 27  && settings.close_on_esc && !self.locked) { // 27 is the keycode for the Escape key
          self.close.call(self, open_modal);
        }
      });

      return true;
    },

    // PATCH #3: turning on key up capture only when a reveal window is open
    key_up_off : function (scope) {
      this.S(\'body\').off(\'keyup.fndtn.reveal\');
      return true;
    },

    open : function (target, ajax_settings) {
      var self = this,
          modal;

      if (target) {
        if (typeof target.selector !== \'undefined\') {
          // Find the named node; only use the first one found, since the rest of the code assumes there\'s only one node
          modal = self.S(\'#\' + target.data(self.data_attr(\'reveal-id\'))).first();
        } else {
          modal = self.S(this.scope);

          ajax_settings = target;
        }
      } else {
        modal = self.S(this.scope);
      }

      var settings = modal.data(self.attr_name(true) + \'-init\');
      settings = settings || this.settings;

      if (modal.hasClass(\'open\') && target.attr(\'data-reveal-id\') == modal.attr(\'id\')) {
        return self.close(modal);
      }

      if (!modal.hasClass(\'open\')) {
        var open_modal = self.S(\'[\' + self.attr_name() + \'].open\');

        if (typeof modal.data(\'css-top\') === \'undefined\') {
          modal.data(\'css-top\', parseInt(modal.css(\'top\'), 10))
            .data(\'offset\', this.cache_offset(modal));
        }

        this.key_up_on(modal);    // PATCH #3: turning on key up capture only when a reveal window is open

        modal.on(\'open.fndtn.reveal\').trigger(\'open.fndtn.reveal\');

        if (open_modal.length < 1) {
          this.toggle_bg(modal, true);
        }

        if (typeof ajax_settings === \'string\') {
          ajax_settings = {
            url : ajax_settings
          };
        }

        if (typeof ajax_settings === \'undefined\' || !ajax_settings.url) {
          if (open_modal.length > 0) {
            if (settings.multiple_opened) {
              this.to_back(open_modal);
            } else {
              this.hide(open_modal, settings.css.close);
            }
          }

          this.show(modal, settings.css.open);
        } else {
          var old_success = typeof ajax_settings.success !== \'undefined\' ? ajax_settings.success : null;

          $.extend(ajax_settings, {
            success : function (data, textStatus, jqXHR) {
              if ( $.isFunction(old_success) ) {
                var result = old_success(data, textStatus, jqXHR);
                if (typeof result == \'string\') {
                  data = result;
                }
              }

              modal.html(data);
              self.S(modal).foundation(\'section\', \'reflow\');
              self.S(modal).children().foundation();

              if (open_modal.length > 0) {
                if (settings.multiple_opened) {
                  this.to_back(open_modal);
                } else {
                  this.hide(open_modal, settings.css.close);
                }
              }
              self.show(modal, settings.css.open);
            }
          });

          $.ajax(ajax_settings);
        }
      }
      self.S(window).trigger(\'resize\');
    },

    close : function (modal) {
      var modal = modal && modal.length ? modal : this.S(this.scope),
          open_modals = this.S(\'[\' + this.attr_name() + \'].open\'),
          settings = modal.data(this.attr_name(true) + \'-init\') || this.settings;

      if (open_modals.length > 0) {
        this.locked = true;
        this.key_up_off(modal);   // PATCH #3: turning on key up capture only when a reveal window is open
        modal.trigger(\'close\').trigger(\'close.fndtn.reveal\');

        if ((settings.multiple_opened && open_modals.length === 1) || !settings.multiple_opened || modal.length > 1) {
          this.toggle_bg(modal, false);
          this.to_front(modal);
        }

        if (settings.multiple_opened) {
          this.hide(modal, settings.css.close, settings);
          this.to_front($($.makeArray(open_modals).reverse()[1]));
        } else {
          this.hide(open_modals, settings.css.close, settings);
        }
      }
    },

    close_targets : function () {
      var base = \'.\' + this.settings.dismiss_modal_class;

      if (this.settings.close_on_background_click) {
        return base + \', .\' + this.settings.bg_class;
      }

      return base;
    },

    toggle_bg : function (modal, state) {
      if (this.S(\'.\' + this.settings.bg_class).length === 0) {
        this.settings.bg = $(\'<div />\', {\'class\': this.settings.bg_class})
          .appendTo(\'body\').hide();
      }

      var visible = this.settings.bg.filter(\':visible\').length > 0;
      if ( state != visible ) {
        if ( state == undefined ? visible : !state ) {
          this.hide(this.settings.bg);
        } else {
          this.show(this.settings.bg);
        }
      }
    },

    show : function (el, css) {
      // is modal
      if (css) {
        var settings = el.data(this.attr_name(true) + \'-init\') || this.settings,
            root_element = settings.root_element;

        if (el.parent(root_element).length === 0) {
          var placeholder = el.wrap(\'<div style="display: none;" />\').parent();

          el.on(\'closed.fndtn.reveal.wrapped\', function () {
            el.detach().appendTo(placeholder);
            el.unwrap().unbind(\'closed.fndtn.reveal.wrapped\');
          });

          el.detach().appendTo(root_element);
        }

        var animData = getAnimationData(settings.animation);
        if (!animData.animate) {
          this.locked = false;
        }
        if (animData.pop) {
          css.top = $(window).scrollTop() - el.data(\'offset\') + \'px\';
          var end_css = {
            top: $(window).scrollTop() + el.data(\'css-top\') + \'px\',
            opacity: 1
          };

          return setTimeout(function () {
            return el
              .css(css)
              .animate(end_css, settings.animation_speed, \'linear\', function () {
                this.locked = false;
                el.trigger(\'opened\').trigger(\'opened.fndtn.reveal\');
              }.bind(this))
              .addClass(\'open\');
          }.bind(this), settings.animation_speed / 2);
        }

        if (animData.fade) {
          css.top = $(window).scrollTop() + el.data(\'css-top\') + \'px\';
          var end_css = {opacity: 1};

          return setTimeout(function () {
            return el
              .css(css)
              .animate(end_css, settings.animation_speed, \'linear\', function () {
                this.locked = false;
                el.trigger(\'opened\').trigger(\'opened.fndtn.reveal\');
              }.bind(this))
              .addClass(\'open\');
          }.bind(this), settings.animation_speed / 2);
        }

        return el.css(css).show().css({opacity : 1}).addClass(\'open\').trigger(\'opened\').trigger(\'opened.fndtn.reveal\');
      }

      var settings = this.settings;

      // should we animate the background?
      if (getAnimationData(settings.animation).fade) {
        return el.fadeIn(settings.animation_speed / 2);
      }

      this.locked = false;

      return el.show();
    },

    to_back : function(el) {
      el.addClass(\'toback\');
    },

    to_front : function(el) {
      el.removeClass(\'toback\');
    },

    hide : function (el, css) {
      // is modal
      if (css) {
        var settings = el.data(this.attr_name(true) + \'-init\');
        settings = settings || this.settings;

        var animData = getAnimationData(settings.animation);
        if (!animData.animate) {
          this.locked = false;
        }
        if (animData.pop) {
          var end_css = {
            top: - $(window).scrollTop() - el.data(\'offset\') + \'px\',
            opacity: 0
          };

          return setTimeout(function () {
            return el
              .animate(end_css, settings.animation_speed, \'linear\', function () {
                this.locked = false;
                el.css(css).trigger(\'closed\').trigger(\'closed.fndtn.reveal\');
              }.bind(this))
              .removeClass(\'open\');
          }.bind(this), settings.animation_speed / 2);
        }

        if (animData.fade) {
          var end_css = {opacity : 0};

          return setTimeout(function () {
            return el
              .animate(end_css, settings.animation_speed, \'linear\', function () {
                this.locked = false;
                el.css(css).trigger(\'closed\').trigger(\'closed.fndtn.reveal\');
              }.bind(this))
              .removeClass(\'open\');
          }.bind(this), settings.animation_speed / 2);
        }

        return el.hide().css(css).removeClass(\'open\').trigger(\'closed\').trigger(\'closed.fndtn.reveal\');
      }

      var settings = this.settings;

      // should we animate the background?
      if (getAnimationData(settings.animation).fade) {
        return el.fadeOut(settings.animation_speed / 2);
      }

      return el.hide();
    },

    close_video : function (e) {
      var video = $(\'.flex-video\', e.target),
          iframe = $(\'iframe\', video);

      if (iframe.length > 0) {
        iframe.attr(\'data-src\', iframe[0].src);
        iframe.attr(\'src\', iframe.attr(\'src\'));
        video.hide();
      }
    },

    open_video : function (e) {
      var video = $(\'.flex-video\', e.target),
          iframe = video.find(\'iframe\');

      if (iframe.length > 0) {
        var data_src = iframe.attr(\'data-src\');
        if (typeof data_src === \'string\') {
          iframe[0].src = iframe.attr(\'data-src\');
        } else {
          var src = iframe[0].src;
          iframe[0].src = undefined;
          iframe[0].src = src;
        }
        video.show();
      }
    },

    data_attr : function (str) {
      if (this.namespace.length > 0) {
        return this.namespace + \'-\' + str;
      }

      return str;
    },

    cache_offset : function (modal) {
      var offset = modal.show().height() + parseInt(modal.css(\'top\'), 10);

      modal.hide();

      return offset;
    },

    off : function () {
      $(this.scope).off(\'.fndtn.reveal\');
    },

    reflow : function () {}
  };

  /*
   * getAnimationData(\'popAndFade\') // {animate: true,  pop: true,  fade: true}
   * getAnimationData(\'fade\')       // {animate: true,  pop: false, fade: true}
   * getAnimationData(\'pop\')        // {animate: true,  pop: true,  fade: false}
   * getAnimationData(\'foo\')        // {animate: false, pop: false, fade: false}
   * getAnimationData(null)         // {animate: false, pop: false, fade: false}
   */
  function getAnimationData(str) {
    var fade = /fade/i.test(str);
    var pop = /pop/i.test(str);
    return {
      animate : fade || pop,
      pop : pop,
      fade : fade
    };
  }
}(jQuery, window, window.document));
;(function ($, window, document, undefined) {
  \'use strict\';

  Foundation.libs.interchange = {
    name : \'interchange\',

    version : \'5.5.1\',

    cache : {},

    images_loaded : false,
    nodes_loaded : false,

    settings : {
      load_attr : \'interchange\',

      named_queries : {
        \'default\'     : \'only screen\',
        \'small\'       : Foundation.media_queries[\'small\'],
        \'small-only\'  : Foundation.media_queries[\'small-only\'],
        \'medium\'      : Foundation.media_queries[\'medium\'],
        \'medium-only\' : Foundation.media_queries[\'medium-only\'],
        \'large\'       : Foundation.media_queries[\'large\'],
        \'large-only\'  : Foundation.media_queries[\'large-only\'],
        \'xlarge\'      : Foundation.media_queries[\'xlarge\'],
        \'xlarge-only\' : Foundation.media_queries[\'xlarge-only\'],
        \'xxlarge\'     : Foundation.media_queries[\'xxlarge\'],
        \'landscape\'   : \'only screen and (orientation: landscape)\',
        \'portrait\'    : \'only screen and (orientation: portrait)\',
        \'retina\'      : \'only screen and (-webkit-min-device-pixel-ratio: 2),\' +
          \'only screen and (min--moz-device-pixel-ratio: 2),\' +
          \'only screen and (-o-min-device-pixel-ratio: 2/1),\' +
          \'only screen and (min-device-pixel-ratio: 2),\' +
          \'only screen and (min-resolution: 192dpi),\' +
          \'only screen and (min-resolution: 2dppx)\'
      },

      directives : {
        replace : function (el, path, trigger) {
          // The trigger argument, if called within the directive, fires
          // an event named after the directive on the element, passing
          // any parameters along to the event that you pass to trigger.
          //
          // ex. trigger(), trigger([a, b, c]), or trigger(a, b, c)
          //
          // This allows you to bind a callback like so:
          // $(\'#interchangeContainer\').on(\'replace\', function (e, a, b, c) {
          //   console.log($(this).html(), a, b, c);
          // });

          if (/IMG/.test(el[0].nodeName)) {
            var orig_path = el[0].src;

            if (new RegExp(path, \'i\').test(orig_path)) {
              return;
            }

            el[0].src = path;

            return trigger(el[0].src);
          }
          var last_path = el.data(this.data_attr + \'-last-path\'),
              self = this;

          if (last_path == path) {
            return;
          }

          if (/\\.(gif|jpg|jpeg|tiff|png)([?#].*)?/i.test(path)) {
            $(el).css(\'background-image\', \'url(\' + path + \')\');
            el.data(\'interchange-last-path\', path);
            return trigger(path);
          }

          return $.get(path, function (response) {
            el.html(response);
            el.data(self.data_attr + \'-last-path\', path);
            trigger();
          });

        }
      }
    },

    init : function (scope, method, options) {
      Foundation.inherit(this, \'throttle random_str\');

      this.data_attr = this.set_data_attr();
      $.extend(true, this.settings, method, options);
      this.bindings(method, options);
      this.load(\'images\');
      this.load(\'nodes\');
    },

    get_media_hash : function () {
        var mediaHash = \'\';
        for (var queryName in this.settings.named_queries ) {
            mediaHash += matchMedia(this.settings.named_queries[queryName]).matches.toString();
        }
        return mediaHash;
    },

    events : function () {
      var self = this, prevMediaHash;

      $(window)
        .off(\'.interchange\')
        .on(\'resize.fndtn.interchange\', self.throttle(function () {
            var currMediaHash = self.get_media_hash();
            if (currMediaHash !== prevMediaHash) {
                self.resize();
            }
            prevMediaHash = currMediaHash;
        }, 50));

      return this;
    },

    resize : function () {
      var cache = this.cache;

      if (!this.images_loaded || !this.nodes_loaded) {
        setTimeout($.proxy(this.resize, this), 50);
        return;
      }

      for (var uuid in cache) {
        if (cache.hasOwnProperty(uuid)) {
          var passed = this.results(uuid, cache[uuid]);

          if (passed) {
            this.settings.directives[passed
              .scenario[1]].call(this, passed.el, passed.scenario[0], (function (passed) {
                if (arguments[0] instanceof Array) {
                  var args = arguments[0];
                } else {
                  var args = Array.prototype.slice.call(arguments, 0);
                }

                return function() {
                  passed.el.trigger(passed.scenario[1], args);
                }
              }(passed)));
          }
        }
      }

    },

    results : function (uuid, scenarios) {
      var count = scenarios.length;

      if (count > 0) {
        var el = this.S(\'[\' + this.add_namespace(\'data-uuid\') + \'="\' + uuid + \'"]\');

        while (count--) {
          var mq, rule = scenarios[count][2];
          if (this.settings.named_queries.hasOwnProperty(rule)) {
            mq = matchMedia(this.settings.named_queries[rule]);
          } else {
            mq = matchMedia(rule);
          }
          if (mq.matches) {
            return {el : el, scenario : scenarios[count]};
          }
        }
      }

      return false;
    },

    load : function (type, force_update) {
      if (typeof this[\'cached_\' + type] === \'undefined\' || force_update) {
        this[\'update_\' + type]();
      }

      return this[\'cached_\' + type];
    },

    update_images : function () {
      var images = this.S(\'img[\' + this.data_attr + \']\'),
          count = images.length,
          i = count,
          loaded_count = 0,
          data_attr = this.data_attr;

      this.cache = {};
      this.cached_images = [];
      this.images_loaded = (count === 0);

      while (i--) {
        loaded_count++;
        if (images[i]) {
          var str = images[i].getAttribute(data_attr) || \'\';

          if (str.length > 0) {
            this.cached_images.push(images[i]);
          }
        }

        if (loaded_count === count) {
          this.images_loaded = true;
          this.enhance(\'images\');
        }
      }

      return this;
    },

    update_nodes : function () {
      var nodes = this.S(\'[\' + this.data_attr + \']\').not(\'img\'),
          count = nodes.length,
          i = count,
          loaded_count = 0,
          data_attr = this.data_attr;

      this.cached_nodes = [];
      this.nodes_loaded = (count === 0);

      while (i--) {
        loaded_count++;
        var str = nodes[i].getAttribute(data_attr) || \'\';

        if (str.length > 0) {
          this.cached_nodes.push(nodes[i]);
        }

        if (loaded_count === count) {
          this.nodes_loaded = true;
          this.enhance(\'nodes\');
        }
      }

      return this;
    },

    enhance : function (type) {
      var i = this[\'cached_\' + type].length;

      while (i--) {
        this.object($(this[\'cached_\' + type][i]));
      }

      return $(window).trigger(\'resize\').trigger(\'resize.fndtn.interchange\');
    },

    convert_directive : function (directive) {

      var trimmed = this.trim(directive);

      if (trimmed.length > 0) {
        return trimmed;
      }

      return \'replace\';
    },

    parse_scenario : function (scenario) {
      // This logic had to be made more complex since some users were using commas in the url path
      // So we cannot simply just split on a comma
      var directive_match = scenario[0].match(/(.+),\\s*(\\w+)\\s*$/),
      media_query         = scenario[1];

      if (directive_match) {
        var path  = directive_match[1],
        directive = directive_match[2];
      } else {
        var cached_split = scenario[0].split(/,\\s*$/),
        path             = cached_split[0],
        directive        = \'\';
      }

      return [this.trim(path), this.convert_directive(directive), this.trim(media_query)];
    },

    object : function (el) {
      var raw_arr = this.parse_data_attr(el),
          scenarios = [],
          i = raw_arr.length;

      if (i > 0) {
        while (i--) {
          var split = raw_arr[i].split(/\\(([^\\)]*?)(\\))$/);

          if (split.length > 1) {
            var params = this.parse_scenario(split);
            scenarios.push(params);
          }
        }
      }

      return this.store(el, scenarios);
    },

    store : function (el, scenarios) {
      var uuid = this.random_str(),
          current_uuid = el.data(this.add_namespace(\'uuid\', true));

      if (this.cache[current_uuid]) {
        return this.cache[current_uuid];
      }

      el.attr(this.add_namespace(\'data-uuid\'), uuid);

      return this.cache[uuid] = scenarios;
    },

    trim : function (str) {

      if (typeof str === \'string\') {
        return $.trim(str);
      }

      return str;
    },

    set_data_attr : function (init) {
      if (init) {
        if (this.namespace.length > 0) {
          return this.namespace + \'-\' + this.settings.load_attr;
        }

        return this.settings.load_attr;
      }

      if (this.namespace.length > 0) {
        return \'data-\' + this.namespace + \'-\' + this.settings.load_attr;
      }

      return \'data-\' + this.settings.load_attr;
    },

    parse_data_attr : function (el) {
      var raw = el.attr(this.attr_name()).split(/\\[(.*?)\\]/),
          i = raw.length,
          output = [];

      while (i--) {
        if (raw[i].replace(/[\\W\\d]+/, \'\').length > 4) {
          output.push(raw[i]);
        }
      }

      return output;
    },

    reflow : function () {
      this.load(\'images\', true);
      this.load(\'nodes\', true);
    }

  };

}(jQuery, window, window.document));
;(function ($, window, document, undefined) {
  \'use strict\';

  Foundation.libs[\'magellan-expedition\'] = {
    name : \'magellan-expedition\',

    version : \'5.5.1\',

    settings : {
      active_class : \'active\',
      threshold : 0, // pixels from the top of the expedition for it to become fixes
      destination_threshold : 20, // pixels from the top of destination for it to be considered active
      throttle_delay : 30, // calculation throttling to increase framerate
      fixed_top : 0, // top distance in pixels assigend to the fixed element on scroll
      offset_by_height : true,  // whether to offset the destination by the expedition height. Usually you want this to be true, unless your expedition is on the side.
      duration : 700, // animation duration time
      easing : \'swing\' // animation easing
    },

    init : function (scope, method, options) {
      Foundation.inherit(this, \'throttle\');
      this.bindings(method, options);
    },

    events : function () {
      var self = this,
          S = self.S,
          settings = self.settings;

      // initialize expedition offset
      self.set_expedition_position();

      S(self.scope)
        .off(\'.magellan\')
        .on(\'click.fndtn.magellan\', \'[\' + self.add_namespace(\'data-magellan-arrival\') + \'] a[href^="#"]\', function (e) {
          e.preventDefault();
          var expedition = $(this).closest(\'[\' + self.attr_name() + \']\'),
              settings = expedition.data(\'magellan-expedition-init\'),
              hash = this.hash.split(\'#\').join(\'\'),
              target = $(\'a[name="\' + hash + \'"]\');

          if (target.length === 0) {
            target = $(\'#\' + hash);

          }

          // Account for expedition height if fixed position
          var scroll_top = target.offset().top - settings.destination_threshold + 1;
          if (settings.offset_by_height) {
            scroll_top = scroll_top - expedition.outerHeight();
          }

          $(\'html, body\').stop().animate({
            \'scrollTop\' : scroll_top
          }, settings.duration, settings.easing, function () {
            if (history.pushState) {
              history.pushState(null, null, \'#\' + hash);
            } else {
              location.hash = \'#\' + hash;
            }
          });
        })
        .on(\'scroll.fndtn.magellan\', self.throttle(this.check_for_arrivals.bind(this), settings.throttle_delay));

      $(window)
        .on(\'resize.fndtn.magellan\', self.throttle(this.set_expedition_position.bind(this), settings.throttle_delay));
    },

    check_for_arrivals : function () {
      var self = this;
      self.update_arrivals();
      self.update_expedition_positions();
    },

    set_expedition_position : function () {
      var self = this;
      $(\'[\' + this.attr_name() + \'=fixed]\', self.scope).each(function (idx, el) {
        var expedition = $(this),
            settings = expedition.data(\'magellan-expedition-init\'),
            styles = expedition.attr(\'styles\'), // save styles
            top_offset, fixed_top;

        expedition.attr(\'style\', \'\');
        top_offset = expedition.offset().top + settings.threshold;

        //set fixed-top by attribute
        fixed_top = parseInt(expedition.data(\'magellan-fixed-top\'));
        if (!isNaN(fixed_top)) {
          self.settings.fixed_top = fixed_top;
        }

        expedition.data(self.data_attr(\'magellan-top-offset\'), top_offset);
        expedition.attr(\'style\', styles);
      });
    },

    update_expedition_positions : function () {
      var self = this,
          window_top_offset = $(window).scrollTop();

      $(\'[\' + this.attr_name() + \'=fixed]\', self.scope).each(function () {
        var expedition = $(this),
            settings = expedition.data(\'magellan-expedition-init\'),
            styles = expedition.attr(\'style\'), // save styles
            top_offset = expedition.data(\'magellan-top-offset\');

        //scroll to the top distance
        if (window_top_offset + self.settings.fixed_top >= top_offset) {
          // Placeholder allows height calculations to be consistent even when
          // appearing to switch between fixed/non-fixed placement
          var placeholder = expedition.prev(\'[\' + self.add_namespace(\'data-magellan-expedition-clone\') + \']\');
          if (placeholder.length === 0) {
            placeholder = expedition.clone();
            placeholder.removeAttr(self.attr_name());
            placeholder.attr(self.add_namespace(\'data-magellan-expedition-clone\'), \'\');
            expedition.before(placeholder);
          }
          expedition.css({position :\'fixed\', top : settings.fixed_top}).addClass(\'fixed\');
        } else {
          expedition.prev(\'[\' + self.add_namespace(\'data-magellan-expedition-clone\') + \']\').remove();
          expedition.attr(\'style\', styles).css(\'position\', \'\').css(\'top\', \'\').removeClass(\'fixed\');
        }
      });
    },

    update_arrivals : function () {
      var self = this,
          window_top_offset = $(window).scrollTop();

      $(\'[\' + this.attr_name() + \']\', self.scope).each(function () {
        var expedition = $(this),
            settings = expedition.data(self.attr_name(true) + \'-init\'),
            offsets = self.offsets(expedition, window_top_offset),
            arrivals = expedition.find(\'[\' + self.add_namespace(\'data-magellan-arrival\') + \']\'),
            active_item = false;
        offsets.each(function (idx, item) {
          if (item.viewport_offset >= item.top_offset) {
            var arrivals = expedition.find(\'[\' + self.add_namespace(\'data-magellan-arrival\') + \']\');
            arrivals.not(item.arrival).removeClass(settings.active_class);
            item.arrival.addClass(settings.active_class);
            active_item = true;
            return true;
          }
        });

        if (!active_item) {
          arrivals.removeClass(settings.active_class);
        }
      });
    },

    offsets : function (expedition, window_offset) {
      var self = this,
          settings = expedition.data(self.attr_name(true) + \'-init\'),
          viewport_offset = window_offset;

      return expedition.find(\'[\' + self.add_namespace(\'data-magellan-arrival\') + \']\').map(function (idx, el) {
        var name = $(this).data(self.data_attr(\'magellan-arrival\')),
            dest = $(\'[\' + self.add_namespace(\'data-magellan-destination\') + \'=\' + name + \']\');
        if (dest.length > 0) {
          var top_offset = dest.offset().top - settings.destination_threshold;
          if (settings.offset_by_height) {
            top_offset = top_offset - expedition.outerHeight();
          }
          top_offset = Math.floor(top_offset);
          return {
            destination : dest,
            arrival : $(this),
            top_offset : top_offset,
            viewport_offset : viewport_offset
          }
        }
      }).sort(function (a, b) {
        if (a.top_offset < b.top_offset) {
          return -1;
        }
        if (a.top_offset > b.top_offset) {
          return 1;
        }
        return 0;
      });
    },

    data_attr : function (str) {
      if (this.namespace.length > 0) {
        return this.namespace + \'-\' + str;
      }

      return str;
    },

    off : function () {
      this.S(this.scope).off(\'.magellan\');
      this.S(window).off(\'.magellan\');
    },

    reflow : function () {
      var self = this;
      // remove placeholder expeditions used for height calculation purposes
      $(\'[\' + self.add_namespace(\'data-magellan-expedition-clone\') + \']\', self.scope).remove();
    }
  };
}(jQuery, window, window.document));
;(function ($, window, document, undefined) {
  \'use strict\';

  Foundation.libs.accordion = {
    name : \'accordion\',

    version : \'5.5.1\',

    settings : {
      content_class : \'content\',
      active_class : \'active\',
      multi_expand : false,
      toggleable : true,
      callback : function () {}
    },

    init : function (scope, method, options) {
      this.bindings(method, options);
    },

    events : function () {
      var self = this;
      var S = this.S;
      S(this.scope)
      .off(\'.fndtn.accordion\')
      .on(\'click.fndtn.accordion\', \'[\' + this.attr_name() + \'] > .accordion-navigation > a\', function (e) {
        var accordion = S(this).closest(\'[\' + self.attr_name() + \']\'),
            groupSelector = self.attr_name() + \'=\' + accordion.attr(self.attr_name()),
            settings = accordion.data(self.attr_name(true) + \'-init\') || self.settings,
            target = S(\'#\' + this.href.split(\'#\')[1]),
            aunts = $(\'> .accordion-navigation\', accordion),
            siblings = aunts.children(\'.\' + settings.content_class),
            active_content = siblings.filter(\'.\' + settings.active_class);

        e.preventDefault();

        if (accordion.attr(self.attr_name())) {
          siblings = siblings.add(\'[\' + groupSelector + \'] dd > \' + \'.\' + settings.content_class);
          aunts = aunts.add(\'[\' + groupSelector + \'] .accordion-navigation\');
        }

        if (settings.toggleable && target.is(active_content)) {
          target.parent(\'.accordion-navigation\').toggleClass(settings.active_class, false);
          target.toggleClass(settings.active_class, false);
          settings.callback(target);
          target.triggerHandler(\'toggled\', [accordion]);
          accordion.triggerHandler(\'toggled\', [target]);
          return;
        }

        if (!settings.multi_expand) {
          siblings.removeClass(settings.active_class);
          aunts.removeClass(settings.active_class);
        }

        target.addClass(settings.active_class).parent().addClass(settings.active_class);
        settings.callback(target);
        target.triggerHandler(\'toggled\', [accordion]);
        accordion.triggerHandler(\'toggled\', [target]);
      });
    },

    off : function () {},

    reflow : function () {}
  };
}(jQuery, window, window.document));
;(function ($, window, document, undefined) {
  \'use strict\';

  Foundation.libs.topbar = {
    name : \'topbar\',

    version : \'5.5.1\',

    settings : {
      index : 0,
      sticky_class : \'sticky\',
      custom_back_text : true,
      back_text : \'Back\',
      mobile_show_parent_link : true,
      is_hover : true,
      scrolltop : true, // jump to top when sticky nav menu toggle is clicked
      sticky_on : \'all\'
    },

    init : function (section, method, options) {
      Foundation.inherit(this, \'add_custom_rule register_media throttle\');
      var self = this;

      self.register_media(\'topbar\', \'foundation-mq-topbar\');

      this.bindings(method, options);

      self.S(\'[\' + this.attr_name() + \']\', this.scope).each(function () {
        var topbar = $(this),
            settings = topbar.data(self.attr_name(true) + \'-init\'),
            section = self.S(\'section, .top-bar-section\', this);
        topbar.data(\'index\', 0);
        var topbarContainer = topbar.parent();
        if (topbarContainer.hasClass(\'fixed\') || self.is_sticky(topbar, topbarContainer, settings) ) {
          self.settings.sticky_class = settings.sticky_class;
          self.settings.sticky_topbar = topbar;
          topbar.data(\'height\', topbarContainer.outerHeight());
          topbar.data(\'stickyoffset\', topbarContainer.offset().top);
        } else {
          topbar.data(\'height\', topbar.outerHeight());
        }

        if (!settings.assembled) {
          self.assemble(topbar);
        }

        if (settings.is_hover) {
          self.S(\'.has-dropdown\', topbar).addClass(\'not-click\');
        } else {
          self.S(\'.has-dropdown\', topbar).removeClass(\'not-click\');
        }

        // Pad body when sticky (scrolled) or fixed.
        self.add_custom_rule(\'.f-topbar-fixed { padding-top: \' + topbar.data(\'height\') + \'px }\');

        if (topbarContainer.hasClass(\'fixed\')) {
          self.S(\'body\').addClass(\'f-topbar-fixed\');
        }
      });

    },

    is_sticky : function (topbar, topbarContainer, settings) {
      var sticky     = topbarContainer.hasClass(settings.sticky_class);
      var smallMatch = matchMedia(Foundation.media_queries.small).matches;
      var medMatch   = matchMedia(Foundation.media_queries.medium).matches;
      var lrgMatch   = matchMedia(Foundation.media_queries.large).matches;

       if (sticky && settings.sticky_on === \'all\') {
          return true;
       }
       if (sticky && this.small() && settings.sticky_on.indexOf(\'small\') !== -1) {
           if (smallMatch && !medMatch && !lrgMatch) { return true; }
       }
       if (sticky && this.medium() && settings.sticky_on.indexOf(\'medium\') !== -1) {
           if (smallMatch && medMatch && !lrgMatch) { return true; }
       }
       if (sticky && this.large() && settings.sticky_on.indexOf(\'large\') !== -1) {
           if (smallMatch && medMatch && lrgMatch) { return true; }
       }

       // fix for iOS browsers
       if (sticky && navigator.userAgent.match(/(iPad|iPhone|iPod)/g)) {
        return true;
       }
       return false;
    },

    toggle : function (toggleEl) {
      var self = this,
          topbar;

      if (toggleEl) {
        topbar = self.S(toggleEl).closest(\'[\' + this.attr_name() + \']\');
      } else {
        topbar = self.S(\'[\' + this.attr_name() + \']\');
      }

      var settings = topbar.data(this.attr_name(true) + \'-init\');

      var section = self.S(\'section, .top-bar-section\', topbar);

      if (self.breakpoint()) {
        if (!self.rtl) {
          section.css({left : \'0%\'});
          $(\'>.name\', section).css({left : \'100%\'});
        } else {
          section.css({right : \'0%\'});
          $(\'>.name\', section).css({right : \'100%\'});
        }

        self.S(\'li.moved\', section).removeClass(\'moved\');
        topbar.data(\'index\', 0);

        topbar
          .toggleClass(\'expanded\')
          .css(\'height\', \'\');
      }

      if (settings.scrolltop) {
        if (!topbar.hasClass(\'expanded\')) {
          if (topbar.hasClass(\'fixed\')) {
            topbar.parent().addClass(\'fixed\');
            topbar.removeClass(\'fixed\');
            self.S(\'body\').addClass(\'f-topbar-fixed\');
          }
        } else if (topbar.parent().hasClass(\'fixed\')) {
          if (settings.scrolltop) {
            topbar.parent().removeClass(\'fixed\');
            topbar.addClass(\'fixed\');
            self.S(\'body\').removeClass(\'f-topbar-fixed\');

            window.scrollTo(0, 0);
          } else {
            topbar.parent().removeClass(\'expanded\');
          }
        }
      } else {
        if (self.is_sticky(topbar, topbar.parent(), settings)) {
          topbar.parent().addClass(\'fixed\');
        }

        if (topbar.parent().hasClass(\'fixed\')) {
          if (!topbar.hasClass(\'expanded\')) {
            topbar.removeClass(\'fixed\');
            topbar.parent().removeClass(\'expanded\');
            self.update_sticky_positioning();
          } else {
            topbar.addClass(\'fixed\');
            topbar.parent().addClass(\'expanded\');
            self.S(\'body\').addClass(\'f-topbar-fixed\');
          }
        }
      }
    },

    timer : null,

    events : function (bar) {
      var self = this,
          S = this.S;

      S(this.scope)
        .off(\'.topbar\')
        .on(\'click.fndtn.topbar\', \'[\' + this.attr_name() + \'] .toggle-topbar\', function (e) {
          e.preventDefault();
          self.toggle(this);
        })
        .on(\'click.fndtn.topbar\', \'.top-bar .top-bar-section li a[href^="#"],[\' + this.attr_name() + \'] .top-bar-section li a[href^="#"]\', function (e) {
            var li = $(this).closest(\'li\');
            if (self.breakpoint() && !li.hasClass(\'back\') && !li.hasClass(\'has-dropdown\')) {
              self.toggle();
            }
        })
        .on(\'click.fndtn.topbar\', \'[\' + this.attr_name() + \'] li.has-dropdown\', function (e) {
          var li = S(this),
              target = S(e.target),
              topbar = li.closest(\'[\' + self.attr_name() + \']\'),
              settings = topbar.data(self.attr_name(true) + \'-init\');

          if (target.data(\'revealId\')) {
            self.toggle();
            return;
          }

          if (self.breakpoint()) {
            return;
          }

          if (settings.is_hover && !Modernizr.touch) {
            return;
          }

          e.stopImmediatePropagation();

          if (li.hasClass(\'hover\')) {
            li
              .removeClass(\'hover\')
              .find(\'li\')
              .removeClass(\'hover\');

            li.parents(\'li.hover\')
              .removeClass(\'hover\');
          } else {
            li.addClass(\'hover\');

            $(li).siblings().removeClass(\'hover\');

            if (target[0].nodeName === \'A\' && target.parent().hasClass(\'has-dropdown\')) {
              e.preventDefault();
            }
          }
        })
        .on(\'click.fndtn.topbar\', \'[\' + this.attr_name() + \'] .has-dropdown>a\', function (e) {
          if (self.breakpoint()) {

            e.preventDefault();

            var $this = S(this),
                topbar = $this.closest(\'[\' + self.attr_name() + \']\'),
                section = topbar.find(\'section, .top-bar-section\'),
                dropdownHeight = $this.next(\'.dropdown\').outerHeight(),
                $selectedLi = $this.closest(\'li\');

            topbar.data(\'index\', topbar.data(\'index\') + 1);
            $selectedLi.addClass(\'moved\');

            if (!self.rtl) {
              section.css({left : -(100 * topbar.data(\'index\')) + \'%\'});
              section.find(\'>.name\').css({left : 100 * topbar.data(\'index\') + \'%\'});
            } else {
              section.css({right : -(100 * topbar.data(\'index\')) + \'%\'});
              section.find(\'>.name\').css({right : 100 * topbar.data(\'index\') + \'%\'});
            }

            topbar.css(\'height\', $this.siblings(\'ul\').outerHeight(true) + topbar.data(\'height\'));
          }
        });

      S(window).off(\'.topbar\').on(\'resize.fndtn.topbar\', self.throttle(function () {
          self.resize.call(self);
      }, 50)).trigger(\'resize\').trigger(\'resize.fndtn.topbar\').load(function () {
          // Ensure that the offset is calculated after all of the pages resources have loaded
          S(this).trigger(\'resize.fndtn.topbar\');
      });

      S(\'body\').off(\'.topbar\').on(\'click.fndtn.topbar\', function (e) {
        var parent = S(e.target).closest(\'li\').closest(\'li.hover\');

        if (parent.length > 0) {
          return;
        }

        S(\'[\' + self.attr_name() + \'] li.hover\').removeClass(\'hover\');
      });

      // Go up a level on Click
      S(this.scope).on(\'click.fndtn.topbar\', \'[\' + this.attr_name() + \'] .has-dropdown .back\', function (e) {
        e.preventDefault();

        var $this = S(this),
            topbar = $this.closest(\'[\' + self.attr_name() + \']\'),
            section = topbar.find(\'section, .top-bar-section\'),
            settings = topbar.data(self.attr_name(true) + \'-init\'),
            $movedLi = $this.closest(\'li.moved\'),
            $previousLevelUl = $movedLi.parent();

        topbar.data(\'index\', topbar.data(\'index\') - 1);

        if (!self.rtl) {
          section.css({left : -(100 * topbar.data(\'index\')) + \'%\'});
          section.find(\'>.name\').css({left : 100 * topbar.data(\'index\') + \'%\'});
        } else {
          section.css({right : -(100 * topbar.data(\'index\')) + \'%\'});
          section.find(\'>.name\').css({right : 100 * topbar.data(\'index\') + \'%\'});
        }

        if (topbar.data(\'index\') === 0) {
          topbar.css(\'height\', \'\');
        } else {
          topbar.css(\'height\', $previousLevelUl.outerHeight(true) + topbar.data(\'height\'));
        }

        setTimeout(function () {
          $movedLi.removeClass(\'moved\');
        }, 300);
      });

      // Show dropdown menus when their items are focused
      S(this.scope).find(\'.dropdown a\')
        .focus(function () {
          $(this).parents(\'.has-dropdown\').addClass(\'hover\');
        })
        .blur(function () {
          $(this).parents(\'.has-dropdown\').removeClass(\'hover\');
        });
    },

    resize : function () {
      var self = this;
      self.S(\'[\' + this.attr_name() + \']\').each(function () {
        var topbar = self.S(this),
            settings = topbar.data(self.attr_name(true) + \'-init\');

        var stickyContainer = topbar.parent(\'.\' + self.settings.sticky_class);
        var stickyOffset;

        if (!self.breakpoint()) {
          var doToggle = topbar.hasClass(\'expanded\');
          topbar
            .css(\'height\', \'\')
            .removeClass(\'expanded\')
            .find(\'li\')
            .removeClass(\'hover\');

            if (doToggle) {
              self.toggle(topbar);
            }
        }

        if (self.is_sticky(topbar, stickyContainer, settings)) {
          if (stickyContainer.hasClass(\'fixed\')) {
            // Remove the fixed to allow for correct calculation of the offset.
            stickyContainer.removeClass(\'fixed\');

            stickyOffset = stickyContainer.offset().top;
            if (self.S(document.body).hasClass(\'f-topbar-fixed\')) {
              stickyOffset -= topbar.data(\'height\');
            }

            topbar.data(\'stickyoffset\', stickyOffset);
            stickyContainer.addClass(\'fixed\');
          } else {
            stickyOffset = stickyContainer.offset().top;
            topbar.data(\'stickyoffset\', stickyOffset);
          }
        }

      });
    },

    breakpoint : function () {
      return !matchMedia(Foundation.media_queries[\'topbar\']).matches;
    },

    small : function () {
      return matchMedia(Foundation.media_queries[\'small\']).matches;
    },

    medium : function () {
      return matchMedia(Foundation.media_queries[\'medium\']).matches;
    },

    large : function () {
      return matchMedia(Foundation.media_queries[\'large\']).matches;
    },

    assemble : function (topbar) {
      var self = this,
          settings = topbar.data(this.attr_name(true) + \'-init\'),
          section = self.S(\'section, .top-bar-section\', topbar);

      // Pull element out of the DOM for manipulation
      section.detach();

      self.S(\'.has-dropdown>a\', section).each(function () {
        var $link = self.S(this),
            $dropdown = $link.siblings(\'.dropdown\'),
            url = $link.attr(\'href\'),
            $titleLi;

        if (!$dropdown.find(\'.title.back\').length) {

          if (settings.mobile_show_parent_link == true && url) {
            $titleLi = $(\'<li class="title back js-generated"><h5><a href="javascript:void(0)"></a></h5></li><li class="parent-link hide-for-large-up"><a class="parent-link js-generated" href="\' + url + \'">\' + $link.html() +\'</a></li>\');
          } else {
            $titleLi = $(\'<li class="title back js-generated"><h5><a href="javascript:void(0)"></a></h5>\');
          }

          // Copy link to subnav
          if (settings.custom_back_text == true) {
            $(\'h5>a\', $titleLi).html(settings.back_text);
          } else {
            $(\'h5>a\', $titleLi).html(\'&laquo; \' + $link.html());
          }
          $dropdown.prepend($titleLi);
        }
      });

      // Put element back in the DOM
      section.appendTo(topbar);

      // check for sticky
      this.sticky();

      this.assembled(topbar);
    },

    assembled : function (topbar) {
      topbar.data(this.attr_name(true), $.extend({}, topbar.data(this.attr_name(true)), {assembled : true}));
    },

    height : function (ul) {
      var total = 0,
          self = this;

      $(\'> li\', ul).each(function () {
        total += self.S(this).outerHeight(true);
      });

      return total;
    },

    sticky : function () {
      var self = this;

      this.S(window).on(\'scroll\', function () {
        self.update_sticky_positioning();
      });
    },

    update_sticky_positioning : function () {
      var klass = \'.\' + this.settings.sticky_class,
          $window = this.S(window),
          self = this;

      if (self.settings.sticky_topbar && self.is_sticky(this.settings.sticky_topbar, this.settings.sticky_topbar.parent(), this.settings)) {
        var distance = this.settings.sticky_topbar.data(\'stickyoffset\');
        if (!self.S(klass).hasClass(\'expanded\')) {
          if ($window.scrollTop() > (distance)) {
            if (!self.S(klass).hasClass(\'fixed\')) {
              self.S(klass).addClass(\'fixed\');
              self.S(\'body\').addClass(\'f-topbar-fixed\');
            }
          } else if ($window.scrollTop() <= distance) {
            if (self.S(klass).hasClass(\'fixed\')) {
              self.S(klass).removeClass(\'fixed\');
              self.S(\'body\').removeClass(\'f-topbar-fixed\');
            }
          }
        }
      }
    },

    off : function () {
      this.S(this.scope).off(\'.fndtn.topbar\');
      this.S(window).off(\'.fndtn.topbar\');
    },

    reflow : function () {}
  };
}(jQuery, window, window.document));
;(function ($, window, document, undefined) {
  \'use strict\';

  Foundation.libs.tab = {
    name : \'tab\',

    version : \'5.5.1\',

    settings : {
      active_class : \'active\',
      callback : function () {},
      deep_linking : false,
      scroll_to_content : true,
      is_hover : false
    },

    default_tab_hashes : [],

    init : function (scope, method, options) {
      var self = this,
          S = this.S;

      this.bindings(method, options);

      // store the initial href, which is used to allow correct behaviour of the
      // browser back button when deep linking is turned on.
      self.entry_location = window.location.href;

      this.handle_location_hash_change();

      // Store the default active tabs which will be referenced when the
      // location hash is absent, as in the case of navigating the tabs and
      // returning to the first viewing via the browser Back button.
      S(\'[\' + this.attr_name() + \'] > .active > a\', this.scope).each(function () {
        self.default_tab_hashes.push(this.hash);
      });
    },

    events : function () {
      var self = this,
          S = this.S;

      var usual_tab_behavior =  function (e) {
          var settings = S(this).closest(\'[\' + self.attr_name() + \']\').data(self.attr_name(true) + \'-init\');
          if (!settings.is_hover || Modernizr.touch) {
            e.preventDefault();
            e.stopPropagation();
            self.toggle_active_tab(S(this).parent());
          }
        };

      S(this.scope)
        .off(\'.tab\')
        // Click event: tab title
        .on(\'focus.fndtn.tab\', \'[\' + this.attr_name() + \'] > * > a\', usual_tab_behavior )
        .on(\'click.fndtn.tab\', \'[\' + this.attr_name() + \'] > * > a\', usual_tab_behavior )
        // Hover event: tab title
        .on(\'mouseenter.fndtn.tab\', \'[\' + this.attr_name() + \'] > * > a\', function (e) {
          var settings = S(this).closest(\'[\' + self.attr_name() + \']\').data(self.attr_name(true) + \'-init\');
          if (settings.is_hover) {
            self.toggle_active_tab(S(this).parent());
          }
        });

      // Location hash change event
      S(window).on(\'hashchange.fndtn.tab\', function (e) {
        e.preventDefault();
        self.handle_location_hash_change();
      });
    },

    handle_location_hash_change : function () {

      var self = this,
          S = this.S;

      S(\'[\' + this.attr_name() + \']\', this.scope).each(function () {
        var settings = S(this).data(self.attr_name(true) + \'-init\');
        if (settings.deep_linking) {
          // Match the location hash to a label
          var hash;
          if (settings.scroll_to_content) {
            hash = self.scope.location.hash;
          } else {
            // prefix the hash to prevent anchor scrolling
            hash = self.scope.location.hash.replace(\'fndtn-\', \'\');
          }
          if (hash != \'\') {
            // Check whether the location hash references a tab content div or
            // another element on the page (inside or outside the tab content div)
            var hash_element = S(hash);
            if (hash_element.hasClass(\'content\') && hash_element.parent().hasClass(\'tabs-content\')) {
              // Tab content div
              self.toggle_active_tab($(\'[\' + self.attr_name() + \'] > * > a[href=\' + hash + \']\').parent());
            } else {
              // Not the tab content div. If inside the tab content, find the
              // containing tab and toggle it as active.
              var hash_tab_container_id = hash_element.closest(\'.content\').attr(\'id\');
              if (hash_tab_container_id != undefined) {
                self.toggle_active_tab($(\'[\' + self.attr_name() + \'] > * > a[href=#\' + hash_tab_container_id + \']\').parent(), hash);
              }
            }
          } else {
            // Reference the default tab hashes which were initialized in the init function
            for (var ind = 0; ind < self.default_tab_hashes.length; ind++) {
              self.toggle_active_tab($(\'[\' + self.attr_name() + \'] > * > a[href=\' + self.default_tab_hashes[ind] + \']\').parent());
            }
          }
        }
       });
     },

    toggle_active_tab : function (tab, location_hash) {
      var self = this,
          S = self.S,
          tabs = tab.closest(\'[\' + this.attr_name() + \']\'),
          tab_link = tab.find(\'a\'),
          anchor = tab.children(\'a\').first(),
          target_hash = \'#\' + anchor.attr(\'href\').split(\'#\')[1],
          target = S(target_hash),
          siblings = tab.siblings(),
          settings = tabs.data(this.attr_name(true) + \'-init\'),
          interpret_keyup_action = function (e) {
            // Light modification of Heydon Pickering\'s Practical ARIA Examples: http://heydonworks.com/practical_aria_examples/js/a11y.js

            // define current, previous and next (possible) tabs

            var $original = $(this);
            var $prev = $(this).parents(\'li\').prev().children(\'[role="tab"]\');
            var $next = $(this).parents(\'li\').next().children(\'[role="tab"]\');
            var $target;

            // find the direction (prev or next)

            switch (e.keyCode) {
              case 37:
                $target = $prev;
                break;
              case 39:
                $target = $next;
                break;
              default:
                $target = false
                  break;
            }

            if ($target.length) {
              $original.attr({
                \'tabindex\' : \'-1\',
                \'aria-selected\' : null
              });
              $target.attr({
                \'tabindex\' : \'0\',
                \'aria-selected\' : true
              }).focus();
            }

            // Hide panels

            $(\'[role="tabpanel"]\')
              .attr(\'aria-hidden\', \'true\');

            // Show panel which corresponds to target

            $(\'#\' + $(document.activeElement).attr(\'href\').substring(1))
              .attr(\'aria-hidden\', null);

          },
          go_to_hash = function(hash) {
            // This function allows correct behaviour of the browser\'s back button when deep linking is enabled. Without it
            // the user would get continually redirected to the default hash.
            var is_entry_location = window.location.href === self.entry_location,
                default_hash = settings.scroll_to_content ? self.default_tab_hashes[0] : is_entry_location ? window.location.hash :\'fndtn-\' + self.default_tab_hashes[0].replace(\'#\', \'\')

            if (!(is_entry_location && hash === default_hash)) {
              window.location.hash = hash;
            }
          };

      // allow usage of data-tab-content attribute instead of href
      if (S(this).data(this.data_attr(\'tab-content\'))) {
        target_hash = \'#\' + S(this).data(this.data_attr(\'tab-content\')).split(\'#\')[1];
        target = S(target_hash);
      }

      if (settings.deep_linking) {

        if (settings.scroll_to_content) {

          // retain current hash to scroll to content
          go_to_hash(location_hash || target_hash);

          if (location_hash == undefined || location_hash == target_hash) {
            tab.parent()[0].scrollIntoView();
          } else {
            S(target_hash)[0].scrollIntoView();
          }
        } else {
          // prefix the hashes so that the browser doesn\'t scroll down
          if (location_hash != undefined) {
            go_to_hash(\'fndtn-\' + location_hash.replace(\'#\', \'\'));
          } else {
            go_to_hash(\'fndtn-\' + target_hash.replace(\'#\', \'\'));
          }
        }
      }

      // WARNING: The activation and deactivation of the tab content must
      // occur after the deep linking in order to properly refresh the browser
      // window (notably in Chrome).
      // Clean up multiple attr instances to done once
      tab.addClass(settings.active_class).triggerHandler(\'opened\');
      tab_link.attr({\'aria-selected\' : \'true\',  tabindex : 0});
      siblings.removeClass(settings.active_class)
      siblings.find(\'a\').attr({\'aria-selected\' : \'false\',  tabindex : -1});
      target.siblings().removeClass(settings.active_class).attr({\'aria-hidden\' : \'true\',  tabindex : -1});
      target.addClass(settings.active_class).attr(\'aria-hidden\', \'false\').removeAttr(\'tabindex\');
      settings.callback(tab);
      target.triggerHandler(\'toggled\', [tab]);
      tabs.triggerHandler(\'toggled\', [target]);

      tab_link.off(\'keydown\').on(\'keydown\', interpret_keyup_action );
    },

    data_attr : function (str) {
      if (this.namespace.length > 0) {
        return this.namespace + \'-\' + str;
      }

      return str;
    },

    off : function () {},

    reflow : function () {}
  };
}(jQuery, window, window.document));
;(function ($, window, document, undefined) {
  \'use strict\';

  Foundation.libs.abide = {
    name : \'abide\',

    version : \'5.5.1\',

    settings : {
      live_validate : true,
      validate_on_blur : true,
      focus_on_invalid : true,
      error_labels : true, // labels with a for="inputId" will recieve an `error` class
      error_class : \'error\',
      timeout : 1000,
      patterns : {
        alpha : /^[a-zA-Z]+$/,
        alpha_numeric : /^[a-zA-Z0-9]+$/,
        integer : /^[-+]?\\d+$/,
        number : /^[-+]?\\d*(?:[\\.\\,]\\d+)?$/,

        // amex, visa, diners
        card : /^(?:4[0-9]{12}(?:[0-9]{3})?|5[1-5][0-9]{14}|6(?:011|5[0-9][0-9])[0-9]{12}|3[47][0-9]{13}|3(?:0[0-5]|[68][0-9])[0-9]{11}|(?:2131|1800|35\\d{3})\\d{11})$/,
        cvv : /^([0-9]){3,4}$/,

        // http://www.whatwg.org/specs/web-apps/current-work/multipage/states-of-the-type-attribute.html#valid-e-mail-address
        email : /^[a-zA-Z0-9.!#$%&\'*+\\/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)+$/,

        url : /^(https?|ftp|file|ssh):\\/\\/(((([a-zA-Z]|\\d|-|\\.|_|~|[\\u00A0-\\uD7FF\\uF900-\\uFDCF\\uFDF0-\\uFFEF])|(%[\\da-f]{2})|[!\\$&\'\\(\\)\\*\\+,;=]|:)*@)?(((\\d|[1-9]\\d|1\\d\\d|2[0-4]\\d|25[0-5])\\.(\\d|[1-9]\\d|1\\d\\d|2[0-4]\\d|25[0-5])\\.(\\d|[1-9]\\d|1\\d\\d|2[0-4]\\d|25[0-5])\\.(\\d|[1-9]\\d|1\\d\\d|2[0-4]\\d|25[0-5]))|((([a-zA-Z]|\\d|[\\u00A0-\\uD7FF\\uF900-\\uFDCF\\uFDF0-\\uFFEF])|(([a-zA-Z]|\\d|[\\u00A0-\\uD7FF\\uF900-\\uFDCF\\uFDF0-\\uFFEF])([a-zA-Z]|\\d|-|\\.|_|~|[\\u00A0-\\uD7FF\\uF900-\\uFDCF\\uFDF0-\\uFFEF])*([a-zA-Z]|\\d|[\\u00A0-\\uD7FF\\uF900-\\uFDCF\\uFDF0-\\uFFEF])))\\.)+(([a-zA-Z]|[\\u00A0-\\uD7FF\\uF900-\\uFDCF\\uFDF0-\\uFFEF])|(([a-zA-Z]|[\\u00A0-\\uD7FF\\uF900-\\uFDCF\\uFDF0-\\uFFEF])([a-zA-Z]|\\d|-|\\.|_|~|[\\u00A0-\\uD7FF\\uF900-\\uFDCF\\uFDF0-\\uFFEF])*([a-zA-Z]|[\\u00A0-\\uD7FF\\uF900-\\uFDCF\\uFDF0-\\uFFEF])))\\.?)(:\\d*)?)(\\/((([a-zA-Z]|\\d|-|\\.|_|~|[\\u00A0-\\uD7FF\\uF900-\\uFDCF\\uFDF0-\\uFFEF])|(%[\\da-f]{2})|[!\\$&\'\\(\\)\\*\\+,;=]|:|@)+(\\/(([a-zA-Z]|\\d|-|\\.|_|~|[\\u00A0-\\uD7FF\\uF900-\\uFDCF\\uFDF0-\\uFFEF])|(%[\\da-f]{2})|[!\\$&\'\\(\\)\\*\\+,;=]|:|@)*)*)?)?(\\?((([a-zA-Z]|\\d|-|\\.|_|~|[\\u00A0-\\uD7FF\\uF900-\\uFDCF\\uFDF0-\\uFFEF])|(%[\\da-f]{2})|[!\\$&\'\\(\\)\\*\\+,;=]|:|@)|[\\uE000-\\uF8FF]|\\/|\\?)*)?(\\#((([a-zA-Z]|\\d|-|\\.|_|~|[\\u00A0-\\uD7FF\\uF900-\\uFDCF\\uFDF0-\\uFFEF])|(%[\\da-f]{2})|[!\\$&\'\\(\\)\\*\\+,;=]|:|@)|\\/|\\?)*)?$/,
        // abc.de
        domain : /^([a-zA-Z0-9]([a-zA-Z0-9\\-]{0,61}[a-zA-Z0-9])?\\.)+[a-zA-Z]{2,8}$/,

        datetime : /^([0-2][0-9]{3})\\-([0-1][0-9])\\-([0-3][0-9])T([0-5][0-9])\\:([0-5][0-9])\\:([0-5][0-9])(Z|([\\-\\+]([0-1][0-9])\\:00))$/,
        // YYYY-MM-DD
        date : /(?:19|20)[0-9]{2}-(?:(?:0[1-9]|1[0-2])-(?:0[1-9]|1[0-9]|2[0-9])|(?:(?!02)(?:0[1-9]|1[0-2])-(?:30))|(?:(?:0[13578]|1[02])-31))$/,
        // HH:MM:SS
        time : /^(0[0-9]|1[0-9]|2[0-3])(:[0-5][0-9]){2}$/,
        dateISO : /^\\d{4}[\\/\\-]\\d{1,2}[\\/\\-]\\d{1,2}$/,
        // MM/DD/YYYY
        month_day_year : /^(0[1-9]|1[012])[- \\/.](0[1-9]|[12][0-9]|3[01])[- \\/.]\\d{4}$/,
        // DD/MM/YYYY
        day_month_year : /^(0[1-9]|[12][0-9]|3[01])[- \\/.](0[1-9]|1[012])[- \\/.]\\d{4}$/,

        // #FFF or #FFFFFF
        color : /^#?([a-fA-F0-9]{6}|[a-fA-F0-9]{3})$/
      },
      validators : {
        equalTo : function (el, required, parent) {
          var from  = document.getElementById(el.getAttribute(this.add_namespace(\'data-equalto\'))).value,
              to    = el.value,
              valid = (from === to);

          return valid;
        }
      }
    },

    timer : null,

    init : function (scope, method, options) {
      this.bindings(method, options);
    },

    events : function (scope) {
      var self = this,
          form = self.S(scope).attr(\'novalidate\', \'novalidate\'),
          settings = form.data(this.attr_name(true) + \'-init\') || {};

      this.invalid_attr = this.add_namespace(\'data-invalid\');

      form
        .off(\'.abide\')
        .on(\'submit.fndtn.abide validate.fndtn.abide\', function (e) {
          var is_ajax = /ajax/i.test(self.S(this).attr(self.attr_name()));
          return self.validate(self.S(this).find(\'input, textarea, select\').get(), e, is_ajax);
        })
        .on(\'reset\', function () {
          return self.reset($(this));
        })
        .find(\'input, textarea, select\')
          .off(\'.abide\')
          .on(\'blur.fndtn.abide change.fndtn.abide\', function (e) {
            if (settings.validate_on_blur === true) {
              self.validate([this], e);
            }
          })
          .on(\'keydown.fndtn.abide\', function (e) {
            if (settings.live_validate === true && e.which != 9) {
              clearTimeout(self.timer);
              self.timer = setTimeout(function () {
                self.validate([this], e);
              }.bind(this), settings.timeout);
            }
          });
    },

    reset : function (form) {
      form.removeAttr(this.invalid_attr);
      $(this.invalid_attr, form).removeAttr(this.invalid_attr);
      $(\'.\' + this.settings.error_class, form).not(\'small\').removeClass(this.settings.error_class);
    },

    validate : function (els, e, is_ajax) {
      var validations = this.parse_patterns(els),
          validation_count = validations.length,
          form = this.S(els[0]).closest(\'form\'),
          submit_event = /submit/.test(e.type);

      // Has to count up to make sure the focus gets applied to the top error
      for (var i = 0; i < validation_count; i++) {
        if (!validations[i] && (submit_event || is_ajax)) {
          if (this.settings.focus_on_invalid) {
            els[i].focus();
          }
          form.trigger(\'invalid\').trigger(\'invalid.fndtn.abide\');
          this.S(els[i]).closest(\'form\').attr(this.invalid_attr, \'\');
          return false;
        }
      }

      if (submit_event || is_ajax) {
        form.trigger(\'valid\').trigger(\'valid.fndtn.abide\');
      }

      form.removeAttr(this.invalid_attr);

      if (is_ajax) {
        return false;
      }

      return true;
    },

    parse_patterns : function (els) {
      var i = els.length,
          el_patterns = [];

      while (i--) {
        el_patterns.push(this.pattern(els[i]));
      }

      return this.check_validation_and_apply_styles(el_patterns);
    },

    pattern : function (el) {
      var type = el.getAttribute(\'type\'),
          required = typeof el.getAttribute(\'required\') === \'string\';

      var pattern = el.getAttribute(\'pattern\') || \'\';

      if (this.settings.patterns.hasOwnProperty(pattern) && pattern.length > 0) {
        return [el, this.settings.patterns[pattern], required];
      } else if (pattern.length > 0) {
        return [el, new RegExp(pattern), required];
      }

      if (this.settings.patterns.hasOwnProperty(type)) {
        return [el, this.settings.patterns[type], required];
      }

      pattern = /.*/;

      return [el, pattern, required];
    },

    // TODO: Break this up into smaller methods, getting hard to read.
    check_validation_and_apply_styles : function (el_patterns) {
      var i = el_patterns.length,
          validations = [],
          form = this.S(el_patterns[0][0]).closest(\'[data-\' + this.attr_name(true) + \']\'),
          settings = form.data(this.attr_name(true) + \'-init\') || {};
      while (i--) {
        var el = el_patterns[i][0],
            required = el_patterns[i][2],
            value = el.value.trim(),
            direct_parent = this.S(el).parent(),
            validator = el.getAttribute(this.add_namespace(\'data-abide-validator\')),
            is_radio = el.type === \'radio\',
            is_checkbox = el.type === \'checkbox\',
            label = this.S(\'label[for="\' + el.getAttribute(\'id\') + \'"]\'),
            valid_length = (required) ? (el.value.length > 0) : true,
            el_validations = [];

        var parent, valid;

        // support old way to do equalTo validations
        if (el.getAttribute(this.add_namespace(\'data-equalto\'))) { validator = \'equalTo\' }

        if (!direct_parent.is(\'label\')) {
          parent = direct_parent;
        } else {
          parent = direct_parent.parent();
        }

        if (validator) {
          valid = this.settings.validators[validator].apply(this, [el, required, parent]);
          el_validations.push(valid);
        }

        if (is_radio && required) {
          el_validations.push(this.valid_radio(el, required));
        } else if (is_checkbox && required) {
          el_validations.push(this.valid_checkbox(el, required));
        } else {

          if (el_patterns[i][1].test(value) && valid_length ||
            !required && el.value.length < 1 || $(el).attr(\'disabled\')) {
            el_validations.push(true);
          } else {
            el_validations.push(false);
          }

          el_validations = [el_validations.every(function (valid) {return valid;})];

          if (el_validations[0]) {
            this.S(el).removeAttr(this.invalid_attr);
            el.setAttribute(\'aria-invalid\', \'false\');
            el.removeAttribute(\'aria-describedby\');
            parent.removeClass(this.settings.error_class);
            if (label.length > 0 && this.settings.error_labels) {
              label.removeClass(this.settings.error_class).removeAttr(\'role\');
            }
            $(el).triggerHandler(\'valid\');
          } else {
            this.S(el).attr(this.invalid_attr, \'\');
            el.setAttribute(\'aria-invalid\', \'true\');

            // Try to find the error associated with the input
            var errorElem = parent.find(\'small.\' + this.settings.error_class, \'span.\' + this.settings.error_class);
            var errorID = errorElem.length > 0 ? errorElem[0].id : \'\';
            if (errorID.length > 0) {
              el.setAttribute(\'aria-describedby\', errorID);
            }

            // el.setAttribute(\'aria-describedby\', $(el).find(\'.error\')[0].id);
            parent.addClass(this.settings.error_class);
            if (label.length > 0 && this.settings.error_labels) {
              label.addClass(this.settings.error_class).attr(\'role\', \'alert\');
            }
            $(el).triggerHandler(\'invalid\');
          }
        }
        validations.push(el_validations[0]);
      }
      validations = [validations.every(function (valid) {return valid;})];
      return validations;
    },

    valid_checkbox : function (el, required) {
      var el = this.S(el),
          valid = (el.is(\':checked\') || !required || el.get(0).getAttribute(\'disabled\'));

      if (valid) {
        el.removeAttr(this.invalid_attr).parent().removeClass(this.settings.error_class);
      } else {
        el.attr(this.invalid_attr, \'\').parent().addClass(this.settings.error_class);
      }

      return valid;
    },

    valid_radio : function (el, required) {
      var name = el.getAttribute(\'name\'),
          group = this.S(el).closest(\'[data-\' + this.attr_name(true) + \']\').find("[name=\'" + name + "\']"),
          count = group.length,
          valid = false,
          disabled = false;

      // Has to count up to make sure the focus gets applied to the top error
        for (var i=0; i < count; i++) {
            if( group[i].getAttribute(\'disabled\') ){
                disabled=true;
                valid=true;
            } else {
                if (group[i].checked){
                    valid = true;
                } else {
                    if( disabled ){
                        valid = false;
                    }
                }
            }
        }

      // Has to count up to make sure the focus gets applied to the top error
      for (var i = 0; i < count; i++) {
        if (valid) {
          this.S(group[i]).removeAttr(this.invalid_attr).parent().removeClass(this.settings.error_class);
        } else {
          this.S(group[i]).attr(this.invalid_attr, \'\').parent().addClass(this.settings.error_class);
        }
      }

      return valid;
    },

    valid_equal : function (el, required, parent) {
      var from  = document.getElementById(el.getAttribute(this.add_namespace(\'data-equalto\'))).value,
          to    = el.value,
          valid = (from === to);

      if (valid) {
        this.S(el).removeAttr(this.invalid_attr);
        parent.removeClass(this.settings.error_class);
        if (label.length > 0 && settings.error_labels) {
          label.removeClass(this.settings.error_class);
        }
      } else {
        this.S(el).attr(this.invalid_attr, \'\');
        parent.addClass(this.settings.error_class);
        if (label.length > 0 && settings.error_labels) {
          label.addClass(this.settings.error_class);
        }
      }

      return valid;
    },

    valid_oneof : function (el, required, parent, doNotValidateOthers) {
      var el = this.S(el),
        others = this.S(\'[\' + this.add_namespace(\'data-oneof\') + \']\'),
        valid = others.filter(\':checked\').length > 0;

      if (valid) {
        el.removeAttr(this.invalid_attr).parent().removeClass(this.settings.error_class);
      } else {
        el.attr(this.invalid_attr, \'\').parent().addClass(this.settings.error_class);
      }

      if (!doNotValidateOthers) {
        var _this = this;
        others.each(function () {
          _this.valid_oneof.call(_this, this, null, null, true);
        });
      }

      return valid;
    }
  };
}(jQuery, window, window.document));
;(function ($, window, document, undefined) {
  \'use strict\';

  Foundation.libs.tooltip = {
    name : \'tooltip\',

    version : \'5.5.1\',

    settings : {
      additional_inheritable_classes : [],
      tooltip_class : \'.tooltip\',
      append_to : \'body\',
      touch_close_text : \'Tap To Close\',
      disable_for_touch : false,
      hover_delay : 200,
      show_on : \'all\',
      tip_template : function (selector, content) {
        return \'<span data-selector="\' + selector + \'" id="\' + selector + \'" class="\'
          + Foundation.libs.tooltip.settings.tooltip_class.substring(1)
          + \'" role="tooltip">\' + content + \'<span class="nub"></span></span>\';
      }
    },

    cache : {},

    init : function (scope, method, options) {
      Foundation.inherit(this, \'random_str\');
      this.bindings(method, options);
    },

    should_show : function (target, tip) {
      var settings = $.extend({}, this.settings, this.data_options(target));

      if (settings.show_on === \'all\') {
        return true;
      } else if (this.small() && settings.show_on === \'small\') {
        return true;
      } else if (this.medium() && settings.show_on === \'medium\') {
        return true;
      } else if (this.large() && settings.show_on === \'large\') {
        return true;
      }
      return false;
    },

    medium : function () {
      return matchMedia(Foundation.media_queries[\'medium\']).matches;
    },

    large : function () {
      return matchMedia(Foundation.media_queries[\'large\']).matches;
    },

    events : function (instance) {
      var self = this,
          S = self.S;

      self.create(this.S(instance));

      $(this.scope)
        .off(\'.tooltip\')
        .on(\'mouseenter.fndtn.tooltip mouseleave.fndtn.tooltip touchstart.fndtn.tooltip MSPointerDown.fndtn.tooltip\',
          \'[\' + this.attr_name() + \']\', function (e) {
          var $this = S(this),
              settings = $.extend({}, self.settings, self.data_options($this)),
              is_touch = false;

          if (Modernizr.touch && /touchstart|MSPointerDown/i.test(e.type) && S(e.target).is(\'a\')) {
            return false;
          }

          if (/mouse/i.test(e.type) && self.ie_touch(e)) {
            return false;
          }

          if ($this.hasClass(\'open\')) {
            if (Modernizr.touch && /touchstart|MSPointerDown/i.test(e.type)) {
              e.preventDefault();
            }
            self.hide($this);
          } else {
            if (settings.disable_for_touch && Modernizr.touch && /touchstart|MSPointerDown/i.test(e.type)) {
              return;
            } else if (!settings.disable_for_touch && Modernizr.touch && /touchstart|MSPointerDown/i.test(e.type)) {
              e.preventDefault();
              S(settings.tooltip_class + \'.open\').hide();
              is_touch = true;
            }

            if (/enter|over/i.test(e.type)) {
              this.timer = setTimeout(function () {
                var tip = self.showTip($this);
              }.bind(this), self.settings.hover_delay);
            } else if (e.type === \'mouseout\' || e.type === \'mouseleave\') {
              clearTimeout(this.timer);
              self.hide($this);
            } else {
              self.showTip($this);
            }
          }
        })
        .on(\'mouseleave.fndtn.tooltip touchstart.fndtn.tooltip MSPointerDown.fndtn.tooltip\', \'[\' + this.attr_name() + \'].open\', function (e) {
          if (/mouse/i.test(e.type) && self.ie_touch(e)) {
            return false;
          }

          if ($(this).data(\'tooltip-open-event-type\') == \'touch\' && e.type == \'mouseleave\') {
            return;
          } else if ($(this).data(\'tooltip-open-event-type\') == \'mouse\' && /MSPointerDown|touchstart/i.test(e.type)) {
            self.convert_to_touch($(this));
          } else {
            self.hide($(this));
          }
        })
        .on(\'DOMNodeRemoved DOMAttrModified\', \'[\' + this.attr_name() + \']:not(a)\', function (e) {
          self.hide(S(this));
        });
    },

    ie_touch : function (e) {
      // How do I distinguish between IE11 and Windows Phone 8?????
      return false;
    },

    showTip : function ($target) {
      var $tip = this.getTip($target);
      if (this.should_show($target, $tip)) {
        return this.show($target);
      }
      return;
    },

    getTip : function ($target) {
      var selector = this.selector($target),
          settings = $.extend({}, this.settings, this.data_options($target)),
          tip = null;

      if (selector) {
        tip = this.S(\'span[data-selector="\' + selector + \'"]\' + settings.tooltip_class);
      }

      return (typeof tip === \'object\') ? tip : false;
    },

    selector : function ($target) {
      var id = $target.attr(\'id\'),
          dataSelector = $target.attr(this.attr_name()) || $target.attr(\'data-selector\');

      if ((id && id.length < 1 || !id) && typeof dataSelector != \'string\') {
        dataSelector = this.random_str(6);
        $target
          .attr(\'data-selector\', dataSelector)
          .attr(\'aria-describedby\', dataSelector);
      }

      return (id && id.length > 0) ? id : dataSelector;
    },

    create : function ($target) {
      var self = this,
          settings = $.extend({}, this.settings, this.data_options($target)),
          tip_template = this.settings.tip_template;

      if (typeof settings.tip_template === \'string\' && window.hasOwnProperty(settings.tip_template)) {
        tip_template = window[settings.tip_template];
      }

      var $tip = $(tip_template(this.selector($target), $(\'<div></div>\').html($target.attr(\'title\')).html())),
          classes = this.inheritable_classes($target);

      $tip.addClass(classes).appendTo(settings.append_to);

      if (Modernizr.touch) {
        $tip.append(\'<span class="tap-to-close">\' + settings.touch_close_text + \'</span>\');
        $tip.on(\'touchstart.fndtn.tooltip MSPointerDown.fndtn.tooltip\', function (e) {
          self.hide($target);
        });
      }

      $target.removeAttr(\'title\').attr(\'title\', \'\');
    },

    reposition : function (target, tip, classes) {
      var width, nub, nubHeight, nubWidth, column, objPos;

      tip.css(\'visibility\', \'hidden\').show();

      width = target.data(\'width\');
      nub = tip.children(\'.nub\');
      nubHeight = nub.outerHeight();
      nubWidth = nub.outerHeight();

      if (this.small()) {
        tip.css({\'width\' : \'100%\'});
      } else {
        tip.css({\'width\' : (width) ? width : \'auto\'});
      }

      objPos = function (obj, top, right, bottom, left, width) {
        return obj.css({
          \'top\' : (top) ? top : \'auto\',
          \'bottom\' : (bottom) ? bottom : \'auto\',
          \'left\' : (left) ? left : \'auto\',
          \'right\' : (right) ? right : \'auto\'
        }).end();
      };

      objPos(tip, (target.offset().top + target.outerHeight() + 10), \'auto\', \'auto\', target.offset().left);

      if (this.small()) {
        objPos(tip, (target.offset().top + target.outerHeight() + 10), \'auto\', \'auto\', 12.5, $(this.scope).width());
        tip.addClass(\'tip-override\');
        objPos(nub, -nubHeight, \'auto\', \'auto\', target.offset().left);
      } else {
        var left = target.offset().left;
        if (Foundation.rtl) {
          nub.addClass(\'rtl\');
          left = target.offset().left + target.outerWidth() - tip.outerWidth();
        }
        objPos(tip, (target.offset().top + target.outerHeight() + 10), \'auto\', \'auto\', left);
        tip.removeClass(\'tip-override\');
        if (classes && classes.indexOf(\'tip-top\') > -1) {
          if (Foundation.rtl) {
            nub.addClass(\'rtl\');
          }
          objPos(tip, (target.offset().top - tip.outerHeight()), \'auto\', \'auto\', left)
            .removeClass(\'tip-override\');
        } else if (classes && classes.indexOf(\'tip-left\') > -1) {
          objPos(tip, (target.offset().top + (target.outerHeight() / 2) - (tip.outerHeight() / 2)), \'auto\', \'auto\', (target.offset().left - tip.outerWidth() - nubHeight))
            .removeClass(\'tip-override\');
          nub.removeClass(\'rtl\');
        } else if (classes && classes.indexOf(\'tip-right\') > -1) {
          objPos(tip, (target.offset().top + (target.outerHeight() / 2) - (tip.outerHeight() / 2)), \'auto\', \'auto\', (target.offset().left + target.outerWidth() + nubHeight))
            .removeClass(\'tip-override\');
          nub.removeClass(\'rtl\');
        }
      }

      tip.css(\'visibility\', \'visible\').hide();
    },

    small : function () {
      return matchMedia(Foundation.media_queries.small).matches &&
        !matchMedia(Foundation.media_queries.medium).matches;
    },

    inheritable_classes : function ($target) {
      var settings = $.extend({}, this.settings, this.data_options($target)),
          inheritables = [\'tip-top\', \'tip-left\', \'tip-bottom\', \'tip-right\', \'radius\', \'round\'].concat(settings.additional_inheritable_classes),
          classes = $target.attr(\'class\'),
          filtered = classes ? $.map(classes.split(\' \'), function (el, i) {
            if ($.inArray(el, inheritables) !== -1) {
              return el;
            }
          }).join(\' \') : \'\';

      return $.trim(filtered);
    },

    convert_to_touch : function ($target) {
      var self = this,
          $tip = self.getTip($target),
          settings = $.extend({}, self.settings, self.data_options($target));

      if ($tip.find(\'.tap-to-close\').length === 0) {
        $tip.append(\'<span class="tap-to-close">\' + settings.touch_close_text + \'</span>\');
        $tip.on(\'click.fndtn.tooltip.tapclose touchstart.fndtn.tooltip.tapclose MSPointerDown.fndtn.tooltip.tapclose\', function (e) {
          self.hide($target);
        });
      }

      $target.data(\'tooltip-open-event-type\', \'touch\');
    },

    show : function ($target) {
      var $tip = this.getTip($target);

      if ($target.data(\'tooltip-open-event-type\') == \'touch\') {
        this.convert_to_touch($target);
      }

      this.reposition($target, $tip, $target.attr(\'class\'));
      $target.addClass(\'open\');
      $tip.fadeIn(150);
    },

    hide : function ($target) {
      var $tip = this.getTip($target);

      $tip.fadeOut(150, function () {
        $tip.find(\'.tap-to-close\').remove();
        $tip.off(\'click.fndtn.tooltip.tapclose MSPointerDown.fndtn.tapclose\');
        $target.removeClass(\'open\');
      });
    },

    off : function () {
      var self = this;
      this.S(this.scope).off(\'.fndtn.tooltip\');
      this.S(this.settings.tooltip_class).each(function (i) {
        $(\'[\' + self.attr_name() + \']\').eq(i).attr(\'title\', $(this).text());
      }).remove();
    },

    reflow : function () {}
  };
}(jQuery, window, window.document));
';

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
        
        $result .= '/*! normalize.css v3.0.2 | MIT License | git.io/normalize */

/**
 * 1. Set default font family to sans-serif.
 * 2. Prevent iOS text size adjust after orientation change, without disabling
 *    user zoom.
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
 * Hide the `template` element in IE 8/9/11, Safari, and Firefox < 22.
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
 * Improve readability when focused and also mouse hovered in all browsers.
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
  -moz-box-sizing: content-box;
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
 * 2. Address `box-sizing` set to `border-box` in Safari and Chrome
 *    (include `-moz` to future-proof).
 */

input[type="search"] {
  -webkit-appearance: textfield; /* 1 */
  -moz-box-sizing: content-box;
  -webkit-box-sizing: content-box; /* 2 */
  box-sizing: content-box;
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
        $result = self::valueToString($name);
        $result = trim(strtoupper($result));

        return $result;
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
        $result = str_ireplace(' ' , '_'     , $result);
        $result = str_ireplace("\n", '<br />', $result);

        return $result;
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

        $result .= "var p = window.open('', 'phpDeeBuk', 'width=800,height=600,resizable=yes,scrollbars=yes');\n";
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

        // <body>
        {
            $tabs = array();

            $tabs[] = $this->createTabEntry('Console', 'getConsoleHtml', true);
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

        $this->_analyzeDumpCounter = 0;
        $this->_analyzeObjCounter  = 0;
        $this->_analyzeValCounter  = 0;
        $this->_tabs               = array();

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
                    if (call_user_func($predicate, $v->name, $v->value)) {
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
     *
     * @return string The converted value.
     */
    public static function valueToString($value) {
        if (!is_array($value)) {
            if (!is_object($value)) {
                $str = settype($value, 'string');
                if (false !== $str) {
                    return $str;
                }
            }
            else {
                if (method_exists($value, '__toString')) {
                    return $value->__toString();
                }
            }
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
        $newEntry               = new \stdClass();
        $newEntry->bgColor      = $this->_bgColor;
        $newEntry->fgColor      = $this->_fgColor;
        $newEntry->isBold       = $this->_isBold;
        $newEntry->isUnderline  = $this->_isUnderline;
        $newEntry->value        = $str;

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
        $str = call_user_func_array('sprintf',
                                    func_get_args());

        return $this->write($str);
    }

    /**
     * Writes data to the console and appends a new line.
     *
     * @param mixed $str The data to write.
     *
     * @return $this
     */
    public function writeLine($str = null) {
        return $this->write(strval($str) . "\n");
    }
}
