<?php

namespace MyNamespace;

?>
<html>
<body>
<?php

require_once "./phpDeeBuk.php";

class MyClass {
    public $C = 12;
    public $B;
    private $_a;

    const ABC = 'TM';

    public function a() {

    }

    private function b() {

    }

    public function c(\phpDeeBuk $dbg) {
        $dbg->backtrace();
    }
}

function myFunc(\phpDeeBuk $dbg) {
    $dbg->backtrace();
}

$dbg = \phpDeeBuk::getInstance();

$a = 1000;

$o = new MyClass();
$o->B = 5979;

$dbg->analyze($a, 'testValue')
    ->analyze($o)
    ->dump($o);

$dbg->setVar(' B  ', 13);
$dbg->setVar('a', 12);

$i1 = $dbg->issetVar('A');
$i2 = $dbg->issetVar('c');

$dbg->writeFormat('%s :: %s', $dbg->issetVar('A'), $dbg->issetVar('c') );
$dbg->dump($dbg->getVarArray());

$dbg->backtrace();
$o->c($dbg);
myFunc($dbg);

$dbg->assertFalse(false);
$dbg->assertTrue(1 == 2, 'My test');

$dbg->renderAndOutput();


?>
</body>
</html>