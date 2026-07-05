<?php
session_start();
header('Content-Type: application/json');

require_once '../../config/db_connection.php';

// Verifica autenticação
if (!isset($_SESSION['user_id']) || !isset($_SESSION['client_id'])) {
    echo json_encode(['success' => false, 'message' => 'Não autorizado']);
    exit();
}

try {
    $employee_id = isset($_GET['employee_id']) ? (int)$_GET['employee_id'] : 0;

    if ($employee_id <= 0) {
        throw new Exception('ID do funcionário inválido');
    }

    $hasExpiryDateColumn = false;
    try {
        $hasExpiryDateColumn = (bool)$pdo->query("SHOW COLUMNS FROM employee_documents LIKE 'expiry_date'")->fetch();
    } catch (Throwable $e) {
    }

    // Buscar documentos do funcionário (apenas do cliente logado)
    $selectExpiry = $hasExpiryDateColumn ? '' : ', NULL AS expiry_date';
    $stmt = $pdo->prepare(
        "SELECT
            d.*,
            u.nome_completo as uploaded_by_name
            {$selectExpiry}
         FROM employee_documents d
         LEFT JOIN usuarios u ON d.uploaded_by = u.id
         WHERE d.employee_id = ? AND d.client_id = ?
         ORDER BY d.created_at DESC"
    );

    $stmt->execute([$employee_id, $_SESSION['client_id']]);
    $documents = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // Formatar tamanho/tempo/validade dos arquivos
    foreach ($documents as &$doc) {
        $doc['file_size_formatted'] = formatFileSize($doc['file_size']);
        $doc['created_at_formatted'] = date('d/m/Y H:i', strtotime((string)$doc['created_at']));

        $expiry = trim((string)($doc['expiry_date'] ?? ''));
        $doc['expiry_date_formatted'] = null;
        $doc['days_to_expiry'] = null;
        $doc['expiry_status'] = 'no-expiry';

        if ($expiry !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiry)) {
            $doc['expiry_date_formatted'] = date('d/m/Y', strtotime($expiry));
            $days = (int)floor((strtotime($expiry . ' 00:00:00') - strtotime(date('Y-m-d') . ' 00:00:00')) / 86400);
            $doc['days_to_expiry'] = $days;

            if ($days < 0) {
                $doc['expiry_status'] = 'expired';
            } elseif ($days <= 30) {
                $doc['expiry_status'] = 'expiring_soon';
            } else {
                $doc['expiry_status'] = 'valid';
            }
        }
    }
    unset($doc);

    // Checklist base do dossiê digital
    $requiredTypes = ['Contrato', 'Identificação', 'Certidão'];
    $normalizedPresent = [];
    foreach ($documents as $doc) {
        $type = mb_strtolower(trim((string)($doc['document_type'] ?? '')));
        if ($type !== '') {
            $normalizedPresent[$type] = true;
        }
    }

    $missingTypes = [];
    foreach ($requiredTypes as $requiredType) {
        if (!isset($normalizedPresent[mb_strtolower($requiredType)])) {
            $missingTypes[] = $requiredType;
        }
    }

    $checklistTotal = count($requiredTypes);
    $checklistPresent = $checklistTotal - count($missingTypes);
    $completionPercent = $checklistTotal > 0 ? (int)round(($checklistPresent / $checklistTotal) * 100) : 100;

    echo json_encode([
        'success' => true,
        'documents' => $documents,
        'checklist' => [
            'required_types' => $requiredTypes,
            'missing_types' => $missingTypes,
            'present_count' => $checklistPresent,
            'total_count' => $checklistTotal,
            'completion_percent' => $completionPercent
        ]
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

function formatFileSize($bytes)
{
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    }
    if ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    }
    if ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    }
    return $bytes . ' bytes';
}
