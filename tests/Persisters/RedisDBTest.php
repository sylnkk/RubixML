<?php

namespace Rubix\ML\Tests\Persisters;

use Rubix\ML\Persistable;
use Rubix\ML\Persisters\RedisDB;
use Rubix\ML\Persisters\Persister;
use Rubix\ML\Classifiers\DummyClassifier;
use PHPUnit\Framework\TestCase;

class RedisDBTest extends TestCase
{
    protected $persistable;

    protected $persister;

    public function setUp()
    {
        $this->persistable = new DummyClassifier();

        $this->persister = $this->createMock(RedisDB::class);
    }

    public function test_build_persister()
    {
        $this->assertInstanceOf(RedisDB::class, $this->persister);
        $this->assertInstanceOf(Persister::class, $this->persister);
    }

    // public function test_save_and_load()
    // {
    //     $this->persister->save($this->persistable);
    //
    //     $model = $this->persister->load();
    //
    //     $this->assertInstanceOf(Persistable::class, $model);
    // }
}
