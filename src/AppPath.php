<?php
/**
 * NETopes AJAX path class file
 * The NETopes AJAX path class contains helper methods for application paths.
 *
 * @package    NETopes\Ajax
 * @author     George Benjamin-Schonberger
 * @copyright  Copyright (c) 2013 - 2019 AdeoTEK Software SRL
 * @license    LICENSE.md
 * @version    1.2.0.0
 * @filesource
 */
namespace NETopes\Ajax;
/**
 * Class AppPath
 *
 * @package  NETopes\Ajax
 */
class AppPath {
    /**
     * Get NETopes AJAX path
     *
     * @return string
     */
    public static function GetPath(): string {
        return __DIR__;
    }//END public static function GetPath

    /**
     * Get NETopes AJAX boot file
     *
     * @return string
     */
    public static function GetBootFile(): string {
        return __DIR__.'/boot.php';
    }//END public static function GetBootFile
}//END class AppPath