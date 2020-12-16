<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace MolliePrefix\Symfony\Component\Cache\Tests\Simple;

use MolliePrefix\Symfony\Component\Cache\Simple\PdoCache;
use MolliePrefix\Symfony\Component\Cache\Tests\Traits\PdoPruneableTrait;
/**
 * @group time-sensitive
 */
class PdoCacheTest extends \MolliePrefix\Symfony\Component\Cache\Tests\Simple\CacheTestCase
{
    use PdoPruneableTrait;
    protected static $dbFile;
    public static function setUpBeforeClass()
    {
        if (!\extension_loaded('pdo_sqlite')) {
            self::markTestSkipped('Extension pdo_sqlite required.');
        }
        self::$dbFile = \tempnam(\sys_get_temp_dir(), 'sf_sqlite_cache');
        $pool = new \MolliePrefix\Symfony\Component\Cache\Simple\PdoCache('sqlite:' . self::$dbFile);
        $pool->createTable();
    }
    public static function tearDownAfterClass()
    {
        @\unlink(self::$dbFile);
    }
    public function createSimpleCache($defaultLifetime = 0)
    {
        return new \MolliePrefix\Symfony\Component\Cache\Simple\PdoCache('sqlite:' . self::$dbFile, 'ns', $defaultLifetime);
    }
}