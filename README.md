# phpDeeBuk

Debugger that outputs PHP data via JavaScript generated popup.

## Getting started

* Include [phpDeeBuk class](https://github.com/mkloubert/phpDeeBuk/blob/master/phpDeeBuk.php) to your project.
* And work:

```php
<?php

class MyClass {
    public $Var2 = 'TM';
    private $Var1 = 'MK';
    
    const MY_CONST = 5979;
    
    public function add ($a, $b) {
        return $a + $b;
    }
}

?>
<html>
  <head>
    <title>phpDeeBuk example</title>
  </head>
  
  <body>
<?php
    $debugger = phpDeeBuk::getInstance();
    
    $myObj = new MyClass();
    $myVal = 5979;
    
    // analyze and dump data
    $debugger->analyze($myObj, 'My analyzed object')
             ->dump($myObj, 'My dumped object')
             ->analyze($myVal, 'My analyzed value')
             ->dump($myVal, 'My dumped value');
      
    // do unit tests       
    $debugger->assertTrue(1 == '1')  // ok
             ->assertFalse('2' == 2)  // fails
             ->assertEqual('3', 3)  // ok
             ->assertExact('3', 3)  // fails
             ->assertNotEqual('4', 4)  // fails
             ->assertNotExact('4', 4, 'My 2nd 4 value check')  // ok (with custom caption)
             
    // variables and console
    $debugger['myVar'] = 23979;
    $debugger->writeLine($debugger['myVar']);
    
    // create JavaScript code with HTML <script> tags
    $debugger->renderAndOutput();
?>
  </body>
</html>
```

Have a look at the examples:

## Example

### Analyze objects and values ###

```php
class MyClass {
    public $Var2 = 'TM';
    private $Var1 = 'MK';
    
    const MY_CONST = 5979;
    
    public function add ($a, $b) {
        return $a + $b;
    }
}


$debugger = phpDeeBuk::getInstance();

$debugger->analyze(new MyClass())
         ->analyze(5979)
         ->analyze('TM', 'My string');  // define custom caption
```

### Backtrace

You can output the current [debug_backtrace()](http://php.net/manual/en/function.debug-backtrace.php) in a new tab:

```php
$debugger = phpDeeBuk::getInstance();

$debugger->backtrace();
```

### Console

Output data to a virtual console:

```php
$debugger = phpDeeBuk::getInstance();

// outputs: Hello
//          world!
$debugger->writeLine('Hello')
         ->setForgroundColor('f00')  // set #f00 as text color in CSS format
         ->setBackgroundColor('yellow')
         ->writeFormat('%s', 'world')  // s. sprintf()
         ->resetColors()
         ->write('!');
         
// clear console
$debugger->clr();
```

### Dump objects and values

Dump vars with [var_export()](http://php.net/manual/en/function.var-export.php):

```php
class MyClass {
    public $Var1 = 'TM';
    private $Var2 = 'MK';
}


$debugger = phpDeeBuk::getInstance();

$debugger->dump(new MyClass())
         ->dump(23979)
         ->dump('MK', 'My string');  // define custom caption
```

### Multi instance

```php
$defDbg = phpDeeBuk::getInstance();
$dbg1   = phpDeeBuk::getInstance('My debugger');  // new instance
$dbg2   = phpDeeBuk::getInstance();  // same instance as $defDbg
```

### Test

Do simple unit tests:

```php
class MyClass {
    public $Var1 = 'TM';
    private $Var2 = 'MK';
}


$debugger = phpDeeBuk::getInstance();

$debugger->assertTrue(1 == '1')  // ok
         ->assertFalse('2' == 2)  // fails
         ->assertEqual('3', 3)  // ok
         ->assertExact('3', 3)  // fails
         ->assertNotEqual('4', 4)  // fails
         ->assertNotExact('4', 4, 'My 2nd 4 value check')  // ok (with custom caption)
```

### Variables

Work with variables:

```php
$debugger = phpDeeBuk::getInstance();

$debugger->setVar('TM', 5979);
$var1 = $debugger->getVar('tm');   // var names are NOT case sensitive
                                   // other way: $var1 = $debugger['tm']

// other way: isset($debugger['MK'])
if (!$debugger->issetVar('MK')) {
    $debugger['mk'] = 23979;
}

// ['MK'] = 23979
// ['TM'] = 5979
$vars = $debugger->getVarArray();

// remove 'MK'
$debugger->unsetVar('Mk');
// other way: unset($debugger['Mk'])

$debugger->clearVars();
```
