<?php
// Start session for flash messages
session_start();

// Include database connection to get tables list
require_once __DIR__ . '/../config/db.php';

// Get all tables from PostgreSQL database
$tables = [];
try {
    $conn = getDbConnection();
    if ($conn) {
        // Query to get all tables from the public schema
        $query = "SELECT table_name FROM information_schema.tables WHERE table_schema = 'public' ORDER BY table_name";
        $stmt = $conn->query($query);
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
} catch (PDOException $e) {
    $_SESSION['error'] = 'Erro ao buscar tabelas do banco de dados: ' . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importador de Excel para PostgreSQL</title>
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
        <div class="max-w-2xl mx-auto bg-white rounded-lg shadow-md overflow-hidden">
            <div class="bg-primary p-6 text-white">
                <h1 class="text-2xl font-bold">Importador de Excel para PostgreSQL</h1>
                <p class="mt-2 text-gray-200">Faça upload de arquivos Excel (.xlsx) para importar no banco de dados</p>
            </div>
            <!-- Flash Messages -->
            <?php if (isset($_SESSION['success'])): ?>
                <div class="bg-success/10 border-l-4 border-success text-success p-4 mb-4">
                    <p><?php echo $_SESSION['success']; ?></p>
                </div>
                <?php unset($_SESSION['success']); ?>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="bg-error/10 border-l-4 border-error text-error p-4 mb-4">
                    <p><?php echo $_SESSION['error']; ?></p>
                </div>
                <?php unset($_SESSION['error']); ?>
            <?php endif; ?>
            
            <!-- Upload Form -->
                <div class="p-6">
                <form action="../src/upload.php" method="post" enctype="multipart/form-data" class="space-y-4">
                    <div>
                        <label for="excel_file" class="block text-sm font-medium text-gray-700 mb-1">Arquivo Excel (.xlsx)</label>
                        <div class="flex items-center justify-center w-full">
                            <label for="excel_file" class="flex flex-col items-center justify-center w-full h-32 border-2 border-gray-300 border-dashed rounded-lg cursor-pointer bg-gray-50 hover:bg-gray-100">
                                <div class="flex flex-col items-center justify-center pt-5 pb-6">
                                    <i class="fas fa-file-excel text-4xl text-gray-400 mb-2"></i>
                                    <p class="mb-2 text-sm text-gray-500"><span class="font-semibold">Clique para selecionar</span> ou arraste o arquivo</p>
                                    <p class="text-xs text-gray-500">XLSX (MAX. 10MB)</p>
                                </div>
                                <input id="excel_file" name="excel_file" type="file" accept=".xlsx" class="hidden" required />
                            </label>
                        </div>
                        <div id="file-name" class="mt-2 text-sm text-gray-500 hidden">
                            Arquivo selecionado: <span class="font-medium"></span>
                        </div>
                    </div>
                    
                    <div>
                        <label for="table_name" class="block text-sm font-medium text-gray-700 mb-1">Nome da Tabela no PostgreSQL</label>
                        <select id="table_name" name="table_name" class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-accent focus:border-transparent" required>
                            <option value="">Selecione uma tabela</option>
                            <?php foreach ($tables as $table): ?>
                                <option value="<?php echo htmlspecialchars($table); ?>"><?php echo htmlspecialchars($table); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="pt-4">
                        <button type="submit" class="w-full bg-accent hover:bg-accent/80 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline transition duration-300">
                            <i class="fas fa-upload mr-2"></i> Enviar e Processar
                        </button>
                    </div>
                </form>
            </div>
        </div>
        
        <!-- Results Section (will be populated by process_excel.php) -->
        <?php if (isset($_SESSION['import_results'])): ?>
            <div class="max-w-4xl mx-auto mt-8 bg-white rounded-lg shadow-md overflow-hidden">
                <div class="bg-primary p-4 text-white">
                    <h2 class="text-xl font-bold">Resultados da Importação</h2>
                </div>
                
                <div class="p-6 space-y-6">
                    <!-- Success Section -->
                    <div>
                        <h3 class="text-lg font-semibold text-success flex items-center">
                            <i class="fas fa-check-circle mr-2"></i> Registros Importados com Sucesso
                            <span class="ml-2 bg-success text-white text-xs px-2 py-1 rounded-full">
                                <?php echo count($_SESSION['import_results']['success']); ?>
                            </span>
                        </h3>
                        
                        <?php if (!empty($_SESSION['import_results']['success'])): ?>
                            <div class="mt-2 h-96 overflow-x-auto">
                                <table class="min-w-full bg-white border border-gray-200">
                                    <thead class="bg-gray-100">
                                        <tr>
                                            <th class="py-2 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Linha</th>
                                            <th class="py-2 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dados</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <?php foreach ($_SESSION['import_results']['success'] as $index => $data): ?>
                                            <tr>
                                                <td class="py-2 px-4 text-sm text-gray-500"><?php echo $index + 2; ?></td>
                                                <td class="py-2 px-4 text-sm text-gray-500">
                                                    <pre class="whitespace-pre-wrap"><?php echo htmlspecialchars(json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="mt-2 text-gray-500 italic">Nenhum registro importado com sucesso.</p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Error Section -->
                    <div>
                        <h3 class="text-lg font-semibold text-error flex items-center">
                            <i class="fas fa-exclamation-circle mr-2"></i> Erros na Importação
                            <span class="ml-2 bg-error text-white text-xs px-2 py-1 rounded-full">
                                <?php echo count($_SESSION['import_results']['errors']); ?>
                            </span>
                        </h3>
                        
                        <?php if (!empty($_SESSION['import_results']['errors'])): ?>
                            <div class="mt-2 h-96 overflow-x-auto">
                                <table class="min-w-full bg-white border border-gray-200">
                                    <thead class="bg-gray-100">
                                        <tr>
                                            <th class="py-2 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Linha</th>
                                            <th class="py-2 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Dados</th>
                                            <th class="py-2 px-4 border-b text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Erro</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-200">
                                        <?php foreach ($_SESSION['import_results']['errors'] as $error): ?>
                                            <tr>
                                                <td class="py-2 px-4 text-sm text-gray-500"><?php echo $error['row']; ?></td>
                                                <td class="py-2 px-4 text-sm text-gray-500">
                                                    <pre class="whitespace-pre-wrap"><?php echo htmlspecialchars(json_encode($error['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)); ?></pre>
                                                </td>
                                                <td class="py-2 px-4 text-sm text-error"><?php echo $error['message']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="mt-2 text-gray-500 italic">Nenhum erro encontrado durante a importação.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <footer class="mt-8 text-center text-gray-500 text-sm">
            <p>Sistema de Importação Excel para PostgreSQL &copy; <?php echo date('Y'); ?></p>
        </footer>
    </div>
    
    <?php
        // $_SESSION['import_results'] = [
        //     'success' => [],
        //     'errors' => []
        // ];
    ?>
    <script>
        // Display selected file name
        document.getElementById('excel_file').addEventListener('change', function(e) {
            const fileName = e.target.files[0]?.name;
            const fileNameContainer = document.getElementById('file-name');
            
            if (fileName) {
                fileNameContainer.querySelector('span').textContent = fileName;
                fileNameContainer.classList.remove('hidden');
            } else {
                fileNameContainer.classList.add('hidden');
            }
        });
    </script>
</body>
</html>