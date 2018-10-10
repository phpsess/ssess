<?php

declare(strict_types=1);

use Ssess\Ssess;
use Ssess\CryptProvider\OpenSSLCryptProvider;
use Ssess\Exception\UseStrictModeDisabledException;
use Ssess\Exception\UseCookiesDisabledException;
use Ssess\Exception\UseOnlyCookiesDisabledException;
use Ssess\Exception\UseTransSidEnabledException;

use PHPUnit\Framework\TestCase;
use org\bovigo\vfs\vfsStream;

/**
 * @runTestsInSeparateProcesses
 */
final class SsessTest extends TestCase
{

    public function setUp()
    {
        $path = vfsStream::setup()->url();

        $class_name = self::class;

        $test_name = $this->getName();

        $session_path = "$path/$class_name-$test_name";

        ini_set('session.save_path', $session_path);
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_cookies', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');

        parent::setUp();
    }

    public function testSessionFixation()
    {
        $arbitrary_session_id = $this->setArbitrarySessionId();

        $this->initSecureSession();

        $current_session_id = session_id();

        $this->assertNotEquals($current_session_id, $arbitrary_session_id);
    }

    public function testSessionFixationWhenSidExists()
    {
        $this->initSecureSession();

        $session_id = session_id();

        $_SESSION['password'] = 'password';

        session_write_close();

        $this->setArbitrarySessionId($session_id);

        $this->initSecureSession();

        $current_session_id = session_id();

        $this->assertEquals($session_id, $current_session_id);
    }

    public function testWarnStrictModeDisabled()
    {
        ini_set('session.use_strict_mode', '0');

        $this->expectException(UseStrictModeDisabledException::class);

        $crypt_provider = new OpenSSLCryptProvider('testKey');

        new Ssess($crypt_provider);
    }

    public function testWarnUseCookiesDisabled()
    {
        ini_set('session.use_cookies', '0');

        $this->expectException(UseCookiesDisabledException::class);

        $crypt_provider = new OpenSSLCryptProvider('testKey');

        new Ssess($crypt_provider);
    }

    public function testWarnUseOnlyCookiesDisabled()
    {
        ini_set('session.use_only_cookies', '0');

        $this->expectException(UseOnlyCookiesDisabledException::class);

        $crypt_provider = new OpenSSLCryptProvider('testKey');

        new Ssess($crypt_provider);
    }

    public function testWarnUseTransSidEnabled()
    {
        ini_set('session.use_trans_sid', '1');

        $this->expectException(UseTransSidEnabledException::class);

        $crypt_provider = new OpenSSLCryptProvider('testKey');

        new Ssess($crypt_provider);
    }

    public function testDisabledWarnInsecureSettings()
    {
        ini_set('session.use_strict_mode', '0');
        ini_set('session.use_cookies', '0');
        ini_set('session.use_only_cookies', '0');
        ini_set('session.use_trans_sid', '1');

        Ssess::$warnInsecureSettings = false;

        $crypt_provider = new OpenSSLCryptProvider('testKey');

        try {
            new Ssess($crypt_provider);
            $did_not_throw_errors = true;
        } catch(Exception $e) {
            $did_not_throw_errors = false;
        }

        $this->assertTrue($did_not_throw_errors);
    }

    public function testIgnoreSessionFixation()
    {
        Ssess::$warnInsecureSettings = false;

        ini_set('session.use_strict_mode', '0');

        $arbitrary_session_id = $this->setArbitrarySessionId();

        $this->initSecureSession();

        $current_session_id = session_id();

        $this->assertEquals($arbitrary_session_id, $current_session_id);
    }

    public function testCanWriteReopenAndRead()
    {
        $this->initSecureSession();

        $_SESSION['password'] = 'password';

        session_write_close();

        $this->initSecureSession();

        $this->assertEquals($_SESSION['password'], 'password');
    }

    public function testCantReadWithWrongAppKey()
    {
        $this->initSecureSession('original-key');

        $_SESSION['password'] = 'password';

        session_write_close();

        $this->initSecureSession('wrong-key');

        $this->assertArrayNotHasKey('password', $_SESSION);
    }

    public function testDestroy()
    {
        $crypt_provider = $this->initSecureSession();

        $session_id = session_id();

        $_SESSION['password'] = 'test';

        session_write_close();

        $destroyed = $crypt_provider->destroy($session_id);

        $this->assertTrue($destroyed);

        $crypt_provider = $this->initSecureSession();

        $data = $crypt_provider->read($session_id);

        $this->assertEquals($data, '');
    }

    public function testDestroyInexistentSessionId()
    {
        $crypt_provider = $this->initSecureSession('aSessionId');

        $_SESSION['password'] = 'test';

        session_write_close();

        $destroyed = $crypt_provider->destroy('anotherSessionId');

        $this->assertFalse($destroyed);
    }

    public function testGarbageCollector()
    {
        $crypt_provider = $this->initSecureSession();

        $session_id = session_id();

        $_SESSION['password'] = 'test';

        session_write_close();

        sleep(2);

        $crypt_provider->gc(1);

        $new_crypt_provider = $this->initSecureSession();

        $data = $new_crypt_provider->read($session_id);

        $this->assertEquals('', $data);
    }

    private function setArbitrarySessionId($arbitrary_session_id = '')
    {
        if (!$arbitrary_session_id) {
            $arbitrary_session_id = session_create_id();
        }

        $session_name = session_name();
        $_COOKIE[$session_name] = $arbitrary_session_id;

        return $arbitrary_session_id;
    }

    private function initSecureSession($key = 'testKey')
    {
        $crypt_provider = new OpenSSLCryptProvider($key);

        $ssess = new Ssess($crypt_provider);

        session_set_save_handler($ssess);

        session_start();

        return $ssess;
    }
}