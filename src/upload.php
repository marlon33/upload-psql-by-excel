<?php
/**
 * File Upload Handler
 * Handles Excel file upload and redirects to processing script
 */

// Start session for flash messages
session_start();

// Define upload directory
$upload_dir = __DIR__ . '/../uploads/';

// Create uploads directory if it doesn't exist
if (!file_exists($upload_dir)) {
    mkdir($upload_dir, 0777, true);
}

// Check if form was submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate table name
    if (empty($_POST['table_name'])) {
        $_SESSION['error'] = 'Nome da tabela é obrigatório';
        header('Location: ../public/index.php');
        exit;
    }
    
    $table_name = trim($_POST['table_name']);
    
    // Validate table name format (alphanumeric and underscore only)
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table_name)) {
        $_SESSION['error'] = 'Nome da tabela inválido. Use apenas letras, números e underscore (_)';
        header('Location: ../public/index.php');
        exit;
    }
    
    // Check if file was uploaded
    if (!isset($_FILES['excel_file']) || $_FILES['excel_file']['error'] !== UPLOAD_ERR_OK) {
        $error_message = 'Erro no upload do arquivo: ';
        
        // Get specific error message
        switch ($_FILES['excel_file']['error']) {
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                $error_message .= 'O arquivo excede o tamanho máximo permitido.';
                break;
            case UPLOAD_ERR_PARTIAL:
                $error_message .= 'O upload do arquivo foi feito parcialmente.';
                break;
            case UPLOAD_ERR_NO_FILE:
                $error_message .= 'Nenhum arquivo foi enviado.';
                break;
            default:
                $error_message .= 'Erro desconhecido.';
        }
        
        $_SESSION['error'] = $error_message;
        header('Location: ../public/index.php');
        exit;
    }
    
    // Validate file type
    $file_info = pathinfo($_FILES['excel_file']['name']);
    $extension = strtolower($file_info['extension']);
    
    if ($extension !== 'xlsx') {
        $_SESSION['error'] = 'Apenas arquivos .xlsx são permitidos';
        header('Location: ../public/index.php');
        exit;
    }
    
    // Generate unique filename
    $filename = uniqid('excel_') . '.xlsx';
    $filepath = $upload_dir . $filename;
    
    // Move uploaded file to destination
    if (!move_uploaded_file($_FILES['excel_file']['tmp_name'], $filepath)) {
        $_SESSION['error'] = 'Falha ao salvar o arquivo. Verifique as permissões do diretório';
        header('Location: ../public/index.php');
        exit;
    }
    
    // Redirect to column mapping script
    header("Location: map_columns.php?file=$filename&table=$table_name");
    exit;
} else {
    // If accessed directly without POST
    $_SESSION['error'] = 'Acesso inválido';
    header('Location: ../public/index.php');
    exit;
}