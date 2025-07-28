<?php
//error_reporting(E_ALL);
//ini_set('display_errors', 1);
// Database connection
include("assets/head.php");
// Ensure user is logged in or username is provided
if (!isset($_GET['username'])) {
    if (!isset($_SESSION['username'])) {
        header("Location: https://qrsume.com/error.php");
        exit();
    } else {
        $username = $_SESSION['username'];
    }
} else {
    $username = $_GET['username'];
}

// Prepare and execute query
$stmt_login = $db->prepare("SELECT id, privilege FROM users WHERE username = :username");
$stmt_login->bindValue(":username", $username, PDO::PARAM_STR);
$stmt_login->execute();

// Fetch user
$user = $stmt_login->fetch(PDO::FETCH_ASSOC);

// If user not found, redirect to error page
if (!$user) {
    header("Location: https://qrsume.com/error.php");
    exit();
}

// Store user ID for further use
$user_id = $user['id'];
try {
    $pdo = $db;
    // Fetchtch user Info
    $user = db_select($pdo, 'users', 'username,email', ['id' => $user_id]);
    $user = $user[0] ?? null;
    
    //Fetchtch Personal Info
    $personal = db_select($pdo, 'personalinfo', '*', ['user_id' => $user_id]);
    $personal = $personal[0] ?? null;
    
    //Fetch Contact Info
    $contact = db_select($pdo, 'contactinfo', '*', ['user_id' => $user_id]);
    $contact = $contact[0] ?? null;
    
    // Fetch Education
    $education = db_select($pdo, 'education', '*', ['user_id' => $user_id]);
    
    // Fetch experience
    $experience = db_select($pdo, 'experience', '*', ['user_id' => $user_id]);
    
    // Fetch languages
    $languages = db_select($pdo, 'languages', '*', ['user_id' => $user_id]);
    
    // Fetch Skills (Aptitudes)
    $skills = db_select($pdo, 'aptitudes', '*', ['user_id' => $user_id]);
    
    // Fetch Interests
    $interests = db_select($pdo, 'interests', '*', ['user_id' => $user_id]);
    // Fetch Interests
    $custom_sections = db_select($pdo, 'custom_sections', '*', ['user_id' => $user_id]);


} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Supongamos que ya tienes todas las variables: $personal, $education, $experience, $skills, $languages, $interests
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
    textarea {
  overflow: hidden;    /* Hides scrollbar */
  resize: none;        /* Optional: prevents manual resize */
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
<?php include("assets/nav.php");?>

<div class="text-center my-4 mx-5">
  <h1 class="display-5 fw-bold">Preview Your Resume</h1>
  <p class="text-muted fs-5 mt-2">
    This preview allows you to see and edit your resume in real time.<br> You can modify your personal information, education, experience, and more. 
    <br>
    Choose your preferred font style and select which sections to include. <br> Once you're satisfied, click the button below to generate a professional PDF version.
  </p>
  
</div>

<form class="mb-3" action="https://qrsume.com/create_pdf.php" method="POST">
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
        <div  class="mb-3 d-flex align-items-center">
            <label for="contentFontSize">Gap Size:</label>
                <select name="spaceSize" id="spaceSize" class="form-select">
                  <option value="5">5</option>
                  <option value="10" selected>10(default)</option>
                  <option value="20">20</option>
                  <option value="30">30</option>
                </select>
        </div>
        <div>
        <label>Add personal bio:</label>
        <input type="checkbox" name="show_bio" id="toggleBio" checked>
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
    
    <!-- Personal Bio -->
<div class="w-100 mt-2" id="bioContainer" style="">
  <textarea class="form-control" name="personal_bio" rows="4" placeholder="Write a short bio about yourself..."><?= $personal['personal_bio'] ?? '' ?></textarea>
</div>

    <!-- Education -->
    <div class="section-title">
      <label>Education</label>
      <div>
      <input type="checkbox" name="show_education" checked>
      <label class="ms-2"><span class="text-muted fs-6">Mostrar descripción:</span>
      <input type="checkbox" name="show_education_description"></label>
      </div>
    </div>
    <?php foreach ($education as $i => $edu): ?>
      <div class="row-group">
        <div class="job-title">
          <input class="fw-bold" type="text" name="education[<?= $i ?>][name_of_studies]" value="<?= $edu['name_of_studies'] ?>">
        </div>
        <div class="job-date">
          <input class="fw-bold text-end" type="text" name="education[<?= $i ?>][date]" value="<?= $edu['date'] ?>">
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
        <div class=" w-100 job-title d-flex justify-content-between">
            <div class="job-title d-flex" style="width:75%">
                <input class="fw-bold" style="width:50%;"  type="text" name="experience[<?= $i ?>][job_name]" value="<?= $exp['job_name']?>, ">
                <input class="fw-bold" style="width:50%;" type="text" name="experience[<?= $i ?>][place_of_work]" value="<?= $exp['place_of_work'] ?>">
            </div>
            <input class="fw-bold w-25 text-end" type="text" name="experience[<?= $i ?>][date]" value="<?= $exp['date'] ?>">
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
      <div class="section-title" style="font-weight: bold; font-size: 16px; margin-bottom: 4px;">
        <?= htmlspecialchars($section['section_title'] ?: 'Untitled Section') ?>
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
      <button type="submit" class="btn btn-primary me-2" formaction="https://qrsume.com/create_pdf.php" formtarget="_blank">
        Generate PDF (English)
    </button>
    <button type="submit" class="btn btn-secondary" formaction="https://qrsume.com/create_pdf.php?spanish=true" formtarget="_blank">
        Generar PDF (Español)
    </button>
    </div>
  
</form>
<?php include("assets/footer.php");?>
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
    document.getElementById('toggleBio').addEventListener('change', function () {
  const bioContainer = document.getElementById('bioContainer');
  bioContainer.style.display = this.checked ? 'block' : 'none';
});
    
    document.querySelectorAll("textarea").forEach(function (el) {
    function autoResize() {
      el.style.height = 'auto';
      el.style.height = el.scrollHeight + 'px';
    }
    el.addEventListener("input", autoResize);
    autoResize(); // run on page load (if prefilled)
  });
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

