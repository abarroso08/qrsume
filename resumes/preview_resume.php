<?php
include("../assets/head.php");

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure resume_id is passed
if (!isset($_GET['resume_id']) || !is_numeric($_GET['resume_id'])) {
    header("Location: https://qrsume.com/error.php");
    exit();
}

$resume_id = (int) $_GET['resume_id'];
$username = $_SESSION['username'] ?? null;
$userId = $_SESSION['id'] ?? null;

// Check session
if (!$userId) {
    header("Location: https://qrsume.com/login.php");
    exit();
}

// Fetch resume
$stmt = $db->prepare("SELECT * FROM resumes WHERE resume_id = :resume_id AND user_id = :user_id");
$stmt->execute([':resume_id' => $resume_id, ':user_id' => $userId]);
$resume = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$resume) {
    header("Location: https://qrsume.com/error.php");
    exit();
}

// Parse the JSON content
$resume_data = json_decode($resume['data'], true);
if (json_last_error() !== JSON_ERROR_NONE) {
    die("Resume data is corrupted.");
}

// Personal & Contact Info
$personal = array_merge([
    'personal_name' => 'Your Name',
    'personal_lastname' => 'Last Name'
], $resume_data['personal_info'] ?? []);

$contact = array_merge([
    'email' => 'your@email.com',
    'phone_number' => '+123 456 789'
], $resume_data['contact_info'] ?? []);

// Sections
$education = $resume_data['education'] ?? [[
    'name_of_studies' => 'B.Sc. in Something',
    'date' => '2022',
    'place_of_study' => 'University Name',
    'brief_description' => 'Brief description of your academic background'
]];

$experience = $resume_data['experience'] ?? [[
    'job_name' => 'Job Title',
    'place_of_work' => 'Company Name',
    'date' => '2023',
    'brief_description' => 'Summary of responsibilities and achievements'
]];

$skills = $resume_data['skills'] ?? [[
    'aptitude' => 'Problem Solving'
]];

$languages = $resume_data['languages'] ?? [[
    'language' => 'English',
    'level' => 'Fluent'
]];

$interests = $resume_data['projects'] ?? [[
    'interest' => 'Portfolio Website',
    'description' => 'Built a personal portfolio website using HTML/CSS/JavaScript'
]];

$custom_sections = $resume_data['custom_sections'] ?? [[
    'section_title' => 'Certifications',
    'section_content' => 'Certified in Google Data Analytics, 2023'
]];

?>


  <style>
    body{
        min-width: 816px;
    }
    .resume-container {
      background: white;
      width: 816px; /* Letter width at 96dpi */
      min-height: 1056px;
      margin: auto;
      padding: 2.5rem;
      box-shadow: 0 0 10px rgba(0,0,0,0.1);
    }

    .resume-container h1 {
      font-size: 26pt;
      font-weight: bold;
      text-align: center;
      margin-bottom: 0.2rem;
    }

    .resume-container .contact {
      text-align: center;
      font-size: 11pt;
      margin-bottom: 1rem;
    }

    .section-title {
      font-size: 14pt;
      font-weight: bold;
      margin-top: 2rem;
      margin-bottom: 0.5rem;
      border-bottom: 1px solid black;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    input[type="text"], textarea {
      border: none;
      background: transparent;
      width: 100%;
      resize: none;
      padding: 0;
      margin: 0;
      height: auto;
      cursor:pointer;
      border-bottom: 1px dashed transparent;
      transition: border-color 0.2s;
    }
    
    input[type="text"]:hover {
    border-bottom: 1px dashed #999;
    outline: none;
}

    input[type="text"]:focus, textarea:focus {
      outline: none;
      border-bottom: 1px solid #ccc;
    }

    .form-select {
      font-size: 11pt;
      width: fit-content;
    }

    .submit-btn {
      margin-top: 2rem;
      text-align: center;
    }

    .row-group {
      margin-bottom: 1rem;
    }

    .job-title {
      font-weight: bold;
      display: inline-block;
      width: 70%;
    }

    .job-date {
      float: right;
      text-align: right;
      font-size: 10pt;
    }

    textarea {
      font-family: 'Times New Roman', Times, serif;
    }
    .resume-container {
  font-size: var(--font-base, 18px);
}

.resume-container .h1 {
  font-size: var(--font-name, 50px);
}

.section-title {
  font-size: var(--font-section, 27px);
}

.job-title input,
input.fw-bold {
  font-size: var(--font-title, 22px);
}

textarea,
input[type="text"] {
  font-size: var(--font-base, 18px);
}

    
  </style>
</head>
<body>
<?php include("../assets/nav_profile.php");?>

<div class="text-center my-4 mx-5">
  <h1 class="display-5 fw-bold">Preview Your Resume</h1>
  <p class="text-muted fs-5 mt-2">
    This preview allows you to see and edit your resume in real time.<br> You can modify your personal information, education, experience, and more. 
    <br>
    Choose your preferred font style and select which sections to include. <br> Once you're satisfied, click the button below to generate a professional PDF version.
  </p>
  
</div>

<form class="mb-3" action="https://qrsume.com/resumes/create.php" method="POST">
    <!-- Font selection -->
    <div class="mb-3 d-flex justify-content-around">
        <div class="d-flex align-items-center">
            <label for="font" class="form-label">Choose Font:</label>
                <select name="font" id="font" class="form-select">
                  <option value="times">Times</option>
                  <option value="helvetica">Helvetica</option>
                </select>
        </div>
        <div  class="mb-3 d-flex align-items-center">
            <label for="contentFontSize">Font Size:</label>
                <select name="contentFontSize" id="contentFontSize" class="form-select">
                  <option value="9">Small</option>
                  <option value="10" selected>Default</option>
                  <option value="11">Large</option>
                  <option value="12">Extra Large</option>
                </select>
        </div>
      <div>
        <label>Display QR to your web:</label>
        <input type="checkbox" name="show_QR" checked>
      </div>
      
    </div>
  <div class="resume-container shadow">
    

    <!-- Personal Info -->
    <div class="w-100 d-flex align-items-center justify-content-center">
        <input class="fw-bold h1 w-50 text-end mx-1" type="text" name="personal_name" value="<?= $personal['personal_name'] ?>">  
        <input class="fw-bold h1 w-50 text-start" type="text" name="personal_lastname" value="<?= $personal['personal_lastname'] ?>">
    </div>
    <div class="w-100 d-flex align-items-center justify-content-center">
        <input class="w-50 text-end" type="text" name="email" value="<?= $contact['email'] ?> "> | 
        <input class="w-50" type="text" name="phone_number" value=" <?= $contact['phone_number'] ?>"> 
    </div>

    <!-- Education -->
    <div class="section-title">
      <label>Education</label>
      <input type="checkbox" name="show_education" checked>
    </div>
    <?php foreach ($education as $i => $edu): ?>
      <div class="row-group">
        <div class="job-title">
          <input class="fw-bold" type="text" name="education[<?= $i ?>][name_of_studies]" value="<?= $edu['name_of_studies'] ?>">
        </div>
        <div class="job-date">
          <input class="fw-bold" type="text" name="education[<?= $i ?>][date]" value="<?= $edu['date'] ?>">
        </div>
        <input type="text" name="education[<?= $i ?>][place_of_study]" value="<?= $edu['place_of_study'] ?>">
        <textarea name="education[<?= $i ?>][desc]"><?= $edu['brief_description'] ?></textarea>
      </div>
    <?php endforeach; ?>

    <!-- Experience -->
    <div class="section-title">
      <label>Work Experience</label>
      <input type="checkbox" name="show_experience" checked>
    </div>
    <?php foreach ($experience as $i => $exp): ?>
      <div class="row-group">
        <div class="job-title d-flex">
          <input class="fw-bold" style="width:50%;"  type="text" name="experience[<?= $i ?>][job_name]" value="<?= $exp['job_name']?>, ">
          <input class="fw-bold" style="width:50%;" type="text" name="experience[<?= $i ?>][place_of_work]" value="<?= $exp['place_of_work'] ?>">
        </div>
        <div class="job-date">
          <input class="fw-bold" type="text" name="experience[<?= $i ?>][date]" value="<?= $exp['date'] ?>">
        </div>
        <textarea name="experience[<?= $i ?>][brief_description]" rows="3"><?= $exp['brief_description'] ?></textarea>
      </div>
    <?php endforeach; ?>

    <!-- Skills -->
    <div class="section-title">
      <label>Aptitudes</label>
      <input type="checkbox" name="show_skills" checked>
    </div>
    <div class="row row-cols-2">
      <?php foreach ($skills as $i => $skill): ?>
        <div class="col">
          <input type="text" name="skills[<?= $i ?>][aptitude]" value="<?= $skill['aptitude'] ?>">
        </div>
      <?php endforeach; ?>
    </div>

    <!-- Languages -->
    <div class="section-title">
      <label>Languages</label>
      <input type="checkbox" name="show_languages" checked>
    </div>
    <?php foreach ($languages as $i => $lang): ?>
      <div class="d-flex w-25">
          <input class="w-50" type="text" name="languages[<?= $i ?>][language]" value="<?= $lang['language'] ?>"> 
          <input class="w-50" type="text" name="languages[<?= $i ?>][level]" value="<?= $lang['level'] ?>">
      </div>
    <?php endforeach; ?>

    <!-- Projects -->
    <div class="section-title">
      <label>Projects</label>
      <input type="checkbox" name="show_projects" checked>
    </div>
    <?php foreach ($interests as $i => $int): ?>
      <div class="row-group"><input class="fw-bold" type="text" name="projects[<?= $i ?>][interest]" value="<?= $int['interest'] ?>">
        <textarea name="projects[<?= $i ?>][description]" rows="2"><?= $int['description'] ?></textarea>
      </div>
    <?php endforeach; ?>
    
    <?php if (!empty($custom_sections)): ?>
  <?php foreach ($custom_sections as $i => $section): ?>
    <div class="row-group mb-3">
      <!-- Display section title as heading -->
      <div class="section-title" >
        <label><?= htmlspecialchars($section['section_title'] ?: 'Untitled Section') ?></label>
        <input type="checkbox" name="custom_sections[<?= $i ?>][show]" value="1" checked class="ms-2">
      </div>

      <!-- Editable inputs for section data -->
      <input type="hidden" name="custom_sections[<?= $i ?>][section_title]" value="<?= htmlspecialchars($section['section_title']) ?>">
      <textarea
        name="custom_sections[<?= $i ?>][section_content]"
        rows="3"
        placeholder="Section Content"
      ><?= htmlspecialchars($section['section_content']) ?></textarea>
    </div>
  <?php endforeach; ?>
<?php endif; ?>




    <!-- Submit -->
    </div>
    <div class="submit-btn">
        <p class="text-muted" style="font-size:14px;">(This is just a preview for you to modify the contents of your resume, the layout and struture may change in the final printed pdf)</p>
      <button class="btn btn-primary" type="submit">Generate PDF</button>
    </div>
  
</form>
<?php include("../assets/footer.php");?>
<script>
document.addEventListener("DOMContentLoaded", function () {
  const fontSelector = document.getElementById("font");
  const container = document.querySelector(".resume-container");

  function applyFont(font) {
    if (font === "helvetica") {
      container.style.fontFamily = "'Helvetica Neue', Helvetica, Arial, sans-serif";
    } else if (font === "times") {
      container.style.fontFamily = "'Times New Roman', Times, serif";
    }
  }

  // Aplica la fuente seleccionada inicialmente
  applyFont(fontSelector.value);

  // Cambia la fuente al seleccionar otra opción
  fontSelector.addEventListener("change", function () {
    applyFont(this.value);
  });
});
</script>
<script>
document.addEventListener("DOMContentLoaded", function () {
  const fontSizeSelector = document.getElementById("contentFontSize");
  const container = document.querySelector(".resume-container");

  function applyFontSizes(basePt) {
    const basePx = parseFloat(basePt) * 1.6; // Much larger multiplier

    const nameSize = basePx * 2.5;
    const sectionSize = basePx * 1.4;
    const titleSize = basePx * 1.1;

    container.style.setProperty('--font-base', basePx + 'px');
    container.style.setProperty('--font-title', titleSize + 'px');
    container.style.setProperty('--font-section', sectionSize + 'px');
    container.style.setProperty('--font-name', nameSize + 'px');
  }

  fontSizeSelector.addEventListener("change", function () {
    applyFontSizes(this.value);
  });

  applyFontSizes(fontSizeSelector.value);
});
</script>




</body>
</html>

