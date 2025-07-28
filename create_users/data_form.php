<?php
include '../assets/head.php';

?>
<style>
     /* Simple styling for step indicator (optional) */
     .step-indicator {
          display: flex;
          justify-content: space-between;
          margin-bottom: 1.5rem;
     }

     .step-indicator .step {
          flex: 1;
          text-align: center;
          position: relative;
     }

     .step-indicator .step::before {
          content: "";
          height: 2px;
          background: #ddd;
          position: absolute;
          top: 20px;
          /* vertically center line relative to step circles */
          left: -50%;
          width: 100%;
          z-index: -1;
     }

     .step-indicator .step:first-child::before {
          display: none;
          /* no line before first step */
     }

     .step-indicator .step .step-circle {
          display: inline-block;
          width: 40px;
          height: 40px;
          line-height: 40px;
          border-radius: 50%;
          background: #ddd;
          color: #000;
          font-weight: 600;
     }

     .step-indicator .step.active .step-circle {
          background: #0d6efd;
          /* Bootstrap primary */
          color: #fff;
     }

     .step-indicator .step.completed .step-circle {
          background: #198754;
          /* Bootstrap success */
          color: #fff;
     }

     .step-indicator .step .label {
          display: block;
          margin-top: 0.5rem;
          font-size: 0.85rem;
          font-weight: 500;
     }

     /* Hide all steps by default */
     .form-step {
          display: none;
     }

     .form-step.active {
          display: block;
     }

     .skill-tag {
          background-color: #007bff;
          color: white;
          padding: 5px 10px;
          display: inline-flex;
          align-items: center;
          gap: 5px;
          cursor: pointer;
     }

     .skill-tag:hover {
          background-color: #0056b3;
     }

     .skill-tag span {
          font-size: 12px;
          margin-left: 5px;
          cursor: pointer;
     }
</style>
</head>

<body class="d-flex flex-column min-vh-100">
     <?php include '../assets/nav.php'; ?>
     <div class="container py-3">
          <h3 class="text-center mb-3">Create your profile</h3>

          <!-- Step Indicator -->
          <div class="step-indicator mb-2">
               <div  onclick="changeCurrent(0)" style="cursor:pointer;"  class="step active" data-step="1">
                    <div class="step-circle">1</div>
                    <span class="label">Basic Profile</span>
               </div>
               <div onclick="changeCurrent(1)" style="cursor:pointer;" class="step" data-step="2">
                    <div class="step-circle">2</div>
                    <span class="label">Location</span>
               </div>
               <div onclick="changeCurrent(2)" style="cursor:pointer;" class="step" data-step="3">
                    <div class="step-circle">3</div>
                    <span class="label">Work Experience</span>
               </div>
               <div onclick="changeCurrent(3)" style="cursor:pointer;" class="step" data-step="4">
                    <div class="step-circle">4</div>
                    <span class="label">Education and training</span>
               </div>
               <div onclick="changeCurrent(4)" style="cursor:pointer;" class="step" data-step="5">
                    <div class="step-circle">5</div>
                    <span class="label">Languages</span>
               </div>
               <div onclick="changeCurrent(5)" style="cursor:pointer;" class="step" data-step="6">
                    <div class="step-circle">6</div>
                    <span class="label">Extras</span>
               </div>
          </div>

          <!-- Form with POST action to viewhtml.php -->
          <div class="shadow-lg-lg p-3 mb-5 bg-body rounded">
               <form id="resumeForm" action="create_users/upload_data.php" method="POST">



                    <!-- Step 2: Basic Info -->
                    <div class="form-step active" data-step="1">
                         <div class="row gy-3 gx-4">
                              <div class="col-md-6">
                                   <label for="firstName" class="form-label">First Name <span class="text-danger">*</span></label>
                                   <input type="text" class="form-control" id="firstName" name="firstName" placeholder="e.g.John" required>
                              </div>
                              <div class="col-md-6">
                                   <label for="LastName" class="form-label">Last Name <span class="text-danger">*</span></label>
                                   <input type="text" class="form-control" id="LastName" name="LastName" placeholder="e.g.Doe" required>
                              </div>
                              <div class="col-6">
                                   <label for="aboutMe" class="form-label">About Me</label>
                                   <textarea class="form-control" id="aboutMe" name="aboutMe" placeholder="You can provide a description of yourself here..." rows="2"></textarea>
                              </div>
                              <div class="mb-3 col-md-6" id="profession-wrapper">
                                  <label for="profession" class="form-label">What is your current profession?</label>
                                  <select class="form-select" id="profession" name="personal_profession" required>
                                      <option value="">-- Please choose an option --</option>
                                      <option value="Student">Student</option>
                                      <option value="Other">Other</option>
                                  </select>
                              </div>
                         </div>
                         <div class="mt-4">
                              <button type="button" class="btn btn-primary" onclick="nextStep()">Next</button>
                         </div>
                    </div>

                    <!-- Step 2: How many years of work experience? -->
                    <!--
                    <div class="form-step active" data-step="2">
                         <div class="row gy-3 gx-5 mb-3">
                              <div class="col-md-6">
                                   <label for="city" class="form-label">City <span class="text-danger">*</span></label>
                                   <input type="text" class="form-control" id="city" name="city" required>
                              </div>
                              <div class="col-md-6">
                                   <label for="state" class="form-label">State <span class="text-danger">*</span></label>
                                   <input type="text" class="form-control" id="state" name="state" required>
                              </div>
                              <div class="col-md-6">
                                   <label for="zipCode" class="form-label">Zip Code <span class="text-danger">*</span></label>
                                   <input type="text" class="form-control" id="zipCode" name="zipCode" required>
                              </div>
                         </div>
                         <button type="button" class="btn btn-secondary me-2" onclick="prevStep()">Back</button>
                         <button type="button" class="btn btn-primary" onclick="nextStep()">Next</button>
                    </div>
                    -->

                    <!-- Step 3: Work Experience Details (Up to 3) -->
                    <div class="form-step" data-step="2">
                         <div id="workExperienceContainer">
                              <!-- Default Work Experience Group 1 -->
                              <div class="row gy-3 gx-5 work-group">
                                   <div class="col-md-6">
                                        <label for="jobTitle1" class="form-label">Occupation or position held <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="jobTitle1" name="jobTitle[]" required>
                                   </div>
                                   <div class="col-md-6">
                                        <label for="employer1" class="form-label">Employer <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="employer1" name="employer[]" required>
                                   </div>
                                   <div class="col-md-6">
                                        <label for="workCity1" class="form-label">City</label>
                                        <input type="text" class="form-control" id="workCity1" name="workCity[]">
                                   </div>
                                   <div class="col-md-6">
                                        <label for="workState1" class="form-label">State</label>
                                        <input type="text" class="form-control" id="workState1" name="workState[]">
                                   </div>
                                   <div class="col-md-6">
                                        <label for="startMonth1" class="form-label">Start Month <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="startMonth1" name="startMonth[]" placeholder="e.g., January" required>
                                   </div>
                                   <div class="col-md-6">
                                        <label for="startYear1" class="form-label">Start Year <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" id="startYear1" name="startYear[]" placeholder="e.g., 2020" required>
                                   </div>
                                   <div class="col-md-6">
                                        <label for="endMonth1" class="form-label">End Month <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="endMonth1" name="endMonth[]" placeholder="e.g., December" required>
                                   </div>
                                   <div class="col-md-6">
                                        <label for="endYear1" class="form-label">End Year <span class="text-danger">*</span></label>
                                        <input type="number" class="form-control" id="endYear1" name="endYear[]" placeholder="e.g., 2023" required>
                                   </div>
                                   <div class="col-12">
                                        <label for="workDescription1" class="form-label">Main activities and responsabilities</label>
                                        <textarea class="form-control" id="workDescription1" name="workDescription[]" rows="5"
                                             placeholder="e.g. -maintenance of computers &#10;-relations with suppliers &#10;-coaching a junior Ice Hockey team (10 hours/week)"></textarea>
                                   </div>
                              </div>
                         </div>

                         <!-- Buttons to manage multiple Work Experiences -->
                         <div class="mt-3">
                              <button type="button" class="btn btn-primary me-2" onclick="addWork()">Add Another Work</button>
                              <button type="button" class="btn btn-danger" onclick="removeWork()">Remove Last Work</button>
                         </div>

                         <div class="mt-4">
                              <button type="button" class="btn btn-secondary me-2" onclick="prevStep()">Back</button>
                              <button type="button" class="btn btn-primary" onclick="nextStep()">Next</button>
                         </div>
                    </div>

                    <!-- Step 4: Education (Up to 3) -->
                    <div class="form-step" data-step="4">
                         <div id="educationContainer">
                              <!-- Default Education Group 1 -->
                              <div class="row gy-3 gx-5 edu-group">
                                   <!-- Education ID (Hidden Field for Internal Use) -->
                                   <input type="hidden" id="educationId1" name="educationId[]" value="">

                                   <!-- CV ID (Hidden Field for Internal Use) -->
                                   <input type="hidden" id="cvId1" name="cvId[]" value="">

                                   <!-- Qualification -->
                                   <div class="col-md-6">
                                        <label for="qualification1" class="form-label">Qualification <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="qualification1" name="qualification[]" placeholder="e.g., Bachelor's" required>
                                   </div>

                                   <!-- Organisation -->
                                   <div class="col-md-6">
                                        <label for="organisation1" class="form-label">Institution Name <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="organisation1" name="organisation[]" placeholder="e.g., Purdue University" required>
                                   </div>

                                   <!-- Website -->
                                   <div class="col-md-6">
                                        <label for="website1" class="form-label">Institution Website (Optional)</label>
                                        <input type="url" class="form-control" id="website1" name="website[]" placeholder="e.g., https://www.purdue.edu" value="https://">
                                   </div>

                                   <!-- City -->
                                   <div class="col-md-6">
                                        <label for="city1" class="form-label">City <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="city1" name="city[]" placeholder="e.g., West Lafayette" required>
                                   </div>

                                   <!-- Country -->
                                   <div class="col-md-6">
                                        <label for="country1" class="form-label">Country <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="country1" name="country[]" placeholder="e.g., USA" required>
                                   </div>

                                   <!-- Start Date -->
                                   <div class="col-md-3">
                                        <label for="startDate1" class="form-label">Start Date <span class="text-danger">*</span></label>
                                        <input type="date" class="form-control" id="startDate1" name="startDate[]" required>
                                   </div>

                                   <!-- End Date -->
                                   <div class="col-md-3">
                                        <label for="endDate1" class="form-label">End Date</label>
                                        <input type="date" class="form-control" id="endDate1" name="endDate[]">
                                   </div>

                                   <!-- Ongoing -->
                                   <div class="col-md-6 d-flex justify-content-center align-items-center">
                                        <div class="form-check form-switch">
                                             <input class="form-check-input" type="checkbox" name="ongoing[]" role="switch" id="flexSwitchCheckDefault">
                                             <label class="form-check-label ms-2" for="flexSwitchCheckDefault">Ongoing</label>
                                        </div>
                                   </div>

                                   <!-- Field of Study -->
                                   <div class="col-md-6">
                                        <label for="fieldOfStudy1" class="form-label">Field of Study <span class="text-danger">*</span></label>
                                        <input type="text" class="form-control" id="fieldOfStudy1" name="fieldOfStudy[]" placeholder="e.g., Computer Science" required>
                                   </div>

                                   <!-- Final Grade -->
                                   <div class="col-md-6">
                                        <label for="finalGrade1" class="form-label">Final Grade (Optional)</label>
                                        <input type="text" class="form-control" id="finalGrade1" name="finalGrade[]" placeholder="e.g., 3.8 GPA">
                                   </div>

                                   <div class="col-12">
                                        <label for="skillsInput" class="form-label">Skills <span class="text-danger">*</span></label>
                                        <div id="skillsContainer" class="form-control" style="display: flex; flex-wrap: wrap; gap: 5px; min-height: 50px; align-items: center;">
                                             <!-- Skills will be dynamically added here -->
                                             <input id="skillsInput" type="text" placeholder="Add a skill and press Enter" style="border: none; outline: none; flex: 1;" />
                                        </div>
                                        <small class="form-text text-muted">Press Enter or comma to add a skill. Click on a skill to remove it.</small>
                                   </div>

                              </div>
                         </div>

                         <!-- Buttons to manage multiple Educations -->
                         <div class="mt-3">
                              <button type="button" class="btn btn-primary me-2" onclick="addEducation()">Add Another Education</button>
                              <button type="button" class="btn btn-danger" onclick="removeEducation()">Remove Last Education</button>
                         </div>

                         <div class="mt-4">
                              <button type="button" class="btn btn-secondary me-2" onclick="prevStep()">Back</button>
                              <button type="button" class="btn btn-primary" onclick="nextStep()">Next</button>
                         </div>
                    </div>

                    <!-- Step 5: Languages (Up to 5) -->
                    <div class="form-step" data-step="5">
                         <div id="languageContainer">
                              <!-- Language Group 1 (default) -->
                              <div class="row gy-3 gx-5 language-group">
                                   <div class="col-md-6">
                                        <label for="language1" class="form-label">
                                             Language <span class="text-danger">*</span>
                                        </label>
                                        <input
                                             type="text"
                                             class="form-control"
                                             id="language1"
                                             name="language[]"
                                             placeholder="e.g., English"
                                             required>
                                   </div>
                                   <div class="col-md-6">
                                        <label for="proficiency1" class="form-label">
                                             Proficiency <span class="text-danger">*</span>
                                        </label>
                                        <select class="form-select" id="proficiency1" name="proficiency[]" required>
                                             <option selected disabled value="">Choose...</option>
                                             <option value="Native">Native</option>
                                             <option value="Fluent">Fluent</option>
                                             <option value="Intermediate">Intermediate</option>
                                             <option value="Beginner">Beginner</option>
                                        </select>
                                   </div>
                              </div>
                         </div>

                         <!-- Buttons to manage multiple languages -->
                         <div class="mt-3">
                              <button type="button" class="btn btn-primary me-2" onclick="addLanguage()">Add Another Language</button>
                              <button type="button" class="btn btn-danger" onclick="removeLanguage()">Remove Last Language</button>
                         </div>

                         <div class="mt-4">
                              <button type="button" class="btn btn-secondary me-2" onclick="prevStep()">Back</button>
                              <button type="button" class="btn btn-primary" onclick="nextStep()">Next</button>
                         </div>
                    </div>

                    <!-- Step 6: Skills -->
                    <div class="form-step" data-step="6">
                         <div class="mb-3">
                              <label for="skills" class="form-label">
                                   Skills (Use bullet points or comma separated) <span class="text-danger">*</span>
                              </label>
                              <textarea
                                   class="form-control"
                                   id="skills"
                                   name="skills"
                                   rows="5"
                                   placeholder="- HTML/CSS\n- JavaScript\n- Team Leadership\n..."
                                   required></textarea>
                         </div>

                         <div class="mt-4">
                              <button type="button" class="btn btn-secondary me-2" onclick="prevStep()">Back</button>
                              <!-- Final form submission -->
                              <button type="submit" class="btn btn-success">Submit</button>
                         </div>
                    </div>
               </form>
          </div>
     </div>
     <?php include '../assets/footer.php'; ?>

     <!-- Step Wizard & Multi-Language Logic (Plain JS) -->
     <script>
         document.getElementById('profession').addEventListener('change', function () {
    if (this.value === 'Other') {
        const wrapper = document.getElementById('profession-wrapper');
        wrapper.innerHTML = `
            <label for="custom_profession" class="form-label">Please specify your profession</label>
            <input type="text" class="form-control" id="custom_profession" name="custom_profession" placeholder="e.g. Aerospace Engineer" required>
            <input type="hidden" name="personal_profession" value="Other">
        `;
    }
});
          const steps = document.querySelectorAll('.form-step');
          const stepIndicators = document.querySelectorAll('.step-indicator .step');
          let currentStep = 0; // index based, 0 -> step 1
          
          function changeCurrent(index){
              currentStep = index;
              showStep(currentStep);
          }

          function showStep(index) {
              console.log("HOla");
               // Hide all steps
               steps.forEach(step => step.classList.remove('active'));
               // Show current step
               steps[index].classList.add('active');

               // Update step indicator (active/completed)
               stepIndicators.forEach((stepEl, i) => {
                    stepEl.classList.remove('active', 'completed');
                    if (i < index) {
                         stepEl.classList.add('completed');
                    } else if (i === index) {
                         stepEl.classList.add('active');
                    }
               });
          }

          function nextStep() {
               // Attempt to validate the current step’s inputs before proceeding
               if (!validateStep(currentStep)) return;

               if (currentStep < steps.length - 1) {
                    currentStep++;
                    showStep(currentStep);
               }
          }

          function prevStep() {
               if (currentStep > 0) {
                    currentStep--;
                    showStep(currentStep);
               }
          }

          // Validate required fields in the current step
          function validateStep(stepIndex) {
               const currentFormStep = steps[stepIndex];
               const inputs = currentFormStep.querySelectorAll('input, select, textarea');
               for (let field of inputs) {
                    if (!field.checkValidity()) {
                         // Let browser show the native validation pop-up
                         field.reportValidity();
                         return false;
                    }
               }
               return true;
          }

          // Multi-language logic
          let languageCount = 1;
          const maxLanguages = 5;

          function addLanguage() {
               if (languageCount >= maxLanguages) return; // Limit to 5 languages
               languageCount++;
               const languageContainer = document.getElementById('languageContainer');

               // Create a new row wrapper
               const newRow = document.createElement('div');
               newRow.className = 'row gy-3 gx-5 language-group mt-3';

               // Column 1: Language
               const col1 = document.createElement('div');
               col1.className = 'col-md-6';
               const label1 = document.createElement('label');
               label1.className = 'form-label';
               label1.innerHTML = `Language <span class="text-danger">*</span>`;
               const input1 = document.createElement('input');
               input1.type = 'text';
               input1.className = 'form-control';
               input1.name = 'language[]';
               input1.placeholder = 'e.g., Spanish';
               input1.required = true;
               col1.appendChild(label1);
               col1.appendChild(input1);

               // Column 2: Proficiency
               const col2 = document.createElement('div');
               col2.className = 'col-md-6';
               const label2 = document.createElement('label');
               label2.className = 'form-label';
               label2.innerHTML = `Proficiency <span class="text-danger">*</span>`;
               const select2 = document.createElement('select');
               select2.className = 'form-select';
               select2.name = 'proficiency[]';
               select2.required = true;

               // Populate select options
               const defaultOption = document.createElement('option');
               defaultOption.value = '';
               defaultOption.disabled = true;
               defaultOption.selected = true;
               defaultOption.textContent = 'Choose...';
               select2.appendChild(defaultOption);

               ['Native', 'Fluent', 'Intermediate', 'Beginner'].forEach(level => {
                    const opt = document.createElement('option');
                    opt.value = level;
                    opt.textContent = level;
                    select2.appendChild(opt);
               });

               col2.appendChild(label2);
               col2.appendChild(select2);

               // Append columns to the row
               newRow.appendChild(col1);
               newRow.appendChild(col2);

               // Append the new row to container
               languageContainer.appendChild(newRow);
          }

          function removeLanguage() {
               if (languageCount <= 1) return; // Don't remove the initial language group
               const languageContainer = document.getElementById('languageContainer');
               const groups = languageContainer.querySelectorAll('.language-group');
               if (groups.length > 1) {
                    languageContainer.removeChild(groups[groups.length - 1]);
                    languageCount--;
               }
          }

          // Work Experience logic (Up to 3)
          let workCount = 1;
          const maxWork = 3;

          function addWork() {
               if (workCount >= maxWork) return; // Limit to 3 work experiences
               workCount++;
               const workContainer = document.getElementById('workExperienceContainer');

               // Create a new row wrapper
               const newRow = document.createElement('div');
               newRow.className = 'row gy-3 gx-5 work-group mt-3';

               //Line
               const col0 = document.createElement('div');
               col0.className = 'border border-secondary p-3';

               // jobTitle
               const col1 = document.createElement('div');
               col1.className = 'col-md-6';
               const label1 = document.createElement('label');
               label1.className = 'form-label';
               label1.innerHTML = `Job Title <span class="text-danger">*</span>`;
               const input1 = document.createElement('input');
               input1.type = 'text';
               input1.className = 'form-control';
               input1.name = 'jobTitle[]';
               input1.required = true;
               col1.appendChild(label1);
               col1.appendChild(input1);

               // employer
               const col2 = document.createElement('div');
               col2.className = 'col-md-6';
               const label2 = document.createElement('label');
               label2.className = 'form-label';
               label2.innerHTML = `Employer <span class="text-danger">*</span>`;
               const input2 = document.createElement('input');
               input2.type = 'text';
               input2.className = 'form-control';
               input2.name = 'employer[]';
               input2.required = true;
               col2.appendChild(label2);
               col2.appendChild(input2);

               // workCity
               const col3 = document.createElement('div');
               col3.className = 'col-md-6';
               const label3 = document.createElement('label');
               label3.className = 'form-label';
               label3.innerText = 'City';
               const input3 = document.createElement('input');
               input3.type = 'text';
               input3.className = 'form-control';
               input3.name = 'workCity[]';
               col3.appendChild(label3);
               col3.appendChild(input3);

               // workState
               const col4 = document.createElement('div');
               col4.className = 'col-md-6';
               const label4 = document.createElement('label');
               label4.className = 'form-label';
               label4.innerText = 'State';
               const input4 = document.createElement('input');
               input4.type = 'text';
               input4.className = 'form-control';
               input4.name = 'workState[]';
               col4.appendChild(label4);
               col4.appendChild(input4);



               // Start Month
               const col5 = document.createElement('div');
               col5.className = 'col-md-6';
               const label5 = document.createElement('label');
               label5.className = 'form-label';
               label5.innerHTML = `Start Month <span class="text-danger">*</span>`;
               const input5 = document.createElement('input');
               input5.type = 'text';
               input5.className = 'form-control';
               input5.name = 'startMonth[]';
               input5.placeholder = 'e.g., January';
               input5.required = true;
               col5.appendChild(label5);
               col5.appendChild(input5);

               // Start Year
               const col6 = document.createElement('div');
               col6.className = 'col-md-6';
               const label6 = document.createElement('label');
               label6.className = 'form-label';
               label6.innerHTML = `Start Year <span class="text-danger">*</span>`;
               const input6 = document.createElement('input');
               input6.type = 'number';
               input6.className = 'form-control';
               input6.name = 'startYear[]';
               input6.placeholder = 'e.g., 2020';
               input6.required = true;
               col6.appendChild(label6);
               col6.appendChild(input6);

               // End Month
               const col7 = document.createElement('div');
               col7.className = 'col-md-6';
               const label7 = document.createElement('label');
               label7.className = 'form-label';
               label7.innerHTML = `End Month <span class="text-danger">*</span>`;
               const input7 = document.createElement('input');
               input7.type = 'text';
               input7.className = 'form-control';
               input7.name = 'endMonth[]';
               input7.placeholder = 'e.g., December';
               input7.required = true;
               col7.appendChild(label7);
               col7.appendChild(input7);

               // End Year
               const col8 = document.createElement('div');
               col8.className = 'col-md-6';
               const label8 = document.createElement('label');
               label8.className = 'form-label';
               label8.innerHTML = `End Year <span class="text-danger">*</span>`;
               const input8 = document.createElement('input');
               input8.type = 'number';
               input8.className = 'form-control';
               input8.name = 'endYear[]';
               input8.placeholder = 'e.g., 2023';
               input8.required = true;
               col8.appendChild(label8);
               col8.appendChild(input8);

               // Description
               const col9 = document.createElement('div');
               col9.className = 'col-12';
               const label9 = document.createElement('label');
               label9.className = 'form-label';
               label9.innerText = 'Work description(Optional)';
               const textarea9 = document.createElement('textarea');
               textarea9.className = 'form-control';
               textarea9.name = 'workDescription[]';
               textarea9.rows = 2;
               col9.appendChild(label9);
               col9.appendChild(textarea9);


               // Append columns to the newRow
               newRow.appendChild(col0);
               newRow.appendChild(col1);
               newRow.appendChild(col2);
               newRow.appendChild(col3);
               newRow.appendChild(col4);
               newRow.appendChild(col5);
               newRow.appendChild(col6);
               newRow.appendChild(col7);
               newRow.appendChild(col8);
               newRow.appendChild(col9);

               // Append the new row to container
               workContainer.appendChild(newRow);
          }

          function removeWork() {
               if (workCount <= 1) return; // Don't remove the initial group
               const workContainer = document.getElementById('workExperienceContainer');
               const groups = workContainer.querySelectorAll('.work-group');
               if (groups.length > 1) {
                    workContainer.removeChild(groups[groups.length - 1]);
                    workCount--;
               }
          }

          // Education logic (Up to 3)
          let eduCount = 1;
          const maxEdu = 3;

          function addEducation() {
               if (eduCount >= maxEdu) return; // Limit to 3 educations
               eduCount++;
               const eduContainer = document.getElementById('educationContainer');

               const newRow = document.createElement('div');
               newRow.className = 'row gy-3 gx-5 edu-group mt-3';

               // Education ID (Hidden Field)
               const eduIdInput = document.createElement('input');
               eduIdInput.type = 'hidden';
               eduIdInput.id = `educationId${eduCount}`;
               eduIdInput.name = 'educationId[]';
               eduIdInput.value = '';

               // CV ID (Hidden Field)
               const cvIdInput = document.createElement('input');
               cvIdInput.type = 'hidden';
               cvIdInput.id = `cvId${eduCount}`;
               cvIdInput.name = 'cvId[]';
               cvIdInput.value = '';

               // Qualification
               const col1 = createFormField('col-md-6', `qualification${eduCount}`, 'Qualification <span class="text-danger">*</span>', 'text', 'qualification[]', "e.g., Bachelor's", true);

               // Organisation
               const col2 = createFormField('col-md-6', `organisation${eduCount}`, 'Institution Name <span class="text-danger">*</span>', 'text', 'organisation[]', 'e.g., Purdue University', true);

               // Website
               const col3 = createFormField('col-md-6', `website${eduCount}`, 'Institution Website (Optional)', 'url', 'website[]', 'e.g., https://www.purdue.edu', false, 'https://');

               // City
               const col4 = createFormField('col-md-6', `city${eduCount}`, 'City <span class="text-danger">*</span>', 'text', 'city[]', 'e.g., West Lafayette', true);

               // Country
               const col5 = createFormField('col-md-6', `country${eduCount}`, 'Country <span class="text-danger">*</span>', 'text', 'country[]', 'e.g., USA', true);

               // Start Date
               const col6 = createFormField('col-md-6', `startDate${eduCount}`, 'Start Date <span class="text-danger">*</span>', 'date', 'startDate[]', '', true);

               // End Date
               const col7 = createFormField('col-md-6', `endDate${eduCount}`, 'End Date', 'date', 'endDate[]', '', false);

               // Ongoing Checkbox
               const col8 = document.createElement('div');
               col8.className = 'col-md-6 d-flex justify-content-center align-items-center';
               const ongoingDiv = document.createElement('div');
               ongoingDiv.className = 'form-check form-switch';
               const ongoingInput = document.createElement('input');
               ongoingInput.className = 'form-check-input';
               ongoingInput.type = 'checkbox';
               ongoingInput.name = 'ongoing[]';
               ongoingInput.id = `ongoing${eduCount}`;
               ongoingInput.setAttribute('role', 'switch');
               const ongoingLabel = document.createElement('label');
               ongoingLabel.className = 'form-check-label ms-2';
               ongoingLabel.htmlFor = `ongoing${eduCount}`;
               ongoingLabel.innerText = 'Ongoing';
               ongoingDiv.appendChild(ongoingInput);
               ongoingDiv.appendChild(ongoingLabel);
               col8.appendChild(ongoingDiv);

               // Field of Study
               const col9 = createFormField('col-md-6', `fieldOfStudy${eduCount}`, 'Field of Study <span class="text-danger">*</span>', 'text', 'fieldOfStudy[]', 'e.g., Computer Science', true);

               // Final Grade
               const col10 = createFormField('col-md-6', `finalGrade${eduCount}`, 'Final Grade (Optional)', 'text', 'finalGrade[]', 'e.g., 3.8 GPA', false);

               // Skills Input
               const col11 = document.createElement('div');
               col11.className = 'col-12';
               const label11 = document.createElement('label');
               label11.className = 'form-label';
               label11.innerHTML = 'Skills <span class="text-danger">*</span>';
               const skillsContainer = document.createElement('div');
               skillsContainer.id = `skillsContainer${eduCount}`;
               skillsContainer.className = 'form-control';
               skillsContainer.style = 'display: flex; flex-wrap: wrap; gap: 5px; min-height: 50px; align-items: center;';
               const skillsInput = document.createElement('input');
               skillsInput.id = `skillsInput${eduCount}`;
               skillsInput.type = 'text';
               skillsInput.placeholder = 'Add a skill and press Enter';
               skillsInput.style = 'border: none; outline: none; flex: 1;';
               skillsContainer.appendChild(skillsInput);
               const helpText11 = document.createElement('small');
               helpText11.className = 'form-text text-muted';
               helpText11.innerText = 'Press Enter or comma to add a skill. Click on a skill to remove it.';
               col11.appendChild(label11);
               col11.appendChild(skillsContainer);
               col11.appendChild(helpText11);

               //Line
               const col0 = document.createElement('hr');
               col0.className = 'border border-secondary';

               // Append fields to the newRow
               newRow.appendChild(col0);
               newRow.appendChild(eduIdInput);
               newRow.appendChild(cvIdInput);
               newRow.appendChild(col1);
               newRow.appendChild(col2);
               newRow.appendChild(col3);
               newRow.appendChild(col4);
               newRow.appendChild(col5);
               newRow.appendChild(col6);
               newRow.appendChild(col7);
               newRow.appendChild(col8);
               newRow.appendChild(col9);
               newRow.appendChild(col10);
               newRow.appendChild(col11);

               // Append the new row to the container
               eduContainer.appendChild(newRow);
          }

          // Utility function to create form fields
          function createFormField(colClass, id, labelHTML, inputType, inputName, placeholder, isRequired, defaultValue = '') {
               const col = document.createElement('div');
               col.className = colClass;
               const label = document.createElement('label');
               label.className = 'form-label';
               label.htmlFor = id;
               label.innerHTML = labelHTML;
               const input = document.createElement('input');
               input.type = inputType;
               input.className = 'form-control';
               input.id = id;
               input.name = inputName;
               input.placeholder = placeholder;
               input.value = defaultValue;
               if (isRequired) input.required = true;
               col.appendChild(label);
               col.appendChild(input);
               return col;
          }

          function removeEducation() {
               if (eduCount <= 1) return; // Prevent removing the initial group
               const eduContainer = document.getElementById('educationContainer');
               const groups = eduContainer.querySelectorAll('.edu-group');
               if (groups.length > 1) {
                    eduContainer.removeChild(groups[groups.length - 1]);
                    eduCount--;
               }
          }

          // Initialize the first step
          showStep(currentStep);

          // On final submission, the form goes to viewhtml.php via POST
          const resumeForm = document.getElementById('resumeForm');
          resumeForm.addEventListener('submit', (e) => {
               // If there's invalid fields on the last step, we prevent the default
               if (!validateStep(currentStep)) {
                    e.preventDefault();
               }
          });
          document.addEventListener('DOMContentLoaded', function() {
               const skillsContainer = document.getElementById('skillsContainer');
               const skillsInput = document.getElementById('skillsInput');

               // Store added skills
               let skills = [];

               // Function to render skills
               function renderSkills() {
                    // Clear current skills
                    skillsContainer.querySelectorAll('.skill-tag').forEach(tag => tag.remove());

                    // Add each skill as a tag
                    skills.forEach((skill, index) => {
                         const skillTag = document.createElement('div');
                         skillTag.className = 'skill-tag rounded';
                         skillTag.textContent = skill;

                         // Add a small "x" to remove skill
                         const removeIcon = document.createElement('i');
                         removeIcon.className = 'bi bi-x';
                         removeIcon.onclick = () => {
                              skills.splice(index, 1);
                              renderSkills();
                         };

                         skillTag.appendChild(removeIcon);
                         skillsContainer.insertBefore(skillTag, skillsInput);
                    });
               }

               // Add skill on Enter or comma
               skillsInput.addEventListener('keydown', function(event) {
                    if (event.key === 'Enter' || event.key === ',') {
                         event.preventDefault();
                         const skill = skillsInput.value.trim();
                         if (skill && !skills.includes(skill)) {
                              skills.push(skill);
                              skillsInput.value = '';
                              renderSkills();
                         }
                    }
               });
          });
     </script>

     <?php include 'includes/footer.php'; ?>
</body>

</html>