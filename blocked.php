<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 Not Found</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            text-align: center;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 600px;
            width: 90%;
            padding: 40px 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            font-size: 4em;
            margin: 0;
            color: #e74c3c;
        }
        p {
            font-size: 1.2em;
            color: #666;
            margin: 20px 0;
        }
        @media (max-width: 480px) {
            h1 {
                font-size: 3em;
            }
            p {
                font-size: 1em;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>404</h1>
        <p>Oops! The page you're looking for doesn't exist.</p>
        <p>Redirecting to Google in <span id="countdown">5</span> seconds...</p>
    </div>

    <script>
        let seconds = 5;
        const countdownElement = document.getElementById('countdown');
        
        const countdown = setInterval(() => {
            seconds--;
            countdownElement.textContent = seconds;
            
            if (seconds <= 0) {
                clearInterval(countdown);
                window.location.href = 'https://www.google.com';
            }
        }, 1000);
    </script>
</body>
</html>
