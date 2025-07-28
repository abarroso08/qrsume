<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

include("head.php");

if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
     header("Location: login.php");
     exit();
}

$quick_access_tables = ['personalinfo', 'contactinfo', 'aptitudes', 'education', 'experience', 'interests', 'languages','custom_sections'];

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
?>

<body>

     <?php include("nav.php"); ?>
     <div class="row px-2">
               <!-- Static Sidebar -->
                <div class="col-md-3 position-fixed bg-white border-end p-3 d-none d-md-flex flex-column" 
                     style="left: 0; overflow-y: auto; ;z-index:0;">
                    <h4 class="text-center mb-3">Menu</h4>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active fw-semibold text-dark" href="/assets/profile.php#profile">
                                <i class="bi bi-person-circle me-2"></i> Profile
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link fw-semibold text-dark" href="/assets/profile.php#contact">
                                <i class="bi bi-envelope me-2"></i> Contact
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link fw-semibold text-dark" href="/assets/profile.php#education">
                                <i class="bi bi-book me-2"></i> Education
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link fw-semibold text-dark" href="/assets/profile.php#experience">
                                <i class="bi bi-briefcase me-2"></i> Experience
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link fw-semibold text-dark" href="/assets/profile.php#projects">
                                <i class="bi bi-star me-2"></i> Projects
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link fw-semibold text-dark" href="/assets/profile.php#aptitudes">
                                <i class="bi bi-lightbulb me-2"></i> Aptitudes
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link fw-semibold text-dark" href="/assets/profile.php#languages">
                                <i class="bi bi-translate me-2"></i> Languages
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link fw-semibold text-dark" href="/assets/profile.php#customSections">
                                <i class="bi bi-ui-checks me-2"></i> Custom Sections
                            </a>
                        </li>

                    </ul>
                </div>

                <div class="col-12 col-md-9 offset-md-3">
               <!-- Personal Information Form -->
               <div class="card mt-4">
                    <!-- Clickable Card Header -->
                    <div class="card-header d-flex justify-content-between align-items-center" 
                         data-bs-toggle="collapse" data-bs-target="#personalInfoCollapse" 
                         aria-expanded="true" aria-controls="personalInfoCollapse" style="cursor: pointer;">
                        <h2 class="mb-0">Personal Information</h2>
                        <i class="bi bi-chevron-down"></i> <!-- Bootstrap Icon for Toggle Indicator -->
                    </div>

                    <!-- Collapsible Card Body -->
                    <div id="personalInfoCollapse" class="collapse show">
                        <div class="card-body">

                         <!-- Loading Screen (Initially Hidden) -->
                         <div id="loading-overlay" style="display:none" class="alert alert-warning text-center">
                              <div class="spinner"></div>
                              <p>Processing your image, please wait...</p>
                         </div>


                         <!-- Image Upload Form -->
                            <!-- Modal -->
                            <div class="modal fade" id="profilePicModal" tabindex="-1" aria-labelledby="profilePicModalLabel" aria-hidden="true">
                              <div class="modal-dialog modal-dialog-centered">
                                <div class="modal-content">
                                  <form id="uploadForm" action="../assets/upload/upload_profile_picture.php" method="POST" enctype="multipart/form-data" class="needs-validation" novalidate onsubmit               ="closeModalOnSubmit(event)">
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
                                      <input type="hidden" name="referrer" value="../profile.php">
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

                         
                         <!-- Success Message -->
                         <?php if (isset($_GET['success_profile'])): ?>
                              <div class="alert alert-success text-center" role="alert">
                                   <?= htmlspecialchars($_GET['success_profile']) ?>
                              </div>
                         <?php endif; ?>
                         
                         <!-- Personal Information Form -->
                         <form method="POST" action="../assets/upload/upload_profile.php">
                              <input type="hidden" name="action" value="save_personalinfo">
                              <input type="hidden" name="id" value="<?= $personalinfo['id'] ?? '' ?>"> <!-- Hidden ID for update -->
                                   <div class="row mt-3">
                                        <!-- Profile Photo Section -->
                                        <div class="col-md-3 text-center">
                                            <img id="profile-photo"
                                            src="images/<?= htmlspecialchars($personalinfo['personal_photo'] ?? 'default.webp') ?>"
                                            alt="Profile Photo"
                                            class="img-fluid rounded">
                                            <button type="button" class="btn btn-primary m-2" data-bs-toggle="modal" data-bs-target="#profilePicModal">Edit Profile Picture</button>
                                        </div>
                                   <!-- Personal Information Fields -->
                                   <div class="col-md-9">
                                        <div class="mb-3">
                                             <label for="personal_name" class="form-label"><strong>Name</strong></label>
                                             <input type="text" id="personal_name" name="personal_name" class="form-control"
                                                  value="<?= htmlspecialchars($personalinfo['personal_name'] ?? '') ?>" required="">
                                        </div>

                                        <div class="mb-3">
                                             <label for="personal_lastname" class="form-label"><strong>Last Name</strong></label>
                                             <input type="text" id="personal_lastname" name="personal_lastname" class="form-control"
                                                  value="<?= htmlspecialchars($personalinfo['personal_lastname'] ?? '') ?>" required>
                                        </div>

                                        <div class="mb-3">
                                             <label for="personal_profession" class="form-label"><strong>Profession</strong></label>
                                             <input type="text" id="personal_profession" name="personal_profession" class="form-control"
                                                  value="<?= htmlspecialchars($personalinfo['personal_profession'] ?? '') ?>" required>
                                        </div>

                                        <div class="mb-3">
                                             <label for="personal_bio" class="form-label"><strong>Bio</strong></label>
                                             <textarea id="personal_bio" name="personal_bio" class="form-control"><?= htmlspecialchars($personalinfo['personal_bio'] ?? '') ?></textarea>
                                        </div>

                                        <!-- Hidden CV URL Field -->
                                        <input type="hidden" name="cv_url" value="<?= htmlspecialchars($personalinfo['cv_url'] ?? 'cv_url') ?>">


                                   </div>
                                   <!-- Save Button -->
                                   <div class="d-flex justify-content align-items-end flex-column">
                                        <button type="submit" class="btn btn-primary w-25">Save</button>
                                   </div>
                              </div>
                         </form>
                    </div>
                    </div>
               </div>

               <!-- Contact Information Form -->
               <div class="card mt-4" id="contact">
                    <div class="card-header d-flex justify-content-between align-items-center" 
                         data-bs-toggle="collapse" data-bs-target="#contactInfoCollapse" 
                         aria-expanded="true" aria-controls="contactInfoCollapse" style="cursor: pointer;">
                        <h2 class="mb-0">Contact Information</h2>
                        <i class="bi bi-chevron-down"></i> <!-- Bootstrap Icon for Toggle Indicator -->
                    </div>
                    
                    <div id="contactInfoCollapse" class="collapse show">
                        <div class="card-body">
    
                             <!-- Success Message -->
                             <?php if (isset($_GET['success_contact'])): ?>
                                  <div class="alert alert-success text-center" role="alert">
                                       <?= htmlspecialchars($_GET['success_contact']) ?>
                                  </div>
                             <?php endif; ?>
    
                             <form method="POST" action="../assets/upload/upload_contact.php">
                                  <input type="hidden" name="action" value="save_contactinfo">
                                  <input type="hidden" name="user_id" value="<?= $_SESSION['id'] ?? '' ?>"> <!-- Ensure user ID is set -->
    
                                  <div class="row mt-3">
                                       <div class="col-md-6 mb-3">
                                            <label for="phone_number" class="form-label"><strong>Phone Number</strong></label>
                                            <input type="text" id="phone_number" name="phone_number" class="form-control"
                                                 value="<?= htmlspecialchars($contactinfo['phone_number'] ?? '') ?>">
                                       </div>
    
                                       <div class="col-md-6 mb-3">
                                            <label for="email" class="form-label"><strong>Email</strong></label>
                                            <input type="email" id="email" name="email" class="form-control"
                                                 value="<?= htmlspecialchars($contactinfo['email'] ?? '') ?>">
                                       </div>
    
                                       <div class="col-md-6 mb-3">
                                            <label for="github" class="form-label"><strong>GitHub</strong></label>
                                            <input type="url" id="github" name="github" class="form-control"
                                                 placeholder="https://"
                                                 value="<?= htmlspecialchars(!empty($contactinfo['github']) ? $contactinfo['github'] : 'https://') ?>">
                                       </div>
    
                                       <div class="col-md-6 mb-3">
                                            <label for="facebook" class="form-label"><strong>Facebook</strong></label>
                                            <input type="url" id="facebook" name="facebook" class="form-control"
                                                 placeholder="https://"
                                                 value="<?= htmlspecialchars($contactinfo['facebook'] ?? '') ?>">
                                       </div>
    
                                       <div class="col-md-6 mb-3">
                                            <label for="linkedin" class="form-label"><strong>LinkedIn</strong></label>
                                            <input type="url" id="linkedin" name="linkedin" class="form-control"
                                                 value="<?= htmlspecialchars($contactinfo['linkedin'] ?? '') ?>"
                                                 placeholder="https://">
                                       </div>
    
                                       <div class="col-md-6 mb-3">
                                            <label for="twitter" class="form-label"><strong>Twitter</strong></label>
                                            <input type="url" id="twitter" name="twitter" class="form-control"
                                                 placeholder="https://"
                                                 value="<?= htmlspecialchars($contactinfo['twitter'] ?? '') ?>">
                                       </div>
    
                                       <!-- Save Button -->
                                       <div class="d-flex justify-content align-items-end flex-column">
                                            <button type="submit" class="btn btn-primary w-25">Save</button>
                                       </div>
    
                                  </div>
                             </form>
                        </div>
                    </div>
               </div>

               <!-- Education Information Section -->
               <div class="card mt-4" id="education">
                   <div class="card-header d-flex justify-content-between align-items-center" 
                         data-bs-toggle="collapse" data-bs-target="#educationInfoCollapse" 
                         aria-expanded="true" aria-controls="educationInfoCollapse" style="cursor: pointer;">
                        <h2 class="mb-0">Education Information</h2>
                        <i class="bi bi-chevron-down"></i> <!-- Bootstrap Icon for Toggle Indicator -->
                    </div>
                    <div id="educationInfoCollapse" class="collapse show">
                    <div class="card-body">

                         <!-- Success Message -->
                         <?php if (isset($_GET['success_education'])): ?>
                              <div class="alert alert-success text-center" role="alert">
                                   <?= htmlspecialchars($_GET['success_education']) ?>
                              </div>
                         <?php endif; ?>

                         <!-- Display Existing Education Entries -->
                         <?php if (!empty($results['education'])): ?>
                              <?php foreach ($results['education'] as $edu): ?>
                                   <!-- Update Form -->
                                   <form method="POST" action="../assets/upload/upload_education.php" class="education-form d-flex flex-column">
                                        <input type="hidden" name="action" value="save_educationinfo">
                                        <input type="hidden" name="education_id" value="<?= htmlspecialchars($edu['education_id']) ?>">
                                        <input type="hidden" name="user_id" value="<?= $_SESSION['id'] ?? '' ?>">

                                        <div class="row mt-3">
                                             <div class="col-md-6 mb-3">
                                                  <label for="date_<?= $edu['education_id'] ?>" class="form-label"><strong>Date</strong></label>
                                                  <input type="text" id="date_<?= $edu['education_id'] ?>" name="date" class="form-control"
                                                       value="<?= htmlspecialchars($edu['date'] ?? '') ?>" placeholder="e.g., 2020-2022">
                                             </div>

                                             <div class="col-md-6 mb-3">
                                                  <label for="place_of_study_<?= $edu['education_id'] ?>" class="form-label"><strong>Place of Study</strong></label>
                                                  <input type="text" id="place_of_study_<?= $edu['education_id'] ?>" name="place_of_study" class="form-control"
                                                       value="<?= htmlspecialchars($edu['place_of_study'] ?? '') ?>">
                                             </div>

                                             <div class="col-md-6 mb-3">
                                                  <label for="name_of_studies_<?= $edu['education_id'] ?>" class="form-label"><strong>Name of Studies</strong></label>
                                                  <input type="text" id="name_of_studies_<?= $edu['education_id'] ?>" name="name_of_studies" class="form-control"
                                                       value="<?= htmlspecialchars($edu['name_of_studies'] ?? '') ?>">
                                             </div>

                                             <div class="col-md-6 mb-3">
                                                  <label for="brief_description_<?= $edu['education_id'] ?>" class="form-label"><strong>Brief Description</strong></label>
                                                  <textarea id="brief_description_<?= $edu['education_id'] ?>" name="brief_description" class="form-control"><?= htmlspecialchars($edu['brief_description'] ?? '') ?></textarea>
                                             </div>
                                        </div>

                                        <!-- Buttons (Update & Delete) -->
                                        <div class="d-flex justify-content-end">
                                             <!-- Update Button -->
                                             <button type="submit" class="btn btn-success m-2">Update</button>


                                        </div>
                                   </form>
                                   <!-- Delete Button (Separate Form) -->
                                   <div class="w-100 d-flex justify-content-end">
                                        <form method="POST" action="../assets/upload/upload_education.php" class="m-2">
                                             <input type="hidden" name="action" value="delete_education">
                                             <input type="hidden" name="education_id" value="<?= htmlspecialchars($edu['education_id']) ?>">
                                             <button type="submit" class="btn btn-danger"style="position:relative;bottom:53px; right:80px;">Delete</button>
                                        </form>
                                   </div>

                                   <hr>


                              <?php endforeach; ?>
                         <?php endif; ?>

                         <!-- New Education Form (Hidden if 5 entries exist) -->
                         <?php if (count($results['education']) < 5): ?>
                              <form method="POST" action="../assets/upload/upload_education.php" id="new-education-form" class="education-form" style="display:none;">
                                   <input type="hidden" name="action" value="save_educationinfo">
                                   <input type="hidden" name="user_id" value="<?= $_SESSION['id'] ?? '' ?>">

                                   <div class="row mt-3">
                                        <div class="col-md-6 mb-3">
                                             <label for="new_date" class="form-label"><strong>Date</strong></label>
                                             <input type="text" id="new_date" name="date" class="form-control" placeholder="e.g., 2020-2022">
                                        </div>

                                        <div class="col-md-6 mb-3">
                                             <label for="new_place_of_study" class="form-label"><strong>Place of Study</strong></label>
                                             <input type="text" id="new_place_of_study" name="place_of_study" class="form-control">
                                        </div>

                                        <div class="col-md-6 mb-3">
                                             <label for="new_name_of_studies" class="form-label"><strong>Name of Studies</strong></label>
                                             <input type="text" id="new_name_of_studies" name="name_of_studies" class="form-control">
                                        </div>

                                        <div class="col-md-6 mb-3">
                                             <label for="new_brief_description" class="form-label"><strong>Brief Description</strong></label>
                                             <textarea id="new_brief_description" name="brief_description" class="form-control"></textarea>
                                        </div>

                                        <!-- Save Button -->
                                        <div class="d-flex justify-content align-items-end flex-column">
                                             <button type="submit" class="btn btn-primary w-25">Add</button>
                                        </div>
                                   </div>
                              </form>

                              <!-- Add New Education Button -->
                              <div class="d-flex justify-content-end mt-3">
                                   <button id="add-education-btn" class="btn btn-secondary">+ Add New Education</button>
                              </div>
                         <?php else: ?>
                              <p class="text-danger text-center mt-3">❌ You have reached the maximum of 5 education entries.</p>
                         <?php endif; ?>
                    </div>
                    <!-- Education Ideas Toggle -->
                        <p class="text-muted mt-2" style="cursor:pointer;" data-bs-toggle="collapse" data-bs-target="#educationExamples" aria-expanded="false" aria-controls="educationExamples">
                          💡 Need ideas? Click here to see examples you can copy.
                        </p>
                        
                        <div class="collapse" id="educationExamples">
                          <div class="card card-body bg-light border">
                        
                            <p><strong>📘 Business Administration</strong><br>
                            <strong>Date:</strong> 2019–2023<br>
                            <strong>Place of Study:</strong> University of Michigan<br>
                            <strong>Name of Studies:</strong> Bachelor in Business Administration (BBA)<br>
                            <strong>Description:</strong> Focused on management, marketing, finance, and organizational behavior. Participated in case competitions and completed internships in consulting                   and retail management.</p>
                            
                            <hr>
                        
                            <p><strong>💻 Computer Science</strong><br>
                            <strong>Date:</strong> 2020–2024<br>
                            <strong>Place of Study:</strong> MIT<br>
                            <strong>Name of Studies:</strong> BSc in Computer Science<br>
                            <strong>Description:</strong> Courses in algorithms, machine learning, cybersecurity, and web development. Developed several projects including a mobile app and a machine                   learning model for image classification.</p>
                        
                            <hr>
                        
                            <p><strong>🩺 Medicine</strong><br>
                            <strong>Date:</strong> 2017–2023<br>
                            <strong>Place of Study:</strong> University of São Paulo<br>
                            <strong>Name of Studies:</strong> MD in General Medicine<br>
                            <strong>Description:</strong> Six-year medical degree combining clinical practice, diagnostics, and research. Rotated through internal medicine, pediatrics, surgery, and public                     health departments.</p>
                        
                            <hr>
                        
                            <p><strong>⚙️ Mechanical Engineering</strong><br>
                            <strong>Date:</strong> 2018–2022<br>
                            <strong>Place of Study:</strong> TU Delft<br>
                            <strong>Name of Studies:</strong> Bachelor in Mechanical Engineering<br>
                            <strong>Description:</strong> Studied thermodynamics, materials science, and control systems. Worked on robotics and sustainable energy projects, and completed an internship in              automotive design.</p>
                        
                            <hr>
                        
                            <p><strong>🧠 Psychology</strong><br>
                            <strong>Date:</strong> 2021–2025<br>
                            <strong>Place of Study:</strong> University of Toronto<br>
                            <strong>Name of Studies:</strong> BA in Psychology<br>
                            <strong>Description:</strong> Studying cognitive psychology, behavior analysis, and mental health. Participated in research on attention span in adolescents and volunteered in                      peer support programs.</p>
                            
                          </div>
                        </div>

                    </div>
               </div>


               <!-- Work Experience Section -->
               <div class="card mt-4" id="experience">
                    <div class="card-header">
                         <h2>Work Experience</h2>
                    </div>
                    <div class="card-body">

                         <!-- Success Message -->
                         <?php if (isset($_GET['success_experience'])): ?>
                              <div class="alert alert-success text-center" role="alert">
                                   <?= htmlspecialchars($_GET['success_experience']) ?>
                              </div>
                         <?php endif; ?>

                         <!-- Display Existing Work Experience Entries -->
                         <?php if (!empty($results['experience'])): ?>
                              <?php foreach ($results['experience'] as $job): ?>
                                   <!-- Update Form -->
                                   <form method="POST" action="../assets/upload/upload_experience.php" class="experience-form d-flex flex-column">
                                        <input type="hidden" name="action" value="save_experience">
                                        <input type="hidden" name="experience_id" value="<?= htmlspecialchars($job['experience_id']) ?>">
                                        <input type="hidden" name="user_id" value="<?= $_SESSION['id'] ?? '' ?>">

                                        <div class="row mt-3">
                                             <div class="col-md-6 mb-3">
                                                  <label for="date_<?= $job['experience_id'] ?>" class="form-label"><strong>Date</strong></label>
                                                  <input type="text" id="date_<?= $job['experience_id'] ?>" name="date" class="form-control"
                                                       value="<?= htmlspecialchars($job['date'] ?? '') ?>" placeholder="e.g., 2022-present">
                                             </div>

                                             <div class="col-md-6 mb-3">
                                                  <label for="place_of_work_<?= $job['experience_id'] ?>" class="form-label"><strong>Place of Work</strong></label>
                                                  <input type="text" id="place_of_work_<?= $job['experience_id'] ?>" name="place_of_work" class="form-control"
                                                       value="<?= htmlspecialchars($job['place_of_work'] ?? '') ?>">
                                             </div>

                                             <div class="col-md-6 mb-3">
                                                  <label for="job_name_<?= $job['experience_id'] ?>" class="form-label"><strong>Job Name</strong></label>
                                                  <input type="text" id="job_name_<?= $job['experience_id'] ?>" name="job_name" class="form-control"
                                                       value="<?= htmlspecialchars($job['job_name'] ?? '') ?>">
                                             </div>

                                             <div class="col-md-6 mb-3">
                                                  <label for="brief_description_<?= $job['experience_id'] ?>" class="form-label"><strong>Brief Description</strong></label>
                                                  <textarea id="brief_description_<?= $job['experience_id'] ?>" name="brief_description" class="form-control"><?= htmlspecialchars($job['brief_description'] ?? '') ?></textarea>
                                             </div>
                                        </div>

                                        <!-- Buttons (Update & Delete) -->
                                        <div class="d-flex justify-content-end">
                                             <!-- Update Button -->
                                             <button type="submit" class="btn btn-success m-2">Update</button>
                                        </div>
                                   </form>

                                   <!-- Delete Button (Separate Form) -->
                                   <div class="w-100 d-flex justify-content-end">
                                        <form method="POST" action="../assets/upload/upload_experience.php" class="m-2">
                                             <input type="hidden" name="action" value="delete_experience">
                                             <input type="hidden" name="experience_id" value="<?= htmlspecialchars($job['experience_id']) ?>">
                                             <button type="submit" class="btn btn-danger" style="position:relative;bottom:53px; right:80px;">Delete</button>
                                        </form>
                                   </div>
                                   <hr>
                              <?php endforeach; ?>
                         <?php endif; ?>

                         <!-- New Work Experience Form (Hidden if 5 entries exist) -->
                         <?php if (count($results['experience']) < 5): ?>
                              <form method="POST" action="../assets/upload/upload_experience.php" id="new-experience-form" class="experience-form" style="display:none;">
                                   <input type="hidden" name="action" value="save_experience">
                                   <input type="hidden" name="user_id" value="<?= $_SESSION['id'] ?? '' ?>">

                                   <div class="row mt-3">
                                        <div class="col-md-6 mb-3">
                                             <label for="new_date" class="form-label"><strong>Date</strong></label>
                                             <input type="text" id="new_date" name="date" class="form-control" placeholder="e.g., 2022-present">
                                        </div>

                                        <div class="col-md-6 mb-3">
                                             <label for="new_place_of_work" class="form-label"><strong>Place of Work</strong></label>
                                             <input type="text" id="new_place_of_work" name="place_of_work" class="form-control">
                                        </div>

                                        <div class="col-md-6 mb-3">
                                             <label for="new_job_name" class="form-label"><strong>Job Name</strong></label>
                                             <input type="text" id="new_job_name" name="job_name" class="form-control">
                                        </div>

                                        <div class="col-md-6 mb-3">
                                             <label for="new_brief_description" class="form-label"><strong>Brief Description</strong></label>
                                             <textarea id="new_brief_description" name="brief_description" class="form-control"></textarea>
                                        </div>

                                        <!-- Save Button -->
                                        <div class="d-flex justify-content align-items-end flex-column">
                                             <button type="submit" class="btn btn-primary w-25">Add</button>
                                        </div>
                                   </div>
                              </form>

                              <!-- Add New Work Experience Button -->
                              <div class="d-flex justify-content-end mt-3">
                                   <button id="add-experience-btn" class="btn btn-secondary">+ Add New Work Experience</button>
                              </div>
                         <?php else: ?>
                              <p class="text-danger text-center mt-3">❌ You have reached the maximum of 5 work experience entries.</p>
                         <?php endif; ?>
                    </div>
                    <!-- Work Experience Ideas Toggle -->
                    <p class="text-muted mt-2" style="cursor:pointer;" data-bs-toggle="collapse" data-bs-target="#experienceExamples" aria-expanded="false" aria-controls="experienceExamples">
                      💡 Need ideas? Click here to see examples you can copy.
                    </p>
                    
                    <div class="collapse" id="experienceExamples">
                      <div class="card card-body bg-light border">
                    
                        <p><strong>☕ Barista</strong><br>
                        <strong>Place of Work:</strong> Starbucks<br>
                        <strong>Date:</strong> 2022–2023<br>
                        <strong>Description:</strong> Provided customer service in a fast-paced environment, prepared drinks and maintained cleanliness standards. Developed time management and                  multitasking skills under pressure.</p>
                    
                        <hr>
                    
                        <p><strong>🖥️ IT Support Assistant</strong><br>
                        <strong>Place of Work:</strong> University Tech Center<br>
                        <strong>Date:</strong> 2023–Present<br>
                        <strong>Description:</strong> Assisted students and faculty with technical issues, managed software updates and hardware troubleshooting. Gained experience in help desk                   protocols and team collaboration.</p>
                    
                        <hr>
                    
                        <p><strong>📚 Peer Tutor</strong><br>
                        <strong>Place of Work:</strong> Academic Success Center<br>
                        <strong>Date:</strong> 2021–2023<br>
                        <strong>Description:</strong> Tutored undergraduate students in calculus and statistics. Customized learning plans and improved students’ academic performance by over 15% on                 average.</p>
                    
                        <hr>
                    
                        <p><strong>🛍️ Retail Sales Associate</strong><br>
                        <strong>Place of Work:</strong> H&M<br>
                        <strong>Date:</strong> 2022 (Summer)<br>
                        <strong>Description:</strong> Assisted customers, managed stock and organized the sales floor. Learned customer service, upselling techniques, and point-of-sale systems.</p>
                    
                        <hr>
                    
                        <p><strong>🧑‍🍳 Food Service Worker</strong><br>
                        <strong>Place of Work:</strong> Campus Dining Hall<br>
                        <strong>Date:</strong> 2021–2022<br>
                        <strong>Description:</strong> Prepared meals, maintained food safety standards, and cleaned work areas. Built teamwork and punctuality in a structured kitchen environment.</p>
                    
                      </div>
                    </div>

               </div>

               <!-- Interests Section -->
               <div class="card mt-4" id="projects">
                    <div class="card-header">
                         <h2>Projects</h2>
                    </div>
                    <div class="card-body">

                         <!-- Success Message -->
                         <?php if (isset($_GET['success_interest'])): ?>
                              <div class="alert alert-success text-center" role="alert">
                                   <?= htmlspecialchars($_GET['success_interest']) ?>
                              </div>
                         <?php endif; ?>

                         <!-- Display Existing Interests -->
                         <?php if (!empty($results['interests'])): ?>
                              <?php foreach ($results['interests'] as $interest): ?>
                                   <div class="interest-entry mb-3 p-3 border rounded">
                                        <!-- Update Form -->
                                        <form method="POST" action="../assets/upload/upload_interest.php" class="interest-form">
                                             <input type="hidden" name="action" value="save_interest">
                                             <input type="hidden" name="interest_id" value="<?= htmlspecialchars($interest['interest_id']) ?>">
                                             <input type="hidden" name="user_id" value="<?= $_SESSION['id'] ?? '' ?>">

                                             <div class="row">
                                                  <div class="col-md-6 mb-3">
                                                       <label for="interest_<?= $interest['interest_id'] ?>" class="form-label"><strong>Project name</strong></label>
                                                       <input type="text" id="interest_<?= $interest['interest_id'] ?>" name="interest" class="form-control"
                                                            value="<?= htmlspecialchars($interest['interest'] ?? '') ?>" placeholder="e.g., Data Science">
                                                  </div>

                                                  <div class="col-md-6 mb-3">
                                                       <label for="description_<?= $interest['interest_id'] ?>" class="form-label"><strong>Description</strong></label>
                                                       <textarea id="description_<?= $interest['interest_id'] ?>" name="description" class="form-control"><?= htmlspecialchars($interest['description'] ?? '') ?></textarea>
                                                  </div>
                                             </div>

                                             <!-- Update Button -->
                                             <div class="d-flex justify-content-end">
                                                  <button type="submit" class="btn btn-success m-2">Update</button>
                                             </div>
                                        </form>

                                        <!-- Delete Button (Separate Form) -->
                                        <div class="w-100 d-flex justify-content-end">
                                             <form method="POST" action="../assets/upload/upload_interest.php" class="m-2">
                                                  <input type="hidden" name="action" value="delete_interest">
                                                  <input type="hidden" name="interest_id" value="<?= htmlspecialchars($interest['interest_id']) ?>">
                                                  <button type="submit" class="btn btn-danger" style="position:relative;bottom:53px; right:80px;">Delete</button>
                                             </form>
                                        </div>
                                   </div>
                              <?php endforeach; ?>
                         <?php endif; ?>

                         <!-- New Interest Form (Hidden if 5 entries exist) -->
                         <?php if (count($results['interests']) < 5): ?>
                              <form method="POST" action="../assets/upload/upload_interest.php" id="new-interest-form" class="interest-form" style="display:none;">
                                   <input type="hidden" name="action" value="save_interest">
                                   <input type="hidden" name="user_id" value="<?= $_SESSION['id'] ?? '' ?>">

                                   <div class="row mt-3">
                                        <div class="col-md-6 mb-3">
                                             <label for="new_interest" class="form-label"><strong>Project name</strong></label>
                                             <input type="text" id="new_interest" name="interest" class="form-control" placeholder="e.g., AI & Machine Learning">
                                        </div>

                                        <div class="col-md-6 mb-3">
                                             <label for="new_description" class="form-label"><strong>Description</strong></label>
                                             <textarea id="new_description" name="description" class="form-control"></textarea>
                                        </div>

                                        <!-- Save Button -->
                                        <div class="d-flex justify-content-end">
                                             <button type="submit" class="btn btn-primary">Add</button>
                                        </div>
                                   </div>
                              </form>

                              <!-- Add New Interest Button -->
                              <div class="d-flex justify-content-end mt-3">
                                   <button id="add-interest-btn" class="btn btn-secondary">+ Add New project</button>
                              </div>
                         <?php else: ?>
                              <p class="text-danger text-center mt-3">❌ You have reached the maximum of 5 projects.</p>
                         <?php endif; ?>
                    </div>
                    <!-- Project Examples Toggle -->
                    <p class="text-muted mt-2" style="cursor:pointer;" data-bs-toggle="collapse" data-bs-target="#projectExamples" aria-expanded="false" aria-controls="projectExamples">
                      💡 Need inspiration? Click here to view project examples.
                    </p>
                    
                    <div class="collapse" id="projectExamples">
                      <div class="card card-body bg-light border">
                    
                        <p><strong>📊 Predictive Modeling for Business</strong><br>
                        <strong>Description:</strong> Developed predictive models using Python to forecast sales trends and customer retention using datasets from Kaggle. Applied regression and              classification algorithms for actionable business insights.</p>
                    
                        <hr>
                    
                        <p><strong>🖥️ Portfolio Website</strong><br>
                        <strong>Description:</strong> Designed and coded a personal website using HTML, CSS, and JavaScript. Showcases my projects, resume, and contact form with responsive layout and               interactive elements.</p>
                    
                        <hr>
                    
                        <p><strong>📱 App for Campus Navigation</strong><br>
                        <strong>Description:</strong> Created a mobile app using Flutter that helps students navigate campus buildings, find classrooms, and locate available study spaces in real time                 using GPS and QR codes.</p>
                    
                        <hr>
                    
                        <p><strong>🧠 Mental Health Awareness Campaign</strong><br>
                        <strong>Description:</strong> Led a digital marketing campaign to promote mental health among students. Collaborated with psychology department to create infographics and run                    weekly awareness posts across social media.</p>
                    
                        <hr>
                    
                        <p><strong>📦 Supply Chain Optimization Case Study</strong><br>
                        <strong>Description:</strong> Collaborated in a group project simulating logistics optimization for an e-commerce company. Used linear programming and Excel Solver to reduce            distribution costs by 15%.</p>
                    
                      </div>
                    </div>

               </div>


               <!-- Aptitudes Section -->
               <div class="card mt-4" id="aptitudes">
                    <div class="card-header">
                         <h2>Aptitudes</h2>
                    </div>
                    <div class="card-body">

                         <!-- Success Message -->
                         <?php if (isset($_GET['success_aptitude'])): ?>
                              <div class="alert alert-success text-center" role="alert">
                                   <?= htmlspecialchars($_GET['success_aptitude']) ?>
                              </div>
                         <?php endif; ?>

                         <!-- Display Existing Aptitudes in Rows of 2 -->
                         <div class="row">
                              <?php if (!empty($results['aptitudes'])): ?>
                                   <?php foreach ($results['aptitudes'] as $index => $aptitude): ?>
                                        <div class="col-md-6">
                                             <div class="aptitude-entry mb-3 p-3 border rounded">
                                                  <!-- Update Form -->
                                                  <form method="POST" action="../assets/upload/upload_aptitude.php" class="aptitude-form">
                                                       <input type="hidden" name="action" value="save_aptitude">
                                                       <input type="hidden" name="aptitude_id" value="<?= htmlspecialchars($aptitude['aptitude_id']) ?>">
                                                       <input type="hidden" name="user_id" value="<?= $_SESSION['id'] ?? '' ?>">

                                                       <div class="mb-3">
                                                            <label for="aptitude_<?= $aptitude['aptitude_id'] ?>" class="form-label"><strong>Aptitude</strong></label>
                                                            <input type="text" id="aptitude_<?= $aptitude['aptitude_id'] ?>" name="aptitude" class="form-control"
                                                                 value="<?= htmlspecialchars($aptitude['aptitude'] ?? '') ?>" placeholder="e.g., Leadership">
                                                       </div>

                                                       <!-- Update Button -->
                                                       <div class="d-flex justify-content-end">
                                                            <button type="submit" class="btn btn-success">Update</button>
                                                       </div>
                                                  </form>

                                                  <!-- Delete Button (Separate Form) -->
                                                  <div class="d-flex justify-content-end mt-2">
                                                       <form method="POST" action="../assets/upload/upload_aptitude.php">
                                                            <input type="hidden" name="action" value="delete_aptitude">
                                                            <input type="hidden" name="aptitude_id" value="<?= htmlspecialchars($aptitude['aptitude_id']) ?>">
                                                            <button type="submit" class="btn btn-danger" >Delete</button>
                                                       </form>
                                                  </div>
                                             </div>
                                        </div>

                                        <!-- Close row and open a new one every 2 items -->
                                        <?php if (($index + 1) % 2 == 0): ?>
                         </div>
                         <div class="row">
                         <?php endif; ?>
                    <?php endforeach; ?>
               <?php endif; ?>
                         </div>


                         <!-- New Aptitude Form (Hidden if 5 entries exist) -->
                         <?php if (count($results['aptitudes']) < 5): ?>
                              <form method="POST" action="../assets/upload/upload_aptitude.php" id="new-aptitude-form" class="aptitude-form" style="display:none;">
                                   <input type="hidden" name="action" value="save_aptitude">
                                   <input type="hidden" name="user_id" value="<?= $_SESSION['id'] ?? '' ?>">

                                   <div class="row mt-3">
                                        <div class="col-md-12 mb-3">
                                             <label for="new_aptitude" class="form-label"><strong>Aptitude</strong></label>
                                             <input type="text" id="new_aptitude" name="aptitude" class="form-control" placeholder="e.g., Communication Skills">
                                        </div>

                                        <!-- Save Button -->
                                        <div class="d-flex justify-content-end">
                                             <button type="submit" class="btn btn-primary">Add</button>
                                        </div>
                                   </div>
                              </form>

                              <!-- Add New Aptitude Button -->
                              <div class="d-flex justify-content-end mt-3">
                                   <button id="add-aptitude-btn" class="btn btn-secondary">+ Add New Aptitude</button>
                              </div>
                         <?php else: ?>
                              <p class="text-danger text-center mt-3">❌ You have reached the maximum of 5 aptitudes.</p>
                         <?php endif; ?>
                    </div>
                    <!-- Aptitude Suggestions -->
                    <p class="text-muted mt-2" style="cursor:pointer;" data-bs-toggle="collapse" data-bs-target="#aptitudeExamples" aria-expanded="false" aria-controls="aptitudeExamples">
                      💡 Not sure what to write? Click to see example aptitudes.
                    </p>
                    
                    <div class="collapse" id="aptitudeExamples">
                      <div class="card card-body bg-light border">
                        <p>🧠 <strong>Critical Thinking</strong><br>Ability to analyze problems logically and make sound decisions in complex situations.</p>
                        <hr>
                        <p>🗣️ <strong>Communication Skills</strong><br>Confidently presenting ideas, listening actively, and writing clearly in team or individual settings.</p>
                        <hr>
                        <p>🤝 <strong>Teamwork and Collaboration</strong><br>Working effectively with others in diverse groups to achieve common goals.</p>
                        <hr>
                        <p>⏱️ <strong>Time Management</strong><br>Effectively prioritizing tasks and meeting deadlines, especially under pressure.</p>
                        <hr>
                        <p>🎯 <strong>Adaptability and Flexibility</strong><br>Quickly adjusting to new tasks, challenges, or team environments with a positive attitude.</p>
                      </div>
                    </div>

               </div>



               <!-- Languages Section -->
               <div class="card mt-4" id="languages">
                    <div class="card-header">
                         <h2>Languages</h2>
                    </div>
                    <div class="card-body">

                         <!-- Success Message -->
                         <?php if (isset($_GET['success_language'])): ?>
                              <div class="alert alert-success text-center" role="alert">
                                   <?= htmlspecialchars($_GET['success_language']) ?>
                              </div>
                         <?php endif; ?>

                         <!-- Display Existing Languages in Rows of 2 -->
                         <div class="row">
                              <?php if (!empty($results['languages'])): ?>
                                   <?php foreach ($results['languages'] as $index => $language): ?>
                                        <div class="col-md-6">
                                             <div class="language-entry mb-3 p-3 border rounded">
                                                  <!-- Update Form -->
                                                  <form method="POST" action="../assets/upload/upload_language.php" class="language-form">
                                                       <input type="hidden" name="action" value="save_language">
                                                       <input type="hidden" name="languages_id" value="<?= htmlspecialchars($language['languages_id']) ?>">
                                                       <input type="hidden" name="user_id" value="<?= $_SESSION['id'] ?? '' ?>">

                                                       <div class="mb-3">
                                                            <label for="language_<?= $language['languages_id'] ?>" class="form-label"><strong>Language</strong></label>
                                                            <input type="text" id="language_<?= $language['languages_id'] ?>" name="language" class="form-control"
                                                                 value="<?= htmlspecialchars($language['language'] ?? '') ?>" placeholder="e.g., French">
                                                       </div>

                                                       <div class="mb-3">
                                                            <label for="level_<?= $language['languages_id'] ?>" class="form-label"><strong>Level</strong></label>
                                                            <input type="text" id="level_<?= $language['languages_id'] ?>" name="level" class="form-control"
                                                                 value="<?= htmlspecialchars($language['level'] ?? '') ?>" placeholder="e.g., B2">
                                                       </div>

                                                       <!-- Update Button -->
                                                       <div class="d-flex justify-content-end">
                                                            <button type="submit" class="btn btn-success">Update</button>
                                                       </div>
                                                  </form>

                                                  <!-- Delete Button (Separate Form) -->
                                                  <div class="d-flex justify-content-end mt-2">
                                                       <form method="POST" action="../assets/upload/upload_language.php">
                                                            <input type="hidden" name="action" value="delete_language">
                                                            <input type="hidden" name="languages_id" value="<?= htmlspecialchars($language['languages_id']) ?>">
                                                            <button type="submit" class="btn btn-danger">Delete</button>
                                                       </form>
                                                  </div>
                                             </div>
                                        </div>

                                        <!-- Close row and open a new one every 2 items -->
                                        <?php if (($index + 1) % 2 == 0): ?>
                         </div>
                         <div class="row">
                         <?php endif; ?>
                    <?php endforeach; ?>
               <?php endif; ?>
                         </div>

                         <!-- New Language Form (Hidden if 5 entries exist) -->
                         <?php if (count($results['languages']) < 5): ?>
                              <form method="POST" action="../assets/upload/upload_language.php" id="new-language-form" class="language-form" style="display:none;">
                                   <input type="hidden" name="action" value="save_language">
                                   <input type="hidden" name="user_id" value="<?= $_SESSION['id'] ?? '' ?>">

                                   <div class="row mt-3">
                                        <div class="col-md-6 mb-3">
                                             <label for="new_language" class="form-label"><strong>Language</strong></label>
                                             <input type="text" id="new_language" name="language" class="form-control" placeholder="e.g., Italian">
                                        </div>

                                        <div class="col-md-6 mb-3">
                                             <label for="new_level" class="form-label"><strong>Level</strong></label>
                                             <input type="text" id="new_level" name="level" class="form-control" placeholder="e.g., A1">
                                        </div>

                                        <!-- Save Button -->
                                        <div class="d-flex justify-content-end">
                                             <button type="submit" class="btn btn-primary">Add</button>
                                        </div>
                                   </div>
                              </form>

                              <!-- Add New Language Button -->
                              <div class="d-flex justify-content-end mt-3">
                                   <button id="add-language-btn" class="btn btn-secondary">+ Add New Language</button>
                              </div>
                         <?php else: ?>
                              <p class="text-danger text-center mt-3">❌ You have reached the maximum of 5 languages.</p>
                         <?php endif; ?>
                    </div>
                    <!-- Language Suggestions -->
                    <p class="text-muted mt-2" style="cursor:pointer;" data-bs-toggle="collapse" data-bs-target="#languageExamples" aria-expanded="false" aria-controls="languageExamples">
                      💡 Not sure what to write? Click to see example languages and levels.
                    </p>
                    
                    <div class="collapse" id="languageExamples">
                      <div class="card card-body bg-light border">
                        <p><img src="https://flagcdn.com/us.svg" width="24" class="me-2"> <strong>English</strong> — C1 (Advanced)</p>
                        <hr>
                        <p><img src="https://flagcdn.com/es.svg" width="24" class="me-2"> <strong>Spanish</strong> — Native</p>
                        <hr>
                        <p><img src="https://flagcdn.com/fr.svg" width="24" class="me-2"> <strong>French</strong> — B1 (Intermediate)</p>
                        <hr>
                        <p><img src="https://flagcdn.com/de.svg" width="24" class="me-2"> <strong>German</strong> — A2 (Basic)</p>
                        <hr>
                        <p><img src="https://flagcdn.com/cn.svg" width="24" class="me-2"> <strong>Mandarin Chinese</strong> — Beginner</p>
                        <small class="text-muted">Use CEFR levels: A1, A2 (basic), B1, B2 (intermediate), C1, C2 (advanced).</small>
                      </div>
                    </div>


               </div>
               
               <!-- Custom Sections -->
               <div class="card mt-4" id="customSections">
  <div class="card-header d-flex justify-content-between align-items-center" 
       data-bs-toggle="collapse" data-bs-target="#customSectionsCollapse" 
       aria-expanded="true" aria-controls="customSectionsCollapse" style="cursor: pointer;">
    <h2 class="mb-0">Custom Sections</h2>
    <i class="bi bi-chevron-down"></i>
  </div>

  <div id="customSectionsCollapse" class="collapse show">
    <div class="card-body">

      <?php if (isset($_GET['success_custom_section'])): ?>
        <div class="alert alert-success text-center" role="alert">
          <?= htmlspecialchars($_GET['success_custom_section']) ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($results['custom_sections'])): ?>
        <?php foreach ($results['custom_sections'] as $section): ?>
          <!-- Update Form -->
          <form method="POST" action="https://qrsume.com/assets/upload/upload_custom_section.php" class="mb-3">
            <input type="hidden" name="action" value="save_custom_section">
            <input type="hidden" name="section_id" value="<?= $section['section_id'] ?>">
            <input type="hidden" name="user_id" value="<?= $_SESSION['id'] ?? '' ?>">

            <div class="mb-2">
              <label for="section_title_<?= $section['section_id'] ?>"><strong>Section Title</strong></label>
              <input type="text" name="section_title" class="form-control" id="section_title_<?= $section['section_id'] ?>" value="<?= htmlspecialchars($section['section_title']) ?>">
            </div>
            <div class="mb-2">
              <label for="section_content_<?= $section['section_id'] ?>"><strong>Section Content</strong></label>
              <textarea name="section_content" class="form-control" id="section_content_<?= $section['section_id'] ?>"><?= htmlspecialchars($section['section_content']) ?></textarea>
            </div>
            <div class="d-flex justify-content-end">
              <button type="submit" class="btn btn-success me-2">Update</button>
              <form method="POST" action="../assets/upload/upload_custom_section.php">
                <input type="hidden" name="action" value="delete_custom_section">
                <input type="hidden" name="section_id" value="<?= $section['section_id'] ?>">
                <button type="submit" class="btn btn-danger">Delete</button>
              </form>
            </div>
            <hr>
          </form>
        <?php endforeach; ?>
      <?php endif; ?>

      <!-- New Custom Section -->
      <form method="POST" action="https://qrsume.com/assets/upload/upload_custom_section.php" id="new-custom-section-form">
        <input type="hidden" name="action" value="save_custom_section">
        <input type="hidden" name="user_id" value="<?= $_SESSION['id'] ?? '' ?>">

        <div class="mb-2">
          <label for="new_section_title"><strong>Section Title</strong></label>
          <input type="text" name="section_title" class="form-control" id="new_section_title">
        </div>
        <div class="mb-2">
          <label for="new_section_content"><strong>Section Content</strong></label>
          <textarea name="section_content" class="form-control" id="new_section_content"></textarea>
        </div>

        <div class="d-flex justify-content-end">
          <button type="submit" class="btn btn-primary">Add</button>
        </div>
      </form>

    </div>
  </div>
</div>

            </div>

          </div>
     </div>
     
     
     <?php
     include("footer.php"); ?>
     <script>
         // Show preview
        document.getElementById('article_photo').addEventListener('change', function (e) {
          const file = e.target.files[0];
          const preview = document.getElementById('previewImage');
        
          if (file) {
            const reader = new FileReader();
            reader.onload = function (event) {
              preview.src = event.target.result;
              preview.classList.remove('d-none');
            };
            reader.readAsDataURL(file);
          } else {
            preview.src = "#";
            preview.classList.add('d-none');
          }
        });
        
        // Auto close modal on submit
        function closeModalOnSubmit(e) {
          e.preventDefault(); // Prevent default form submit
          const form = e.target;
          const modal = bootstrap.Modal.getInstance(document.getElementById('profilePicModal'));
        
          // Optional: you can add a spinner or disable the form to prevent double submit
          form.querySelector('button[type="submit"]').disabled = true;
        
          // Submit the form via JavaScript
          form.submit();
        
          // Hide the modal
          modal.hide();
        }
     </script>
     
     <script>
          document.getElementById("uploadForm").addEventListener("submit", function() {
               document.getElementById("loading-overlay").style.display = "flex";
          });
          document.getElementById("add-education-btn")?.addEventListener("click", function() {
               document.getElementById("new-education-form").style.display = "block";
               this.style.display = "none"; // Hide the button after showing the form
          });
          document.getElementById("add-interest-btn")?.addEventListener("click", function() {
               document.getElementById("new-interest-form").style.display = "block";
               this.style.display = "none"; // Hide the button after showing the form
          });
          document.getElementById("add-experience-btn")?.addEventListener("click", function() {
               document.getElementById("new-experience-form").style.display = "block";
               this.style.display = "none"; // Hide the button after showing the form
          });
          // Select the dropdown and profile image element
          let profilePhotoSelect = document.getElementById("profile_photo");
          let profilePhotoImg = document.getElementById("profile-photo");

          // Function to update the profile image
          profilePhotoSelect.addEventListener("change", function() {
               let selectedPhoto = this.value; // Get selected image value
               if (selectedPhoto) {
                    profilePhotoImg.src = "images/" + selectedPhoto; // Update profile image
               }
          });
          document.addEventListener("DOMContentLoaded", function() {
               let addAptitudeBtn = document.getElementById("add-aptitude-btn");
               if (addAptitudeBtn) {
                    addAptitudeBtn.addEventListener("click", function() {
                         document.getElementById("new-aptitude-form").style.display = "block";
                         this.style.display = "none"; // Hide the button after showing the form
                    });
               }
          });
          document.addEventListener("DOMContentLoaded", function() {
               let addLanguageBtn = document.getElementById("add-language-btn");
               if (addLanguageBtn) {
                    addLanguageBtn.addEventListener("click", function() {
                         document.getElementById("new-language-form").style.display = "block";
                         this.style.display = "none"; // Hide the button after showing the form
                    });
               }
          });
          
          
              
        
     </script>


</body>