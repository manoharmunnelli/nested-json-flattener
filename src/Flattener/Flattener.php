<?php

/*
 * The MIT License
 *
 * Copyright 2015 tonirilix.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */

namespace NestedJsonFlattener;

use Exception;
use RecursiveArrayIterator;
use RecursiveIteratorIterator;
use stdClass;
use Peekmo\JsonPath\JsonStore;

/**
 * Cvswriter allows you to transform nested json data into a flat csv
 *
 * @author tonirilix
 */
class Csvcreator {

    /**
     * Stores the data converted to object wether was passed as object or json string
     * @var type 
     */
    private $data;

    /**
     * TODO: This is going to be the configuration. WIP
     * @var type 
     */
    private $options;

    /**
     * A simple constructor
     */
    public function __construct($options = []) {
        $this->data = [];
        $this->options = $options;
    }

    /**
     * Sets a json passed as string
     * @param string $json
     */
    public function setJsonData($json = '{}') {

        $selectedNode = json_decode($json, true);
        $selectedNode = $this->getPath($selectedNode);
        $this->data = $this->arrayToObject($selectedNode);
    }

    /**
     * Sets a simple array
     * @param array $array
     */
    public function setArrayData(array $array = []) {

        $selectedNode = $array;

        $selectedNode = $this->getPath($selectedNode);

        $this->data = $this->arrayToObject($selectedNode);
    }

    private function getPath($data) {
        $selectedNode = $data;
        if (!empty($this->options) && isset($this->options['path'])) {
            $store = new JsonStore($data);
            $path = $this->options['path'];
            // Returns an array with all categories from books which have an isbn attribute
            $selectedNode = $store->get($path);
        }
        return $selectedNode;
    }

    /**
     * TODO: Sets options that are going to be used as configuration. WIP
     * @param array $options
     */
    public function setOptions(array $options = []) {

        throw new Exception('Please, set options in constructor. This is method is not yet implemented');
        //$this->_options = $options;
    }

    /**
     * Resturns a flatted array
     * @return array
     */
    public function getFlatData() {

        $result = [];

        // Checks wether data is an array or not
        if (!is_array($this->data)) {
            // If it's not we convert it to array
            $this->data = [$this->data];
        }

        // Loops the array 
        foreach ($this->data as $data) {
            // Flats passed array of data
            $result[] = $this->flatten($data, []);
        }

        // Returns
        return $result;
    }

    /**
     * Writes a csv file with the passed data
     * @param string $name the name of the file. Default: "file_" . rand()
     */
    public function writeCsv($name = '') {
        $fileName = !empty($name) ? $name : "file_" . rand();
        // Setting data
        $dataFlattened = $this->getFlatData();

        $csvFormat = $this->arrayToCsv($dataFlattened);
        $this->writeCsvToFile($csvFormat, $fileName);
    }

    private function arrayToCsv($data) {

        $dataNormalized = $this->normalizeKeys($data);

        $rows[0] = array_keys($dataNormalized[0]);

        foreach ($dataNormalized as $value) {
            //$rows[0] = array_keys($value);
            $rows[] = array_values($value);
        }
        return $rows;
    }

    private function writeCsvToFile($data, $name) {
        $file = fopen($name . '.csv', 'w');
        foreach ($data as $line) {
            fputcsv($file, $line, ',');
        }
        fclose($file);
    }

    private function normalizeKeys($param) {
        $keys = array();
        foreach (new RecursiveIteratorIterator(new RecursiveArrayIterator($param)) as $key => $val) {
            $keys[$key] = '';
        }

        $data = array();
        foreach ($param as $values) {
            $data[] = array_merge($keys, $values);
        }

        return $data;
    }

    /**
     * This function works as same as json_decode(json_encode($arr), false). 
     * It was taken from http://stackoverflow.com/a/31652810/3442878
     * @param array $arr
     * @return object
     */
    private function arrayToObject(array $arr) {
        $flat = array_keys($arr) === range(0, count($arr) - 1);
        $out = $flat ? [] : new stdClass();

        foreach ($arr as $key => $value) {
            $temp = is_array($value) ? $this->arrayToObject($value) : $value;

            if ($flat) {
                $out[] = $temp;
            } else {
                $out->{$key} = $temp;
            }
        }

        return $out;
    }

    /**
     * Flats a nested array
     * @param array $data Array with data to be flattened
     * @param array $path Options param, it's used by the recursive method to set the full key name     
     * @return array Flattened array
     */
    private function flatten($data, array $path = array()) {

        // Check if the data is an object        
        if (is_object($data)) {

            $flatObject = $this->flatObject($data, $path);
            return $flatObject;

            // Check if the data is an array
        } elseif (is_array($data)) {

            $flatArray = $this->flatArray($data, $path);
            return $flatArray;
        }

        // If the data isn't an object or an array is a value
        $flatValue = $this->addValue($data, $path);
        return $flatValue;
    }

    private function flatObject($data, array $path = array()) {


        $dataModified = get_object_vars($data);

        $flatArrayHelper = $this->flatArrayHelper($dataModified, $path);
        return $flatArrayHelper;
    }

    private function flatArray($data, array $path = array()) {

        if (count($data) > 0 && !is_object($data[0]) && !is_array($data[0])) {
            $flatPrimitives = $this->flatten(join(",", $data), $path);
            return $flatPrimitives;
        }


        $flatArrayHelper = $this->flatArrayHelper($data, $path);
        return $flatArrayHelper;
    }

    private function flatArrayHelper($data, $path) {
        $result = array();

        foreach ($data as $key => $value) {
            $currentPath = array_merge($path, array($key));
            $flat = $this->flatten($value, $currentPath);
            $result = array_merge($result, $flat);
        }

        return $result;
    }

    private function addValue($data, array $path = array()) {
        $result = array();

        $pathName = join('.', $path);
        $result[$pathName] = $data;

        return $result;
    }

}