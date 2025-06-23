<!DOCTYPE html>
<html>
<head>
    <title>Simple Data Normalization</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 800px; }
        input, select, textarea { width: 100%; padding: 8px; margin: 5px 0; }
        button { padding: 10px 20px; background: #007cba; color: white; border: none; cursor: pointer; }
        button:hover { background: #005a8b; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background: #f2f2f2; }
        .message { padding: 10px; margin: 10px 0; border-radius: 4px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    </style>
</head>
<body>
    <?php
    // Database setup
    $host = 'localhost';
    $user = 'root';
    $pass = '';
    $db = 'simple_norm';
    
    $message = '';
    $results = [];
    
    try {
        $pdo = new PDO("mysql:host=$host", $user, $pass);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS $db");
        $pdo->exec("USE $db");
        $pdo->exec("CREATE TABLE IF NOT EXISTS data_norm (
            id INT AUTO_INCREMENT PRIMARY KEY,
            original_data TEXT,
            normalized_data TEXT,
            method VARCHAR(20),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
    } catch(Exception $e) {
        $message = "Database error: " . $e->getMessage();
    }
    
    // Normalization functions
    function normalize_minmax($data) {
        $min = min($data);
        $max = max($data);
        $range = $max - $min;
        if ($range == 0) return $data;
        
        $result = [];
        foreach($data as $val) {
            $result[] = round(($val - $min) / $range, 4);
        }
        return $result;
    }
    
    function normalize_zscore($data) {
        $mean = array_sum($data) / count($data);
        $sum_sq = 0;
        foreach($data as $val) {
            $sum_sq += pow($val - $mean, 2);
        }
        $std = sqrt($sum_sq / count($data));
        if ($std == 0) return array_fill(0, count($data), 0);
        
        $result = [];
        foreach($data as $val) {
            $result[] = round(($val - $mean) / $std, 4);
        }
        return $result;
    }
    
    // Process form
    if ($_POST) {
        try {
            $input = trim($_POST['data']);
            $method = $_POST['method'];
            
            if (empty($input)) throw new Exception("Please enter data");
            
            // Parse numbers
            $numbers = [];
            $parts = preg_split('/[,\s]+/', $input);
            foreach($parts as $part) {
                if (is_numeric(trim($part))) {
                    $numbers[] = floatval(trim($part));
                }
            }
            
            if (empty($numbers)) throw new Exception("No valid numbers found");
            
            // Normalize
            if ($method == 'minmax') {
                $normalized = normalize_minmax($numbers);
            } else {
                $normalized = normalize_zscore($numbers);
            }
            
            // Save to database
            $stmt = $pdo->prepare("INSERT INTO data_norm (original_data, normalized_data, method) VALUES (?, ?, ?)");
            $stmt->execute([json_encode($numbers), json_encode($normalized), $method]);
            
            $results = ['original' => $numbers, 'normalized' => $normalized];
            $message = "Data normalized successfully!";
            
        } catch(Exception $e) {
            $message = "Error: " . $e->getMessage();
        }
    }
    
    // Get history
    $history = [];
    try {
        $stmt = $pdo->query("SELECT * FROM data_norm ORDER BY created_at DESC LIMIT 5");
        $history = $stmt->fetchAll();
    } catch(Exception $e) {}
    ?>

    <div class="container">
        <h1>Simple Data Normalization</h1>
        
        <?php if ($message): ?>
            <div class="message <?php echo strpos($message, 'Error') === 0 ? 'error' : 'success'; ?>">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <form method="post">
            <h3>Enter Your Data</h3>
            <label>Numbers (comma or space separated):</label>
            <textarea name="data" rows="3" placeholder="1, 2, 3, 4, 5" required><?php echo $_POST['data'] ?? ''; ?></textarea>
            
            <label>Normalization Method:</label>
            <select name="method" required>
                <option value="">Choose method</option>
                <option value="minmax" <?php echo ($_POST['method'] ?? '') == 'minmax' ? 'selected' : ''; ?>>Min-Max (0 to 1)</option>
                <option value="zscore" <?php echo ($_POST['method'] ?? '') == 'zscore' ? 'selected' : ''; ?>>Z-Score (Standard)</option>
            </select>
            
            <button type="submit">Normalize Data</button>
        </form>
        
        <?php if ($results): ?>
            <h3>Results</h3>
            <table>
                <tr>
                    <th>Index</th>
                    <th>Original</th>
                    <th>Normalized</th>
                </tr>
                <?php for($i = 0; $i < count($results['original']); $i++): ?>
                <tr>
                    <td><?php echo $i + 1; ?></td>
                    <td><?php echo $results['original'][$i]; ?></td>
                    <td><?php echo $results['normalized'][$i]; ?></td>
                </tr>
                <?php endfor; ?>
            </table>
        <?php endif; ?>
        
        <?php if ($history): ?>
            <h3>Recent History</h3>
            <table>
                <tr>
                    <th>Date</th>
                    <th>Method</th>
                    <th>Data Points</th>
                </tr>
                <?php foreach($history as $row): ?>
                <tr>
                    <td><?php echo date('Y-m-d H:i', strtotime($row['created_at'])); ?></td>
                    <td><?php echo ucfirst($row['method']); ?></td>
                    <td><?php echo count(json_decode($row['original_data'])); ?></td>
                </tr>
                <?php endforeach; ?>
            </table>
        <?php endif; ?>
        
        <hr>
        <h4>How to use:</h4>
        <ul>
            <li><strong>Min-Max:</strong> Scales data between 0 and 1</li>
            <li><strong>Z-Score:</strong> Centers data around 0 with standard deviation of 1</li>
            <li>Enter numbers like: 1, 2, 3, 4, 5 or 10 20 30 40 50</li>
        </ul>
