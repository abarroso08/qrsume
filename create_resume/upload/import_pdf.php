<?php

declare(strict_types=1);

/**
 * Fill the create_resume form from a CV in PDF (one page).
 * The PDF content replaces the form's content; the user then checks each step and saves it as usual.
 */

require_once __DIR__ . '/../../assets/db.php';
require_once __DIR__ . '/../../PDFtoCV/lib/PdfResumeImport.php';

$formUrl = '/create_resume/form_with_login.php';

if (!isset($_SESSION['admin_logged_in'], $_SESSION['id']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: /assets/login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $formUrl);
    exit();
}

$userId = (int) $_SESSION['id'];
$isAdmin = ($_SESSION['privilege'] ?? '') === 'admin';
$import = new PdfResumeImport($db);
$sectionNames = [
    'education' => 'education entries',
    'experience' => 'work experience entries',
    'skills' => 'aptitudes',
    'languages' => 'languages',
    'projects' => 'projects',
    'custom_sections' => 'custom sections',
];

try {
    if (!hash_equals((string) ($_SESSION['csrf_token'] ?? ''), (string) ($_POST['csrf_token'] ?? ''))) {
        // A missing token usually means the upload was bigger than PHP accepts
        PdfResumeImport::validateUpload($_FILES['cv_pdf'] ?? null);
        throw new PdfToCvError('Your session expired. Reload the page and try again.', 403);
    }

    $pdfPath = PdfResumeImport::validateUpload($_FILES['cv_pdf'] ?? null);
    $importId = $import->reserve($userId, $isAdmin);

    try {
        $resume = $import->read($pdfPath);
        $leftOut = $import->saveToProfile($userId, $resume);
        $import->complete($importId);
    } catch (Throwable $exception) {
        $import->release($importId); // a failed import does not use the allowance
        throw $exception;
    }

    $notes = [];
    foreach ($leftOut as $list => $count) {
        $notes[] = 'The form keeps up to ' . PdfResumeImport::LIST_LIMITS[$list] . ' ' . $sectionNames[$list]
            . ", so {$count} from your PDF " . ($count === 1 ? 'was' : 'were') . ' left out.';
    }

    $_SESSION['pdf_import_flash'] = [
        'type' => 'success',
        'message' => 'Your CV has been imported. Go through each step, fix anything that is wrong or missing, and save it.',
        'notes' => $notes,
    ];
} catch (PdfToCvError | AdobePdfExtractException $exception) {
    $_SESSION['pdf_import_flash'] = ['type' => 'danger', 'message' => $exception->getMessage(), 'notes' => []];
} catch (Throwable $exception) {
    error_log('PDF import: ' . $exception->getMessage());
    $_SESSION['pdf_import_flash'] = ['type' => 'danger', 'message' => 'Something went wrong while importing your CV. Please try again.', 'notes' => []];
}

header('Location: ' . $formUrl . '#pdf-import');
exit();
