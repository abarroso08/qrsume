<?php

declare(strict_types=1);

/**
 * Turns Adobe Extract output (structuredData.json) into the QRsume resume JSON (resume.schema.json).
 *
 * Resumes have no fixed layout, so this works from what most of them share: a name at the top,
 * contact details that match well-known patterns, short section headings (English or Spanish),
 * and entries that start with a bold line or a date range.
 */
final class ResumeExtractor
{
    /** Normalized heading texts (lowercase, no accents) for each known section. */
    private const SECTION_HEADINGS = [
        'summary' => [
            'summary', 'professional summary', 'career summary', 'profile', 'professional profile', 'personal profile',
            'about', 'about me', 'objective', 'career objective', 'perfil', 'perfil profesional', 'sobre mi',
            'acerca de mi', 'resumen', 'resumen profesional', 'extracto', 'objetivo', 'objetivo profesional',
        ],
        'education' => [
            'education', 'academic background', 'academic history', 'academic qualifications', 'qualifications',
            'studies', 'educacion', 'formacion', 'formacion academica', 'estudios', 'titulacion', 'titulaciones',
            'datos academicos',
        ],
        'experience' => [
            'experience', 'work experience', 'professional experience', 'relevant experience', 'employment',
            'employment history', 'work history', 'career history', 'experiencia', 'experiencia laboral',
            'experiencia profesional', 'trayectoria profesional', 'historial laboral',
        ],
        'skills' => [
            'skills', 'technical skills', 'key skills', 'core skills', 'hard skills', 'soft skills', 'competencies',
            'core competencies', 'abilities', 'technologies', 'tech stack', 'tools', 'aptitudes', 'habilidades',
            'competencias', 'conocimientos', 'habilidades tecnicas', 'tecnologias', 'herramientas',
        ],
        'languages' => ['languages', 'language skills', 'languages spoken', 'idiomas', 'lenguas'],
        'projects' => [
            'projects', 'personal projects', 'side projects', 'academic projects', 'portfolio', 'interests',
            'proyectos', 'proyectos personales', 'proyectos academicos', 'intereses', 'portafolio',
        ],
        // Recognized as headings, imported as custom sections
        'custom' => [
            'certifications', 'certificates', 'licenses', 'courses', 'training', 'awards', 'honors', 'achievements',
            'volunteering', 'volunteer experience', 'publications', 'references', 'hobbies', 'activities',
            'extracurricular activities', 'additional information', 'other', 'certificaciones', 'certificados',
            'cursos', 'formacion complementaria', 'premios', 'logros', 'voluntariado', 'publicaciones',
            'referencias', 'aficiones', 'actividades', 'informacion adicional', 'otros', 'otros datos',
        ],
    ];

    private const LANGUAGE_LEVELS = 'native|mother tongue|bilingual|fluent|proficient|advanced|upper[- ]intermediate|'
        . 'intermediate|lower[- ]intermediate|conversational|basic|beginner|elementary|'
        . '(?:limited|professional|full professional) working proficiency|nativo|nativa|lengua materna|'
        . 'biling[uü]e|fluido|fluida|avanzado|avanzada|intermedio|intermedia|b[aá]sico|b[aá]sica|principiante|'
        . 'alto|medio|bajo|elemental';

    private const BULLETS = '•·▪▫◦‣∙●○■□►▸➢➤✓✔-–—*';

    /** @var array<int, array{type: string, path: string, lines: list<array{text: string, bold: bool}>, bold: bool, size: float, bullet: bool}> */
    private array $blocks = [];
    private float $bodySize = 10.0;

    public static function emptyResume(): array
    {
        return [
            'personal_info' => ['personal_name' => '', 'personal_lastname' => '', 'personal_profession' => '', 'personal_bio' => ''],
            'contact_info' => ['email' => '', 'phone_number' => '', 'github' => '', 'linkedin' => '', 'twitter' => '', 'facebook' => ''],
            'education' => [],
            'experience' => [],
            'skills' => [],
            'languages' => [],
            'projects' => [],
            'custom_sections' => [],
        ];
    }

    public function extract(array $structuredData): array
    {
        $this->blocks = $this->buildBlocks($structuredData['elements'] ?? []);
        $this->bodySize = $this->medianBodySize();

        $resume = self::emptyResume();
        $resume['contact_info'] = $this->extractContact();

        [$header, $sections] = $this->splitSections();
        $this->readHeader($resume, $header);

        foreach ($sections as [$type, $title, $blocks]) {
            switch ($type) {
                case 'summary':
                    $bio = $this->prose($blocks);
                    $resume['personal_info']['personal_bio'] = trim($resume['personal_info']['personal_bio'] . "\n" . $bio);
                    break;
                case 'education':
                    foreach ($this->parseEntries($blocks, false) as [$title1, $title2, $date, $description]) {
                        $resume['education'][] = [
                            'name_of_studies' => $title1,
                            'place_of_study' => $title2,
                            'date' => $date,
                            'brief_description' => $description,
                        ];
                    }
                    break;
                case 'experience':
                    foreach ($this->parseEntries($blocks, true) as [$title1, $title2, $date, $description]) {
                        $resume['experience'][] = [
                            'job_name' => $title1,
                            'place_of_work' => $title2,
                            'date' => $date,
                            'brief_description' => $description,
                        ];
                    }
                    break;
                case 'skills':
                    foreach ($this->listItems($blocks, true) as $item) {
                        $resume['skills'][] = ['aptitude' => $item];
                    }
                    break;
                case 'languages':
                    foreach ($this->listItems($blocks, false) as $item) {
                        $resume['languages'][] = $this->parseLanguage($item);
                    }
                    break;
                case 'projects':
                    $resume['projects'] = array_merge($resume['projects'], $this->parseProjects($blocks));
                    break;
                default:
                    $resume['custom_sections'][] = [
                        'section_title' => $title,
                        'section_content' => $this->content($blocks),
                    ];
            }
        }

        return $resume;
    }

    // -------------------------------------------------------------------------
    // Blocks
    // -------------------------------------------------------------------------

    /**
     * Group Adobe elements into text blocks. A paragraph can come split into several elements:
     * "Sub" elements are its visual lines, "StyleSpan"/"ParagraphSpan" are runs with other styling.
     */
    private function buildBlocks(array $elements): array
    {
        $blocks = [];

        foreach ($elements as $element) {
            $text = $element['Text'] ?? null;
            if (!is_string($text)) {
                continue;
            }
            $text = trim(preg_replace('/[^\S\n]+/u', ' ', $text) ?? $text);
            if ($text === '') {
                continue;
            }

            $path = (string) ($element['Path'] ?? '');
            $isLine = (bool) preg_match('#/Sub(\[\d+\])?$#', $path);
            $blockPath = (string) preg_replace('#/(Sub|StyleSpan|ParagraphSpan)(\[\d+\])?#', '', $path);
            $segments = explode('/', trim($blockPath, '/'));
            $type = (string) preg_replace('/\[\d+\]$/', '', end($segments) ?: '');

            if ($type === 'Lbl') {
                continue; // the bullet glyph of a list item
            }

            $bold = $this->isBold($element['Font'] ?? []);
            $size = (float) ($element['TextSize'] ?? 0);
            $last = array_key_last($blocks);

            if ($last !== null && $blocks[$last]['path'] === $blockPath) {
                if ($isLine) {
                    $blocks[$last]['lines'][] = ['text' => $text, 'bold' => $bold];
                } else {
                    $lastLine = array_key_last($blocks[$last]['lines']);
                    $blocks[$last]['lines'][$lastLine]['text'] .= ' ' . $text;
                }
                $blocks[$last]['size'] = max($blocks[$last]['size'], $size);
                continue;
            }

            $lines = [];
            foreach (preg_split('/\n+/', $text) as $lineText) {
                $lines[] = ['text' => trim($lineText), 'bold' => $bold];
            }

            $blocks[] = [
                'type' => $type,
                'path' => $blockPath,
                'lines' => $lines,
                'bold' => $bold,
                'size' => $size,
                'bullet' => str_contains($blockPath, '/LBody') || str_contains($blockPath, '/LI'),
            ];
        }

        return $blocks;
    }

    private function isBold(mixed $font): bool
    {
        if (!is_array($font)) {
            return false;
        }

        return (int) ($font['weight'] ?? 400) >= 600
            || (bool) preg_match('/bold|black|heavy|semibold|demi/i', (string) ($font['name'] ?? ''));
    }

    private function medianBodySize(): float
    {
        $sizes = [];
        foreach ($this->blocks as $block) {
            if ($block['size'] > 0 && !$this->isHeadingType($block['type'])) {
                $sizes[] = $block['size'];
            }
        }
        if ($sizes === []) {
            return 10.0;
        }

        sort($sizes);
        return $sizes[intdiv(count($sizes), 2)];
    }

    private function blockText(array $block): string
    {
        return trim(implode(' ', array_column($block['lines'], 'text')));
    }

    private function isHeadingType(string $type): bool
    {
        return (bool) preg_match('/^(H\d?|Title)$/', $type);
    }

    // -------------------------------------------------------------------------
    // Contact details
    // -------------------------------------------------------------------------

    /**
     * Find contact details anywhere in the document and take them out of the text,
     * dropping blocks that held nothing else (e.g. "Email: ... | Phone: ...").
     */
    private function extractContact(): array
    {
        $contact = self::emptyResume()['contact_info'];

        $patterns = [
            'email' => '/[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}/i',
            'linkedin' => '#(?:https?://)?(?:[a-z]{2,3}\.)?linkedin\.com/in/[A-Za-z0-9_%\-]+/?#i',
            'github' => '#(?:https?://)?(?:www\.)?github\.com/[A-Za-z0-9\-]+/?(?![A-Za-z0-9/\-])#i',
            'twitter' => '#(?:https?://)?(?:www\.)?(?:twitter|x)\.com/[A-Za-z0-9_]+/?(?![A-Za-z0-9/_])#i',
            'facebook' => '#(?:https?://)?(?:www\.)?facebook\.com/[A-Za-z0-9.\-]+/?#i',
            // 9 to 15 digits, optional +country code and separators; dates like "2018 - 2022" have too few digits
            'phone_number' => '/(?<![\w+(])(?:\(?\+\d{1,3}\)?[\s.\-]?)?(?:\(\d{1,4}\)[\s.\-]?)?\d{2,4}(?:[\s.\-]?\d{2,4}){2,4}(?![\w])/',
            // QRsume's own profile link and QR label (PDFs made here): not resume content
            'branding' => '#(?:https?://)?(?:www\.)?qrsume\.com/[A-Za-z0-9_.\-]*(?:\?\S*)?|Full Profile & Projects|Perfil Completo y Proyectos|Created with QRsume#iu',
        ];

        foreach ($this->blocks as $index => $block) {
            $changed = false;

            foreach ($block['lines'] as $lineIndex => $line) {
                $text = $line['text'];

                foreach ($patterns as $field => $pattern) {
                    if (!preg_match_all($pattern, $text, $matches)) {
                        continue;
                    }
                    foreach ($matches[0] as $match) {
                        if ($field === 'phone_number' && !$this->isPhoneNumber($match)) {
                            continue;
                        }
                        if ($field !== 'branding' && $contact[$field] === '') {
                            $contact[$field] = $this->normalizeContact($field, $match);
                        }
                        $text = str_replace($match, ' ', $text);
                        $changed = true;
                    }
                }

                $block['lines'][$lineIndex]['text'] = trim(preg_replace('/\s+/', ' ', $text) ?? $text);
            }

            if (!$changed) {
                continue;
            }

            // Drop lines that are only labels and separators now
            $block['lines'] = array_values(array_filter($block['lines'], fn(array $line) => !$this->isContactLeftover($line['text'])));
            if ($block['lines'] === []) {
                unset($this->blocks[$index]);
            } else {
                $this->blocks[$index] = $block;
            }
        }

        $this->blocks = array_values($this->blocks);

        return $contact;
    }

    private function isPhoneNumber(string $candidate): bool
    {
        $digits = strlen((string) preg_replace('/\D/', '', $candidate));
        return $digits >= 9 && $digits <= 15;
    }

    private function normalizeContact(string $field, string $value): string
    {
        $value = rtrim(trim($value), '/');
        if ($field === 'email' || $field === 'phone_number') {
            return $value;
        }

        return preg_match('#^https?://#i', $value) ? $value : 'https://' . $value;
    }

    private function isContactLeftover(string $text): bool
    {
        $labels = 'e-?mail|correo(?: electr[oó]nico)?|phone|tel[eé]fono|tel|m[oó]vil|mobile|cell|linkedin|github|twitter|facebook|contact|contacto';

        return (bool) preg_match('/^(?:[\s|•·,;:\/–—\-]|\b(?:' . $labels . ')\b)*$/iu', $text);
    }

    // -------------------------------------------------------------------------
    // Sections
    // -------------------------------------------------------------------------

    /**
     * @return array{0: list<array>, 1: list<array{0: string, 1: string, 2: list<array>}>}
     */
    private function splitSections(): array
    {
        $headings = [];
        $knownStyles = [];

        // Pass 1: headings with a known name
        foreach ($this->blocks as $index => $block) {
            $type = $this->knownSection($block);
            if ($type !== null && $this->looksLikeKnownHeading($block)) {
                $headings[$index] = $type;
                $knownStyles[] = $block;
            }
        }

        // Pass 2: other headings styled like the known ones become custom sections
        $firstHeading = $headings === [] ? 1 : min(array_keys($headings));
        foreach ($this->blocks as $index => $block) {
            if ($index < $firstHeading || isset($headings[$index])) {
                continue;
            }
            if ($this->looksLikeOtherHeading($block, $knownStyles)) {
                $headings[$index] = 'custom';
            }
        }

        ksort($headings);

        $header = [];
        $sections = [];
        $current = null;

        foreach ($this->blocks as $index => $block) {
            if (isset($headings[$index])) {
                if ($current !== null) {
                    $sections[] = $current;
                }
                $current = [$headings[$index], $this->headingTitle($block), []];
                continue;
            }

            if ($current === null) {
                $header[] = $block;
            } else {
                $current[2][] = $block;
            }
        }
        if ($current !== null) {
            $sections[] = $current;
        }

        return [$header, $sections];
    }

    private function knownSection(array $block): ?string
    {
        $text = $this->normalize($this->blockText($block));
        if ($text === '' || $this->wordCount($text) > 5) {
            return null;
        }

        // Exact names first, then names followed by a few words ("skills & tools")
        foreach ([true, false] as $exact) {
            foreach (self::SECTION_HEADINGS as $type => $names) {
                foreach ($names as $name) {
                    if ($text === $name || (!$exact && str_starts_with($text, $name . ' ') && $this->wordCount($text) <= 4)) {
                        return $type;
                    }
                }
            }
        }

        return null;
    }

    private function looksLikeKnownHeading(array $block): bool
    {
        $text = $this->blockText($block);

        return !$block['bullet']
            && count($block['lines']) === 1
            && !preg_match('/[.,;]$/', $text)
            && ($this->isHeadingType($block['type'])
                || $block['bold']
                || $this->isUpperCase($text)
                || $block['size'] > $this->bodySize * 1.1
                || $this->wordCount($text) <= 3);
    }

    private function looksLikeOtherHeading(array $block, array $knownStyles): bool
    {
        $text = $this->blockText($block);
        if ($block['bullet'] || count($block['lines']) !== 1 || $this->wordCount($text) > 5 || mb_strlen($text) > 45
            || preg_match('/[.,;:@]$|\d{4}/', $text)) {
            return false;
        }

        if ($knownStyles === []) {
            return in_array($block['type'], ['H1', 'H2', 'H3'], true) || ($block['bold'] && $this->isUpperCase($text));
        }

        foreach ($knownStyles as $known) {
            $sameTag = $this->isHeadingType($known['type']) && $known['type'] === $block['type'];
            $sameLook = $known['bold'] === $block['bold']
                && abs($known['size'] - $block['size']) < 0.6
                && $this->isUpperCase($this->blockText($known)) === $this->isUpperCase($text);
            if ($sameTag || $sameLook) {
                return true;
            }
        }

        return false;
    }

    private function headingTitle(array $block): string
    {
        $title = rtrim($this->blockText($block), ': ');
        return $this->isUpperCase($title) ? $this->titleCase($title) : $title;
    }

    // -------------------------------------------------------------------------
    // Header: name, headline, bio
    // -------------------------------------------------------------------------

    private function readHeader(array &$resume, array $header): void
    {
        if ($header === []) {
            return;
        }

        $nameIndex = null;
        foreach ($header as $index => $block) {
            if ($block['type'] === 'Title' && $this->isName($this->blockText($block))) {
                $nameIndex = $index;
                break;
            }
        }
        if ($nameIndex === null) {
            // The largest text near the top
            $largest = 0.0;
            foreach (array_slice($header, 0, 4, true) as $index => $block) {
                if ($this->isName($this->blockText($block)) && $block['size'] > $largest) {
                    $largest = $block['size'];
                    $nameIndex = $index;
                }
            }
        }

        if ($nameIndex !== null) {
            $name = $this->blockText($header[$nameIndex]);
            if ($this->isUpperCase($name)) {
                $name = $this->titleCase($name);
            }
            $parts = preg_split('/\s+/u', $name);
            $resume['personal_info']['personal_name'] = array_shift($parts);
            $resume['personal_info']['personal_lastname'] = implode(' ', $parts);
        }

        $bio = [];
        foreach ($header as $index => $block) {
            if ($index === $nameIndex) {
                continue;
            }

            $text = $this->blockText($block);
            $isHeadline = $resume['personal_info']['personal_profession'] === ''
                && $nameIndex !== null && $index === $nameIndex + 1
                && $this->wordCount($text) <= 10 && !preg_match('/[.]$|[|@]|https?:|www\./', $text);

            if ($isHeadline) {
                $resume['personal_info']['personal_profession'] = $text;
            } elseif ($this->wordCount($text) >= 6) {
                // Longer text above the first section is the summary; short leftovers (a city) are skipped
                $bio[] = $text;
            }
        }

        $resume['personal_info']['personal_bio'] = implode("\n", $bio);
    }

    private function isName(string $text): bool
    {
        $words = $this->wordCount($text);
        return $words >= 1 && $words <= 5 && !preg_match('/[\d@:|]/', $text) && $this->knownSection(['lines' => [['text' => $text]]]) === null;
    }

    // -------------------------------------------------------------------------
    // Entries (education, experience)
    // -------------------------------------------------------------------------

    /**
     * An entry starts with title lines (bold, or holding a date range) followed by description lines.
     *
     * @return list<array{0: string, 1: string, 2: string, 3: string}> [title, place, date, description]
     */
    private function parseEntries(array $blocks, bool $isExperience): array
    {
        $entries = [];
        $current = null;

        foreach ($blocks as $blockIndex => $block) {
            foreach ($block['lines'] as $line) {
                $isBullet = $block['bullet'] || $this->startsWithBullet($line['text']);
                $text = $this->stripBullet($line['text']);
                if ($text === '') {
                    continue;
                }

                [$date, $rest] = $isBullet ? ['', $text] : $this->findDate($text);
                $words = $this->wordCount($rest);
                $isTitle = !$isBullet && ($date !== '' || ($line['bold'] && $words <= 15));

                if ($isTitle) {
                    $startsNew = $current === null
                        || $current['description'] !== []
                        || ($date !== '' && $current['date'] !== '')
                        || ($date === '' && $current['date'] !== '' && count($current['titles']) >= ($current['dateFirst'] ? 2 : 1));

                    if ($startsNew) {
                        if ($current !== null) {
                            $entries[] = $current;
                        }
                        $current = ['titles' => [], 'date' => '', 'dateFirst' => false, 'description' => []];
                    }
                    if ($date !== '') {
                        $current['dateFirst'] = $current['titles'] === [];
                        $current['date'] = $date;
                    }
                    if ($rest !== '') {
                        $current['titles'][] = $rest;
                    }
                    continue;
                }

                // A short plain line right under a single title: the school or company
                if ($current !== null && $current['description'] === [] && count($current['titles']) === 1
                    && !$isBullet && $words <= 10 && !preg_match('/[.:]$/', $text)) {
                    $current['titles'][] = $text;
                    continue;
                }

                if ($current === null) {
                    $current = ['titles' => [], 'date' => '', 'dateFirst' => false, 'description' => []];
                }
                $this->addParagraph($current['description'], $text, $isBullet, $blockIndex);
            }
        }
        if ($current !== null) {
            $entries[] = $current;
        }

        $result = [];
        foreach ($entries as $entry) {
            $titles = $entry['titles'];
            if (count($titles) <= 1) {
                [$title, $place] = $this->splitTitle($titles[0] ?? '', $isExperience);
            } else {
                $title = array_shift($titles);
                $place = implode(', ', $titles);
            }

            $result[] = [$title, $place, $entry['date'], $this->paragraphsToText($entry['description'])];
        }

        return $result;
    }

    /**
     * Find a date or date range ("Jan 2020 - Present", "2018–2022", "09/2019 - 06/2021", "2026 (4 months)").
     * Only lines that are short apart from the date count, so years inside sentences are left alone.
     *
     * @return array{0: string, 1: string} [date, rest of the line]
     */
    private function findDate(string $text): array
    {
        $month = '(?:jan(?:uary)?|feb(?:ruary)?|mar(?:ch)?|apr(?:il)?|may|june?|july?|aug(?:ust)?|sept?(?:ember)?|oct(?:ober)?'
            . '|nov(?:ember)?|dec(?:ember)?|ene(?:ro)?|febrero|marzo|abr(?:il)?|mayo|junio|julio|ago(?:sto)?|septiembre|setiembre'
            . '|octubre|noviembre|dic(?:iembre)?)\.?';
        $point = "(?:(?:{$month}\\s*(?:de\\s+)?)?(?:\\d{1,2}\\s*/\\s*)?(?:19|20)\\d{2})";
        $now = '(?:present|current|now|today|ongoing|actualidad|la actualidad|presente|actual|hoy)';
        $duration = '(?:\s*\(\s*\d+\s*(?:months?|years?|yrs?|mos?|meses|mes|años|año)\s*\))?';
        $range = "(?:(?:{$point}|{$month})\\s*(?:-|–|—|to|a|hasta|until)\\s*(?:{$point}|{$now})|{$point}){$duration}";

        if (!preg_match("~(?<![\\p{L}\\d]){$range}(?![\\p{L}\\d])~iu", $text, $match)) {
            return ['', $text];
        }

        $rest = trim(str_replace($match[0], ' ', $text));
        $rest = trim((string) preg_replace('/^[\s|,·•–—\-()]+|[\s|,·•–—\-(]+$/u', '', $rest));

        if ($this->wordCount($rest) > 12) {
            return ['', $text];
        }

        return [trim($match[0]), $rest];
    }

    /**
     * "Software Engineer - Acme Corp" -> ["Software Engineer", "Acme Corp"]
     *
     * @return array{0: string, 1: string}
     */
    private function splitTitle(string $title, bool $isExperience): array
    {
        $separators = [' — ', ' – ', ' - ', ' | ', ' · '];
        if ($isExperience) {
            array_push($separators, ' at ', ' @ ');
        }

        foreach ($separators as $separator) {
            $position = mb_stripos($title, $separator);
            if ($position !== false) {
                return [
                    trim(mb_substr($title, 0, $position)),
                    trim(mb_substr($title, $position + mb_strlen($separator))),
                ];
            }
        }

        return [trim($title), ''];
    }

    // -------------------------------------------------------------------------
    // Projects
    // -------------------------------------------------------------------------

    private function parseProjects(array $blocks): array
    {
        $projects = [];
        $current = null;

        foreach ($blocks as $blockIndex => $block) {
            foreach ($block['lines'] as $line) {
                $isBullet = $block['bullet'] || $this->startsWithBullet($line['text']);
                $text = $this->stripBullet($line['text']);
                if ($text === '') {
                    continue;
                }

                $isTitle = $line['bold'] && $this->wordCount($text) <= 12;
                $labelled = preg_match('/^(.{2,60}?)\s*(?::| – | — | - )\s*(.+)$/u', $text, $match)
                    && $this->wordCount($match[1]) <= 6;

                if ($isTitle) {
                    $current = $this->pushProject($projects, $current, $text, [], false);
                } elseif ($labelled && ($current === null || $current['fromList'])) {
                    // "QRsume: online resumes with QR codes"
                    $current = $this->pushProject($projects, $current, trim($match[1]), [], true);
                    $this->addParagraph($current['description'], trim($match[2]), false, $blockIndex);
                } elseif ($current !== null) {
                    $this->addParagraph($current['description'], $text, $isBullet, $blockIndex);
                } elseif ($this->wordCount($text) <= 10) {
                    $current = $this->pushProject($projects, $current, $text, [], true);
                } else {
                    $current = $this->pushProject($projects, $current, '', [], true);
                    $this->addParagraph($current['description'], $text, $isBullet, $blockIndex);
                }
            }
        }
        if ($current !== null) {
            $projects[] = $current;
        }

        return array_map(fn(array $project) => [
            'interest' => $project['interest'],
            'description' => $this->paragraphsToText($project['description']),
        ], $projects);
    }

    private function pushProject(array &$projects, ?array $current, string $title, array $description, bool $fromList): array
    {
        if ($current !== null) {
            $projects[] = $current;
        }

        return ['interest' => $title, 'description' => $description, 'fromList' => $fromList];
    }

    // -------------------------------------------------------------------------
    // Lists (skills, languages)
    // -------------------------------------------------------------------------

    /**
     * Split list sections into items: bullets, commas, semicolons, pipes; "Frontend: React, Vue" keeps the items.
     *
     * @return list<string>
     */
    private function listItems(array $blocks, bool $dropLabels): array
    {
        $items = [];
        $seen = [];

        foreach ($blocks as $block) {
            foreach ($block['lines'] as $line) {
                $text = $this->stripBullet($line['text']);

                if ($dropLabels && preg_match('/^([^:]{2,40}):\s*(.+)$/u', $text, $match) && $this->wordCount($match[1]) <= 4) {
                    $text = $match[2];
                }

                foreach ($this->splitOutsideParentheses($text, [',', ';', '|', '•', '·', '▪', '●']) as $item) {
                    $item = trim($this->stripBullet($item), " \t.");
                    $key = mb_strtolower($item);
                    if ($item === '' || isset($seen[$key])) {
                        continue;
                    }
                    $seen[$key] = true;
                    $items[] = $item;
                }
            }
        }

        return $items;
    }

    /**
     * @return list<string>
     */
    private function splitOutsideParentheses(string $text, array $separators): array
    {
        $parts = [];
        $buffer = '';
        $depth = 0;

        foreach (mb_str_split($text) as $char) {
            if ($char === '(' || $char === '[') {
                $depth++;
            } elseif (($char === ')' || $char === ']') && $depth > 0) {
                $depth--;
            }

            if ($depth === 0 && in_array($char, $separators, true)) {
                $parts[] = $buffer;
                $buffer = '';
            } else {
                $buffer .= $char;
            }
        }
        $parts[] = $buffer;

        return $parts;
    }

    /**
     * "English (C1)", "English - Native", "Spanish: native", "Inglés B2", "German" -> language + level
     */
    private function parseLanguage(string $item): array
    {
        $levels = self::LANGUAGE_LEVELS;
        $patterns = [
            '/^(.+?)\s*\((.+)\)$/u',
            '/^(.+?)\s*(?::|\s[–—\-]\s)\s*(.+)$/u',
            '/^(.+?)\s+([ABC][12](?:\b.*)?)$/iu',
            "/^(.+?)\\s+((?:{$levels})\\b.*)$/iu",
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $item, $match)) {
                $level = trim($match[2]);
                if (preg_match('/^[abc][12]$/i', $level)) {
                    $level = strtoupper($level);
                }

                return ['language' => trim($match[1]), 'level' => $level];
            }
        }

        return ['language' => $item, 'level' => ''];
    }

    // -------------------------------------------------------------------------
    // Text helpers
    // -------------------------------------------------------------------------

    /**
     * Paragraph text for prose (bio): the visual lines of one paragraph are joined back together.
     */
    private function prose(array $blocks): string
    {
        return implode("\n", array_map(fn(array $block) => $this->stripBullet($this->blockText($block)), $blocks));
    }

    /**
     * Custom section content: one line per paragraph or bullet.
     */
    private function content(array $blocks): string
    {
        $paragraphs = [];
        foreach ($blocks as $blockIndex => $block) {
            foreach ($block['lines'] as $line) {
                $isBullet = $block['bullet'] || $this->startsWithBullet($line['text']);
                $text = $this->stripBullet($line['text']);
                if ($text !== '') {
                    $this->addParagraph($paragraphs, $text, $isBullet, $blockIndex);
                }
            }
        }

        return $this->paragraphsToText($paragraphs);
    }

    /**
     * Wrapped lines of the same paragraph are joined with a space; a bullet always starts a new line.
     */
    private function addParagraph(array &$paragraphs, string $text, bool $isBullet, int $blockIndex): void
    {
        $last = array_key_last($paragraphs);
        if ($last !== null && !$isBullet && $paragraphs[$last]['block'] === $blockIndex) {
            $paragraphs[$last]['text'] .= ' ' . $text;
            return;
        }

        $paragraphs[] = ['text' => $text, 'bullet' => $isBullet, 'block' => $blockIndex];
    }

    private function paragraphsToText(array $paragraphs): string
    {
        return implode("\n", array_map(fn(array $p) => ($p['bullet'] ? '• ' : '') . $p['text'], $paragraphs));
    }

    private function startsWithBullet(string $text): bool
    {
        return (bool) preg_match('/^[' . preg_quote(self::BULLETS, '/') . ']\s/u', $text);
    }

    private function stripBullet(string $text): string
    {
        return trim((string) preg_replace('/^[' . preg_quote(self::BULLETS, '/') . ']+\s*/u', '', trim($text)));
    }

    private function normalize(string $text): string
    {
        $text = mb_strtolower(trim($text));
        if (class_exists('Normalizer')) {
            $text = (string) preg_replace('/\p{Mn}+/u', '', (string) Normalizer::normalize($text, Normalizer::FORM_D));
        }
        $text = (string) preg_replace('/[^\p{L}\p{N}]+/u', ' ', $text);

        return trim($text);
    }

    private function wordCount(string $text): int
    {
        return count(preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY));
    }

    private function isUpperCase(string $text): bool
    {
        return preg_match('/\p{L}/u', $text) === 1 && mb_strtoupper($text) === $text;
    }

    private function titleCase(string $text): string
    {
        return mb_convert_case(mb_strtolower($text), MB_CASE_TITLE);
    }
}
