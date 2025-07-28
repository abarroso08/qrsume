<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Subscribe to QRsume</title>
    <script src="https://www.paypal.com/sdk/js?client-id=YOUR_CLIENT_ID&vault=true&intent=subscription"></script>
</head>
<body>
    <h1>Choose Your Subscription Plan</h1>

    <div>
        <button onclick="startSubscription('basic')">Basic - $5/month</button>
        <button onclick="startSubscription('pro')">Pro - $10/month</button>
        <button onclick="startSubscription('premium')">Premium - $20/month</button>
    </div>

    <div id="paypal-button-container"></div>

    <script>
        let selectedPlan = '';

        function startSubscription(plan) {
            selectedPlan = plan;
            document.getElementById('paypal-button-container').innerHTML = '';

            paypal.Buttons({
                createSubscription: function(data, actions) {
                    let planId = '';
                    if (selectedPlan === 'basic') planId = "BASIC_PLAN_ID";
                    if (selectedPlan === 'pro') planId = "PRO_PLAN_ID";
                    if (selectedPlan === 'premium') planId = "PREMIUM_PLAN_ID";

                    return actions.subscription.create({ plan_id: planId });
                },
                onApprove: function(data, actions) {
                    fetch('process_subscription.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ subscriptionID: data.subscriptionID, plan: selectedPlan })
                    })
                    .then(response => response.json())
                    .then(result => alert(result.message));
                }
            }).render('#paypal-button-container');
        }
    </script>
</body>
</html>
