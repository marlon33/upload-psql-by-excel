<?php
/**
 * Column Mapping Script
 * Allows user to map Excel columns to PostgreSQL table columns
 */

// Start session for flash messages and data persistence
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
require_once __DIR__ . '/../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

// Function to get table columns from PostgreSQL
function getTableColumns($conn, $table_name) {
    try {
        // Query to get column information from the information_schema
        $sql = "SELECT column_name, data_type 
                FROM information_schema.columns 
                WHERE table_name = ? 
                ORDER BY ordinal_position";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$table_name]);
        
        $columns = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $columns[] = [
                'name' => $row['column_name'],
                'type' => $row['data_type']
            ];
        }
        
        return $columns;
    } catch (PDOException $e) {
        error_log("Error getting table columns: " . $e->getMessage());
        return [];
    }
}

// Initialize variables
$excel_headers = [];
$table_columns = [];
$error_message = '';

try {
    // Get database connection
    $conn = getDbConnection();
    if (!$conn) {
        throw new Exception('Não foi possível conectar ao banco de dados');
    }
    
    // Get table columns
    $table_columns = getTableColumns($conn, $table_name);
    
    if (empty($table_columns)) {
        throw new Exception("Não foi possível encontrar colunas na tabela '$table_name'. Verifique se a tabela existe.");
    }
    
    // Load the spreadsheet to get Excel headers
    $spreadsheet = IOFactory::load($filepath);
    $worksheet = $spreadsheet->getActiveSheet();
    
    // Get the highest column
    $highest_column = $worksheet->getHighestColumn();
    $highest_column_index = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::columnIndexFromString($highest_column);
    
    // Get Excel headers (first row)
    for ($col = 1; $col <= $highest_column_index; $col++) {
        $column_letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);
        $cell_value = $worksheet->getCell($column_letter . '1')->getValue();
        
        if ($cell_value) {
            $excel_headers[$col] = [
                'index' => $col,
                'letter' => $column_letter,
                'name' => trim($cell_value)
            ];
        }
    }
    
    // Check if headers were found
    if (empty($excel_headers)) {
        throw new Exception('Não foi possível encontrar cabeçalhos no arquivo Excel');
    }
    
    // Process form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate the mapping
        if (!isset($_POST['column_mapping']) || !is_array($_POST['column_mapping'])) {
            throw new Exception('Mapeamento de colunas inválido');
        }
        
        // Store mapping in session and redirect to processing
        $_SESSION['column_mapping'] = $_POST['column_mapping'];
        header("Location: process_excel.php?file=$filename&table=$table_name&mapped=1");
        exit;
    }
} catch (Exception $e) {
    $error_message = $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mapear Colunas - Importador Excel</title>
    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#2c3e50',
                        accent: '#3498db',
                        success: '#2ecc71',
                        error: '#e74c3c'
                    },
                    fontFamily: {
                        'sans': ['Montserrat', 'sans-serif'],
                    }
                }
            }
        }
    </script>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;600;700&display=swap" rel="stylesheet">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body class="bg-gray-100 min-h-screen font-sans">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto bg-white rounded-lg shadow-md overflow-hidden">
            <div class="bg-primary p-6 text-white">
                <h1 class="text-2xl font-bold">Mapear Colunas do Excel para PostgreSQL</h1>
                <p class="mt-2 text-gray-200">Associe cada coluna do Excel com a coluna correspondente no banco de dados</p>
            </div>
            
            <?php if ($error_message): ?>
                <div class="bg-error/10 border-l-4 border-error text-error p-4 mb-4">
                    <p><?php echo $error_message; ?></p>
                </div>
                <div class="p-6">
                    <a href="../public/index.php" class="inline-block bg-accent hover:bg-accent/80 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300">
                        <i class="fas fa-arrow-left mr-2"></i> Voltar
                    </a>
                </div>
            <?php else: ?>
                <div class="p-6">
                    <form action="map_columns.php?file=<?php echo urlencode($filename); ?>&table=<?php echo urlencode($table_name); ?>" method="post" class="space-y-6">
                        <div class="bg-blue-50 border-l-4 border-accent p-4 mb-4">
                            <p class="text-sm text-gray-700">
                                <i class="fas fa-info-circle text-accent mr-2"></i>
                                Selecione para cada coluna do Excel a coluna correspondente no banco de dados PostgreSQL.
                                Colunas não mapeadas serão ignoradas durante a importação.
                            </p>
                        </div>
                        
                        <div class="overflow-x-auto">
                            <table class="min-w-full bg-white border border-gray-200">
                                <thead class="bg-gray-100">
                                    <tr>
                                        <th class="py-3 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Coluna no Excel</th>
                                        <th class="py-3 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Mapear para Coluna no PostgreSQL</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-gray-200">
                                    <?php foreach ($excel_headers as $col => $header): ?>
                                        <tr>
                                            <td class="py-3 px-4 text-sm">
                                                <div class="font-medium text-gray-900"><?php echo htmlspecialchars($header['name']); ?></div>
                                                <div class="text-xs text-gray-500">Coluna <?php echo $header['letter']; ?></div>
                                            </td>
                                            <td class="py-3 px-4">
                                                <select name="column_mapping[<?php echo $col; ?>]" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent">
                                                    <option value="">-- Ignorar esta coluna --</option>
                                                    <?php foreach ($table_columns as $column): ?>
                                                        <option value="<?php echo htmlspecialchars($column['name']); ?>">
                                                            <?php echo htmlspecialchars($column['name']); ?> (<?php echo htmlspecialchars($column['type']); ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="flex justify-between pt-4">
                            <a href="../public/index.php" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300">
                                <i class="fas fa-times mr-2"></i> Cancelar
                            </a>
                            <button type="submit" class="bg-accent hover:bg-accent/80 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300">
                                <i class="fas fa-check mr-2"></i> Confirmar e Processar
                            </button>
                        </div>
                    </form>
                </div>
            <?php endif; ?>
        </div>
        
        <footer class="mt-8 text-center text-gray-500 text-sm">
            <p>Sistema de Importação Excel para PostgreSQL &copy; <?php echo date('Y'); ?></p>
        </footer>
    </div>
</body>
</html>