<?php
/**
 * Copyright (C) 2013 rym <rym.the.great@gmail.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @license http://www.gnu.org/licenses/ GPLv3
 */
namespace Rym;

use Zend\Cache\StorageFactory;
use Zend\Cache\Storage;
use Zend\Http;
use Stylecow;

/**
 * Class GetSinglePage
 *
 * Split a HTML page from provided URL into HTML/JS/CSS.
 *
 * @package Rym
 */
class GetSinglePage
{

    /**
     * @var string Path to cache directory.
     */
    protected $path;

    /**
     * @var Storage\StorageInterface cache.
     */
    protected $cache;

    public function __construct($path = '../cache')
    {
        if (!is_dir($path) && !mkdir($path, 0755, true)) {
            throw new \Exception('Cache path doesn\'t exist');
        }
        $this->path = $path;
        $this->cache = StorageFactory::factory(
            array(
                'adapter' => array(
                    'name'    => 'filesystem',
                    'options' => array(
                        'ttl' => 3600,
                        'cache_dir' => $path,
                        'dir_level' => 1,
                        'dir_permission' => 0755,
                        'file_permission' => 0644,
                    ),
                ),
                'plugins' => array(
                    'exception_handler' => array('throw_exceptions' => false),
                    'serializer',
                ),
            )
        );
    }

    /**
     * Load content from $url.
     * @param string $url
     *
     * @return string
     */
    public function getHtml($url)
    {
        $result = '';
        $client = new Http\Client($url);
        $response = $client->send();
        if ($response->getStatusCode() == Http\Response::STATUS_CODE_200) {
            $result = $response->getBody();
        }
        return $result;
    }

    /**
     * Get separate html & css from url.
     * @param string $url
     * @param string $prefix
     *
     * @return array
     */
    public function get($url, $prefix = '')
    {
        $cacheKey = md5($url . $prefix);
        $success = false;
        $result = $this->cache->getItem($cacheKey, $success);
        if (!$success) {
            $html = $this->getHtml($url);
            $src = array();
            $res = array();
            //get included scripts
            preg_match_all('|<script[^>]*(src)+[^>]*>(</script>)?|im', $html, $res);
            foreach($res[0] as $script) {
                $file = array();
                if (preg_match('|src=[\'"]{1}([^"\']+)[\'"]{1}|', $script, $file)) {
                    array_push($src, $this->getValidUrl($file[1], $url, true));
                }
            }
            $html = str_replace($res[0], '', $html);
            //remove inline scripts
            preg_match_all('|<script[^>]*>.*</script>|Uis', $html, $res);
            $html = str_replace($res[0], '', $html);
            //get included css
            preg_match_all('|<link[^>]*(stylesheet)+[^>]*>|', $html, $res);
            $totalCSS = '';
            foreach($res[0] as $css) {
                $file = array();
                if (preg_match('|href=[\'"]{1}([^"\']+)[\'"]{1}|', $css, $file)) {
                    $cssUrl = $this->getValidUrl($file[1], $url, true);
                    $css = $this->loadCss($cssUrl);
                    $css->applyPlugins(array(
                            'BaseUrl' => dirname($cssUrl) . '/',
                            'AddPrefix' => $prefix
                        ));
                    $totalCSS .= $css->__toString();
                }
            }
            $html = str_replace($res[0], '', $html);
            $res = array();
            //get inline styles
            preg_match_all('|<style[^>]*>([^>]*)</style>|', $html, $res);
            foreach ($res[1] as $cssString) {
                $css = $this->getCss($cssString, $url);
                $css->applyPlugins(array(
                        'BaseUrl' => dirname($url) . '/',
                        'AddPrefix' => $prefix
                    ));
                $totalCSS .= $css->__toString();
            }
            //remove useless tags, if possible.
            $html = str_replace($res[0], '', $html);
            if (preg_match('|<body[^>]*>(.*)</body>|is', $html, $res)) {
                $html = $res[1];
            }
            $totalCSS = $this->compressCss($totalCSS);
            $html = $this->compressHtml($html);
            $result = array(
                'html' => $html,
                'css' => $totalCSS,
                'src' => $src
            );
            $this->cache->setItem($cacheKey, $result);
        }
        return $result;
    }

    /**
     * Parse CSS from file/url.
     * @param string $file
     * @return Stylecow\Css
     */
    protected function loadCss($file)
    {
        $cssString = $this->getHtml($file);
        $css = $this->getCss($cssString, $file);
        return $css;
    }

    /**
     * Get Stylecow\Css object from a string.
     * @param string $cssString
     * @param string $file
     *
     * @return Stylecow\Css
     */
    protected function getCss($cssString, $file)
    {
        $css = Stylecow\Parser::parseString($cssString);
        $this->checkCssImport($css, $file);
        return $css;
    }

    /**
     * Check @import in css and include it.
     * Extends Stylecow\Parser::parseImport
     * @param Stylecow\Css $css
     * @param string $file
     */
    protected function checkCssImport($css, $file)
    {
        $remove = array();
        foreach ($css as $c) {
            if ($c->selector->type == '@import' && !empty($c->selector->selectors[0])) {
                $impFile = trim(str_replace(array('\'', '"', 'url(', ')'), '', $c->selector->selectors[0]));
                $impFile = $this->getValidUrl($impFile, dirname($file), true);
                $import = $this->loadCss($impFile);
                if ($import) {
                    foreach ($import->getChildren() as $child) {
                        $css->addChild($child);
                    }
                    array_push($remove, $c);
                }
            }
        }
        foreach ($remove as $c) {
            $c->removeFromParent();
        }
    }

    /**
     * Get valid URL for.
     * @param string $url URL to transform.
     * @param string $start Url to get parts from
     * @param bool $reset Reset cache.
     *
     * @return string Valid URL.
     */
    protected function getValidUrl($url, $start, $reset = false)
    {
        static $st;
        if (empty($st) || $reset) {
            if (empty($start)) {
                return $url;
            }
            $st = parse_url($start);
            if (empty($st['scheme'])) {
                $st['scheme'] = 'http';
            }
            $st['path'] = rtrim($st['path'], '/');
        }
        $u = parse_url($url);
        if (empty($u['scheme'])) {
            $u['scheme'] = $st['scheme'];
        }
        if (empty($u['host'])) {
            $u['host'] = $st['host'];
            if (strpos($u['path'], '/') !== 0) {
                $u['path'] = $st['path'] . '/' . $u['path'];
            }
        }
        return $u['scheme'] . '://' . $u['host'] . $u['path'];
    }

    /**
     * Remove useless space from css.
     * @param string $css
     *
     * @return string
     */
    protected function compressCss($css)
    {
        $css = preg_replace('!/\*[^*]*\*+([^/][^*]*\*+)*/!', '', $css);
        /* remove tabs, spaces, newlines, etc. */
        $css = str_replace(array("\r\n", "\r", "\n", "\t", '  ', '    ', '    '), '', $css);
        $css = str_replace(array(" {", ": ",), array("{", ":"), $css);
        return $css;
    }

    /**
     * Remove useless space from HTML.
     * @param string $html
     *
     * @return string
     */
    protected function compressHtml($html)
    {
       $html = preg_replace(
            array('/ {2,}/', '/<!--.*?-->|\t|(?:\r?\n[ \t]*)+/s'),
            array(' ',''),
            $html
       );
       return $html;
    }
}
