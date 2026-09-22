<?php
if (php_sapi_name() === 'cli-server') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

include("../assets/head.php");

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header("Location: ../assets/login.php");
    exit();
}
$sectionNumber = isset($_GET['section']) ? (int) $_GET['section'] : 0;
$prevSection = max(0, $sectionNumber - 1);
$nextSection = $sectionNumber + 1;
$isLastSection = ($sectionNumber == 7);
$isFirstSection = ($sectionNumber == 0);

$highlightStyle = 'background-color: rgba(241, 85, 85, 0.15); border: 2px solid #F15555; border-radius: 8px;';
// Generate CSRF token
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$quick_access_tables = ['personalinfo', 'contactinfo', 'aptitudes', 'education', 'experience', 'interests', 'languages', 'custom_sections'];

// Fetch User Data
$results = [];
foreach ($quick_access_tables as $table) {
    $query = "SELECT * FROM `$table` WHERE `user_id` = ?";
    $stmt = $db->prepare($query);
    $stmt->execute([$_SESSION['id']]);
    $results[$table] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$personalinfo = $results['personalinfo'][0] ?? null;

$sql = "SELECT photo_url FROM photos WHERE user_id = :user_id";
$stmt = $db->prepare($sql);
$stmt->bindValue(':user_id', $_SESSION['id'], PDO::PARAM_INT);
$stmt->execute();
$photos = $stmt->fetchAll(PDO::FETCH_COLUMN);

$totalSections = 8; // from 0 to 7
$progressPercent = intval(($sectionNumber + 1) / $totalSections * 100);
?>
<style>
  body {
    background-color: #f4f4f5;
  }

  h2 {
    font-family: "Inter", sans-serif;
  }
  textarea {
  overflow: hidden;    /* Hides scrollbar */
  resize: none;        /* Optional: prevents manual resize */
}


  .section-round {
    border-radius: 12px;
  }

  .section-round-top {
    border-radius: 12px 12px 0 0;
  }

  .hover-highlight:hover {
    background-color: #f4f4f5;
  }

  .delete-btn i {
    transition: color 0.3s ease;
  }

  .delete-btn:hover i {
    color: red;
  }

  .error-message {
    color: red;
    font-size: 0.9em;
    margin-top: 5px;
    display: none;
  }

  .success-message {
    color: green;
    font-size: 0.9em;
    margin-top: 5px;
    display: none;
  }
  
  .progress {
  background-color: #e9ecef;
  border-radius: 50px;
  overflow: hidden;
}


    #sectionWrapper{
        max-height: 70vh;
        overflow-y: auto;
    }

  #sectionWrapper::-webkit-scrollbar {
    width: 8px;
    
  }

  #sectionWrapper::-webkit-scrollbar-thumb {
    background-color: #ccc;
    border-radius: 4px;
  }

  .resume-container {
    --font-name: 20px;
    /* Reduced from 28px */
    --font-section: 12px;
    /* Reduced from 16px */
    --font-title: 10px;
    /* Reduced from 14px */
    --font-base: 8px;
    /* Reduced from 12px */
  }
  .text-small{
      font-size: 12px;!important
  }
  
  @media (max-width: 768px) {
    .resume-container {
      position: fixed;
      bottom: 1rem;
      right: 1rem;
      max-width: 500px;
      transform: scale(0.4);
      transform-origin: bottom right;
      z-index: 1050; /* above modals/overlays */
      border: 1px solid #ddd;
      border-radius: 12px;
    }
    #profile-photo{
        width:50%;
    }
  #sectionWrapper {
    max-width: 100% !important;
    max-height: none;
overflow-y: visible; /* o auto / hidden / scroll según el comportamiento que quieras */

  }

  }
</style>

<body class="d-flex justify-content-center align-items-center flex-column">
  <div id="loading-overlay" style="
    position: fixed;
    top: 0; left: 0;
    width: 100vw; height: 100vh;
    background-color: white;
    z-index: 9999;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    text-align: center;
    font-family: 'Segoe UI', sans-serif;
">
    <div class="spinner-border text-primary mb-4" role="status" style="width: 3rem; height: 3rem;">
      <span class="visually-hidden">Recollecting all your data...</span>
    </div>
    <p id="loading-text" class="text-muted fs-5">Recollecting all your site data...</p>
  </div>
 <?php include("../assets/nav_dashboard.php");?>

  <div class="" style="width:95vw;min-height:auto;">
      <div class="progress my-2" style="height: 15px;">
  <div class="progress-bar bg-success fw-semibold" role="progressbar" style="width: <?= $progressPercent ?>%;" aria-valuenow="<?= $progressPercent ?>" aria-valuemin="0" aria-valuemax="100">
    <?= $progressPercent ?>%
  </div>
</div>

    <div class="h-100 row g-4">
      <!-- Sidebar -->
      <div class="section-round col-lg-2 col-sm-6 col-md-4 col-12 d-none d-md-flex flex-column bg-white shadow p-2 h-100">
        <h4 class="text-center mb-3">Menu</h4>
        <ul class="nav flex-column">
          <li class="hover-highlight nav-item">
            <a class="nav-link active fw-semibold text-dark" href="https://qrsume.com/create_resume/form_with_login.php" data-section="0">
              <i class="bi bi-person-circle me-2"></i> Profile
            </a>
          </li>
          <li class="hover-highlight nav-item">
            <a class="nav-link fw-semibold text-dark" href="https://qrsume.com/create_resume/form_with_login.php?section=1" data-section="1">
              <i class="bi bi-envelope me-2"></i> Contact
            </a>
          </li>
          <li class="hover-highlight nav-item">
            <a class="nav-link fw-semibold text-dark" href="https://qrsume.com/create_resume/form_with_login.php?section=2" data-section="2">
              <i class="bi bi-book me-2"></i> Education
            </a>
          </li>
          <li class="hover-highlight nav-item">
            <a class="nav-link fw-semibold text-dark" href="https://qrsume.com/create_resume/form_with_login.php?section=3" data-section="3">
              <i class="bi bi-briefcase me-2"></i> Experience
            </a>
          </li>
          
          <li class="hover-highlight nav-item">
            <a class="nav-link fw-semibold text-dark" href="https://qrsume.com/create_resume/form_with_login.php?section=4" data-section="5">
              <i class="bi bi-lightbulb me-2"></i> Aptitudes
            </a>
          </li>
          <li class="hover-highlight nav-item">
            <a class="nav-link fw-semibold text-dark" href="https://qrsume.com/create_resume/form_with_login.php?section=5" data-section="6">
              <i class="bi bi-translate me-2"></i> Languages
            </a>
          </li>
          <li class="hover-highlight nav-item">
            <a class="nav-link fw-semibold text-dark" href="https://qrsume.com/create_resume/form_with_login.php?section=6" data-section="4">
              <i class="bi bi-star me-2"></i> Projects
            </a>
          </li>
          <li class="hover-highlight nav-item">
            <a class="nav-link fw-semibold text-dark" href="https://qrsume.com/create_resume/form_with_login.php?section=7" data-section="7">
              <i class="bi bi-ui-checks me-2"></i> Custom Sections
            </a>
          </li>
        </ul>
      </div>

      <!-- Main Content -->
      <div class="col-12 col-lg-6 col-md-8 position-relative" style="height:80%;">
        <div class="bg-white shadow section-round-top p-4 w-100 h-100" id="sectionWrapper" style="">
          <!-- Section 0: Personal Info -->
<section class="form-section <?= $sectionNumber === 0 ? '' : 'd-none' ?>" id="section-0">
    <h2>Personal Information</h2>
    <div id="photo-upload-status"></div>

    <?php if (isset($_GET['success_profile'])): ?>
  <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
    <?= htmlspecialchars($_GET['success_profile']) ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
  </div>
<?php endif; ?>

  

  <div class="row align-items-start">
    <!-- Profile Photo Section -->
    <div class="col-12 col-md-3 text-center mb-4">
      <img id="profile-photo" class="img-fluid rounded mb-2" alt="Profile Photo"
        src="images/<?= htmlspecialchars($personalinfo['personal_photo'] ?? 'default.webp') ?>">
      <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#profilePicModal">Edit Photo</button>
    </div>

    <!-- Personal Info Form -->
    <div class="col-12 col-md-9">
      <form id="form-0" method="post" action="https://qrsume.com/create_resume/upload/save_profile.php">
        <input type="hidden" name="action" value="save_personalinfo">
        <input type="hidden" name="id" value="<?= htmlspecialchars($personalinfo['id'] ?? '') ?>">
        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
        
        <div class="mb-3">
          <label class="form-label">Name</label>
          <input type="text" name="personal_name" class="form-control" required
            value="<?= htmlspecialchars($personalinfo['personal_name'] ?? '') ?>">
        </div>

        <div class="mb-3">
          <label class="form-label">Last Name</label>
          <input type="text" name="personal_lastname" class="form-control" required
            value="<?= htmlspecialchars($personalinfo['personal_lastname'] ?? '') ?>">
        </div>

        <div class="mb-3">
          <label class="form-label">Profession <span class="text-muted text-small">(For online resume, you can leave it blank)</span></label>
          <input type="text" name="personal_profession" class="form-control"
            value="<?= htmlspecialchars($personalinfo['personal_profession'] ?? '') ?>">
        </div>

        <div class="mb-3">
          <label class="form-label">Bio <span class="text-muted text-small">(For online resume, you can leave it blank)</span></label>
          <textarea name="personal_bio" class="form-control" rows="4"><?= htmlspecialchars($personalinfo['personal_bio'] ?? '') ?></textarea>
        </div>

        <input type="hidden" name="cv_url" value="<?= htmlspecialchars($personalinfo['cv_url'] ?? '') ?>">

        <div class="text-end">
          <div class="error-message" id="personalinfo-error"></div>
        </div>
      </form>
    </div>
  </div>

  <!-- Image Upload Modal -->
  <div class="modal fade" id="profilePicModal" tabindex="-1" aria-labelledby="profilePicModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <form id="uploadForm" action="../assets/upload/upload_profile_picture.php" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate onsubmit="closeModalOnSubmit(event)">
          <div class="modal-header">
            <h5 class="modal-title" id="profilePicModalLabel">Update Profile Picture</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          
          <div class="modal-body text-center">
            <!-- Image preview -->
            <img id="previewImage" src="#" alt="Preview"
              class="img-thumbnail d-none"
              style="object-fit: cover; width: 200px; height: 200px; border-radius: 0.5rem;">

            <!-- File input -->
            <input class="form-control mb-3" type="file" name="article_photo" id="article_photo" accept="image/*" required>
            <div class="invalid-feedback">Please select an image to upload.</div>

            <!-- Hidden fields -->
            <input type="hidden" name="referrer" value="https://qrsume.com/create_resume/form_with_login.php">
            <input type="hidden" name="source" value="profile_picture">
          </div>

          <div class="modal-footer">
            <button type="submit" class="btn btn-primary">Upload</button>
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          </div>
        </form>
      </div>
    </div>
  </div>
</section>


          <!-- Contact Info -->
          <div class="form-section <?= $sectionNumber === 1 ? '' : 'd-none' ?>" id="section-1">
            <h2 class="mb-4">Contact Information</h2>
            <form id="form-1" method="post" action="https://qrsume.com/create_resume/upload/save_contactinfo.php">
              <input type="hidden" name="action" value="save_contactinfo">
              <input type="hidden" name="user_id" value="<?= $_SESSION['id'] ?? '' ?>">
              <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label for="phone_number" class="form-label fw-semibold">
                    Phone 
                    <input type="checkbox" name="visibility[phone_number]" value="1"
                        <?= (!isset($visibility['phone']) || $visibility['phone'] == 0) ? 'checked' : '' ?>
                      class="form-check-input ms-2" title="Make public">
                      <!-- Phone label with warning and collapsible advice -->
                        <label for="visible_phone" class="form-text text-muted mb-0" style="font-size:12px;">
                          Hide phone in website
                           <i class="bi bi-exclamation-triangle-fill text-warning" data-bs-toggle="collapse" href="#phoneWarning" role="button" aria-expanded="false" aria-controls="phoneWarning"></i>
                        </label>
                        
                        <!-- Collapsible warning message -->
                        <div class="collapse mt-1" id="phoneWarning">
                          <div class="card card-body text-muted" style="font-size: 12px;">
                            Checking this box hides your phone number in the public website. <br> <strong>But it is still visible in the resume.</strong>
                            We recommend keeping it hidden online to avoid exposing your personal contact details to potential misuse.
                          </div>
                        </div>
                  </label>
                  <input type="text" id="phone_number" name="phone_number" class="form-control"
                    value="<?= htmlspecialchars($results['contactinfo'][0]['phone_number'] ?? '') ?>">
                </div>
                
                <div class="col-md-6 mb-3">
                  <label for="email" class="form-label fw-semibold">
                    Email
                    <input type="checkbox" name="visibility[email]" value="1"
                      <?= (!isset($visibility['email']) || $visibility['email'] == 0) ? 'checked' : '' ?>
                      class="form-check-input ms-2" title="Make public">
                      <!-- Email label with warning icon and collapsible info -->
                        <label for="visible_email" class="form-text text-muted mb-0" style="font-size:12px;">
                            Hide email in website
                          <i class="bi bi-exclamation-triangle-fill text-warning" data-bs-toggle="collapse" href="#emailWarning" role="button" aria-expanded="false" aria-controls="emailWarning"></i>
                          
                        </label>
                        
                        <!-- Collapsible warning message -->
                        <div class="collapse mt-1" id="emailWarning">
                          <div class="card card-body text-muted" style="font-size: 12px;">
                            Making your email public means it will be visible on your website. <br>
                            <strong>We recommend keeping it private</strong> to reduce the risk of spam or unsolicited messages. <br>
                            It will still appear in your downloadable resume.
                          </div>
                        </div>

                  </label>
                  <input type="email" id="email" name="email" class="form-control"
                    value="<?= htmlspecialchars($results['contactinfo'][0]['email'] ?? '') ?>">
                </div>

                <div class="col-md-6 mb-3">
                  <label for="github" class="form-label fw-semibold">GitHub <span class="text-muted text-small">(For online resume, you can leave it blank)</span></label>
                  <input type="url" id="github" name="github" class="form-control"
                    placeholder="https://" value="<?= htmlspecialchars($results['contactinfo'][0]['github'] ?? 'https://') ?>">
                </div>
                <div class="col-md-6 mb-3">
                  <label for="facebook" class="form-label fw-semibold">Facebook <span class="text-muted text-small">(For online resume, you can leave it blank)</span></label>
                  <input type="url" id="facebook" name="facebook" class="form-control"
                    placeholder="https://" value="<?= htmlspecialchars($results['contactinfo'][0]['facebook'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                  <label for="linkedin" class="form-label fw-semibold">LinkedIn <span class="text-muted text-small">(For online resume, you can leave it blank)</span></label>
                  <input type="url" id="linkedin" name="linkedin" class="form-control"
                    placeholder="https://" value="<?= htmlspecialchars($results['contactinfo'][0]['linkedin'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                  <label for="twitter" class="form-label fw-semibold">Twitter <span class="text-muted text-small">(For online resume, you can leave it blank)</span></label>
                  <input type="url" id="twitter" name="twitter" class="form-control"
                    placeholder="https://" value="<?= htmlspecialchars($results['contactinfo'][0]['twitter'] ?? '') ?>">
                </div>
              </div>
            </form>
          </div>

          <!-- Education Section -->
          <div class="form-section <?= $sectionNumber === 2 ? '' : 'd-none' ?>" id="section-2">
            <h2 class="mb-1">Education Information</h2>
            <p class="text-muted mb-4">Add the name of your school, what degree you obtained, your field of study, and your graduation year.</p>

            <form id="form-2" action="https://qrsume.com/create_resume/upload/save_education.php" method="POST">
              <input type="hidden" name="action" value="save_educationinfo_group">
              <input type="hidden" name="user_id" value="<?= $_SESSION['id'] ?? '' ?>">
              <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

              <div id="education-entries">
                <!-- Existing entries -->
                <?php if (!empty($results['education'])): ?>
                  <?php foreach ($results['education'] as $index => $edu): ?>
                    <?php $eduId = $edu['education_id']; ?>
                    <div class="education-entry overflow-hidden border mb-3" style="border-radius:12px;">

                      <div class="d-flex justify-content-between hover-highlight align-items-center p-3"
                        data-bs-toggle="collapse" data-bs-target="#edu-collapse-<?= $index ?>" aria-expanded="false" role="button">
                        <h5 class="mb-0 fw-semibold fs-6"><?= htmlspecialchars($edu['name_of_studies'] ?? 'Untitled Education') ?></h5>
                        <i class="bi bi-chevron-down transition"></i>
                      </div>

                      <div class="collapse show fs-6" id="edu-collapse-<?= $index ?>">
                        <div class="p-3 position-relative">
                          <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1 delete-edu-btn" title="Delete this entry">
                            <i class="bi bi-trash3"></i>
                          </button>
                          <input type="hidden" name="delete_flag[]" value="false">

                          <input type="hidden" name="education_id[]" value="<?= htmlspecialchars($eduId) ?>">

                          <div class="row">

                            <div class="col-md-8 mb-3">
                              <label class="form-label fw-semibold">Name of Studies</label>
                              <input type="text" class="form-control" name="name_of_studies[]" value="<?= htmlspecialchars($edu['name_of_studies'] ?? '') ?>">
                            </div>
                            <div class="col-md-4 mb-3">
                              <label class="form-label fw-semibold">Date</label>
                              <input type="text" class="form-control" name="date[]" value="<?= htmlspecialchars($edu['date'] ?? '') ?>" placeholder="e.g., 2020–2022">
                            </div>
                            <div class="col-md-12 mb-3">
                              <label class="form-label fw-semibold">Place of Study</label>
                              <input type="text" class="form-control" name="place_of_study[]" value="<?= htmlspecialchars($edu['place_of_study'] ?? '') ?>">
                            </div>
                            <div class="col-md-12 mb-3">
                              <label class="form-label fw-semibold">Brief Description</label>
                              <textarea class="form-control" name="brief_description[]"><?= htmlspecialchars($edu['brief_description'] ?? '') ?></textarea>
                            </div>
                          </div>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>

              <!-- Add New Button -->
              <?php if (count($results['education']) < 5): ?>
                <button type="button" class="btn btn-outline-secondary w-100 mb-3" id="add-education-btn">
                  + Add New Education
                </button>
              <?php else: ?>
                <p class="text-danger text-center mt-3">❌ You have reached the maximum of 5 education entries.</p>
              <?php endif; ?>

            </form>
          </div>

          <!-- Template for dynamic education entry -->
          <template id="new-education-template">
            <div class="education-entry overflow-hidden border mb-3" style="border-radius:12px;">
              <div class="d-flex justify-content-between hover-highlight align-items-center p-3"
                data-bs-toggle="collapse" data-bs-target="" aria-expanded="false" role="button">
                <h5 class="mb-0 fw-semibold fs-6">Untitled Education</h5>
                <i class="bi bi-chevron-down transition"></i>
              </div>
              <div class="collapse fs-6">
                <div class="p-3 position-relative">
                  <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1 delete-edu-btn" title="Delete this entry">
                    <i class="bi bi-trash3"></i>
                  </button>
                  <input type="hidden" name="delete_flag[]" value="false">
                  <input type="hidden" name="education_id[]" value="">

                  <div class="row">
                    <div class="col-md-8 mb-3">
                      <label class="form-label fw-semibold">Name of Studies</label>
                      <input type="text" class="form-control" name="name_of_studies[]">
                    </div>
                    <div class="col-md-4 mb-3">
                      <label class="form-label fw-semibold">Date</label>
                      <input type="text" class="form-control" name="date[]" placeholder="e.g., 2020–2022">
                    </div>
                    <div class="col-md-12 mb-3">
                      <label class="form-label fw-semibold">Place of Study</label>
                      <input type="text" class="form-control" name="place_of_study[]">
                    </div>
                    <div class="col-md-12 mb-3">
                      <label class="form-label fw-semibold">Brief Description</label>
                      <textarea class="form-control" name="brief_description[]"></textarea>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </template>

          <!-- Work Experience -->
          <div class="form-section <?= $sectionNumber === 3 ? '' : 'd-none' ?>" id="section-3">
            <h2 class="mb-4">Work Experience</h2>

            <form id="form-3" action="https://qrsume.com/create_resume/upload/save_experience.php" method="POST">
              <input type="hidden" name="action" value="save_experienceinfo_group">
              <input type="hidden" name="user_id" value="<?= $_SESSION['id'] ?? '' ?>">
              <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

              <div id="experience-entries">
                <?php if (!empty($results['experience'])): ?>
                  <?php foreach ($results['experience'] as $index => $job): ?>
                    <div class="experience-entry border rounded mb-3 position-relative">
                      <input type="hidden" name="experience_id[]" value="<?= htmlspecialchars($job['experience_id']) ?>">


                      <div class="d-flex justify-content-between align-items-center p-3 bg-light" data-bs-toggle="collapse" data-bs-target="#job-collapse-<?= $index ?>" role="button">
                        <h5 class="mb-0 fw-semibold">💼 <?= htmlspecialchars($job['job_name'] ?? 'Untitled Experience') ?></h5>
                        <i class="bi bi-chevron-down transition"></i>
                      </div>

                      <div class="collapse show p-3 position-relative" id="job-collapse-<?= $index ?>">
                        <input type="hidden" name="delete_flag[]" value="false">

                        <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1 delete-experience-btn" title="Delete this entry">
                          <i class="bi bi-trash3"></i>
                        </button>
                        <div class="row">
                          <div class="col-md-8 mb-3">
                            <label class="form-label fw-semibold">Job Name</label>
                            <input type="text" class="form-control" name="job_name[]" value="<?= htmlspecialchars($job['job_name'] ?? '') ?>">
                          </div>
                          <div class="col-md-4 mb-3">
                            <label class="form-label fw-semibold">Date</label>
                            <input type="text" class="form-control" name="date[]" value="<?= htmlspecialchars($job['date'] ?? '') ?>" placeholder="e.g., 2022–Present">
                          </div>
                          <div class="col-md-12 mb-3">
                            <label class="form-label fw-semibold">Place of Work</label>
                            <input type="text" class="form-control" name="place_of_work[]" value="<?= htmlspecialchars($job['place_of_work'] ?? '') ?>">
                          </div>
                          <div class="col-md-12 mb-3">
                            <label class="form-label fw-semibold">Brief Description</label>
                            <textarea class="form-control" name="brief_description[]"><?= htmlspecialchars($job['brief_description'] ?? '') ?></textarea>
                          </div>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                <?php endif; ?>
              </div>

              <?php if (count($results['experience']) < 5): ?>
                <button type="button" class="btn btn-outline-secondary w-100 mb-3" id="add-experience-btn">
                  + Add New Work Experience
                </button>
              <?php else: ?>
                <p class="text-danger text-center mt-3">❌ You have reached the maximum of 5 work experience entries.</p>
              <?php endif; ?>
            </form>
          </div>

          <!-- Template for New Experience -->
          <template id="experience-template">
            <div class="experience-entry border rounded mb-3 position-relative">
              <input type="hidden" name="experience_id[]" value="">


              <div class="d-flex justify-content-between align-items-center p-3 bg-light" data-bs-toggle="collapse" data-bs-target="" role="button">
                <h5 class="mb-0 fw-semibold">💼 Untitled Experience</h5>
                <i class="bi bi-chevron-down transition"></i>
              </div>

              <div class="collapse show p-3 position-relative">
                <input type="hidden" name="delete_flag[]" value="false">

                <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1 delete-experience-btn" title="Delete this entry">
                  <i class="bi bi-trash3"></i>
                </button>
                <div class="row">
                  <div class="col-md-8 mb-3">
                    <label class="form-label fw-semibold">Job Name</label>
                    <input type="text" class="form-control" name="job_name[]">
                  </div>
                  <div class="col-md-4 mb-3">
                    <label class="form-label fw-semibold">Date</label>
                    <input type="text" class="form-control" name="date[]" placeholder="e.g., 2022–Present">
                  </div>
                  <div class="col-md-12 mb-3">
                    <label class="form-label fw-semibold">Place of Work</label>
                    <input type="text" class="form-control" name="place_of_work[]">
                  </div>
                  <div class="col-md-12 mb-3">
                    <label class="form-label fw-semibold">Brief Description</label>
                    <textarea class="form-control" name="brief_description[]"></textarea>
                  </div>
                </div>
              </div>
            </div>
          </template>


          <!-- Projects -->
<div class="form-section <?= $sectionNumber === 6 ? '' : 'd-none' ?>" id="section-4">
  <h2 class="mb-4">Projects</h2>

  <form id="form-4" action="https://qrsume.com/create_resume/upload/save_projects.php" method="POST">
    <input type="hidden" name="action" value="save_projects_group">
    <input type="hidden" name="user_id" value="<?= $_SESSION['id'] ?? '' ?>">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div id="project-entries">
      <?php if (!empty($results['interests'])): ?>
        <?php foreach ($results['interests'] as $index => $interest): ?>
          <div class="project-entry border rounded mb-3 position-relative">
            <input type="hidden" name="interest_id[]" value="<?= htmlspecialchars($interest['interest_id']) ?>">
            

            <div class="d-flex justify-content-between align-items-center p-3 bg-light"
              data-bs-toggle="collapse" data-bs-target="#interest-collapse-<?= $index ?>" role="button">
              <h5 class="mb-0 fw-semibold">🚀 <?= htmlspecialchars($interest['interest'] ?? 'Untitled Project') ?></h5>
              <i class="bi bi-chevron-down transition"></i>
            </div>

            <div class="collapse show  p-3 position-relative" id="interest-collapse-<?= $index ?>">
                <input type="hidden" name="delete_flag[]" value="false">

            <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1 delete-project-btn" title="Delete this entry">
              <i class="bi bi-trash3"></i>
            </button>
              <div class="row">
                <div class="col-md-12 mb-3">
                  <label class="form-label fw-semibold">Project Name</label>
                  <input type="text" class="form-control" name="interest[]" value="<?= htmlspecialchars($interest['interest'] ?? '') ?>">
                </div>
                <div class="col-md-12 mb-3">
                  <label class="form-label fw-semibold">Description</label>
                  <textarea class="form-control" name="description[]"><?= htmlspecialchars($interest['description'] ?? '') ?></textarea>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <?php if (count($results['interests']) < 10): ?>
      <button type="button" class="btn btn-outline-secondary w-100 mb-3" id="add-project-btn">
        + Add New Project
      </button>
    <?php else: ?>
      <p class="text-danger text-center mt-3">❌ You have reached the maximum of 5 projects.</p>
    <?php endif; ?>
  </form>
</div>

<!-- Template for New Project Entry -->
<template id="project-template">
  <div class="project-entry border rounded mb-3 position-relative">
    <input type="hidden" name="interest_id[]" value="">
    

    <div class="d-flex justify-content-between align-items-center p-3 bg-light"
      data-bs-toggle="collapse" data-bs-target="" role="button">
      <h5 class="mb-0 fw-semibold">🚀 Untitled Project</h5>
      <i class="bi bi-chevron-down transition"></i>
    </div>

    <div class="collapse show p-3 position-relative">
        <input type="hidden" name="delete_flag[]" value="false">

    <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-1 delete-project-btn" title="Delete this entry">
      <i class="bi bi-trash3"></i>
    </button>
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label fw-semibold">Project Name</label>
          <input type="text" class="form-control" name="interest[]">
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label fw-semibold">Description</label>
          <textarea class="form-control" name="description[]"></textarea>
        </div>
      </div>
    </div>
  </div>
</template>


          <!-- Aptitudes -->
<div class="form-section <?= $sectionNumber === 4 ? '' : 'd-none' ?>" id="section-5">
  <h2 class="mb-4">Aptitudes</h2>

  <form id="form-5" action="https://qrsume.com/create_resume/upload/save_aptitudes.php" method="POST">
    <input type="hidden" name="action" value="save_aptitudes_group">
    <input type="hidden" name="user_id" value="<?= $_SESSION['id'] ?? '' ?>">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div id="aptitude-entries">
      <?php if (!empty($results['aptitudes'])): ?>
        <?php foreach ($results['aptitudes'] as $index => $apt): ?>
          <div class="aptitude-entry border rounded mb-3 position-relative">
            <input type="hidden" name="aptitude_id[]" value="<?= htmlspecialchars($apt['aptitude_id']) ?>">
            <input type="hidden" name="delete_flag[]" value="false">

            <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-3 delete-aptitude-btn" title="Delete this entry">
              <i class="bi bi-trash3"></i>
            </button>

            <div class="d-flex justify-content-between align-items-center p-3 bg-light"
              data-bs-toggle="collapse" data-bs-target="#aptitude-collapse-<?= $index ?>" role="button">
              <h5 class="mb-0 fw-semibold">🧠 <?= htmlspecialchars($apt['aptitude'] ?? 'Untitled Aptitude') ?></h5>
              <i class="bi bi-chevron-down transition"></i>
            </div>

            <div class="collapse show p-3" id="aptitude-collapse-<?= $index ?>">
              <div class="mb-3">
                <label class="form-label fw-semibold">Aptitude</label>
                <input type="text" class="form-control" name="aptitude[]" value="<?= htmlspecialchars($apt['aptitude'] ?? '') ?>">
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <?php if (count($results['aptitudes']) < 10): ?>
      <button type="button" class="btn btn-outline-secondary w-100 mb-3" id="add-aptitude-btn">
        + Add New Aptitude
      </button>
    <?php else: ?>
      <p class="text-danger text-center mt-3">❌ You have reached the maximum of 5 aptitudes.</p>
    <?php endif; ?>
  </form>
</div>

<!-- Template for new aptitude -->
<template id="aptitude-template">
  <div class="aptitude-entry border rounded mb-3 position-relative">
    <input type="hidden" name="aptitude_id[]" value="">
    <input type="hidden" name="delete_flag[]" value="false">

    <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-3 delete-aptitude-btn" title="Delete this entry">
      <i class="bi bi-trash3"></i>
    </button>

    <div class="d-flex justify-content-between align-items-center p-3 bg-light"
      data-bs-toggle="collapse" data-bs-target="" role="button">
      <h5 class="mb-0 fw-semibold">🧠 Untitled Aptitude</h5>
      <i class="bi bi-chevron-down transition"></i>
    </div>

    <div class="collapse show p-3">
      <div class="mb-3">
        <label class="form-label fw-semibold">Aptitude</label>
        <input type="text" class="form-control" name="aptitude[]">
      </div>
    </div>
  </div>
</template>


         <!-- Languages -->
<div class="form-section <?= $sectionNumber === 5 ? '' : 'd-none' ?>" id="section-6">
  <h2 class="mb-4">Languages</h2>

  <form id="form-6" action="https://qrsume.com/create_resume/upload/save_languages.php" method="POST">
    <input type="hidden" name="action" value="save_languages_group">
    <input type="hidden" name="user_id" value="<?= $_SESSION['id'] ?? '' ?>">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div id="language-entries">
      <?php if (!empty($results['languages'])): ?>
        <?php foreach ($results['languages'] as $index => $lang): ?>
          <div class="language-entry border rounded mb-3 position-relative">
            <input type="hidden" name="languages_id[]" value="<?= htmlspecialchars($lang['languages_id']) ?>">
            <input type="hidden" name="delete_flag[]" value="false">

            <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-3 delete-language-btn" title="Delete this entry">
              <i class="bi bi-trash3"></i>
            </button>

            <div class="d-flex justify-content-between align-items-center p-3 bg-light"
                 data-bs-toggle="collapse" data-bs-target="#language-collapse-<?= $index ?>" role="button">
              <h5 class="mb-0 fw-semibold">🌐 <?= htmlspecialchars($lang['language'] ?? 'Untitled Language') ?></h5>
              <i class="bi bi-chevron-down transition"></i>
            </div>

            <div class="collapse show p-3" id="language-collapse-<?= $index ?>">
              <div class="row">
                <div class="col-md-6 mb-3">
                  <label class="form-label fw-semibold">Language</label>
                  <input type="text" class="form-control" name="language[]" value="<?= htmlspecialchars($lang['language'] ?? '') ?>">
                </div>
                <div class="col-md-6 mb-3">
                  <label class="form-label fw-semibold">Level</label>
                  <input type="text" class="form-control" name="level[]" value="<?= htmlspecialchars($lang['level'] ?? '') ?>">
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <?php if (count($results['languages']) < 5): ?>
      <button type="button" class="btn btn-outline-secondary w-100 mb-3" id="add-language-btn">
        + Add New Language
      </button>
    <?php else: ?>
      <p class="text-danger text-center mt-3">❌ You have reached the maximum of 5 languages.</p>
    <?php endif; ?>

  </form>
</div>

<!-- Template for new language -->
<template id="language-template">
  <div class="language-entry border rounded mb-3 position-relative">
    <input type="hidden" name="languages_id[]" value="">
    <input type="hidden" name="delete_flag[]" value="false">

    <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-3 delete-language-btn" title="Delete this entry">
      <i class="bi bi-trash3"></i>
    </button>

    <div class="d-flex justify-content-between align-items-center p-3 bg-light"
         data-bs-toggle="collapse" data-bs-target="" role="button">
      <h5 class="mb-0 fw-semibold">🌐 Untitled Language</h5>
      <i class="bi bi-chevron-down transition"></i>
    </div>

    <div class="collapse show p-3">
      <div class="row">
        <div class="col-md-6 mb-3">
          <label class="form-label fw-semibold">Language</label>
          <input type="text" class="form-control" name="language[]">
        </div>
        <div class="col-md-6 mb-3">
          <label class="form-label fw-semibold">Level</label>
          <input type="text" class="form-control" name="level[]">
        </div>
      </div>
    </div>
  </div>
</template>


          <!-- Custom Sections -->
<div class="form-section <?= $sectionNumber === 7 ? '' : 'd-none' ?>" id="section-7">
  <h2 class="mb-4">Custom Sections</h2>

  <form id="form-7" action="https://qrsume.com/create_resume/upload/save_custom_sections.php" method="POST">
    <input type="hidden" name="action" value="save_custom_sections_group">
    <input type="hidden" name="user_id" value="<?= $_SESSION['id'] ?? '' ?>">
    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">

    <div id="custom-section-entries">
      <?php if (!empty($results['custom_sections'])): ?>
        <?php foreach ($results['custom_sections'] as $index => $sections): ?>
          <div class="custom-section-entry border rounded mb-3 position-relative">
            <input type="hidden" name="section_id[]" value="<?= htmlspecialchars($sections['section_id']) ?>">
            <input type="hidden" name="delete_flag[]" value="false">

            <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-3 delete-custom-btn" title="Delete this entry">
              <i class="bi bi-trash3"></i>
            </button>

            <div class="d-flex justify-content-between align-items-center p-3 bg-light"
              data-bs-toggle="collapse" data-bs-target="#custom-collapse-<?= $index ?>" role="button">
              <h5 class="mb-0 fw-semibold">🧩 <?= htmlspecialchars($sections['section_title'] ?? 'Untitled Section') ?></h5>
              <i class="bi bi-chevron-down transition"></i>
            </div>

            <div class="collapse show p-3" id="custom-collapse-<?= $index ?>">
              <div class="mb-3">
                <label class="form-label fw-semibold">Section Title</label>
                <input type="text" class="form-control" name="section_title[]" value="<?= htmlspecialchars($sections['section_title'] ?? '') ?>">
              </div>
              <div class="mb-3">
                <label class="form-label fw-semibold">Section Content</label>
                <textarea class="form-control" name="section_content[]"><?= htmlspecialchars($sections['section_content'] ?? '') ?></textarea>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <?php if (count($results['custom_sections']) < 5): ?>
      <button type="button" class="btn btn-outline-secondary w-100 mb-3" id="add-custom-btn">
        + Add New Custom Section
      </button>
    <?php else: ?>
      <p class="text-danger text-center mt-3">❌ You have reached the maximum of 5 custom sections.</p>
    <?php endif; ?>

  </form>
</div>

<!-- Template -->
<template id="custom-template">
  <div class="custom-section-entry border rounded mb-3 position-relative">
    <input type="hidden" name="section_id[]" value="">
    <input type="hidden" name="delete_flag[]" value="false">

    <button type="button" class="btn btn-sm btn-danger position-absolute top-0 end-0 m-3 delete-custom-btn" title="Delete this entry">
      <i class="bi bi-trash3"></i>
    </button>

    <div class="d-flex justify-content-between align-items-center p-3 bg-light"
      data-bs-toggle="collapse" data-bs-target="" role="button">
      <h5 class="mb-0 fw-semibold">🧩 Untitled Section</h5>
      <i class="bi bi-chevron-down transition"></i>
    </div>

    <div class="collapse show p-3">
      <div class="mb-3">
        <label class="form-label fw-semibold">Section Title</label>
        <input type="text" class="form-control" name="section_title[]">
      </div>
      <div class="mb-3">
        <label class="form-label fw-semibold">Section Content</label>
        <textarea class="form-control" name="section_content[]"></textarea>
      </div>
    </div>
  </div>
</template>

        </div>
        <!-- Navigation Buttons -->
        <div class="d-flex justify-content-between p-3 bg-white border-top" style="border-radius:0 0 12px 12px;">

          <!-- Back Button -->
          <a class="btn btn-secondary rounded-pill px-4 py-2 fw-semibold shadow-sm <?= $isFirstSection ? 'disabled' : '' ?>"
            href="<?= $isFirstSection ? '#' : 'https://qrsume.com/create_resume/form_with_login.php?section=' . $prevSection ?>"
            style="<?= $isFirstSection ? 'opacity: 0.5; pointer-events: none;' : '' ?>"
            id="prevBtn"><i class="bi bi-chevron-left me-1"></i> Back</a>

          <!-- Next/Finish Button -->
          <button
  type="submit"
  form="form-<?= $sectionNumber ?>"
  class="btn btn-primary fw-semibold px-4 py-2 shadow-sm border-0"
  style="border-radius: 999px; background-color: #2563eb;"
  id="nextBtn">
  <span id="nextBtnLabel">
    <?= $isLastSection ? 'Download' : 'Save & Next' ?>
  </span>
  <i class="bi <?= $isLastSection ? 'bi-download' : 'bi-chevron-right' ?> ms-1"></i>
</button>
        </div>
      </div>

      <div class="col-12 col-lg-4 resume-container shadow" style="background: white; padding: 1.5rem; box-shadow: 0 0 8px rgba(0,0,0,0.1);">

        <!-- Profile and Contact (highlighted for sections 0 and 1) -->
        <div style="<?= in_array($sectionNumber, [0, 1]) ? $highlightStyle : '' ?>">
          <h1 class="fw-bold text-center" style="font-size: var(--font-name, 28px);">
            <?= htmlspecialchars($results['personalinfo'][0]['personal_name'] ?? '') ?>
            <?= htmlspecialchars($results['personalinfo'][0]['personal_lastname'] ?? '') ?>
          </h1>
          <div class="contact text-center" style="font-size: var(--font-base, 12px); margin-bottom: 0.2rem;">
            <?= htmlspecialchars($results['contactinfo'][0]['email'] ?? '') ?> |
            <?= htmlspecialchars($results['contactinfo'][0]['phone_number'] ?? '') ?>
          </div>
        </div>

        <!-- Education (highlighted for section 2) -->
        <?php if (!empty($results['education'])): ?>
          <div style="<?= $sectionNumber === 2 ? $highlightStyle : '' ?>">
            <div class="section-title" style="font-size: var(--font-section, 16px); font-weight: bold; margin-top: 0.4rem; margin-bottom: 0.3rem; border-bottom: 1px solid black;">
              Education
            </div>
            <?php foreach ($results['education'] as $edu): ?>
              <div class="row-group" style="margin-bottom: 0.2rem;">
                <div class="d-flex justify-content-between align-items-center" style="font-size: var(--font-title, 14px);">
                  <div class="job-title fw-bold">
                    <?= htmlspecialchars($edu['name_of_studies'] ?? '') ?>
                  </div>
                  <div class="job-date" style="font-size: 8px;">
                    <?= htmlspecialchars($edu['date'] ?? '') ?>
                  </div>
                </div>
                <div style="font-size: var(--font-base, 12px);"><?= htmlspecialchars($edu['place_of_study'] ?? '') ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <!-- Work Experience (highlighted for section 3) -->
        <?php if (!empty($results['experience'])): ?>
          <div style="<?= $sectionNumber === 3 ? $highlightStyle : '' ?>">
            <div class="section-title" style="font-size: var(--font-section, 16px); font-weight: bold; margin-top: 0.4rem; margin-bottom: 0.3rem; border-bottom: 1px solid black;">
              Work Experience
            </div>
            <?php foreach ($results['experience'] as $exp): ?>
              <div class="row-group" style="margin-bottom: 0.2rem;">
                <div class="d-flex justify-content-between align-items-center" style="font-size: var(--font-title, 14px);">
                  <div class="job-title fw-bold">
                    <?= htmlspecialchars($exp['job_name'] ?? '') ?>, <?= htmlspecialchars($exp['place_of_work'] ?? '') ?>
                  </div>
                  <div class="job-date" style="font-size: 8px;">
                    <?= htmlspecialchars($exp['date'] ?? '') ?>
                  </div>
                </div>
                <div style="font-size: var(--font-base, 12px);"><?= htmlspecialchars($exp['brief_description'] ?? '') ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <!-- Aptitudes (highlighted for section 5) -->
        <?php if (!empty($results['aptitudes'])): ?>
          <div style="<?= $sectionNumber === 4 ? $highlightStyle : '' ?>">
            <div class="section-title" style="font-size: var(--font-section, 16px); font-weight: bold; margin-top: 0.4rem; margin-bottom: 0.3rem; border-bottom: 1px solid black;">
              Aptitudes
            </div>
            <div class="row row-cols-2" style="font-size: var(--font-base, 12px);">
              <?php foreach ($results['aptitudes'] as $apt): ?>
                <div class="col"><?= htmlspecialchars($apt['aptitude'] ?? '') ?></div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <!-- Languages (highlighted for section 6) -->
        <?php if (!empty($results['languages'])): ?>
          <div style="<?= $sectionNumber === 5 ? $highlightStyle : '' ?>">
            <div class="section-title" style="font-size: var(--font-section, 16px); font-weight: bold; margin-top: 0.4rem; margin-bottom: 0.3rem; border-bottom: 1px solid black;">
              Languages
            </div>
            <?php foreach ($results['languages'] as $lang): ?>
              <div class="d-flex w-25" style="font-size: var(--font-base, 12px);">
                <span class="w-50"><?= htmlspecialchars($lang['language'] ?? '') ?></span>
                <span class="w-50"><?= htmlspecialchars($lang['level'] ?? '') ?></span>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <!-- Projects (highlighted for section 4) -->
        <?php if (!empty($results['interests'])): ?>
          <div style="<?= $sectionNumber === 6 ? $highlightStyle : '' ?>">
            <div class="section-title" style="font-size: var(--font-section, 16px); font-weight: bold; margin-top: 0.4rem; margin-bottom: 0.3rem; border-bottom: 1px solid black;">
              Projects
            </div>
            <?php foreach ($results['interests'] as $int): ?>
              <div class="row-group" style="margin-bottom: 0.2rem;">
                <div class="fw-bold" style="font-size: var(--font-title, 14px);"><?= htmlspecialchars($int['interest'] ?? '') ?></div>
                <div style="font-size: var(--font-base, 12px);"><?= htmlspecialchars($int['description'] ?? '') ?></div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

        <?php if (!empty($results['custom_sections'])): ?>
  <div style="<?= $sectionNumber === 7 ? $highlightStyle : '' ?>">
    <?php foreach ($results['custom_sections'] as $section_item): ?>
      <div class="row-group" style="margin-bottom: 1.5rem;">
        <div class="section-title" style="font-size: var(--font-section, 16px); font-weight: bold; margin-bottom: 0.3rem; border-bottom: 1px solid black;">
          <?= htmlspecialchars($section_item['section_title'] ?? 'Untitled Section') ?>
        </div>
        <div style="font-size: var(--font-base, 12px);"><?= nl2br(htmlspecialchars($section_item['section_content'] ?? '')) ?></div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

      </div>
    </div>
  </div>

  <?php include("../assets/footer.php"); ?>

  <script>
      function enhanceTextarea(el, index = null) {
  // === Auto-resize ===
  function autoResize() {
    el.style.height = 'auto';
    el.style.height = el.scrollHeight + 'px';
  }
  el.addEventListener("input", autoResize);
  autoResize();

  // === Add bullet button ===
  if (!el.dataset.bulletAttached) { // prevent duplicate buttons
    const button = document.createElement('button');
    button.type = 'button';
    button.className = 'btn btn-sm btn-secondary mb-1';
    button.innerHTML = '<i class="bi bi-list-ul"></i>';

    // Optional: assign unique ID
    if (!el.id && index !== null) {
      el.id = `textarea-${index}`;
    }

    button.addEventListener('click', () => {
      const lines = el.value.split('\n');
      const bulleted = lines.map(line => {
        const trimmed = line.trim();
        return trimmed.startsWith('•') ? line : '• ' + trimmed;
      });
      el.value = bulleted.join('\n');
    });

    el.parentNode.insertBefore(button, el);
    el.dataset.bulletAttached = 'true';
  }
}

document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll("textarea").forEach((el, i) => enhanceTextarea(el, i));

  // === FORM TOGGLE MODULE ===
  const formToggle = (() => {
    const toggles = [
      { buttonId: "add-experience-btn", formId: "new-experience-form" },
      { buttonId: "add-interest-btn", formId: "new-interest-form" },
      { buttonId: "add-aptitude-btn", formId: "new-aptitude-form" },
      { buttonId: "add-language-btn", formId: "new-language-form" },
    ];

    const init = () => {
      toggles.forEach(({ buttonId, formId }) => {
        const btn = document.getElementById(buttonId);
        const form = document.getElementById(formId);
        if (btn && form) {
          btn.addEventListener("click", () => {
            form.style.display = "block";
            btn.style.display = "none";
          });
        }
      });
    };

    return { init };
  })();


  // === PROFILE PHOTO PREVIEW (MODAL UPLOAD) MODULE ===
  const profilePhotoModalPreview = (() => {
    const fileInput = document.getElementById('article_photo');
    const previewImg = document.getElementById('previewImage');

    const init = () => {
      if (fileInput && previewImg) {
        fileInput.addEventListener('change', function (e) {
          const file = e.target.files[0];
          if (file) {
            const reader = new FileReader();
            reader.onload = function (event) {
              previewImg.src = event.target.result;
              previewImg.classList.remove('d-none');
            };
            reader.readAsDataURL(file);
          } else {
            previewImg.src = "#";
            previewImg.classList.add('d-none');
          }
        });
      }
    };

    return { init };
  })();


  // === PROFILE PHOTO PREVIEW (DROPDOWN CHANGE) MODULE ===
  const profilePhotoDropdownPreview = (() => {
    const photoSelect = document.getElementById("profile_photo");
    const photoImg = document.getElementById("profile-photo");

    const init = () => {
      if (photoSelect && photoImg) {
        photoSelect.addEventListener("change", () => {
          const val = photoSelect.value;
          if (val) {
            photoImg.src = `images/${val}`;
          }
        });
      }
    };

    return { init };
  })();


  // === OVERLAY REMOVE ON LOAD MODULE ===
  const removeOverlay = () => {
    const overlay = document.getElementById('loading-overlay');
    if (overlay) {
      overlay.style.transition = 'opacity 0.5s ease';
      overlay.style.opacity = 0;
      setTimeout(() => overlay.remove(), 500);
    }
  };


  // === EDUCATION SECTION ===
  let eduEntryCount = <?= count($results['education'] ?? []) ?>;
  const maxEduEntries = 5;
  const eduAddBtn = document.getElementById('add-education-btn');
  const eduTemplate = document.getElementById('new-education-template');
  const eduContainer = document.getElementById('education-entries');

  if (eduAddBtn) {
    eduAddBtn.addEventListener('click', function () {
      if (eduEntryCount >= maxEduEntries) return;
      const clone = eduTemplate.content.cloneNode(true);
      const collapseId = 'edu-collapse-new-' + eduEntryCount;
      clone.querySelector('[data-bs-toggle]').setAttribute('data-bs-target', '#' + collapseId);
      clone.querySelector('.collapse').setAttribute('id', collapseId);
      eduContainer.appendChild(clone);
      eduEntryCount++;
      
    });
    
  }

  eduContainer.addEventListener('click', function (e) {
    if (e.target.closest('.delete-edu-btn')) {
      const block = e.target.closest('.education-entry');
      if (block) {
        block.style.display = 'none';
        const flag = block.querySelector('input[name="delete_flag[]"]');
        if (flag) flag.value = 'true';
      }
    }
  });


  // === EXPERIENCE SECTION ===
  let workEntryCount = <?= count($results['experience'] ?? []) ?>;
  const maxWorkEntries = 5;
  const workAddBtn = document.getElementById("add-experience-btn");
  const workTemplate = document.getElementById("experience-template").content;
  const workContainer = document.getElementById("experience-entries");

  if (workAddBtn) {
    workAddBtn.addEventListener("click", function () {
      if (workEntryCount >= maxWorkEntries) return;
      const clone = workTemplate.cloneNode(true);
      const collapseId = "job-collapse-new-" + workEntryCount;
      clone.querySelector('[data-bs-toggle]').setAttribute("data-bs-target", "#" + collapseId);
      clone.querySelector('.collapse').setAttribute("id", collapseId);
      workContainer.appendChild(clone);
      workEntryCount++;
    });
  }

  workContainer.addEventListener("click", function (e) {
    if (e.target.closest(".delete-experience-btn")) {
      const entry = e.target.closest(".experience-entry");
      entry.style.display = "none";
      const flag = entry.querySelector('input[name="delete_flag[]"]');
      if (flag) flag.value = "true";
    }
  });


  // === PROJECT SECTION ===
  const projectContainer = document.getElementById("project-entries");
  const projectTemplate = document.getElementById("project-template").content;
  const maxProjects = 5;
  let projectCount = projectContainer.children.length;

  const addProjectBtn = document.getElementById("add-project-btn");
  if (addProjectBtn) {
    addProjectBtn.addEventListener("click", function () {
      if (projectCount >= maxProjects) return;
      const clone = projectTemplate.cloneNode(true);
      const collapseId = "interest-collapse-new-" + projectCount;
      clone.querySelector('[data-bs-toggle]').setAttribute("data-bs-target", "#" + collapseId);
      clone.querySelector('.collapse').setAttribute("id", collapseId);
      projectContainer.appendChild(clone);
      projectCount++;
    });
  }

  projectContainer.addEventListener("click", function (e) {
    if (e.target.closest(".delete-project-btn")) {
      const block = e.target.closest(".project-entry");
      block.style.display = "none";
      const flag = block.querySelector('input[name="delete_flag[]"]');
      if (flag) flag.value = "true";
    }
  });


  // === APTITUDE SECTION ===
  const aptitudeContainer = document.getElementById("aptitude-entries");
  const aptitudeTemplate = document.getElementById("aptitude-template").content;
  const maxAptitudes = 5;
  let aptitudeCount = aptitudeContainer.children.length;

  const addAptitudeBtn = document.getElementById("add-aptitude-btn");
  if (addAptitudeBtn) {
    addAptitudeBtn.addEventListener("click", function () {
      if (aptitudeCount >= maxAptitudes) return;
      const clone = aptitudeTemplate.cloneNode(true);
      const collapseId = "aptitude-collapse-new-" + aptitudeCount;
      clone.querySelector('[data-bs-toggle]').setAttribute("data-bs-target", "#" + collapseId);
      clone.querySelector('.collapse').setAttribute("id", collapseId);
      aptitudeContainer.appendChild(clone);
      aptitudeCount++;
    });
  }

  aptitudeContainer.addEventListener("click", function (e) {
    if (e.target.closest(".delete-aptitude-btn")) {
      const block = e.target.closest(".aptitude-entry");
      block.style.display = "none";
      const flag = block.querySelector('input[name="delete_flag[]"]');
      if (flag) flag.value = "true";
    }
  });


  // === LANGUAGE SECTION ===
  const lanContainer = document.getElementById("language-entries");
  const lanTemplate = document.getElementById("language-template").content;
  const maxLanguages = 5;
  let lanCount = lanContainer.children.length;

  const lanAddBtn = document.getElementById("add-language-btn");
  if (lanAddBtn) {
    lanAddBtn.addEventListener("click", function () {
      if (lanCount >= maxLanguages) return;
      const clone = lanTemplate.cloneNode(true);
      const collapseId = "language-collapse-new-" + lanCount;
      clone.querySelector('[data-bs-toggle]').setAttribute("data-bs-target", "#" + collapseId);
      clone.querySelector('.collapse').setAttribute("id", collapseId);
      lanContainer.appendChild(clone);
      lanCount++;
    });
  }

  lanContainer.addEventListener("click", function (e) {
    if (e.target.closest(".delete-language-btn")) {
      const block = e.target.closest(".language-entry");
      block.style.display = "none";
      const flag = block.querySelector('input[name="delete_flag[]"]');
      if (flag) flag.value = "true";
    }
  });


  // === CUSTOM SECTION ===
  const customContainer = document.getElementById("custom-section-entries");
  const customTemplate = document.getElementById("custom-template").content;
  const maxCustomSections = 5;
  let customCount = customContainer.children.length;

  const addCustomBtn = document.getElementById("add-custom-btn");
  if (addCustomBtn) {
    addCustomBtn.addEventListener("click", function () {
      if (customCount >= maxCustomSections) return;
      const clone = customTemplate.cloneNode(true);
      const collapseId = "custom-collapse-new-" + customCount;
      clone.querySelector('[data-bs-toggle]').setAttribute("data-bs-target", "#" + collapseId);
      clone.querySelector('.collapse').setAttribute("id", collapseId);
      customContainer.appendChild(clone);
      customCount++;
    });
  }

  customContainer.addEventListener("click", function (e) {
    if (e.target.closest(".delete-custom-btn")) {
      const block = e.target.closest(".custom-section-entry");
      block.style.display = "none";
      const flag = block.querySelector('input[name="delete_flag[]"]');
      if (flag) flag.value = "true";
    }
  });

  // Initialize modules
  formToggle.init();
  profilePhotoModalPreview.init();
  profilePhotoDropdownPreview.init();
});


// === REMOVE OVERLAY ON LOAD ===
window.addEventListener('load', function() {
  const overlay = document.getElementById('loading-overlay');
  if (overlay) {
    overlay.style.transition = 'opacity 0.5s ease';
    overlay.style.opacity = 0;
    setTimeout(() => overlay.remove(), 500);
  }
});


// === CLOSE MODAL ON SUBMIT ===
function closeModalOnSubmit(e) {
  e.preventDefault();
  const form = e.target;
  const modal = bootstrap.Modal.getInstance(document.getElementById('profilePicModal'));

  form.querySelector('button[type="submit"]').disabled = true;
  form.submit();
  modal.hide();
}
</script>

</body>