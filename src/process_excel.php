<?php
/**
 * Excel Processing Script
 * Reads Excel file and imports data into PostgreSQL database
 */

// Start session for flash messages
session_start();

// Include database connection
require_once __DIR__ . '/../config/db.php';

// Check if required parameters are provided
if (!isset($_GET['file']) || !isset($_GET['table'])) {
    $_SESSION['error'] = 'Parâmetros inválidos';
    header('Location: ../public/index.php');
    exit;
}

// Get parameters
$filename = $_GET['file'];
$table_name = $_GET['table'];

// Validate filename (security check)
if (!preg_match('/^excel_[a-f0-9]+\.xlsx$/', $filename)) {
    $_SESSION['error'] = 'Nome de arquivo inválido';
    header('Location: ../public/index.php');
    exit;
}

// Set file path
$filepath = __DIR__ . '/../uploads/' . $filename;

// Check if file exists
if (!file_exists($filepath)) {
    $_SESSION['error'] = 'Arquivo não encontrado';
    header('Location: ../public/index.php');
    exit;
}

// Require PhpSpreadsheet library
// Note: You need to install this via Composer
// composer require phpoffice/phpspreadsheet
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// Initialize results arrays
$success_rows = [];
$error_rows = [];

try {
    // Load the spreadsheet
    $spreadsheet = IOFactory::load($filepath);
    $worksheet = $spreadsheet->getActiveSheet();
    
    // Get the highest row and column
    $highest_row = $worksheet->getHighestRow();
    $highest_column = $worksheet->getHighestColumn();
    $highest_column_index = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highest_column);
    
    // Check if file has data
    if ($highest_row <= 1) {
        throw new Exception('O arquivo Excel não contém dados');
    }
    
    // Get column headers (first row) - still needed for reference
    $headers = [];
    for ($col = 1; $col <= $highest_column_index; $col++) {
        $column_letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
        $cell_value = $worksheet->getCell($column_letter . '1')->getValue();
        if ($cell_value) {
            $headers[$col] = trim($cell_value);
        }
    }
    
    // Check if headers were found
    if (empty($headers)) {
        throw new Exception('Não foi possível encontrar cabeçalhos no arquivo Excel');
    }
    
    // Get database connection
    $conn = getDbConnection();
    if (!$conn) {
        throw new Exception('Não foi possível conectar ao banco de dados');
    }
    
    // Check if we have column mapping from the previous step
    if (!isset($_SESSION['column_mapping']) || !is_array($_SESSION['column_mapping']) || empty($_SESSION['column_mapping'])) {
        throw new Exception('Mapeamento de colunas não encontrado. Por favor, mapeie as colunas primeiro.');
    }
    
    // Get column mapping
    $column_mapping = $_SESSION['column_mapping'];
    
    // Process each row (skip header row)
    for ($row = 2; $row <= $highest_row; $row++) {
        $row_data = [];
        $empty_row = true;
        
        // Get data from each cell in the row, but only for mapped columns
        foreach ($column_mapping as $excel_col => $db_column) {
            // Skip columns that were set to be ignored (empty value)
            if (empty($db_column)) {
                continue;
            }
            
            $column_letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($excel_col);
            $cell_value = $worksheet->getCell($column_letter . $row)->getValue();
            
            // Check if cell has value
            if ($cell_value !== null && $cell_value !== '') {
                $empty_row = false;
            }
            
            $row_data[$db_column] = $cell_value;
        }
        
        // Skip empty rows
        if ($empty_row) {
            continue;
        }
        
        try {
            // Prepare column names and placeholders for SQL
            $columns = implode(', ', array_keys($row_data));
            $placeholders = implode(', ', array_fill(0, count($row_data), '?'));
            
            // Prepare SQL statement
            $sql = "INSERT INTO $table_name ($columns) VALUES ($placeholders)";
            $stmt = $conn->prepare($sql);
            
            // Execute statement with row data
            $stmt->execute(array_values($row_data));
            
            // Add to success rows
            $success_rows[] = $row_data;
        } catch (PDOException $e) {
            // Add to error rows
            $error_rows[] = [
                'row' => $row,
                'data' => $row_data,
                'message' => $e->getMessage()
            ];
            
            // Log error
            error_log("Error importing row $row: " . $e->getMessage());
        }
    }
    
    // Store results in session
    $_SESSION['import_results'] = [
        'success' => $success_rows,
        'errors' => $error_rows
    ];
    
    // Clear column mapping from session after processing
    unset($_SESSION['column_mapping']);
    
    // Set success message
    $success_count = count($success_rows);
    $error_count = count($error_rows);
    $_SESSION['success'] = "Importação concluída: $success_count registros importados com sucesso, $error_count erros.";
    
    // Redirect to index page
    header('Location: ../public/index.php');
    exit;
} catch (Exception $e) {
    // Handle general errors
    $_SESSION['error'] = 'Erro ao processar o arquivo: ' . $e->getMessage();
    error_log('Excel Processing Error: ' . $e->getMessage());
    header('Location: ../public/index.php');
    exit;
} finally {
    // Clean up - delete the uploaded file
    if (file_exists($filepath)) {
        unlink($filepath);
    }
}