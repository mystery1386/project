<?php
/**
 * Created by JetBrains PhpStorm.
 * User: mregner
 * Date: 10.05.13
 * Time: 12:13
 * To change this template use File | Settings | File Templates.  */

class ArrayUtil {
    /**
     * @param array $data
     * @return array
     * @author mregner
     */
    public static function unique(array $data) {
        if (!empty($data)) {
            return array_unique($data, SORT_REGULAR);
        }
        return $data;
    }

    /**
     * @param array $data
     * @return array
     * @author mregner
     */
    public static function flatten(array $data) {
        if (!empty($data)) {
            $flatten = array();
            array_walk_recursive($data,
                function ($value) use(&$flatten)
                {
                    $flatten[] = $value;
                });
            return $flatten;
        }

        return $data;
    }

    /**
     * Sucht in den Daten rekursiv nach dem ersten Element zu dem Pfad.
     *
     * @param array $path
     * @param array $tree
     * @return mixed
     * @author mregner
     */
    public static function find(array $tree, array $path) {
        if (is_array($tree) && is_array($path)) {
            $key = array_shift($path);
            if (empty($key) && !empty($path)) {
                return self::find($tree, $path);
            }
            elseif (isset($tree[$key])) {
                if (!empty($path)) {
                    return self::find($tree[$key], $path);
                } else {
                    return $tree[$key];
                }
            }
        }
        return null;
    }

    /**
     * Sucht in den Daten recursiv nach allen Elementen mit dem passenden Key und
     * optional nach dem passenden Wert. Darüber hinaus kann noch nach einem passenden
     * Pfad gesucht werden.
     *
     * @param array $tree
     * @param $needle
     * @param string $pattern
     * @param array $path
     * @return array
     */
    public static function findAll(array $tree, $needle, $pattern = null, array & $path = null) {
        $result = array();
        isset($path) || $path = array();
        foreach($tree as $key => $value) {
            $subbath = array_merge($path, array($key));
            $pathKey = implode("/", $subbath);
            if ($key === $needle || preg_match("~{$needle}~", $pathKey)) {
                if (isset($pattern)) {
                    preg_match($pattern, $value) && ($result[$pathKey] = $value);
                } else {
                    $result[$pathKey] = $value;
                }
            }
            elseif (is_array($value)) {
                $result = array_merge($result, self::findAll($value, $needle, $pattern, $subbath));
            }
        }

        return $result;
    }

    /**
     * @param array $tree
     * @param array $path
     * @param mixed $value
     * @author mregner
     */
    public static function create(array & $tree, array $path, $value) {
        $lastIndex = count($path) - 1;
        $current = & $tree;
        foreach($path as $index => $element) {
            if (!empty($element)) {
                if (preg_match("~([a-z0-9_-]+)\[last\(\)\+1\]~i", $element, $matches)){
                    if (!isset($current[$matches[1]])) {
                        $current[$matches[1]] = array();
                    }

                    $current = & $current[$matches[1]][];
                    continue;
                } elseif (preg_match("~([a-z0-9_-]+)\[last\(\)\]~i", $element, $matches)){
                    if (is_array($current[$matches[1]])) {
                        end($current[$matches[1]]);
                        $key = key($current[$matches[1]]);
                        $current = & $current[$matches[1]][$key];
                    }
                        continue;
                } elseif ($index === $lastIndex){
                    if ($value === '@none' && isset($current[$element])) {
                        unset($current[$element]);
                    } else {
                        $current[$element] = $value;
                    }
                        break;
                    } else if (!isset($current[$element])) {
                        $current[$element] = array();
                    }

                $current = & $current[$element];
            }
        }
    }

    /**
     * @param array $data
     * @param string $column_key
     * @param string $index_key
     * @return array
     * @author mregner
     */
    public static function getColumn(array $data, $column_key, $index_key = null) {
        $column = array();
        foreach($data as $index => $element) {
            $key = isset($element[$index_key]) ? $element[$index_key] : $index;
            isset($element[$column_key]) && $column[$key] = $element[$column_key];
        }

        return $column;
    }

    /**      * Gruppiere Daten nach Feldern in $fields hierarchisch.      *      * @param array $data      * @param array $fields      * @return array      * @author mregner      */
    public static

    function groupBy(array $data, array $fields)
    {
        if (isset($fields))
        {
            $groupedData = array();
            foreach($data as $element)
            {
                $currentGroup = & $groupedData;
                foreach($fields as $group)
                {
                    if (isset($element[$group]))
                    {
                        $groupName = $element[$group];
                    }
                    else
                    {
                        $groupName = $group;
                    }

                    if (!isset($currentGroup[$groupName]))
                    {
                        $currentGroup[$groupName] = array();
                    }

                    $currentGroup = & $currentGroup[$groupName];
                }

                $currentGroup[] = $element;
            }

            return $groupedData;
        }

        return $data;
    }

    /**      * Gruppiere Daten nach den ersten $count Buchstaben von Feld $field.      *      * @param array $data      * @param string $field      * @param integer $count      * @return array      * @author mregner      */
    public static

    function groupByCharacter(array $data, $field, $count = 1)
    {
        if (isset($field) && $count > 0)
        {
            $groupedData = array();
            foreach($data as $element)
            {
                if (isset($element[$field]))
                {
                    $key = strtoupper(mb_substr($element[$field], 0, $count));
                    isset($groupedData[$key]) || $groupedData[$key] = array();
                    $groupedData[$key][] = $element;
                }
            }

            return $groupedData;
        }

        return $data;
    }

    /**      * Sortiert ein die Daten nach den angegeben Feldern.      *      * Bsp.: $arrayUtil->sortBy(array('feld1' => SORT_DESC));      *      * @param array $data      * @param array $fields      * @return array      * @author mregner      */
    public static

    function sortBy(array $data, array $fields)
    {
        $defaultDir = SORT_ASC;
        if (!is_array($data) || empty($data))
        {
            return $data;
        }

        if (!is_array($fields))
        {
            $fields = array(
                $fields
            );
        }

        $sortRule = '';
        $doSort = false;
        $sortArray = array();
        foreach($fields as $key => $value)
        {
            /**              * $fields kann entweder ein Associatives oder eine indiziertes Array sein.              * Der Aufbau muss entweder so:              * 'key' => 'SORT_DIR'              * oder so:              * 0 => 'key'              * sein.              */
            if (is_string($key) && ($value == SORT_ASC || $value == SORT_DESC))
            {
                $sortField = $key;
                $sortDir = $value;
            }
            else
            {
                $sortField = $value;
                $sortDir = $defaultDir;
            }

            if (!$sortField || isset($sortArray[$sortField])) continue;
            $doSort = true;
            $sortType = false;
            foreach($data as $element)
            {
                if (!$sortType && $sortType !== SORT_STRING)
                {
                    if (!$element[$sortField] || is_numeric($element[$sortField]))
                    {
                        $sortType = SORT_NUMERIC;
                    }
                    else
                    {
                        $sortType = SORT_STRING;
                    }
                }

                $sortArray[$sortField][] = strtolower($element[$sortField]);
            }

            if (!$sortType)
            {
                $sortType = SORT_NUMERIC;
            }

            $sortRule.= "\$sortArray['{$sortField}'], {$sortDir}, {$sortType}, ";
        }

        if ($doSort)
        {
            $evalString = "array_multisort({$sortRule} \$data);";
            eval($evalString);
            reset($data);
        }

        return $data;
    }

    /**      * @param array $data      * @param string $base_tag      * @param bool $pretty      *      * @return string      */
    public static

    function toSimpleXML(array $data, $base_tag = '', $pretty = false)
    {
        $document = new ChainingDOMDocument();
        $root = ($base_tag !== '') ? $base_tag : 'root';
        $document->appendChild($root)->appendArray($data);
        return $document->toXML($pretty);
    }

    /**      * @param string $xml      *      * @return array      */
    public static

    function fromSimpleXML($xml)
    {
        $document = new ChainingDOMDocument(null, null, $xml);
        return $document->toArray();
    }

    /**      * Erstellt aus einem Integer ein Array das für jedes Bit entweder den Wert 0 oder 1      * enthält ($int_values = false) oder den entsprechenden Integer Wert für das Bit      * (1,2,4,8... wenn $int_values=true).      *      * @param int $int      * @param bool $int_values      * @param int $minbits      * @return array      * @author mregner      */
    public static

    function intToBitArray($int, $int_values = false, $minbits = 0)
    {
        $array = array();
        for ($i = 0; $int > 0; $i++)
        {
            if ($int_values === false)
            {
                $array[$i] = ($int & 1) ? 1 : 0;
            }
            else
                if ($int_values === true && ($int & 1))
                {
                    $array[] = pow(2, $i);
                }
                else
                {
                    $array[] = 0;
                }

            $int = $int >> 1;
            $minbits > 0 && $minbits--;
        }

        for ($i = 0; $i < $minbits; $i++)
        {
            $array[] = 0;
        }

        return $array;
    }

    /**      * Ermittelt aus einem eindimensionalen Array einen Integer Wert. Dabei werden die      * einzelnen Elemente entweder als gesetztes Bit ( $int_values=false ) oder als      * integer Repräsentation interpretiert.      *      * @param array $bits      * @param bool $int_values      * @return int      * @author mregner      */
    public static

    function bitArrayToInt(array $bits, $int_values = false)
    {
        if (is_array($bits))
        {
            $intValue = 0;
            for ($i = 0; $i < count($bits); $i++)
            {
                if ($int_values === false && $bits[$i] > 0)
                {
                    $intValue+= pow(2, $i);
                }
                else
                    if ($int_values === true)
                    {
                        $intValue+= $bits[$i];
                    }
            }

            return $intValue;
        }

        return 0;
    }

    /**      * @param array $array1      * @param array $array2      * @return array      * @author mregner      */
    public static

    function mergeRecursiveIntersectKeys(array $array1, array $array2)
    {
        return self::mergeRecursivePreserveKeys($array1, $array2, true);
    }

    /**      * Mergt zwei Arrays rekursiv, wobei auch numerische Keys erhalten bleiben.Wird der Parameter $intersect_keys = true      * übergeben, so werden die Werte gelöscht, wenn der wert in $array1 ein scalar ist und kein Wert in $array2 zu dem      * Schlüssel vorhanden ist.      *      * @param array $array1      * @param array $array2      * @param array $return      * @param bool $intersect_keys      *      * @return array $return      * @author karing      */
    public static

    function mergeRecursivePreserveKeys(array $array1, array $array2, $intersect_keys = false, array & $return = null)
    {
        is_array($return) || ($return = array());
        foreach($array1 as $key1 => $value1)
        {
            if (isset($array2[$key1]))
            {
                if (is_array($value1) && is_array($array2[$key1]) && !empty($array2[$key1]))
                {
                    $return[$key1] = $value1 + $array2[$key1];
                }
                else
                {
                    if (isset($return[$key1]) && is_array($return[$key1]))
                    {
                        $return[$key1][] = $array2[$key1];
                    }
                    else
                    {
                        $return[$key1] = $array2[$key1];
                    }
                }

                if (is_array($value1) && is_array($array2[$key1]))
                {
                    self::mergeRecursivePreserveKeys($value1, $array2[$key1], $intersect_keys, $return[$key1]);
                }
            }
            else
                if (is_scalar($value1) && $intersect_keys && isset($return[$key1]))
                {
                    unset($return[$key1]);
                }
                else
                {
                    $return[$key1] = $value1;
                }
        }

        foreach($array2 as $key2 => $value2)
        {
            if (!isset($array1[$key2]))
            {
                $return[$key2] = $value2;
            }
        }

        return $return;
    }
}
