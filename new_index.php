<?php 
include "assets/head.php";

?>
<head>
  <style>
    :root {
      --primary: #111;
      --secondary: #555;
      --accent: #007bff;
    }

    body {
      background-color: #fff;
      color: var(--primary);
      font-family: 'Segoe UI', sans-serif;
    }

    .resume-card {
      border-radius: 20px;
      box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
      padding: 2rem;
      background: #fff;
      transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .resume-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 20px 30px rgba(0, 0, 0, 0.2);
    }

    .section-title {
      font-size: 1.8rem;
      font-weight: 700;
      margin-bottom: 1rem;
      border-left: 5px solid var(--accent);
      padding-left: 1rem;
    }

    .interactive-icon {
      transition: transform 0.3s ease;
    }

    .interactive-icon:hover {
      transform: scale(1.2);
      color: var(--accent);
    }

    .btn-primary {
      background-color: var(--accent);
      border: none;
      padding: 10px 20px;
      font-weight: bold;
      border-radius: 999px;
      transition: background-color 0.3s ease, transform 0.3s ease;
    }

    .btn-primary:hover {
      background-color: #0056b3;
      transform: scale(1.05);
    }

    .fade-in {
      animation: fadeInUp 1s ease forwards;
      opacity: 0;
    }

    @keyframes fadeInUp {
      from {
        transform: translateY(30px);
        opacity: 0;
      }
      to {
        transform: translateY(0);
        opacity: 1;
      }
    }
  </style>
</head>
<body>
  <div class="container py-5">
    <div class="text-center mb-5">
      <h1 class="display-4 fade-in"><?= htmlspecialchars($personalinfo["personal_name"] . ' ' . $personalinfo["personal_lastname"]) ?></h1>
      <h2 class="text-muted fade-in"><?= htmlspecialchars($personalinfo["personal_profession"]) ?></h2>
    </div>

    <div class="row g-4">
      <div class="col-md-4">
        <div class="resume-card fade-in">
          <img src="images/<?= htmlspecialchars($personalinfo['personal_photo']) ?>" class="img-fluid rounded-circle mb-3" alt="Profile Picture">
          <h3 class="section-title">Contact</h3>
          <?php if (!empty($contactinfo["email"])): ?>
            <p><a href="mailto:<?= htmlspecialchars($contactinfo["email"]) ?>" class="text-decoration-none text-dark">📧 <?= htmlspecialchars($contactinfo["email"]) ?></a></p>
          <?php endif; ?>
          <?php if (!empty($contactinfo["phone_number"])): ?>
            <p>📞 <?= htmlspecialchars($contactinfo["phone_number"]) ?></p>
          <?php endif; ?>
          <!-- Add other contact icons here -->
        </div>
      </div>
      <div class="col-md-8">
        <div class="resume-card fade-in">
          <h3 class="section-title">About Me</h3>
          <p><?= nl2br(htmlspecialchars($personalinfo["personal_bio"])) ?></p>
        </div>
      </div>
    </div>

    <div class="row g-4 mt-4">
      <div class="col-md-6">
        <div class="resume-card fade-in">
          <h3 class="section-title">Experience</h3>
          <?php foreach ($results["experience"] as $exp): ?>
            <div class="mb-3">
              <h5><?= htmlspecialchars($exp["job_name"]) ?> at <?= htmlspecialchars($exp["place_of_work"]) ?></h5>
              <small class="text-muted"><?= htmlspecialchars($exp["date"]) ?></small>
              <p><?= htmlspecialchars($exp["brief_description"]) ?></p>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="col-md-6">
        <div class="resume-card fade-in">
          <h3 class="section-title">Education</h3>
          <?php foreach ($results["education"] as $edu): ?>
            <div class="mb-3">
              <h5><?= htmlspecialchars($edu["name_of_studies"]) ?> at <?= htmlspecialchars($edu["place_of_study"]) ?></h5>
              <small class="text-muted"><?= htmlspecialchars($edu["date"]) ?></small>
              <p><?= htmlspecialchars($edu["brief_description"]) ?></p>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</body>
