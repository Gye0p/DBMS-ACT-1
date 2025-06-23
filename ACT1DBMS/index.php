<?php
/**
 * Main Application File
 * File: index.php
 */

// Include all required files
require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/database_operations.php';

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
        
        // Validate method
        if (!isValidMethod($method)) {
            throw new Exception("Please select a valid normalization method");
        }
        
        // Parse and validate input data
        $numbers = parseNumbers($input);
        
        // Perform normalization
        if ($method === 'minmax') {
            $normalized = normalize_minmax($numbers);
        } else {
            $normalized = normalize_zscore($numbers);
        }
        
        // Save to database
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

// Get recent history
$history = getNormalizationHistory(5);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple Data Normalization Tool</title>
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
    <div class="container">
        <h1>Simple Data Normalization Tool</h1>
        
        <?php if ($message): ?>
            <div class="message <?php echo $messageType; ?> fade-in">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>
        
        <form method="post" id="normalizationForm">
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
            <div class="results-section fade-in">
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
        
        <hr>
        
        <h4>How to Use This Tool</h4>
        <ul>
            <li><strong>Min-Max Normalization:</strong> Scales all values to fit between 0 and 1. Good for when you know the approximate range of your data.</li>
            <li><strong>Z-Score Normalization:</strong> Centers data around 0 with a standard deviation of 1. Good for normally distributed data.</li>
            <li><strong>Input Format:</strong> Enter numbers separated by commas or spaces. Examples:
                <ul>
                    <li>Comma separated: <code>1, 2, 3, 4, 5</code></li>
                    <li>Space separated: <code>10 20 30 40 50</code></li>
                    <li>Mixed: <code>1.5, 2.7 3.2, 4.8 5.1</code></li>
                </ul>
            </li>
        </ul>
        
        <h4>About the Methods</h4>
        <ul>
            <li><strong>Min-Max:</strong> Uses formula (x - min) / (max - min)</li>
            <li><strong>Z-Score:</strong> Uses formula (x - mean) / standard_deviation</li>
        </ul>
    </div>

    <script>
        // Simple form validation and UX improvements
        document.getElementById('normalizationForm').addEventListener('submit', function(e) {
            const button = this.querySelector('button[type="submit"]');
            button.textContent = 'Processing...';
            button.classList.add('loading');
        });

        // Auto-resize textarea
        const textarea = document.getElementById('data');
        textarea.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = this.scrollHeight + 'px';
        });
    </script>
</body>
</html>