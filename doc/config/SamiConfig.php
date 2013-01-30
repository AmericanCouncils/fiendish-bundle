<?php

use Sami\Sami;
use Sami\Parser\Filter\SymfonyFilter;
use Sami\Reflection\ClassReflection;
use Sami\Reflection\MethodReflection;
use Symfony\Component\Finder\Finder;

$iterator = Finder::create()
    ->files()
    ->name("*.php")
    ->exclude('Tests')
    ->exclude('vendor')
    ->exclude('doc')
    ->in(__DIR__ . "/../../");

class DescriptionFilter extends SymfonyFilter
{
    public function acceptClass(ClassReflection $class)
    {
        print("POINT Y\n");
        return parent::acceptClass($class) &&
            strlen($class->getDocBlock()->getShortDesc()) > 0;
    }

    public function acceptMethod(MethodReflection $method)
    {
        print("POINT X\n");
        return parent::acceptMethod($method) &&
            strlen($method->getDocBlock()->getShortDesc()) > 0;
    }
}

return new Sami($iterator, [
    'build_dir' => __DIR__ . "/../build",
    'cache_dir' => __DIR__ . "/../cache",
    'filter' => new SymfonyFilter()
]);
