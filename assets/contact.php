<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - QRSume</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
        }
        .contact-section {
            background: #ffffff;
            padding: 40px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            width: 100%;
            text-align: center;
        }
        .contact-section h1 {
            color: #333;
        }
        .contact-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }
        .contact-form label {
            text-align: left;
            font-weight: bold;
        }
        .contact-form input,
        .contact-form textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 5px;
        }
        .contact-form button {
            background-color: #007bff;
            color: white;
            border: none;
            padding: 10px;
            border-radius: 5px;
            cursor: pointer;
            transition: background 0.3s;
        }
        .contact-form button:hover {
            background-color: #0056b3;
        }
        .contact-info {
            margin-top: 20px;
            text-align: center;
        }
        .contact-info h2 {
            color: #333;
        }
        .contact-info p {
            margin: 5px 0;
        }
        .contact-info a {
            color: #007bff;
            text-decoration: none;
            transition: color 0.3s;
        }
        .contact-info a:hover {
            color: #0056b3;
        }
    </style>
</head>
<body>
    <section class="contact-section">
        <h1>Contact Us</h1>
        <p class="contact-subtext">Have questions? We're here to help! Reach out through the form below or connect with us on social media. We appreciate your feedback—good things take time. ✨</p>
        
        <form action="contact_process.php" method="post" class="contact-form">
            <label for="name">Name:</label>
            <input type="text" id="name" name="name" required>
            
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" required>
            
            <label for="message">Message:</label>
            <textarea id="message" name="message" rows="5" required></textarea>
            
            <button type="submit">Send Message</button>
        </form>
        
        <div class="contact-info">
            <h2>QRsume</h2>
            <p>Email: info@qrsume.com</p>
          
            
            <h2>Follow Us</h2>
            <p>
                <a href="#">Twitter</a> | <a href="#">LinkedIn</a> | <a href="#">Instagram</a>
            </p>
        </div>
    </section>
</body>
</html>