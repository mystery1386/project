<?php
/**
 * Tools fuer das Filesystem. Achtung hier kann es sein das es unter
 * Windows zu schwierigkeiten kommt.
 *
 * @created 30.11.2012 16:25:06
 * @author mregner
 * @version $Id$
 */
require_once('fblib/core/io/logger/Logger.php');

class FileSystemUtil
{
    const TYPE_FILE = 'file';
    const TYPE_DIRECTORY = 'directory';

    /**
     * @param string $dirname
     * @param string $pattern
     * @param string $type
     * @param integer $maxdepth
     * @throws Exception
     * @return array
     * @author mregner
     */
    public static function scan($dirname, $pattern = '', $type = '', $maxdepth = 0)
    {
        $results = array();
        $dirname = preg_replace("~/\$~", "", $dirname);
        if (file_exists($dirname)) {
            $dh = opendir($dirname);
            if (is_resource($dh)) {
                while (false !== ($file = readdir($dh))) {
                    if ($file[0] == '.') continue;
                    if (is_dir($dirname . '/' . $file)) {
                        if ($maxdepth != 1) {
                            $subResults = self::scan($dirname . '/' . $file, $pattern, $type, $maxdepth - 1);
                        } else {
                            $subResults = array();
                        }
                        if (is_array($subResults) && count($subResults) > 0) {
                            $results = array_merge($results, $subResults);
                        } else if ((empty($pattern) || preg_match("~{$pattern}~i", $file)) && (empty($type) || $type == self::TYPE_DIRECTORY)) {
                            $results[] = $dirname . '/' . $file;
                        }
                    } else if ((empty($pattern) || preg_match("~{$pattern}~i", $file)) && (empty($type) || $type == self::TYPE_FILE)) {
                        $results[] = preg_replace("~/+~", "/", ($dirname . '/' . $file));
                    }
                }
                closedir($dh);
            }
        }
        return $results;
    }

    /**
     * Ermittelt den Platz zu allen mounts.
     *
     * @return array
     */
    public static function df()
    {
        $df = array();
        exec("df -x tmpfs", $df);
        $result = array();
        foreach ($df as $line => $data) {
            if ($line > 0) {
                $splitted = preg_split("~\s+~", $data);
                $set = array(
                    'filesystem' => $splitted[0],
                    '1k_blocks' => $splitted[1],
                    'used' => $splitted[2],
                    'available' => $splitted[3],
                    'use%' => preg_replace("~[^0-9]+~", "", $splitted[4]),
                    'mounted_on' => $splitted[5]
                );
                $result[] = $set;
            }
        }
        return $result;
    }

    /**
     * @param $dir
     * @param bool $directories
     * @param bool $files
     * @param bool $filepattern
     * @return array
     * @throws FileSystemUtilCheckDirectoryIsWorkingDirectoryException
     * @throws FileSystemUtilCheckDirectoryIsBaseDirectoryException
     * @throws FileSystemUtilCheckDirectoryIsNotADirectoryException
     * @throws FileSystemUtilCheckDirectoryIsLinkException
     * @throws FileSystemUtilCheckDirectoryIsForbiddenException
     * @throws FileSystemUtilCheckDirectoryIsRootDirectoryException
     */
    public static function checkDirectory($dir, &$directories = false, &$files = false, $filepattern = false)
    {
        $forbiddenDirs = "|bin|boot|etc|dev|lib|lib64|opt|proc|root|sys|usr|..|";
        $ignoreDirs = "|.svn|";
        if ($directories === false) {
            $directories = array();
        }
        if ($files === false) {
            $files = array();
        }
        $dir = str_replace("\\", "/", $dir);
        $realPath = realpath($dir);
        $realPath = str_replace("\\", "/", $realPath);
        if (is_dir($realPath)) {
            $pathElements = explode("/", preg_replace("~^/|/$~", "", $dir));
            $realPathElements = explode("/", preg_replace("~^/|/$~", "", $realPath));
            $forbiddenPathElements = explode("|", $forbiddenDirs);
            $ignorePathElements = explode("|", $ignoreDirs);
            $currentWorkingDir = getcwd();
            if ($realPath === '/') {
                throw new FileSystemUtilCheckDirectoryIsRootDirectoryException($realPath);
            } else if ($realPath === $currentWorkingDir) {
                throw new FileSystemUtilCheckDirectoryIsWorkingDirectoryException($realPath);
            } else if (count(($foundForbiddenElements = array_intersect($forbiddenPathElements, $pathElements))) > 0) {
                throw new FileSystemUtilCheckDirectoryIsForbiddenException($realPath);
            } else if (count(($foundForbiddenElements = array_intersect($forbiddenPathElements, $realPathElements))) > 0) {
                throw new FileSystemUtilCheckDirectoryIsForbiddenException($realPath);
            } else if (count($realPathElements) === 1) {
                throw new FileSystemUtilCheckDirectoryIsBaseDirectoryException($realPath);
            } else if (count(($foundIgnoreElements = array_intersect($ignorePathElements, $pathElements))) > 0) {
                // Just Ignore, as the name says
            } else {
                $dirHandle = dir($realPath);
                while (($entry = $dirHandle->read()) !== false) {
                    if ($entry !== '.' && $entry !== '..') {
                        $fullEntry = $realPath . "/" . $entry;
                        if (is_link($fullEntry)) {
                            throw new FileSystemUtilCheckDirectoryIsLinkException($fullEntry);
                        } else if (is_file($fullEntry) && ($filepattern === false OR preg_match($filepattern, basename($fullEntry)))) {
                            $files[] = $fullEntry;
                        } else if (is_dir($fullEntry)) {
                            $directories[] = $fullEntry;
                            self::checkDirectory($fullEntry, $directories, $files);
                        }
                    }
                }
            }
        } else {
            throw new FileSystemUtilCheckDirectoryIsNotADirectoryException($realPath);
        }
        $directories[] = $dir;
        $checkResult = array('files' => $files, 'directories' => $directories,);
        return $checkResult;
    }

    /**
     * @param $dir
     * @return bool
     */
    public static function removeDir($dir)
    {
        try {
            $checkResult = self::checkDirectory($dir);
            //First Delete all files.
            self::removeAllFilesInDir($dir);
            foreach ($checkResult['directories'] as $dirName) {
                if (file_exists($dirName)) {
                    rmdir($dirName);
                }
            }
            return true;
        } catch (Exception $exception) {
            Logger::fatal($exception);
            Logger::fatal("\$_SERVER -> ");
            Logger::fatal($_SERVER);
            Logger::fatal("\$_SESSION -> ");
            Logger::fatal($_SESSION);
        }
        return false;
    }

    /**
     * @param $dir
     * @return bool
     */
    public function removeAllFilesInDir($dir)
    {
        try {
            $checkResult = self::checkDirectory($dir);
            foreach ($checkResult['files'] as $fileName) {
                unlink($fileName);
            }
            return true;
        } catch (Exception $exception) {
            Logger::fatal($exception);
            Logger::fatal("\$_SERVER -> ");
            Logger::fatal($_SERVER);
            Logger::fatal("\$_SESSION -> ");
            Logger::fatal($_SESSION);
        }
        return false;
    }
}