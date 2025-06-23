<?php
/**
 * Temporary Single File Version - Data Normalization Tool
 * Use this while setting up the proper file structure
 */

// Database Configuration
$config = [
    'host' => 'localhost',
    'user' => 'root',
    'pass' => '',
    'db' => 'simple_norm'
];

$pdo = null;

// Database Functions
function initDatabase() {
    global $config, $pdo;
    
    try {
        $pdo = new PDO("mysql:host={$config['host']}", $config['user'], $config['pass']);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        $pdo->exec("CREATE DATABASE IF NOT EXISTS {$config['db']}");
        $pdo->exec("USE {$config['db']}");
        
        $pdo->exec("CREATE TABLE IF NOT EXISTS data_norm (
            id INT AUTO_INCREMENT PRIMARY KEY,
            original_data TEXT,
            normalized_data TEXT,
            method VARCHAR(20),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        return $pdo;
        
    } catch (PDOException $e) {
        error_log("Database error: " . $e->getMessage());
        return null;
    }
}

// Normalization Functions
function normalize_minmax($data) {
    if (empty($data)) return [];
    
    $min = min($data);
    $max = max($data);
    $range = $max - $min;
    
    if ($range == 0) return array_fill(0, count($data), 0);
    
    $result = [];
    foreach ($data as $val) {
        $result[] = round(($val - $min) / $range, 4);
    }
    
    return $result;
}

function normalize_zscore($data) {
    if (empty($data)) return [];
    
    $count = count($data);
    $mean = array_sum($data) / $count;
    
    $sum_squared_diff = 0;
    foreach ($data as $val) {
        $sum_squared_diff += pow($val - $mean, 2);
    }
    
    $std = sqrt($sum_squared_diff / $count);
    
    if ($std == 0) return array_fill(0, $count, 0);
    
    $result = [];
    foreach ($data as $val) {
        $result[] = round(($val - $mean) / $std, 4);
    }
    
    return $result;
}

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

function isValidMethod($method) {
    return in_array($method, ['minmax', 'zscore']);
}

function getMethodDescription($method) {
    $descriptions = [
        'minmax' => 'Min-Max normalization (scales to 0-1)',
        'zscore' => 'Z-Score normalization (mean=0, std=1)'
    ];
    
    return $descriptions[$method] ?? 'Unknown method';
}

// Database Operations
function saveNormalizationResult($originalData, $normalizedData, $method) {
    global $pdo;
    
    if (!$pdo) return false;
    
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

function getNormalizationHistory($limit = 5) {
    global $pdo;
    
    if (!$pdo) return [];
    
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

// Initialize variables
$message = '';
$messageType = '';
$results = [];

// Initialize database
$pdo = initDatabase();
if (!$pdo) {
    $message = "Database connection failed. Please check your configuration.";
    $messageType = 'error';
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $input = $_POST['data'] ?? '';
        $method = $_POST['method'] ?? '';
        
        if (!isValidMethod($method)) {
            throw new Exception("Please select a valid normalization method");
        }
        
        $numbers = parseNumbers($input);
        
        if ($method === 'minmax') {
            $normalized = normalize_minmax($numbers);
        } else {
            $normalized = normalize_zscore($numbers);
        }
        
        if (saveNormalizationResult($numbers, $normalized, $method)) {
            $results = [
                'original' => $numbers,
                'normalized' => $normalized,
                'method' => $method
            ];
            $message = "Data normalized successfully using " . getMethodDescription($method) . "!";
            $messageType = 'success';
        } else {
            $message = "Data normalized but couldn't save to database.";
            $messageType = 'error';
        }
        
    } catch (Exception $e) {
        $message = "Error: " . $e->getMessage();
        $messageType = 'error';
    }
}

$history = getNormalizationHistory(5);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Data Normalization Tool</title>
    <style>
        * { box-sizing: border-box; }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0; padding: 20px; background-color: #f5f5f5;
            color: #333; line-height: 1.6;
        }
        
        .container {
            max-width: 900px; margin: 0 auto; background: white;
            padding: 30px; border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
        }
        
        h1 {
            color: #2c3e50; text-align: center; margin-bottom: 30px;
            font-size: 2.2em; font-weight: 300;
        }
        
        h3 {
            color: #34495e; border-bottom: 2px solid #3498db;
            padding-bottom: 5px; margin-top: 30px;
        }
        
        form {
            background: #f8f9fa; padding: 25px; border-radius: 8px;
            margin-bottom: 25px; border: 1px solid #e9ecef;
        }
        
        label {
            display: block; font-weight: 600; color: #495057;
            margin-bottom: 5px; margin-top: 15px;
        }
        
        input, select, textarea {
            width: 100%; padding: 12px; margin: 5px 0 15px 0;
            border: 1px solid #ced4da; border-radius: 5px;
            font-size: 14px; transition: border-color 0.3s ease;
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none; border-color: #3498db;
            box-shadow: 0 0 0 2px rgba(52, 152, 219, 0.2);
        }
        
        textarea {
            resize: vertical; min-height: 80px;
            font-family: 'Courier New', monospace;
        }
        
        button {
            padding: 12px 25px;
            background: linear-gradient(135deg, #3498db, #2980b9);
            color: white; border: none; border-radius: 5px;
            cursor: pointer; font-size: 16px; font-weight: 600;
            transition: all 0.3s ease; margin-top: 10px;
        }
        
        button:hover {
            background: linear-gradient(135deg, #2980b9, #1f5582);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }
        
        table {
            width: 100%; border-collapse: collapse; margin: 20px 0;
            background: white; border-radius: 8px; overflow: hidden;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }
        
        th, td {
            border: 1px solid #e1e8ed; padding: 12px 15px; text-align: left;
        }
        
        th {
            background: linear-gradient(135deg, #34495e, #2c3e50);
            color: white; font-weight: 600; text-transform: uppercase;
            font-size: 12px; letter-spacing: 0.5px;
        }
        
        tr:nth-child(even) { background: #f8f9fa; }
        tr:hover { background: #e3f2fd; transition: background-color 0.2s ease; }
        
        .message {
            padding: 15px 20px; margin: 15px 0; border-radius: 6px;
            font-weight: 500; border-left: 4px solid;
        }
        
        .success {
            background: #d1ecf1; color: #0c5460; border-left-color: #17a2b8;
            border: 1px solid #bee5eb;
        }
        
        .error {
            background: #f8d7da; color: #721c24; border-left-color: #dc3545;
            border: 1px solid #f5c6cb;
        }
        
        ul { padding-left: 20px; }
        li { margin: 8px 0; color: #6c757d; }
        li strong { color: #495057; }
        
        .results-section, .history-section {
            background: #f0f8ff; padding: 20px; border-radius: 8px;
            border: 1px solid #cce7ff; margin: 20px 0;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Simple Data Normalization Tool</h1>
        
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <form method="post">
            <h3>Enter Your Data</h3>
            
            <label for="data">Numbers (comma or space separated):</label>
            <textarea 
                id="data" 
                name="data" 
                rows="4" 
                placeholder="Example: 1, 2, 3, 4, 5 or 10 20 30 40 50" 
                required
            ><?php echo htmlspecialchars($_POST['data'] ?? ''); ?></textarea>
            
            <label for="method">Normalization Method:</label>
            <select id="method" name="method" required>
                <option value="">Choose a normalization method...</option>
                <option value="minmax" <?php echo ($_POST['method'] ?? '') === 'minmax' ? 'selected' : ''; ?>>
                    Min-Max Normalization (0 to 1 scale)
                </option>
                <option value="zscore" <?php echo ($_POST['method'] ?? '') === 'zscore' ? 'selected' : ''; ?>>
                    Z-Score Normalization (Standard Score)
                </option>
            </select>
            
            <button type="submit">Normalize Data</button>
        </form>
        
        <?php if ($results): ?>
            <div class="results-section">
                <h3>Normalization Results</h3>
                <p><strong>Method Used:</strong> <?php echo getMethodDescription($results['method']); ?></p>
                <p><strong>Data Points:</strong> <?php echo count($results['original']); ?></p>
                
                <table>
                    <thead>
                        <tr>
                            <th>Index</th>
                            <th>Original Value</th>
                            <th>Normalized Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ($i = 0; $i < count($results['original']); $i++): ?>
                        <tr>
                            <td><?php echo $i + 1; ?></td>
                            <td><?php echo $results['original'][$i]; ?></td>
                            <td><?php echo $results['normalized'][$i]; ?></td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($history)): ?>
            <div class="history-section">
                <h3>Recent Normalization History</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Method</th>
                            <th>Data Points</th>
                            <th>Sample Data</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($history as $row): ?>
                        <?php 
                            $originalData = json_decode($row['original_data'], true);
                            $sampleData = array_slice($originalData, 0, 3);
                            $sampleText = implode(', ', $sampleData);
                            if (count($originalData) > 3) {
                                $sampleText .= '...';
                            }
                        ?>
                        <tr>
                            <td><?php echo date('M j, Y H:i', strtotime($row['created_at'])); ?></td>
                            <td><?php echo ucfirst($row['method']); ?></td>
                            <td><?php echo count($originalData); ?></td>
                            <td><?php echo $sampleText; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        
        <hr style="border: none; border-top: 2px solid #e9ecef; margin: 30px 0;">
        
        <h4>How to Use This Tool</h4>
        <ul>
            <li><strong>Min-Max Normalization:</strong> Scales all values to fit between 0 and 1</li>
            <li><strong>Z-Score Normalization:</strong> Centers data around 0 with standard deviation of 1</li>
            <li><strong>Input Examples:</strong> 1,2,3,4,5 or 10 20 30 40 50</li>
        </ul>
    </div>
</body>
</html>