<?php

declare(strict_types=1);

require_once __DIR__ . '/AdobePdfExtract.php';
require_once __DIR__ . '/ResumeExtractor.php';
require_once __DIR__ . '/../../assets/premium.php';

final class PdfToCvError extends RuntimeException
{
}

/**
 * Importing a resume PDF into a user's profile:
 * - checks the upload (a one-page PDF),
 * - reads it with Adobe PDF Services and maps it to the resume structure,
 * - saves it into the tables the create_resume form edits,
 * - enforces the per-account allowance: 1 free import, 5 more with premium (branding removal).
 *
 * Needs assets/db.php loaded (cleanInput, env).
 */
final class PdfResumeImport
{
    public const MAX_BYTES = 10 * 1024 * 1024; // lowered to PHP's upload limits if those are smaller
    public const FREE_IMPORTS = 1;
    public const PREMIUM_EXTRA_IMPORTS = 5;
    public const PREMIUM_PRODUCT = 'remove_qrsume_branding';
    public const ONE_PAGE_MESSAGE = 'Please upload a one-page PDF. Only single-page CVs can be imported.';

    /** Same limits as create_resume/form_with_login.php */
    public const LIST_LIMITS = [
        'education' => 5,
        'experience' => 5,
        'skills' => 10,
        'languages' => 5,
        'projects' => 10,
        'custom_sections' => 5,
    ];

    /** An import that never finished (e.g. the request died) stops counting after this. */
    private const PENDING_MINUTES = 10;

    /** Sample values register.php puts in a new account */
    private const PLACEHOLDERS = [
        'personal_name' => 'Your Name',
        'personal_lastname' => 'Your Last Name',
        'personal_profession' => 'Your Profession',
        'personal_bio' => 'This is a short bio about yourself.',
        'phone_number' => '123-456-7890',
        'email' => 'youremail@gmail.com',
        'github' => 'https://github.com/yourusername',
        'linkedin' => 'https://linkedin.com/in/yourusername',
        'twitter' => 'https://twitter.com/yourusername',
    ];

    public function __construct(private PDO $db)
    {
    }

    // -------------------------------------------------------------------------
    // Allowance
    // -------------------------------------------------------------------------

    /**
     * @return array{used: int, allowed: int, remaining: int, premium: bool, unlimited: bool}
     */
    public function allowance(int $userId, bool $isAdmin = false): array
    {
        $premium = userHasPurchase($this->db, $userId, self::PREMIUM_PRODUCT);
        $allowed = self::FREE_IMPORTS + ($premium ? self::PREMIUM_EXTRA_IMPORTS : 0);
        $used = $this->countImports($userId);

        return [
            'used' => $used,
            'allowed' => $allowed,
            'remaining' => $isAdmin ? PHP_INT_MAX : max(0, $allowed - $used),
            'premium' => $premium,
            'unlimited' => $isAdmin,
        ];
    }

    /**
     * Take one import from the allowance before calling Adobe, so two requests at once cannot both use the last one.
     * Call complete() when the import worked, release() when it did not (failed imports are not counted).
     */
    public function reserve(int $userId, bool $isAdmin = false): int
    {
        $allowance = $this->allowance($userId, $isAdmin);
        if ($allowance['remaining'] <= 0) {
            throw new PdfToCvError($allowance['premium']
                ? 'You have used all your CV imports.'
                : 'You have used your free CV import. Go premium to get ' . self::PREMIUM_EXTRA_IMPORTS . ' more.', 403);
        }

        $stmt = $this->db->prepare("INSERT INTO pdf_imports (user_id, status, created_at) VALUES (?, 'pending', ?)");
        $stmt->execute([$userId, date('Y-m-d H:i:s')]);

        return (int) $this->db->lastInsertId();
    }

    public function complete(int $importId): void
    {
        $this->db->prepare("UPDATE pdf_imports SET status = 'done' WHERE id = ?")->execute([$importId]);
    }

    public function release(int $importId): void
    {
        $this->db->prepare('DELETE FROM pdf_imports WHERE id = ?')->execute([$importId]);
    }

    private function countImports(int $userId): int
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM pdf_imports WHERE user_id = ? AND (status = 'done' OR created_at > ?)");
        $stmt->execute([$userId, date('Y-m-d H:i:s', time() - self::PENDING_MINUTES * 60)]);

        return (int) $stmt->fetchColumn();
    }

    // -------------------------------------------------------------------------
    // Upload and reading
    // -------------------------------------------------------------------------

    /**
     * Check the uploaded file and return its temporary path.
     */
    public static function validateUpload(?array $upload): string
    {
        // Above post_max_size PHP drops the whole request body
        if ($upload === null && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > self::maxUploadBytes()) {
            throw new PdfToCvError('The PDF is too large. The limit is ' . self::formatMegabytes(self::maxUploadBytes()) . '.', 413);
        }

        $error = $upload['error'] ?? UPLOAD_ERR_NO_FILE;
        if ($error === UPLOAD_ERR_NO_FILE) {
            throw new PdfToCvError('Choose a PDF file first.', 400);
        }
        if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true) || ($upload['size'] ?? 0) > self::maxUploadBytes()) {
            throw new PdfToCvError('The PDF is too large. The limit is ' . self::formatMegabytes(self::maxUploadBytes()) . '.', 413);
        }
        if ($error !== UPLOAD_ERR_OK || !is_uploaded_file($upload['tmp_name'])) {
            throw new PdfToCvError('The upload failed. Please try again.', 400);
        }

        $content = (string) file_get_contents($upload['tmp_name']);
        if (!str_starts_with($content, '%PDF-')) {
            throw new PdfToCvError('That file is not a PDF.', 415);
        }
        if (self::countPages($content) > 1) {
            throw new PdfToCvError(self::ONE_PAGE_MESSAGE, 422);
        }

        return $upload['tmp_name'];
    }

    /**
     * Number of pages of a PDF, or 0 when it cannot be told. The page tree is often inside
     * compressed object streams (PDF 1.5+, e.g. Word exports), so those are unpacked too.
     */
    public static function countPages(string $pdf): int
    {
        $sources = [$pdf];

        if (preg_match_all('/(?<!end)stream\r?\n/', $pdf, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as [$marker, $offset]) {
                $from = max(0, $offset - 300);
                $dictionary = substr($pdf, $from, $offset - $from);
                $dictionary = substr($dictionary, (int) strrpos($dictionary, 'obj'));
                if (!str_contains($dictionary, '/ObjStm')) {
                    continue;
                }

                $start = $offset + strlen($marker);
                if (preg_match('#/Length\s+(\d+)(?!\s+\d+\s+R)#', $dictionary, $length)) {
                    $data = substr($pdf, $start, (int) $length[1]);
                } else {
                    // Length given as a reference to another object: cut at endstream instead
                    $end = strpos($pdf, 'endstream', $start);
                    if ($end === false) {
                        continue;
                    }
                    $data = rtrim(substr($pdf, $start, $end - $start), "\r\n");
                }
                $inflated = @gzuncompress($data);
                if ($inflated === false) {
                    $inflated = @gzinflate($data);
                }
                if (is_string($inflated)) {
                    $sources[] = $inflated;
                }
            }
        }

        $treeCount = 0;
        $pageObjects = 0;
        foreach ($sources as $source) {
            // The root /Pages node has the largest /Count
            if (preg_match_all('#/Type\s*/Pages\b#', $source, $nodes, PREG_OFFSET_CAPTURE)) {
                foreach ($nodes[0] as [, $offset]) {
                    $around = substr($source, max(0, $offset - 200), 400);
                    if (preg_match_all('#/Count\s+(\d+)#', $around, $counts)) {
                        $treeCount = max($treeCount, ...array_map('intval', $counts[1]));
                    }
                }
            }
            $pageObjects += preg_match_all('#/Type\s*/Page(?![a-zA-Z])#', $source);
        }

        return $treeCount > 0 ? $treeCount : $pageObjects;
    }

    /**
     * Read the PDF with Adobe and map it to the resume structure (resume.schema.json).
     */
    public function read(string $pdfPath): array
    {
        $clientId = (string) env('ADOBE_PDF_SERVICES_CLIENT_ID', '');
        $clientSecret = (string) env('ADOBE_PDF_SERVICES_CLIENT_SECRET', '');
        if ($clientId === '' || $clientSecret === '') {
            throw new PdfToCvError('PDF import is not configured yet.', 503);
        }

        set_time_limit(150);
        $adobe = new AdobePdfExtract($clientId, $clientSecret, (string) env('ADOBE_PDF_SERVICES_REGION', 'us'));
        $structuredData = $adobe->extract($pdfPath);

        // Adobe knows the real page count even when the file did not show it
        if (self::extractedPageCount($structuredData) > 1) {
            throw new PdfToCvError(self::ONE_PAGE_MESSAGE, 422);
        }

        return (new ResumeExtractor())->extract($structuredData);
    }

    private static function extractedPageCount(array $structuredData): int
    {
        if (!empty($structuredData['pages']) && is_array($structuredData['pages'])) {
            return count($structuredData['pages']);
        }

        $lastPage = 0;
        foreach ($structuredData['elements'] ?? [] as $element) {
            $lastPage = max($lastPage, (int) ($element['Page'] ?? 0));
        }

        return $lastPage + 1;
    }

    public static function maxUploadBytes(): int
    {
        $limit = self::MAX_BYTES;
        foreach (['upload_max_filesize', 'post_max_size'] as $setting) {
            $value = trim((string) ini_get($setting));
            $bytes = function_exists('ini_parse_quantity')
                ? ini_parse_quantity($value)
                : (int) $value * (1024 ** (int) strpos('bkmg', strtolower(substr($value, -1)) ?: 'b'));
            if ($bytes > 0) {
                $limit = min($limit, $bytes);
            }
        }

        return $limit;
    }

    public static function formatMegabytes(int $bytes): string
    {
        return rtrim(rtrim(number_format($bytes / 1048576, 1), '0'), '.') . ' MB';
    }

    // -------------------------------------------------------------------------
    // Saving into the profile
    // -------------------------------------------------------------------------

    /**
     * Replace the resume content of the form with the imported one, in one transaction.
     * Lists are replaced (up to the form's limits); personal and contact fields keep their
     * current value when the PDF has none, except the sample values of a new account.
     *
     * @return array<string, int> entries left out per list because of the form's limits
     */
    public function saveToProfile(int $userId, array $resume): array
    {
        $personal = $resume['personal_info'];
        $contact = $resume['contact_info'];
        $leftOut = [];

        $this->db->beginTransaction();
        try {
            // Personal info (keeps photo and cv_url)
            $current = $this->fetchRow('SELECT * FROM personalinfo WHERE user_id = ?', $userId);
            $values = [];
            foreach (['personal_name', 'personal_lastname', 'personal_profession', 'personal_bio'] as $field) {
                $required = in_array($field, ['personal_name', 'personal_lastname'], true);
                $values[$field] = $this->mergeField($field, $personal[$field] ?? '', $current[$field] ?? '', $required, $field === 'personal_bio');
            }
            if ($current === null) {
                db_insert($this->db, 'personalinfo', ['user_id' => $userId] + $values);
            } else {
                db_update($this->db, 'personalinfo', $values, ['user_id' => $userId]);
            }

            // Contact info
            $current = $this->fetchRow('SELECT * FROM contactinfo WHERE user_id = ?', $userId);
            $values = [];
            foreach (['phone_number', 'email', 'github', 'linkedin', 'twitter', 'facebook'] as $field) {
                $values[$field] = $this->mergeField($field, $contact[$field] ?? '', $current[$field] ?? '', false, false);
            }
            if ($values['email'] === '') {
                $values['email'] = $this->accountEmail($userId);
            }
            if ($current === null) {
                db_insert($this->db, 'contactinfo', ['user_id' => $userId] + $values);
            } else {
                db_update($this->db, 'contactinfo', $values, ['user_id' => $userId]);
            }

            // Lists: replaced by what the PDF has
            $lists = [
                'education' => ['education', ['name_of_studies' => false, 'place_of_study' => false, 'date' => false, 'brief_description' => true]],
                'experience' => ['experience', ['job_name' => false, 'place_of_work' => false, 'date' => false, 'brief_description' => true]],
                'skills' => ['aptitudes', ['aptitude' => false]],
                'languages' => ['languages', ['language' => false, 'level' => false]],
                'projects' => ['interests', ['interest' => false, 'description' => true]],
                'custom_sections' => ['custom_sections', ['section_title' => false, 'section_content' => true]],
            ];

            foreach ($lists as $key => [$table, $fields]) {
                $this->db->prepare("DELETE FROM `$table` WHERE user_id = ?")->execute([$userId]);

                $entries = array_values($resume[$key] ?? []);
                $limit = self::LIST_LIMITS[$key];
                if (count($entries) > $limit) {
                    $leftOut[$key] = count($entries) - $limit;
                    $entries = array_slice($entries, 0, $limit);
                }

                foreach ($entries as $entry) {
                    $row = ['user_id' => $userId];
                    foreach ($fields as $field => $multiline) {
                        $row[$field] = $this->clean((string) ($entry[$field] ?? ''), $multiline);
                    }
                    if (count(array_filter(array_slice($row, 1), fn(string $value) => $value !== '')) > 0) {
                        db_insert($this->db, $table, $row);
                    }
                }
            }

            $this->db->commit();
        } catch (Throwable $exception) {
            $this->db->rollBack();
            throw $exception;
        }

        return $leftOut;
    }

    private function mergeField(string $field, string $imported, string $current, bool $required, bool $multiline): string
    {
        $imported = $this->clean($imported, $multiline);
        if ($imported !== '') {
            return $imported;
        }

        // Nothing in the PDF: drop a sample value from registration, but never leave a required field empty
        $isPlaceholder = $current === (self::PLACEHOLDERS[$field] ?? null);

        return $isPlaceholder && !$required ? '' : $current;
    }

    /**
     * Same cleaning the form applies when saving (cleanInput), after turning typographic characters into
     * ones it keeps: dashes, quotes, and accented letters it does not allow (à, ç, ö...) lose only the accent.
     */
    private function clean(string $text, bool $multiline): string
    {
        $text = strtr($text, [
            '–' => '-', '—' => '-', '−' => '-', '‐' => '-', '‑' => '-',
            '‘' => "'", '’' => "'", '“' => '"', '”' => '"', '…' => '...',
            '·' => '•', '▪' => '•', '●' => '•', "\t" => ' ', "\u{00A0}" => ' ',
        ]);

        if (class_exists('Normalizer')) {
            $text = (string) preg_replace_callback('/[^\x00-\x7FñÑáéíóúÁÉÍÓÚüÜ•]/u', static function (array $match): string {
                return (string) preg_replace('/\p{Mn}+/u', '', (string) Normalizer::normalize($match[0], Normalizer::FORM_D));
            }, $text);
        }

        if (!$multiline) {
            $text = (string) preg_replace('/\s+/u', ' ', $text);
        }

        return mb_substr(trim(cleanInput($text)), 0, $multiline ? 2000 : 255);
    }

    private function fetchRow(string $sql, int $userId): ?array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row ?: null;
    }

    private function accountEmail(int $userId): string
    {
        return (string) ($this->fetchRow('SELECT email FROM users WHERE id = ?', $userId)['email'] ?? '');
    }
}
