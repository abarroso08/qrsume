<?php
include("head.php");
?>
<head>
    <title>QRSume Pricing Plans</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #0F172A;
            color: white;
            text-align: center;
            margin: 0;
            padding: 0;
        }

        .pricing-container {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 20px;
            padding: 50px 20px;
        }

        .pricing-card {
            background: linear-gradient(135deg, #1E293B, #334155);
            border-radius: 10px;
            width: 280px;
            text-align: left;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.4);
            transition: transform 0.3s ease-in-out;
            overflow: hidden;
        }

        .pricing-card:hover {
            transform: scale(1.05);
            box-shadow: 0 6px 15px rgba(0, 0, 0, 0.6);
        }

        .pricing-header {
            font-size: 22px;
            font-weight: bold;
            padding: 15px;
            text-align: center;
            color: white;
        }

        .intern-header { background: #16A34A; }
        .junior-header { background: #3B82F6; }
        .senior-header { background: #9333EA; }

        .pricing-price {
            font-size: 24px;
            font-weight: bold;
            text-align: center;
            padding: 15px;
            background: rgba(255, 255, 255, 0.1);
            border-bottom: 1px solid rgba(255, 255, 255, 0.3);
        }

        .pricing-content {
            padding: 20px;
            background: transparent;
        }

        .divider {
            border-top: 1px dashed rgba(255, 255, 255, 0.5);
            margin: 15px 0;
        }

        .pricing-features {
            list-style: none;
            padding: 0;
        }

        .pricing-features li {
            padding: 8px 0;
            font-size: 14px;
            text-align: left;
            display: flex;
            align-items: center;
        }

        .pricing-features li::before {
            content: "•";
            color: #38BDF8;
            font-weight: bold;
            padding-right: 8px;
        }

        .previous-plan {
            font-style: italic;
            opacity: 0.8;
            text-align: center;
            padding: 8px 0;
        }

        .pricing-button {
            display: block;
            text-align: center;
            background: #38BDF8;
            color: black;
            padding: 10px;
            margin-top: 15px;
            border-radius: 5px;
            text-decoration: none;
            font-weight: bold;
            transition: background 0.3s;
        }

        .pricing-button:hover {
            background: #0284C7;
        }

        @media (max-width: 768px) {
            .pricing-container {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <?php include("nav2.php");?>
    <h1>INNOVATE YOUR CAREER</h1>
    <div class="pricing-container">

        <div class="pricing-card">
            <div class="pricing-header intern-header">Intern</div>
            <div class="pricing-price">FREE</div>
            <div class="pricing-content">
                <ul class="pricing-features">
                    <li><b>PDF Resume</b></li>
                    <li><b>Personal Webpage</b></li>
                    <li><b>QR Code in Resume</b></li>
                    <li><b>Small Watermark</b></li>
                    <li><b>Up to 2 Articles</b></li>
                </ul>
                <div class="divider"></div>
                <a href="#" class="pricing-button">Select Plan</a>
            </div>
        </div>

        <div class="pricing-card">
            <div class="pricing-header junior-header">Junior</div>
            <div class="pricing-price">$1.99 (One-time)</div>
            <div class="pricing-content">
                <ul class="pricing-features">
                    <li><b>+1 Premium Template</b></li>
                    <li><b>Colour Theme Change</b></li>
                    <li><b>Watermark Removal</b></li>
                    <li><b>Up to 4 Articles</b></li>
                    <li><b>QRemove Feature (you can remove the QR)</b></li>
                    <div class="previous-plan"><em>Includes all Intern Plan features</em></div>
                </ul>
                <div class="divider"></div>
                <a href="#" class="pricing-button">Select Plan</a>
                
            </div>
        </div>

        <div class="pricing-card">
            <div class="pricing-header senior-header">Senior</div>
            <div class="pricing-price">$4.99/month (Subscription)</div>
            <div class="pricing-content">
                <ul class="pricing-features">
                    <li><b>Access to All Premium Templates</b></li>
                    <li><b>Unlimited Articles</b></li>
                    <li><b>Access to Website Analytics</b></li>
                    <div class="previous-plan"><em>Includes all Junior Plan features</em></div>
                </ul>
                <div class="divider"></div>
                <a href="#" class="pricing-button">Select Plan</a>
              
            </div>
        </div>

    </div>

    <h2>🚀 Choose your plan and get started now at <a href="https://www.qrsume.com" style="color: #38BDF8; text-decoration: none;">www.qrsume.com</a></h2>

<?php include("footer.php")?>
</body>
</html>