<?php
/**
 * Database Operations
 * File: includes/database_operations.php
 */

require_once 'config/database.php';

/**
 * Save normalization result to database
 * 
 * @param array $originalData Original numeric data
 * @param array $normalizedData Normalized data
 * @param string $method Normalization method used
 * @return bool Success status
 */
function saveNormalizationResult($originalData, $normalizedData, $method) {
    $pdo = getConnection();
    
    if (!$pdo) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("
            INSERT INTO data_norm (original_data, normalized_data, method) 
            VALUES (?, ?, ?)
        ");
        
        return $stmt->execute([
            json_encode($originalData),
            json_encode($normalizedData),
            $method
        ]);
        
    } catch (PDOException $e) {
        error_log("Save error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get recent normalization history
 * 
 * @param int $limit Number of records to retrieve
 * @return array Array of history records
 */
function getNormalizationHistory($limit = 5) {
    $pdo = getConnection();
    
    if (!$pdo) {
        return [];
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT id, original_data, normalized_data, method, created_at 
            FROM data_norm 
            ORDER BY created_at DESC 
            LIMIT ?
        ");
        
        $stmt->execute([$limit]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("History retrieval error: " . $e->getMessage());
        return [];
    }
}

/**
 * Get specific normalization record by ID
 * 
 * @param int $id Record ID
 * @return array|null Record data or null if not found
 */
function getNormalizationById($id) {
    $pdo = getConnection();
    
    if (!$pdo) {
        return null;
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT * FROM data_norm WHERE id = ?
        ");
        
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Record retrieval error: " . $e->getMessage());
        return null;
    }
}

/**
 * Delete normalization record
 * 
 * @param int $id Record ID to delete
 * @return bool Success status
 */
function deleteNormalizationRecord($id) {
    $pdo = getConnection();
    
    if (!$pdo) {
        return false;
    }
    
    try {
        $stmt = $pdo->prepare("DELETE FROM data_norm WHERE id = ?");
        return $stmt->execute([$id]);
        
    } catch (PDOException $e) {
        error_log("Delete error: " . $e->getMessage());
        return false;
    }
}

/**
 * Get statistics about stored normalizations
 * 
 * @return array Statistics data
 */
function getNormalizationStats() {
    $pdo = getConnection();
    
    if (!$pdo) {
        return [];
    }
    
    try {
        $stmt = $pdo->query("
            SELECT 
                COUNT(*) as total_records,
                COUNT(CASE WHEN method = 'minmax' THEN 1 END) as minmax_count,
                COUNT(CASE WHEN method = 'zscore' THEN 1 END) as zscore_count,
                MIN(created_at) as first_record,
                MAX(created_at) as last_record
            FROM data_norm
        ");
        
        return $stmt->fetch(PDO::FETCH_ASSOC);
        
    } catch (PDOException $e) {
        error_log("Stats error: " . $e->getMessage());
        return [];
    }
}
?>