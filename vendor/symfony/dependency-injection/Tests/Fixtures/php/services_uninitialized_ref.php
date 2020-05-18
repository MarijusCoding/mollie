<?php

namespace _PhpScoper5ea00cc67502b;

use _PhpScoper5ea00cc67502b\Symfony\Component\DependencyInjection\Argument\RewindableGenerator;
use _PhpScoper5ea00cc67502b\Symfony\Component\DependencyInjection\ContainerInterface;
use _PhpScoper5ea00cc67502b\Symfony\Component\DependencyInjection\Container;
use _PhpScoper5ea00cc67502b\Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use _PhpScoper5ea00cc67502b\Symfony\Component\DependencyInjection\Exception\LogicException;
use _PhpScoper5ea00cc67502b\Symfony\Component\DependencyInjection\Exception\RuntimeException;
use _PhpScoper5ea00cc67502b\Symfony\Component\DependencyInjection\ParameterBag\FrozenParameterBag;
use stdClass;
use function class_alias;
use function sprintf;
use function trigger_error;
use const E_USER_DEPRECATED;

/**
 * This class has been auto-generated
 * by the Symfony Dependency Injection Component.
 *
 * @final since Symfony 3.3
 */
class Symfony_DI_PhpDumper_Test_Uninitialized_Reference extends Container
{
    private $parameters = [];
    private $targetDirs = [];
    public function __construct()
    {
        $this->services = [];
        $this->methodMap = ['bar' => 'getBarService', 'baz' => 'getBazService', 'foo1' => 'getFoo1Service', 'foo3' => 'getFoo3Service'];
        $this->privates = ['foo3' => true];
        $this->aliases = [];
    }
    public function getRemovedIds()
    {
        return ['_PhpScoper5ea00cc67502b\\Psr\\Container\\ContainerInterface' => true, '_PhpScoper5ea00cc67502b\\Symfony\\Component\\DependencyInjection\\ContainerInterface' => true, 'foo2' => true, 'foo3' => true];
    }
    public function compile()
    {
        throw new LogicException('You cannot compile a dumped container that was already compiled.');
    }
    public function isCompiled()
    {
        return true;
    }
    public function isFrozen()
    {
        @trigger_error(sprintf('The %s() method is deprecated since Symfony 3.3 and will be removed in 4.0. Use the isCompiled() method instead.', __METHOD__), E_USER_DEPRECATED);
        return true;
    }
    /**
     * Gets the public 'bar' shared service.
     *
     * @return stdClass
     */
    protected function getBarService()
    {
        $this->services['bar'] = $instance = new stdClass();
        $instance->foo1 = ${($_ = isset($this->services['foo1']) ? $this->services['foo1'] : null) && false ?: '_'};
        $instance->foo2 = null;
        $instance->foo3 = ${($_ = isset($this->services['foo3']) ? $this->services['foo3'] : null) && false ?: '_'};
        $instance->closures = [0 => function () {
            return ${($_ = isset($this->services['foo1']) ? $this->services['foo1'] : null) && false ?: '_'};
        }, 1 => function () {
            return null;
        }, 2 => function () {
            return ${($_ = isset($this->services['foo3']) ? $this->services['foo3'] : null) && false ?: '_'};
        }];
        $instance->iter = new RewindableGenerator(function () {
            if (isset($this->services['foo1'])) {
                (yield 'foo1' => ${($_ = isset($this->services['foo1']) ? $this->services['foo1'] : null) && false ?: '_'});
            }
            if (false) {
                (yield 'foo2' => null);
            }
            if (isset($this->services['foo3'])) {
                (yield 'foo3' => ${($_ = isset($this->services['foo3']) ? $this->services['foo3'] : null) && false ?: '_'});
            }
        }, function () {
            return 0 + (int) isset($this->services['foo1']) + (int)false + (int) isset($this->services['foo3']);
        });
        return $instance;
    }
    /**
     * Gets the public 'baz' shared service.
     *
     * @return stdClass
     */
    protected function getBazService()
    {
        $this->services['baz'] = $instance = new stdClass();
        $instance->foo3 = ${($_ = isset($this->services['foo3']) ? $this->services['foo3'] : ($this->services['foo3'] = new stdClass())) && false ?: '_'};
        return $instance;
    }
    /**
     * Gets the public 'foo1' shared service.
     *
     * @return stdClass
     */
    protected function getFoo1Service()
    {
        return $this->services['foo1'] = new stdClass();
    }
    /**
     * Gets the private 'foo3' shared service.
     *
     * @return stdClass
     */
    protected function getFoo3Service()
    {
        return $this->services['foo3'] = new stdClass();
    }
}
/**
 * This class has been auto-generated
 * by the Symfony Dependency Injection Component.
 *
 * @final since Symfony 3.3
 */
class_alias('_PhpScoper5ea00cc67502b\\Symfony_DI_PhpDumper_Test_Uninitialized_Reference', 'Symfony_DI_PhpDumper_Test_Uninitialized_Reference', false);
