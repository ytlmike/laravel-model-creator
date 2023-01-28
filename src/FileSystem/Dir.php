<?php

namespace ModelCreator\FileSystem;

use Closure;

class Dir
{
    const PATH_CURRENT_DIR = '.';
    const PATH_PARENT_DIR = '..';

    public static function walk(string $path, bool $deep, Closure $func)
    {
        $handler = opendir($path);
        while (($filename = readdir($handler)) !== false) {
            if ($filename == self::PATH_CURRENT_DIR || $filename == self::PATH_PARENT_DIR) { //文件夹文件名字为'.'和'..'，不要对他们进行操作
                continue;
            }
            $fullName = $path . '/' . $filename;
            if (is_dir($fullName)) {// 如果读取的某个对象是文件夹，则递归
                if ($deep) {
                    self::walk($fullName, $deep, $func);
                }
            } else {
                $func($fullName);
            }
        }
        closedir($handler);
    }
}
