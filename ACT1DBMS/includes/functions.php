<?php
/**
 * Data Normalization Functions
 * File: includes/functions.php
 */

/**
 * Normalize data using Min-Max normalization (0 to 1 scale)
 * Formula: (x - min) / (max - min)
 * 
 * @param array $data Array of numeric values
 * @return array Normalized values between 0 and 1
 */
function normalize_minmax($data) {
    if (empty($data)) {
        return [];
    }
    
    $min = min($data);
    $max = max($data);
    $range = $max - $min;
    
    // If all values are the same, return array of zeros
    if ($range == 0) {
        return array_fill(0, count($data), 0);
    }
    
    $result = [];
    foreach ($data as $val) {
        $result[] = round(($val - $min) / $range, 4);
    }
    
    return $result;
}

/**
 * Normalize data using Z-Score normalization (standard score)
 * Formula: (x - mean) / standard_deviation
 * 
 * @param array $data Array of numeric values
 * @return array Normalized values with mean=0 and std=1
 */
function normalize_zscore($data) {
    if (empty($data)) {
        return [];
    }
    
    $count = count($data);
    
    // Calculate mean
    $mean = array_sum($data) / $count;
    
    // Calculate standard deviation
    $sum_squared_diff = 0;
    foreach ($data as $val) {
        $sum_squared_diff += pow($val - $mean, 2);
    }
    
    $std = sqrt($sum_squared_diff / $count);
    
    // If standard deviation is 0, return array of zeros
    if ($std == 0) {
        return array_fill(0, $count, 0);
    }
    
    $result = [];
    foreach ($data as $val) {
        $result[] = round(($val - $mean) / $std, 4);
    }
    
    return $result;
}

/**
 * Parse input string into array of numbers
 * Accepts comma or space-separated values
 * 
 * @param string $input Raw input string
 * @return array Array of numeric values
 * @throws Exception If no valid numbers found
 */
function parseNumbers($input) {
    if (empty(trim($input))) {
        throw new Exception("Please enter data");
    }
    
    $numbers = [];
    $parts = preg_split('/[,\s]+/', trim($input));
    
    foreach ($parts as $part) {
        $part = trim($part);
        if (is_numeric($part)) {
            $numbers[] = floatval($part);
        }
    }
    
    if (empty($numbers)) {
        throw new Exception("No valid numbers found");
    }
    
    return $numbers;
}

/**
 * Validate normalization method
 * 
 * @param string $method Method name
 * @return bool True if valid method
 */
function isValidMethod($method) {
    return in_array($method, ['minmax', 'zscore']);
}

/**
 * Get method description
 * 
 * @param string $method Method name
 * @return string Description of the method
 */
function getMethodDescription($method) {
    $descriptions = [
        'minmax' => 'Min-Max normalization (scales to 0-1)',
        'zscore' => 'Z-Score normalization (mean=0, std=1)'
    ];
    
    return $descriptions[$method] ?? 'Unknown method';
}
?>