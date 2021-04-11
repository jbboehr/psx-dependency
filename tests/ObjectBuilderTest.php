<?php
/*
 * PSX is a open source PHP framework to develop RESTful APIs.
 * For the current version and informations visit <http://phpsx.org>
 *
 * Copyright 2010-2020 Christoph Kappestein <christoph.kappestein@gmail.com>
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace PSX\Dependency\Tests;

use InvalidArgumentException;
use ReflectionException;
use RuntimeException;
use Doctrine\Common\Annotations\SimpleAnnotationReader;
use Doctrine\Common\Cache\ArrayCache;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use PSX\Cache\Pool;
use PSX\Dependency\Container;
use PSX\Dependency\ObjectBuilder;

/**
 * ObjectBuilderTest
 *
 * @author  Christoph Kappestein <christoph.kappestein@gmail.com>
 * @license http://www.apache.org/licenses/LICENSE-2.0
 * @link    http://phpsx.org
 */
class ObjectBuilderTest extends TestCase
{
    public function testGetObject()
    {
        $container = new Container();
        $container->set('foo', new \stdClass());
        $container->set('foo_bar', new \DateTime());

        $builder = $this->newObjectBuilder($container);
        $object  = $builder->getObject(Playground\FooService::class);

        $this->assertInstanceof(Playground\FooService::class, $object);
        $this->assertInstanceof(\stdClass::class, $object->getFoo());
        $this->assertInstanceof(\DateTime::class, $object->getBar());
        $this->assertNull($object->getProperty());
    }

    public function testGetObjectInjectUnknownService()
    {
        $container = new Container();
        
        $builder = $this->newObjectBuilder($container);
        
        $this->expectException(RuntimeException::class);
        $builder->getObject(Playground\FooService::class);
    }

    public function testGetObjectUnknownClass()
    {
        $container = new Container();
        
        $builder = $this->newObjectBuilder($container);

        $this->expectException(ReflectionException::class);
        $builder->getObject('PSX\Framework\Tests\Dependency\BarService');
    }

    public function testGetObjectInstanceOf()
    {
        $container = new Container();
        $container->set('foo', new \stdClass());
        $container->set('foo_bar', new \stdClass());

        $builder = $this->newObjectBuilder($container);
        $object  = $builder->getObject(Playground\FooService::class, array(), Playground\FooService::class);

        $this->assertInstanceof(Playground\FooService::class, $object);
    }

    public function testGetObjectInstanceOfInvalid()
    {
        $container = new Container();
        $container->set('foo', new \stdClass());
        $container->set('foo_bar', new \stdClass());

        $builder = $this->newObjectBuilder($container);

        $this->expectException(InvalidArgumentException::class);
        $builder->getObject(Playground\FooService::class, array(), 'PSX\Framework\Tests\Dependency\BarService');
    }

    public function testGetObjectConstructorArguments()
    {
        $container = new Container();
        $container->set('foo', new \stdClass());
        $container->set('foo_bar', new \stdClass());

        $builder = $this->newObjectBuilder($container);
        $object  = $builder->getObject(Playground\FooService::class, array('foo'), Playground\FooService::class);

        $this->assertInstanceof(Playground\FooService::class, $object);
        $this->assertEquals('foo', $object->getProperty());
    }

    public function testGetObjectWithoutConstructor()
    {
        $container = new Container();
        
        $builder  = $this->newObjectBuilder($container);
        $stdClass = $builder->getObject(\stdClass::class);

        $this->assertInstanceof(\stdClass::class, $stdClass);
    }

    public function testGetObjectCache()
    {
        $container = new Container();
        $container->set('foo', new \stdClass());
        $container->set('foo_bar', new \stdClass());

        $cache   = new Pool(new ArrayCache());
        $builder = $this->newObjectBuilder($container, $cache, false);
        $object  = $builder->getObject(Playground\FooService::class);

        $item = $cache->getItem(ObjectBuilder::class . Playground\FooService::class);

        $this->assertInstanceof(Playground\FooService::class, $object);
        $this->assertTrue($item->isHit());
        $this->assertEquals(['foo' => 'foo', 'bar' => 'foo_bar'], $item->get());

        $item = $cache->getItem(ObjectBuilder::class . Playground\FooService::class);

        $object = $builder->getObject(Playground\FooService::class);

        $this->assertInstanceof(Playground\FooService::class, $object);
        $this->assertTrue($item->isHit());
        $this->assertEquals(['foo' => 'foo', 'bar' => 'foo_bar'], $item->get());
    }

    private function newObjectBuilder(ContainerInterface $container, $cache = null, $debug = true)
    {
        $reader = new SimpleAnnotationReader();
        $reader->addNamespace('PSX\Dependency\Annotation');

        if ($cache === null) {
            $cache = new Pool(new ArrayCache());
        }

        return new ObjectBuilder($container, $reader, $cache, $debug);
    }
}
