<?php

// autoload_real.php @generated by Composer

class ComposerAutoloaderInitb0e4e69e4a12f7cbb062c33891b6cd30
{
    private static $loader;

    public static function loadClassLoader($class)
    {
        if ('Composer\Autoload\ClassLoader' === $class) {
            require __DIR__ . '/ClassLoader.php';
        }
    }

    /**
     * @return \Composer\Autoload\ClassLoader
     */
    public static function getLoader()
    {
        if (null !== self::$loader) {
            return self::$loader;
        }

        require __DIR__ . '/platform_check.php';

        spl_autoload_register(array('ComposerAutoloaderInitb0e4e69e4a12f7cbb062c33891b6cd30', 'loadClassLoader'), true, true);
        self::$loader = $loader = new \Composer\Autoload\ClassLoader(\dirname(__DIR__));
        spl_autoload_unregister(array('ComposerAutoloaderInitb0e4e69e4a12f7cbb062c33891b6cd30', 'loadClassLoader'));

        require __DIR__ . '/autoload_static.php';
        call_user_func(\Composer\Autoload\ComposerStaticInitb0e4e69e4a12f7cbb062c33891b6cd30::getInitializer($loader));

        $loader->register(true);

        return $loader;
    }
}
