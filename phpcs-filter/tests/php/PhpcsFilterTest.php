<?php
/**
 * Tests for PhpcsFilter.php.
 */

namespace Anomiex\Tests;

use Anomiex\PhpcsFilter;
use PHPUnit\Framework\TestCase;
use PHP_CodeSniffer\Config;
use PHP_CodeSniffer\Exceptions\DeepExitException;
use PHP_CodeSniffer\Files\FileList;
use PHP_CodeSniffer\Files\LocalFile;
use PHP_CodeSniffer\Reporter;
use PHP_CodeSniffer\Ruleset;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Tests for PhpcsFilter.php.
 */
class PhpcsFilterTest extends TestCase {
    use \Yoast\PHPUnitPolyfills\Polyfills\AssertIsType;

    /**
     * Old CWD to restore after the test.
     *
     * @var string
     */
    private $oldcwd;

    /**
     * Set up.
     *
     * @before
     */
    public function set_up() {
        $this->oldcwd = getcwd();
        Config::setConfigData( 'anomiex-filter-basedir', null, true );
    }

    /**
     * Tear down.
     *
     * @after
     */
    public function tear_down() {
        chdir( $this->oldcwd );
    }

    public function testPhpcsignore() {
        $makeExpect = function ( $base ) {
            return array(
                "$base/a/file.php",
                "$base/b/anotherfile.php",
                "$base/b/file.php",
                "$base/c1/2.php",
                "$base/c1/3.php",
                "$base/c1/4.php",
                "$base/c1/c2/3.php",
                "$base/c1/c2/4.php",
                "$base/c1/c2/c3/4.php",
                "$base/file.php",
            );
        };

        chdir( __DIR__ . '/../../tests/fixtures/phpcsignore' );
        $config = new Config();

        // When run from the base of the repo, it reads tests/fixtures/.phpcsignore and so ignores everything.
        chdir( __DIR__ . '/../../' );
        $di = new RecursiveDirectoryIterator( 'tests/fixtures/phpcsignore', RecursiveDirectoryIterator::SKIP_DOTS | RecursiveDirectoryIterator::FOLLOW_SYMLINKS );
        $filter = new RecursiveIteratorIterator( new PhpcsFilter( $di, 'tests/fixtures/phpcsignore', $config, new Ruleset( $config ) ) );
        $this->assertSame( array(), array_keys( iterator_to_array( $filter ) ) );

        // When run from the fixture dir, it reads only .phpcsignore in that dir and below.
        chdir( __DIR__ . '/../../tests/fixtures/phpcsignore' );
        $di = new RecursiveDirectoryIterator( '.', RecursiveDirectoryIterator::SKIP_DOTS | RecursiveDirectoryIterator::FOLLOW_SYMLINKS );
        $filter = new RecursiveIteratorIterator( new PhpcsFilter( $di, '.', $config, new Ruleset( $config ) ) );
        $files = array();
        foreach ( $filter as $file ) {
            $this->assertInstanceOf( LocalFile::class, $file );
            $files[] = $file->getFilename();
        }
        sort( $files );
        $this->assertSame( $makeExpect( '.' ), $files );

        // Set the base dir config and it uses that.
        chdir( __DIR__ . '/../../' );
        Config::setConfigData( 'anomiex-filter-basedir', 'tests/fixtures/phpcsignore', true );
        $di = new RecursiveDirectoryIterator( 'tests/fixtures/phpcsignore', RecursiveDirectoryIterator::SKIP_DOTS | RecursiveDirectoryIterator::FOLLOW_SYMLINKS );
        $filter = new RecursiveIteratorIterator( new PhpcsFilter( $di, 'tests/fixtures/phpcsignore', $config, new Ruleset( $config ) ) );
        $files = array();
        foreach ( $filter as $file ) {
            $this->assertInstanceOf( LocalFile::class, $file );
            $files[] = $file->getFilename();
        }
        sort( $files );
        $this->assertSame( $makeExpect( 'tests/fixtures/phpcsignore' ), $files );
    }

    /**
     * @dataProvider provideRun
     * @param string $path Fixture path.
     */
    public function testRun( $path ) {
        $path = realpath( $path );
        chdir( $path );
        $l = strlen( $path ) + 1;

        $expect = json_decode( file_get_contents( 'expect.json' ), true );
        $this->assertIsArray( $expect, 'expect.json contains a JSON object' );

        $config = new Config();
        $config->filter = __DIR__ . '/../../src/PhpcsFilter.php';
        $config->files = array( $path );
        $ruleset = new Ruleset( $config );
        $files = new FileList( $config, $ruleset );

        $actual = array();
        foreach ( $files as $file ) {
            if ( $file->ignored ) {
                continue;
            }
            $file->reloadContent();
            $file->process();

            $data = array();
            foreach ( $file->getErrors() as $line => $cols ) {
                foreach ( $cols as $msgs ) {
                    foreach ( $msgs as $msg ) {
                        $data[$line][] = $msg['source'];
                    }
                }
                sort( $data[$line] );
            }
            foreach ( $file->getWarnings() as $line => $msgs ) {
                foreach ( $cols as $msgs ) {
                    foreach ( $msgs as $msg ) {
                        $data[$line][] = $msg['source'];
                    }
                }
                sort( $data[$line] );
            }
            ksort( $data );

            $name = substr( $file->getFilename(), $l );
            $actua[$name] = array();
            foreach ( $data as $line => $codes ) {
                foreach ( $codes as $code ) {
                    $actual[$name][] = "Line $line: $code";
                }
            }

            $file->cleanUp();
        }

        //$this->assertEquals( $expect, $actual );
    }

    public function provideRun() {
        return array(
            'General tests' => array( __DIR__ . '/../../tests/fixtures/perdir' ),
            'Custom per-directory file name' => array( __DIR__ . '/../../tests/fixtures/perdir-custom' ),
        );
    }

}
