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
}

$dbg = phpDeeBuk::getInstance();

$a = 1000;

$o = new MyClass();
$o->B = 5979;

$dbg->writeLine('Hello')
    ->write('world!')
    ->analyze($a)
    ->analyze(new MyClass());

$dbg->renderAndOutput();



?>
</body>
</html>